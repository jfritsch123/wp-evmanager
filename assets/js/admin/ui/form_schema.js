// --- Formular-Schema (Gruppen/Spalten/Reihenfolge) ---
export const FORM_SCHEMA = [
    {
        id: 'title',
        title: 'Veranstaltung',
        columns: 1,
        fields: [
            { id: 'title', type: 'text', label: 'Titel', required: true },
            { id: 'type', type: 'text', label: 'Veranstaltungsart' },
            { id: 'descr3', type: 'textarea', label: 'Anfragetext' },
        ],
    },
    {
        id: 'times',
        title: 'Reservierung von bis',
        columns: 2,
        fields: [
            { id: 'fromdate', type: 'text',  label: 'Startdatum', required: true },
            { id: 'fromtime', type: 'text',  label: 'Startzeit' },
            { id: 'todate',   type: 'text',  label: 'Enddatum' },
            { id: 'totime',   type: 'text',  label: 'Endzeit' },
        ],
    },
    {
        id: 'editing',
        title: 'Bearbeitung',
        columns: 2,
        fields: [
            { id: 'status', type: 'statusGroup', label: 'Status',col:1 },
            { id: 'editor', type: 'textReadonly',label: 'Bearbeitet von',col:2 },
            { id: 'processed', type: 'textReadonly',label: 'Zuletzt bearbeitet am',col:2 },
            { id: 'wpforms_entry_id', type: 'request',   label: '' }, // NEU in DB
        ],
    },
    {
        id: 'saveall',
        title: '',
        columns: 1,
        fields: [
            { id: 'save', type: 'saveButton', label: ''},
        ],
    },

    {
        id: 'organizer',
        title: 'Veranstalter',
        columns: 2,
        fields: [
            { id: 'organizer',    type: 'text',  label: 'Name des Veranstalters' },
            { id: 'organization', type: 'organization',  label: 'Organisation' },
        ],
    },
    {
        id: 'contact',
        title: 'Kontakt',
        columns: 2,
        fields: [
            { id: 'email',        type: 'text',  label: 'E‑Mail' },
            { id: 'tel',          type: 'text',  label: 'Telefon' },
        ],
    },
    {
        id: 'places',
        title: 'Saalbelegung',
        columns: 2,
        fields: [
            { id: 'placeMatrix', type: 'placeMatrix', label: 'Saalreservierung' }, // place1/2/3 zusammen
            { id: 'dayEvents', type: 'dayEvents', label: 'Weitere Belegungen' }
        ],
    },
    {
        id: 'booked',
        title: 'Saalauslastung',
        columns: 2,
        fields: [
            { id: 'booked',      type: 'yesno',       label: 'Ausgebucht (keine Buchungsanfrage möglich)' },
            { id: 'persons',     type: 'number',      label: 'Anzahl Personen' },

        ],
    },

    /*
    {
        id: 'persons',
        title: 'Personen',
        columns: 2,
        fields: [
            { id: 'persons',     type: 'number',      label: 'Anzahl Personen' },
        ],
    },
    */

    {
        id: 'publish',
        title: 'Publizieren',
        columns: 2,
        fields: [
            { id: 'publish',     type: 'publish',     label: 'Publizieren' },
        ],
    },
    {
        id: 'info',
        title: 'Infos zur Veranstaltung',
        columns: 1,
        fields: [
            { id: 'descr1', type: 'wysiwyg', label: 'Beschreibung' },
            { id: 'descr2', type: 'wysiwyg', label: 'Kartenvorverkauf' },
        ],
    },
    {
        id: 'saveall',
        title: '',
        columns: 1,
        fields: [
            { id: 'save', type: 'saveButton', label: ''},
        ],
    },

    {
        id: 'picture',
        title: 'Mediathek',
        columns: 1,
        fields: [
            { id: 'picture', type: 'picture', label: 'Bild' },
        ],
    },
    {
        id: 'admin',
        title: 'Verwaltung',
        columns: 1,
        fields: [
            { id: 'note',    type: 'wysiwyg', label: 'Interne Notizen' },
        ],
    },
    {
        id: 'history',
        title: 'Zusatzinformationen',
        columns: 1,
        fields: [
            { id: 'history', type: 'history', label: '' },
        ],
    },

];

// WYSIWYG-Felder dynamisch aus Schema ableiten:
export const WYSIWYG_FIELDS = FORM_SCHEMA
    .flatMap(g => g.fields)
    .filter(f => f.type === 'wysiwyg')
    .map(f => f.id);

