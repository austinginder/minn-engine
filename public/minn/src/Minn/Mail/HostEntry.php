<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * One entry of a mailer's Host setting ("host", "host:port",
 * "ssl://host:465", "tls://host"; entries are separated by semicolons):
 * the security prefix, the host and the port. Only ssl:// and tls:// are
 * prefixes; anything else stays part of the host and fails the host check.
 */
final readonly class HostEntry
{
    public function __construct(public string $prefix, public string $host, public ?int $port)
    {
    }

    /**
     * The entries of a Host setting, null where one cannot be read.
     *
     * @return list<array{0: string, 1: self|null}> the trimmed entry text and its reading
     */
    public static function parseAll(string $hosts): array
    {
        $out = [];
        foreach (explode(';', $hosts) as $entry) {
            $entry = trim($entry);
            $out[] = [$entry, preg_match('#^(?:(ssl|tls)://)?(.+?)(?::(\d+))?$#i', $entry, $m) ? new self(strtolower($m[1]), $m[2], isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null) : null];
        }
        return $out;
    }

    /** Whether a host name, IPv4 address or bracketed IPv6 address is well formed. */
    public static function validHost(mixed $host): bool
    {
        if (!is_string($host) || $host === '' || strlen($host) > 255) {
            return false;
        }
        if ($host[0] === '[') {
            return str_ends_with($host, ']') && preg_match('/^[0-9a-f:]+$/i', $inner = substr($host, 1, -1)) && filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        if (preg_match('/^[0-9.]+$/', $host)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\.?$/i', $host);
    }
}
