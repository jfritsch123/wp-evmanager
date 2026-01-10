<?php
/**
 * Plugin Name: WP EvManager
 * Plugin URI: https://github.com/jfritsch123/wp-evmanager
 * Description: Klassenbasiertes Event-Plugin (Admin/Frontend getrennt).
 * Version: 1.2.1.4
 * Author: Joe Fritsch
 * GitHub Plugin URI: https://github.com/jfritsch123/wp-evmanager
 * GitHub Branch: main
 * Text Domain: wp-evmanager
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('WPEVMANAGER_FILE', __FILE__);
define('WPEVMANAGER_DIR', plugin_dir_path(__FILE__));
define('WPEVMANAGER_URL', plugin_dir_url(__FILE__));
define('WPEVMANAGER_VERSION', '0.1.9');

// Fallbacks for WordPress result type constants when static analyzers or non-WP
// environments don't predefine them. WordPress defines these globally, so this
// is a no-op on normal runtime, but prevents "Undefined constant" issues.
if (!defined('OBJECT'))  { define('OBJECT',  'OBJECT'); }
if (!defined('OBJECT_K')){ define('OBJECT_K','OBJECT_K'); }
if (!defined('ARRAY_A')) { define('ARRAY_A','ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N','ARRAY_N'); }

define('WPEM_WPFORMS_FORM_ID', 14);         // deine Formular-ID
define('WPEM_WPFORMS_FROMDATE_ID',  30);     // Feld-ID des Datumfeldes fromdate
define('WPEM_WPFORMS_TODATE_ID',  31);       // Feld-ID des Datumfeldes todate
define('WPFORMS_HALLS_ID',  24);           // Feld-ID der Checkboxgruppe place1,place2,place3


// Globale Mapping-Konstante für WPForms
if ( ! defined('WPEM_WPFORMS_MAP') ) {
    define('WPEM_WPFORMS_MAP', [
        'fromdate'  => 30,
        'fromtime'  => 7,
        'todate'    => 31,
        'totime'    => 8,
        'descr3'    => 19,
        'organizer' => 14,
        'persons'   => 40,
        'email'     => 17,
        'tel'       => 16,
        'type'      => 11,

        // places => Feld 24 (speziell behandelt)
    ]);
}
require_once WPEVMANAGER_DIR . 'includes/Autoloader.php';
WP_EvManager\Autoloader::register('WP_EvManager', WPEVMANAGER_DIR . 'includes/');

register_activation_hook(__FILE__, [WP_EvManager\Setup\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [WP_EvManager\Setup\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', function () {
    // i18n
    //load_plugin_textdomain('wp-evmanager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    // Zentrales Plugin starten
    (new WP_EvManager\Plugin())->boot();
});



