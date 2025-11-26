function statusKey(v){ const n = Number(v); return n===2 ? 'booked' : (n===1 ? 'opt' : 'free'); }
function statusText(v){ const n = Number(v); return n===2 ? 'belegt' : (n===1 ? 'optional' : 'frei'); }

/** Baut hübsches HTML für die drei Säle (mehrzeilig) */
export function buildPlacesHTML(p,booked){
    if(booked){
       return `<div class="wpem-tip-row" style="flex-wrap: wrap;width:150px">
         <span class="wpem-tip-dot wpem-tip-dot--booked"></span>
         <span class="wpem-tip-label">Veranstaltung</span>
         <span class="wpem-tip-status" style="width:100%;">ausgebucht</span>
       </div>`;
    }
    const g = Number(p?.place1 ?? 0); // Großer
    const k = Number(p?.place2 ?? 0); // Kleiner
    const f = Number(p?.place3 ?? 0); // Foyer
    const rows = [
        { label: 'Großer Saal', key: statusKey(g), text: statusText(g) },
        { label: 'Kleiner Saal', key: statusKey(k), text: statusText(k) },
        { label: 'Foyer',        key: statusKey(f), text: statusText(f) },
    ];

    return rows.map(r =>
        `<div class="wpem-tip-row">
       <span class="wpem-tip-dot wpem-tip-dot--${r.key}"></span>
       <span class="wpem-tip-label">${r.label}:</span>
       <span class="wpem-tip-status">${r.text}</span>
     </div>`
    ).join('');
}


/* Tooltip-Manager (innerHTML statt textContent) */
export const Tooltip = {
    el: null,
    init() {
        if (this.el) return;
        this.el = document.createElement('div');
        this.el.className = 'wpem-tooltip';
        document.body.appendChild(this.el);
    },
    show(html, x, y) {
        this.init();
        this.el.innerHTML = html;
        this.move(x, y);
        this.el.classList.add('show');
    },
    move(x, y) {
        if (!this.el) return;
        this.el.style.left = (x + 12) + 'px';
        this.el.style.top  = (y + 12) + 'px';
    },
    hide() { this.el?.classList.remove('show'); }
};

window.Tooltip = Tooltip; // global verfügbar machen, zB. in admin/util/tooltip.js

// Tooltip-Events gesammelt binden + beim Klick schließen
export function attachTooltip(dayElem, html){
    dayElem.addEventListener('mouseenter', e => Tooltip.show(html, e.pageX, e.pageY));
    dayElem.addEventListener('mousemove',  e => Tooltip.move(e.pageX, e.pageY));
    dayElem.addEventListener('mouseleave', () => Tooltip.hide());
    dayElem.addEventListener('click',      () => Tooltip.hide()); // wichtig: nach Datumsklick Tooltip ausblenden
}

// ▼ utils: responsive showMonths
export function desiredMonths(){
    const w = window.innerWidth || document.documentElement.clientWidth;
    if (w < 767) return 1;        // sm-
    if (w < 1024) return 2;       // md
    return 3;                     // lg+
}
export function applyResponsiveMonths(fpInstances){
    const n = desiredMonths();
    fpInstances.forEach(fp => { if (fp) fp.set('showMonths', n); });
}

// ▼ debounce helper (damit set() nicht bei jedem Pixel feuert)
export function debounce(fn, wait=150){
    let t; return (...args)=>{ clearTimeout(t); t = setTimeout(()=>fn(...args), wait); };
}

export function addDatepickerHelp(el, text) {
    if (!el) return;
    // Wenn schon vorhanden, nicht doppelt hinzufügen
    if (el.parentElement.querySelector('.wpem-datepicker-help')) return;

    const help = document.createElement('div');
    help.className = 'wpem-datepicker-help';
    help.textContent = text;
    el.parentElement.appendChild(help);
}

