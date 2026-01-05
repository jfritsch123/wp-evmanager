<?php
namespace WP_EvManager\Database;

defined('ABSPATH') || exit;

final class Schema
{
    const OPTION_KEY = 'wpem_db_version';
    const VERSION = '1.2.0'; // bump, weil neue Tabelle

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'evmanager';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /* =========================================================
         * 1. wp_evmanager (Haupttabelle, OHNE status-SET in dbDelta)
         * ========================================================= */
        $table_events = $wpdb->prefix . 'evmanager';

        $sql_events = "CREATE TABLE {$table_events} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        fromdate DATE NOT NULL,
        fromtime VARCHAR(10) DEFAULT '',
        todate DATE DEFAULT '0000-00-00',
        totime VARCHAR(10) DEFAULT '',
        descr1 TEXT,
        descr2 TEXT,
        descr3 TEXT,
        short VARCHAR(500) DEFAULT '',
        organizer VARCHAR(255) DEFAULT '',
        persons VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT '',
        tel VARCHAR(100) NOT NULL,
        picture VARCHAR(255) DEFAULT '',
        title VARCHAR(255) DEFAULT '',
        type VARCHAR(255) NOT NULL,
        place1 VARCHAR(1) DEFAULT '0',
        place2 VARCHAR(1) DEFAULT '0',
        place3 VARCHAR(1) DEFAULT '0',
        places VARCHAR(255) NOT NULL,
        soldout VARCHAR(10) DEFAULT NULL,
        processed DATETIME DEFAULT '0000-00-00 00:00:00',
        editor VARCHAR(255) NOT NULL,
        informed DATE DEFAULT '0000-00-00',
        publish VARCHAR(1) DEFAULT '0',
        booked TINYINT(1) NOT NULL,
        organization VARCHAR(255) DEFAULT NULL,
        note LONGTEXT NOT NULL,
        ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip VARCHAR(100) NOT NULL,
        wpforms_entry_id INT UNSIGNED NOT NULL,
        wp_evmanager_log_id INT NOT NULL,
        import DATETIME DEFAULT NULL,
        trash TINYINT(1) NOT NULL DEFAULT 0,
        duplicated_from INT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;";

        dbDelta($sql_events);

        /* =========================================================
         * 1a. status SET(...) – MANUELL, NICHT über dbDelta
         * ========================================================= */
        $has_status = $wpdb->get_var("
            SHOW COLUMNS FROM `{$table_events}` LIKE 'status'
        ");

        if (!$has_status) {
            $wpdb->query("
            ALTER TABLE `{$table_events}`
            ADD COLUMN status SET(
                'Anfrage erhalten',
                'In Bearbeitung',
                'Gebucht',
                'Vereinbarung unterzeichnet'
            ) DEFAULT NULL
            AFTER place3
        ");
        }
        $has_addinfos = $wpdb->get_var("
            SHOW COLUMNS FROM `{$table_events}` LIKE 'addinfos'
        ");

        if (!$has_addinfos) {
            $wpdb->query("
                ALTER TABLE `{$table_events}`
                ADD COLUMN addinfos SET('Alles','Kultur im Löwen') DEFAULT NULL
                AFTER publish
            ");
        }

        /* =========================================================
         * 2. wp_evmanager_help
         * ========================================================= */
        $table_help = $wpdb->prefix . 'evmanager_help';

        $sql_help = "CREATE TABLE {$table_help} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        context_key VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY context_key (context_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        dbDelta($sql_help);

        /* =========================================================
         * 3. wp_evmanager_history
         * ========================================================= */
        $table_history = $wpdb->prefix . 'evmanager_history';

        $sql_history = "CREATE TABLE {$table_history} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id BIGINT UNSIGNED NOT NULL,
        editor VARCHAR(255) NOT NULL,
        changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        changes JSON NOT NULL,
        PRIMARY KEY (id),
        KEY idx_event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        dbDelta($sql_history);

        /* =========================================================
         * 3. wp_evmanager_settings – neue für Version 1.2.0
         * ========================================================= */
        $table_settings = $wpdb->prefix . 'evmanager_settings';
        $sql = "
        CREATE TABLE {$table_settings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(50) NOT NULL,
            settings LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY scope (scope)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        /* =========================================================
         * Logging & Version
         * ========================================================= */
        if (!empty($wpdb->last_error)) {
            error_log('[WP_EvManager][DB] ' . $wpdb->last_error);
        }

        dbDelta($sql);

        // Default-Datensatz für Manager anlegen (falls noch nicht vorhanden)
        $wpdb->query(
            $wpdb->prepare(
                "
            INSERT IGNORE INTO {$table_settings} (scope, settings)
            VALUES (%s, %s)
            ",
                'manager',
                wp_json_encode([
                    'locked_statuses' => [],
                    'year_limit'      => 'all',
                ])
            )
        );

        update_option(self::OPTION_KEY, self::VERSION);
    }

    public static function maybe_upgrade(): void
    {
        $installed = get_option(self::OPTION_KEY);
        if ($installed !== self::VERSION) {
            self::install();
        }
    }

}
