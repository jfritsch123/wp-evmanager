import { FORM_SCHEMA} from './form_schema.js';
import { escapeHtml,formatUTCToLocalYMDHMS,spinnerHTML} from '../util/helper.js';
import { fieldStatusGroup,fieldAddInfosGroup,fieldPlaceMatrix,fieldPicture,fieldPublishGroup,fieldYesNo,fieldHistoryLoader,fieldRequestLoader,fieldSaveButton,fieldDayEvents,fieldOrganization } from '../fields/controls.js';
import { fieldText, fieldTextarea, fieldTextReadonly, fieldNumber, fieldDate} from '../fields/controls.js';
import { initEditors, destroyEditors} from '../fields/wysiwyg.js';
import { initDatePickers } from '../fields/datepickers.js';
import { post } from '../data/api.js';
import { state} from './filterpanel.js';
import { saveEditor } from '../ui/saveeditor.js';
import { moveToTrash } from '../ui/saveeditor.js';
import { waitForPickers,setPickerDate,setPickerMinDate } from '../util/initflatpicker.js';
import { loadHistory} from '../util/history.js';

window.$ = window.jQuery;

/**
 * Rendert den Editor anhand des Schemas.
 * @param model
 * @param isNew
 */
export function renderEditor(model, isNew) {

    console.debug('Rendering editor: ', model);

    const header = `<h2>${isNew ? 'Create Event' : 'Edit Event'}</h2>`;

    // --- Papierkorb-Status prüfen ---
    const isTrashed = Number(model.trash) === 1 || state.ui.trashMode;
    let trashWarning = '';
    if (isTrashed) {
        trashWarning = `
        <div class="wpem-lock-warning notice notice-error" 
             style="margin:10px 0;padding:10px;border:1px solid #dc3545;">
            <strong>Achtung:</strong> Dieses Event befindet sich im Papierkorb und kann nicht bearbeitet werden.
        </div>
    `;
    }
    // --- Schreibschutz-Hinweis falls Status != Anfrage erhalten ---
    let lockWarning = '';
    const lockedStatuses = WPEM.lockedStatuses || [];

    // Status-String in Array aufsplitten (Komma-getrennt, Whitespace trimmen)
    const statusList = (model.status || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);

    // Locked = wenn mindestens ein Status aus der Liste in lockedStatuses vorkommt
    const locked = !isNew && statusList.some(s => lockedStatuses.includes(s));

    if (locked) {
        lockWarning = `
        <div class="wpem-lock-warning notice notice-warning" style="margin:10px 0;padding:10px;border:1px solid #f0ad4e;">
            <strong>Hinweis:</strong> Dieses Event hat den Status <em>${escapeHtml(model.status)}</em> 
            und ist schreibgeschützt. Sie können das in den <a href="/wp-admin/admin.php?page=wpem-settings">Einstellungen</a> ändern.<br>
            <label style="margin-top:5px;display:inline-block;">
                <input type="checkbox" class="js-unlock-editor"> Bearbeitung trotzdem erlauben
            </label>
        </div>
    `;
    }

    const groupsHtml = FORM_SCHEMA.map(group => {
        const totalCols = group.columns || 1;
        const cols = Array.from({ length: totalCols }, () => []);
        let autoIndex = 0;

        group.fields.forEach(f => {
            if (f.col) {
                const colIndex = Math.max(0, Math.min(totalCols - 1, f.col - 1));
                cols[colIndex].push(renderField(f, model,isNew));
            } else {
                const colIndex = autoIndex % totalCols;
                cols[colIndex].push(renderField(f, model,isNew));
                autoIndex++;
            }
        });

        const colHtml = cols.map(fields => `
            <div class="wpem-col">${fields.join('')}</div>
        `).join('');

        return `
            <fieldset class="wpem-group col${totalCols}" style="--cols:${totalCols}">
                <legend>${escapeHtml(group.title || '')}</legend>
                <div class="wpem-group-grid">${colHtml}</div>
            </fieldset>
        `;
    }).join('');

    const actions = `
    <div class="wpem-form__actions">

        <button class="button button-primary js-save"
                ${isTrashed ? 'disabled' : ''}>
            ${isNew ? 'Event anlegen' : 'Alles speichern'}
        </button>

        ${model.id && !isTrashed ? `
            <button class="button js-duplicate-event"
                    type="button"
                    data-id="${model.id}">
                Event duplizieren
            </button>

            <button class="button button-secondary js-move-to-trash" 
                    type="button" 
                    data-id="${model.id}">
                In den Papierkorb
            </button>
        ` : ''}

        ${model.id && isTrashed ? `
            <button class="button button-secondary js-restore-from-trash" 
                    type="button"
                    data-id="${model.id}">
                Wiederherstellen
            </button>

            <button class="button button-link-delete js-delete-final" 
                    type="button"
                    data-id="${model.id}">
                Endgültig löschen
            </button>
        ` : ''}
    </div>`;

    const history = `
        <div id="wpem-history-modal" style="display:none;">
            <div class="wpem-history-content"></div>
        </div>`;

    const request = `
        <div id="wpem-request-modal" style="display:none;">
            <div class="wpem-request-content"></div>
        </div>`;

    const html = `
        ${header}
        <form id="wpem-form" data-id="${model.id||0}">
            ${trashWarning}
            ${!isTrashed ? lockWarning : ''}            
            ${groupsHtml}
            ${actions}
        </form>            
        ${history}
        ${request}`;

    destroyEditors();
    jQuery('#wpem-editor').html(html);

    Promise.resolve().then(() => {
        if (isTrashed) {
            // Alles deaktivieren
            jQuery('#wpem-form :input').prop('disabled', true);
            jQuery('#wpem-form a').css({
                'pointer-events': 'none',
                'opacity': '0.4',
                'cursor': 'default'
            });

            // Aber: Buttons „Wiederherstellen“ und „Endgültig löschen“ sollen nicht deaktiviert werden
            jQuery('.js-restore-from-trash,.js-delete-final').prop('disabled', false);

            return; // Keine weitere Lock-Logik anwenden
        }
        initEditors();
        initDatePickers();
        // Signal: Editor-DOM + Picker sind da
        document.dispatchEvent(new CustomEvent('wpem:editor:ready'));

        if (locked) {
            // Alle Eingaben deaktivieren
            jQuery('#wpem-form :input').prop('disabled', true);
            // Unlock-Checkbox ausnehmen
            jQuery('.js-unlock-editor').prop('disabled', false);

            // Klick-Handler für Unlock
            jQuery('.js-unlock-editor').on('change', function () {
                const unlocked = jQuery(this).is(':checked');
                jQuery('#wpem-form :input').prop('disabled', !unlocked);
                jQuery(this).prop('disabled', false); // Checkbox selbst bleibt aktiv
            });
        }
    });

}

