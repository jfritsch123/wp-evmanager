# WP EvManager – Testprotokoll

## Plugin-Information
- Name: WP EvManager
- Version: 1.2.1.4
- Repository: wp-evmanager
- Status: Beta

## Testumgebung
- WordPress: 6.9
- PHP: 8.5.1 / 8.2
- Datenbank: MySQL / mysqli 8.0.37
- Browser:
  - Chrome (aktuell)
  - Firefox (aktuell)
- Umgebung:
  - Staging (https://jfritsch.at/mt)

---

## Benutzer & Rollen

Getestet mit folgenden Rollen:

- [x] Administrator
- [x] Ev Manager (alle Events)
- [x] Ev Manager (eigene Events)
- [ ] Redakteur (nur Leserechte)
- [ ] Abonnent (kein Zugriff)

---
## Frontend – Anmeldung
### Anmeldeformular ausfüllen, abschicken
- [x] Bestätigungs-E-Mail Admin erhalten
- [x] Bestätigungs-E-Mail Kunde erhalten
- [x] "Zuletzt bearbeitet am": 0000-00-00 00:00:00
- [x] "Bearbeitet von": leer
- [x] Saalbelegung: Zeile "Angefragt"
- [x] Original-Anfrage in Popup
- [x] Anfrage in Änderungs-History
- [x] Kalender Daymap aktualisiert (Frontend, Backend)
  - [x] ⚠️ **Bug**: nur 1 Event in daymap sichtbar obwohl zwei Anfragen vorliegen
    - ✅ gelöst, Bug in EventRepository->getDayMapSince behoben (falsches Trennzeichen)
    - 🌐 [Refactoring Joe FE 02](http://localhost/trm/projectwork/project-snippet.php?id=48)
- [ ]  📌  **TODO**: E-mail formatieren (HTML)
- [x] ⚠️ **Bug**: Liste Anfrage erhalten: nach TS sortieren
  - ✅ gelöst, Bug in renderlist.js bzw. filterpanel.js behoben
  - 🌐 [Refactoring Joe 27 Bug in renderlist.js bzw. filterpanel.js](http://localhost/trm/projectwork/project-snippet.php?id=59)
- [ ]  📌  **TODO**: EV Manager Organization kann keine Anmeldungen bearbeiten<br>

## Backend – Event Editor
### Filtermöglichkeiten
- [x ] Filter nach Suche
- [x ] Filter nach Jahr/Monat
- [x ] Filter nach Status
- [x] Filter nach Saalbelegung
- [x] Filter nach Ausgebucht
- [x] Filter nach Papierkorb anzeigen
  - [x] ⚠️ **Bug**: Suche Buttons haben nach Aktivierung keinen Text mehr
    - ✅  gelöst: applyTrashMode
    - 🌐 [Refactoring Joe 28](http://localhost/trm/projectwork/project-snippet.php?id=60)
- [x] Alle Filter zurücksetzen
### Anlegen & Bearbeiten
- [x] Neuanlage Event
- [x] Pflichtfelder werden validiert
  - Plichtfelder: Öffentlicher Titel (Veranstaltungskalender), Startdatum, Name des Veranstalters
- [x] Event speichern
  - [x] ⚠️ **Bug**: Zuletzt bearbeitet am wird nicht aktualisiert
  - [x] ⚠️ **Bug**: Anzahl Personen wird als int gespeichert
    - ✅ gelöst
    - 🌐 [Refactoring Joe 29](http://localhost/trm/projectwork/project-snippet.php?id=60)
- [x] alle Felder korrekt gespeichert

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
- Name: (Joe Fritsch)
- Datum: 2026-01
