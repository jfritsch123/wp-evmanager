<?php
namespace WP_EvManager\Settings;

defined('ABSPATH') || exit;

final class ManagerSettings
{
    private const SCOPE = 'manager';

    /**
     * Aktuelle Settings laden (inkl. Defaults)
     */
    public static function get(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'evmanager_settings';

        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT settings FROM {$table} WHERE scope = %s LIMIT 1",
                self::SCOPE
            )
        );

        $settings = $json ? json_decode($json, true) : [];

        return wp_parse_args($settings, self::defaults());
    }

    /**
     * Settings speichern (komplett ersetzen)
     */
    public static function save(array $settings): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'evmanager_settings';

        $data = [
            'scope'    => self::SCOPE,
            'settings' => wp_json_encode(self::sanitize($settings)),
        ];

        $wpdb->replace(
            $table,
            $data,
            ['%s', '%s']
        );
    }

    /**
     * Einzelnen Wert holen
     */
    public static function get_value(string $key, $default = null)
    {
        $settings = self::get();
        return $settings[$key] ?? $default;
    }

    /**
     * Defaults für Manager
     */
    private static function defaults(): array
    {
        return [
            'locked_statuses' => [],
            'year_limit'      => 'all',
            'status_request_default' => false,
        ];
    }

    /**
     * Sanitizing aller bekannten Settings
     */
    private static function sanitize(array $settings): array
    {
        return [
            'locked_statuses' => array_values(
                array_map(
                    'sanitize_text_field',
                    (array) ($settings['locked_statuses'] ?? [])
                )
            ),
            'year_limit' => self::sanitize_year_limit(
                $settings['year_limit'] ?? 'all'
            ),
            // Standard-Status „Anfrage erhalten“ beim Start aktivieren
            'status_request_default' => !empty($settings['status_request_default']),
        ];
    }

    private static function sanitize_year_limit($val): string
    {
        if ($val === 'all') {
            return 'all';
        }

        return ctype_digit((string) $val) ? (string) $val : 'all';
    }
}
