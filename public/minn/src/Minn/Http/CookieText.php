<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * Set-Cookie text and the matching rules a cookie jar applies: parsing a
 * header into name, value and attributes; normalizing attributes (expires
 * and max-age as timestamps, the domain without its leading dot); whether
 * a cookie belongs to a domain and a path (RFC 6265).
 */
final class CookieText
{
    /**
     * A Set-Cookie value split up. With a name given, the first part is
     * all value; a first part without "=" is a value with an empty name.
     *
     * @return array{name: string, value: string, attributes: array<string, string|true>}
     */
    public static function parse(string $header, string $name = ''): array
    {
        $parts = explode(';', $header);
        $first = (string) array_shift($parts);
        if ($name !== '') {
            $value = $first;
        } elseif (!str_contains($first, '=')) {
            [$name, $value] = ['', $first];
        } else {
            [$name, $value] = explode('=', $first, 2);
        }
        $attributes = [];
        foreach ($parts as $part) {
            [$key, $attribute] = str_contains($part, '=') ? explode('=', $part, 2) : [$part, true];
            $attributes[trim($key)] = is_string($attribute) ? trim($attribute) : $attribute;
        }
        return ['name' => trim($name), 'value' => trim($value), 'attributes' => $attributes];
    }

    /** One attribute normalized, or null when it should go: dates become timestamps, the domain loses its leading dot. */
    public static function normalizeAttribute(string $name, mixed $value, int $referenceTime): mixed
    {
        switch (strtolower($name)) {
            case 'expires':
                if (is_int($value)) {
                    return $value;
                }
                $stamp = strtotime((string) $value);
                return $stamp === false ? null : $stamp;
            case 'max-age':
                if (is_int($value)) {
                    return $value;
                }
                return is_numeric($value) ? $referenceTime + (int) $value : null;
            case 'domain':
                $value = (string) $value;
                if ($value === '') {
                    return null;
                }
                return $value[0] === '.' ? substr($value, 1) : $value;
            default:
                return $value;
        }
    }

    /** Whether a host-only cookie with this domain attribute (none means any) belongs to the domain: the same text only. */
    public static function hostMatches(?string $cookieDomain, string $domain): bool
    {
        return $cookieDomain === null || $cookieDomain === $domain;
    }

    /** Whether a cookie with this domain attribute (none means any) belongs to the domain or one of its subdomains (never an IP address). */
    public static function domainMatches(?string $cookieDomain, string $domain): bool
    {
        if (self::hostMatches($cookieDomain, $domain)) {
            return true;
        }
        if (strlen($domain) <= strlen((string) $cookieDomain) || !str_ends_with($domain, (string) $cookieDomain)) {
            return false;
        }
        $prefix = substr($domain, 0, strlen($domain) - strlen((string) $cookieDomain));
        return str_ends_with($prefix, '.') && filter_var($domain, FILTER_VALIDATE_IP) === false;
    }

    /** Whether a cookie with this path attribute (none means any) is sent for the request path. */
    public static function pathMatches(?string $cookiePath, string $requestPath): bool
    {
        $requestPath = $requestPath === '' ? '/' : $requestPath;
        if ($cookiePath === null || $cookiePath === $requestPath) {
            return true;
        }
        if (strlen($requestPath) > strlen($cookiePath) && str_starts_with($requestPath, $cookiePath)) {
            return str_ends_with($cookiePath, '/') || $requestPath[strlen($cookiePath)] === '/';
        }
        return false;
    }

    /** The path a cookie without one gets: the request path up to its last slash, or "/". */
    public static function defaultPath(string $requestPath): string
    {
        if ($requestPath === '' || $requestPath[0] !== '/' || substr_count($requestPath, '/') === 1) {
            return '/';
        }
        return substr($requestPath, 0, (int) strrpos($requestPath, '/'));
    }
}