/**
 * Rendert ein einzelnes Feld anhand des Schemas.
 * @param f
 * @param e
 * @returns {string}
 */
function renderField(f, e,isNew) {
    const id = f.id;
    const label = f.label || id;
    const req = !!f.required;

    switch (f.type) {
        case 'text':            return fieldText(label, id, e[id], req);
        case 'textReadonly':    return fieldTextReadonly(label, id, e[id], req);
        case 'textarea':        return fieldTextarea(label, id, e[id], req);
        case 'number':          return fieldNumber(label, id, e[id]);
        case 'date':            return fieldDate(label, id, e[id], req);
        case 'wysiwyg':         return fieldTextarea(label, id, e[id], true); // TinyMCE kommt oben
        case 'yesno':           return fieldYesNo(label, id, String(e[id] ?? '0')); // 0/1
        case 'statusGroup':     return fieldStatusGroup(e.status); // geändert auf single choice
        case 'addInfosGroup':   return fieldAddInfosGroup(e.addinfos);
        case 'organization':    return fieldOrganization(e);
        case 'placeMatrix':     return fieldPlaceMatrix(e); // Saalbelegung
        case 'dayEvents':       return fieldDayEvents(e); // wird dynamisch geladen
        case 'picture':         return fieldPicture('picture', e.picture);
        case 'publish':         return fieldPublishGroup('publish', e.publish);
        case 'history':         return fieldHistoryLoader(e.id,isNew);
        case 'request':         return fieldRequestLoader(e.id,e,isNew);
        case 'saveButton':      return fieldSaveButton(isNew);// kein Feld, nur Speichern-Button
        default:                return fieldText(label, id, e[id] ?? '');
    }
}

