# WP EvManager – Testprotokoll

## Plugin-Information
- Name: WP EvManager
- Version: 0.9.x
- Repository: wp-evmanager
- Status: Beta

## Testumgebung
- WordPress: 6.x
- PHP: 8.1 / 8.2
- Datenbank: MySQL / MariaDB
- Browser:
  - Chrome (aktuell)
  - Firefox (aktuell)
- Umgebung:
  - Lokal (LocalWP / XAMPP / Docker)
  - Staging (optional)

---

## Benutzer & Rollen

Getestet mit folgenden Rollen:

- [x] Administrator
- [x] Ev Manager (alle Events)
- [x] Ev Manager (eigene Events)
- [ ] Redakteur (nur Leserechte)
- [ ] Abonnent (kein Zugriff)

---

## Backend – Event Editor

### Anlegen & Bearbeiten
- [x] Event anlegen
- [x] Pflichtfelder werden validiert
- [x] Event speichern
- [x] Editor neu laden nach Save
- [x] processed-Datum wird gesetzt
- [x] Editor-Feld korrekt gesetzt

### Status & Schreibschutz
- [x] Schreibschutz greift abhängig vom Status
- [x] Schreibschutz per Checkbox temporär aufhebbar
- [x] Schreibschutz-Einstellungen aus Backend wirksam

---

## Papierkorb

### In den Papierkorb
- [x] Button „In den Papierkorb“ sichtbar
- [x] Event wird nicht gelöscht
- [x] DB-Feld `trash = 1` gesetzt
- [x] Event verschwindet aus normaler Liste
- [x] History-Eintrag wird erzeugt

### Papierkorb-Filter
- [x] Filter „Papierkorb anzeigen“ vorhanden
- [x] Aktiv → alle anderen Filter deaktiviert
- [x] Inaktiv → alle Filter wieder aktiv
- [x] Papierkorb-Liste zeigt nur `trash = 1`

### Wiederherstellen
- [x] Restore-Button sichtbar
- [x] Restore bestätigt per Dialog
- [x] Event wird wieder sichtbar
- [x] Event erscheint **an erster Stelle**
- [x] Papierkorb-Modus wird automatisch verlassen
- [x] Kalender-Daymap wird aktualisiert
- [x] History-Eintrag wird erzeugt

---

## Duplicate Event

- [x] Button „Event duplizieren“ sichtbar
- [x] Duplikat wird angelegt
- [x] Titel wird angepasst (Kopie)
- [x] Duplikat erscheint direkt unter Original
- [x] Neues `processed`-Datum
- [x] Neuer `editor`
- [x] History-Eintrag „dupliziert von #ID“

---

## History

- [x] Create-History bei Neuanlage
- [x] Update-History nur bei echten Änderungen
- [x] Papierkorb-History
- [x] Restore-History
- [x] Duplicate-History
- [x] Anzeige im Modal (AJAX)
- [x] Tabelle korrekt formatiert

---

## Kalender / Flatpickr

- [x] Daymap korrekt geladen
- [x] Farben (grün/orange/rot) korrekt
- [x] Sperrung roter Tage
- [x] Update nach Save
- [x] Update nach Trash
- [x] Update nach Restore
- [x] Anfragen (places) korrekt dargestellt

---

## Frontend (Shortcode)

- [x] Shortcode zeigt Events ab heutigem Datum
- [x] Gruppierung nach Monaten
- [x] publish = 0 → Event verborgen
- [x] publish = 1 → Titel sichtbar
- [x] publish = 2 → Info-Icon sichtbar
- [x] publish = 3 → Löwen-Icon sichtbar
- [x] Button „Weitere Veranstaltungen“
- [x] AJAX-Nachladen funktioniert
- [x] Monatsüberschrift nicht doppelt

---

## Sicherheit & Stabilität

- [x] Nonces bei allen AJAX-Aktionen
- [x] Capabilities geprüft
- [x] Keine PHP Notices/Warnings
- [x] Keine JS Errors in Konsole
- [x] WP_DEBUG_LOG leer nach Tests

---

## Bekannte Einschränkungen
- Keine automatisierten PHPUnit-Tests
- Endgültiges Löschen optional

---

## Tester
- Name: (dein Name)
- Datum: YYYY-MM-DD
