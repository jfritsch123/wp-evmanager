<?php

namespace WP_EvManager\Database\Repositories;

use WP_EvManager\Database\Schema;
use WP_EvManager\Security\Permissions;
use WP_EvManager\Settings\ManagerSettings;

defined('ABSPATH') || exit;

final class EventRepository
{
    private string $table;
    private const OLD_DOMAIN = 'https://loewensaal.at/wordpress/';
    private const NEW_DOMAIN = 'https://evmanager.loewensaal.at/';

    public function __construct()
    {
        $this->table = Schema::table_name();
    }

    public function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), \ARRAY_A);

        $row = $this->adjustPictureDomain($row);
        return $row ?: null;
    }

    private function adjustPictureDomain(array $row): array
    {
        if (!empty($row['import']) && !empty($row['picture'])) {
            $row['picture'] = str_replace(
                self::OLD_DOMAIN,
                self::NEW_DOMAIN,
                $row['picture']
            );
        }
        return $row;
    }

    /**
     * Findet Events anhand der übergebenen Filterkriterien.
     * Mögliche Argumente:
     * - q (string): Suchbegriff (in title, short, descr1-3, organizer, organization)
     * - editor (string): user_login des Editors
     * - fromdate_min (string): Datum YYYY-MM-DD
     * - fromdate_max (string): Datum YYYY-MM-DD
     * - booked_only (bool): nur ausgebuchte Events
     * - place1 (string): Saal 1
     * - place2 (string): Saal 2
     * - place3 (string): Saal 3
     * - status (array of string): Status-Werte (exakte Übereinstimmung, Mehrfachauswahl möglich)
     * - order_by (string): Sortierspalte (fromdate, todate, title, ts, id) - default: fromdate
     * - order_dir (string): Sortierrichtung (ASC, DESC) - default: ASC
     * - per_page (int): Anzahl pro Seite (1..200) - default: 50
     * - page (int): Seitenzahl ab 1 (alternativ offset)
     * - offset (int): Offset ab 0 (alternativ page)
     *
     * Liefert ein Array mit zwei Elementen:
     * - [0]: Array der gefundenen Events
     * - [1]: Total-Anzahl der gefundenen Events (für Pagination)
     *
     * @param array $args
     * @return array [items[], total]
     */
    public function find(array $args = []): array
    {
        global $wpdb;

        $where  = [];
        $params = [];

        // =========================================================
        // 🔍 Suche
        // =========================================================
        if (!empty($args['q'])) {
            $q = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = '(title LIKE %s OR type LIKE %s OR descr1 LIKE %s OR descr2 LIKE %s OR descr3 LIKE %s OR organizer LIKE %s OR organization LIKE %s)';
            array_push($params, $q, $q, $q, $q, $q, $q, $q);
        }

        // =========================================================
        // ✏️ Editor
        // =========================================================
        if (!empty($args['editor'])) {
            $where[]  = 'editor = %s';
            $params[] = (string) $args['editor'];
        }

        // =========================================================
        // 📅 Datumsbereich
        // =========================================================
        if (!empty($args['fromdate_min'])) {
            $where[]  = 'fromdate >= %s';
            $params[] = $args['fromdate_min'];
        }
        if (!empty($args['fromdate_max'])) {
            $where[]  = 'fromdate <= %s';
            $params[] = $args['fromdate_max'];
        }

        // Jahreslimit aus Settings (immer aktiv)
        $year_limit      = ManagerSettings::get_value('year_limit', 'all');
        if ($year_limit !== 'all' && ctype_digit((string) $year_limit)) {
            $where[]  = 'YEAR(fromdate) >= %d';
            $params[] = (int) $year_limit;
        }

        /*
        // Wenn kein Bereich gesetzt → Jahrbegrenzung aus Settings
        if (empty($args['fromdate_min']) && empty($args['fromdate_max'])) {
            $year_limit      = ManagerSettings::get_value('year_limit', 'all');
            if ($year_limit !== 'all' && ctype_digit((string) $year_limit)) {
                $where[]  = 'YEAR(fromdate) >= %d';
                $params[] = (int) $year_limit;
            }
        }
        */

        // =========================================================
        // 🚫 Ausgebucht-Filter
        // =========================================================
        if (!empty($args['booked_only'])) {
            $where[] = 'booked = 1';
        }

        // =========================================================
        // 🏛️ Saalbelegung
        // =========================================================
        foreach (['place1', 'place2', 'place3'] as $col) {
            if (isset($args[$col]) && $args[$col] !== '' && $args[$col] !== null) {
                $where[]  = "$col = %s";
                $params[] = (string) $args[$col];
            }
        }

		// =========================================================
		// 🟩 Status (Einfachauswahl – finde Events mit gewählten Statuswerten)
		// =========================================================
	    if (!empty($args['status']) && is_array($args['status'])) {
		    $sel = array_values(array_filter($args['status'], static fn($s) => $s !== ''));
		    if ($sel) {
			    // Baue Platzhalter (..., ..., ...)
			    $placeholders = implode(',', array_fill(0, count($sel), '%s'));
			    $where[] = "status IN ($placeholders)";
			    foreach ($sel as $s) {
				    $params[] = (string) $s;
			    }
		    }
	    }

        /**
         * Papierkorb-Filter
         * neu ab Version 1.0.0
         * 1. Wenn NICHT gesetzt → Standard: trash = 0 oder NULL
         * 2. Wenn gesetzt → NUR Papierkorb und alle anderen Filter deaktivieren
         */
        if (!empty($args['trash'])) {
            // Papierkorb-Modus → alle anderen Filter entfernen
            $where = [];
            $params = [];
            $where[] = "trash = 1";

        } else {
            // Standard-Modus → trash = 0 oder NULL
            $where[] = "(trash = 0 OR trash IS NULL)";
        }


        // =========================================================
        // 🧱 WHERE-Bedingungen zusammenbauen
        // =========================================================
        $where_sql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        // =========================================================
        // 🔢 ORDER BY (Whitelist)
        // =========================================================
        $order_by = 'fromdate ASC, fromtime ASC, id DESC';
        if (!empty($args['order_by'])) {
            $allowed = ['fromdate', 'todate', 'title', 'ts', 'id'];
            if (in_array($args['order_by'], $allowed, true)) {
                $dir = (!empty($args['order_dir']) && strtoupper($args['order_dir']) === 'DESC') ? 'DESC' : 'ASC';
                $order_by = $args['order_by'] . ' ' . $dir . ', id DESC';
            }
        }

        // =========================================================
        // 📊 Total-Zeilen zählen
        // =========================================================
        $sql_total = "SELECT COUNT(*) FROM {$this->table}{$where_sql}";
        $params_where = $params; // Kopie vor Limit/Offset-Erweiterung

        $total = $where
            ? (int) $wpdb->get_var($wpdb->prepare($sql_total, ...$params_where))
            : (int) $wpdb->get_var($sql_total);

        // =========================================================
        // 📃 Pagination
        // =========================================================
        $per_page = isset($args['per_page'])
            ? (int) $args['per_page']
            : (isset($args['limit']) ? (int) $args['limit'] : 50);
        $per_page = max(1, min(200, $per_page));

        $page   = isset($args['page']) ? max(1, (int) $args['page']) : null;
        $offset = isset($args['offset']) ? (int) $args['offset'] : null;

        if ($page !== null) {
            $limit  = $per_page;
            $offset = ($page - 1) * $per_page;
        } else {
            $limit  = $per_page;
            $offset = (int)($offset ?? 0);
        }

        // =========================================================
        // 📦 Daten laden
        // =========================================================
        $sql_items = "SELECT * FROM {$this->table}{$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";

        // Limit + Offset ans Ende anhängen
        $params_all = array_merge($params_where, [$limit, $offset]);

        // Sicherheits-Log vor Ausführung
        //error_log('EventRepository::find() WHERE: ' . $where_sql);
        //error_log('EventRepository::find() PARAMS (WHERE): ' . print_r($params_where, true));
        //error_log('EventRepository::find() LIMIT/OFFSET: limit=' . $limit . ' offset=' . $offset);

        $query = $wpdb->prepare($sql_items, ...$params_all);
        //error_log('EventRepository::find() SQL prepared: ' . $wpdb->remove_placeholder_escape($query));

        $items = $wpdb->get_results($query, \ARRAY_A) ?: [];

        // Abschluss-Log
        //error_log('EventRepository::find() total: ' . $total);
        //error_log('EventRepository::find() returned items: ' . count($items));

        return [$items, $total];

    }

    /**
     * Fügt einen neuen Event-Datensatz hinzu.
     * Erwartet die Daten in einem assoziativen Array mit den Spaltennamen als Keys.
     * @param array $data
     * @return int ID des neuen Datensatzes
     */
    public function insert(array $data): int
    {
        global $wpdb;

        // Whitelist + Format-Map (alle Spalten außer id)
        $allowed = [
            'fromdate' => '%s',
            'fromtime' => '%s',
            'todate' => '%s',
            'totime' => '%s',
            'descr1' => '%s',
            'descr2' => '%s',
            'descr3' => '%s',
            'short' => '%s',
            'organizer' => '%s',
            'persons' => '%s',
            'email' => '%s',
            'tel' => '%s',
            'picture' => '%s',
            'title' => '%s',
            'type' => '%s',
            'place1' => '%s',
            'place2' => '%s',
            'place3' => '%s',
            'soldout' => '%s',
            'processed' => '%s', // DATE
            'editor' => '%s', // user_login
            'informed' => '%s', // DATE
            'publish' => '%s',
            'status' => '%s', // SET als string
            'booked' => '%d',
            'organization' => '%s',
            'note' => '%s',
            // ✅ NEU
            'addinfos'      => '%s',

            //'wpforms_entry_id' => '%d',
            //'ip' => '%s',
        ];

        // Defaults: editor, falls nicht gesetzt
        if (empty($data['editor'])) {
            $data['editor'] = Permissions::current_login();
        }

        // 👉 processed immer auf aktuelles Datum + Uhrzeit setzen
        $data['processed'] = current_time('mysql');

        $filtered = array_intersect_key($data, $allowed);
        $formats = array_values(array_intersect_key($allowed, $filtered));

        error_log('EventRepository::insert() <pre>: ' .print_r($_POST,1). print_r($filtered, true) .print_r($formats, true));

        $wpdb->insert($this->table, $filtered, $formats);
        $insert_id = (int) $wpdb->insert_id;
        $historyRepo = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $historyRepo->log_created($insert_id, 'backend');
        return $insert_id;
    }

    public function update_if_permitted(int $id, array $data, bool $can_edit_all): bool
    {
        global $wpdb;

        $existing = $this->get($id);
        if (!$existing) {
            return false;
        }
        if (!$can_edit_all && !\WP_EvManager\Security\Permissions::can_edit_own((string)$existing['editor'])) {
            return false;
        }

        // Whitelist (Ownership-Felder nicht änderbar)
        $allowed = [
            'fromdate' => '%s',
            'fromtime' => '%s',
            'todate' => '%s',
            'totime' => '%s',
            'descr1' => '%s',
            'descr2' => '%s',
            'descr3' => '%s',
            'short' => '%s',
            'organizer' => '%s',
            'persons' => '%s',
            'email' => '%s',
            'tel' => '%s',
            'picture' => '%s',
            'title' => '%s',
            'type' => '%s',
            'place1' => '%s',
            'place2' => '%s',
            'place3' => '%s',
            'soldout' => '%s',
            'processed' => '%s',
            'editor'      => '%s', // Ownership NICHT per Update erlauben
            'informed' => '%s',
            'publish' => '%s',
            'status' => '%s',
            'booked' => '%d',
            'organization' => '%s',
            'note' => '%s',
            // ✅ NEU
            'addinfos'      => '%s',
            // 'ts'          => '%s', // i.d.R. DB Default/Trigger nutzen
            //'ip' => '%s',
        ];

        if (empty($data['editor'])) {
            $data['editor'] = Permissions::current_login();
        }

        $data['processed'] = current_time('mysql');

        [$filtered, $formats] = (function(array $data, array $allowed){
            $f = [];
            $fm = [];
            foreach ($allowed as $key => $fmt) {
                if (array_key_exists($key, $data)) {
                    $f[$key]  = $data[$key];
                    $fm[]     = $fmt;
                }
            }
            return [$f, $fm];
        })($data, $allowed);

        //error_log('EventRepository::update_if_permitted() <pre>: ' .print_r($_POST,1). print_r($filtered, true) .print_r($formats, true));

        $result = $wpdb->update(
            $this->table,
            $filtered,
            ['id' => $id],
            $formats,
            ['%d']
        );
        // 🔹 History nur loggen, wenn Update erfolgreich war
        $historyRepo = new \WP_EvManager\Database\Repositories\HistoryRepository();

        if ($result !== false && !empty($filtered)) {
            $historyRepo->log_history(
                $id,
                $filtered,   // neue Daten (NUR was geändert wurde!)
                $existing,   // alter DB-Zustand
                true         // Whitelist verwenden
            );
        }
        return ($result !== false);
    }

    /**
     * Fügt einen duplizierten Event-Datensatz hinzu.
     * Erwartet die Daten in einem assoziativen Array mit den Spaltennamen als Keys.
     * Nur erlaubte Felder werden übernommen.
     * @param array $data
     * @return int ID des neuen Datensatzes
     */
    private function insert_duplicate_row(array $data): int
    {
        global $wpdb;

        // ✅ Erlaubte Felder + Formate
        $allowed = [
            'fromdate' => '%s',
            'fromtime' => '%s',
            'todate'   => '%s',
            'totime'   => '%s',
            'title'    => '%s',
            'short'    => '%s',
            'descr1'   => '%s',
            'descr2'   => '%s',
            'descr3'   => '%s',
            'organizer'=> '%s',
            'organization' => '%s',
            'persons'  => '%d',
            'email'    => '%s',
            'tel'      => '%s',
            'type'     => '%s',
            'place1'   => '%s',
            'place2'   => '%s',
            'place3'   => '%s',
            'places'   => '%s',
            'booked'   => '%d',
            'publish'  => '%s',
            'status'   => '%s',
            'picture'  => '%s',
            'note'     => '%s',
            'editor'   => '%s',
            'processed'=> '%s',
            'trash'    => '%d',
        ];

        // 🔒 Whitelisting
        $insert = [];
        $formats = [];

        foreach ($allowed as $key => $fmt) {
            if (array_key_exists($key, $data)) {
                $insert[$key] = $data[$key];
                $formats[] = $fmt;
            }
        }

        $ok = $wpdb->insert($this->table, $insert, $formats);
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Dupliziert einen Event-Datensatz.
     * @param int $id ID des zu duplizierenden Events
     * @return int ID des neuen (duplizierten) Events oder 0 bei Fehler
     */
    public function duplicate(int $id): array
    {
        global $wpdb;

        $original = $this->get($id);
        if (!$original) {
            return [];
        }

        unset(
            $original['id'],
            $original['ts'],
            $original['wpforms_entry_id'],
            $original['trash'],
            $original['import']
        );

        $original['trash']     = 0;
        $original['processed'] = current_time('mysql');
        $original['editor']    = \WP_EvManager\Security\Permissions::current_login();

        $original['title'] = $original['title'] . ' OPTION Kopie';
        $original['duplicated_from'] = $id;

        $newId = $this->insert_duplicate_row($original);
        if (!$newId) {
            return [];
        }

        // 🔹 History
        $history = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $history->log_duplicated($newId, $id);

        return [
            'id'        => $newId,
            'fromdate'  => $original['fromdate'] ?? null,
        ];
    }


    public function delete_if_permitted(int $id, bool $can_delete_all): bool
    {
        global $wpdb;

        $existing = $this->get($id);
        if (!$existing) {
            return false;
        }
        if (!$can_delete_all && !\WP_EvManager\Security\Permissions::can_delete_own_by_editor((string)$existing['editor'])) {
            return false;
        }
        $historyRep = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $historyRep->log_force_delete($id);

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        return (bool)$result;
    }

    /**
     * Neuerung ab Version 1.0.0: Papierkorb-Funktionalität
     * Verschiebt einen Event-Datensatz in den Papierkorb (setzt trash=1).
     * @param int $id
     * @param bool $can_delete_all
     * @return bool
     */
    public function move_to_trash(int $id, bool $can_delete_all): bool
    {
        global $wpdb;

        $existing = $this->get($id);
        if (!$existing) return false;

        if (!$can_delete_all && !\WP_EvManager\Security\Permissions::can_delete_own_by_editor((string)$existing['editor'])) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            ['trash' => 1, 'processed' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );
        $historyRepo = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $historyRepo->log_trash($id, 'move-to');

        return ($result !== false);
    }

    public function restore_from_trash(int $id): array|false
    {
        global $wpdb;

        $ok = $wpdb->update(
            $this->table,
            [
                'trash'     => 0,
                'processed' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($ok === false) {
            return false;
        }

        $historyRepo = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $historyRepo->log_trash($id, 'restore-from');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, fromdate FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Liefert alle Events für den Kalender (id, fromdate, todate, place1-3, status, booked).
     * Wenn $since gesetzt ist, nur Events ab diesem Datum (Einzeltage oder Zeiträume, die darüber hinausragen).
     * @param \DateTimeImmutable|null $since
     * @return array
     */
    public function get_all_for_calendar($since = null): array
    {
        global $wpdb;
        $table = $this->table;
        if (!$since) {
            $sql = "SELECT id, fromdate, todate,
                       COALESCE(place1,'') place1, COALESCE(place2,'') place2, COALESCE(place3,'') place3,
                       COALESCE(status,'') status, COALESCE(booked,0) booked
                FROM {$table}";
            return (array)$wpdb->get_results($sql, \ARRAY_A);
        }
        $s = $since->format('Y-m-d');
        // Ranges, die >= $since liegen (Einzeltage oder Zeiträume, die darüber hinausragen)
        $sql = $wpdb->prepare(
            "SELECT id, fromdate, todate,
                COALESCE(place1,'') place1, COALESCE(place2,'') place2, COALESCE(place3,'') place3,
                COALESCE(status,'') status, COALESCE(booked,0) booked
         FROM {$table}
         WHERE 
           (fromdate IS NOT NULL AND fromdate <> '0000-00-00' AND fromdate >= %s)
           OR
           (todate   IS NOT NULL AND todate   <> '0000-00-00' AND todate   >= %s)",
            $s, $s
        );
        return (array)$wpdb->get_results($sql, \ARRAY_A);
    }

    /**
     * Fügt einen Event-Datensatz aus einem WPForms-Formular hinzu.
     * Erwartet die Daten in einem assoziativen Array mit den Spaltennamen als Keys.
     * @param array $data
     * @return int ID des neuen Datensatzes oder 0 bei Fehler
     */
    public function insert_from_submission(array $data): int
    {
        global $wpdb;

        $defaults = [
            'fromdate' => null,
            'fromtime' => '',
            'todate' => null,
            'totime' => '',

            'descr3' => '',
            'organizer' => '',
            'persons' => '',
            'email' => '',
            'tel' => '',

            'type' => '',
            'place1' => '0',
            'place2' => '0',
            'place3' => '0',
            'places' => '',

            'title' => '',
            'short' => '',
            'publish' => '',
            'booked' => 0,

            'status' => 'Anfrage erhalten',
            'wpforms_entry_id' => 0
        ];
        $row = array_merge($defaults, $data);

        // Null-Dates auf '0000-00-00' wenn DB das (noch) erwartet
        $row['fromdate'] = $row['fromdate'] ?? '0000-00-00';
        $row['todate']   = $row['todate']   ?? '0000-00-00';

        $ok = $wpdb->insert(
            $this->table,
            $row,
            [
                '%s','%s','%s','%s',        // fromdate, fromtime, todate, totime
                '%s','%s','%s','%s','%s',   // descr3, organizer, persons, email, tel
                '%s','%s','%s','%s','%s',   // type, place1, place2, place3, places
                '%s','%s','%s',             // title, short, publish(int?) -> ist varchar(1) ⇒ %s sicherer
                '%d','%s','%d'              // booked(int), status, wpforms_entry_id(int)
            ]
        );

        if (!$ok) return 0;

        $historyRep = new \WP_EvManager\Database\Repositories\HistoryRepository();
        $historyRep->log_created($wpdb->insert_id, 'frontend');

        return (int)$wpdb->insert_id;
    }

    public function get_years_months_named($year_limit=true): array {
        global $wpdb;
        $t = $this->table;
        // Limit aus Settings
        if( $year_limit === true ){
            $year_limit = ManagerSettings::get_value('year_limit', 'all');
            $year_limit = ($year_limit !== 'all') ? (int)$year_limit : null;
        }
        else{
            $year_limit = null;
        }
        $where_from = "fromdate IS NOT NULL AND fromdate <> '0000-00-00'";
        $where_to   = "todate   IS NOT NULL AND todate   <> '0000-00-00'";

        if ($year_limit) {
            $where_from .= $wpdb->prepare(" AND YEAR(fromdate) >= %d", $year_limit);
            $where_to   .= $wpdb->prepare(" AND YEAR(todate)   >= %d", $year_limit);
        }

        // Jahre
        $years_sql = "
        SELECT y FROM (
          SELECT DISTINCT YEAR(fromdate) AS y FROM {$t} WHERE {$where_from}
          UNION
          SELECT DISTINCT YEAR(todate)   AS y FROM {$t} WHERE {$where_to}
        ) yy
        WHERE y IS NOT NULL
        ORDER BY y DESC
    ";
        $years = $wpdb->get_col($years_sql);

        // Monate je Jahr (1..12)
        $rows = $wpdb->get_results("
        SELECT y, m FROM (
          SELECT DISTINCT YEAR(fromdate) AS y, MONTH(fromdate) AS m
          FROM {$t}
          WHERE fromdate IS NOT NULL AND fromdate <> '0000-00-00'
          UNION
          SELECT DISTINCT YEAR(todate) AS y, MONTH(todate) AS m
          FROM {$t}
          WHERE todate IS NOT NULL AND todate <> '0000-00-00'
        ) ym
        WHERE y IS NOT NULL AND m IS NOT NULL
        ORDER BY y DESC, m ASC
    ", \ARRAY_A);

        $monthsByYear = [];
        foreach ($rows as $r) {
            $y  = (int) $r['y'];
            $m  = (int) $r['m'];
            $mm = sprintf('%02d', $m);

            // Lokalisierter Monatsname (WordPress-API nutzt die eingestellte Sprache)
            // Variante A: WP_Locale
            $name = isset($GLOBALS['wp_locale']) ? $GLOBALS['wp_locale']->get_month($m) : date('F', mktime(0,0,0,$m,1,$y));
            // Variante B (alternativ): $name = wp_date('F', mktime(0,0,0,$m,1,$y));

            $monthsByYear[$y][] = ['num' => $mm, 'name' => $name];
        }

        return [
            'years'        => array_map('intval', $years),
            'monthsByYear' => $monthsByYear, // z.B. {2025:[{num:"01",name:"Jänner"},…]}
        ];
    }

    /**
     * Baut eine Day-Map ab $since (YYYY-MM-DD).
     * - SQL bleibt simpel (GROUP BY fromdate,todate + MAX(place1..3))
     * - In PHP werden from..to tagesweise expandiert
     */
    public function getDayMapSince(string $since, ?string $until = null): array
    {
        global $wpdb;

        // Bounds vorbereiten
        $since = $since ?: date('Y-m-d');
        if ($until && strtotime($until) < strtotime($since)) {
            [$since, $until] = [$until, $since];
        }

        // WHERE: nur Events berücksichtigen, die überhaupt in/ab dem Fenster liegen
        // (to >= $since) ODER (todate leer/0000-00-00 und from >= $since)
        $where = "WHERE fromdate IS NOT NULL 
                  AND trash = 0 
                  AND fromdate <> '0000-00-00'
                  AND (
                        COALESCE(NULLIF(todate,'0000-00-00'), fromdate) >= %s
                      )";
        $params = [$since];

        if ($until) {
            $where .= " AND fromdate <= %s";
            $params[] = $until;
        }

        $sql = $wpdb->prepare("
            SELECT 
                fromdate,
                todate,
                MAX(CAST(place1 AS UNSIGNED)) AS place1,
                MAX(CAST(place2 AS UNSIGNED)) AS place2,
                MAX(CAST(place3 AS UNSIGNED)) AS place3,
                MAX(CAST(booked AS UNSIGNED)) AS booked   -- << NEU/prüfen
            FROM {$this->table}
            {$where}
            GROUP BY fromdate, todate
            ORDER BY fromdate ASC
        ", $params);

        $rows = $wpdb->get_results($sql, \ARRAY_A) ?: [];

        // Zusätzliche Anfragen mit "places" und Status = 'Anfrage erhalten'
        $sqlPlaces = $wpdb->prepare("
            SELECT fromdate, todate, places
            FROM {$this->table}
            WHERE trash = 0 
              AND status = %s
              AND places IS NOT NULL AND places <> ''
              AND fromdate >= %s
        ", ['Anfrage erhalten', $since]);

        $rowsPlaces = $wpdb->get_results($sqlPlaces, \ARRAY_A) ?: [];
        // Expandieren & mergen (wie gehabt)
        $map = [];
        $ymd = static fn(\DateTimeInterface $d) => $d->format('Y-m-d');

        $mergeDay = static function (&$map, string $day, int $p1, int $p2, int $p3, int $booked): void {
            if (!isset($map[$day])) {
                $map[$day] = [
                    'p'      => ['place1'=>0,'place2'=>0,'place3'=>0],
                    'booked' => 0,
                ];
            }
            // Säle: max (0<1<2)
            $map[$day]['p']['place1'] = max((int)$map[$day]['p']['place1'], $p1);
            $map[$day]['p']['place2'] = max((int)$map[$day]['p']['place2'], $p2);
            $map[$day]['p']['place3'] = max((int)$map[$day]['p']['place3'], $p3);
            // booked: max (0/1)
            $map[$day]['booked'] = max((int)$map[$day]['booked'], $booked);
        };

        foreach ($rows as $r) {
            $from = \DateTime::createFromFormat('Y-m-d', $r['fromdate'] ?? '');
            if (!$from) continue;

            $toRaw = $r['todate'] ?? '';
            $to = ($toRaw && $toRaw !== '0000-00-00')
                ? \DateTime::createFromFormat('Y-m-d', $toRaw)
                : null;
            if (!$to || $to < $from) $to = clone $from;

            // auf Fenster schneiden
            $sinceDt = \DateTime::createFromFormat('Y-m-d', $since);
            if ($from < $sinceDt) $from = clone $sinceDt;
            if ($until) {
                $untilDt = \DateTime::createFromFormat('Y-m-d', $until);
                if ($to > $untilDt) $to = clone $untilDt;
            }

            $p1 = (int)($r['place1'] ?? 0);
            $p2 = (int)($r['place2'] ?? 0);
            $p3 = (int)($r['place3'] ?? 0);
            $bk = (int)($r['booked'] ?? 0); // << NEU

            for ($cur = clone $from; $cur <= $to; $cur->modify('+1 day')) {
                $mergeDay($map, $ymd($cur), $p1, $p2, $p3, $bk);
            }
        }

        // Zusätzliche "Anfrage erhalten" mit places expandieren & mergen
        foreach ($rowsPlaces as $r) {
            $from = \DateTime::createFromFormat('Y-m-d', $r['fromdate'] ?? '');
            if (!$from) continue;

            $toRaw = $r['todate'] ?? '';
            $to = ($toRaw && $toRaw !== '0000-00-00')
                ? \DateTime::createFromFormat('Y-m-d', $toRaw)
                : null;
            if (!$to || $to < $from) $to = clone $from;

            // expandieren
            for ($cur = clone $from; $cur <= $to; $cur->modify('+1 day')) {
                $places = array_map('trim', explode(',', (string)$r['places']));
                $p1 = in_array('Großer Saal', $places, true) ? 1 : 0;
                $p2 = in_array('Kleiner Saal', $places, true) ? 1 : 0;
                $p3 = in_array('Foyer', $places, true) ? 1 : 0;

                $mergeDay($map, $ymd($cur), $p1, $p2, $p3, 0);
            }
        }

        // Farben/Disable nach deiner 3-Stufen-Logik
        foreach ($map as $day => &$info) {
            $p1 = (int)$info['p']['place1'];
            $p2 = (int)$info['p']['place2'];
            $p3 = (int)$info['p']['place3'];
            $booked = (int)$info['booked'];

            $allFree   = ($p1===0 && $p2===0 && $p3===0);
            $allBooked = ($p1===2 && $p2===2 && $p3===2);

            $isRed    = ($booked===1) || $allBooked;
            $isGreen  = !$isRed && $allFree;
            $isOrange = !$isRed && !$isGreen;

            $info['cats'] = [
                'red'    => $isRed,
                'orange' => $isOrange,
                'green'  => $isGreen,
            ];
            $info['disable'] = $isRed ? 1 : 0;
        }
        unset($info);

        ksort($map);
        return $map;
    }

    /**
     * Liefert den Status (als Array) für ein Event anhand der ID.
     * @param int $id
     * @return array Status-Werte als Array (leer, wenn kein Status gesetzt ist)
     */
    public function getStatusFromId(int $id): array{
        global $wpdb;
        $status = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$this->table} WHERE id = %d", $id)
        );
        return $status ? explode(',', $status) : [];
    }

    /**
     * Findet Events an einem bestimmten Datum (fromdate).
     * Optional kann eine ID zum Ausschluss angegeben werden (z.B. bei der Bearbeitung).
     * Liefert ein Array mit den Events (id, title, fromtime, todate, totime, place1-3, status).
     * @param string $date YYYY-MM-DD
     * @param int $excludeId
     * @return array
     */
    public function findByDate(string $date, int $excludeId = 0): array
    {
        global $wpdb;

        $where = 'fromdate = %s';
        $params = [$date];

        if ($excludeId > 0) {
            $where .= ' AND id != %d';
            $params[] = $excludeId;
        }

        $sql = $wpdb->prepare("
            SELECT id, title, fromtime, todate, totime, place1, place2, place3, status, places, booked
            FROM {$this->table}
            WHERE {$where}
            ORDER BY {$this->orderByEvents()}",
        $params);

        return $wpdb->get_results($sql, \ARRAY_A) ?: [];
    }

    /**
     * Alle Events, die an einem bestimmten Tag laufen, also auch solche,
     * die z. B. am 01.09. starten und bis 03.09. laufen → bei Abfrage für 02.09. werden sie mit berücksichtigt.
     *
     * noch nicht implementiert!!!
     *
     * @param string $date YYYY-MM-DD
     * @param int $excludeId
     * @return array
     */
    public function findByDateToDate(string $day, int $excludeId = 0): array {
        global $wpdb;

        $sql = "
        SELECT id, title, organizer, fromdate, todate, fromtime, totime,
               place1, place2, place3, places, status, booked
        FROM {$this->table}
        WHERE trash = 0
          AND fromdate <= %s
          AND COALESCE(NULLIF(todate,'0000-00-00'), fromdate) >= %s
    ";
        $params = [$day, $day];

        if ($excludeId > 0) {
            $sql .= " AND id <> %d";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY fromdate ASC, fromtime ASC, id ASC";
        //error_log("SQL only for $day: " . $sql);
		//error_log('EventRepository::findByDateToDate() SQL: ' . $wpdb->prepare($sql, $params));
        return $wpdb->get_results($wpdb->prepare($sql, $params), \ARRAY_A) ?: [];
    }

    public function findByRange(string $start, string $end, int $excludeId = 0): array {
        global $wpdb;

        // Korrektes Overlap: [fromdate, todate*] überlappt [start, end]
        $sql = "
        SELECT id, title, organizer, fromdate, todate, fromtime, totime, 
            place1, place2, place3, places, status, booked
        FROM {$this->table}
        WHERE trash = 0
        AND fromdate <= %s
          AND COALESCE(NULLIF(todate,'0000-00-00'), fromdate) >= %s
        ";

        $params = [$end, $start];

        if ($excludeId > 0) {
            $sql .= " AND id <> %d";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY fromdate"; //{$this->orderByEvents()}";
        return $wpdb->get_results($wpdb->prepare($sql, $params), \ARRAY_A) ?: [];
    }

    /**
     * Liefert kommende Events ab heutigem Datum.
     *
     * @param int $limit Max. Anzahl Events (0 = alle)
     * @param string|null $fromDate Startdatum im Format YYYY-MM-DD (default = heute)
     * @return array
     */
    public function find_upcoming(int $limit = 10, ?string $fromDate = null): array
    {
        global $wpdb;

        $fromDate = $fromDate ?: current_time('Y-m-d');
        $params = [$fromDate, $fromDate];

        $sql = "
        SELECT *
        FROM {$this->table}
        WHERE 
            (fromdate >= %s)
            OR (todate IS NOT NULL AND todate <> '0000-00-00' AND todate >= %s)
        ORDER BY fromdate ASC, fromtime ASC
    ";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
            $params[] = $limit;
        }

        $prepared = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($prepared, \ARRAY_A) ?: [];
    }

    public function find_upcoming_range(int $months = 3, int $offset = 0): array
    {
        global $wpdb;

        $from = date('Y-m-d');
        $start = new \DateTimeImmutable($from);
        $since = $start->modify('+' . ($offset * $months) . ' months');
        $until = $since->modify('+' . $months . ' months');
        $where[] = 'publish IS NOT NULL AND publish <> "0"';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
         WHERE fromdate >= %s 
           AND fromdate <= %s
           AND publish IS NOT NULL 
           AND publish <> '0'
         ORDER BY {$this->orderByEvents()}",
            $since->format('Y-m-d'),
            $until->format('Y-m-d')
        );

        return $wpdb->get_results($sql, \ARRAY_A) ?: [];
    }

    /**
     * Liefert ein Event für das Frontend (gefilterte Felder).
     * @param int $id
     * @return array|null
     */
    public function get_for_frontend(int $id): ?array
    {
        $row = $this->get($id);
        if (!$row) {
            return null;
        }

        // publish = 0 → nicht anzeigen
        if (empty($row['publish']) || $row['publish'] === '0') {
            return null;
        }

        return [
            'id'        => (int)$row['id'],
            'title'     => $row['title'],
            'fromdate'  => $row['fromdate'],
            'todate'    => $row['todate'],
            'fromtime'  => $row['fromtime'],
            'totime'    => $row['totime'],
            'organizer' => $row['organizer'],
            'descr1'    => $row['descr1'],
            'descr2'    => $row['descr2'],
            'descr3'    => $row['descr3'],
            'place1'    => $row['place1'],
            'place2'    => $row['place2'],
            'place3'    => $row['place3'],
            'status'    => $row['status'],
            'booked'    => $row['booked'],
            'publish'   => $row['publish'],
            'picture'   => $row['picture'],
        ];
    }

    /**
     * Zentrale ORDER-BY-Logik für Event-Listen
     * - Original + Duplikate direkt hintereinander
     * - stabile Sortierung
     */
    private function orderByEvents(): string
    {
        return "
        COALESCE(duplicated_from, id) ASC,
        duplicated_from IS NOT NULL ASC,
        fromdate ASC,
        fromtime ASC,
        id ASC
    ";
    }
}

