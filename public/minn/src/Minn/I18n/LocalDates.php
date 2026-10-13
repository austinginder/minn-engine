<?php

declare(strict_types=1);

namespace Minn\I18n;

use DateTimeInterface;
use Minn\Support\Locale;

/**
 * A date in the site's language, as the reference's wp_date writes one:
 * PHP's format, with the weekday and month names (D, l, F, M) and the
 * meridiems (a, A) in the language's own words, and a month written after
 * its day in the genitive where the language declines month names.
 * Backslashed characters stay as written.
 */
final class LocalDates
{
    /** The date in the format given, its names translated. */
    public static function format(DateTimeInterface $time, string $format): string
    {
        $month = Locale::MONTHS[(int) $time->format('n') - 1];
        $day = Locale::WEEKDAYS[(int) $time->format('w')];
        $declined = Gettext::inContext('off', 'decline months names: on or off') === 'on';
        $names = [
            'D' => Gettext::text(Locale::abbreviation($day)),
            'l' => Gettext::text($day),
            'F' => Gettext::text($month),
            'M' => Gettext::inContext(Locale::abbreviation($month), $month . ' abbreviation'),
            'a' => Gettext::text($time->format('a')),
            'A' => Gettext::text($time->format('A')),
        ];
        $out = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '\\') {
                $out .= $char . ($format[++$i] ?? '');
                continue;
            }
            if (!isset($names[$char])) {
                $out .= $char;
                continue;
            }
            // A month after its day number reads in the genitive where the language declines months.
            $name = $char === 'F' && $declined && preg_match('/[dj][^A-Za-z]*$/', $out) === 1 ? Gettext::inContext($month, 'genitive') : $names[$char];
            $out .= (string) preg_replace('/([A-Za-z])/', '\\\\$1', $name);
        }
        return $time->format($out);
    }
}
