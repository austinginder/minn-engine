<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * Whether a TLS certificate names a host: a wildcard only as a whole first
 * label with at least two labels after it, matching exactly one label; an
 * IP address never matches by name; the subjectAltName DNS entries win over
 * the common name when present.
 */
final class CertificateName
{
    /** Whether a certificate's reference name is one a host can match. */
    public static function valid(string $reference): bool
    {
        $labels = explode('.', $reference);
        $first = (string) array_shift($labels);
        if (str_contains($first, '*') && ($first !== '*' || count($labels) < 2)) {
            return false;
        }
        foreach ($labels as $label) {
            if (str_contains($label, '*')) {
                return false;
            }
        }
        return true;
    }

    /** Whether the host matches the reference name. */
    public static function matches(string $host, string $reference): bool
    {
        if (!self::valid($reference) || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        if (strcasecmp($host, $reference) === 0) {
            return true;
        }
        if (!str_starts_with($reference, '*.')) {
            return false;
        }
        $hostLabels = explode('.', $host);
        array_shift($hostLabels);
        return strcasecmp(implode('.', $hostLabels), substr($reference, 2)) === 0;
    }

    /**
     * Whether a parsed certificate (openssl_x509_parse) names the host.
     *
     * @param array<string, mixed> $certificate
     */
    public static function certificateMatches(string $host, array $certificate): bool
    {
        $alternatives = (string) ($certificate['extensions']['subjectAltName'] ?? '');
        if ($alternatives !== '') {
            foreach (explode(',', $alternatives) as $entry) {
                $entry = trim($entry);
                if (stripos($entry, 'DNS:') === 0 && self::matches($host, trim(substr($entry, 4)))) {
                    return true;
                }
            }
            return false;
        }
        $common = $certificate['subject']['CN'] ?? null;
        return is_string($common) && self::matches($host, $common);
    }
}
