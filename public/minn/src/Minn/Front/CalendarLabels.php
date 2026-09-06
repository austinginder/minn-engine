<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;

/**
 * The words a calendar prints: weekday names Sunday first, the short form
 * each column shows (an initial or an abbreviation), month names by number,
 * and the abbreviation the month navigation uses.
 */
final readonly class CalendarLabels
{
    /**
     * @param array<int, string> $weekdays Sunday first
     * @param array<string, string> $weekdayShort full weekday name to the column heading
     * @param array<int, string> $months 1 to 12
     * @param array<string, string> $monthAbbrev full month name to its abbreviation
     */
    public function __construct(
        public array $weekdays,
        public array $weekdayShort,
        public array $months,
        public array $monthAbbrev,
    ) {
    }

    /** The English tables, with weekday initials as the reference shows by default. */
    public static function english(): self
    {
        return self::fromEnglish(static fn (string $day): string => substr($day, 0, 1));
    }

    /** The English tables with three-letter weekday abbreviations. */
    public static function englishAbbreviated(): self
    {
        return self::fromEnglish(static fn (string $day): string => substr($day, 0, 3));
    }

    /** @param Closure(string): string $short the column heading for a weekday name */
    private static function fromEnglish(Closure $short): self
    {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $headings = [];
        foreach ($weekdays as $day) {
            $headings[$day] = $short($day);
        }
        $abbrev = [];
        foreach ($months as $month) {
            $abbrev[$month] = substr($month, 0, 3);
        }
        return new self($weekdays, $headings, array_combine(range(1, 12), $months), $abbrev);
    }
}
