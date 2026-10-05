<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The Requests library's PSR-0 class names (Requests_Exception_HTTP_404,
 * Requests_IDNAEncoder, ...) mapped to the PSR-4 names they stand for.
 */
final class RequestsNames
{
    private const SEGMENTS = ['http' => 'Http', 'idnaencoder' => 'IdnaEncoder', 'ipv6' => 'Ipv6', 'iri' => 'Iri', 'ssl' => 'Ssl', 'curl' => 'Curl', 'fsockopen' => 'Fsockopen', 'hooker' => 'HookManager', 'casesensitivedictionary' => 'CaseInsensitiveDictionary', 'caseinsensitivedictionary' => 'CaseInsensitiveDictionary', 'filterediterator' => 'FilteredIterator'];

    /** The namespaced name for a Requests_* name, or null when it is not one. */
    public static function modern(string $legacy): ?string
    {
        if (!str_starts_with($legacy, 'Requests_')) {
            return null;
        }
        $segments = explode('_', substr($legacy, strlen('Requests_')));
        if (count($segments) === 3 && strtolower($segments[0]) === 'exception' && strtolower($segments[1]) === 'http') {
            return 'WpOrg\\Requests\\Exception\\Http\\Status' . (strtolower($segments[2]) === 'unknown' ? 'Unknown' : $segments[2]);
        }
        $mapped = array_map(static fn (string $s): string => self::SEGMENTS[strtolower($s)] ?? ucfirst($s), $segments);
        return 'WpOrg\\Requests\\' . implode('\\', $mapped);
    }
}
