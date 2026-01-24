import { Tooltip, buildPlacesHTML, attachTooltip,applyResponsiveMonths,addDatepickerHelp,desiredMonths,debounce} from './util.js';

(function($){

    if (!window.flatpickr) return;
    $('.flatpickr-month').prepend('<span class="flatpickr-month-arrow prev" title="Vorheriger Monat">&lt;</span>');

    const TODAY_YMD = ymd(new Date()); // für Vergleiche als String
    function ymd(d){ const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), da=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${da}`; }
    function getEl(sel){ return document.querySelector(sel); }

    const FORM_ID  = WPEM_F?.formId;

    const FROM_ID  = WPEM_F?.fromId;
    const TO_ID    = WPEM_F?.toId;
    const HALLS_ID = WPEM_F?.hallsId;

    const selFrom   = `#wpforms-${FORM_ID}-field_${FROM_ID}`;
    const selTo     = `#wpforms-${FORM_ID}-field_${TO_ID}`;
    const selGross  = `#wpforms-${FORM_ID}-field_${HALLS_ID}_1`; // place1 (Großer Saal)
    const selKlein  = `#wpforms-${FORM_ID}-field_${HALLS_ID}_2`; // place2 (Kleiner Saal)
    const selFoyer  = `#wpforms-${FORM_ID}-field_${HALLS_ID}_3`; // place3 (Foyer)

    function labelFor(input){
        if (!input || !input.id) return null;
        return document.querySelector(`label[for="${input.id}"]`);
    }

    function setHallState(input, state /* 'off'|'free'|'opt'|'booked' */, enable){
        if (!input) return;
        input.disabled = !enable;
        const lab = labelFor(input);
        const wrap = lab?.closest('li') || lab?.parentElement;
        if (wrap) {
            wrap.classList.remove('wpem-hall--off','wpem-hall--free','wpem-hall--opt','wpem-hall--booked');
            wrap.classList.add(`wpem-hall--${state}`);
            lab && lab.classList.add('wpem-hall-label');
        }
        if (!enable) input.checked = false;
    }

    function disableAllHalls(){
        setHallState(getEl(selGross), 'off', false);
        setHallState(getEl(selKlein), 'off', false);
        setHallState(getEl(selFoyer), 'off', false);
    }

    function applyHallsForDate(dateYmd){
        const info = (WPEM_F?.calendarDays || {})[dateYmd] || null;
        const p1 = info?.p?.place1 ?? 0; // Großer
        const p2 = info?.p?.place2 ?? 0; // Kleiner
        const p3 = info?.p?.place3 ?? 0; // Foyer
        const sGross = p1 === 2 ? 'booked' : (p1 === 1 ? 'opt' : 'free');
        const sKlein = p2 === 2 ? 'booked' : (p2 === 1 ? 'opt' : 'free');
        const sFoyer = p3 === 2 ? 'booked' : (p3 === 1 ? 'opt' : 'free');
        setHallState(getEl(selGross), sGross, p1 === 0);
        setHallState(getEl(selKlein), sKlein, p2 === 0);
        setHallState(getEl(selFoyer), sFoyer, p3 === 0);
    }

    // Enddatum-Setup inkl. altInput-Handling
    function syncEndDate(fromYmd){
        const toInput = getEl(selTo);
        if (!toInput || !toInput._flatpickr) return;
        const fpTo = toInput._flatpickr;

        // minDate setzen + freischalten
        fpTo.set('minDate', fromYmd);
        fpTo.set('clickOpens', true);
        if (fpTo.altInput) {
            fpTo.altInput.removeAttribute('disabled');
            fpTo.altInput.classList.remove('wpem-altinput-disabled');
        }

        // Vorbelegen, immer !!! (nicht falls leer oder kleiner als from)
        //if (!toInput.value || toInput.value < fromYmd) {

        fpTo.setDate(fromYmd, true);
        //}
    }

    function attachValidation(){
        const fromInput = getEl(selFrom);
        const toInput   = getEl(selTo);
        if (!fromInput || !toInput) return;

        function showErr(msg){
            let box = document.querySelector('.wpem-date-error');
            if (!box) {
                box = document.createElement('div');
                box.className = 'wpem-date-error';
                box.style.color = 'var(--wpem-red)';
                box.style.marginTop = '6px';
                toInput.parentElement?.appendChild(box);
            }
            box.textContent = msg || '';
        }

        toInput.addEventListener('change', () => {
            if (fromInput.value && toInput.value && toInput.value < fromInput.value) {
                showErr('Enddatum darf nicht vor dem Startdatum liegen.');
            } else {
                const box = document.querySelector('.wpem-date-error');
                if (box) box.textContent = '';
            }
        });
    }

    function init(){
        const fromEl = getEl(selFrom);
        const toEl   = getEl(selTo);
        if (!fromEl || !toEl) return;

        disableAllHalls(); // Säle initial sperren

        const optsCommon = {
            locale: 'de',
            altInput: true,
            dateFormat: 'Y-m-d',   // bleibt MySQL-kompatibel im hidden/original Input
            altFormat: 'd.m.Y',    // sichtbare Anzeige
            allowInput: true,
            showMonths: 3,
            // ⬇︎ WICHTIG: Tage deaktivieren, wenn in der Vergangenheit oder "rot" (booked)
            disable: [
                function(date){
                    const k = ymd(date);
                    if (k < TODAY_YMD) return true; // Vergangenheit nicht klickbar
                    return false;
                    //const info = (WPEM_F?.calendarDays || {})[k];
                    //return !!(info && info.cats && info.cats.red); // rot (booked) nicht klickbar
                }
            ],


            onDayCreate: (dObj, dStr, fp, dayElem) => {
                const key = ymd(dayElem.dateObj);

                // Vergangenheit: nichts markieren, kein Tooltip
                if (typeof TODAY_YMD !== 'undefined' && key < TODAY_YMD) return;

                const info = (WPEM_F?.calendarDays || {})[key];
                //console.debug('Decorate day:', key, info);
                if (info) {
                    // vorhandene Belegungsinfos: Farben + data-places
                    if (info.cats?.red)    dayElem.classList.add('wpem-cal-red');
                    if (info.cats?.orange) dayElem.classList.add('wpem-cal-orange');
                    if (info.cats?.green)  dayElem.classList.add('wpem-cal-green');

                    const p = info.p || {place1:0,place2:0,place3:0};
                    const booked = info.booked || 0;

                    dayElem.dataset.places = `G:${p.place1};K:${p.place2};F:${p.place3}`;
                    dayElem.dataset.booked = booked ? '1' : '0';

                    // Tooltip (mehrzeilig) anhängen
                    attachTooltip(dayElem, buildPlacesHTML(p,booked));
                } else {
                    // Keine Einträge → zukünftiger Tag ist komplett frei
                    const p = { place1:0, place2:0, place3:0 };
                    dayElem.classList.add('wpem-cal-green');
                    dayElem.dataset.places = `G:0;K:0;F:0`;

                    // Tooltip auch für freie Tage
                    attachTooltip(dayElem, buildPlacesHTML(p,0));
                }
            },


        };

        // Startdatum
        const fpFrom = window.flatpickr(fromEl, Object.assign({}, optsCommon, {
            //onDayCreate: (dObj, dStr, fp, dayElem) => decorateDay(dayElem),
            onChange: (sel) => {
                const ymdFrom = Array.isArray(sel) && sel[0] ? ymd(sel[0]) : (fromEl.value || '');
                if (!ymdFrom) return;
                applyHallsForDate(ymdFrom);
                syncEndDate(ymdFrom);
            }
        }));

        // Disable-Regel: nur Vergangenheit (& rot (booked) sperren deaktiviert)
        const toDisable = {
            disable: [
                function(date){
                    const k = ymd(date);
                    if (typeof TODAY_YMD !== 'undefined' && k < TODAY_YMD) return true; // Vergangenheit
                    return false;
                    //const info = (WPEM_F?.calendarDays || {})[k];
                    //return !!(info && info.cats && info.cats.red); // rot = nicht auswählbar
                }
            ]
        };

        // ⚠️ NICHT das Original-Input deaktivieren!
        // Initial stattdessen Flatpickr schließen & altInput sperren:
        // todate: Farben & Tooltip JA, aber KEINE Hallen-Logik (kein onChange!)
        const fpTo = window.flatpickr(toEl, Object.assign({}, optsCommon, toDisable, {
            //onDayCreate: (dObj, dStr, fp, dayElem) => decorateDay(dayElem)
        }));

        fpTo.set('clickOpens', false);
        if (fpTo.altInput) {
            fpTo.altInput.setAttribute('disabled','disabled');
            // optional Styling:
            fpTo.altInput.classList.add('wpem-altinput-disabled');
        }
        // ▼ Responsive Monatsdarstellung
        applyResponsiveMonths([fpFrom, fpTo]);
        window.addEventListener('resize', debounce(()=> {
            applyResponsiveMonths([fpFrom, fpTo]);
        }, 150));

        attachValidation();
        //addDatepickerHelp(fromEl, 'Bitte wählen Sie das Startdatum. Rot = ausgebucht, Orange = teilweise belegt, Grün = frei.');
        //addDatepickerHelp(toEl,   'Bitte wählen Sie ein Enddatum. Nur Tage nach dem Startdatum sind möglich.');

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(jQuery);
