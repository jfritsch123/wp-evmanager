<?php
namespace WP_EvManager\Admin;

defined('ABSPATH') || exit;

final class HelpMedia
{
    public static function init(): void
    {
        // Upload-Verzeichnis für Hilfebilder
        add_filter('upload_dir', [__CLASS__, 'custom_upload_dir']);

        // Meta setzen beim Upload
        add_action('add_attachment', [__CLASS__, 'mark_help_media']);

        // Filter für Media Modal
        add_filter('ajax_query_attachments_args', [__CLASS__, 'filter_media_query']);

        // Script nur auf der Hilfe-Seite laden
        add_action('admin_enqueue_scripts', function($hook) {
            wp_enqueue_script(
                'wpem-help-upload',
                plugins_url('../../assets/js/admin/admin-help-upload.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );
        });
    }

    /**
     * Upload-Verzeichnis ändern, wenn Hilfebild-Flag aktiv
     */
    public static function custom_upload_dir(array $uploads): array
    {
        if (!empty($_REQUEST['wpem_help_upload'])) {
            $subdir = '/wpem-help';
            $uploads['path']   = $uploads['basedir'] . $subdir;
            $uploads['url']    = $uploads['baseurl'] . $subdir;
            $uploads['subdir'] = $subdir;
            self::log('custom_upload_dir: using subdir ' . $subdir);
        } else {
            self::log('custom_upload_dir: using default upload dir');
        }

        return $uploads;
    }

    /**
     * Neue Medien als "Hilfebild" markieren
     */
    public static function mark_help_media(int $post_id): void
    {

        if (!empty($_REQUEST['wpem_help_upload'])) {
            update_post_meta($post_id, '_wpem_help_media', 1);
            self::log("add_attachment: _wpem_help_media=1 SET on {$post_id}");
        } else {
            self::log("add_attachment: _wpem_help_media NOT set (flag missing) on {$post_id}");
        }
    }

    /**
     * Media Modal filtern (Ajax)
     */
    public static function filter_media_query($args) {
        $flag = $_REQUEST['wpem_help_upload'] ?? null;
        //error_log('[WPEM HelpMedia] ajax_query_attachments_args: wpem_help_upload=' . var_export($flag, true));

        if ($flag == 1) {
            // Nur Hilfebilder anzeigen
            $args['meta_query'][] = [
                'key'     => '_wpem_help_media',
                'value'   => 1,
                'compare' => '='
            ];
        } else {
            // Hilfebilder in normaler Mediathek ausblenden
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'     => '_wpem_help_media',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => '_wpem_help_media',
                    'value'   => 0,
                    'compare' => '='
                ]
            ];
        }
        return $args;
    }


    private static function log(string $msg): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('[WPEM HelpMedia] ' . $msg);
        }
    }

}
