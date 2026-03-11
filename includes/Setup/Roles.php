<?php

namespace WP_EvManager\Setup;

defined('ABSPATH') || exit;

final class Roles
{
    // Granular, falls du später feiner steuern willst:
    public const CAPS = [
        'evm_read_events',
        'evm_create_events',
        'evm_edit_own_events',
        'evm_delete_own_events',
        'evm_edit_all_events',
        'evm_delete_all_events',
        'evm_manage_help',
        'evm_manage_settings',
    ];

    /**
     * Rollen anlegen (bei Aktivierung oder init)
     */
    public static function add_roles(): void
    {
        // Volle Rechte für alle Events
        add_role(
            'event_manager_all',
            __('Ev Manager', 'wp-evmanager'),
            [
                'read' => true,
            ]
        );

        // Nur eigene Events
        add_role(
            'event_manager_own',
            __('Ev Manager Organization', 'wp-evmanager'),
            [
                'read' => true,
            ]
        );
    }

    /**
     * Capabilities zuweisen
     */
    public static function add_caps(): void
    {
        // Administrator: alles
        if ($role = get_role('administrator')) {
            foreach (self::CAPS as $cap) {
                $role->add_cap($cap);
            }
            $role->add_cap('upload_files'); // wichtig für Medien-Upload
        }

        // Event Manager ALL: alles
        if ($role = get_role('event_manager_all')) {
            foreach (self::CAPS as $cap) {
                $role->add_cap($cap);
            }
            $role->add_cap('upload_files'); // wichtig für Medien-Upload

            // Zusätzliche Editor-Rechte hinzufügen (ohne manage_options)
            if ($editor_role = get_role('editor')) {
                foreach ($editor_role->capabilities as $cap => $value) {
                    if ($cap !== 'manage_options') {
                        $role->add_cap($cap, $value);
                    }
                }
            }

            // Google Site Kit Zugriff erlauben
            $role->add_cap('googlesitekit_manage_options');
            $role->add_cap('googlesitekit_setup');
            $role->add_cap('googlesitekit_authenticate');

            // User Management erlauben (um event_manager_all user anzulegen)
            $role->add_cap('list_users');
            $role->add_cap('create_users');
            $role->add_cap('edit_users');
            $role->add_cap('promote_users');
            $role->add_cap('delete_users');

            // Elementor Theme Builder & Editor Zugriff
            $role->add_cap('elementor_edit_design');
            $role->add_cap('elementor_manage_templates');
            $role->add_cap('elementor_manage_global_settings');
            $role->add_cap('elementor_manage_site_settings');
            $role->add_cap('elementor_edit_site_settings');
            $role->add_cap('elementor_edit_page');
            $role->add_cap('elementor_admin_access');
            $role->add_cap('elementor_manage_settings');
            $role->add_cap('elementor_role_manager');
            $role->add_cap('elementor_manage_library');
            $role->add_cap('elementor_edit_library');

            // Elementor Library (Templates) CPT Caps
            $role->add_cap('edit_elementor_library');
            $role->add_cap('edit_others_elementor_library');
            $role->add_cap('publish_elementor_library');
            $role->add_cap('read_elementor_library');
            $role->add_cap('delete_elementor_library');
            $role->add_cap('delete_others_elementor_library');

            // WordPress Standard-Rechte für Theme Builder & Content
            $role->add_cap('edit_theme_options');
            $role->add_cap('unfiltered_html');
            $role->add_cap('manage_categories');
            $role->add_cap('manage_links');

            // Elementor Pro Features Simulation
            $role->add_cap('elementor_pro_theme_builder');
            $role->add_cap('elementor_pro_conditions_manager');

            // Yoast SEO Zugriff erlauben
            $role->add_cap('wpseo_manage_options');
            $role->add_cap('wpseo_manage_redirects');
            $role->add_cap('wpseo_edit_advanced_metadata');
            $role->add_cap('wpseo_bulk_edit');
        }

        // Event Manager OWN: nur eigene
        if ($role = get_role('event_manager_own')) {
            $role->add_cap('evm_read_events');
            $role->add_cap('evm_create_events');
            $role->add_cap('evm_edit_own_events');
            $role->add_cap('evm_delete_own_events');
            $role->add_cap('evm_manage_help');
            $role->add_cap('evm_manage_settings');
            $role->add_cap('upload_files'); // damit auch eigene Bilder hochgeladen werden können
        }
    }

