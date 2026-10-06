<?php

declare(strict_types=1);

namespace Minn\Http;

use RuntimeException;

/**
 * A file the engine fetches for itself (a package, a language pack), over
 * Minn\Http with the rules a download needs: https at every hop, one of the
 * caller's host prefixes when given, no private address, and a body over
 * the cap fails rather than being truncated. The messages are written for
 * the person who asked for the download.
 */
final class Download
{
    /**
     * The body at $url, following at most five redirects.
     *
     * @param list<string> $hostPrefixes URL prefixes every hop must start with (none: any https host)
     * @throws RuntimeException naming what refused the download
     */
    public static function https(string $url, int $maxBytes, array $hostPrefixes = [], string $userAgent = 'Minn Engine'): string
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('Downloads are fetched over https only.');
        }
        $reply = \Minn\Http::get($url, timeout: 60, hosts: $hostPrefixes === [] ? ['https://'] : $hostPrefixes, maxBytes: $maxBytes, userAgent: $userAgent);
        $host = Destination::host($reply->url !== '' ? $reply->url : $url);
        if ($reply->failed()) {
            throw new RuntimeException(match ($reply->errno) {
                0 => "The download led to {$host}, which is not a host this package may come from.",
                CURLE_FILESIZE_EXCEEDED => 'The download is larger than the ' . (int) ($maxBytes / 1048576) . ' MB the engine accepts.',
                CURLE_TOO_MANY_REDIRECTS => 'The download redirected more than five times.',
                default => "The download failed. Check the site can reach {$host} and try again.",
            });
        }
        if (!$reply->ok()) {
            throw new RuntimeException("{$host} answered {$reply->code}.");
        }
        if ($reply->body === '') {
            throw new RuntimeException('The download was empty.');
        }
        return $reply->body;
    }
}
