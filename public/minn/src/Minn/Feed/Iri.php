<?php

declare(strict_types=1);

namespace Minn\Feed;

/**
 * URLs in feeds: a reference resolved against its base (RFC 3986), then
 * normalized the way the reference prints them: scheme and host in lower
 * case, the default port dropped, dot segments removed, and every byte a
 * URL may not carry (spaces, angle brackets, non-ASCII) percent-encoded.
 */
final class Iri
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443, 'ftp' => 21];

    /** The reference resolved against the base and normalized; null when neither is absolute. */
    public static function resolve(string $base, string $reference): ?string
    {
        $reference = trim($reference);
        $ref = self::split($reference);
        if ($ref['scheme'] !== null) {
            return self::normalize($ref);
        }
        $parts = self::split($base);
        if ($parts['scheme'] === null) {
            return null;
        }
        if ($ref['authority'] !== null) {
            return self::normalize(['scheme' => $parts['scheme']] + $ref);
        }
        if ($ref['path'] === '') {
            $parts['query'] = $ref['query'] ?? $parts['query'];
        } else {
            $parts['path'] = str_starts_with($ref['path'], '/') ? $ref['path'] : self::merge($parts, $ref['path']);
            $parts['query'] = $ref['query'];
        }
        $parts['fragment'] = $ref['fragment'];
        return self::normalize($parts);
    }

    /** An absolute URL normalized; anything else returned as given. */
    public static function clean(string $url): string
    {
        $parts = self::split(trim($url));
        return $parts['scheme'] === null ? $url : self::normalize($parts);
    }

    /** @return array{scheme: ?string, authority: ?string, path: string, query: ?string, fragment: ?string} */
    private static function split(string $url): array
    {
        preg_match('%^(?:([A-Za-z][A-Za-z0-9+.-]*):)?(?://([^/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#(.*))?$%s', $url, $m, PREG_UNMATCHED_AS_NULL);
        return ['scheme' => $m[1] ?? null, 'authority' => $m[2] ?? null, 'path' => (string) ($m[3] ?? ''), 'query' => $m[4] ?? null, 'fragment' => $m[5] ?? null];
    }

    private static function merge(array $base, string $path): string
    {
        if ($base['authority'] !== null && $base['path'] === '') {
            return '/' . $path;
        }
        $at = strrpos($base['path'], '/');
        return ($at === false ? '' : substr($base['path'], 0, $at + 1)) . $path;
    }

    private static function normalize(array $parts): string
    {
        $scheme = strtolower((string) $parts['scheme']);
        $url = $scheme . ':';
        if ($parts['authority'] !== null) {
            $authority = $parts['authority'];
            if (preg_match('/^(.*@)?([^@:]*|\[[^\]]*\])(?::(\d*))?$/', $authority, $a)) {
                $port = ($a[3] ?? '') === '' || (int) $a[3] === (self::DEFAULT_PORTS[$scheme] ?? -1) ? '' : ':' . (int) $a[3];
                $authority = ($a[1] ?? '') . strtolower($a[2]) . $port;
            }
            $url .= '//' . self::encode($authority, ':@[]');
        }
        $path = self::removeDots((string) $parts['path']);
        if ($parts['authority'] !== null && $path === '' && in_array($scheme, ['http', 'https'], true)) {
            $path = '/';
        }
        $url .= self::encode($path, '/:@');
        if ($parts['query'] !== null) {
            $url .= '?' . self::encode($parts['query'], '/:@?');
        }
        if ($parts['fragment'] !== null) {
            $url .= '#' . self::encode($parts['fragment'], '/:@?');
        }
        return $url;
    }

    private static function removeDots(string $path): string
    {
        $out = [];
        $segments = explode('/', $path);
        foreach ($segments as $i => $segment) {
            if ($segment === '.') {
                if ($i === count($segments) - 1) {
                    $out[] = '';
                }
                continue;
            }
            if ($segment === '..') {
                if (count($out) > 1 || (count($out) === 1 && $out[0] !== '')) {
                    array_pop($out);
                }
                if ($i === count($segments) - 1) {
                    $out[] = '';
                }
                continue;
            }
            $out[] = $segment;
        }
        return implode('/', $out);
    }

    /** Percent-encodes every byte outside the unreserved and sub-delimiter sets and the extra characters a component allows. */
    private static function encode(string $text, string $allowed): string
    {
        return (string) preg_replace_callback('/%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-._~!$&\'()*+,;=%' . preg_quote($allowed, '/') . ']/', static fn (array $m): string => rawurlencode($m[0]), $text);
    }
}
