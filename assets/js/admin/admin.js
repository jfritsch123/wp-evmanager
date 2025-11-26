import { bootFilterDatePickers} from "./util/initflatpicker.js";
import { enableToggleableHallRadios,initYearMonthFilters} from "./ui/filterhelper.js";
import { loadList } from './ui/renderlist.js';
import { setDefaultFilterState} from "./ui/filterpanel.js";

window.$ = window.jQuery;

jQuery(function($){
    bootFilterDatePickers();
    initYearMonthFilters();
    enableToggleableHallRadios();
    setDefaultFilterState();
    loadList();
});
