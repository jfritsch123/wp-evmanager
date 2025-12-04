import {escapeHtml, fmtDMY, shortSpinnerHTML} from './helper.js';
import { dot } from '../fields/controls.js';
import { fieldDayEvents} from '../fields/controls.js';
import { Tooltip } from '../../frontend/util.js';

// ============================================================
// 🧭 WPEM Tooltip mit AJAX-Ladedaten und Caching
// ============================================================

// Interner Cache, um wiederholte Abfragen zu vermeiden
const TooltipCache = {};

/**
 * AJAX-Abfrage: lädt Eventdaten zu einem Tag.
 * @param {string} day - Tag im Format 'YYYY-MM-DD'
 * @returns {Promise<Array>} Promise mit Event-Objekten
 */
export function fetchDayInfo(day) {
    if (!day) return Promise.resolve([]);
    if (TooltipCache[day]) return Promise.resolve(TooltipCache[day]);

    return jQuery.post(WPEM.ajaxurl, {
        action: 'wpem_tooltip_dayinfo',
        _ajax_nonce: WPEM.nonce,
        day: day
    }).then(resp => {
        if (resp.success && resp.data?.items) {

            TooltipCache[day] = resp.data.items;
            return TooltipCache[day];
        }
        throw new Error('Keine Daten');
    }).catch(err => {
        console.warn('Tooltip AJAX Fehler', err);
        return [];
    });
}

/**
 * Baut das HTML für den Tooltip-Content.
 * @param {Array} items - Array mit Event-Objekten
 * @returns {string} HTML
 */
export function buildDayTooltipHTML(items) {
    if (!items?.length) {
        return `<div class="wpem-tip-empty">Keine Einträge</div>`;
    }

    const list = items || [];
    /*
    // 🔹 Kleinsten fromdate & größten todate berechnen
    const fromMin = list.reduce((min, ev) => {
        const d = new Date(ev.fromdate || ev.todate);
        return (!min || d < min) ? d : min;
    }, null);

    const toMax = list.reduce((max, ev) => {
        const d = new Date(ev.todate || ev.fromdate);
        return (!max || d > max) ? d : max;
    }, null);

    // 🔹 Datumsbereich formatiert ausgeben
    const fromStr = fmtDMY(fromMin ? fromMin.toISOString().slice(0, 10) : '');
    const toStr   = fmtDMY(toMax ? toMax.toISOString().slice(0, 10) : '');
    */

    const rows = list.map(ev => {
        //console.debug('fieldDayEvents',ev);
        const isAnfrage = ev.status === 'Anfrage erhalten';
        const fromStr = fmtDMY(ev?.fromdate || '');
        const toStr = ev.todate > ev.fromdate ? fmtDMY(ev?.todate || '') : fromStr;
        const rangeLabel = (fromStr === toStr)
            ? `${fromStr}`
            : `${fromStr} bis ${toStr}`;

        return `
        <div class="wpem-dayevents tooltip">
            <label class="wpem-matrix-head wpem-matrix-cell">${rangeLabel}</label>    
            <table class="widefat">
              <thead>
                <tr>
                  <th>Gr</th><th>Kl</th><th>Fo</th><th>A</th>
                  <th>Titel</th>
                </tr>
              </thead>                
              <tbody>
                  <tr data-id="${ev.id}" class="js-open">
                    <td>${dot(ev.place1, isAnfrage, ev.places, 'Großer Saal')}</td>
                    <td>${dot(ev.place2, isAnfrage, ev.places, 'Kleiner Saal')}</td>
                    <td>${dot(ev.place3, isAnfrage, ev.places, 'Foyer')}</td>
                    <td>${dot(ev.booked, isAnfrage, ev.places, 'Ausgebucht')}</td>
            
                    <td>${escapeHtml(ev.title || '(ohne Titel)')}</td>
                    <td></td>
                  </tr>
                </thead>
                </tbody>
              </table>
        </div>`;
    }).join('');

    return `${rows}`;
}


/**
 * Kleine Hilfsfunktion für farbliche Saalpunkte
 */
function buildPlaces(p1, p2, p3) {
    const dot = (val, label) => {
        let cls = 'free';
        if (val == 1) cls = 'opt';
        else if (val == 2) cls = 'booked';
        return `<span class="wpem-dot wpem-${cls}" title="${label}"></span>`;
    };
    return `
        <div class="wpem-tip-places">
            ${dot(p1, 'Großer Saal')}
            ${dot(p2, 'Kleiner Saal')}
            ${dot(p3, 'Foyer')}
        </div>`;
}

