<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * IPv6 addresses as text: written out in full (eight groups, or six and an
 * IPv4 tail), compressed ("::" for the longest run of zero groups, the
 * first when two tie), and checked. Zone suffixes ("%eth0") do not pass.
 */
final class Ipv6
{
    /** The address with "::" expanded into zero groups. */
    public static function expand(string $ip): string
    {
        if (!str_contains($ip, '::')) {
            return $ip;
        }
        [$head, $tail] = explode('::', $ip, 2);
        $count = static fn (string $part): int => $part === '' ? 0 : substr_count($part, ':') + 1 + (str_contains($part, '.') ? 1 : 0);
        $missing = max(0, 8 - $count($head) - $count($tail));
        $zeros = implode(':', array_fill(0, $missing, '0'));
        return trim(($head === '' ? '' : $head . ':') . $zeros . ($tail === '' ? '' : ':' . $tail), ':');
    }

    /** The address with leading zeros dropped from all-digit groups and the longest zero run as "::". */
    public static function compress(string $ip): string
    {
        $ip = self::expand($ip);
        $v4 = '';
        if (str_contains($ip, '.')) {
            $at = strrpos($ip, ':');
            [$ip, $v4] = $at === false ? ['', $ip] : [substr($ip, 0, $at), substr($ip, $at)];
        }
        $ip = (string) preg_replace('/(^|:)0+([0-9])/', '$1$2', $ip);
        preg_match_all('/(?:^|:)(?:0(?::|$))+/', $ip, $runs, PREG_OFFSET_CAPTURE);
        $best = null;
        foreach ($runs[0] as [$run, $offset]) {
            if ($best === null || strlen($run) > strlen($best[0])) {
                $best = [$run, $offset];
            }
        }
        if ($best !== null && strlen($best[0]) > 1) {
            $ip = substr_replace($ip, '::', $best[1], strlen($best[0]));
        }
        return $ip . $v4;
    }

    /** Whether the text is an IPv6 address (with an optional IPv4 tail). */
    public static function valid(string $ip): bool
    {
        $ip = self::expand($ip);
        $groups = explode(':', $ip);
        $want = 8;
        if (str_contains($ip, '.')) {
            $v4 = (string) array_pop($groups);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                return false;
            }
            $want = 6;
        }
        if (count($groups) !== $want) {
            return false;
        }
        foreach ($groups as $group) {
            if (!preg_match('/^[0-9a-fA-F]{1,4}$/', $group)) {
                return false;
            }
        }
        return true;
    }
}
