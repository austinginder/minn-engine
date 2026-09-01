<?php

declare(strict_types=1);

namespace Minn\Support;

/** Human-scale spans: a number of seconds as the largest whole unit it fills, rounded, never below one. */
final class Time
{
    private const UNITS = [['second', 1], ['minute', 60], ['hour', 3600], ['day', 86400], ['week', 604800], ['month', 2592000], ['year', 31536000]];

    /**
     * A number of seconds as its largest whole unit and count.
     *
     * @return array{0: int, 1: string} count and unit name
     */
    public static function span(int $seconds): array
    {
        $seconds = abs($seconds);
        foreach (self::UNITS as $index => [$unit, $length]) {
            $next = self::UNITS[$index + 1][1] ?? PHP_INT_MAX;
            if ($seconds < $next) {
                return [max($index === 0 ? $seconds : (int) round($seconds / $length), 1), $unit];
            }
        }
        return [1, 'year'];
    }
}
