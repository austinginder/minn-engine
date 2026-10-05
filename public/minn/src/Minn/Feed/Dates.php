<?php

declare(strict_types=1);

namespace Minn\Feed;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/** Feed dates (RFC 822, W3C, and the looser forms feeds carry) as Unix timestamps; a date without a zone is UTC. */
final class Dates
{
    /** The timestamp, or null when the text names no date. */
    public static function parse(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($text, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }
}
