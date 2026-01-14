<?php
namespace WP_EvManager;

defined('ABSPATH') || exit;

use WP_EvManager\Admin\Admin;
use WP_EvManager\Common\Assets;
use WP_EvManager\Frontend\Frontend;
use WP_EvManager\Admin\Ajax as AdminAjax;
use WP_EvManager\Frontend\WPFormsIntegration;

final class Plugin
{
    private ?AdminAjax $adminAjax = null; // ← nullable + Default
    private Assets $assets;
    private Admin $admin;
    private Frontend $frontend;
    private ?WPFormsIntegration $wpforms = null;

    public function __construct()
    {
        $this->assets = new Assets();
        $this->admin = new Admin();
        $this->frontend = new Frontend();
    }

    public function boot(): void
    {
        // Gemeinsame Assets laden (registrieren)
        add_action('init', [$this->assets, 'register']);

        // WICHTIG: hier den Hook registrieren
        add_action('init', [$this, 'init_wpforms_process_hook']);

        // Admin-spezifische Hooks
        if (is_admin()) {

            //add_action('admin_init', [\WP_EvManager\Admin\AdminMenu::class, 'register_settings']);

            $this->admin->hooks();
            if ($this->adminAjax === null) {
                $this->adminAjax = new AdminAjax();
            }
            $this->adminAjax->hooks();
            \WP_EvManager\Admin\AdminMenu::init();

            \WP_EvManager\Admin\HelpAjax::init();
            \WP_EvManager\Admin\HelpDebug::init();
            \WP_EvManager\Admin\HelpMedia::init();

        }

        // Frontend-spezifische Hooks
        if (!is_admin()) {
            $this->frontend->hooks();
            $this->register_wpforms_integration();
        }

        // Ajax-Hooks (müssen in beiden Fällen laufen)
        $this->frontend->register_ajax();
    }

    private function register_wpforms_integration(): void
    {
        // Falls WPForms nicht aktiv ist: still raus
        if ( ! class_exists('\WPForms\WPForms') && ! function_exists('wpforms') ) {
            return;
        }
        if ( ! class_exists('\WP_EvManager\Frontend\WPFormsIntegration') ) {
            return;
        }

        // IDs beziehen (per Filter überschreibbar)
        $cfg = apply_filters('wpem_wpforms_config', [
            'form_id'       => defined('WPEM_WPFORMS_FORM_ID') ? (int) WPEM_WPFORMS_FORM_ID       : 0,
            'fromdate_field_id' => defined('WPEM_WPFORMS_FROMDATE_ID') ? (int) WPEM_WPFORMS_FROMDATE_ID : 0,
            'todate_field_id' => defined('WPEM_WPFORMS_TODATE_ID') ? (int) WPEM_WPFORMS_TODATE_ID : 0,
            'halls_field_id' => defined('WPFORMS_HALLS_ID') ? (int) WPFORMS_HALLS_ID : 0,

        ]);

        $formId = (int)($cfg['form_id'] ?? 0);
        $fromDateId = (int)($cfg['fromdate_field_id'] ?? 0);
        $toDateId = (int)($cfg['todate_field_id'] ?? 0);
        $hallsId = (int)($cfg['halls_field_id'] ?? 0);
        if ($formId <= 0 || $fromDateId <= 0 || $toDateId <= 0 || $hallsId <= 0) {
            // Nichts konfigurert → Integration überspringen
            return;
        }
        $this->wpforms = new WPFormsIntegration($formId, $fromDateId,$toDateId,$hallsId);
        $this->wpforms->hooks(); // hängt on_form_output, field_properties, AJAX an
    }

    public function init_wpforms_process_hook(): void{
        $submission = new \WP_EvManager\Frontend\WPFormsSubmission(WPEM_WPFORMS_FORM_ID,WPEM_WPFORMS_MAP);
        add_action('wpforms_process_complete', [$submission, 'handle'], 20, 4);
    }
}
