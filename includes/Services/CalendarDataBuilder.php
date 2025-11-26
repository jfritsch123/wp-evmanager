<?php
namespace WP_EvManager\Services;

defined('ABSPATH') || exit;

use WP_EvManager\Database\Repositories\EventRepository;

final class CalendarDataBuilder
{
    /**
     * Baut eine Map je Tag: Y-m-d => Aggregat (cats/places/status/booked)
     * cats: ['red'=>bool,'orange'=>bool,'green'=>bool]
     * p:    ['place1'=>0..2,'place2'=>0..2,'place3'=>0..2]  (max pro Tag)
     * status: string[] unique
     * booked: bool (mind. 1 Event booked=1)
     */
    public static function build_day_map(EventRepository $repo): array
    {
        $rows = $repo->get_all_for_calendar(); // siehe unten
        $map  = [];

        $normDate = static fn($v) => ($v && $v !== '0000-00-00') ? $v : null;
        $normPlace = static function($v): string {
            $v = trim((string)$v);
            return \in_array($v, ['0','1','2'], true) ? $v : '0';
        };

        foreach ($rows as $r) {
            $fromRaw = $normDate($r['fromdate'] ?? null);
            $toRaw   = $normDate($r['todate'] ?? null);

            // Effektiver Start: fromdate, sonst todate (Einzeltag)
            $fromEff = $fromRaw ?: $toRaw;
            if (!$fromEff) { continue; }

            $toEff = ($toRaw && $toRaw >= $fromEff) ? $toRaw : null;

            $p1 = $normPlace($r['place1'] ?? '');
            $p2 = $normPlace($r['place2'] ?? '');
            $p3 = $normPlace($r['place3'] ?? '');
            $statusStr = trim((string)($r['status'] ?? ''));
            $statusArr = $statusStr === '' ? [] : array_values(array_unique(array_map('trim', explode(',', $statusStr))));
            $booked = (int)($r['booked'] ?? 0);

            // Kategorie nach deinen Regeln:
            // rot: booked=1; orange: mind. place!=0 ODER status gesetzt; grün: sonst
            $cat = ($booked === 1) ? 'red'
                : (($p1!=='0' || $p2!=='0' || $p3!=='0' || !empty($statusArr)) ? 'orange' : 'green');

            // Tage expandieren (inkl. toEff)
            $start = new \DateTimeImmutable($fromEff);
            $end   = $toEff ? new \DateTimeImmutable($toEff) : $start;

            for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
                $k = $d->format('Y-m-d');
                if (!isset($map[$k])) {
                    $map[$k] = [
                        'cats'   => ['red'=>false,'orange'=>false,'green'=>false],
                        'p'      => ['place1'=>0,'place2'=>0,'place3'=>0],
                        'status' => [],
                        'booked' => false,
                    ];
                }
                $map[$k]['cats'][$cat] = true;
                // max pro Ort
                $map[$k]['p']['place1'] = max($map[$k]['p']['place1'], (int)$p1);
                $map[$k]['p']['place2'] = max($map[$k]['p']['place2'], (int)$p2);
                $map[$k]['p']['place3'] = max($map[$k]['p']['place3'], (int)$p3);
                if ($statusArr) {
                    $map[$k]['status'] = array_values(array_unique(array_merge($map[$k]['status'], $statusArr)));
                }
                if ($booked === 1) {
                    $map[$k]['booked'] = true;
                }
            }
        }

        return $map;
    }
}

