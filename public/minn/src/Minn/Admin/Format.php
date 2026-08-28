<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Texturize;

/** The dashboard's number, size, age, and title formatting. */
final class Format
{
    /** number_format_i18n for the default locale: comma thousands. */
    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals);
    }

    /** size_format with one decimal: 1024-step units, comma thousands. */
    public static function size(int $bytes): string
    {
        foreach (['TB' => 2 ** 40, 'GB' => 2 ** 30, 'MB' => 2 ** 20, 'KB' => 2 ** 10] as $unit => $magnitude) {
            if ($bytes >= $magnitude) {
                return self::number($bytes / $magnitude, 1) . ' ' . $unit;
            }
        }
        return self::number($bytes, 1) . ' B';
    }

    /**
     * Humanized age, pinned by an oracle capture table: each unit rounds,
     * floors at 1, and hands off at the next unit boundary.
     */
    public static function humanTimeDiff(int $from, ?int $to = null): string
    {
        $diff = max(($to ?? time()) - $from, 1);
        $units = [
            [60, 1, 'second', 'seconds'],
            [3600, 60, 'minute', 'minutes'],
            [86400, 3600, 'hour', 'hours'],
            [604800, 86400, 'day', 'days'],
            [30 * 86400, 604800, 'week', 'weeks'],
            [365 * 86400, 30 * 86400, 'month', 'months'],
            [PHP_INT_MAX, 365 * 86400, 'year', 'years'],
        ];
        foreach ($units as [$limit, $divisor, $one, $many]) {
            if ($diff < $limit) {
                $n = $limit === 60 ? $diff : max(1, (int) round($diff / $divisor));
                return $n . ' ' . ($n === 1 ? $one : $many);
            }
        }
        return '';
    }

    /** A post title as the feed shows it: texturized, entities decoded, tags gone. */
    public static function plainTitle(string $raw): string
    {
        $title = html_entity_decode(strip_tags(Texturize::html($raw)), ENT_QUOTES, 'UTF-8');
        return trim($title) === '' ? '(no title)' : $title;
    }
}
