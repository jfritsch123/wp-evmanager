<?php
namespace WP_EvManager\Common;

defined('ABSPATH') || exit;

final class FieldLabels
{
    /**
     * Zentrales Feld-Mapping
     * short  → Tabellen / kompakte Anzeigen
     * long   → History, Formulare, Tooltips
     */
    private static array $labels = [
        'title' => [
            'short' => 'Titel',
            'long'  => 'Öffentlicher Titel (Veranstaltungskalender)',
        ],
        'type' => [
            'short' => 'Intern',
            'long'  => 'Interner Titel',
        ],
        'fromdate' => [
            'short' => 'Start',
            'long'  => 'Startdatum',
        ],
        'fromtime' => [
            'short' => 'Zeit',
            'long'  => 'Startzeit',
        ],
        'todate' => [
            'short' => 'Ende',
            'long'  => 'Enddatum',
        ],
        'totime' => [
            'short' => 'Zeit',
            'long'  => 'Endzeit',
        ],
        'place1' => [
            'short' => 'Gr',
            'long'  => 'Großer Saal',
        ],
        'place2' => [
            'short' => 'Kl',
            'long'  => 'Kleiner Saal',
        ],
        'place3' => [
            'short' => 'Fo',
            'long'  => 'Foyer',
        ],
        'booked' => [
            'short' => 'Ausg.',
            'long'  => 'Ausgebucht',
        ],
        'status' => [
            'short' => 'Status',
            'long'  => 'Status',
        ],
        'publish' => [
            'short' => 'Publ.',
            'long'  => 'Publizierungsstatus',
        ],
        'note' => [
            'short' => 'Notiz',
            'long'  => 'Interne Notizen',
        ],
        'processed' => [
            'short' => 'Bearb.',
            'long'  => 'Zuletzt bearbeitet am',
        ],
        'editor' => [
            'short' => 'Bearb.',
            'long'  => 'Bearbeitet von',
        ],
    ];

    /**
     * Liefert das Label für ein Feld
     */
    public static function label(string $field, string $mode = 'long'): string
    {
        if (!isset(self::$labels[$field])) {
            return $field; // Fallback: technischer Name
        }

        return self::$labels[$field][$mode]
            ?? self::$labels[$field]['long']
            ?? $field;
    }
}

