import { WYSIWYG_FIELDS } from '../ui/form_schema.js';
 /**
 * Initialisiert die TinyMCE-Editoren für die WYSIWYG-Felder.
 */
export function initEditors() {
    if (!window.wp || !wp.editor || !window.tinyMCE) return;
    WYSIWYG_FIELDS.forEach(id => {
        const existing = window.tinyMCE.get(id);
        if (existing) existing.remove();
        wp.editor.initialize(id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo,removeformat,wpem_help',
                toolbar2: '',
                menubar: false,
                statusbar: true,
                height: (id === 'note') ? 260 : 220,
                setup: function(editor) {
                    editor.addButton('wpem_help', {
                        title: 'Hilfe anzeigen',
                        icon: 'help',
                        onclick: function() {
                            const context = 'link_external';
                            if (typeof ajaxurl !== 'undefined') {
                                jQuery.get(ajaxurl, { action: 'wpem_help', context }, function(response) {
                                    if (response.success) {
                                        jQuery('#wpem-help-modal .wpem-help-title').text(response.data.title);
                                        jQuery('#wpem-help-modal .wpem-help-content').html(response.data.content);
                                        jQuery('#wpem-help-overlay, #wpem-help-modal').fadeIn(200);
                                    } else {
                                        alert('Keine Hilfe gefunden.');
                                    }
                                });
                            }
                        }
                    });
                }
            },
            quicktags: false,
            mediaButtons: false
        });
    });
}

/**
 * Zerstört die TinyMCE-Editoren für die WYSIWYG-Felder.
 */
export function destroyEditors() {
    if (!window.tinyMCE) return;
    WYSIWYG_FIELDS.forEach(id => {
        const ed = window.tinyMCE.get(id);
        if (ed) ed.remove();
    });
}


export function collectEditorValues(target) {
    WYSIWYG_FIELDS.forEach(id => {
        const ed = window.tinyMCE && window.tinyMCE.get(id);
        if (ed && !ed.isHidden()) {
            target[id] = ed.getContent();
        } else {
            const el = document.getElementById(id);
            target[id] = el ? el.value : '';
        }
    });
}
