<?php
// in includes/Admin/HelpAjax.php
namespace WP_EvManager\Admin;

use WP_EvManager\Database\Repositories\HelpRepository;
use WP_EvManager\Database\Repositories\EventRepository;

defined('ABSPATH') || exit;

final class HelpAjax
{
    public static function init(): void
    {
        add_action('wp_ajax_wpem_help', [__CLASS__, 'handle']);
    }

    public static function handle(): void
    {
        if (!current_user_can('manage_options')) {
            //wp_send_json_error('No permission');
        }

        $context = sanitize_text_field($_GET['context'] ?? '');
        if (!$context) {
            wp_send_json_error('Missing context');
        }

        $repo = new HelpRepository();
        $help = $repo->find_by_context($context);

        if (!$help) {
            wp_send_json_error('No help found');
        }

        wp_send_json_success([
            'title' => $help['title'],
            'content' => stripslashes(apply_filters('the_content',$help['content'])),
        ]);
    }

}

