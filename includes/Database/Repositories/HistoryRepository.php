<?php
namespace WP_EvManager\Database\Repositories;

defined('ABSPATH') || exit;

class HistoryRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'evmanager_history';
    }

    /**
     * Änderungen für ein Event speichern
     */
    public function log_changes(int $event_id, string $editor, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        global $wpdb;
        $wpdb->insert(
            $this->table,
            [
                'event_id'   => $event_id,
                'editor'     => $editor,
                'changes'    => wp_json_encode($changes, JSON_UNESCAPED_UNICODE),
                'changed_at' => current_time('mysql', 1),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Historie für ein Event abrufen
     */
    public function get_history(int $event_id, int $limit = 20): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE event_id = %d ORDER BY changed_at DESC LIMIT %d",
            $event_id,
            $limit
        );
        $rows = $wpdb->get_results($sql, \ARRAY_A);

        // JSON decodieren
        foreach ($rows as &$row) {
            $row['changes'] = json_decode($row['changes'], true);
        }

        return $rows;
    }

    public function log_history(int $event_id, array $new_data, array $old_data = null, bool $use_whitelist = true): void
    {
        global $wpdb;

        // Falls keine alten Daten übergeben wurden → aus DB laden
        if ($old_data === null) {
            $old_data = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $event_id),
                \ARRAY_A
            );
        }

        if (!$old_data) {
            return;
        }

        // Nur diese Felder vergleichen
        $allowed = [
            'fromdate',
            'fromtime',
            'todate',
            'totime',
            'descr1',
            'descr2',
            'descr3',
            'short',
            'organizer',
            'persons',
            'email',
            'tel',
            'picture',
            'title',
            'type',
            'place1',
            'place2',
            'place3',
            'soldout',
            'processed',
            'editor',
            'informed',
            'publish',
            'addinfos',
            'status',
            'booked',
            'organization',
            'note'
        ];

        $compare_fields = $use_whitelist ? $allowed : array_keys($new_data);

        $changes = [];
        foreach ($compare_fields as $field) {
            // ⚡️ Nur Felder vergleichen, die wirklich übergeben wurden
            if (!array_key_exists($field, $new_data)) {
                continue;
            }

            $old_val = $old_data[$field] ?? null;
            $new_val = $new_data[$field];

            // ⚡️ Normalisiere "0000-00-00" zu NULL
            if ($old_val === '0000-00-00') {
                $old_val = null;
            }
            if ($new_val === '0000-00-00') {
                $new_val = null;
            }

            // ⚡️ Strings und Zahlen konsistent vergleichen
            if ((string)$old_val !== (string)$new_val) {
                $changes[$field] = [$old_val, $new_val];
            }
        }

        if (!empty($changes)) {
            $editor = wp_get_current_user()->user_login ?? 'system';

            //$historyRepo = new \WP_EvManager\Database\Repositories\HistoryRepository();
            $this->log_changes($event_id, $editor, $changes);
        }
    }

    public function log_created(int $event_id, string $source = 'backend'): void
    {
        if ($event_id <= 0) {
            return;
        }

        $editor = wp_get_current_user()->user_login ?? ($source === 'frontend' ? 'formular' : 'system');

        $this->log_changes(
            $event_id,
            $editor,
            [
                '_created' => [
                    null,
                    ($source === 'frontend' ? 'Neue Anfrage über Formular' : 'Event im Backend angelegt')
                ]
            ]
        );

        //error_log("EventRepository::log_created - ID {$event_id} - source={$source}");
    }

    /**
     * Duplizierung eines Events in der Historie festhalten
     * @param int $new_id
     * @param int $source_id
     * @param string|null $editor
     * @return void
     */
    public function log_duplicated(
        int $new_id,
        int $source_id,
        string $editor = null
    ): void {
        if ($new_id <= 0 || $source_id <= 0) {
            return;
        }

        if ($editor === null) {
            $editor = wp_get_current_user()->user_login ?? 'system';
        }

        // 🆕 History beim NEUEN Event
        $this->log_changes(
            $new_id,
            $editor,
            [
                '_duplicated_from' => [
                    null,
                    "Dupliziert von Event #{$source_id}"
                ]
            ]
        );

        // 🆕 History beim ORIGINAL
        $this->log_changes(
            $source_id,
            $editor,
            [
                '_duplicated_to' => [
                    null,
                    "Event wurde dupliziert → neue ID #{$new_id}"
                ]
            ]
        );
    }


    public function log_trash(int $event_id, string $source): void
    {
        if ($event_id <= 0) {
            return;
        }

        $editor = wp_get_current_user()->user_login ?? 'system';

        $this->log_changes(
            $event_id,
            $editor,
            [
                '_trash' => [
                    null,
                    ($source === 'move-to' ? 'Event in den Papierkor verschoben' : 'Event aus dem Papierkorb wiederhergestell')
                ]
            ]
        );

        //error_log("EventRepository::log_trash - ID {$event_id} - source={$source}");
    }

    public function log_force_delete(int $event_id): void
    {
        if ($event_id <= 0) {
            return;
        }

        $editor = wp_get_current_user()->user_login ?? 'system';

        $this->log_changes(
            $event_id,
            $editor,
            [
                '_deleted' => [
                    null,
                    'Event endgültig gelöscht'
                ]
            ]
        );

        //error_log("EventRepository::log_deleted - ID {$event_id}");
    }

    public function get_history__(int $event_id, int $limit = 20): array
    {
        if ($event_id <= 0) {
            return [];
        }

        return $this->get_history($event_id, $limit);
    }

}

