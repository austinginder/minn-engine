<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use DOMDocument;

/** oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes. */
final class OEmbed
{
    /**
     * @param array<string, array{0: string, 1: bool}> $providers mask => [endpoint with {format}, mask is a regex]
     * @return string|null the JSON endpoint of the first matching provider
     */
    public static function providerFor(array $providers, string $url): ?string
    {
        foreach ($providers as $mask => [$endpoint, $isRegex]) {
            if (!$isRegex) {
                $mask = '#' . str_replace('___wildcard___', '(.+)', preg_quote(str_replace('*', '___wildcard___', (string) $mask), '#')) . '#i';
                $mask = (string) preg_replace('|^#http\\\\://|', '#https?\://', $mask);
            }
            if (preg_match($mask, $url)) {
                return str_replace('{format}', 'json', $endpoint);
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public static function parseJson(string $body): ?array
    {
        $data = json_decode(trim($body), true);
        return is_array($data) && $data !== [] && !array_is_list($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    public static function parseXml(string $body): ?array
    {
        if (!function_exists('simplexml_import_dom') || !class_exists(DOMDocument::class, false)) {
            return null;
        }
        $dom = new DOMDocument();
        if (!@$dom->loadXML($body) || $dom->documentElement === null || $dom->documentElement->tagName !== 'oembed') {
            return null;
        }
        $data = [];
        foreach (simplexml_import_dom($dom->documentElement) as $key => $value) {
            $data[(string) $key] = (string) $value;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data the oEmbed payload
     * @param Closure(string): string $escUrl @param Closure(string): string $escAttr @param Closure(string): string $escHtml
     */
    public static function html(array $data, string $url, Closure $escUrl, Closure $escAttr, Closure $escHtml): ?string
    {
        $title = is_string($data['title'] ?? null) ? $data['title'] : '';
        return match ($data['type'] ?? '') {
            'photo' => empty($data['url']) || empty($data['width']) || empty($data['height']) ? null
                : '<a href="' . $escUrl($url) . '"><img src="' . $escUrl((string) $data['url']) . '" alt="' . $escAttr($title) . '" width="' . $escAttr((string) $data['width']) . '" height="' . $escAttr((string) $data['height']) . '" /></a>',
            'video', 'rich' => is_string($data['html'] ?? null) && $data['html'] !== '' ? $data['html'] : null,
            'link' => $title !== '' ? '<a href="' . $escUrl($url) . '">' . $escHtml($title) . '</a>' : null,
            default => null,
        };
    }

    /** Drops newlines from embed markup while leaving the inside of <pre> blocks untouched. */
    public static function stripNewlines(string $html): string
    {
        if (!str_contains($html, "\n")) {
            return $html;
        }
        $search = ["\t", "\n", "\r", ' '];
        $replace = ['__TAB__', '__NL__', '__CR__', '__SPACE__'];
        $found = [];
        preg_match_all('#(<pre[^>]*>.+?</pre>)#i', str_replace($search, $replace, $html), $matches, PREG_SET_ORDER);
        foreach ($matches as $index => $match) {
            $block = str_replace($replace, $search, $match[0]);
            $token = '__PRE__' . $index;
            $found[$token] = $block;
            $html = str_replace($block, $token, $html);
        }
        $stripped = str_replace(["\r\n", "\n"], '', str_replace($replace, $search, $html));
        return str_replace(array_keys($found), array_values($found), $stripped);
    }
}
