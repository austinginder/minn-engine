<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Support\Escape;

/**
 * The resource hint links wp_head prints (wp_resource_hints): each relation
 * (dns-prefetch, preconnect, prefetch, prerender) handed to the filter with
 * what the engine found, its entries printed once each. A plain address
 * prints rel first; an entry given as attributes prints its href, the
 * attributes the reference keeps (as, crossorigin, pr, type; a bare word
 * for a numeric key) and rel last. dns-prefetch names only the host,
 * preconnect the scheme and host, the others the whole address (probe
 * media-template-doubts).
 */
final class ResourceHints
{
    private const KEPT = ['as', 'crossorigin', 'href', 'pr', 'type'];

    /**
     * The links, one a line.
     *
     * @param list<string> $dnsPrefetch the external hosts the queued assets use
     * @param Closure(array, string): mixed $filter the wp_resource_hints filter
     */
    public static function render(array $dnsPrefetch, Closure $filter): string
    {
        $out = '';
        foreach (['dns-prefetch' => $dnsPrefetch, 'preconnect' => [], 'prefetch' => [], 'prerender' => []] as $relation => $urls) {
            $seen = [];
            foreach ((array) $filter($urls, $relation) as $entry) {
                $attrs = is_array($entry) ? $entry : [];
                $href = self::address((string) (is_array($entry) ? ($entry['href'] ?? '') : $entry), $relation);
                if ($href === '' || isset($seen[$href])) {
                    continue;
                }
                $seen[$href] = true;
                $out .= is_array($entry) ? self::withAttributes($href, $attrs, $relation) : "<link rel='" . Escape::attr($relation) . "' href='" . Escape::url($href) . "' />\n";
            }
        }
        return $out;
    }

    /** An entry's address as its relation names it. */
    private static function address(string $href, string $relation): string
    {
        $parts = parse_url($href);
        if ($href === '' || !is_array($parts)) {
            return '';
        }
        return match ($relation) {
            'dns-prefetch' => '//' . ltrim((string) ($parts['host'] ?? $href), '/'),
            'preconnect' => isset($parts['host']) ? (isset($parts['scheme']) ? $parts['scheme'] . ':' : '') . '//' . $parts['host'] : $href,
            default => $href,
        };
    }

    /** @param array<int|string, mixed> $attrs */
    private static function withAttributes(string $href, array $attrs, string $relation): string
    {
        $html = "<link href='" . Escape::url($href) . "'";
        foreach ($attrs as $name => $value) {
            $attribute = is_int($name) ? (string) $value : $name;
            if ($attribute === 'href' || !in_array($attribute, self::KEPT, true)) {
                continue;
            }
            $html .= is_int($name) ? ' ' . Escape::attr($value) : ' ' . Escape::attr($name) . "='" . Escape::attr($value) . "'";
        }
        return $html . " rel='" . Escape::attr($relation) . "' />\n";
    }
}
