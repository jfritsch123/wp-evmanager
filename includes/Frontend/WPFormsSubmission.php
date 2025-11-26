<?php
namespace WP_EvManager\Frontend;

use WP_EvManager\Database\Repositories\EventRepository;

final class WPFormsSubmission
{
    private int $form_id;
    /** @var array<int,string|array> */
    private array $map;

    public function __construct(int $form_id, array $map)
    {
        $this->form_id = $form_id;
        $this->map     = $map;
    }

    /**
     * @param array    $fields    Verarbeitete Felder (by WPForms)
     * @param array    $entry     Entry-Metadaten
     * @param array    $form_data Formular-Definition (inkl. Felder/Choices)
     * @param int      $entry_id  WPForms Entry ID
     */
    public function handle($fields, $entry, $form_data, $entry_id): void
    {
        $formId = (int)($form_data['id'] ?? 0);
        if ($formId !== $this->form_id) {
            return; // anderes Formular
        }

        // Rohdaten aus WPForms holen (immer im MySQL-Format wenn unsere Picker genutzt werden)
        $get = function (int $fid): ?string {
            if (!isset($GLOBALS['_POST']['wpforms']['fields'])) {
                // Fallback auf $fields (z. B. bei unit tests)
                return isset($fields[$fid]['value']) ? (string)$fields[$fid]['value'] : null;
            }
            $src = $_POST['wpforms']['fields'] ?? []; // phpcs:ignore
            if (!array_key_exists($fid, $src)) {
                return null;
            }
            $val = $src[$fid];

            // WPForms "date-time" oder "time" Felder liefern ein Array
            if (is_array($val)) {
                if (isset($val['time'])) {
                    $val = $val['time']; // z.B. "10:00"
                } elseif (isset($val['date'])) {
                    $val = $val['date']; // fallback falls gebraucht
                } else {
                    return null;
                }
            }

            $val = wp_unslash((string)$val);
            return $val === '' ? null : $val;
        };

        // Checkboxen (Säle) speziell: Feld-ID 24 → Array ausgewählter Werte
        $rawFields = $_POST['wpforms']['fields'] ?? []; // phpcs:ignore
        $selected  = (isset($rawFields[WPFORMS_HALLS_ID]) && is_array($rawFields[WPFORMS_HALLS_ID])) ? array_map('wp_unslash', $rawFields[WPFORMS_HALLS_ID]) : [];

        // Wir erkennen place1/2/3 anhand der Labels im Formular
        $choices = [];
        $placesStr = '';
        if (!empty($form_data['fields'][WPFORMS_HALLS_ID]['choices']) && is_array($form_data['fields'][WPFORMS_HALLS_ID]['choices'])) {
            foreach ($form_data['fields'][WPFORMS_HALLS_ID]['choices'] as $ch) {
                // WPForms-Choice-Struktur: ['label'=>'Großer Saal','value'=>'...']
                $label = (string)($ch['label'] ?? '');
                $value = (string)($ch['value'] ?? $label);
                $choices[] = ['label' => $label, 'value' => $value];
            }
            $v = $fields[WPFORMS_HALLS_ID]['value_raw'] ?? $fields[WPFORMS_HALLS_ID]['value'] ?? '';
            if (is_array($v)) {
                $placesStr = implode("\n", array_filter(array_map('trim', $v)));
            } else {
                $placesStr = trim((string) $v);
            }

        }

        // ACHTUNG: bei Anfrage werden die gewählten Räumlichkeiten im Feld "places" als Text gespeichert.
        // Die Einzel-Felder place1, place2, place3 werden null gesetzt, da nur Anfrage
        // place1 = Großer Saal, place2 = Kleiner Saal, place3 = Foyer werden auf null gesetzt
        $place1 = null;
        $place2 = null;
        $place3 = null;

        // Datensatz für die DB aufbauen (nur die Felder aus deinem Mapping)
        $data = [
            // Dates/Times
            'fromdate'  => $this->sanitize_date($get( (int)$this->map['fromdate'] )), // ID 30
            'fromtime'  => $this->sanitize_time($get( (int)$this->map['fromtime'] )), // ID 7
            'todate'    => $this->sanitize_date($get( (int)$this->map['todate']   )), // ID 31
            'totime'    => $this->sanitize_time($get( (int)$this->map['totime']   )), // ID 8
            // Textfelder
            'descr3'    => $this->sanitize_text($get( (int)$this->map['descr3']  )),   // ID 19
            'organizer' => $this->sanitize_text($get( (int)$this->map['organizer'] )), // ID 15
            'persons'   => $this->sanitize_int($get( (int)$this->map['persons']   )),  // ID 13
            'email'     => $this->sanitize_email($get( (int)$this->map['email']   )),  // ID 17
            'tel'       => $this->sanitize_text($get( (int)$this->map['tel']     )),   // ID 16
            'type'      => $this->sanitize_text($get( (int)$this->map['type']    )),   // ID 11
            // Places aus Checkboxen
            'place1'    => $place1,
            'place2'    => $place2,
            'place3'    => $place3,
            'places'    => $placesStr, // Textfeld mit allen ausgewählten Sälen
            // Meta/Defaults
            'title'     => 'Buchungsanfrage',                   // optional leer
            'short'     => '',                   // optional leer
            'publish'   => '0',                  // Nichts
            'booked'    => 0,                    // Anfrage → nicht gebucht
            'status'    => 'Anfrage erhalten',   // sinnvoller default
            'editor'    => '',                   // kein WP-User im Frontend
            'wpforms_entry_id' => $entry_id,
            'ip'=> $this->client_ip(),
        ];

        // Insert
        $repo  = new EventRepository();
        $newId = $repo->insert_from_submission($data);
        //\WP_EvManager\Database\DBTools::log_new_request($newId,json_encode($data),'new');

     }

    private function sanitize_date(?string $v): ?string {
        if (!$v) return null;
        // erwartetes Format Y-m-d (durch deinen Flatpickr)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        // fallback: versuchen zu parsen
        try { return (new \DateTimeImmutable($v))->format('Y-m-d'); } catch (\Throwable $e) { return null; }
    }

    private function sanitize_time($val): ?string {
        $val = trim((string)$val);
        if ($val === '') return null;

        // gültiges HH:MM prüfen
        if (preg_match('/^\d{1,2}:\d{2}$/', $val)) {
            return $val;
        }
        return null;
    }

    private function sanitize_text(?string $v): string {
        return $v === null ? '' : sanitize_text_field($v);
    }

    private function sanitize_int(?string $v): int {
        return $v !== null ? (int)$v : 0;
    }

    private function sanitize_email(?string $v): string {
        $v = $v === null ? '' : sanitize_email($v);
        return $v ?: '';
    }

    /**
     * Holt mehrzeilige Inhalte sicher aus $fields (WPForms gibt dort oft hübscher formatiert zurück)
     */
    private function sanitize_textarea_from_fields(array $fields, int $fid): string {
        if (isset($fields[$fid]['value'])) {
            $val = (string)$fields[$fid]['value'];
            $val = wp_kses_post($val);
            return trim($val);
        }
        return '';
    }

    private function client_ip(): string {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return sanitize_text_field((string)$_SERVER[$k]);
        }
        return '';
    }
}