export function loadEditor(id) {

    jQuery('#wpem-editor').html(`${spinnerHTML()}`);
    if (!id) {
        renderEditor(emptyEvent(), true);
        state.activeId = 0;
        highlightActive();
        return;
    }
    post('wpem_get_event', { id: id })
        .then(data => {
            renderEditor(data.event, false);
            state.activeId = Number(id);
            highlightActive();
        })
        .fail(err => showError('#wpem-editor', err));
}


/* ---------- Helpers ---------- */
function emptyEvent() {
    return {
        id: 0, title:'', type: '', short:'', fromdate:'', fromtime:'',
        todate:'', totime:'', descr1:'', descr2:'', descr3:'',
        persons:'', organizer:'', organization:'', email:'', tel:'',
        picture:'', publish:'0', booked:0, status:'', place1:'0', place2:'0', place3:'0',
        note:'', processed:'0000-00-00', informed:'0000-00-00', soldout:''
    };
}

export function showError(sel, msg){
    jQuery(sel).html(`<div class="notice notice-error"><p>${escapeHtml(msg||'Error')}</p></div>`);
}
export function notice(type, text) {
    const $wrap = jQuery('.wrap');
    // alle bestehenden Notices entfernen
    $wrap.find('.notice').remove();

    // neue erstellen
    const $n = jQuery(
        `<div class="notice notice-${type} is-dismissible"><p>${escapeHtml(text || '')}</p></div>`
    );
    $wrap.prepend($n);
}

export function highlightActive(){
    jQuery('#wpem-list tr').each(function(){
        $(this).toggleClass('is-active', Number($(this).data('id')) === state.activeId);
    });
}

/* ---------- Bindings ---------- */

$('#wpem-editor').on('click','.wpem-dayevents .js-open',function(e){
    const id = $(this).data('id');
    if(id) loadEditor(id);
});

// „+ Neu“: Neuanlage für denselben Tag, Dates korrekt setzen
jQuery('#wpem-editor').on('click', '.js-new-same-day', function (e) {
    e.preventDefault();
    const date = this.dataset.date; // erwartet 'YYYY-MM-DD'
    if (!date) return;

    // Neuen Editor öffnen
    loadEditor(0);

    // Warten bis Picker da sind, dann setzen
    waitForPickers()
        .then(({ fromEl, toEl }) => {
            setPickerDate(fromEl, date);
            setPickerDate(toEl,   date);
            setPickerMinDate(toEl, date);
        })
        .catch(() => {
            // Optional: stiller Fallback
            const f = document.querySelector('#wpem-editor [name="fromdate"]');
            const t = document.querySelector('#wpem-editor [name="todate"]');
            if (f) f.value = date;
            if (t) t.value = date;
        });
});

$('#wpem-editor').on('click', '.js-show-history', function () {

    const eventId = $(this).data('id');
    $.post(ajaxurl, { action: 'wpem_get_history', event_id: eventId }, function (resp) {
        if (!resp.success) {
            alert('Fehler beim Laden der History');
            return;
        }
        const items = resp.data || [];
        if (!items.length) {
            $('#wpem-history-modal .wpem-history-content').html('<p>Keine Änderungen vorhanden.</p>');
        } else {
            loadHistory(items);
        }

        tb_show('Änderungshistorie', '#TB_inline?inlineId=wpem-history-modal&width=800&height=600');
    });
});


$('#wpem-editor').on('click', '.js-show-request', function () {
    const entryId = $(this).data('entry-id');
    if (!entryId) {
        alert('Keine Anfrage-ID vorhanden.');
        return;
    }
    const logId = $(this).data('log-id');
    $.post(ajaxurl, { action: 'wpem_get_request', entry_id: entryId,log_id:logId }, function (resp) {
        if (!resp.success) {
            $('#wpem-request-modal .wpem-request-content').html('<p>Fehler beim Laden der Anfrage.</p>');
        } else {
            const entry = resp.data.entry_data;
            const utc_date = new Date(entry.date + 'Z');
            const entry_date = formatUTCToLocalYMDHMS(entry.date );
            let html = `<p><strong>Entry-ID:</strong> ${entry.entry_id}<br>
                     <strong>Form-ID:</strong> ${entry.form_id}<br>
                     <strong>Datum:</strong> ${entry_date}<br>
                     <strong>IP:</strong> ${entry.ip}</p>`;

            html += '<table class="widefat striped"><tbody>';
            entry.fields.forEach(f => {
                html += `<tr><th>${f.label}</th><td>${f.value}</td></tr>`;
            });
            html += '</tbody></table>';

            //html += renderLogEntry(resp.data.log_data);

            $('#wpem-request-modal .wpem-request-content').html(html);
        }

        tb_show('Buchungsanfrage', '#TB_inline?inlineId=wpem-request-modal&width=800&height=600');
    });
});

