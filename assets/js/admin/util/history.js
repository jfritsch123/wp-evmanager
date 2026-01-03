// Wertemappings für spezielle Felder
const valueMaps = {
    publish: {
        '0': 'Nichts',
        '1': 'Nur öffentlicher Titel',
        '2': 'Öffentlicher Titel + Beschreibung + Kartenvorverkauf + Bild',
    },
    place: {
        '0': 'Frei',
        '1': 'Optional',
        '2': 'Gebucht',
    }
};

export function loadHistory(items) {
    // Alias-Map für Feldnamen
    const fieldAliases = {
        title: 'Titel',
        type: 'Veranstaltungsart',
        descr1: 'Beschreibung',
        descr2: 'Kartenvorverkauf',
        descr3: 'Anfragetext',
        short: 'Kurztext',
        organizer: 'Veranstalter',
        organization: 'Organisation',
        persons: 'Personenanzahl',
        email: 'E-Mail',
        tel: 'Telefon',
        picture: 'Bild',
        place1: 'Großer Saal',
        place2: 'Kleiner Saal',
        place3: 'Foyer',
        booked: 'Ausgebucht',
        publish: 'Publizieren',
        addinfos: 'Zusatzinfos',
        processed: 'Bearbeitet am',
        editor: 'Bearbeiter',
        informed: 'Informiert am',
        status: 'Status',
        note: 'Interne Notiz',
        booking: 'Buchungsanfrage',
        _created: 'Neuer Event',
        _trash: 'Papierkorb',
        _duplicated : 'Duplizierter Event',
        _duplicated_from : 'Dupliziert von',
        _duplicated_to : 'Dupliziert zu',

    };

    // Hilfsfunktionen
    function esc(s) { return jQuery('<div/>').text(s == null ? '' : String(s)).html(); }
    function isUrl(v) { return /^https?:\/\/[^\s]+$/i.test(String(v||'')); }
    function trunc(s, n=80) { return s.length > n ? s.slice(0,n-1) + '…' : s; }
    function fmtOld(v) {
        if (v == null || v === '') return '<span class="wpem-old wpem-empty">—</span>';
        return `<span class="wpem-old">${esc(v)}</span>`;
    }
    function fmtValue(field, v) {
        if(field == '_created' || field == '_trash') {
            return v;
        }
        if (v == null || v === '') {
            return '<span class="wpem-empty">—</span>';
        }

        // Publizieren
        if (field === 'publish' && valueMaps.publish[v]) {
            return esc(valueMaps.publish[v]);
        }

        // Saal-Status
        if (['place1','place2','place3'].includes(field) && valueMaps.place[v]) {
            return esc(valueMaps.place[v]);
        }

        return esc(v);
    }

    function fmtNew(v) {
        if (v == null || v === '') return '<span class="wpem-new wpem-empty">—</span>';
        const t = String(v);
        if (isUrl(t)) {
            const safe = esc(t);
            return `<a class="wpem-new" href="${safe}" target="_blank" rel="noopener noreferrer">${trunc(safe, 100)}</a>`;
        }
        return `<span class="wpem-new">${esc(t)}</span>`;
    }

    // Tabelle rendern
    let html = '<div class="wpem-history-scroll"><table class="widefat striped wpem-history-table"><thead><tr><th>Datum</th><th>Bearbeiter</th><th>Änderungen</th></tr></thead><tbody>';
    items.forEach(row => {
        let changes = '';
        const changesObj =
            row &&
            row.changes &&
            typeof row.changes === 'object' &&
            !Array.isArray(row.changes)
                ? row.changes
                : {};
        //console.debug('[History] Verarbeitung der Änderungen', changesObj);
        Object.entries(changesObj).forEach(([field, vals]) => {
            if (!Array.isArray(vals) || vals.length < 2) {
                console.warn('[History] Ungültiges Change-Format', field, vals);
                return;
            }
            if(field == '_created' || field == '_trash') {
                vals[0] = '';
            }
            const alias = fieldAliases[field] || field;
            const [oldVal, newVal] = vals;
            console.debug([oldVal, newVal],vals);
            changes += `
                <div class="wpem-change">${esc(alias)}:
                     <span class="wpem-old">${fmtValue(field, oldVal)}</span>
                     <span class="wpem-arrow">→</span>
                     <span class="wpem-new">${fmtValue(field, newVal)}</span>
                </div>
            `;
        });
        html += `<tr>
            <td class="wpem-col-date">${esc(row.changed_at)}</td>
            <td class="wpem-col-user">${esc(row.editor)}</td>
            <td class="wpem-col-changes">${changes || '<span class="wpem-empty">—</span>'}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';

    jQuery('#wpem-history-modal .wpem-history-content').html(html);
}
