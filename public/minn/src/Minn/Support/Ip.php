<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Addresses with their identifying tail removed, for logs and analytics that
 * must not keep a visitor's exact address. An IPv4 address loses its last
 * octet, an IPv6 address its last four groups. Anything that is not an
 * address at all reads as the unspecified IPv4 address rather than as itself,
 * so a malformed value can never leak through unchanged.
 */
final class Ip
{
    private const UNSPECIFIED = '0.0.0.0';

    public static function anonymize(string $address): string
    {
        $address = trim($address);
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return (string) preg_replace('/\d+$/', '0', $address);
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return self::UNSPECIFIED;
        }
        $packed = inet_pton($address);
        if ($packed === false) {
            return self::UNSPECIFIED;
        }
        $masked = inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
        return $masked === false ? self::UNSPECIFIED : $masked;
    }
}
