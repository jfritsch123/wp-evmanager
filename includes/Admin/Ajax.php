<?php
namespace WP_EvManager\Admin;

defined('ABSPATH') || exit;

use WP_EvManager\Database\Repositories\EventRepository;
use WP_EvManager\Database\Repositories\HistoryRepository;
use WP_EvManager\Security\Permissions;

class Ajax
{
    private const STATUS_ALLOWED = [
        'Anfrage erhalten',
        'In Bearbeitung',
        'Gebucht',
        'Vereinbarung unterzeichnet',
    ];

    private const PUBLISH_ALLOWED = ['0','1','2','3'];

    public function hooks(): void
    {
        add_action('wp_ajax_wpem_list_events',  [$this, 'list_events']);
        add_action('wp_ajax_wpem_get_event',    [$this, 'get_event']);
        add_action('wp_ajax_wpem_save_event',   [$this, 'save_event']);
        add_action('wp_ajax_wpem_delete_event', [$this, 'delete_event']);

        add_action('wp_ajax_wpem_tooltip_dayinfo', [$this ,'tooltip_dayinfo']);
        add_action('wp_ajax_wpem_reload_daymap', [$this, 'reload_daymap']);
        add_action('wp_ajax_wpem_get_request',[$this, 'get_request']);
        add_action('wp_ajax_wpem_get_history', [$this, 'get_history']);


    }

    private static function sanitize_publish($v): string {
        $v = is_scalar($v) ? (string)$v : '0';
        return in_array($v, self::PUBLISH_ALLOWED, true) ? $v : '0';
    }

    /**
     * Sanitize status coming from the editor.
     * Since the UI is now a single-choice radio, we accept a scalar or comma list
     * and return an array with at most ONE allowed value. If nothing valid was sent,
     * we fall back to "In Bearbeitung" for new records and keep existing value for updates.
     *
     * @param mixed $raw
     * @param int   $id  Event ID (0 for create)
     * @return string[]  One-element array (or empty for update fallback)
     */
    private static function sanitize_status_array($raw, int $id = 0): array {
        // Normalize to array of strings
        $vals = is_array($raw) ? $raw : (is_scalar($raw) ? explode(',', (string)$raw) : []);
        $vals = array_map('sanitize_text_field', $vals);

        // Keep only allowed values and deduplicate
        $vals = array_values(array_unique(array_intersect($vals, self::STATUS_ALLOWED)));

        // Enforce single-choice: take the first allowed value if provided
        if (!empty($vals)) {
            return [ $vals[0] ];
        }

        // No valid value provided
        if ($id === 0) {
            // On create, default to "In Bearbeitung"
            return ['In Bearbeitung'];
        }

        // On update without a value (should not normally happen), keep current DB value
        $repo = new EventRepository();
        $current = $repo->getStatusFromId($id);
        if (!empty($current)) {
            // Ensure we still return only one allowed value
            $curAllowed = array_values(array_intersect($current, self::STATUS_ALLOWED));
            if (!empty($curAllowed)) {
                return [ $curAllowed[0] ];
            }
        }
        // Fallback
        return ['In Bearbeitung'];
    }

