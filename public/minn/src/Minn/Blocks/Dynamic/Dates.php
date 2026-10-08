<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Content\Site;

/** Site-local dates the way the dynamic blocks print them. */
final class Dates
{
    /** ISO 8601 with the site's offset, from a site-local MySQL datetime. */
    public static function iso(Site $site, string $local): string
    {
        $offset = $site->gmtOffset();
        $sign = $offset < 0 ? '-' : '+';
        $zone = sprintf('%s%02d:%02d', $sign, intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60));
        return str_replace(' ', 'T', $local) . $zone;
    }

    /** A site-local MySQL datetime in the format given, else the date_format option. */
    public static function format(Site $site, string $local, string $format = ''): string
    {
        $format = $format !== '' ? $format : ($site->option('date_format') ?: 'F j, Y');
        $time = new DateTimeImmutable($local, new DateTimeZone('UTC'));
        return $time->format($format);
    }
}
