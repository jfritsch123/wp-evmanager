// Standard-Feldrenderer (Text/Number/Date/Textarea/YesNo)
import {escapeHtml, fmtDMY} from '../util/helper.js';

/* ---------- Standard Feld-Renderer ---------- */
export function fieldText(label,name,v,req){ return `<label>${label}<input type="text" name="${name}" value="${escapeHtml(v||'')}" ${req?'required':''}></label>`; }
export function fieldTextReadonly(label,name,v,req){ return `<label>${label}<input type="text" readonly name="${name}" value="${escapeHtml(v||'')}" ${req?'required':''}></label>`; }
//export function fieldTextArea(label,name,v,req){ return `<label>${label}<textarea name="${name}" value="${escapeHtml(v||'')}" ${req?'required':''}></textarea></label>`; }
export function fieldNumber(label,name,v){ return `<label>${label}<input type="number" min="0" step="1" name="${name}" value="${Number(v)||0}"></label>`; }
export function fieldDate(label,name,v,req){ return `<label>${label}<input type="date" name="${name}" value="${escapeHtml(v||'')}" ${req?'required':''}></label>`; }
// Wichtig: id = name → TinyMCE target
export function fieldTextarea(label,name,v,full){ return `<label class="${full?'wpem-form__full':''}">${label}<textarea id="${name}" name="${name}" rows="3">${escapeHtml(v||'')}</textarea></label>`; }

/* ---------- Spezielle Feld-Renderer ---------- */

/**
 * Rendert das Feld "organization" als Dropdown mit optionaler Auswahl.
 * Nur der Wert "Kultur im Löwen" ist auswählbar.
 */
export function fieldOrganization(model) {
    const options = ['Kultur im Löwen'];
    const current = model.organization || '';

    return `
    <label for="organization">
      <span class="wpem-label">Organisation</span>
      <select id="organization" name="organization" class="wpem-input">
        <option value="">– Keine Auswahl –</option>
        ${options.map(opt => `
          <option value="${opt}" ${opt === current ? 'selected' : ''}>${opt}</option>
        `).join('')}
      </select>
    </label>
  `;
}

/**
 * Matrix für Saalbelegung, keine Aggregation mehrerer Events
 * values: { place1, place2, place3, status }
 */
export function fieldPlaceMatrix(values){

    // Basiswerte aus aktuellem Event
    const v = {
        place1: parseInt(values.place1 ?? 0, 10),
        place2: parseInt(values.place2 ?? 0, 10),
        place3: parseInt(values.place3 ?? 0, 10),
    };


    // Aggregation mit weiteren Events am selben Tag
    const agg = { ...v };

    // Hilfsfunktion: ein Feld rendern
    function cell(name, value, checked){
        const id = `${name}_${value}`;
        return `
          <div class="wpem-matrix-cell">
            <input type="radio" id="${id}" name="${name}" value="${value}" ${checked ? 'checked' : ''} />
            <label for="${id}" aria-label="${name}-${value}"></label>
          </div>`;
    }

    // Hilfsfunktion: eine Reihe rendern
    const row = (value, label) => `
      <div class="wpem-matrix-row">
        <div class="wpem-matrix-cell wpem-matrix-label">${escapeHtml(label)}</div>
        ${cell('place1', value, agg.place1 === value)}
        ${cell('place2', value, agg.place2 === value)}
        ${cell('place3', value, agg.place3 === value)}
      </div>
    `;

    // Schwarze Punkte-Reihe "Angefragt"
    const chosenRow = `
      <div class="wpem-matrix-row">
        <div class="wpem-matrix-cell wpem-matrix-label">Angefragt</div>
        <div class="wpem-matrix-cell">
          <span class="wpem-dot-black${values.places?.includes('Großer Saal') ? ' wpem-dot-black--filled' : ''}" aria-label="Großer Saal"></span>
        </div>
        <div class="wpem-matrix-cell">
          <span class="wpem-dot-black${values.places?.includes('Kleiner Saal') ? ' wpem-dot-black--filled' : ''}" aria-label="Kleiner Saal"></span>
        </div>
        <div class="wpem-matrix-cell">
          <span class="wpem-dot-black${values.places?.includes('Foyer') ? ' wpem-dot-black--filled' : ''}" aria-label="Foyer"></span>
        </div>
      </div>
    `;

    return `
      <div class="wpem-matrix">
        <div class="wpem-matrix-row wpem-matrix-head">
          <div class="wpem-matrix-cell"></div>
          <div class="wpem-matrix-cell wpem-matrix-col">${escapeHtml('Großer Saal')}</div>
          <div class="wpem-matrix-cell wpem-matrix-col">${escapeHtml('Kleiner Saal')}</div>
          <div class="wpem-matrix-cell wpem-matrix-col">${escapeHtml('Foyer')}</div>
        </div>
        ${row(0,'Frei')}
        ${row(1,'Optional')}
        ${row(2,'Gebucht')}
        ${chosenRow}
      </div>
    `;
}