    private function check_nonce(): void
    {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'wpem_ajax')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-evmanager')], 403);
        }
    }

    public function list_events(): void
    {
        $this->check_nonce();
        if (!Permissions::can_read()) {
            wp_send_json_error(['message' => __('Not allowed', 'wp-evmanager')], 403);
        }

        $repo  = new EventRepository();
        $page  = max(1, (int)($_POST['page'] ?? 1));
        $pp    = max(1, min(100, (int)($_POST['per_page'] ?? 20)));
        $off   = ($page - 1) * $pp;

        // Arrays sicher einsammeln
        $arr = function(string $key): array {
            $v = $_POST[$key] ?? [];
            if (!is_array($v)) return [];
            return array_values(array_map('sanitize_text_field', $v));
        };

        // Sortierung
        $order_by = isset($_POST['order_by']) ? (string)$_POST['order_by'] : 'fromdate';
        if (isset($_POST['order_dir'])) {
            $order_dir = (string)$_POST['order_dir'];
        } else {
            // Dynamische Standardrichtung
            switch ($order_by) {
                case 'fromdate':
                    $order_dir = 'DESC';
                    break;
                case 'ts':
                    $order_dir = 'ASC';
                    break;
                default:
                    $order_dir = 'DESC';
            }
        }

        $args = [
            'q'            => isset($_POST['q']) ? (string)$_POST['q'] : '',
            'editor'       => isset($_POST['editor']) ? sanitize_text_field((string)$_POST['editor']) : '',
            'status'       => $arr('status'),
            'fromdate_min' => isset($_POST['fromdate_min']) ? sanitize_text_field((string)$_POST['fromdate_min']) : '',
            'fromdate_max' => isset($_POST['fromdate_max']) ? sanitize_text_field((string)$_POST['fromdate_max']) : '',
            'booked_only'  => !empty($_POST['booked_only']) ? 1 : 0,

            // NEU: Radios → einzelne Strings '0'|'1'|'2' oder ''
            'place1'       => isset($_POST['place1']) ? sanitize_text_field((string)$_POST['place1']) : '',
            'place2'       => isset($_POST['place2']) ? sanitize_text_field((string)$_POST['place2']) : '',
            'place3'       => isset($_POST['place3']) ? sanitize_text_field((string)$_POST['place3']) : '',

            'order_by'     => $order_by,
            'order_dir'    => $order_dir,
            'limit'        => $pp,
            'offset'       => $off,
        ];

        [$items, $total]  = $repo->find($args);

        $current_user = wp_get_current_user()->user_login;
        // Berechtigungen ergänzen
        foreach ($items as &$event) {
            // Default: nur lesbar
            $event['editable'] = false;

            if (Permissions::can_edit_all()) {
                $event['editable'] = true;
            } elseif (Permissions::can_edit_own($current_user) && $event['editor'] === $current_user) {
                $event['editable'] = true;
            }
        }
        unset($event);

        wp_send_json_success([
            'items'       => $items,
            'total'       => (int)$total,
            'page'        => $page,
            'per_page'    => $pp,
            'total_pages' => max(1, (int)ceil($total / $pp)),
            'args'        => $args,
        ]);

    }

    /**
     * Handles fetching and returning event data in a JSON response.
     * Validates nonce, checks user permissions, retrieves event data by ID,
     * and handles related events based on date if applicable.
     * Sends appropriate JSON success or error responses based on access
     * and data availability conditions.
     *
     * @return void
     */
    public function get_event(): void
    {
        $this->check_nonce();
        if (!Permissions::can_read()) {
            wp_send_json_error(['message' => __('Not allowed', 'wp-evmanager')], 403);
        }

        $id   = max(0, (int)($_POST['id'] ?? 0));
        $repo = new EventRepository();

        if ($id === 0) {
            wp_send_json_success(['event' => null]); // „Neu“-Formular
        }

        $row = $repo->get($id);
        if (!$row) {
            wp_send_json_error(['message' => __('Event not found', 'wp-evmanager')], 404);
        }
        if (!Permissions::can_edit_all() && !Permissions::can_edit_own((string)$row['editor'])) {
            wp_send_json_error(['message' => __('Not allowed to view this event', 'wp-evmanager')], 403);
        }

        // Normalize status spelling for UI (DB may contain a legacy misspelling)
        $from = $row['fromdate'] ?? null;
        $to   = $row['todate'] ?? null;
        if (!$from) $from = date('Y-m-d');
        if (!$to || $to === '0000-00-00' || $to < $from) $to = $from;

        $more = $repo->findByDateToDate($from);

        /*
        // Wenn eintägig → Day-Query, sonst Range-Query
        if ($from === $to) {
            $more = $repo->findByRange($from,$from);
        } else {
            $more = $repo->findByRange($from, $to);
        }
        */

        //$event['moreEvents'] = $more;
        //wp_send_json_success(['event' => $event]);
        $row['dayEvents'] = $more;
        wp_send_json_success([
            'event' => $row,
        ]);
    }

    public function save_event(): void
    {
        $this->check_nonce();

        $id   = max(0, (int)($_POST['id'] ?? 0));
        $repo = new EventRepository();
        $statusValues = self::sanitize_status_array($_POST['status'] ?? [],$id);
        // Build status string and map to DB legacy spelling if necessary
        $statusStr = $statusValues ? implode(',', $statusValues) : null;

        // WYSIWYG-Felder via wp_kses_post erlauben
        $payload = [
            'title'        => sanitize_text_field($_POST['title'] ?? ''),
            'type'         => sanitize_text_field($_POST['type'] ?? ''),
            'short'        => sanitize_text_field($_POST['short'] ?? ''),
            'fromdate'     => sanitize_text_field($_POST['fromdate'] ?? ''),
            'fromtime'     => sanitize_text_field($_POST['fromtime'] ?? ''),
            'todate'       => sanitize_text_field($_POST['todate'] ?? ''),
            'totime'       => sanitize_text_field($_POST['totime'] ?? ''),
            'descr1'       => wp_kses_post($_POST['descr1'] ?? ''), // WYSIWYG
            'descr2'       => wp_kses_post($_POST['descr2'] ?? ''), // WYSIWYG
            'descr3'       => sanitize_textarea_field($_POST['descr3'] ?? ''),
            'persons'      => (int)($_POST['persons'] ?? 0),
            'organizer'    => sanitize_text_field($_POST['organizer'] ?? ''),
            'organization' => sanitize_text_field($_POST['organization'] ?? ''),
            'email'        => sanitize_email($_POST['email'] ?? ''),
            'tel'          => sanitize_text_field($_POST['tel'] ?? ''),
            'picture'      => esc_url_raw($_POST['picture'] ?? ''),
            'publish'      => self::sanitize_publish($_POST['publish'] ?? '0'),
            'booked'       => (int)($_POST['booked'] ?? 0),
            'status'       => $statusStr,
            'place1'       => self::sanitize_place($_POST['place1'] ?? '0'),
            'place2'       => self::sanitize_place($_POST['place2'] ?? '0'),
            'place3'       => self::sanitize_place($_POST['place3'] ?? '0'),
            'note'         => wp_kses_post($_POST['note'] ?? ''),
            'processed'    => sanitize_text_field($_POST['processed'] ?? ''),
            'informed'     => sanitize_text_field($_POST['informed'] ?? ''),
            'soldout'      => sanitize_text_field($_POST['soldout'] ?? ''),
        ];

        if ($id > 0) {
            $ok = $repo->update_if_permitted($id, $payload, Permissions::can_edit_all());
            if (!$ok) wp_send_json_error(['message' => __('Could not save event', 'wp-evmanager')], 500);
            wp_send_json_success(['id' => $id, 'updated' => true,'sent' => print_r($_POST, true)]);
        } else {
            if (!Permissions::can_create()) {
                wp_send_json_error(['message' => __('Not allowed to create events', 'wp-evmanager')], 403);
            }
            $new_id = $repo->insert($payload);
            if (!$new_id) wp_send_json_error(['message' => __('Could not create event', 'wp-evmanager')], 500);
            wp_send_json_success(['id' => (int)$new_id, 'created' => true], 201);
        }
    }

    public function delete_event(): void
    {
        $this->check_nonce();
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($id <= 0) wp_send_json_error(['message' => __('Invalid ID', 'wp-evmanager')], 400);

        $repo = new EventRepository();
        $ok   = $repo->delete_if_permitted($id, Permissions::can_delete_all());
        if (!$ok) wp_send_json_error(['message' => __('Could not delete event', 'wp-evmanager')], 500);

        wp_send_json_success(['id' => $id, 'deleted' => true]);
    }

    private static function sanitize_place(string $v): string
    {
        $v = is_scalar($v) ? (string) $v : '0';
        return in_array($v, ['0', '1', '2'], true) ? $v : '0';
    }

    public function tooltip_dayinfo(): void
    {
        check_ajax_referer('wpem_ajax', 'nonce');
        if (empty($_POST['day'])) {
            wp_send_json_error('Missing date');
        }
        $repo = new EventRepository();
        $day = sanitize_text_field((string)$_POST['day']);
        $data = $repo->findByDateToDate($day);
        wp_send_json_success(['items' => $data]);
    }

    public function reload_daymap(): void
    {
        check_ajax_referer('wpem_ajax', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error('Not allowed');
        }

        // Generiere aktuelle Belegungs-Map (z. B. wie im AdminMenu beim Localize)
        $repo   = new EventRepository();
        $today  = date('Y-m-d'); //new \DateTimeImmutable('today');   // << hier das heutige Datum
        $dayMap = $repo->getDayMapSince($today);

        wp_send_json_success([
            'calendarDays' => $dayMap,
            'today'        => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    public function get_request(): void
    {
        if (empty($_POST['entry_id'])) {
            wp_send_json_error('Missing entry_id');
        }

        $entry_id = (int) $_POST['entry_id'];
        $log_id = (int) $_POST['log_id'];
        if (!function_exists('wpforms')) {
            wp_send_json_error('WPForms not available');
        }

        $entry = wpforms()->entry->get($entry_id);

        if (!$entry) {
            wp_send_json_error('Entry not found');
        }

        // Fields JSON in ein Array verwandeln
        $fields = json_decode($entry->fields, true);

        $data = [
            'entry_id' => $entry->entry_id,
            'form_id'  => $entry->form_id,
            'date'     => $entry->date,
            'ip'       => $entry->ip_address,
            'user'     => $entry->user_id,
            'fields'   => [],
        ];

        foreach ($fields as $field) {
            $data['fields'][] = [
                'label' => $field['name'] ?? ('Feld '.$field['id']),
                'value' => $field['value'] ?? '',
            ];
        }

        //$log = \WP_EvManager\Database\DBTools::get_request_log($log_id);
        //$log_data = $log['data'] ?? '';

        //wp_send_json_success(['entry_data' => $data, 'log_data' => $log_data]);
        wp_send_json_success(['entry_data' => $data]);
    }

    public function get_history(): void
    {

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ($event_id <= 0) {
            wp_send_json_error('Invalid ID');
        }

        $repo = new HistoryRepository();
        $history = $repo->get_history($event_id, 20);
        wp_send_json_success($history);
    }

}
