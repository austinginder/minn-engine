<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\Runtime;
use Minn\Theme\Printed;

/**
 * A sitemap request at template_redirect, as the reference's sitemaps
 * server answers it: a sitemap address asked for another way (the query
 * form, page 0) moves to its own; with the sitemaps off, or a subtype or
 * page that has nothing, the request is a 404 the theme renders; otherwise
 * the stylesheet, the index or the provider's page is printed and the
 * request ends. A provider nobody registered leaves the request alone.
 */
final class SitemapRequest
{
    /** The canonical step: a sitemap asked for by another address is sent to its own. */
    public static function canonical(): void
    {
        $sitemap = (string) \get_query_var('sitemap');
        $request = Runtime::current()->request;
        if ($sitemap === '' || \is_404() || $request === null) {
            return;
        }
        $url = \get_sitemap_url($sitemap, (string) \get_query_var('sitemap-subtype'), (int) \get_query_var('paged'));
        $wanted = is_string($url) ? (string) parse_url($url, PHP_URL_PATH) : '';
        $queryForm = parse_url((string) $url, PHP_URL_QUERY) === null && $request->has('sitemap');
        if ($wanted === '' || ($wanted === $request->path && !$queryForm)) {
            return;
        }
        \wp_redirect((string) $url, 301);
        throw new Printed();
    }

    /** What the sitemaps server does with the request (render_sitemaps). */
    public static function serve(\WP_Sitemaps $server): void
    {
        $sitemap = (string) \sanitize_text_field(\get_query_var('sitemap'));
        $stylesheet = (string) \sanitize_text_field(\get_query_var('sitemap-stylesheet'));
        if ($sitemap === '' && $stylesheet === '') {
            return;
        }
        if (!$server->sitemaps_enabled()) {
            self::notFound();
            return;
        }
        if ($stylesheet !== '') {
            (new \WP_Sitemaps_Stylesheet())->render_stylesheet($stylesheet);
            throw new Printed();
        }
        if ($sitemap === 'index') {
            $server->renderer->render_index($server->index->get_sitemap_list());
            throw new Printed();
        }
        self::page($server, $sitemap, (string) \sanitize_text_field(\get_query_var('sitemap-subtype')));
    }

    /** One provider's page: printed, or a 404 when the subtype is not the provider's or the page has nothing. */
    private static function page(\WP_Sitemaps $server, string $sitemap, string $subtype): void
    {
        $provider = $server->registry->get_provider($sitemap);
        if (!$provider instanceof \WP_Sitemaps_Provider) {
            return;
        }
        if ($subtype !== '' && !isset($provider->get_object_subtypes()[$subtype])) {
            self::notFound();
            return;
        }
        $urls = $provider->get_url_list(max(1, (int) \absint(\get_query_var('paged'))), $subtype);
        if (empty($urls)) {
            self::notFound();
            return;
        }
        $server->renderer->render_sitemap($urls);
        throw new Printed();
    }

    /** The main query made a 404, which the theme renders. */
    private static function notFound(): void
    {
        $GLOBALS['wp_query']->set_404();
        \status_header(404);
    }
}
