import { post } from "../data/api.js";
import { state,readFilters } from "./filterpanel.js";
import { showError} from "./rendereditor.js";
import { COLUMNS } from '../util/columns.js';
import { escapeHtml, todayYmd, renderHelpIcon, spinnerHTML} from '../util/helper.js';
//import { updateDateFilterAvailability, withFilterLock } from './filterhelper.js';
import { renderPagination, bindPagination } from '../util/pagination.js';
import { loadEditor,highlightActive } from "./rendereditor.js";
import {applyTrashMode} from "./filterhelper.js";

window.$ = window.jQuery;

export function loadList() {

    showLoadingSpinner();
    readFilters();
    /*
    let fromdate_min = '';
    if (state.filters.fromdate_min) {
        fromdate_min = state.filters.fromdate_min;
    } else if (state.filters.start_ab_today) {
        fromdate_min = todayYmd();
    }
    */
    const payload = Object.assign({},
        state.filters, {
            page: state.page,
            per_page: state.per_page,
            //fromdate_min, // das Backend versteht das bereits
            order_dir: state.order_dir,
            order_by: state.order_by
        });

     return post('wpem_list_events', payload)
        .then(data => {
            // Server sollte total, page, per_page, total_pages mitsenden
            state.total       = Number(data.total || 0);
            state.total_pages = Number(data.total_pages || (state.total ? Math.ceil(state.total / state.per_page) : 1));
            state.page        = Number(data.page || state.page);
            state.per_page    = Number(data.per_page || state.per_page);
            state.order_by    = data.args['order_by'] || state.order_by;
            state.order_dir   = data.args['order_dir'] || state.order_dir;

            renderList(data.items || []);
        })
        .fail(err => showError('#wpem-list', err))
        .always(() => hideLoadingSpinner());
}

export function renderList(items) {
    jQuery('#wpem-editor').html(''); // Editor leeren
    const $list = jQuery('#wpem-list');
    if (!items || !items.length) {
        $list.html(`<div class="notice notice-info"><p>${WPEM.i18n.empty}</p></div>`);
        return;
    }

    // 🧩 Tabellenkopf
    const thead = `
      <tr>
        ${COLUMNS.map(c => {

            const isActive = c.key === state.order_by;
            const dir = isActive ? state.order_dir : '';
            const iconClass = dir === 'ASC'
                ? 'dashicons-arrow-up-alt2'
                : dir === 'DESC'
                    ? 'dashicons-arrow-down-alt2'
                    : 'dashicons-arrow-down-alt2'; //'dashicons-minus';
            let helpIcon = '';
            if (c.sortable) {

            if(c.key == 'fromdate'){
                helpIcon = renderHelpIcon('sort_events', 'Sortier-Hilfe anzeigen');
            }
            if(c.key == 'ts'){
                helpIcon = renderHelpIcon('order-ts', 'Filter-Hilfe anzeigen');
            }
        }

        return `
          <th data-key="${c.key}"
              class="${c.sortable ? 'sortable' : ''} ${isActive ? 'is-sorted' : ''}">
            <div>
                ${escapeHtml(c.label)}
                ${c.sortable ? `<span class="dashicons ${iconClass} sort-icon"></span>` : ''}
                ${helpIcon}
            </div>
          </th>
            `;
        }).join('')}
      </tr>
    `;

    // 🧩 Tabellenzeilen
    const rows = items.map(e => {
        const active = (state.activeId === Number(e.id)) ? ' is-active' : '';
        const notEditable = !e.editable ? ' is-readonly' : '';
        const isInTrash = e.trash == 1 ? ' is-trashed' : '';
        const tds = COLUMNS.map(c => `<td>${c.render(e)}</td>`).join('');
        return `<tr class="${active}${notEditable}${isInTrash}" data-id="${e.id}" ${e.editable ? '' : 'data-readonly="1"'}>${tds}</tr>`;
    }).join('');

    // 📃 Pagination
    const pagerHtml = renderPagination({
        page: state.page,
        perPage: state.per_page,
        total: state.total,
        totalPages: state.total_pages
    });

    // 🧩 Gesamtes HTML einfügen
    $list.html(`
        <table class="widefat striped wpem-table">
          <thead>${thead}</thead>
          <tbody>${rows}</tbody>
        </table>
        ${pagerHtml}
    `);

    // 🔁 Pagination Clicks
    bindPagination($list, async (targetPage) => {
        state.page = targetPage;
        await loadList();
        highlightActive();
    });
}

// nur eine Zeile aktualisieren
export function renderEventRow(e) {
    const active = (state.activeId === Number(e.id)) ? ' is-active' : '';
    const notEditable = !e.editable ? ' is-readonly' : '';
    const tds = COLUMNS.map(c => `<td>${c.render(e)}</td>`).join('');

    return `<tr class="${active}${notEditable}" data-id="${e.id}" ${e.editable ? '' : 'data-readonly="1"'}>
                ${tds}
            </tr>`;
}

// 🧠 Hilfsfunktion: Standardrichtung für eine Spalte ermitteln
function getDefaultSort(key) {
    const col = COLUMNS.find(c => c.key === key);
    return col && col.defaultDir ? col.defaultDir : 'DESC';
}

