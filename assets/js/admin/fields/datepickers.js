// Nutzt WPEM.calendarDays (assoziatives Objekt "YYYY-MM-DD" => {cats,p,status,booked})
import { buildPlacesHTML, attachTooltip} from '../../frontend/util.js';
import { attachAjaxTooltip} from "../util/tooltip.js";
import {ymd} from '../util/helper.js'
//import {applyMatrixFromPlaces} from "../util/helper.js";

function applyFromGlobalMap(dayElem) {
    const TODAY_YMD = ymd(new Date()); // für Vergleiche als String

    const dt = dayElem.dateObj;
    if (!dt || !window.WPEM || !WPEM.calendarDays) return;

    const key = ymd(dt);

    if(key < TODAY_YMD) return; // Vergangenheit nicht markieren

    const info = WPEM.calendarDays[key];

    if (!info) {
        dayElem.classList.add('wpem-cal-green');
        return;
    }

    // Klassen nach cats (keine Priorisierung)
    if (info.cats?.red)    dayElem.classList.add('wpem-cal-red');
    if (info.cats?.orange) dayElem.classList.add('wpem-cal-orange');
    if (info.cats?.green)  dayElem.classList.add('wpem-cal-green');

    // Daten-Attributes zur Belegung
    const p = info.p || {place1:0,place2:0,place3:0};
    const booked = info.booked || 0;

    dayElem.dataset.places = `K:${p.place1};G:${p.place2};F:${p.place3}`;
    dayElem.dataset.status = (info.status && info.status.length) ? info.status.join('|') : '';
    dayElem.dataset.booked = booked ? '1' : '0';

    attachAjaxTooltip(dayElem, key);
    //attachTooltip(dayElem, buildPlacesHTML(p, booked));
}

/* Neu: Matrix direkt aus Info (p.place1/2/3) setzen */
function applyMatrixFromInfo(info) {
    if (!info || !info.p) return;
    const matrix = document.querySelector('#wpem-editor .wpem-matrix');
    if (!matrix) return;

    const p = info.p; // {place1, place2, place3}
    // place1 = Großer Saal, place2 = Kleiner Saal, place3 = Foyer
    [['place1', p.place1], ['place2', p.place2], ['place3', p.place3]].forEach(([name, val]) => {
        const inp = matrix.querySelector(`input[name="${name}"][value="${val}"]`);
        if (inp) inp.checked = true;
    });
}

/** Initialisierung für inputs with class .js-wpem-date */
export function initDatePickers() {
    if (!window.flatpickr) return;

    const baseOpts = {
        locale: 'de',
        altInput: true,
        dateFormat: 'Y-m-d',  // Wert im echten <input name=...>
        altFormat: 'd.m.Y',  // Anzeige
        allowInput: true,
        showMonths: 3,
        onDayCreate: (dObj, dStr, fp, dayElem) => applyFromGlobalMap(dayElem),

    };

    const fromEl = document.querySelector('#wpem-editor [name="fromdate"]');
    const toEl = document.querySelector('#wpem-editor [name="todate"]');

    // toDate zuerst initialisieren (damit Instanz existiert)
    // Beim Öffnen des todate-Pickers (onOpen) immer die aktuelle fromdate als minDate setzen.
    if (toEl) {
        try {
            if (toEl.type === 'date') toEl.type = 'text';
        } catch (e) {}

        if (toEl._flatpickr) toEl._flatpickr.destroy();

        window.flatpickr(toEl, Object.assign({}, baseOpts, {
            onOpen(selectedDates, dateStr, inst) {
                if (!fromEl || !fromEl._flatpickr) return;

                const fromVal = fromEl._flatpickr.input?.value;
                if (fromVal) {
                    // minDate setzen, bevor der Kalender angezeigt wird
                    inst.set('minDate', fromVal);
                }
            }
        }));
    }

    // fromDate initialisieren
    if (fromEl) {
        try {
            if (fromEl.type === 'date') fromEl.type = 'text';
        } catch (e) {
        }
        if (fromEl._flatpickr) fromEl._flatpickr.destroy();

        // >>> HIER: onChange direkt im opts des FROM-Pickers
        window.flatpickr(fromEl, Object.assign({}, baseOpts, {
            onChange(selectedDates, dateStr, inst) {
                const isNew = (function () {
                    const form = document.querySelector('#wpem-form');
                    return form && form.dataset && form.dataset.id === '0';
                })();

                if (!dateStr) return;

                // todate übernehmen + minDate setzen (Tage davor nicht klickbar)
                if (toEl && toEl._flatpickr) {
                    const fpTo = toEl._flatpickr;

                    // minDate IMMER setzen
                    fpTo.set('minDate', dateStr);

                    // bisheriges Enddatum holen (als YYYY-MM-DD)
                    const currentTo = fpTo.input?.value || '';

                    // Enddatum NUR automatisch setzen,
                    // wenn es leer ist ODER vor dem neuen Startdatum liegt
                    if (!currentTo || currentTo < dateStr) {
                        fpTo.setDate(dateStr, true);
                    }
                }

                // Nur bei Neuanlage: Matrix gemäß Belegung des gewählten Tages setzen
                if (isNew && window.WPEM && WPEM.calendarDays) {
                    const info = WPEM.calendarDays[dateStr];
                    if (info) applyMatrixFromInfo(info);
                }
            },

            // Optional: auch beim Tippen reagieren
            onValueUpdate(selectedDates, dateStr, inst) {
                // Gleicher Code wie in onChange, falls du Tippen erlaubst
            }
        }));
    }
}