/**
 * Status-Gruppe (radio buttons):
 * "Anfrage erhalten", "In Bearbeitung", "Gebucht", "Vereinbarung unterzeichnet"
 * @param current
 * @returns {string}
 * nicht genutzt, siehe fieldStatus()
 */

export function fieldStatusGroup(current) {

    const allowed = [
        { value: 'Anfrage erhalten', class: 'anfrage' },
        { value: 'In Bearbeitung', class: 'bearbeitung' },
        { value: 'Gebucht', class: 'gebucht' },
        { value: 'Vereinbarung unterzeichnet', class: 'vereinbarung' }
    ];

    // aktuellen Status extrahieren
    const cur = Array.isArray(current)
        ? String(current[0] ?? '')
        : String(current || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean)[0] || '';

    const radio = (item) => {
        const label = item.value;
        const baseClass   = item.class;
        const activeClass = (cur === label) ? `${baseClass}-active` : '';
        const id = 'status_' + label.replace(/\s+/g, '_');

        return `
            <label class="wpem-radio ${baseClass} ${activeClass}">
                <input type="radio"
                       id="${id}"
                       name="status"
                       value="${escapeHtml(label)}"
                       ${cur === label ? 'checked' : ''}>
                <span>${escapeHtml(label)}</span>
            </label>
        `;
    };

    return `
        <fieldset class="wpem-form__status">
            <legend>Status</legend>
            <div class="wpem-radio-group">
                ${allowed.map(radio).join('')}
            </div>
        </fieldset>`;
}

/**
 * Bildfeld: Vorschau + Buttons
 * @param name
 * @param url
 * @returns {string}
 */
export function fieldPicture(name, url) {
    const has = !!(url && String(url).trim());
    const label = WPEM.i18n?.imageField || 'Image';
    const insert = WPEM.i18n?.insertImage || 'Insert image';
    const change = WPEM.i18n?.changeImage || 'Change image';
    const remove = WPEM.i18n?.removeImage || 'Remove';

    return `
            <div class="wpem-picture-field">
              <label>${escapeHtml(label)}</label>
              <div class="wpem-picture-ui" data-name="${name}">
                <input type="hidden" name="${name}" value="${escapeHtml(url || '')}">
                <div class="wpem-picture-preview">
                  ${has ? `<img src="${escapeHtml(url)}" alt="">` : ''}
                </div>
                <div class="wpem-picture-actions">
                  ${has
        ? `<button type="button" class="button js-pic-change">${escapeHtml(change)}</button>
                       <button type="button" class="button js-pic-remove">${escapeHtml(remove)}</button>`
        : `<button type="button" class="button button-secondary js-pic-insert">${escapeHtml(insert)}</button>`}
                </div>
              </div>
            </div>
          `;
}

/**
 * Publizieren: 0=Nichts, 1=Nur Titel, 2=Alles, 3=Kultur im Löwen
 * @param name
 * @param val
 * @returns {string}
 */
export function fieldPublishGroup(name, val) {
    const v = String(val ?? '0');
    const opts = [
        ['0','Nichts'],
        ['1','Nur Titel'],
        ['2','Alles'],
        ['3','Kultur im Löwen'],
    ];
    const radio = ([value,label]) => {
        const id = `${name}_${value}`;
        return `<label class="wpem-radio">
                <input type="radio" id="${id}" name="${name}" value="${value}" ${v===value?'checked':''}>
                <span>${escapeHtml(label)}</span>
            </label>`;
    };
    return `<div class="wpem-radio-group">${opts.map(radio).join('')}</div>`;
}

