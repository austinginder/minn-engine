<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The locale's calendar and number vocabulary as data: the names a site
 * prints for weekdays, months and meridiems, and how it separates
 * thousands and decimals.
 *
 * The names are English here because the engine carries no core
 * translations yet; the facade runs each one through the translation
 * filters on the way out, so a catalogue can answer for them later
 * without this class changing.
 */
final class Locale
{
    public const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];
    public const MERIDIEM = ['am' => 'am', 'pm' => 'pm', 'AM' => 'AM', 'PM' => 'PM'];
    public const NUMBER_FORMAT = ['thousands_sep' => ',', 'decimal_point' => '.'];
    public const LIST_ITEM_SEPARATOR = ', ';
    public const WORD_COUNT_TYPE = 'words';
    public const TEXT_DIRECTION = 'ltr';

    /** A weekday's one-letter form. */
    public static function weekdayInitial(string $day): string
    {
        return substr($day, 0, 1);
    }

    /** A weekday's or month's three-letter form; May is already three letters. */
    public static function abbreviation(string $name): string
    {
        return substr($name, 0, 3);
    }

    /** Months are keyed by their zero-padded number, both ways round. */
    public static function monthKey(int|string $month): string
    {
        return str_pad((string) (int) $month, 2, '0', STR_PAD_LEFT);
    }
}
