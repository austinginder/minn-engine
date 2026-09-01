<?php

declare(strict_types=1);

use Minn\Admin\ActivityChart;

$now = strtotime('2026-09-01 15:00:00 UTC');

return [
    'a week is seven daily bars, oldest first, labelled site-local' => static function () use ($now) {
        $bars = ActivityChart::bucket([], 7, $now, 0);
        return count($bars) === 7 && $bars[0]['label'] === 'Aug 26' && $bars[6]['label'] === 'Sep 1'
            && $bars[6]['to'] === '2026-09-01 15:00:00' && $bars[6]['from'] === '2026-08-31 15:00:00'
            && $bars[5]['to'] === $bars[6]['from'];
    },
    'stamps fall into the bar whose (from, to] holds them' => static function () use ($now) {
        $bars = ActivityChart::bucket(['2026-09-01 14:59:59', '2026-08-31 15:00:01', '2026-08-31 14:00:00', '2026-08-01 00:00:00'], 7, $now, 0);
        return array_column($bars, 'value') === [0, 0, 0, 0, 0, 1, 2];
    },
    'over 45 days the bars are weeks' => static function () use ($now) {
        $bars = ActivityChart::bucket(['2026-09-01 12:00:00'], 90, $now, 0);
        return count($bars) === 13 && str_starts_with($bars[12]['label'], 'Week of ') && $bars[12]['value'] === 1
            && $bars[12]['from'] === '2026-08-25 15:00:00';
    },
    'the site offset moves the label, not the bounds' => static function () use ($now) {
        $bars = ActivityChart::bucket([], 1, $now, 10 * 3600);
        return $bars[0]['label'] === 'Sep 2' && $bars[0]['to'] === '2026-09-01 15:00:00';
    },
];
