export function enableToggleableHallRadios(){
    const wrap = document.querySelector('.wpem-filters-ajax .wpem-halls');
    if (!wrap) return;

    // place1=Großer, place2=Kleiner, place3=Foyer
    function getHallRadioFromTarget(t){
        // direkt auf dem Input?
        if (t instanceof HTMLInputElement
            && t.type === 'radio'

            && t.name.startsWith('place')) return t;

        // über ein Label (mit for=ID)?
        const lab = t.closest('label[for]');
        if (lab) {
            const id = lab.getAttribute('for');
            const inp = id ? document.getElementById(id) : null;
            if (inp instanceof HTMLInputElement
                && inp.type === 'radio'
                && inp.name.startsWith('place')) return inp;
        }
        return null;
    }

    // merken, ob das Radio vor dem Klick aktiv war (für Toggle)
    wrap.addEventListener('mousedown', (e) => {
        const input = getHallRadioFromTarget(e.target);
        if (!input) return;
        input._wasChecked = input.checked;
    });

    // wenn es aktiv war → Klick schaltet es aus (kein Wert = "egal")
    wrap.addEventListener('click', (e) => {
        const input = getHallRadioFromTarget(e.target);
        if (!input) return;
        if (input._wasChecked) {
            input.checked = false;
            // Change-Event feuern, damit readFilters() aktualisiert
            input.dispatchEvent(new Event('change', { bubbles: true }));
            e.preventDefault();
            e.stopPropagation();
        }
    });
}

export function initYearMonthFilters(){
    const $f = jQuery('.wpem-filters-ajax');
    if (!$f.length || !window.WPEM_FILTER) return;

    const $year   = $f.find('select[name="filter_year"]');
    const $month  = $f.find('select[name="filter_month"]');
    const years   = WPEM_FILTER.years || [];

    const elFromMin = $f.find('input[name="fromdate_min"]')[0];
    const elFromMax = $f.find('input[name="fromdate_max"]')[0];
    const $abToday  = $f.find('input[name="start_ab_today"]');

    // Jahre initial befüllen
    years.forEach(y => {
        $year.append(jQuery(`<option/>`, { value:String(y), text:String(y) }));
    });

    // Events
    $year.on('change', function(){
        if (this.value) {
            $month.prop('disabled', false);
            fillMonthsForYear(this.value,$month);
        }else{
            $month.prop('disabled', true).val('');
        }
        //updateDateFilterAvailability();
    });
    //$month.on('change', updateDateFilterAvailability);

    $abToday.on('change', function() {
        if (this.checked) {
            elFromMin.value = '';
            setDateInputEnabled(elFromMin, false);
        } else {
            setDateInputEnabled(elFromMin, true);
        }
    });

};

// Monate für gewähltes Jahr rendern
function fillMonthsForYear(y,$month){
    const monthMap     = WPEM_FILTER.monthsByYear || {};
    $month.empty().append(jQuery(`<option/>`, { value:'', text:'Monat wählen' }));
    const arr = monthMap[String(y)] || [];
    arr.forEach(obj => { // obj = {num:'01', name:'Jänner'}
        $month.append(jQuery(`<option/>`, { value: obj.num, text: obj.name }));
    });
}


// — Flatpickr-Input sauber (de-)aktivieren, inkl. altInput —
export function setDateInputEnabled(inputEl, enabled){
    if (!inputEl) return;
    const fp = inputEl._flatpickr;

    if (fp) {
        // sichtbares Eingabefeld (bei altInput:true)
        if (fp.altInput) {
            fp.altInput.disabled = !enabled;
            if (!enabled) fp.altInput.value = '';
        }

        // echtes Feld (mit name=…)
        fp._input.disabled = !enabled;
        if (!enabled) {
            fp._input.value = '';
            fp.clear(); // Flatpickr-internen Zustand leeren
        }

    } else {
        // kein Flatpickr vorhanden
        inputEl.disabled = !enabled;
        if (!enabled) inputEl.value = '';
    }
}


// =====================================================
// 🔸 not used currently
// =====================================================
// — zentrale (De-)Aktivierungslogik — (überarbeitete Version) —
/**
 * withFilterLock(fn)
 * ------------------
 * Führt eine Funktion nur dann aus, wenn kein anderer
 * Filter-Update-Prozess gerade läuft.
 *
 * Verhindert Endlosschleifen oder Doppeltrigger bei
 * schnellen Input-Änderungen (z. B. change + input gleichzeitig).
 *
 * Dank Closure ist der Lock-Wert privat gespeichert und
 * außerhalb der Funktion nicht zugänglich.
 */
export const withFilterLock = (() => {
    let locked = false; // private Variable, nur im Closure sichtbar

    return function (fn) {
        if (locked) return; // ⛔ bereits aktiv → überspringen
        locked = true;
        try {
            fn(); // ✅ ausführen
        } catch (err) {
            console.error('[withFilterLock] error:', err);
        } finally {
            locked = false; // 🔓 wieder freigeben
        }
    };
})();


// not used currently
export function updateDateFilterAvailability(clearStaus = false) {
    return; // alle filter kombinierbar, darum hier abbrechen

    const $f = jQuery('.wpem-filters-ajax');
    if (!$f.length || !window.WPEM_FILTER) return;

    const $q       = $f.find('input[name="q"]');
    //const $editor  = $f.find('select[name="editor"]');
    const $year    = $f.find('select[name="filter_year"]');
    const $month   = $f.find('select[name="filter_month"]');
    const $abToday = $f.find('input[name="start_ab_today"]');

    //const elFromMin = $f.find('input[name="fromdate_min"]')[0];
    const elFromMax = $f.find('input[name="fromdate_max"]')[0];

    const y  = String($year.val() || '');
    const mm = String($month.val() || '');

    // 🧩 kleine Helper
    const disableDates = () => {
        //setDateInputEnabled(elFromMin, false);
        setDateInputEnabled(elFromMax, false);
    };
    const enableDates = () => {
        //setDateInputEnabled(elFromMin, true);
        setDateInputEnabled(elFromMax, true);
    };
    const resetYearMonth = (yearDisabled = false) => {
        $year.prop('disabled', yearDisabled).val('');
        $month.prop('disabled', true)
            .val('')
            .empty()
            .append(`<option value="">Monat wählen</option>`);
    };
    const disableAbToday = () => $abToday.prop('checked', false).prop('disabled', true);
    const enableAbToday  = () => $abToday.prop('disabled', false);

    // 🧠 1️⃣ Falls Suchbegriff gewählt nichts machen
    if ($q.val()) {
        return;
        disableDates();
        resetYearMonth(true);
        disableAbToday();
        return;
    }

    // 🧠 2️⃣ Falls Jahr gewählt
    if (y) {
        //elFromMin.value = '';
        elFromMax.value = '';
        disableDates();
        //disableAbToday();
        $month.prop('disabled', false);
        if (!$month.val()) fillMonthsForYear(y, $month);
        return;
    }else{
        enableDates();
    }

    // 🧠 3️⃣ Falls manuelle Datumsfelder befüllt
    const hasManualDate = elFromMax?.value;
    if (hasManualDate) {
        resetYearMonth(true);
        //disableAbToday();
        return;
    }

    // 🧠 4️⃣ Standardfall (keine Einschränkung)
    enableDates();
    //enableAbToday();
    resetYearMonth(false);
}