    /**
     * Rollen entfernen (z. B. bei Deaktivierung)
     */
    public static function remove_roles(): void
    {
        remove_role('event_manager_all');
        remove_role('event_manager_own');
    }

    /**
     * Caps von allen Rollen entfernen (Admin bleibt unangetastet)
     */
    public static function remove_caps(): void
    {
        foreach (['event_manager_all', 'event_manager_own', 'administrator'] as $role_name) {
            if ($role = get_role($role_name)) {
                foreach (self::CAPS as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Beschränkt die editierbaren Rollen für event_manager_all
     * (Wird über Hook in Plugin.php eingebunden)
     */
    public static function filter_editable_roles(array $roles): array
    {
        $user = wp_get_current_user();
        if (!$user || !in_array('event_manager_all', (array)$user->roles, true)) {
            return $roles;
        }

        // Nur event_manager_all erlauben
        $allowed = ['event_manager_all'];
        foreach ($roles as $role_key => $role_data) {
            if (!in_array($role_key, $allowed, true)) {
                unset($roles[$role_key]);
            }
        }

        return $roles;
    }

    /**
     * Erlaubt event_manager_all das Editieren/Löschen von Usern mit der gleichen Rolle
     */
    public static function map_meta_cap($caps, $cap, $user_id, $args)
    {
        // Wir greifen nur ein, wenn es explizit um User-Operationen geht
        if (in_array($cap, ['edit_user', 'delete_user', 'promote_user', 'remove_user'], true)) {
            $current_user = wp_get_current_user();
            if ($current_user && in_array('event_manager_all', (array)$current_user->roles, true)) {
                $target_user_id = $args[0] ?? null;

                // WICHTIG: Wenn wir KEINE Target-ID haben (z.B. beim Listen der User), 
                // lassen wir die Standard-Caps (die wir vergeben haben) durch.
                if (!$target_user_id) {
                    return $caps;
                }

                $target_user = get_userdata($target_user_id);
                if (!$target_user) {
                    return $caps;
                }

                // SICHERHEIT: Wenn der Ziel-User ein Administrator ist (oder manage_options hat),
                // darf ein event_manager_all diesen NIEMALS bearbeiten.
                if (in_array('administrator', (array)$target_user->roles, true) || $target_user->has_cap('manage_options')) {
                    return ['do_not_allow'];
                }

                // Wenn der Ziel-User ebenfalls event_manager_all ist, erlauben wir es (mappen auf 'read')
                if (in_array('event_manager_all', (array)$target_user->roles, true)) {
                    return ['read']; 
                }

                // Alles andere (andere Rollen wie 'editor', 'subscriber', 'event_manager_own') verbieten
                return ['do_not_allow'];
            }
        }
        return $caps;
    }

    /**
     * Dynamische Berechtigungsprüfung für Elementor
     */
    public static function user_has_cap($allcaps, $caps, $args, $user)
    {
        if (empty($allcaps['event_manager_all'])) {
            return $allcaps;
        }

        // Wenn nach 'manage_options' gefragt wird, erlauben wir es virtuell,
        // sofern wir uns im Elementor-Kontext befinden.
        if (isset($caps[0]) && $caps[0] === 'manage_options') {
            
            // Check ob wir in einem Elementor Request sind
            $is_elementor = false;
            
            // 1. Elementor Editor / Theme Builder URL (GET-Parameter)
            if (isset($_GET['action']) && in_array($_GET['action'], ['elementor', 'elementor_pro_theme_builder'], true)) {
                $is_elementor = true;
            }

            // 2. Elementor-Menü im Admin (page=elementor-...)
            if (isset($_GET['page']) && (strpos($_GET['page'], 'elementor') !== false || strpos($_GET['page'], 'elementor_pro') !== false)) {
                $is_elementor = true;
            }

            // 3. Post-Typ-Prüfung (Templates sind elementor_library)
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_library') {
                $is_elementor = true;
            }
            
            // 4. Elementor AJAX Actions
            if (defined('DOING_AJAX') && constant('DOING_AJAX')) {
                if (isset($_POST['action']) && strpos($_POST['action'], 'elementor_') === 0) {
                    $is_elementor = true;
                }
                // Manche Elementor-Requests nutzen andere AJAX-Pfade oder Header
                if (isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'elementor_') === 0) {
                    $is_elementor = true;
                }
            }

            // 5. Elementor Editor Screen (via Referer oder Preview-Mode)
            if (isset($_REQUEST['elementor-preview'])) {
                $is_elementor = true;
            }

            // 6. REST API (Elementor nutzt REST für Daten)
            if (defined('REST_REQUEST') && constant('REST_REQUEST')) {
                if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'elementor') !== false) {
                    $is_elementor = true;
                }
            }

            // 7. Theme Builder AJAX spezifisch (manche Pro Aktionen)
            if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['elementor_ajax', 'elementor_pro_ajax'], true)) {
                $is_elementor = true;
            }

