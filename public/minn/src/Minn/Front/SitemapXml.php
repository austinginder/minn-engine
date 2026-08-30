<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Support\Html;

/** The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer. */
final class SitemapXml
{
    /** @param list<array<string, string|null>> $entries each a map of element name => text; null values are skipped */
    public static function index(array $entries, ?string $stylesheet): string
    {
        return self::document($stylesheet, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . self::elements('sitemap', $entries) . '</sitemapindex>');
    }

    /** @param list<array<string, string|null>> $entries */
    public static function urlset(array $entries, ?string $stylesheet): string
    {
        return self::document($stylesheet, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . self::elements('url', $entries) . '</urlset>');
    }

    private static function elements(string $tag, array $entries): string
    {
        $out = '';
        foreach ($entries as $entry) {
            $out .= "<{$tag}>";
            foreach ($entry as $name => $value) {
                if ($value !== null) {
                    $out .= '<' . $name . '>' . Html::esc($value) . '</' . $name . '>';
                }
            }
            $out .= "</{$tag}>";
        }
        return $out;
    }

    private static function document(?string $stylesheet, string $body): string
    {
        $declaration = $stylesheet === null ? '' : '<?xml-stylesheet type="text/xsl" href="' . Html::attr($stylesheet) . '" ?>' . "\n";
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $declaration . $body . "\n";
    }
}
