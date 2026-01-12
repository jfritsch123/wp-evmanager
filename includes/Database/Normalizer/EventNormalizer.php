<?php
namespace WP_EvManager\Database\Normalizer;

defined('ABSPATH') || exit;

final class EventNormalizer
{
    /**
     * Normalisiert Event-Daten für INSERT/UPDATE
     */
    public static function normalize(array $data, string $context = 'insert'): array
    {
        // 🔢 Integer-Felder
        $data = self::normalizeInts($data, [
            'persons',
            'booked',
        ]);

        // 📅 DATE-Felder (ohne Uhrzeit)
        $data = self::normalizeDates($data, [
            'fromdate',
            'todate',
            'processed',
            'informed',
        ]);

        // ⏱ DATETIME-Felder (falls du welche hast)
        $data = self::normalizeDateTimes($data, [
            // 'processed', // nur falls DATETIME im Schema
        ]);

        // 🧵 Pflicht-Strings (NOT NULL)
        $data = self::normalizeStrings($data, [
            'tel',
            'note',
            'editor',
        ]);

        // 🟢 Flags (0 / 1)
        $data = self::normalizeBooleans($data, [
            'publish',
            'place1',
            'place2',
            'place3',
        ]);

        // 🧠 Kontextabhängige Defaults
        if ($context === 'insert') {
            $data = self::applyInsertDefaults($data);
        }

        return $data;
    }

    /* =========================
       Einzelne Normalisierer
       ========================= */

    private static function normalizeInts(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = ($data[$f] === '' || $data[$f] === null)
                    ? 0
                    : (int) $data[$f];
            }
        }
        return $data;
    }

    private static function normalizeDates(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }

            if ($data[$f] === '' || $data[$f] === null) {
                // WICHTIG: NULL statt ''
                $data[$f] = null;
                continue;
            }

            // akzeptiere YYYY-MM-DD oder YYYY-MM-DD HH:MM:SS
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data[$f])) {
                $data[$f] = substr($data[$f], 0, 10);
            } else {
                // ungültig → NULL
                $data[$f] = null;
            }
        }
        return $data;
    }

    private static function normalizeDateTimes(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }

            if ($data[$f] === '' || $data[$f] === null) {
                $data[$f] = null;
                continue;
            }

            // YYYY-MM-DD HH:MM:SS
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data[$f])) {
                $data[$f] = null;
            }
        }
        return $data;
    }

    private static function normalizeStrings(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data) || $data[$f] === null) {
                $data[$f] = '';
            } else {
                $data[$f] = (string) $data[$f];
            }
        }
        return $data;
    }

    private static function normalizeBooleans(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = ($data[$f] === '1' || $data[$f] === 1 || $data[$f] === true) ? '1' : '0';
            }
        }
        return $data;
    }

    private static function applyInsertDefaults(array $data): array
    {
        // processed nur setzen, wenn leer
        if (empty($data['processed'])) {
            $data['processed'] = current_time('mysql');
        }

        // editor absichern
        if (empty($data['editor']) && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->exists()) {
                $data['editor'] = $user->user_login;
            }
        }

        return $data;
    }
}

