<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * What the sitemaps share with WP_Sitemaps, which serves them: the two
 * stylesheets browsers get when they open a sitemap, their CSS
 * (wp_sitemaps_stylesheet_css filters it), and the date form an entry's
 * lastmod takes.
 */
final class Sitemaps
{
    /** The stylesheets' own CSS (wp_sitemaps_stylesheet_css filters it when plugins are loaded). */
    public const CSS = 'body{font:15px/1.5 sans-serif;margin:2em}table{border-collapse:collapse}td{padding:.35em 1em .35em 0;border-bottom:1px solid #ddd}';

    /** The engine's own stylesheet for browsers that open a sitemap. */
    public static function stylesheet(string $css = self::CSS): string
    {
        return self::xsl($css, '<xsl:for-each select="sitemap:urlset/sitemap:url"><tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td><xsl:value-of select="sitemap:lastmod"/></td></tr></xsl:for-each>');
    }

    /** The stylesheet the sitemap index links: one column, the sitemaps. */
    public static function indexStylesheet(string $css = self::CSS): string
    {
        return self::xsl($css, '<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap"><tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td></tr></xsl:for-each>');
    }

    private static function xsl(string $css, string $rows): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" exclude-result-prefixes="sitemap">' . "\n"
            . '<xsl:output method="html" encoding="UTF-8" indent="yes"/>' . "\n"
            . '<xsl:template match="/"><html><head><title>XML Sitemap</title>'
            . '<style>' . $css . '</style>'
            . '</head><body><h1>XML Sitemap</h1><table>' . $rows . '</table></body></html></xsl:template>' . "\n"
            . '</xsl:stylesheet>' . "\n";
    }

    /** A GMT date as a sitemap dates it (W3C, UTC). */
    public static function w3c(string $gmt): string
    {
        return gmdate('Y-m-d\TH:i:s', (int) strtotime($gmt . ' UTC')) . '+00:00';
    }
}