/**
 * Ja/Nein-Feld (Checkbox)
 * @param label
 * @param name
 * @param v
 * @returns {string}
 */
export function fieldYesNo(label, name, v) {
    const checked = String(v) === '1' ? 'checked' : '';
    return `
    <label class="wpem-check">
      <input type="checkbox" name="${name}" value="1" ${checked}>
      <span>${label}</span>
    </label>
  `;
}

export function dot(val, isAnfrage, places, roomLabel) {

    if(roomLabel === 'Ausgebucht') {
        if (val === '1') return '<span class="wpem-dot wpem-dot--red"></span>';
        return '<span class="wpem-dot"></span>';
    }

    if (val === '0' || val === '' || val == null) return '<span class="wpem-dot wpem-dot--free"></span>';
    if (val === '1') return '<span class="wpem-dot wpem-dot--opt"></span>';
    if (val === '2') return '<span class="wpem-dot wpem-dot--booked"></span>';
    if (isAnfrage) {
        if (places && typeof places === 'string') {
            const rooms = places.split(',').map(r => r.trim());
            if (rooms.includes(roomLabel)) {
                return '<span class="wpem-dot wpem-dot-black wpem-dot-black--filled"></span>';
            }
        }
        return '<span class="wpem-dot"></span>';
    }

    return '<span class="wpem-dot"></span>';
}

export function fieldDayEvents(values){

    const list = values.dayEvents || [];
    const date = values.fromdate || '';
    const rows = list.map(ev => {
        //console.debug('fieldDayEvents',ev);
        const isAnfrage = ev.status === 'Anfrage erhalten';
        //console.debug('isAnfrage',isAnfrage);
        const isThisEvent = String(ev.id) === String(values.id);
        const cls = isThisEvent ? ' wpem-dayevents-row--this' : '';
        return `
          <tr data-id="${ev.id}" class="js-open ${cls}">
            <td>${dot(ev.place1, isAnfrage, ev.places, 'Großer Saal')}</td>
            <td>${dot(ev.place2, isAnfrage, ev.places, 'Kleiner Saal')}</td>
            <td>${dot(ev.place3, isAnfrage, ev.places, 'Foyer')}</td>
            <td>${dot(ev.booked, isAnfrage, ev.places, 'Ausgebucht')}</td>

            <td>${escapeHtml(ev.title || '(ohne Titel)')}</td>
            <td></td>
          </tr>`;
    }).join('');


    return `
      <div class="wpem-dayevents">
        <label class="wpem-matrix-head wpem-matrix-cell">Alle Events am ${escapeHtml(fmtDMY(date))}</label>    
        <table class="widefat">
          <thead>
            <tr>
              <th>Gr</th><th>Kl</th><th>Fo</th><th>A</th>
              <th>Titel</th>
              <th style="text-align: right;"><button type="button" class="button js-new-same-day" data-date="${escapeHtml(date)}">+ Neu</button></th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
}

export function fieldHistoryLoader(id,isNew){
    if (isNew) return'';
    return `
    <button type="button" class="button js-show-history" data-id="${id}">
        Änderungs-History laden
    </button>`;
}

export function fieldRequestLoader(id,model,isNew){
    if (isNew) return'';
    console.debug('fieldRequestLoader',model);
    const entryId = model.wpforms_entry_id;
    const logId = model.wp_evmanager_log_id;
    // Nur anzeigen, wenn wirklich eine gültige ID > 0 vorhanden ist
    if (!entryId || entryId === '0' || entryId === 0) {
        return '<em style="margin-left:1em;">Keine Anfrage zu diesem Event</em>';
    }

    return `
        <button type="button"
                class="button js-show-request"
                data-entry-id="${entryId}" data-log-id="${logId}">
            Original-Anfrage laden
        </button>
    `;
}

export function fieldSaveButton(isNew){
    if (isNew) return'';
    return `
        <div class="wpem-form__actions">
            <button class="button button-primary js-save">Alles speichern</button>
        </div>`;
}