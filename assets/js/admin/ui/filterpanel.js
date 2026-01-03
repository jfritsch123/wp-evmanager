
import {loadList} from "./renderlist.js";
import {updateSortStateForMode} from "./renderlist.js";
import {lastDayOfMonthStr} from "../util/helper.js";
import {loadEditor} from "./rendereditor.js";

export const state = {
    filters: {
        q:'',
        editor:'',
        status:'Anfrage erhalten',            // default
        //start_ab_today:true,
        //start_from:'',   // nur From-Feld
        //fromdate_max:'', // alternativ: min/max
        fromdate_min:'',
        fromdate_max:'',
        place1:['0'], place2:['0'], place3:['0'], // defaults
        booked_only:0,
        trash:0,
    },

    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 1,
    activeId: null,
    order_dir: 'DESC',
    order_by: 'fromdate',
    // ✅ NEU
    ui: {
        trashMode: false,
        restoreFocusId: null,
    }
};

// Mapping Status → Active-Klasse
export const statusActiveClasses = {
    'Anfrage erhalten': 'anfrage-active',
    'In Bearbeitung': 'bearbeitung-active',
    'Gebucht': 'gebucht-active',
    'Vereinbarung unterzeichnet': 'vereinbarung-active'
};

export function clearHallRadios(){
    const $f = $('.wpem-filters-ajax');
    $f.find('input[type=radio][name="place1"]').prop('checked', false);
    $f.find('input[type=radio][name="place2"]').prop('checked', false);
    $f.find('input[type=radio][name="place3"]').prop('checked', false);
}

export function setDefaultFilterState(){
    state.filters.q = '';
    state.filters.editor = '';

    // Jahr, Monat
    state.filters.fromdate_min = '';
    state.filters.fromdate_max = '';

    // Status: leer (kein Filter)
    state.filters.status = [];

    // Saal-Radios: „egal“ (leer)
    state.filters.place1 = '';
    state.filters.place2 = '';
    state.filters.place3 = '';

    // Ausgebucht: aus
    state.filters.booked_only = 0;

    // Trash: aus
    state.filters.trash = 0;

    // Pager zurücksetzen
    state.page = 1;
}

export function readFilters(){
    const $f = $('.wpem-filters-ajax');

    state.filters.q      = $f.find('[name=q]').val() || '';
    state.filters.editor = $f.find('[name=editor]').val() || '';

    // Status (Checkboxen → Array)
    state.filters.status = $f.find('input[name="status[]"]:checked')
        .map(function(){ return $(this).val(); })
        .get();

    const y  = $f.find('select[name="filter_year"]').val() || '';
    const mm = $f.find('select[name="filter_month"]').val() || '';

    let fromMin = ($f.find('input[name="fromdate_min"]').val() || '').trim();
    let fromMax = ($f.find('input[name="fromdate_max"]').val() || '').trim();
    //const abToday = $f.find('input[name=start_ab_today]').is(':checked');

    if (y) {
        if (mm) {
            fromMin = `${y}-${mm}-01`;
            fromMax = lastDayOfMonthStr(y, mm);
        } else {
            fromMin = `${y}-01-01`;
            fromMax = `${y}-12-31`;
        }
    }
    /*else {
        if (!fromMin && abToday) {
            fromMin = todayYmd();
        }
    }*/

    state.filters.fromdate_min = fromMin;
    if(fromMax){
        state.filters.fromdate_max = fromMax;
    }

    // Startdatum-Logik
    // const fromVal = ($f.find('input[name=start_from]').val() || '').trim();
    // state.filters.start_ab_today = abToday;
    // state.filters.start_from     = fromVal;

    // NEU: Saal-Radios (ein Wert oder '')
    const valOrEmpty = name => {
        const v = $f.find(`input[name="${name}"]:checked`).val();
        return (v === undefined) ? '' : String(v);
        // '' => kein Filter
    };
    state.filters.place1 = valOrEmpty('place1'); // Kleiner Saal
    state.filters.place2 = valOrEmpty('place2'); // Großer Saal
    state.filters.place3 = valOrEmpty('place3'); // Foyer

    // Ausgebucht
    state.filters.booked_only = $f.find('input[name=booked_only]').is(':checked') ? 1 : 0;

    // trash
    // state.filters.trash = $f.find('input[name=trash]').is(':checked') ? 1 : 0;
}

