import { validateForm} from "../util/validate.js";
import { collectEditorValues} from '../fields/wysiwyg.js';
import { showOverlay, hideOverlay } from '../util/helper.js';
import {post} from "../data/api.js";
import {initDatePickers} from "../fields/datepickers.js";
import {notice} from "./rendereditor.js";
import {loadList} from "./renderlist.js";
import {renderEventRow} from "./renderlist.js";
import {loadEditor} from "./rendereditor.js";
import {state} from "./filterpanel.js";
import {applyTrashMode} from "./filterhelper.js";
window.$ = window.jQuery;

/**
 * Speichert den Editor via AJAX.
 * @param e
 */
export function saveEditor(e) {
    e.preventDefault();

    const $f = $('#wpem-form');
    const id = Number($f.data('id') || 0);

    // 1) Client-Validierung
    const formEl = $f[0];
    if (!validateForm(formEl)) {
        const firstErr = formEl.querySelector('.wpem-error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    console.debug('[saveEditor] Saving event id=', id);

    // 2) Daten sammeln
    const data = serializeForm($f);

    collectEditorValues(data); // WYSIWYG-Inhalte

    showOverlay();

    // 3) AJAX Save
    post('wpem_save_event', Object.assign({ id }, data))
        .then(res => {
            notice('updated', id ? WPEM.i18n.saved : WPEM.i18n.created);

            const newId = res.id || id;

            console.debug('[saveEditor] Saved event id=', newId, res);

            // 🔄 Nur den betroffenen Event erneut laden
            return post('wpem_get_event', { id: newId })
                .then(ev => {

                    // 🟢 INLINE-UPDATE in der Liste
                    updateEventRow(ev.event);

                    // 🟢 Editor neu laden
                    loadEditor(ev.event.id);

                    // 🟢 Daymap aktualisieren: neue function reloadDayMap()
                    return reloadDayMap();
                });
        })
        /*
        .then(resp2 => {
            if (resp2 && resp2.calendarDays) {
                WPEM.calendarDays = resp2.calendarDays;

                const reinit = () => initDatePickers();
                // falls Editor schon ready: sofort
                if (document.querySelector('#wpem-editor [name="fromdate"]')) {
                    reinit();
                    //console.debug('Reinit pickers after save');
                } else {
                    document.addEventListener('wpem:editor:ready', reinit, { once: true });
                    //console.debug('Waiting for editor ready to reinit pickers');
                }

            }
        })
         */
        .fail(err => {
            if (err && typeof err === 'object' && err.errors) {
                // Server-seitige Validierungsfehler
                formEl.querySelectorAll('.wpem-error').forEach(el => el.classList.remove('wpem-error'));
                formEl.querySelectorAll('.wpem-error-msg').forEach(el => el.remove());

                Object.entries(err.errors).forEach(([name, msg]) => {
                    const field = formEl.querySelector(`[name="${name}"]`);
                    if (!field) return;
                    field.classList.add('wpem-error');
                    const span = document.createElement('span');
                    span.className = 'wpem-error-msg';
                    span.textContent = String(msg || 'Invalid value');
                    field.insertAdjacentElement('afterend', span);
                });

                const firstErr = formEl.querySelector('.wpem-error');
                if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            notice('error', (typeof err === 'string' ? err : (err?.message || 'Error')));
        })
        .always(() => {
            hideOverlay();
        });
}

function serializeForm($f) {
    const o = {};
    $f.serializeArray().forEach(({ name, value }) => {
        if (name.endsWith('[]')) {
            const key = name.slice(0, -2);
            if (!Array.isArray(o[key])) o[key] = [];
            o[key].push(value);
        } else {
            if (o[name] !== undefined) {
                if (!Array.isArray(o[name])) o[name] = [o[name]];
                o[name].push(value);
            } else {
                o[name] = value;
            }
        }
    });
    // persons als Text behandeln, da Datentyp auf text geändert wurde
    o.persons = o.persons === "" ? "" : o.persons;
    o.booked  = Number(o.booked  || 0);
    return o;
}

function removeEventRow(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row && row.parentNode) {
        row.parentNode.removeChild(row);
    }
}

export function updateEventRow(e) {
    const $list = jQuery('#wpem-list');
    const $tbody = $list.find('table tbody');

    // Falls Liste noch nicht geladen
    if (!$tbody.length) return;

    const $existing = $tbody.find(`tr[data-id="${e.id}"]`);

    if ($existing.length) {
        // 🔄 UPDATE
        $existing.replaceWith(renderEventRow(e));
    } else {
        // ➕ INSERT (am Anfang)
        $tbody.prepend(renderEventRow(e));
    }

    // ✨ Highlight
    const $row = $tbody.find(`tr[data-id="${e.id}"]`);
    $row.addClass('wpem-updated');
    setTimeout(() => $row.removeClass('wpem-updated'), 1500);
}
export function moveToTrash(id) {
    if (!confirm(WPEM.i18n.trash)) return;
    if (!id) return;
    showOverlay();
    post('wpem_move_to_trash', { id })
        .then(() => {
            notice('updated', WPEM.i18n.trashed);
            state.activeId = null;
            // Falls Seite leer wurde (z.B. letzte Zeile gelöscht), auf vorige Seite springen
            if ((state.total - 1) <= (state.per_page * (state.page - 1)) && state.page > 1) {
                state.page = state.page - 1;
            }
            loadList();
            $('#wpem-editor').empty();
        })
        .fail(err => notice('error', err))
        .always(() => {
            hideOverlay();
        });
}


$('#wpem-editor').on('click', '.js-restore-from-trash', function () {
    const id = $(this).data('id');
    if (!id) return;

    if (!confirm('Soll dieses Event aus dem Papierkorb wiederhergestellt werden?')) {
        return;
    }

    showOverlay();

    post('wpem_restore_from_trash', { id })
        .then(res => {
            if (!res || !res.id) {
                throw new Error('Ungültige Restore-Antwort');
            }

            // Papierkorb-Modus verlassen
            state.ui.trashMode = false;

            // Fokus merken → Event soll ganz oben erscheinen
            state.ui.restoreFocusId = res.id;

            // Optional: Datum absichern (falls Filter aktiv)
            if (res.fromdate) {
            }

            notice('updated', 'Event wurde wiederhergestellt.');

            // UI korrekt setzen (Filter aktivieren, Trash aus)
            applyTrashMode(res.fromdate);

            // 5⃣ Kalender neu laden
            return reloadDayMap();
        })
        .then(() => {
            // 7️⃣ Editor öffnen
            loadEditor(id);
        })
        .fail(err => {
            //console.error('[restore_from_trash]', err);
            notice(
                'error',
                err?.message || 'Fehler beim Wiederherstellen'
            );
        })
        .always(() => {
            hideOverlay();
        });
});

function reloadDayMap() {
    return post('wpem_reload_daymap', { nonce: WPEM.nonce })
        .then(resp => {
            if (!resp || !resp.calendarDays) return;

            WPEM.calendarDays = resp.calendarDays;

            const reinit = () => initDatePickers();

            if (document.querySelector('#wpem-editor [name="fromdate"]')) {
                reinit();
            } else {
                document.addEventListener('wpem:editor:ready', reinit, { once: true });
            }
        });
}


/* ---------- Bindings ---------- */

$('#wpem-editor').on('click', '.js-save', saveEditor);

$('#wpem-editor').on('click', '.js-duplicate-event', function () {
    const id = $(this).data('id');
    if (!id) return;

    if (!confirm('Soll dieses Event dupliziert werden?')) return;

    showOverlay();

    post('wpem_duplicate_event', { id })
        .then(res => {
            if (!res || !res.id) {
                return $.Deferred().reject('Ungültige Antwort vom Server');
            }

            notice('updated', 'Event wurde dupliziert.');

            // 🧹 Zustand normalisieren
            state.ui.trashMode = false;
            state.ui.restoreFocusId = res.id;

            // Optional: Filter setzen, damit Event sichtbar ist
            /*
            if (res.fromdate) {
                state.filters.fromdate_max = res.fromdate;
            }
            */

            // 🔄 Liste neu laden (zentral!)
            loadList();

            // 📅 Kalender neu laden
            return reloadDayMap();
        })
        .then(() => {
            // 📝 Editor auf neues Event
            loadEditor(state.ui.restoreFocusId);
        })
        .fail(err => {
            console.error('[duplicate_event]', err);
            notice('error', err?.message || err || 'Fehler beim Duplizieren');
        })
        .always(() => {
            hideOverlay();
        });
});


$('#wpem-editor').on('click', '.js-move-to-trash', function () {
    const id = $(this).data('id');
    if (!id) return;

    if (!confirm('Soll dieses Event in den Papierkorb verschoben werden?')) {
        return;
    }

    showOverlay();

    post('wpem_move_to_trash', { id })
        .then(res => {
            notice('updated', 'Event wurde in den Papierkorb verschoben.');

            // Zeile aus der Liste entfernen
            removeEventRow(id);

            // Editor leeren / schließen
            $('#wpem-editor').empty();

            // Daymap aktualisieren
            return reloadDayMap();
        })
        .fail(err => {
            notice('error', (typeof err === 'string' ? err : (err?.message || 'Error')));
        })
        .always(() => {
            hideOverlay();
        });
});

// ❌ Endgültig löschen
jQuery('#wpem-editor').on('click', '.js-delete-final', function () {
    if (!confirm(WPEM.i18n.delete)) return;
    const id = Number($('#wpem-form').data('id') || 0);
    if (!id) return;
    post('wpem_delete_event', { id })
        .then(() => {
            notice('updated', WPEM.i18n.deleted);
            state.activeId = null;
            // Falls Seite leer wurde (z.B. letzte Zeile gelöscht), auf vorige Seite springen
            if ((state.total - 1) <= (state.per_page * (state.page - 1)) && state.page > 1) {
                state.page = state.page - 1;
            }
            loadList();
            $('#wpem-editor').empty();
        })
        .fail(err => notice('error', err));

});


/****** not used yet ******/
function loadListOnSave(fromdate){
    updateSortStateForMode(false);
    state.page = 1;
    $('.wpem-filters-ajax')[0].reset();
    $('.wpem-filters-ajax').find('input[name="status[]"]').prop('checked', false);

    const $input = $('input[name="fromdate_max"]');
    const fp = $input[0]?._flatpickr;
    if (fp) {
        fp.setDate(fromdate, true);
    }

    renderList();
}
function loadListAndFocus(focusId) {
    readFilters();
    post('wpem_list_events', Object.assign({}, state.filters, { page: state.page, per_page: state.per_page }))
        .then(data => {
            renderList(data.items);
            // Prüfen, ob das gespeicherte Event in der Liste ist
            const exists = data.items.some(ev => Number(ev.id) === Number(focusId));
            if (!exists) {
                // 🔸 Wenn nicht vorhanden, Filter zurücksetzen und Liste neu laden
                $('.wpem-filters-ajax')[0].reset();
                state.page = 1;
                post('wpem_list_events', { page:1, per_page: state.per_page })
                    .then(data2 => {
                        renderList(data2.items);
                        highlightActive(focusId);
                        scrollToEvent(focusId);
                    });
            } else {
                highlightActive(focusId);
                scrollToEvent(focusId);
            }
        })
        .fail(err => showError('#wpem-list', err));
}


