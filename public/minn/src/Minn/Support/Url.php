<?php

declare(strict_types=1);

namespace Minn\Support;

use Closure;

/**
 * URL shaping the escaping and query helpers share: the character cleanup
 * esc_url applies, bracket encoding outside the authority, and query
 * argument merging. Behaviour pinned by contracts/fixtures/api/functions.json.
 */
final class Url
{
    /** Spaces encoded, stray characters dropped, ";//" healed, a bare host given http; '' when nothing survives. */
    public static function clean(string $url): string
    {
        $url = str_replace(' ', '%20', ltrim($url));
        $url = (string) preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);
        if ($url === '') {
            return '';
        }
        $url = str_replace(';//', '://', $url);
        if (!str_contains($url, ':') && !in_array($url[0], ['/', '#', '?'], true) && !preg_match('/^[a-z0-9-]+?\.php/i', $url)) {
            $url = 'http://' . $url;
        }
        return $url;
    }

    /** Square brackets after the authority are percent-encoded; the authority itself is left alone. @param array<string, mixed> $parsed */
    public static function encodeBrackets(string $url, array $parsed): string
    {
        if (!str_contains($url, '[') && !str_contains($url, ']')) {
            return $url;
        }
        $front = '';
        if (isset($parsed['scheme'])) {
            $front .= $parsed['scheme'] . '://';
        } elseif ($url[0] === '/') {
            $front .= '//';
        }
        $front .= ($parsed['user'] ?? '') . (isset($parsed['pass']) ? ':' . $parsed['pass'] : '') . (isset($parsed['user']) || isset($parsed['pass']) ? '@' : '');
        $front .= ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        return $front . str_replace(['[', ']'], ['%5B', '%5D'], substr($url, strlen($front)));
    }

    /**
     * The URI with query arguments merged in (false removes one), the
     * fragment kept, a bare query string treated as such.
     *
     * @param array<string, mixed> $new
     * @param Closure(array): array $encode encodes the existing arguments the way the reference does
     * @param Closure(array): string $build builds the query string
     */
    public static function withQuery(string $uri, array $new, Closure $encode, Closure $build): string
    {
        $frag = '';
        if (($pos = strpos($uri, '#')) !== false) {
            $frag = substr($uri, $pos);
            $uri = substr($uri, 0, $pos);
        }
        $protocol = '';
        if (preg_match('|^https?://|i', $uri, $m)) {
            $protocol = $m[0];
            $uri = substr($uri, strlen($protocol));
        }
        if (str_contains($uri, '?')) {
            [$base, $query] = explode('?', $uri, 2);
            $base .= '?';
        } elseif ($protocol !== '' || !str_contains($uri, '=')) {
            $base = $uri . '?';
            $query = '';
        } else {
            $base = '';
            $query = $uri;
        }
        parse_str($query, $current);
        $current = $encode($current);
        foreach ($new as $k => $v) {
            $current[$k] = $v;
        }
        $current = array_filter($current, static fn ($v) => $v !== false);
        return $protocol . trim($base . $build($current), '?') . $frag;
    }

    /**
     * The checks wp_http_validate_url makes on a URL whose protocol already
     * passed: an http(s) scheme, a host without credentials or a colon, no
     * private address unless it is this site or allowed, only the usual
     * ports (or the site's own). Returns the URL, or null when refused.
     *
     * @param Closure(string, string): bool $externalAllowed whether a private host may be fetched anyway
     */
    public static function validateForHttp(string $url, string $homeHost, int $homePort, Closure $externalAllowed): ?string
    {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['host'], $parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return null;
        }
        if (isset($parsed['user']) || isset($parsed['pass']) || str_contains($parsed['host'], ':')) {
            return null;
        }
        $host = trim($parsed['host'], '.');
        $ip = preg_match('#^(([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)$#', $host) ? $host : gethostbyname($host);
        if ($ip !== $host || $ip === $host && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip)) {
            $parts = array_map('intval', explode('.', $ip));
            if (count($parts) === 4 && self::isPrivate($parts) && strtolower($host) !== strtolower($homeHost) && !$externalAllowed($host, $url)) {
                return null;
            }
        }
        if (empty($parsed['port'])) {
            return $url;
        }
        $port = (int) $parsed['port'];
        if (in_array($port, [80, 443, 8080], true) || ($parsed['host'] === $homeHost && $port === $homePort)) {
            return $url;
        }
        return null;
    }

    /** @param list<int> $p */
    private static function isPrivate(array $p): bool
    {
        return $p[0] === 127 || $p[0] === 10 || $p[0] === 0 || ($p[0] === 172 && $p[1] >= 16 && $p[1] <= 31) || ($p[0] === 192 && $p[1] === 168) || ($p[0] === 169 && $p[1] === 254);
    }

    /** A query string in the reference's spelling: nested keys as `a%5Bb%5D`, null as a bare key, booleans as 0/1. */
    public static function buildQuery(array $data, string $prefix = ''): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? urlencode((string) $key) : $prefix . '%5B' . urlencode((string) $key) . '%5D';
            if (is_array($value) || is_object($value)) {
                $pairs[] = self::buildQuery((array) $value, $name);
            } elseif ($value === null) {
                $pairs[] = $name;
            } else {
                $pairs[] = $name . '=' . (is_bool($value) ? (int) $value : (string) $value);
            }
        }
        return implode('&', array_filter($pairs, static fn (string $pair) => $pair !== ''));
    }
}
