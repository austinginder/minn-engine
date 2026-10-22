<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\KsesProtocols;
use Minn\Support\Url;

/**
 * Whether an address is one the site may fetch (wp_http_validate_url): an
 * http or https address that survives the protocol check unchanged, whose
 * host is not a private or reserved one unless it is the site's own or
 * http_request_host_is_external lets it through, on a safe port
 * (http_allowed_safe_ports). The address comes back as it was, or false.
 */
final class SafeUrls
{
    /** The address when it may be fetched, else false. */
    public static function validated(mixed $url): string|false
    {
        if (!is_string($url) || $url === '') {
            return false;
        }
        $checked = KsesProtocols::strip($url, ['http', 'https']);
        if ($checked === '' || strtolower($checked) !== strtolower($url)) {
            return false;
        }
        $home = parse_url(SiteUrls::home());
        return Url::validateForHttp(
            $checked,
            (string) ($home['host'] ?? ''),
            (int) ($home['port'] ?? 0),
            static fn (string $host, string $url): bool => (bool) Runtime::hooks()->filter('http_request_host_is_external', [false, $host, $url]),
            static fn (array $ports, string $host, string $url): array => (array) Runtime::hooks()->filter('http_allowed_safe_ports', [$ports, $host, $url]),
        ) ?? false;
    }
}