/**
 * Hauptfunktion: Tooltip per Mouseover aktivieren
 * @param {HTMLElement} dayElem - Flatpickr Tag-Element
 */
export function attachAjaxTooltip(dayElem, dayYmd) {
    if (!dayElem) return;

    let showTimer = null;   // Verzögerung bis zum ersten Tooltip
    let lifeTimer = null;   // Max. Lebensdauer des Tooltips
    let isHovering = false; // Maus über dem Tag?
    let isOpen = false;     // Tooltip gerade sichtbar?

    function safeHide() {
        isHovering = false;
        isOpen = false;
        clearTimeout(showTimer);
        clearTimeout(lifeTimer);
        Tooltip.hide();
    }

    const onEnter = (e) => {
        isHovering = true;

        clearTimeout(showTimer);
        showTimer = setTimeout(() => {
            if (!isHovering) return; // Maus schon wieder weg → abbrechen

            Tooltip.show(`${shortSpinnerHTML()}`, e.pageX, e.pageY);
            isOpen = true;

            // Lebensdauer: z.B. max. 5 Sekunden, dann auf jeden Fall zu
            clearTimeout(lifeTimer);
            lifeTimer = setTimeout(() => {
                if (!isHovering) {
                    safeHide();
                }
            }, 500);

            fetchDayInfo(dayYmd)
                .then(items => {
                    if (!isHovering || !isOpen) return;
                    const html = buildDayTooltipHTML(items);
                    Tooltip.show(html); // Position wird vom Tooltip selbst gehalten
                })
                .catch(() => {
                    if (!isHovering || !isOpen) return;
                    Tooltip.show('Fehler beim Laden');
                });

        }, 200); // kleine Verzögerung gegen nervöses „An-Aus“
    };

    const onMove = (e) => {
        if (!isOpen) return;
        Tooltip.move(e.pageX, e.pageY);
    };

    const onLeave = () => {
        // Maus verlässt den Tag → Tooltip zu (mit kleinem Delay optional)
        safeHide();
    };

    const onClickDay = () => {
        // Bei Klick auf ein Datum (Tag auswählen) → Tooltip SOFORT weg
        safeHide();
    };

    const onDocClick = (ev) => {
        // Klick irgendwo anders auf der Seite → Tooltip zu
        if (!dayElem.contains(ev.target)) {
            safeHide();
        }
    };

    const onScroll = () => {
        // Bei Scrollen ist die Position eh hinfällig → Tooltip zu
        if (isOpen) {
            safeHide();
        }
    };

    const onBlur = () => {
        // Fenster verliert Fokus → Tooltip zu
        safeHide();
    };

    // Events registrieren
    dayElem.addEventListener('mouseenter', onEnter);
    dayElem.addEventListener('mousemove', onMove);
    dayElem.addEventListener('mouseleave', onLeave);
    dayElem.addEventListener('click', onClickDay);

    document.addEventListener('click', onDocClick);
    document.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('blur', onBlur);

    // Optional: Destroy-Funktion zurückgeben, falls du Daymap neu rendert
    return function destroyTooltipHandlers() {
        safeHide();
        dayElem.removeEventListener('mouseenter', onEnter);
        dayElem.removeEventListener('mousemove', onMove);
        dayElem.removeEventListener('mouseleave', onLeave);
        dayElem.removeEventListener('click', onClickDay);
        document.removeEventListener('click', onDocClick);
        document.removeEventListener('scroll', onScroll);
        window.removeEventListener('blur', onBlur);
    };
}

function buildDayEvents(values){
    console.debug('buildDayEvents',values);
    const list = values || [];
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

/* ---------- Bindings ---------- */
document.addEventListener('mousedown', e => {
    // Tooltip schließen, wenn irgendwo im Kalender geklickt wird
    if (e.target.closest('.flatpickr-day')) {
        if (window.Tooltip && typeof Tooltip.hide === 'function') {
            console.debug('Tooltip hide on day click', window.Tooltip);
            Tooltip.hide();
        }
    }
}, true); // capture=true: damit das vor Flatpickr selbst greift
