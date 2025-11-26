export function bootFilterDatePickers() {
    if (!window.flatpickr) return;
    const selMin = '.wpem-filters-ajax input[name="fromdate_min"]';
    const selMax = '.wpem-filters-ajax input[name="fromdate_max"]';

    const opts = {
        locale: 'de',
        altInput: true,
        dateFormat: 'Y-m-d',
        altFormat: 'd.m.Y',
        allowInput: true,
        disableMobile: true
    };
    const minEl = document.querySelector(selMin);
    const maxEl = document.querySelector(selMax);
    if (minEl) window.flatpickr(minEl, opts);
    if (maxEl) window.flatpickr(maxEl, opts);
}

// DOM-ready-Hülle
export function bootFilterDatePicker() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilterDatePicker);
    } else {
        initFilterDatePicker();
    }
}

// Helper: warte bis beide Picker bereit sind
export function waitForPickers() {
    return new Promise((resolve, reject) => {
        let tries = 0;
        const iv = setInterval(() => {
            const fromEl = document.querySelector('#wpem-editor [name="fromdate"]');
            const toEl   = document.querySelector('#wpem-editor [name="todate"]');
            if (fromEl?._flatpickr && toEl?._flatpickr) {
                clearInterval(iv);
                resolve({ fromEl, toEl });
            }
            if (++tries > 50) {
                clearInterval(iv);
                reject(new Error('Flatpickr not ready'));
            }
        }, 60);
    });
}

// Helper: Datum auf einen Flatpickr setzen (YYYY-MM-DD)
export function setPickerDate(el, ymd) {
    if (el?._flatpickr) el._flatpickr.setDate(ymd, true); // true = change event auslösen
    else if (el) el.value = ymd; // Fallback
}

// Helper: minDate setzen
export function setPickerMinDate(el, ymd) {
    if (el?._flatpickr) el._flatpickr.set('minDate', ymd);
}

export function refreshFlatpickr() {
    document.querySelectorAll('.wpem-datepicker').forEach(el => {
        if (el._flatpickr) {
            el._flatpickr.set('disable', WPEM.calendarDays.disable || []);
            el._flatpickr.redraw(); // zwingt Neuzeichnung
        }
    });
}




