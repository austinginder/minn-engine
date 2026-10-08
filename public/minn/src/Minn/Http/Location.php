<?php

declare(strict_types=1);

namespace Minn\Http;

/** Where a redirect leads: a Location header made absolute against the URL that sent it. */
final class Location
{
    /**
     * The absolute URL $location names, read against $base as RFC 3986
     * reads a reference: a scheme keeps it as written, "//" takes the
     * base's scheme, a fragment or query alone keeps the base's path, and a
     * relative path joins the base's directory with "." and ".." resolved.
     */
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
        $basePath = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        if ($location === '' || $location[0] === '#') {
            return $origin . $basePath . (isset($parts['query']) ? '?' . $parts['query'] : '') . $location;
        }
        if ($location[0] === '?') {
            return $origin . $basePath . $location;
        }
        $cut = strcspn($location, '?#');
        $path = substr($location, 0, $cut);
        $merged = str_starts_with($path, '/') ? $path : substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
        return $origin . self::withoutDotSegments($merged) . substr($location, $cut);
    }

    /** A path with its "." and ".." segments resolved (RFC 3986 remove_dot_segments). */
    private static function withoutDotSegments(string $path): string
    {
        $out = [];
        $segments = explode('/', $path);
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($segment === '.' || $segment === '..') {
                if ($segment === '..' && count($out) > 1) {
                    array_pop($out);
                }
                if ($i === $last) {
                    $out[] = '';
                }
                continue;
            }
            $out[] = $segment;
        }
        $joined = implode('/', $out);
        return str_starts_with($joined, '/') ? $joined : '/' . $joined;
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
