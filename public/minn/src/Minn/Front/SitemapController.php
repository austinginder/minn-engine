<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;

/**
 * The sitemap index, its pages, and the two stylesheets.
 */
final readonly class SitemapController
{
    public function __construct(
        private Sitemaps $sitemaps,
        /** @var Closure(): Response renders the themed 404 page */
        private Closure $notFound,
    ) {
    }

    /** The sitemap index. */
    #[Route(Method::Get, '/wp-sitemap.xml', policy: new Policy(Access::Public))]
    public function sitemapIndex(Request $request): Response
    {
        return self::xml($this->sitemaps->index());
    }

    /** One sitemap page. */
    #[Route(Method::Get, '/wp-sitemap-{type:posts|taxonomies|users}-{rest:[a-z_0-9-]+}.xml', policy: new Policy(Access::Public))]
    public function sitemap(Request $request, string $type, string $rest): Response
    {
        if (!preg_match('/^(?:(.+)-)?(\d+)$/', $rest, $m)) {
            return ($this->notFound)();
        }
        $body = $this->sitemaps->page($type, $m[1], (int) $m[2]);
        return $body === null ? ($this->notFound)() : self::xml($body);
    }

    /** The sitemap stylesheet. */
    #[Route(Method::Get, '/wp-sitemap.xsl', policy: new Policy(Access::Public))]
    public function sitemapStylesheet(Request $request): Response
    {
        return self::xml(Sitemaps::stylesheet());
    }

    /** The sitemap index stylesheet. */
    #[Route(Method::Get, '/wp-sitemap-index.xsl', policy: new Policy(Access::Public))]
    public function sitemapIndexStylesheet(Request $request): Response
    {
        return self::xml(Sitemaps::indexStylesheet());
    }

    private static function xml(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/xml; charset=UTF-8'], $body);
    }
}