// 🧭 Sortierzustand abhängig vom „Anfrage erhalten“-Schalter setzen
export function updateSortStateForMode(isAnfrageModus) {
    state.order_by = isAnfrageModus ? 'ts' : 'fromdate';
    state.order_dir = getDefaultSort(state.order_by);
}

// =====================================================
// 🔹 Spinner Utility
// =====================================================
function showLoadingSpinner() {
    const $list = $('#wpem-list').html('');
    if ($list.find('.wpem-loading').length === 0) {
        $list.prepend(`
      ${spinnerHTML()}
    `);
    }
}

function hideLoadingSpinner() {
    $('#wpem-list .wpem-loading').fadeOut(200, function () {
        $(this).remove();
    });
}

/* ---------- Bindings ---------- */
$('#wpem-list')
    .on('click', 'a.js-open', function(e) {
        e.preventDefault();
        const $link = $(this);
        // Falls das Event readonly markiert ist → abbrechen
        if ($link.closest('tr').data('readonly')) {
            alert(WPEM.i18n.readonly_event || 'Dieses Event ist nur lesbar.');
            return;
        }
        loadEditor($link.data('id'));
    })
    .on('click', 'tr[data-id]', function(e) {
        // Klick auf Bedienelemente wie bisher ignorieren
        if ($(e.target).is('a,button,input,select,textarea,label')) return;

        const $tr = $(this);
        if ($tr.data('readonly')) {
            alert(WPEM.i18n.readonly_event || 'Dieses Event ist nur lesbar.');
            return;
        }
        loadEditor($tr.data('id'));
    });


// =====================================================
// 🔸 Klick auf Spaltenüberschrift → Sortierrichtung ändern
// =====================================================
$(document).on('click', 'th.sortable', async function (e) {
    const $target = $(e.target);
    const $th = $(this);
    const key = $th.data('key');

    // 🛑 Abbrechen, wenn auf das Hilfe-Icon oder einen Link geklickt wurde
    if ($target.closest('.wpem-help, .wpem-help span, a.wpem-help').length) {
        return; // nichts tun
    }
    if (state.order_by === key) {
        // Richtung wechseln
        state.order_dir = state.order_dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
        // Neue Spalte → Standardrichtung
        state.order_by = key;
        state.order_dir = getDefaultSort(key);
    }

    // 🔄 Animation: Pfeil drehen
    const $icon = $th.find('.sort-icon');
    if ($icon.length) {
        $icon.addClass('spin');
        setTimeout(() => $icon.removeClass('spin'), 400); // nach 400ms wieder entfernen
    }
    await loadList();
});

// =====================================================
// Automatisches Neuladen bei Änderungen
// neue Version
// =====================================================

const $filters = $('.wpem-filters-ajax');
$filters.on('change input', ':input', function (e) {
    if (e.type === 'input' && this.type === 'checkbox') return;
    const $target = $(e.target);
    // --- Papierkorb ---
    if ($target.is('[name="trash"]')) {
        state.ui.trashMode = this.checked;
        applyTrashMode();
        return; // ⬅️ hier bewusst abbrechen
    }

    // Wenn Papierkorb aktiv → alles andere ignorieren
    if (state.ui.trashMode) {
        return;
    }

    /*
    if ($target.is('[name="filter_year"]')) {
        withFilterLock(() => updateDateFilterAvailability());
    }
    */

    // Textfelder nur bei Enter
    if ($target.is('input[type="text"], input[type="search"]')) {
        return;
    }

    // 🔥 HIER Speziallogik EINMAL behandeln
    if ($target.is('input[name="status[]"]')) {
        const isAnfrage = (
            $filters
                .find('input[name="status[]"][value="Anfrage erhalten"]')
                .is(':checked')
        );
        updateSortStateForMode(isAnfrage);
    }
    loadList();
});


// 5️⃣ Texteingaben: Nur auf ENTER reagieren
$filters.on('keydown', 'input[type="text"], input[type="search"]', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); // verhindert Formular-Submit
        //readFilters();
        loadList();
    }
});


$filters.on('click', 'input[type="button"][name="qsend"]', function() {
    const $q = $filters.find('input[name="q"]');
    if ($q.val()) {
        //$('.wpem-filters-ajax .js-wpem-reset').click();
        //$q.attr('placeholder','letzte Suche: ' + $q.val());
        //$q.val(''); // Suchfeld leeren
        //readFilters();
        loadList();
    }
});

$filters.on('click', 'input[type="button"][name="qreset"]', function() {
    const $q = $filters.find('input[name="q"]');
    if($q.val()){
        $q.val('');
        loadList();
    }
});

$filters.on('click', 'input[type="button"][name="fromdate_max_reset"]', function() {
    const $maxdate = $filters.find('input[name="fromdate_max"]');
    const fp = $maxdate[0]?._flatpickr;
    if(fp){
        fp.clear();
    }
});


// =====================================================
// 🔸 Initialzustand beim Laden der Seite
// =====================================================
$(document).ready(function () {
    const isAnfrageChecked = $('input[name="status[]"][value="Anfrage erhalten"]').is(':checked');
    updateSortStateForMode(isAnfrageChecked);
});





