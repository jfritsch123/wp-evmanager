## Änderungen in WP Event Manager
### für commit Meldungen
ab 02.01.2026, 17:33
- Änderung Feld Organisation in form_schema: typ organization -> typ text
- Filter fromdate_max wieder aktivieren
ab 13.01.2026, 10:00
- Frontend Bug: nur 1 Event in daymap sichtbar obwohl zwei Anfragen vorliegen
- Editor Erweiterung: Beim Öffnen des todate-Pickers (onOpen) immer die aktuelle fromdate als minDate setzen.
Vaersion 1.2.1.6
- Backend CSS Verbesserungen: Editor - legend, label
- Verbessertes Errorhandling in saveditor.js
Version 1.2.1.7
- entfernt da nicht mehr verwendet: public static function register_settings()
- neu in Settings: Schalter Status „Anfrage erhalten“ beim Start aktivieren
- HelpRepository: Hilfe für alle user: if (!current_user_can('manage_options')) {//wp_send_json_error('No permission'); }
