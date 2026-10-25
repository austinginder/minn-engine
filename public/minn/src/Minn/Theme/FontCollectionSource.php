<?php

declare(strict_types=1);

namespace Minn\Theme;

use Closure;
use Minn\Json;
use Minn\Objects\Errors;
use Minn\Runtime\Transients;

/**
 * A font collection read from an address, kept as the reference keeps it
 * (probed 2026-10-24 on its Google Fonts collection): the collection's
 * families, sanitized as the collection keeps them, in a site transient
 * named after the address for a day, read from there until it lapses. A
 * fetch that fails, answers anything but 200, or holds no font_families is
 * kept nowhere, so the next ask tries again.
 */
final class FontCollectionSource
{
    /** Seconds a fetched collection is kept. */
    public const TTL = 86400;

    /**
     * The collection at $url as ['font_families' => ...], or null when it
     * could not be read. $fetch makes the request (the WordPress layer's
     * HTTP API, so its filters hear it) and hands back its response;
     * $sanitize gives the families as the collection keeps them.
     *
     * @param Closure(): mixed $fetch
     * @param Closure(array): array $sanitize
     * @return array{font_families: array}|null
     */
    public static function remember(string $url, Closure $fetch, Closure $sanitize): ?array
    {
        $name = 'wp_font_collection_url_' . $url;
        $kept = Transients::getSite($name);
        if (is_array($kept) && is_array($kept['font_families'] ?? null)) {
            return $kept;
        }
        $response = $fetch();
        if ($response instanceof Errors || !is_array($response) || (int) ($response['response']['code'] ?? 0) !== 200) {
            return null;
        }
        $json = Json::decode((string) ($response['body'] ?? ''), or: null);
        if (!is_array($json) || !is_array($json['font_families'] ?? null)) {
            return null;
        }
        $kept = ['font_families' => $sanitize($json['font_families'])];
        Transients::setSite($name, $kept, self::TTL);
        return $kept;
    }
}
