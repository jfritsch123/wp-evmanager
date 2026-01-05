<?php
namespace WP_EvManager\Admin;

use WP_EvManager\Database\Repositories\HelpRepository;

defined('ABSPATH') || exit;

final class AdminMenu
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);

    }

    public static function register_settings(): void{
        register_setting('wpem_settings', 'wpem_locked_statuses', [
            'type' => 'array',
            'sanitize_callback' => function($val) {
                return array_map('sanitize_text_field', (array)$val);
            },
            'default' => [],
        ]);
        // Anzeige ab Jahr
        register_setting('wpem_settings', 'wpem_year_limit', [
            'type'              => 'string',
            'sanitize_callback' => function ($val) {
                // Erlaubt "all" oder eine Ganzzahl (Jahr)
                if ($val === 'all') {
                    return 'all';
                }
                return ctype_digit((string) $val) ? (string) $val : 'all';
            },
            'default'           => 'all',
        ]);
    }

    public static function register_menu(): void
    {
        $admin = new \WP_EvManager\Admin\Admin();
        // Submenu: Dashboard / Übersicht (zeigt dieselbe Seite wie das Hauptmenü)
        add_menu_page(
            __('EvManager', 'wp-evmanager'),
            __('EvManager', 'wp-evmanager'),
            'evm_read_events', // Anzeige, feingranular prüfen wir innerhalb von render_page()
            'wp-evmanager',
            [$admin, 'render_page'],
            'dashicons-calendar',
            26
        );
        // Ersten Unterpunkt „Events“ anlegen → zeigt dieselbe Seite wie Hauptmenü
        add_submenu_page(
            'wp-evmanager',
            __('Events', 'wp-evmanager'),   // page_title
            __('Events', 'wp-evmanager'),   // menu_title (Anzeige im Submenu)
            'read',
            'wp-evmanager',                 // Slug bleibt gleich wie Hauptmenü!
            [$admin, 'render_page']
        );

        // Submenu: DB-Tools
        add_submenu_page(
            'wp-evmanager',
            __('EvManager DB', 'wp-evmanager'),
            __('EvManager DB', 'wp-evmanager'),
            'manage_options',
            'wpem-db',
            [__CLASS__, 'render_db_page']
        );

        // Submenu: Hilfe-System
        add_submenu_page(
            'wp-evmanager',
            __('EvManager Hilfe', 'wp-evmanager'),
            __('EvManager Hilfe', 'wp-evmanager'),
            'manage_options',
            'wpem-help',
            [__CLASS__, 'render_help_page']
        );

        // Submenu: Einstellungen
        add_submenu_page(
            'wp-evmanager',
            __('Einstellungen', 'wp-evmanager'),
            __('Einstellungen', 'wp-evmanager'),
            'evm_manage_settings',
            'wpem-settings',
            [__CLASS__, 'render_settings_page']
        );

        // Submenu: Rollen & Rechte
        add_submenu_page(
            'wp-evmanager',
            __('Rollen & Rechte', 'wp-evmanager'),
            __('Rollen & Rechte', 'wp-evmanager'),
            'manage_options',
            'wpem-roles',
            [__CLASS__, 'render_roles_page']
        );
    }

    /**
     * DB-Seite (wie schon eingebaut)
     */
    public static function render_db_page__(): void
    {
        ?>
        <div class="wrap wpem-admin-db">
            <h1><?php esc_html_e('Eventmanager Datenbank', 'wp-evmanager'); ?></h1>

            <p><?php esc_html_e('Hier kannst du Tools für die Eventmanager-Datenbank nutzen.', 'wp-evmanager'); ?></p>

            <h2><?php esc_html_e('Import', 'wp-evmanager'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wpem_db_import_action', 'wpem_db_nonce'); ?>
                <p>
                    <button type="submit" name="wpem_db_import" class="button button-primary"
                            onclick="return confirm('Achtung: Bestehende Daten werden überschrieben. Fortfahren?');">
                        <?php esc_html_e('Datenbank Import', 'wp-evmanager'); ?>
                    </button>
                    <button type="submit" name="wpem_db_dryrun" class="button">
                        <?php esc_html_e('Dry Run (Simulation)', 'wp-evmanager'); ?>
                    </button>
                </p>
            </form>

            <h2><?php esc_html_e('Datenbank Tools', 'wp-evmanager'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wpem_db_tools_action', 'wpem_db_tools_nonce'); ?>
                <p>
                    <button type="submit" name="wpem_db_create_history" class="button button-secondary"
                            onclick="return confirm('Soll die History-Tabelle neu erzeugt werden? (bestehende Daten bleiben erhalten)');">
                        <?php esc_html_e('History-Tabelle erzeugen', 'wp-evmanager'); ?>
                    </button>
                    <button type="submit" name="wpem_db_clear" class="button button-secondary"
                            onclick="return confirm('Achtung: Alle Events werden gelöscht. Fortfahren?');">
                        <?php esc_html_e('Events leeren', 'wp-evmanager'); ?>
                    </button>

                </p>
            </form>
        </div>
        <?php

        // --- POST-Aktionen behandeln ---
        if (
            isset($_POST['wpem_db_nonce']) &&
            wp_verify_nonce($_POST['wpem_db_nonce'], 'wpem_db_import_action')
        ) {
            $result = null;

            if (isset($_POST['wpem_db_import'])) {
                $result = \WP_EvManager\Database\DBTools::import_from_loewensaal(false);
            } elseif (isset($_POST['wpem_db_dryrun'])) {
                $result = \WP_EvManager\Database\DBTools::import_from_loewensaal(true);
            }

            if (!empty($result)) {
                $class = $result['success'] ? 'notice-success' : 'notice-error';
                echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        if (
            isset($_POST['wpem_db_tools_nonce']) &&
            wp_verify_nonce($_POST['wpem_db_tools_nonce'], 'wpem_db_tools_action')
        ) {
            if (isset($_POST['wpem_db_create_history'])) {
                global $wpdb;
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $charset_collate = $wpdb->get_charset_collate();
                $history_table = $wpdb->prefix . 'evmanager_history';

                $sql_history = "CREATE TABLE {$history_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            editor VARCHAR(255) NOT NULL,
            changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            changes JSON NOT NULL,
            PRIMARY KEY (id),
            KEY idx_event_id (event_id),
            KEY idx_changed_at (changed_at)
        ) {$charset_collate};";

                dbDelta($sql_history);

                echo '<div class="notice notice-success"><p>' . esc_html__('History-Tabelle erzeugt oder aktualisiert.', 'wp-evmanager') . '</p></div>';
            }
        }



    }


    public static function render_db_page(): void
    {
        ?>
        <div class="wrap wpem-admin-db">
            <h1><?php esc_html_e('Eventmanager Datenbank', 'wp-evmanager'); ?></h1>

            <p><?php esc_html_e('Hier kannst du Tools für die Eventmanager-Datenbank nutzen.', 'wp-evmanager'); ?></p>

            <h2><?php esc_html_e('Import', 'wp-evmanager'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wpem_db_import_action', 'wpem_db_nonce'); ?>
                <p>
                    <button type="submit" name="wpem_db_import" class="button button-primary"
                            onclick="return confirm('Achtung: Bestehende Daten werden überschrieben. Fortfahren?');">
                        <?php esc_html_e('Datenbank Import', 'wp-evmanager'); ?>
                    </button>
                    <button type="submit" name="wpem_db_dryrun" class="button">
                        <?php esc_html_e('Dry Run (Simulation)', 'wp-evmanager'); ?>
                    </button>
                </p>
            </form>

            <h2><?php esc_html_e('Datenbank Tools', 'wp-evmanager'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wpem_db_tools_action', 'wpem_db_tools_nonce'); ?>
                <p>
                    <button type="submit" name="wpem_db_create_history" class="button button-secondary"
                            onclick="return confirm('Soll die History-Tabelle neu erzeugt werden? (bestehende Daten bleiben erhalten)');">
                        <?php esc_html_e('History-Tabelle erzeugen', 'wp-evmanager'); ?>
                    </button>
                    <button type="submit" name="wpem_db_clear" class="button button-secondary"
                            onclick="return confirm('Achtung: Alle Events werden gelöscht. Fortfahren?');">
                        <?php esc_html_e('Events leeren', 'wp-evmanager'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php

        // --- POST-Aktionen: Import ---
        if (
            isset($_POST['wpem_db_nonce']) &&
            wp_verify_nonce($_POST['wpem_db_nonce'], 'wpem_db_import_action')
        ) {
            $result = null;

            if (isset($_POST['wpem_db_import'])) {
                $result = \WP_EvManager\Database\DBTools::import_from_loewensaal(false);
            } elseif (isset($_POST['wpem_db_dryrun'])) {
                $result = \WP_EvManager\Database\DBTools::import_from_loewensaal(true);
            }

            if (!empty($result)) {
                $class = $result['success'] ? 'notice-success' : 'notice-error';
                echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        // --- POST-Aktionen: DB Tools ---
        if (
            isset($_POST['wpem_db_tools_nonce']) &&
            wp_verify_nonce($_POST['wpem_db_tools_nonce'], 'wpem_db_tools_action')
        ) {
            global $wpdb;

            // History-Tabelle anlegen
            if (isset($_POST['wpem_db_create_history'])) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $charset_collate = $wpdb->get_charset_collate();
                $history_table = $wpdb->prefix . 'evmanager_history';

                $sql_history = "CREATE TABLE {$history_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_id BIGINT UNSIGNED NOT NULL,
                editor VARCHAR(255) NOT NULL,
                changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changes JSON NOT NULL,
                PRIMARY KEY (id),
                KEY idx_event_id (event_id),
                KEY idx_changed_at (changed_at)
            ) {$charset_collate};";

                dbDelta($sql_history);

                echo '<div class="notice notice-success"><p>' . esc_html__('History-Tabelle erzeugt oder aktualisiert.', 'wp-evmanager') . '</p></div>';
            }

            // Events leeren
            if (isset($_POST['wpem_db_clear'])) {
                $table = $wpdb->prefix . 'evmanager';
                $wpdb->query("TRUNCATE TABLE {$table}");
                echo '<div class="notice notice-success"><p>' . esc_html__('Alle Events wurden gelöscht.', 'wp-evmanager') . '</p></div>';
                $table = $wpdb->prefix . 'evmanager_history';
                $wpdb->query("TRUNCATE TABLE {$table}");
                echo '<div class="notice notice-success"><p>' . esc_html__('Alle History Einträge wurden gelöscht.', 'wp-evmanager') . '</p></div>';
            }
        }
    }

    public static function render_help_page(): void
    {
        $repo = new \WP_EvManager\Database\Repositories\HelpRepository();

        // --- Speichern ---
        if (isset($_POST['wpem_help_save']) && check_admin_referer('wpem_help_action')) {
            $repo->insert_or_update(
                sanitize_text_field($_POST['context_key']),
                sanitize_text_field($_POST['title']),
                wp_kses_post($_POST['content'])
            );
            echo '<div class="notice notice-success"><p>✅ Hilfetext gespeichert.</p></div>';
        }

        // --- Löschen ---
        if (isset($_GET['delete']) && check_admin_referer('wpem_help_delete')) {
            $repo->delete(sanitize_text_field($_GET['delete']));
            echo '<div class="notice notice-success"><p>🗑️ Hilfetext gelöscht.</p></div>';
        }

        // --- Bearbeiten ---
        $edit_item = null;
        if (isset($_GET['edit'])) {
            $edit_item = $repo->find_by_context(sanitize_text_field($_GET['edit']));
        }

        // --- Laden ---
        $all = $repo->all();

        ?>
        <div class="wrap wpem-admin-help">
            <h1><?php esc_html_e('Eventmanager Hilfe', 'wp-evmanager'); ?></h1>

            <h2><?php echo $edit_item ? 'Hilfetext bearbeiten' : 'Neuen Hilfetext anlegen'; ?></h2>

            <form method="post">
                <?php wp_nonce_field('wpem_help_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="context_key">Context Key</label></th>
                        <td>
                            <input type="text" name="context_key" id="context_key"
                                   value="<?php echo esc_attr($edit_item['context_key'] ?? ''); ?>"
                                   class="regular-text" <?php echo $edit_item ? 'readonly' : ''; ?> required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="title">Titel</label></th>
                        <td>
                            <input type="text" name="title" id="title"
                                   value="<?php echo esc_attr($edit_item['title'] ?? ''); ?>"
                                   class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="content">Inhalt</label></th>
                        <td>
                            <?php
                            // Inhalt vorbereiten
                            $content_value = $edit_item['content'] ?? '';
                            $editor_id = 'content';

                            // WYSIWYG Einstellungen
                            $settings = [
                                'textarea_name' => 'content',
                                'media_buttons' => true, // wichtig für Upload
                                'textarea_rows' => 12,
                                'teeny' => false,
                                'tinymce' => [
                                    'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,table',
                                    'toolbar2' => 'undo,redo,removeformat,pastetext,code',
                                    'block_formats' => 'Absatz=p;Überschrift 2=h2;Überschrift 3=h3;Überschrift 4=h4',
                                ],
                            ];

                            // Editor rendern
                            wp_editor($content_value, $editor_id, $settings);

                            // Hidden Field: Flag für Upload
                            echo '<input type="hidden" name="wpem_help_upload" value="1">';
                            ?>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="wpem_help_save" class="button button-primary" value="Speichern"></p>
            </form>

            <hr>

            <h2><?php esc_html_e('Bestehende Hilfetexte', 'wp-evmanager'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th>Context Key</th>
                    <th>Titel</th>
                    <th>Inhalt (gekürzt)</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($all): ?>
                    <?php foreach ($all as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['context_key']); ?></td>
                            <td><?php echo esc_html($row['title']); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags($row['content']), 15)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['edit' => $row['context_key']])); ?>" class="button">Bearbeiten</a>
                                <a href="<?php echo esc_url(remove_query_arg(['edit','delete'])); ?>" class="button">Neu</a>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['delete' => $row['context_key']]), 'wpem_help_delete'); ?>"
                                   class="button-link-delete" onclick="return confirm('Wirklich löschen?');">Löschen</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Keine Hilfetexte vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_roles_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung.', 'wp-evmanager'));
        }

        global $wp_roles;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Rollen & Rechte', 'wp-evmanager') . '</h1>';

        // --- Teil A: Rollenübersicht ---
        echo '<h2>' . esc_html__('Rollenübersicht', 'wp-evmanager') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Rolle</th><th>Eventmanager-Capabilities</th></tr></thead>';
        echo '<tbody>';

        foreach ($wp_roles->roles as $role_key => $role_data) {
            $name = translate_user_role($role_data['name']);
            $caps = array_keys(array_filter($role_data['capabilities']));

            // Nur evm_* Caps herausfiltern
            $evm_caps = array_filter($caps, fn($c) => str_starts_with($c, 'evm_'));

            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong><br><code>' . esc_html($role_key) . '</code></td>';
            echo '<td><code>' . implode(', ', array_map('esc_html', $evm_caps)) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // --- Teil B: Benutzerübersicht ---
        echo '<h2 style="margin-top:2em;">' . esc_html__('Benutzerübersicht', 'wp-evmanager') . '</h2>';

        $users = get_users(); // liefert WP_User Objekte

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Benutzer</th><th>Rollen</th><th>Eventmanager-Capabilities</th></tr></thead>';
        echo '<tbody>';

        foreach ($users as $user) {
            /** @var \WP_User $user */
            $roles = $user->roles ?? [];
            $caps  = array_keys(array_filter($user->allcaps ?? []));

            // Nur evm_* Caps herausfiltern
            $evm_caps = array_filter($caps, fn($c) => str_starts_with($c, 'evm_'));

            echo '<tr>';
            echo '<td><strong>' . esc_html($user->display_name) . '</strong><br><code>' . esc_html($user->user_login) . '</code></td>';
            echo '<td>' . implode(', ', array_map('esc_html', $roles)) . '</td>';
            echo '<td><code>' . implode(', ', array_map('esc_html', $evm_caps)) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '</div>';
    }



    public static function render_settings_page(): void
    {?>
        <h1><?php esc_html_e('Eventmanager Einstellungen', 'wp-evmanager'); ?></h1>
        <h2><?php esc_html_e('Schreibschutz-Einstellungen', 'wp-evmanager'); ?></h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpem_settings');
            $locked_statuses = (array) get_option('wpem_locked_statuses', []);
            $all_statuses = ['Anfrage erhalten','In Bearbeitung','Gebucht','Vereinbarung unterzeichnet'];
            ?>
            <?php foreach ($all_statuses as $s): ?>
                <label>
                    <input type="checkbox" name="wpem_locked_statuses[]" value="<?php echo esc_attr($s); ?>"
                        <?php checked(in_array($s, $locked_statuses, true)); ?>>
                    <?php echo esc_html($s); ?>
                </label><br>
            <?php endforeach; ?>
            <h2><?php esc_html_e('Anzeige-Einstellungen', 'wp-evmanager'); ?></h2>
            <?php
            // --- Dropdown für "Events ab Jahr" ---
            $year_limit = get_option('wpem_year_limit', 'all'); // Default = Alle Jahre
            $repo = new \WP_EvManager\Database\Repositories\EventRepository();
            $ym   = $repo->get_years_months_named(false);
            $years = $ym['years'] ?? [];
            echo 'year-limit=' . esc_html($year_limit);
            ?>
            <label for="wpem_year_limit"><?php esc_html_e('Events anzeigen ab Jahr:', 'wp-evmanager'); ?></label>
            <select name="wpem_year_limit" id="wpem_year_limit">
                <option value="all" <?php selected($year_limit, 'all'); ?>>
                    <?php esc_html_e('Alle Jahre', 'wp-evmanager'); ?>
                </option>
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo esc_attr($y); ?>" <?php selected((string)$year_limit, (string)$y); ?>>
                        <?php echo esc_html($y); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(); ?>
        </form>
    <?php
    }
}
