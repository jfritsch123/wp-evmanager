// assets/js/admin/util/pagination.js

/**
 * Berechnet eine kompakte Seitenleiste (z.B. max 7 Buttons um die aktuelle Seite).
 */
function calcPageRange(cur, max, width = 7) {
    const w = Math.max(3, width | 0);
    const half = Math.floor(w / 2);
    let start = Math.max(1, cur - half);
    let end = Math.min(max, start + w - 1);
    start = Math.max(1, end - w + 1);

    const arr = [];
    for (let i = start; i <= end; i++) arr.push(i);
    return arr;
}

/**
 * Gibt das Pager-HTML zurück. Auf Buttons liegen data-page Attribute.
 * @param {{page:number, perPage:number, total:number, totalPages:number}} p
 */
export function renderPagination(p) {

    const page = Number(p?.page || 1);
    const perPage = Number(p?.perPage || 20);
    const total = Number(p?.total || 0);
    const totalPages = Math.max(1, Number(p?.totalPages || Math.ceil(total / perPage)));

    if (totalPages <= 1)
        return `
            <div class="wpem-pager" data-page="${page}" data-total="${total}" data-totalpages="${totalPages}">
              <div class="wpem-pager__inner">
                <span class="wpem-pager__meta">${total} Events</span>
              </div>
            </div>
          `;


    const range = calcPageRange(page, totalPages, 7);
    const btn = (target, label, disabled = false, cls = '') =>
        `<button type="button" class="button wpem-page ${cls}" data-page="${target}" ${disabled ? 'disabled' : ''}>${label}</button>`;

    const first = btn(1, '«', page === 1, 'js-page-first');
    const prev  = btn(Math.max(1, page - 1), '‹', page === 1, 'js-page-prev');
    const next  = btn(Math.min(totalPages, page + 1), '›', page === totalPages, 'js-page-next');
    const last  = btn(totalPages, '»', page === totalPages, 'js-page-last');

    const nums  = range.map(n =>
        `<button type="button" class="button wpem-page js-page ${n === page ? 'button-primary' : ''}" data-page="${n}">${n}</button>`
    ).join('');

    const from = (page - 1) * perPage + 1;
    const to   = Math.min(page * perPage, total);

    return `
    <div class="wpem-pager" data-page="${page}" data-total="${total}" data-totalpages="${totalPages}">
      <div class="wpem-pager__inner">
        ${first}${prev}
        ${nums}
        ${next}${last}
        <span class="wpem-pager__meta">${from}–${to} / ${total} Events</span>
      </div>
    </div>
  `;
}


/**
 * Bindet Click-Events innerhalb eines Containers. Ruft onChange(targetPage) auf.
 * @param {HTMLElement|jQuery|string} container
 * @param {(page:number)=>void} onChange
 */
export function bindPagination(container, onChange) {
    const $root = (window.jQuery && container && container.jquery)
        ? container
        : window.jQuery(container);

    if (!$root || !$root.length) return;

    $root.off('click.wpemPager', '.wpem-page'); // doppelte Bindings vermeiden
    $root.on('click.wpemPager', '.wpem-page', function (e) {
        e.preventDefault();
        const target = Number(this.getAttribute('data-page') || 1);
        if (typeof onChange === 'function') onChange(target);
    });
}
