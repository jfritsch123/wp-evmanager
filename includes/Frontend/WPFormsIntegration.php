<?php
namespace WP_EvManager\Frontend;

defined('ABSPATH') || exit;

use WP_EvManager\Database\Repositories\EventRepository;
use WP_EvManager\Services\CalendarDataBuilder;

final class WPFormsIntegration {

    private int $form_id;       // WPForms-ID deines Formulars
    private int $fromdate_field_id; // Feld-ID des Datumfeldes in WPForms
    private int $todate_field_id;   // Feld-ID des Datumfeldes in WPForms
    private int $halls_field_id;    // Feld-ID der Hallen-Checkboxgruppe in WPForms
    public function __construct(int $form_id, int $fromdate_field_id, int $todate_field_id,int $halls_field_id) {
        $this->form_id = $form_id;
        $this->fromdate_field_id = $fromdate_field_id;
        $this->todate_field_id = $todate_field_id;
        $this->halls_field_id = $halls_field_id;
    }

    public function hooks(): void {

        // 1) Wenn dieses Formular gerendert wird → Assets + Localize
        add_action('wpforms_frontend_output', [$this, 'on_form_output'], 20, 2);

        // 2) Datumfeld um Klasse ergänzen, damit unser JS es findet
        add_filter('wpforms_field_properties', [$this, 'add_date_input_class'], 20, 3);

        // 3) AJAX für Gäste & eingeloggte
        add_action('wp_ajax_wpem_calendar_daymap',   [$this, 'ajax_daymap']);
        add_action('wp_ajax_nopriv_wpem_calendar_daymap', [$this, 'ajax_daymap']);

        new \WP_EvManager\Frontend\WPFormsSubmission($this->form_id, WPEM_WPFORMS_MAP);
    }

    public function on_form_output($form_data,$form): void {
        if ((int)($form_data['id'] ?? 0) !== $this->form_id) return;

        // Flatpickr + unser Frontend-Script laden
        wp_enqueue_style('flatpickr');
        wp_enqueue_script('flatpickr');
        //wp_enqueue_script('flatpickr-de');
        wp_enqueue_style('wpem-frontend'); // falls du Styles dort hast
        wp_enqueue_script('wpem-frontend-datepicker'); // siehe unten

        // Initial: DayMap ab heute (optional – macht ersten Render direkt sichtbar)
        $repo   = new EventRepository();
        //$since  = new \DateTimeImmutable('today');
        //$dayMap = CalendarDataBuilder::build_day_map($repo, $since);
        $today  = date('Y-m-d'); //new \DateTimeImmutable('today');   // << hier das heutige Datum
        $dayMap = (new \WP_EvManager\Database\Repositories\EventRepository())->getDayMapSince($today);


        wp_localize_script('wpem-frontend-datepicker', 'WPEM_F', [
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('wpem_ajax'),
            'calendarDays' => $dayMap,
            'calendarMode' => ['type' => 'since', 'since' => $today],
            'formId'    => $this->form_id,
            'fromId'    => $this->fromdate_field_id,
            'toId'      => $this->todate_field_id,
            'hallsId'   => $this->halls_field_id,
        ]);

     }

    public function add_date_input_class($properties, $field, $form_data) {

        // akzeptiere NUR diese beiden Feld-IDs
        $fid = (int)($field['id'] ?? 0);
        $targetIds = [$this->fromdate_field_id, $this->todate_field_id];

        if (!in_array($fid, $targetIds, true)) {
            return $properties; // alle anderen Felder ignorieren
        }

        // WPForms-Struktur: $properties['inputs']['primary']['class'] ist Klassen-Array
        if (!isset($properties['inputs']['primary']['class']) || !is_array($properties['inputs']['primary']['class'])) {
            $properties['inputs']['primary']['class'] = [];
        }
        $properties['inputs']['primary']['class'][] = 'js-wpem-date';
        $properties['inputs']['primary']['class'][] = 'wpem-datepickr'; // optional für selektoren

        // Sicherstellen, dass es ein Text-Input bleibt (Flatpickr hängt sich dran)
        $properties['inputs']['primary']['attr']['type'] = 'text';

        return $properties;
    }

    public function ajax_daymap(): void {
        // Gäste dürfen lesen → Nonce optional; wenn du willst: absichern:
        // check_ajax_referer('wpem_ajax'); // bei Gästen evtl. weglassen oder eigenen Nonce ausgeben

        $mode  = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'since';
        $since = null;
        if ($mode === 'since') {
            $sinceStr = sanitize_text_field($_POST['since'] ?? '');
            try { $since = $sinceStr ? new \DateTimeImmutable($sinceStr) : new \DateTimeImmutable('today'); }
            catch (\Throwable $e) { wp_send_json_error(['message'=>'Bad since'], 400); }
        }

        $repo = new EventRepository();
        $map  = CalendarDataBuilder::build_day_map($repo, $since);

        wp_send_json_success(['days' => $map, 'mode' => $mode, 'since' => $since?->format('Y-m-d')]);
    }
}