export function resetFilters($f,$mode = null ){
    // Browser-Reset für einfache Inputs
    if ($f[0]) $f[0].reset();

    // Status-Checkboxen:
    $f.find('input[name="status[]"]').prop('checked', false);

    // Alle aktiven Farbklassen entfernen
    $f.find('.wpem-status label').removeClass(Object.values(statusActiveClasses));

    // Jahr/Monat explizit zurücksetzen:
    const $year  = $f.find('select[name="filter_year"]').val('').prop('disabled', false);
    const $month = $f.find('select[name="filter_month"]').val('').prop('disabled', true).empty()
        .append(jQuery(`<option/>`, { value:'', text:'Monat wählen' }));

    // Datepicker-Felder wieder aktivieren & leeren
    /*
    const minEl = $f.find('input[name="fromdate_min"]')[0];
    const maxEl = $f.find('input[name="fromdate_max"]')[0];
    if (minEl){ setDateInputEnabled(minEl, true); minEl.value = ''; }
    if (maxEl){ setDateInputEnabled(maxEl, true); maxEl.value = ''; }

    // „Ab heute“ wieder aktiv + angehakt
    $f.find('input[name="start_ab_today"]').prop('disabled', false).prop('checked', true);
    */

    // Saal-Radios komplett leeren (kein Wert = egal)
    clearHallRadios();

    // Ausgebucht aus
    $f.find('input[name="booked_only"]').prop('checked', false);

    // internen State auf Defaults setzen und neu laden
    setDefaultFilterState();

    if( $mode === 'onlyUI') return; // nur UI zurücksetzen

    loadList();
}

/* ---------- Bindings ---------- */
$('.wpem-filters-ajax .js-wpem-new').on('click', () => loadEditor(0));

/*
$('.wpem-filters-ajax .js-wpem-apply').on('click', function(){
    state.page = 1; // bei neuer Suche auf Seite 1
    loadList();
});
*/

$('.wpem-filters-ajax .js-wpem-reset').on('click', function(){
    const $f = $('.wpem-filters-ajax');
    resetFilters($f);
});

// Klick auf „Saal-Filter zurücksetzen“
$('.wpem-filters-ajax').on('click', '.js-clear-halls', function(e){
    e.preventDefault();
    clearHallRadios();          // alle Radios leeren
    state.filters.place1 = '';  // state zurücksetzen
    state.filters.place2 = '';
    state.filters.place3 = '';
    loadList();                 // Liste mit neuen Filtern laden
});

// Beim Laden initial setzen
$('.wpem-status input[type="checkbox"]').each(function() {
    const $input = $(this);
    const value = $input.val();
    const cls = statusActiveClasses[value];
    const $label = $input.closest('label');

    if ($input.is(':checked')) {
        $label.addClass(cls);
    } else {
        $label.removeClass(cls);
    }
});

// Beim Ändern live aktualisieren
$(document).on('change', '.wpem-status input[type="checkbox"]', function() {
    const $input = $(this);
    const value = $input.val();
    const cls = statusActiveClasses[value];
    const $label = $input.closest('label');

    if ($input.is(':checked')) {
        $label.addClass(cls);
    } else {
        $label.removeClass(cls);
    }

    if(value === 'Anfrage erhalten'){
        const isChecked = $input.is(':checked');
        updateSortStateForMode(isChecked);
    }
});

/*
// === Spezialfall: Anfrage erhalten ===
$('input[name="status[]"][value="Anfrage erhalten"]').on('change', async function() {
    const isChecked = $(this).is(':checked');
    updateSortStateForMode(isChecked);
    //await loadList(); // wartet sauber auf Abschluss
});
*/