$(document).on('change', '.wpem-radio-group input[type="radio"][name="status"]', function() {

    // 1) Alle aktiven Klassen entfernen
    $('.wpem-radio-group .wpem-radio').each(function() {
        this.className = this.className
            .replace(/anfrage-active|bearbeitung-active|gebucht-active|vereinbarung-active/g, '')
            .trim();
    });

    // 2) Neue Klasse hinzufügen
    const value = $(this).val().trim();

    const map = {
        'Anfrage erhalten': 'anfrage-active',
        'In Bearbeitung': 'bearbeitung-active',
        'Gebucht': 'gebucht-active',
        'Vereinbarung unterzeichnet': 'vereinbarung-active'
    };

    const activeClass = map[value];

    if (activeClass) {
        $(this).closest('.wpem-radio').addClass(activeClass);
    }
});

function renderLogEntry(entry) {
    const data = JSON.parse(entry);

    // sicheres Escaping für HTML
    const esc = s => String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

    // Schlüssel/Wert in Tabelle
    const rows = Object.entries(data).map(([k,v]) => {
        // Null/undefined hübsch
        const val = (v === null || v === undefined) ? '' : v;
        return `<tr><th>${esc(k)}</th><td>${esc(val)}</td></tr>`;
    }).join('');

    return '<table class="widefat striped">' + rows + '</table>';

}
/* ---------- Media Library Integration for picture ---------- */
let wpemMediaFrame = null;

function openMediaFrame(onSelect) {
    if (wpemMediaFrame) {
        wpemMediaFrame.off('select');
    }
    wpemMediaFrame = wp.media({
        title: WPEM.i18n?.insertImage || 'Insert image',
        button: { text: WPEM.i18n?.insertImage || 'Insert image' },
        library: { type: 'image' },
        multiple: false
    });
    wpemMediaFrame.on('select', function(){
        const attachment = wpemMediaFrame.state().get('selection').first();
        if (!attachment) return;
        const data = attachment.toJSON();
        const url = data?.sizes?.medium?.url || data?.url; // bevorzugt Medium
        if (typeof onSelect === 'function') onSelect(url);
    });
    wpemMediaFrame.open();
}

// Delegierte Clicks im Editor
$('#wpem-editor').on('click', '.js-pic-insert, .js-pic-change', function(e){
    e.preventDefault();
    const ui = e.currentTarget.closest('.wpem-picture-ui');
    if (!ui) return;
    openMediaFrame(function(url){
        const input = ui.querySelector('input[type=hidden][name="picture"]');
        const preview = ui.querySelector('.wpem-picture-preview');
        input.value = url || '';
        preview.innerHTML = url ? `<img src="${escapeHtml(url)}" alt="">` : '';

        // Buttons umschalten
        const actions = ui.querySelector('.wpem-picture-actions');
        const change = WPEM.i18n?.changeImage || 'Change image';
        const remove = WPEM.i18n?.removeImage || 'Remove';
        actions.innerHTML = `
      <button type="button" class="button js-pic-change">${escapeHtml(change)}</button>
      <button type="button" class="button js-pic-remove">${escapeHtml(remove)}</button>
    `;
    });
});

// Klick auf „Bild entfernen“
$('#wpem-editor').on('click', '.js-pic-remove', function(e){
    e.preventDefault();
    const ui = e.currentTarget.closest('.wpem-picture-ui');
    if (!ui) return;
    const input = ui.querySelector('input[type=hidden][name="picture"]');
    const preview = ui.querySelector('.wpem-picture-preview');
    input.value = '';
    preview.innerHTML = '';

    // Buttons auf „einfügen“ zurücksetzen
    const actions = ui.querySelector('.wpem-picture-actions');
    const insert = WPEM.i18n?.insertImage || 'Insert image';
    actions.innerHTML = `<button type="button" class="button button-secondary js-pic-insert">${escapeHtml(insert)}</button>`;
});
