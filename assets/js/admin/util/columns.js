import {fmtDMY, escapeHtml, formatToDMYHM} from '../util/helper.js';
export const COLUMNS = [
    {
        key: 'status',
        label: 'Status',
        render: (e) => {
            // Falls Status leer oder kein Array -> neutral
            const statusVal = Array.isArray(e.status) ? e.status[e.status.length - 1] : e.status;

            // Farbcodes und Titeltexte
            const colorMap = {
                'Anfrage erhalten':   { class: 'anfrage', label: 'Anfrage erhalten' },
                'In Bearbeitung':     { class: 'bearbeitung', label: 'In Bearbeitung' },
                'Gebucht':            { class: 'gebucht', label: 'Gebucht' },
                'Vereinbarung unterzeichnet': { class: 'vereinbarung', label: 'Vereinbarung unterzeichnet' }
            };

            const st = colorMap[statusVal] || { color: '#ccc', label: 'Unbekannt' };

            // Dashicon: Haken im Kreis (dashicons-yes-alt)
            return `
              <span class="dashicons dashicons-yes-alt ${st.class}"
                    style="color:${st.color};"
                    title="${st.label}">
              </span>
            `;
        }
    },

    {
        key: 'fromdate',
        sortable: true,
        default_dir: 'DESC',
        label: 'Start',
        render: (e) => `${fmtDMY(e.fromdate)} ${escapeHtml(e.fromtime||'')}`.trim()
    },
    {
        key: 'todate',
        label: 'Ende',
        render: (e) => `${fmtDMY(e.todate)} ${escapeHtml(e.totime||'')}`.trim()
    },
    {
        key: 'title',
        label: 'Titel',
        render: (e) => {
            const t = escapeHtml(e.title || e.short || '');
            return `<a href="#" class="js-open" data-id="${e.id}">${t}</a>`;
        }
    },
    {
        key: 'import',
        label: 'I',
        render: (e) => {
            const t = e.import && e.import !== '0000-00-00 00:00:00'
                ? '<span title="Importiert">ℹ️</span>'
                : '';
            return t;
        }
    },
    // Großer / Kleiner / Foyer als farbige Dots
    { key: 'place1', label: 'Gr', render: (e) => dotPlace(e.place1, e, 'Großer Saal') },
    { key: 'place2', label: 'Kl', render: (e) => dotPlace(e.place2, e, 'Kleiner Saal') },
    { key: 'place3', label: 'Fo', render: (e) => dotPlace(e.place3, e, 'Foyer') },
    { key: 'booked', label: 'A', render: (e) => dotPlace(e.booked, e, 'Ausgebucht') },];


export function dotPlace(v, e, roomLabel) {
    const isBookedColumn = roomLabel === 'Ausgebucht';

    // --- Spezialfall: Spalte "A" (Ausgebucht) ---
    if (isBookedColumn) {
        return String(e?.booked ?? '0') === '1'
            ? `<span class="wpem-dot wpem-dot--red" title="Ausgebucht"></span>`
            : '';
    }

    // --- Normale Raumspalten (Großer/Kleiner/Foyer) ---
    const val = String(v ?? '');

    // 1) Feste Belegung aus DB
    if (val === '0' || val == '') return `<span class="wpem-dot wpem-dot--green" title="Frei"></span>`;
    if (val === '1') return `<span class="wpem-dot wpem-dot--orange" title="Optional"></span>`;
    if (val === '2') return `<span class="wpem-dot wpem-dot--red"    title="Gebucht"></span>`;

    // 2) Anfrage-Fall: Status kann mehrere Werte enthalten ("In Bearbeitung,Gebucht,...")
    const statusList = String(e?.status ?? '').split(',').map(s => s.trim()).filter(Boolean);
    const isAnfrage = statusList.includes('Anfrage erhalten');

    if (isAnfrage) {
        const places = String(e?.places ?? '').split(',').map(s => s.trim());
        return places.includes(roomLabel)
            ? `<span class="wpem-dot-black wpem-dot-black--filled" title="${roomLabel} (Anfrage)"></span>`
            : `<span class="wpem-dot-black" title="${roomLabel} (frei)"></span>`;
    }

    // 3) Default: nichts (frei/grau)
    return `<span class="wpem-dot wpem-dot--gray" title="Frei"></span>`;
}