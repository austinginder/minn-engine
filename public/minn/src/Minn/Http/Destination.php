<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;

/**
 * Where Minn\Http lets a request go. Only http and https; when hosts are
 * named, every hop must start with one of them; and an address outside the
 * public internet (loopback, private and shared ranges, link-local, cloud
 * metadata, reserved) is refused unless its host is listed in $private. A
 * name is resolved here and curl is pinned to the addresses that were
 * checked, so DNS cannot answer one way to the check and another to the
 * connection.
 */
final readonly class Destination
{
    /** @var list<string> */
    private array $private;

    /**
     * @param list<string> $hosts URL prefixes every hop must start with; none means any public host
     * @param list<string> $private host names or addresses allowed to reach a private address
     * @param (Closure(string): list<string>)|null $lookup a host's addresses; DNS when null
     */
    public function __construct(private array $hosts = [], array $private = [], private ?Closure $lookup = null)
    {
        $this->private = array_map(static fn (string $host): string => strtolower(trim($host, '[]')), $private);
    }

    /** Why $url may not be requested, or null when nothing about the URL itself refuses it (a name is judged again once resolved). */
    public function refusal(string $url): ?string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = self::host($url);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return "Only http and https URLs can be requested, not {$url}.";
        }
        if ($this->hosts !== [] && !$this->listed($url)) {
            return "{$host} is not one of the hosts this request may reach.";
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false && !$this->allowed($host, $host)) {
            return "{$host} is a private address. List it in private: to reach it.";
        }
        return null;
    }

    /**
     * The request with curl pinned to the addresses its host resolves to,
     * or the failed exchange when the name does not resolve or resolves
     * somewhere private it was not allowed.
     */
    public function pinned(Outbound $request): Outbound|Exchange
    {
        $host = self::host($request->url);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || in_array($host, $this->private, true)) {
            return $request;
        }
        $addresses = $this->lookup === null ? self::dns($host) : ($this->lookup)($host);
        if ($addresses === []) {
            return Exchange::failure("Could not resolve host: {$host}", CURLE_COULDNT_RESOLVE_HOST, $request->url);
        }
        foreach ($addresses as $address) {
            if (!$this->allowed($host, $address)) {
                return Exchange::failure("{$host} resolves to {$address}, a private address. List {$host} in private: to reach it.", 0, $request->url);
            }
        }
        $port = (int) (parse_url($request->url, PHP_URL_PORT) ?: (strtolower((string) parse_url($request->url, PHP_URL_SCHEME)) === 'https' ? 443 : 80));
        $entry = $host . ':' . $port . ':' . implode(',', array_map(static fn (string $a): string => str_contains($a, ':') ? "[{$a}]" : $a, $addresses));
        return $request->preparing(static function (\CurlHandle $handle) use ($entry): void {
            curl_setopt($handle, CURLOPT_RESOLVE, [$entry]);
        });
    }

    /** A URL's host, lower-cased, without the brackets around an IPv6 address. */
    public static function host(string $url): string
    {
        return strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
    }

    private function listed(string $url): bool
    {
        foreach ($this->hosts as $prefix) {
            if (str_starts_with(strtolower($url), strtolower($prefix))) {
                return true;
            }
        }
        return false;
    }

    private function allowed(string $host, string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            || in_array($host, $this->private, true)
            || in_array(strtolower($address), $this->private, true);
    }

    /**
     * A name's IPv4 addresses, or its IPv6 ones when it has none.
     *
     * @return list<string>
     */
    private static function dns(string $host): array
    {
        $v4 = @gethostbynamel($host);
        if (is_array($v4) && $v4 !== []) {
            return array_values($v4);
        }
        $v6 = @dns_get_record($host, DNS_AAAA);
        return is_array($v6) ? array_values(array_filter(array_column($v6, 'ipv6'), 'is_string')) : [];
    }
}
