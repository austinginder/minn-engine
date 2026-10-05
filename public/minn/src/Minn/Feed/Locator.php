<?php

declare(strict_types=1);

namespace Minn\Feed;

/**
 * Feed autodiscovery: the feeds an HTML page names in its
 * <link rel="alternate"> elements with a feed type, in page order, each
 * resolved against the page (or its <base href>).
 */
final class Locator
{
    private const TYPES = ['application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', 'text/xml'];

    /** Whether a response looks like a feed document rather than a page or text. */
    public static function looksLikeFeed(string $body, string $contentType): bool
    {
        $head = strtolower(substr(ltrim($body, "\xEF\xBB\xBF \t\r\n"), 0, 2048));
        if (preg_match('/<(rss|feed|rdf:rdf)[\s>]/', $head)) {
            return true;
        }
        $mime = strtolower(trim(explode(';', $contentType)[0]));
        return in_array($mime, self::TYPES, true) && str_starts_with($head, '<') && !str_contains($head, '<html');
    }

    /**
     * The feed URLs a page names, resolved, in page order, each once.
     *
     * @return list<string>
     */
    public static function alternates(string $html, string $pageUrl): array
    {
        $base = $pageUrl;
        if (preg_match('/<base\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $html, $m)) {
            $base = Iri::resolve($pageUrl, html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $pageUrl;
        }
        $found = [];
        preg_match_all('/<link\b([^>]*)>/is', $html, $links);
        foreach ($links[1] as $raw) {
            $attributes = self::attributes($raw);
            $rel = preg_split('/\s+/', strtolower(trim($attributes['rel'] ?? ''))) ?: [];
            $type = strtolower(trim(explode(';', $attributes['type'] ?? '')[0]));
            if (!in_array('alternate', $rel, true) || !in_array($type, self::TYPES, true) || trim($attributes['href'] ?? '') === '') {
                continue;
            }
            $url = Iri::resolve($base, $attributes['href']);
            if ($url !== null && !in_array($url, $found, true)) {
                $found[] = $url;
            }
        }
        return $found;
    }

    /** @return array<string, string> */
    private static function attributes(string $raw): array
    {
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $raw, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $m) {
            $out[strtolower($m[1])] ??= html_entity_decode($m[2] !== '' ? $m[2] : (($m[3] ?? '') !== '' ? $m[3] : ($m[4] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $out;
    }
}
