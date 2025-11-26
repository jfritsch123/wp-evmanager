<?php
namespace WP_EvManager\Common;

defined('ABSPATH') || exit;

final class Assets
{
    public function register(): void
    {
        // Admin
        // wp_register_script('datepicker', WPEVMANAGER_URL.'assets/js/admin/fields/datepickers.js', WPEVMANAGER_VERSION, true);
        wp_register_style('wpem-admin', \WPEVMANAGER_URL . 'assets/css/admin.css', [], \WPEVMANAGER_VERSION);
        wp_register_script('wpem-admin', \WPEVMANAGER_URL . 'assets/js/admin/admin.js', ['jquery'], \WPEVMANAGER_VERSION, true);
        wp_register_script('wpem-help', \WPEVMANAGER_URL . 'assets/js/admin/admin-help.js', ['jquery'], \WPEVMANAGER_VERSION, true);

        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 3);

        // Flatpickr
        wp_register_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_register_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_register_script('flatpickr-de', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js', [], '4.6.13', true);
        //wp_register_script('flatpickr-monthselect','https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js', ['jquery'], '4.6.13', true);

        // Frontend
        wp_register_style('wpem-frontend', \WPEVMANAGER_URL . 'assets/css/frontend.css', [], \WPEVMANAGER_VERSION);
        //wp_register_script('wpem-frontend', \WPEVMANAGER_URL . 'assets/js/frontend/frontend.js', ['jquery'], \WPEVMANAGER_VERSION, true);
        // JS
        wp_register_script(
            'wpem-frontend-datepicker',
            \WPEVMANAGER_URL . 'assets/js/frontend/datepicker.js',
            ['flatpickr','jquery'],
            \WPEVMANAGER_VERSION,
            true
        );
        add_action( 'wpforms_frontend_js', [$this,'wpforms_datepicker_locale'], 10 );
    }

    /**
     * @param $tag
     * @param $handle
     * @param $src
     * @return array|mixed|string|string[]
     * Add type="module" to specific script
     */
    public function add_module_type($tag, $handle, $src) {
        if ( 'wpem-admin' === $handle || 'wpem-frontend-datepicker' === $handle ) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    public function wpforms_datepicker_locale( $forms ) {
        //if ( true === wpforms_has_field_type( 'date-time', $forms, true )){
            wp_enqueue_script(
                'wpforms-datepicker-locale',
                'https://npmcdn.com/flatpickr@4.6.13/dist/l10n/de.js',
                array( 'wpforms-flatpickr' ),
                null,
                true
            );
        //}
    }
}
