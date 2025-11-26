<?php
namespace WP_EvManager\Admin;

defined('ABSPATH') || exit;

final class HelpUI
{
    /**
     * Rendert ein Help-Icon mit context_key
     *
     * @param string $context_key Eindeutiger Schlüssel für den Hilfetext (z.B. 'filter_options')
     * @param string|null $label Optionaler Screenreader-Text / Tooltip
     * @return string HTML
     */
    public static function icon(string $context_key, ?string $label = null): string
    {
        $label = $label ?: __('Hilfe anzeigen', 'wp-evmanager');

        return sprintf(
            '<a href="#" class="wpem-help" data-context="%s" title="%s"><span class="dashicons dashicons-editor-help"></span></a>',
            esc_attr($context_key),
            esc_attr($label)
        );
    }
}
