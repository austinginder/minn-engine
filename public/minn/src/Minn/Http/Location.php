<?php

declare(strict_types=1);

namespace Minn\Http;

/** Where a redirect leads: a Location header made absolute against the URL that sent it. */
final class Location
{
    /** The absolute URL $location names, read against $base. */
    public static function resolve(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        $origin = $scheme . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = $parts['path'] ?? '/';
        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $location;
    }

    /** Whether two URLs share scheme, host, and port, so credentials may follow from one to the other. */
    public static function sameOrigin(string $one, string $two): bool
    {
        $origin = static function (string $url): string {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);
            return $scheme . '://' . Destination::host($url) . ':' . $port;
        };
        return $origin($one) === $origin($two);
    }
}
