<?php

declare(strict_types=1);

namespace Minn\Http;

use RuntimeException;

/**
 * A file the engine fetches for itself (a package, a language pack). Every
 * hop of a redirect chain is judged on its own: https only, and when host
 * prefixes are given, one of them, so a redirect cannot lead a download
 * off the host the caller trusted. The body is capped, and a body over the
 * cap fails rather than being truncated.
 */
final class Download
{
    private const MAX_HOPS = 5;

    /**
     * The body at $url, following at most five redirects.
     *
     * @param list<string> $hostPrefixes URL prefixes every hop must start with (none: any https host)
     * @throws RuntimeException naming what refused the download
     */
    public static function https(string $url, int $maxBytes, array $hostPrefixes = [], string $userAgent = 'Minn Engine'): string
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            self::allow($url, $hostPrefixes);
            $context = stream_context_create([
                'http' => ['timeout' => 60, 'follow_location' => 0, 'ignore_errors' => true, 'user_agent' => $userAgent],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
            $headers = $http_response_header ?? [];
            if ($body === false) {
                throw new RuntimeException('The download failed. Check the site can reach ' . self::host($url) . ' and try again.');
            }
            $status = self::status($headers);
            $location = self::location($headers);
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== null) {
                $url = self::resolve($url, $location);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException(self::host($url) . " answered {$status}.");
            }
            if (strlen($body) > $maxBytes) {
                throw new RuntimeException('The download is larger than the ' . (int) ($maxBytes / 1048576) . ' MB the engine accepts.');
            }
            if ($body === '') {
                throw new RuntimeException('The download was empty.');
            }
            return $body;
        }
        throw new RuntimeException('The download redirected more than five times.');
    }

    /** @param list<string> $hostPrefixes */
    private static function allow(string $url, array $hostPrefixes): void
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('Downloads are fetched over https only.');
        }
        if ($hostPrefixes === []) {
            return;
        }
        foreach ($hostPrefixes as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return;
            }
        }
        throw new RuntimeException('The download led to ' . self::host($url) . ', which is not a host this package may come from.');
    }

    /** @param list<string> $headers */
    private static function status(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /** @param list<string> $headers */
    private static function location(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $value = trim(substr($header, 9));
                return $value === '' ? null : $value;
            }
        }
        return null;
    }

    /** A Location header made absolute against the URL that sent it. */
    private static function resolve(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = $parts['path'] ?? '/';
        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $location;
    }

    private static function host(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }
}
