jQuery(function ($) {
    let offset = 1; // wir zeigen im Shortcode die ersten 3 Monate, daher starten wir bei 1

    $('.evm-events-more').on('click', '.evm-load-more', function () {

        const $btn = $(this);
        $btn.prop('disabled', true).text('Lade...');
        $.post(WPEM_Frontend.ajaxurl, {
            action: 'evm_load_events',
            nonce: WPEM_Frontend.nonce,
            offset: offset
        }).done(function (resp) {
            if (resp.success) {
                $('.evm-events-list').append(resp.data.html);
                offset = resp.data.offset;
                $btn.prop('disabled', false).text('Weitere Veranstaltungen');
            } else {
                $btn.hide();
            }
        }).fail(function () {
            alert('Fehler beim Laden.');
            $btn.prop('disabled', false).text('Weitere Veranstaltungen');
        });
    });
});


jQuery(function ($) {
    const popupId = 349; // deine Popup-ID

    function insertDetailHtml(html) {
        // Suche das neue Popup im DOM
        const $modal = $('#elementor-popup-modal-' + popupId);
         if ($modal.length) {
            // Hier das Element finden, in das du den Inhalt einfügst
            $modal.find('#event-detail-widget').html(html);
            return true;
        }
        return false;
    }

    // Beobachte DOM-Veränderungen
    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            if (m.type === 'childList') {
                for (const node of m.addedNodes) {
                    if (node.nodeType === 1) {
                        const el = node;
                        if (el.classList.contains('elementor-popup-modal')) {
                             // Wenn dein Popup mit ID oder data-id übereinstimmt
                            const id = el.getAttribute('id').match(/\d+$/)?.[0];
                            if (String(id) === String(popupId)) {
                                 // Popup ist geladen — falls du schon HTML hast, einfügen
                                if (window.__evm_detail_html) {
                                    insertDetailHtml(window.__evm_detail_html);
                                }
                            }
                        }
                    }
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

});

let currentEventId = null; // globale Variable merken

window.addEventListener('elementor/popup/show', function(e) {
    if (!currentEventId) return; // nichts zu tun

    const detail = e.detail || {};
    const popupId = detail.id;
    const instance = detail.instance;
    //console.debug('Popup geöffnet (native):', popupId, instance);

    // Event-ID kannst du z. B. als data-Attribut am Trigger setzen:
    // <a class="evm-open-popup" data-event-id="123" data-popup="349">
    const trigger = instance ? instance.getSettings('triggers') : null;
    const $activeTrigger = jQuery('.evm-open-popup[data-popup="' + popupId + '"]').last();
    //console.debug('Aktiver Trigger:', $activeTrigger);

    loadEventDetails(popupId, currentEventId);
});


jQuery(function ($) {
    $(document).on('click', '.evm-open-popup', function (e) {
        e.preventDefault();

        const popupId = $(this).data('popup');   // z. B. 349
        currentEventId = $(this).data('event-id'); // <- richtige ID vom Klick

        //console.debug('Open popup manually:', popupId, currentEventId);

        if (typeof elementorProFrontend !== 'undefined' &&
            elementorProFrontend.modules.popup) {
            elementorProFrontend.modules.popup.showPopup({ id: popupId });
        }
    });
});

/**
 * Lädt Event-Details via AJAX und schreibt sie ins Popup.
 */
function loadEventDetails(popupId, eventId) {
    //console.debug('Load Event Details:', { popupId, eventId });
    //console.debug('WPEM : ', WPEM_Frontend);
    if (!eventId) return;

    jQuery.post(WPEM_Frontend.ajaxurl, {
        action: 'evm_load_event_detail',
        nonce: WPEM_Frontend.nonce,
        event_id: eventId
    }).done(function (resp) {
        if (resp.success) {
            // Ziel-Container im Popup suchen (z.B. HTML Widget mit ID)
            const $popup = jQuery('#elementor-popup-modal-' + popupId);
            const $content = $popup.find('.evm-popup-content');

            if ($content.length) {
                $content
                    .hide()
                    .html(resp.data.html)
                    .fadeIn(1000);
            } else {
                //console.warn('Kein Zielcontainer .evm-popup-content gefunden!');
            }
        } else {
            //console.error('Fehler bei Event-Details:', resp.data);
        }
    }).fail(function () {
        //console.error('AJAX-Fehler beim Laden der Event-Details');
    });
}

document.addEventListener('wpformsReady', function() {
    console.debug('WPForms ready - initialisiere intlTelInput für Telefonfelder');
    const phoneInputs = document.querySelectorAll('.wpforms-field-phone input[type="tel"]');

    phoneInputs.forEach(function(input) {
        // Prüfen, ob intlTelInput vorhanden ist
        if (window.intlTelInputGlobals && window.intlTelInputGlobals.getInstance) {
            const iti = window.intlTelInputGlobals.getInstance(input);
            if (iti) {
                // Wir müssen das Input neu initialisieren, da WPForms keine preferredCountries erlaubt
                const currentConfig = iti.getConfig ? iti.getConfig() : {};
                iti.destroy(); // alte Instanz löschen

                window.intlTelInput(input, {
                    ...currentConfig,
                    preferredCountries: ['at', 'ch', 'de', 'li'], // gewünschte Länder zuerst
                    separateDialCode: true,                       // optional: Vorwahl sichtbar
                });
            }
        }
    });
});
