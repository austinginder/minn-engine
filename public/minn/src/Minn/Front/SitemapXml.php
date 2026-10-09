<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Html;

/**
 * The two sitemap documents, index and URL set, from entry maps; one
 * builder for the engine's routes and the facade's renderer. An element
 * with nothing inside closes itself, at every level, as the reference's
 * XML writer prints it (probe sitemap-classes).
 */
final class SitemapXml
{
    /**
     * A sitemap index document.
     *
     * @param list<array<string, string|null>> $entries each a map of element name => text; null values are skipped
     */
    public static function index(array $entries, ?string $stylesheet): string
    {
        return self::document($stylesheet, self::element('sitemapindex', self::elements('sitemap', $entries), ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'));
    }

    /**
     * A sitemap urlset document.
     *
     * @param list<array<string, string|null>> $entries
     */
    public static function urlset(array $entries, ?string $stylesheet): string
    {
        return self::document($stylesheet, self::element('urlset', self::elements('url', $entries), ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'));
    }

    private static function elements(string $tag, array $entries): string
    {
        $out = '';
        foreach ($entries as $entry) {
            $fields = '';
            foreach ($entry as $name => $value) {
                if ($value !== null) {
                    $fields .= self::element((string) $name, Html::esc($value));
                }
            }
            $out .= self::element($tag, $fields);
        }
        return $out;
    }

    /** One element: closed on itself when empty. */
    private static function element(string $tag, string $inner, string $attributes = ''): string
    {
        return $inner === '' ? "<{$tag}{$attributes}/>" : "<{$tag}{$attributes}>{$inner}</{$tag}>";
    }

    private static function document(?string $stylesheet, string $body): string
    {
        $declaration = $stylesheet === null ? '' : '<?xml-stylesheet type="text/xsl" href="' . Html::attr($stylesheet) . '" ?>' . "\n";
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $declaration . $body . "\n";
    }
}
