<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * Which addresses in front of the engine may speak for the client.
 *
 * By default none do: a forwarded header is a request header, and a request
 * header is the client's word. Behind a proxy or CDN that terminates TLS,
 * `REMOTE_ADDR` is then the proxy's, which makes every visitor look like one
 * address (the sign-in throttle would lock them all out together) and every
 * request look insecure (the auth cookie would lose its Secure flag).
 *
 * A site says who to believe in `wp-config.php`:
 *
 *     define('MINN_TRUSTED_PROXIES', '10.0.0.0/8, 2400:cb00::/32');
 *     define('MINN_TRUSTED_PROXIES', '*');   // any: only when nothing but the proxy can reach PHP
 *
 * The client is then the right-most address in `X-Forwarded-For` that is not
 * itself trusted, which is what a proxy chain appends, so a client that sends
 * its own `X-Forwarded-For` cannot put an address the engine believes to the
 * right of the proxy's.
 */
final readonly class TrustedProxies
{
    /** @param list<string> $ranges addresses or CIDR ranges, or ['*'] for any */
    private function __construct(private array $ranges)
    {
    }

    /** Nobody in front of the engine speaks for the client. */
    public static function none(): self
    {
        return new self([]);
    }

    /** What the site declared in wp-config.php, or nobody. */
    public static function configured(): self
    {
        if (!defined('MINN_TRUSTED_PROXIES')) {
            return self::none();
        }
        $value = constant('MINN_TRUSTED_PROXIES');
        $ranges = is_array($value) ? $value : explode(',', (string) $value);
        return new self(array_values(array_filter(array_map(trim(...), $ranges), static fn (string $r): bool => $r !== '')));
    }

    /** Whether the engine believes what this address says about the client. */
    public function trusts(string $address): bool
    {
        if ($address === '' || $this->ranges === []) {
            return false;
        }
        foreach ($this->ranges as $range) {
            if ($range === '*' || self::covers($range, $address)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The client's address, given who connected and what was forwarded: the
     * right-most forwarded address that is not itself a trusted proxy.
     */
    public function clientAddress(string $remoteAddress, string $forwardedFor): string
    {
        if (!$this->trusts($remoteAddress) || trim($forwardedFor) === '') {
            return $remoteAddress;
        }
        $hops = array_map(trim(...), explode(',', $forwardedFor));
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            $hop = self::bare($hops[$i]);
            if ($hop !== '' && !$this->trusts($hop)) {
                return $hop;
            }
        }
        return $remoteAddress;
    }

    /** Whether a forwarded scheme may be believed, and says https. */
    public function forwardedSecure(string $remoteAddress, string $forwardedProto): bool
    {
        if (!$this->trusts($remoteAddress)) {
            return false;
        }
        // A chain appends, so the first value is the scheme the client spoke.
        $first = trim(explode(',', $forwardedProto)[0] ?? '');
        return strtolower($first) === 'https';
    }

    /** An address with a port or brackets stripped, as a forwarded list may carry them. */
    private static function bare(string $value): string
    {
        $value = trim($value, " \t\"");
        if (str_starts_with($value, '[')) {
            return trim(strstr($value, ']', true) ?: $value, '[');
        }
        // Only an IPv4 address takes a port here; a bare IPv6 address is full of colons.
        return substr_count($value, ':') === 1 ? strstr($value, ':', true) ?: $value : $value;
    }

    /** Whether a CIDR range or a plain address covers the address given. */
    private static function covers(string $range, string $address): bool
    {
        if (!str_contains($range, '/')) {
            return $range === $address;
        }
        [$subnet, $bits] = explode('/', $range, 2);
        $subnetBinary = @inet_pton($subnet);
        $addressBinary = @inet_pton($address);
        $bits = (int) $bits;
        if ($subnetBinary === false || $addressBinary === false || strlen($subnetBinary) !== strlen($addressBinary) || $bits < 0) {
            return false;
        }
        $whole = intdiv($bits, 8);
        $remainder = $bits % 8;
        if (substr($subnetBinary, 0, $whole) !== substr($addressBinary, 0, $whole)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = chr(0xFF << (8 - $remainder) & 0xFF);
        return (($subnetBinary[$whole] ?? "\0") & $mask) === (($addressBinary[$whole] ?? "\0") & $mask);
    }
}
