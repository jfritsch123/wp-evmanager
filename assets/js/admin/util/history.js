
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
        processed: 'Bearbeitet am',
        editor: 'Bearbeiter',
        informed: 'Informiert am',
        status: 'Status',
        note: 'Interne Notiz',
        booking: 'Buchungsanfrage',
    };

    // Hilfsfunktionen
    function esc(s) { return jQuery('<div/>').text(s == null ? '' : String(s)).html(); }
    function isUrl(v) { return /^https?:\/\/[^\s]+$/i.test(String(v||'')); }
    function trunc(s, n=80) { return s.length > n ? s.slice(0,n-1) + '…' : s; }
    function fmtOld(v) {
        if (v == null || v === '') return '<span class="wpem-old wpem-empty">—</span>';
        return `<span class="wpem-old">${esc(v)}</span>`;
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
        Object.entries(row.changes || {}).forEach(([field, vals]) => {
            const alias = fieldAliases[field] || field; // Klartext oder Fallback
            const oldVal = Array.isArray(vals) ? vals[0] : null;
            const newVal = Array.isArray(vals) ? vals[1] : null;
            changes += `
                <div class="wpem-change">
                    <span class="wpem-field"><strong>${esc(alias)}</strong>:</span>
                    ${fmtOld(oldVal)} <span class="wpem-arrow">→</span> ${fmtNew(newVal)}
                </div>`;
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
