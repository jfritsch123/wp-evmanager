jQuery(document).ready(function ($) {

    // Nur auf der Hilfeseite aktiv werden
    if ($('.wrap.wpem-admin-help').length === 0) {
        return;
    }

    console.log('[WPEM] admin-help-upload.js aktiv (Hilfeseite erkannt)');

    /**
     * 1️⃣ Upload-Flag aktivieren, sobald Uploader existiert
     */
    const waitForUploader = setInterval(() => {
        if (window.wp && wp.Uploader && wp.Uploader.defaults) {
            clearInterval(waitForUploader);

            if (!wp.Uploader.defaults.multipart_params) {
                wp.Uploader.defaults.multipart_params = {};
            }
            wp.Uploader.defaults.multipart_params.wpem_help_upload = 1;

            console.log('[WPEM] Uploader-Flag gesetzt (Hilfeseite)');
        }
    }, 400);


    /**
     * 2️⃣ Hook für Mediathek-Queries (query-attachments)
     *     -> sorgt dafür, dass unser Flag auch bei Listenabfragen gesendet wird
     */
    const waitForQuery = setInterval(() => {
        if (window.wp && wp.media && wp.media.model && wp.media.model.Query) {
            clearInterval(waitForQuery);

            const origSync = wp.media.model.Query.prototype.sync;

            wp.media.model.Query.prototype.sync = function (method, model, options) {
                options = options || {};
                options.data = options.data || {};

                if (method === 'read') {
                    options.data.wpem_help_upload = 1;
                    console.log('[WPEM] Flag zu query-attachments hinzugefügt:', options.data);
                }

                return origSync.call(this, method, model, options);
            };

            console.log('[WPEM] Query-Hook aktiviert (Hilfeseite)');
        }
    }, 400);


    /**
     * 3️⃣ Fallback – falls der Editor das Modal früher öffnet
     */
    $(document).on('click', '.wp-editor-wrap .insert-media', function () {
        if (window.wp && wp.Uploader && wp.Uploader.defaults) {
            if (!wp.Uploader.defaults.multipart_params) {
                wp.Uploader.defaults.multipart_params = {};
            }
            wp.Uploader.defaults.multipart_params.wpem_help_upload = 1;
            console.log('[WPEM] Upload-Flag beim Klick reaktiviert');
        }
    });


    /**
     * 4️⃣ Flag nach Schließen des Modals entfernen
     */
    $(document).on('click', '.media-modal-close, .media-modal-backdrop', function () {
        if (window.wp && wp.Uploader && wp.Uploader.defaults && wp.Uploader.defaults.multipart_params) {
            delete wp.Uploader.defaults.multipart_params.wpem_help_upload;
            console.log('[WPEM] Upload-Flag entfernt (Modal geschlossen)');
        }
    });
});
