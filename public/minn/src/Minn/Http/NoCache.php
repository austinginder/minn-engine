<?php

declare(strict_types=1);

namespace Minn\Http;

use Minn\Runtime\Runtime;

/** The headers that keep a response out of every cache. */
final class NoCache
{
    /**
     * The headers (wp_get_nocache_headers): an expiry in the past and a
     * Cache-Control that forbids storing, as nocache_headers leaves them, with
     * Last-Modified always marked for removal.
     *
     * @return array<string, mixed>
     */
    public static function headers(): array
    {
        $headers = Runtime::hooks()->filter('nocache_headers', [['Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private']]);
        $headers['Last-Modified'] = false;
        return $headers;
    }
}
