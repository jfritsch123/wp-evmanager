<?php
namespace WP_EvManager\Frontend;

use WP_EvManager\Database\Repositories\EventRepository;

defined('ABSPATH') || exit;

final class Frontend
{
    public function hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_shortcode('evm_events', [$this, 'shortcode_events']);

    }

    public function register_ajax(): void
    {
        add_action('wp_ajax_nopriv_evm_load_events', [$this, 'ajax_load_events']);
        add_action('wp_ajax_evm_load_events', [$this, 'ajax_load_events']);
        // Ajax-Handler: Event-Details laden
        add_action('wp_ajax_nopriv_evm_load_event_detail', [$this, 'ajax_load_event_detail']);
        add_action('wp_ajax_evm_load_event_detail', [$this, 'ajax_load_event_detail']);
    }

    public function enqueue(): void
    {
        wp_enqueue_style('wpem-frontend', \WPEVMANAGER_URL . 'assets/css/frontend.css', [], \WPEVMANAGER_VERSION);

        //wp_enqueue_style('wpem-frontend');
        wp_enqueue_script(
            'wpem-frontend',
            plugins_url('/assets/js/frontend/frontend.js', dirname(__DIR__)),
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('wpem-frontend', 'WPEM_Frontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wpem_frontend'),
        ]);
    }

    /**
     * Shortcode [evm_events]
     */
    public function shortcode_events(): string
    {
        $repo = new \WP_EvManager\Database\Repositories\EventRepository();
        $groups = $repo->find_grouped_by_month(3, 0);

        ob_start();
        ?>
        <div id="evm-events">
            <?php
            $offset = 0;
            include $this->locate_template('events-list.php');
            ?>
        </div>
        <div class="evm-events-more">
            <button type="button"
                    class="evm-load-more"
                    data-offset="0">
                Weitere Veranstaltungen
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_load_events_test(): void
    {
        //check_ajax_referer('wpem_frontend', 'nonce');

        wp_send_json_success([
            'ok'     => 1,
            'offset' => (int)($_POST['offset'] ?? -1),
        ]);
    }

    /**
     * Ajax-Handler: Weitere Events laden
     */
    public function ajax_load_events(): void
    {
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

        $repo = new \WP_EvManager\Database\Repositories\EventRepository();
        $groups = $repo->find_grouped_by_month(3, $offset);

        if (empty($groups)) {
            wp_send_json_success('');
        }

        ob_start();
        include $this->locate_template('events-list.php');
        wp_send_json_success(ob_get_clean());
    }


    public function ajax_load_event_detail(): void
    {
        check_ajax_referer('wpem_frontend', 'nonce');

        $id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Ungültige Event-ID' . $id]);
        }

        $repo  = new \WP_EvManager\Database\Repositories\EventRepository();
        $event = $repo->get($id);

        if (!$event || empty($event['publish']) || $event['publish'] === '0') {
            wp_send_json_error(['message' => 'Event nicht gefunden oder nicht veröffentlicht.']);
        }

        ob_start();
        $template = $this->locate_template('events-detail.php');
        $item = $event;
        include $template;
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'id'   => $id,
        ]);
    }

    private function locate_template(string $file): string
    {
        // Prüfen, ob im Theme überschrieben
        $theme_template = locate_template('evm/' . $file);
        if ($theme_template) {
            return $theme_template;
        }

        // Standard im Plugin
        return plugin_dir_path(__DIR__) . '../templates/' . $file;
    }

}