            if ($is_elementor) {
                $allcaps['manage_options'] = true;
            }

            // 8. Yoast SEO Kontext Prüfung
            $is_yoast = false;
            if (isset($_GET['page']) && (strpos($_GET['page'], 'wpseo_') === 0 || strpos($_GET['page'], 'yoast-seo') !== false)) {
                $is_yoast = true;
            }
            if (defined('DOING_AJAX') && constant('DOING_AJAX') && isset($_POST['action']) && strpos($_POST['action'], 'wpseo_') === 0) {
                $is_yoast = true;
            }

            if ($is_yoast) {
                $allcaps['manage_options'] = true;
            }
        }

        return $allcaps;
    }

    /**
     * Versteckt Admin-Menüpunkte für diese Rolle, falls sie durch manage_options sichtbar werden
     */
    public static function hide_admin_menus(): void
    {
        $user = wp_get_current_user();
        if (!$user || !in_array('event_manager_all', (array)$user->roles, true)) {
            return;
        }

        // Diese Menüs wollen wir verstecken, auch wenn der User manage_options (temporär) hat
        remove_menu_page('options-general.php'); // Einstellungen
        remove_menu_page('plugins.php');         // Plugins
        remove_menu_page('tools.php');           // Werkzeuge
        remove_menu_page('edit.php?post_type=acf-field-group'); // ACF falls vorhanden
        remove_menu_page('index.php');           // Dashboard (optional, meistens will man es behalten)
        
        // EvManager spezifische Admin-Menüs (Hilfe und DB) für Manager verstecken
        remove_submenu_page('wp-evmanager', 'wpem-help');
        remove_submenu_page('wp-evmanager', 'wpem-db');

        // Zusätzliche Plugins verstecken, die der User nicht sehen soll
        remove_menu_page('wpforms-overview'); // WPForms
        remove_menu_page('duplicator');       // Duplicator
        remove_menu_page('debug-log-viewer');  // Debug Log Viewer
        
        // Google Site Kit (falls es unter einer anderen URL liegt)
        // Aber der User soll ja Google Site Kit nutzen dürfen, also lassen wir das eventuell drin.
    }

    /**
     * Stellt sicher, dass Elementor die Rolle nicht einschränkt
     */
    public static function filter_elementor_restrictions(array $restrictions): array
    {
        $user = wp_get_current_user();
        if ($user && in_array('event_manager_all', (array)$user->roles, true)) {
            // Wir entfernen alle Einschränkungen für diese Rolle
            return [];
        }
        return $restrictions;
    }
}

