export const $ = (sel, ctx=document) => ctx.querySelector(sel);
export const $$ = (sel, ctx=document) => [...ctx.querySelectorAll(sel)];

export function escapeHtml(str) {
    if (typeof str !== 'string') str = String(str ?? '');
    return str.replace(/[&<>"']/g, function(m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m];
    });
}

// Hilfsfunktionen (einmalig oben im File platzieren)
export function lastDayOfMonthStr(year, mm){           // mm = "01".."12"
    const y = parseInt(year,10), m = parseInt(mm,10);
    const d = new Date(y, m, 0).getDate();
    return `${year}-${mm}-${String(d).padStart(2,'0')}`;
}

/**
 * Wandelt "YYYY-MM-DD HH:MM:SS" in "DD.MM.YYYY HH:MM"
 * Beispiel: 2025-10-09 12:41:01 → 09.10.2025 12:41
 */
export function formatToDMYHM(mysql) {
    if (!mysql || mysql === '0000-00-00 00:00:00') return '';

    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/.exec(mysql);
    if (!m) return '';

    const year = Number(m[1]);
    const month = Number(m[2]);
    const day = Number(m[3]);
    const hour = Number(m[4]);
    const minute = Number(m[5]);

    // Date-Objekt zur Sicherheit (korrigiert ggf. Zeitfehler)
    const dt = new Date(year, month - 1, day, hour, minute, 0);

    const DD = String(dt.getDate()).padStart(2, '0');
    const MM = String(dt.getMonth() + 1).padStart(2, '0');
    const YYYY = dt.getFullYear();
    const HH = String(dt.getHours()).padStart(2, '0');
    const Min = String(dt.getMinutes()).padStart(2, '0');

    return `${DD}.${MM}.${YYYY} ${HH}:${Min}`;
}

/**
 * Wandelt UTC-Zeitstring in lokales Datum und formatiert als "YYYY-MM-DD HH:MM:SS"
 */
export function formatUTCToLocalYMDHMS(utcString) {
    if (!utcString) return '';

    // "Z" erzwingt UTC
    const date = new Date(utcString.endsWith('Z') ? utcString : utcString + 'Z');

    if (isNaN(date)) return '';

    const YYYY = date.getFullYear();
    const MM = String(date.getMonth() + 1).padStart(2, '0');
    const DD = String(date.getDate()).padStart(2, '0');
    const HH = String(date.getHours()).padStart(2, '0');
    const MIN = String(date.getMinutes()).padStart(2, '0');
    const SS = String(date.getSeconds()).padStart(2, '0');

    return `${YYYY}-${MM}-${DD} ${HH}:${MIN}:${SS}`;
}

export function ymd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const da= String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
}

export function todayYmd(){
    const d=new Date();
    const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), da=String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
}

export function fmtDMY(ymd){
    if (!ymd || ymd === '0000-00-00') return '';
    const [y,m,d] = ymd.split('-');
    if (!y || !m || !d) return ymd;
    return `${d}.${m}.${y}`;
}

/**
 * Liefert HTML für ein Help-Icon (analog zu PHP HelpUI::icon)
 * @param {string} context - context_key für den Hilfetext
 * @param {string} [label] - Tooltip-Text
 * @returns {string} HTML-String
 */
export function renderHelpIcon(context, label = 'Hilfe anzeigen') {
    return `
        <a href="#" class="wpem-help" data-context="${context}" title="${label}">
            <span class="dashicons dashicons-editor-help"></span>
        </a>
    `;
}

/** not used currently */
export function fmtPlace(v){
    const map = {'0':'Frei','1':'Optional','2':'Gebucht'};
    const key = String(v ?? '');
    const txt = map[key] ?? '';
    const cls = key === '2' ? 'wpem-badge--red' : key === '1' ? 'wpem-badge--orange' : 'wpem-badge--green';
    return txt ? `<span class="wpem-badge ${cls}">${txt}</span>` : '';
}

export function esc(s){ return escapeHtml(String(s ?? '')); }

export function spinnerHTML(t = 'Lade Daten...') {
    return `
        <div class="wpem-loading">
            <span class="dashicons dashicons-update-alt spin"></span>
            <span class="wpem-loading-text">${t}</span>
        </div>
    `;
}

export function shortSpinnerHTML(){
    return `
        <div class="wpem-loading short">
            <span class="dashicons dashicons-update-alt spin"></span>
        </div>
    `;
}

export function showOverlay() {
    jQuery('html, body').animate({ scrollTop: 0 }, 200);
    const $wrap = jQuery('.wrap');

    // Wenn schon ein Overlay existiert → nix doppelt einfügen
    if ($wrap.find('.wpem-overlay').length) return;

    const $overlay = jQuery(`
        <div class="wpem-overlay">
            ${spinnerHTML('Daten speichern ...')}
        </div>
    `);

    $wrap.css('position', 'relative');
    $wrap.append($overlay);
}

export function hideOverlay() {
    jQuery('.wpem-overlay').fadeOut(200, function() {
        jQuery(this).remove();
    });
}

