<?php
/**
 * Admin Page (AJAX+WYSIWYG)
 *
 * - Linke Spalte: Filter + Liste (AJAX via admin-ajax.php)
 * - Rechte Spalte: Editor-Form (AJAX geladen/gespeichert)
 * - WYSIWYG: descr1 & descr2 via wp.editor (TinyMCE + Quicktags)
 */

namespace WP_EvManager\Admin;

use WP_EvManager\Security\Permissions;
use WP_EvManager\Database\Repositories\EventRepository;
use WP_EvManager\Database\Schema;
use WP_EvManager\Settings\ManagerSettings;

defined('ABSPATH') || exit;

final class Admin
{
    /**
     * Register all admin hooks.
     */
    public function hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue'],10,1);
    }

    /**
     * Lädt nur auf der EvManager-Seite die nötigen Assets.
     * - Script ist ein ES-Module (index.js mit Imports)
     * - Editor/Media für WYSIWYG & Mediathek
     * - Lokalisierte Daten für AJAX/i18n/Caps
     */
    public function enqueue(string $hook): void
    {
        if ($hook !== 'toplevel_page_wp-evmanager') {
            return; // nur auf unserer Admin-Seite laden
        }

        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');

        // WYSIWYG & Media
        wp_enqueue_editor();
        wp_enqueue_media();

        // Help-Modal
        wp_enqueue_script('wpem-help');

        // flatpickr
        wp_enqueue_script('flatpickr');
        wp_enqueue_script('flatpickr-de');
        wp_enqueue_script('datepicker');
        wp_enqueue_style('flatpickr');

        // Styles/Scripts (wurden in Assets::register() registriert)
        wp_enqueue_style('wpem-admin');
        wp_enqueue_script('wpem-admin');

        $repo   = new EventRepository();
        $today  = date('Y-m-d'); //new \DateTimeImmutable('today');   // << hier das heutige Datum
        $dayMap = $repo->getDayMapSince($today);

        // Lokalisierte Daten
        wp_localize_script('wpem-admin', 'WPEM', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wpem_ajax'),
            'i18n'    => [
                'empty'       => __('Keine Events gefunden.', 'wp-evmanager'),
                'delete'      => __('Event inwiderruflich Löschen', 'wp-evmanager'),
                'trash'       => __('Diesen Event in den Papierkorb ?', 'wp-evmanager'),
                'created'     => __('Event angelegt.', 'wp-evmanager'),
                'saved'       => __('Event gespeichert.', 'wp-evmanager'),
                'deleted'     => __('Event gelöscht.', 'wp-evmanager'),
                'trashed'     => __('Event in den Papierkorb verschoben.', 'wp-evmanager'),
                'loading'     => __('Lade Daten …', 'wp-evmanager'),
                'filter'      => __('Filter', 'wp-evmanager'),
                'reset'       => __('Reset', 'wp-evmanager'),
                'new'         => __('Neuer Event', 'wp-evmanager'),
                'search'      => __('Suche …', 'wp-evmanager'),
                'from'        => __('Von', 'wp-evmanager'),
                'to'          => __('Bis', 'wp-evmanager'),
                'editor'      => __('Editor (login)', 'wp-evmanager'),
                'insertImage' => __('Bild einfügen','wp-evmanager'),
                'changeImage' => __('Bild ändern','wp-evmanager'),
                'removeImage' => __('Löschen','wp-evmanager'),
                'imageField'  => __('Bild','wp-evmanager'),
            ],
            'caps' => [
                'canCreate'  => \WP_EvManager\Security\Permissions::can_create(),
                'canEditAll' => \WP_EvManager\Security\Permissions::can_edit_all(),
            ],
            'calendarDays' => $dayMap, // <<— globale Variable
            'calendarMode' => [
                'type'  => 'since',
                'since' => $today,
            ],
            'lockedStatuses' => ManagerSettings::get_value('locked_statuses', []),
            'status_request_default' => ManagerSettings::get_value('status_request_default'),
        ]);

        $ym   = $repo->get_years_months_named();
        wp_localize_script('wpem-admin', 'WPEM_FILTER', [
            'years'        => $ym['years'],
            'monthsByYear' => $ym['monthsByYear'],
        ]);
    }

    /**
     * Render the two-column AJAX admin UI.
     * Left: Filters + list placeholder
     * Right: Editor placeholder
     */
    public function render_page(): void
    {
        if (!Permissions::can_read()) {
            wp_die(__('You are not allowed to view events.', 'wp-evmanager'), 403);
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('EvManager', 'wp-evmanager') . '</h1>';

        // Container: zwei Spalten
        echo '<div class="wpem-grid">';

        // ----- Linke Spalte: Filter + Liste (per AJAX befüllt) -----
        echo '<div class="wpem-col wpem-col--left">';

        // Filter-Form (AJAX)
        ?>
        <form class="wpem-filters-ajax" onsubmit="return false;" aria-label="<?php echo esc_attr__('Event filters', 'wp-evmanager'); ?>">
            <?php $this->render_filters();?>
        </form>

        <div id="wpem-list" class="wpem-list" aria-live="polite" aria-busy="true"></div>
        <?php
        echo '</div>'; // .wpem-col--left

        // ----- Rechte Spalte: Editor-Container (per AJAX befüllt) -----
        echo '<div class="wpem-col wpem-col--right">';
        echo '<div id="wpem-editor" class="wpem-editor" aria-live="polite" aria-busy="true"></div>';
        echo '</div>'; // .wpem-col--right

        echo '</div>'; // .wpem-grid
        echo '</div>'; // .wrap
    }

    protected function render_filters(): void {
        echo '<label><h3>Filtermöglichkeiten ' . \WP_EvManager\Admin\HelpUI::icon('filter_options') . '</h3></label>';
        ?>
        <div class="wpem-filter-grid">
            <!-- Linke Spalte -->
            <div class="wpem-col">
                <fieldset class="wpem-searchset">
                    <legend>Suche</legend>
                    <div class="wpem-row">
                        <input type="text" name="q" class="wpem-input" placeholder="Suche …">
                        <?php //$this->render_editor_list(); ?>
                        <input type="button" class="button" name="qsend" class="wpem-input" value="Suchen" />
                        <input type="button" class="button" name="qreset" class="wpem-input" value="Suche löschen" />
                    </div>
                </fieldset>

                <!-- Datumsbereich -->
                <fieldset class="wpem-daterange">
                    <legend>Datumsbereich Start bis </legend>
                    <div class="wpem-row">
                        <!--Von<input type="date" name="fromdate_min" placeholder="DD.MM.YYYY">-->
                        <input type="date" name="fromdate_max" style="width:50%" placeholder="DD.MM.YYYY">
                        <input type="button" class="button" style="width:50%" name="fromdate_max_reset" value="Datum löschen">

                    </div>
                    <!--
                    <label class="wpem-inline" style="margin-top:.5rem">
                        <input type="checkbox" name="start_ab_today" checked>
                        <span>Ab heute</span>
                    </label>
                    -->
                </fieldset>

                <!-- Jahr / Monat -->
                <fieldset class="wpem-yearmonth">
                    <legend>Jahr / Monat</legend>
                    <div class="wpem-row">
                        <select name="filter_year" style="width:50%;">
                            <option value=""><?php esc_html_e('Jahr wählen', 'wp-evmanager'); ?></option>
                            <!-- Optionen füllen wir per JS aus WPEM_FILTER.years -->
                        </select>
                        <select name="filter_month" style="width:50%;" disabled>
                            <option value=""><?php esc_html_e('Monat wählen', 'wp-evmanager'); ?></option>
                            <!-- wird per JS abhängig vom Jahr gefüllt -->
                        </select>
                    </div>
                </fieldset>

                <fieldset class="wpem-status">
                    <legend>Status</legend>

                    <label class="anfrage">
                        <input type="checkbox" name="status[]" value="Anfrage erhalten">
                        <span class="label-text">Anfrage erhalten</span>
                        <span class="dashicons dashicons-yes-alt anfrage" title="Anfrage erhalten"></span>
                    </label>

                    <label class="bearbeitung">
                        <input type="checkbox" name="status[]" value="In Bearbeitung">
                        <span class="label-text">In Bearbeitung</span>
                        <span class="dashicons dashicons-yes-alt bearbeitung" title="In Bearbeitung"></span>
                    </label>

                    <br>

                    <label class="gebucht">
                        <input type="checkbox" name="status[]" value="Gebucht">
                        <span class="label-text">Gebucht</span>
                        <span class="dashicons dashicons-yes-alt gebucht" title="Gebucht"></span>
                    </label>

                    <label class="vereinbarung">
                        <input type="checkbox" name="status[]" value="Vereinbarung unterzeichnet">
                        <span class="label-text">Vereinbarung unterzeichnet</span>
                        <span class="dashicons dashicons-yes-alt vereinbarung" title="Vereinbarung unterzeichnet"></span>
                    </label>
                </fieldset>
            </div>

            <!-- Rechte Spalte -->
            <div class="wpem-col">

                <fieldset class="wpem-halls">
                    <legend>Saalbelegung</legend>

                    <div class="wpem-group-grid">
                        <div class="wpem-matrix">
                            <div class="wpem-matrix-row wpem-matrix-head">
                                <div class="wpem-matrix-cell"></div>
                                <div class="wpem-matrix-cell wpem-matrix-col">Gr</div> <!-- place1 -->
                                <div class="wpem-matrix-cell wpem-matrix-col">Kl</div> <!-- place2 -->
                                <div class="wpem-matrix-cell wpem-matrix-col">Fo</div>        <!-- place3 -->
                            </div>

                            <!-- Frei -->
                            <div class="wpem-matrix-row">
                                <div class="wpem-matrix-cell wpem-matrix-label">Frei</div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place1_0" name="place1" value="0">
                                    <label for="f_place1_0" aria-label="place1-0"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place2_0" name="place2" value="0">
                                    <label for="f_place2_0" aria-label="place2-0"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place3_0" name="place3" value="0">
                                    <label for="f_place3_0" aria-label="place3-0"></label>
                                </div>
                            </div>

                            <!-- Optional -->
                            <div class="wpem-matrix-row">
                                <div class="wpem-matrix-cell wpem-matrix-label">Optional</div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place1_1" name="place1" value="1">
                                    <label for="f_place1_1" aria-label="place1-1"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place2_1" name="place2" value="1">
                                    <label for="f_place2_1" aria-label="place2-1"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place3_1" name="place3" value="1">
                                    <label for="f_place3_1" aria-label="place3-1"></label>
                                </div>
                            </div>

                            <!-- Gebucht -->
                            <div class="wpem-matrix-row">
                                <div class="wpem-matrix-cell wpem-matrix-label">Gebucht</div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place1_2" name="place1" value="2">
                                    <label for="f_place1_2" aria-label="place1-2"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place2_2" name="place2" value="2">
                                    <label for="f_place2_2" aria-label="place2-2"></label>
                                </div>
                                <div class="wpem-matrix-cell">
                                    <input type="radio" id="f_place3_2" name="place3" value="2">
                                    <label for="f_place3_2" aria-label="place3-2"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wpem-mt">
                        <label><input type="checkbox" name="booked_only" value="1"> Ausgebucht</label>
                    </div>
                </fieldset>

                <fieldset class="wpem-special-filters">
                    <legend>Sonderfilter</legend>
                    <label>
                        <input type="checkbox" class="wpem-filter-trash" name="trash">
                        Papierkorb anzeigen
                    </label>
                </fieldset>

                <!-- Buttons jetzt hier unten -->
                <p class="wpem-actions">
                    <button type="button" class="button js-wpem-reset">Alle Filter zurücksetzen</button>
                    <br>
                    <button type="button" class="button button-secondary js-wpem-new">Neuanlage Event</button>
                </p>

            </div>
        </div>

        <div id="wpem-help-overlay"></div>

        <div id="wpem-help-modal">
            <div class="wpem-help-header">
                <h2 class="wpem-help-title">Hilfe</h2>
                <button class="close" aria-label="Schließen">✖</button>
            </div>
            <div class="wpem-help-content">
                <!-- Ajax-Content -->
            </div>
        </div>
        <?php
    }

    protected function render_editor_list(): void {
        global $wpdb;
        $table = Schema::table_name();
        // Alle distinct editor-Logins aus der Event-Tabelle holen
        $editors = $wpdb->get_col("SELECT DISTINCT editor FROM {$table} WHERE editor <> '' ORDER BY editor ASC");

        $current_editor = '';
        if (Permissions::can_edit_own(wp_get_current_user()->user_login) && !Permissions::can_edit_all()) {
            // Nur eigene Events → Vorauswahl
            $current_editor = Permissions::current_login(); // oder wp_get_current_user()->user_login
        }

        ?>
        <select name="editor" class="wpem-input">
            <option value=""><?php esc_html_e('Editor wählen', 'wp-evmanager'); ?></option>
            <?php foreach ($editors as $editor): ?>
                <option value="<?php echo esc_attr($editor); ?>"
                    <?php selected($editor, $current_editor); ?>>
                    <?php echo esc_html($editor); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

}
