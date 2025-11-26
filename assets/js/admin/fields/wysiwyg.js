import { WYSIWYG_FIELDS } from '../ui/form_schema.js';
/**
 * Initialisiert die TinyMCE-Editoren für die WYSIWYG-Felder.
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
                toolbar1: 'formatselect,bold,italic,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo,removeformat',
                toolbar2: '',
                menubar: false,
                statusbar: true,
                height: (id === 'note') ? 260 : 220
            },
            quicktags: true,
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
