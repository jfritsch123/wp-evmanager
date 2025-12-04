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

    public static function help_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'evmanager_help';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // -----------------------------
        // Haupttabelle wp_evmanager
        // dieser Code wird nicht ausgeführt !!!
        // -----------------------------
        $table = self::table_name();
        $sql = "CREATE TABLE {$table} (
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
            persons TINYINT NOT NULL,
            email VARCHAR(255) DEFAULT '',
            tel VARCHAR(100) NOT NULL,
            picture VARCHAR(255) DEFAULT '',
            title VARCHAR(255) DEFAULT '',
            place1 VARCHAR(1) DEFAULT '0',
            place2 VARCHAR(1) DEFAULT '0',
            place3 VARCHAR(1) DEFAULT '0',
            soldout VARCHAR(10) DEFAULT NULL,
            processed DATE DEFAULT '0000-00-00',
            editor VARCHAR(255) NOT NULL,
            informed DATE DEFAULT '0000-00-00',
            publish VARCHAR(1) DEFAULT '0',
            status SET('Anfrage erhalten','In Bearbeitung','Gebucht','Vereinbarung unterzeichnet') DEFAULT NULL,
            booked TINYINT(1) NOT NULL,
            organization VARCHAR(255) DEFAULT NULL,
            note LONGTEXT NOT NULL,
            ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(100) NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_fromdate (fromdate),
            KEY idx_todate (todate),
            KEY idx_editor (editor)
        ) {$charset_collate};";
        //dbDelta($sql);

        // -----------------------------
        // Neue Hilfetabelle wp_evmanager_help
        // -----------------------------
        $help_table = self::help_table_name();
        $sql_help = "CREATE TABLE {$help_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            context_key VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY context_key (context_key)
        ) {$charset_collate};";
        dbDelta($sql_help);

        // -----------------------------
        // Version speichern
        // -----------------------------
        update_option(self::OPTION_KEY, self::VERSION);
    }

    public static function maybe_upgrade(): void
    {
        $installed = get_option(self::OPTION_KEY);
        if ($installed !== self::VERSION) {
            self::install();
        }
    }

    public static function maybe_add_trash_column() {
        global $wpdb;

        $table = self::table_name();
        $col = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'trash'");
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN trash TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

}
