jQuery(document).ready(function($) {
    if (typeof wp === 'undefined' || !wp.media || !wp.media.view) {
        return;
    }

    const l10n = wp.media.view.l10n;

    // Frame überschreiben/erweitern
    const OrigFrame = wp.media.view.MediaFrame.Post;

    wp.media.view.MediaFrame.Post = OrigFrame.extend({
        initialize: function() {
            OrigFrame.prototype.initialize.apply(this, arguments);

            // normalen Library-State behalten
            const lib = this.state('library');

            // neuen State für Hilfebilder hinzufügen
            this.states.add([
                new wp.media.controller.Library({
                    id: 'wpem_help',
                    title: l10n.wpemHelpTab || 'Hilfebilder',
                    priority: 30,
                    toolbar: 'main-insert',
                    filterable: 'all',
                    library: wp.media.query({
                        post_mime_type: 'image',
                        orderby: 'date',
                        order: 'DESC',
                        meta_query: [{
                            key: '_wpem_help_media',
                            value: 1,
                            compare: '='
                        }]
                    }),
                    multiple: false
                })
            ]);
        },

        browseRouter: function(routerView) {
            routerView.set({
                library: {
                    text: l10n.mediaLibraryMenuTitle || 'Mediathek',
                    priority: 20,
                },
                wpem_help: {
                    text: l10n.wpemHelpTab || 'Hilfebilder',
                    priority: 30,
                }
            });
        }
    });
});
