<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The requests a batch payload names, normalised into descriptors the
 * caller turns into real request objects. A path's query string is split
 * off here so each sub-request carries its own query parameters, which is
 * what lets one round trip stand in for several.
 */
final class BatchRequest
{
    /**
     * @param mixed $requests the batch payload's `requests` member
     * @return list<array{method: string, path: string, query: array<string, mixed>, body: ?array, headers: ?array}>
     */
    public static function describe(mixed $requests): array
    {
        $out = [];
        foreach ((array) $requests as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $path = (string) ($entry['path'] ?? '');
            $parsed = parse_url($path);
            $query = [];
            if (is_array($parsed) && ($parsed['query'] ?? '') !== '') {
                parse_str((string) $parsed['query'], $query);
            }
            $out[] = [
                'method' => (string) ($entry['method'] ?? 'POST'),
                'path' => is_array($parsed) && isset($parsed['path']) ? (string) $parsed['path'] : $path,
                'query' => $query,
                'body' => isset($entry['body']) ? (array) $entry['body'] : null,
                'headers' => isset($entry['headers']) ? (array) $entry['headers'] : null,
            ];
        }
        return $out;
    }
}
