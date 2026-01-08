<?php
namespace WP_EvManager\Database;

defined('ABSPATH') || exit;

final class DBTools
{
    /**
     * Importiert Daten aus wp_loewensaal.wp_evmanager in die aktuelle wp_evmanager Tabelle.
     */
    public static function import_from_loewensaal(bool $dryRun = false): array
    {
        global $wpdb;

        $current_db   = DB_NAME; // Aktuelle WP-Datenbank in wp-config.php
        $target_table = $wpdb->prefix . 'evmanager';

        // Achtung Quelle jfritsch.at
        $source_db = 'wp_mtorg';

        // Achtung Quelle loewensaal.de
        // $source_db    = 'wp_loewensaal';
        $source_table = 'wp_evmanager';

        // 1. Spaltenlisten holen
        $sql_cols = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
    ";
        $src_cols = $wpdb->get_col($wpdb->prepare($sql_cols, $source_db, $source_table));
        $dst_cols = $wpdb->get_col($wpdb->prepare($sql_cols, $current_db, $target_table));

        if (!$src_cols || !$dst_cols) {
            return ['success' => false, 'message' => 'Quelle oder Ziel-Tabelle nicht gefunden.'];
        }

        $common = array_values(array_intersect($src_cols, $dst_cols));
        if (empty($common)) {
            return ['success' => false, 'message' => 'Keine gemeinsamen Spalten für Import gefunden.'];
        }

        // 2. Dry Run
        if ($dryRun) {
            $source_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$source_db}`.`{$source_table}`");
            return [
                'success' => true,
                'message' => "Dry Run: {$source_count} Datensätze verfügbar. Gemeinsame Spalten: " . implode(', ', $common),
            ];
        }

        // 3. Echt-Import
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query("TRUNCATE TABLE `{$target_table}`");

            $cols_sql = "`" . implode("`,`", $common) . "`";
            $sql = "
            INSERT INTO `{$current_db}`.`{$target_table}` ({$cols_sql})
            SELECT {$cols_sql}
            FROM `{$source_db}`.`{$source_table}`
        ";
            $wpdb->query($sql);

            // 🔑 Nachbearbeitung
            $adjustments = self::post_import_adjustments($target_table);

            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$target_table}`");
            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => "Import erfolgreich: {$count} Datensätze übernommen. Anpassungen: " . implode('; ', $adjustments),
            ];
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }


    public static function post_import_adjustments_new(string $table, bool $dryRun = false): array
    {
        global $wpdb;
        $log = [];

        $has_col = function(string $col) use ($wpdb, $table): bool {
            return (bool) $wpdb->get_var(
                $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $col)
            );
        };

        $exec = function(string $sql, string $countSql = null) use ($wpdb, $dryRun) {
            if ($dryRun) {
                return (int) $wpdb->get_var($countSql ?: $sql);
            }
            return (int) $wpdb->query($sql);
        };

        /* =========================================
         * Regel 1: ungültige fromdate
         * ========================================= */
        if ($has_col('fromdate')) {
            $count = $exec(
                "DELETE FROM `{$table}` WHERE fromdate IS NULL OR fromdate IN ('0000-00-00','1970-01-01')",
                "SELECT COUNT(*) FROM `{$table}` WHERE fromdate IS NULL OR fromdate IN ('0000-00-00','1970-01-01')"
            );

            if ($count > 0) {
                $log[] = ($dryRun ? '[DRY-RUN] ' : '') .
                    "{$count} Datensätze mit ungültigem fromdate " .
                    ($dryRun ? 'würden entfernt' : 'entfernt');
            }
        }

        /* =========================================
         * Regel 2: Status setzen
         * ========================================= */
        if ($has_col('status')) {

            $count1 = $exec(
                "UPDATE `{$table}` SET status='In Bearbeitung'
             WHERE processed IS NOT NULL AND processed <> '0000-00-00 00:00:00'",
                "SELECT COUNT(*) FROM `{$table}`
             WHERE processed IS NOT NULL AND processed <> '0000-00-00 00:00:00'"
            );

            if ($count1 > 0) {
                $log[] = ($dryRun ? '[DRY-RUN] ' : '') .
                    "{$count1} Status → In Bearbeitung";
            }

            $count2 = $exec(
                "UPDATE `{$table}` SET status='Anfrage erhalten'
             WHERE (processed IS NULL OR processed='0000-00-00 00:00:00')
               AND (informed IS NULL OR informed='0000-00-00')
               AND (publish IS NULL OR publish='0')",
                "SELECT COUNT(*) FROM `{$table}`
             WHERE (processed IS NULL OR processed='0000-00-00 00:00:00')
               AND (informed IS NULL OR informed='0000-00-00')
               AND (publish IS NULL OR publish='0')"
            );

            if ($count2 > 0) {
                $log[] = ($dryRun ? '[DRY-RUN] ' : '') .
                    "{$count2} Status → Anfrage erhalten";
            }
        }

        /* =========================================
         * Regel 8: Import-Zeitstempel
         * ========================================= */
        if ($has_col('import')) {
            $count = $exec(
                "UPDATE `{$table}` SET import = NOW()",
                "SELECT COUNT(*) FROM `{$table}`"
            );

            if ($count > 0) {
                $log[] = ($dryRun ? '[DRY-RUN] ' : '') .
                    "{$count} Datensätze mit Importzeit versehen";
            }
        }

        return $log;
    }

    protected static function post_import_adjustments(string $table): array
    {
        global $wpdb;

        // 🔑 Liste von Regeln
        $rules = [

            // Regel 1: Status anpassen
            function($wpdb, $table) {
                // 1a) processed != '0000-00-00' → "In Bearbeitung"
                $affected1 = $wpdb->query($wpdb->prepare("
                UPDATE `{$table}`
                SET status = %s
                WHERE processed IS NOT NULL
                  AND processed <> %s
            ", 'In Bearbeitung', '0000-00-00'));

                // 1b) processed = '0000-00-00' UND informed = '0000-00-00' UND publish = 0 → "Anfrage erhalten"
                $affected2 = $wpdb->query("
                UPDATE `{$table}`
                SET status = 'Anfrage erhalten',
                descr3 = descr1
                WHERE (processed IS NULL OR processed = '0000-00-00')
                  AND (informed IS NULL OR informed = '0000-00-00')
                  AND (publish IS NULL OR publish = '0')
            ");

                $msg = [];
                if ($affected1 !== false && $affected1 > 0) {
                    $msg[] = "{$affected1} Datensätze auf Status 'In Bearbeitung' gesetzt";
                }
                if ($affected2 !== false && $affected2 > 0) {
                    $msg[] = "{$affected2} Datensätze auf Status 'Anfrage erhalten' gesetzt";
                }

                return $msg ? implode('; ', $msg) : null;
            },

            // Regel 2: ungültige fromdate löschen
            function($wpdb, $table) {
                $affected = $wpdb->query("
                DELETE FROM `{$table}`
                WHERE fromdate = '1970-01-01'
                   OR fromdate = '0000-00-00'
            ");

                return $affected !== false && $affected > 0
                    ? "{$affected} Datensätze mit ungültigem fromdate entfernt"
                    : null;
            },

            // Regel 3: place1 und place2 robust vertauschen (NULL-sicher) via Self-JOIN
            function($wpdb, $table) {
                // Wir nehmen die Originalwerte aus einer Subselect-Quelle (src)
                // und schreiben sie in die Zielzeile (dst). So werden beide Spalten
                // in einem Rutsch mit den alten Werten getauscht – NULL inklusive.
                $sql = "
                UPDATE `{$table}` AS dst
                INNER JOIN (
                    SELECT id, place1 AS old_place1, place2 AS old_place2
                    FROM `{$table}`
                ) AS src ON src.id = dst.id
                SET dst.place1 = src.old_place2,
                    dst.place2 = src.old_place1
            ";
                $affected = $wpdb->query($sql);

                return ($affected !== false && $affected > 0)
                    ? "{$affected} Datensätze: place1 und place2 vertauscht"
                    : "0 Datensätze vertauscht (ggf. identische Werte)";
            },

            // Regel 4: aktuelles Datum im Feld "import" eintragen
            function($wpdb, $table) {
                $today = current_time('mysql'); // WP-sicheres aktuelles Datum/Zeit
                $affected = $wpdb->query($wpdb->prepare("
                UPDATE `{$table}`
                SET `import` = %s
            ", $today));

                return ($affected !== false && $affected > 0)
                    ? "{$affected} Datensätze mit aktuellem Importdatum ({$today}) versehen"
                    : null;
            },

            // Regel 5: organisation = "Kultur im Löwen" → addinfos enthält "Kultur im Löwen"
            function($wpdb, $table) {

                $affected = $wpdb->query($wpdb->prepare("
                UPDATE `{$table}`
                SET addinfos = 
                    CASE
                        WHEN addinfos IS NULL OR addinfos = ''
                            THEN %s
                        WHEN FIND_IN_SET(%s, addinfos) = 0
                            THEN CONCAT(addinfos, ',', %s)
                        ELSE addinfos
                    END
                WHERE organization = %s
            ",
                    'Kultur im Löwen',          // initialer Wert
                    'Kultur im Löwen',          // prüfen
                    'Kultur im Löwen',          // anhängen
                    'Kultur im Löwen'           // WHERE organisation
                ));

                return ($affected !== false && $affected > 0)
                    ? "{$affected} Datensätze: addinfos um 'Kultur im Löwen' ergänzt"
                    : null;
            },

            // 🔧 hier kannst du jederzeit weitere Regeln ergänzen
            // function($wpdb, $table) { ... }
        ];

        // Regeln ausführen
        $log = [];
        foreach ($rules as $rule) {
            $msg = $rule($wpdb, $table);
            if ($msg) {
                $log[] = $msg;
            }
        }

        return $log;
    }

    public static function log_new_request($new_id,$data,$src)
    {
        global $wpdb;
        $log_table = $wpdb->prefix .'evmanager_log';
        $target_table = $wpdb->prefix . 'evmanager';


        $result = $wpdb->insert($log_table, [
            'entry_id' => $new_id,
            'source' => $src,
            'data'   => $data,
        ]);

        if ($result) {
            $log_id = $wpdb->insert_id;
            $wpdb->update(
                $target_table,
                [ 'wp_evmanager_log_id' => $log_id ],
                [ 'id' => $new_id ],
                [ '%d' ],
                [ '%d' ]
            );
            error_log('[Sync] Neuer Eintrag im wp_evmanager_log gespeichert.');
        } else {
            error_log('[Sync] Fehler: ' . $wpdb->last_error);
        }
    }

    public static function get_request_log($id)
    {
        global $wpdb;
        $log_table = $wpdb->prefix .'evmanager_log';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$log_table} WHERE id = %d", $id), \ARRAY_A);
        return $row;
    }
}

