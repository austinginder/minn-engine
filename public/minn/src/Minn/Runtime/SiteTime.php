<?php

declare(strict_types=1);

namespace Minn\Runtime;

use DateTime;
use DateTimeZone;
use Minn\I18n\LocalDates;

/**
 * Time as the site keeps it: its timezone (the named one, else the UTC
 * offset), now in that zone or in UTC, a moment written in the site's
 * language, and a stored date read back in a format.
 */
final class SiteTime
{
    /** The site's timezone as text (wp_timezone_string): its name, else its offset as +HH:MM. */
    public static function zoneName(): string
    {
        $timezone = (string) Runtime::options()->filtered('timezone_string');
        if ($timezone !== '') {
            return $timezone;
        }
        $offset = (float) Runtime::options()->filtered('gmt_offset');
        $hours = (int) $offset;
        $minutes = $offset - $hours;
        return sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', abs($hours), abs($minutes * 60));
    }

    /** The site's timezone (wp_timezone). */
    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(self::zoneName());
    }

    /**
     * Now (current_time): a timestamp for "timestamp" or "U" (the site's
     * offset added unless in UTC), a database date for "mysql", else the
     * format given, in the site's zone or UTC.
     */
    public static function now(mixed $type, mixed $gmt = false): mixed
    {
        if ($type === 'timestamp' || $type === 'U') {
            return $gmt ? time() : time() + (int) ((float) Runtime::options()->filtered('gmt_offset') * 3600);
        }
        if ($type === 'mysql') {
            $type = 'Y-m-d H:i:s';
        }
        return (new DateTime('now', $gmt ? new DateTimeZone('UTC') : self::zone()))->format((string) $type);
    }

    /** A date in the site's zone written in UTC (get_gmt_from_date); the epoch for one that is not a date. */
    public static function toGmt(mixed $date, mixed $format = 'Y-m-d H:i:s'): string
    {
        $datetime = date_create((string) $date, self::zone());
        if ($datetime === false) {
            return gmdate((string) $format, 0);
        }
        return $datetime->setTimezone(new DateTimeZone('UTC'))->format((string) $format);
    }

    /** A date in UTC written in the site's zone (get_date_from_gmt); the epoch for one that is not a date. */
    public static function fromGmt(mixed $date, mixed $format = 'Y-m-d H:i:s'): string
    {
        $datetime = date_create((string) $date, new DateTimeZone('UTC'));
        if ($datetime === false) {
            return gmdate((string) $format, 0);
        }
        return $datetime->setTimezone(self::zone())->format((string) $format);
    }

    /** A moment written in a format, its names in the site's language (wp_date), as the filter leaves it. */
    public static function format(mixed $format, mixed $timestamp = null, mixed $timezone = null): mixed
    {
        $timestamp ??= time();
        $timezone ??= self::zone();
        $datetime = date_create('@' . $timestamp);
        $datetime->setTimezone($timezone);
        return Runtime::hooks()->filter('wp_date', [LocalDates::format($datetime, (string) $format), $format, $timestamp, $timezone]);
    }

    /**
     * A stored date in a format (mysql2date): false for none or one that is
     * not a date; "G" and "U" are timestamps (G with the site's offset); the
     * names translated unless asked not to be.
     */
    public static function fromStored(mixed $format, mixed $date, mixed $translate = true): mixed
    {
        if (empty($date)) {
            return false;
        }
        $datetime = date_create((string) $date, self::zone());
        if ($datetime === false) {
            return false;
        }
        if ($format === 'G') {
            return $datetime->getTimestamp() + $datetime->getOffset();
        }
        if ($format === 'U') {
            return $datetime->getTimestamp();
        }
        return $translate ? self::format($format, $datetime->getTimestamp()) : $datetime->format((string) $format);
    }
}
