import { bootFilterDatePickers} from "./util/initflatpicker.js";
import { enableToggleableHallRadios,initYearMonthFilters} from "./ui/filterhelper.js";
import { loadList } from './ui/renderlist.js';
import { setDefaultFilterState} from "./ui/filterpanel.js";

window.$ = window.jQuery;

/* ----------------------------------------------------------------------
   1) Globale JS-Fehler
   ---------------------------------------------------------------------- */
window.addEventListener('error', function (e) {
    console.group('%c[WPEM JS ERROR]', 'color:red;font-weight:bold');
    console.error('Message:', e.message);
    console.error('File:', e.filename);
    console.error('Line:', e.lineno, 'Column:', e.colno);
    console.error('Error:', e.error);
    console.groupEnd();

    alert('JavaScript-Fehler:\n' + e.message + e.filename + ':' + e.lineno);
});

/* ----------------------------------------------------------------------
   2) Promise / async Fehler (sehr wichtig!)
   ---------------------------------------------------------------------- */
window.addEventListener('unhandledrejection', function (e) {
    console.group('%c[WPEM PROMISE ERROR]', 'color:darkred;font-weight:bold');
    console.error('Reason:', e.reason);
    console.groupEnd();

    alert('Promise-Fehler – Details in der Konsole');
});

jQuery(function($){
    bootFilterDatePickers();
    initYearMonthFilters();
    enableToggleableHallRadios();
    setDefaultFilterState();
    loadList();
});
