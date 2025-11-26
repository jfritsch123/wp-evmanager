<?php
namespace WP_EvManager\Admin;

defined('ABSPATH') || exit;

final class HelpDebug
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'upload.php', // Unter Mediathek
            __('Hilfebilder Debug', 'wp-evmanager'),
            __('Hilfebilder Debug', 'wp-evmanager'),
            'manage_options',
            'wpem-help-debug',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void
    {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm.meta_value, p.guid
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
              ON p.ID = pm.post_id AND pm.meta_key = '_wpem_help_media'
            WHERE p.post_type = 'attachment'
            ORDER BY p.ID DESC
            LIMIT 50
        ");

        ?>
        <div class="wrap">
            <h1>Hilfebilder Debug</h1>
            <p>Zeigt die letzten 50 Attachments mit Meta-Key <code>_wpem_help_media</code>.</p>

            <table class="widefat striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>_wpem_help_media</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->ID); ?></td>
                        <td><?php echo esc_html($row->post_title); ?></td>
                        <td><?php echo $row->meta_value ? esc_html($row->meta_value) : '<em>nicht gesetzt</em>'; ?></td>
                        <td><a href="<?php echo esc_url($row->guid); ?>" target="_blank">Ansehen</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

