<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Content\Site;
use Minn\I18n\LocalDates;

/** Site-local dates the way the dynamic blocks print them. */
final class Dates
{
    /** ISO 8601 with the site's offset at that date (summer or winter time), from a site-local MySQL datetime. */
    public static function iso(Site $site, string $local): string
    {
        return (new DateTimeImmutable($local, $site->timezone()))->format('c');
    }

    /** A site-local MySQL datetime in the format given, else the date_format option, in the site's language. */
    public static function format(Site $site, string $local, string $format = ''): string
    {
        $format = $format !== '' ? $format : ($site->option('date_format') ?: 'F j, Y');
        return LocalDates::format(new DateTimeImmutable($local, new DateTimeZone('UTC')), $format);
    }
}
