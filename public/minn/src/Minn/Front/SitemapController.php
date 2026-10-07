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
use Minn\Runtime\Runtime;
use Minn\Theme\MainQueryBridge;

/**
 * The sitemap index, its pages, and the two stylesheets. With plugins
 * loaded they are the reference's: the request's sitemap variables stand
 * in the main query and the sitemaps server answers at template_redirect
 * (SitemapRequest), through every filter a plugin hooks, any provider it
 * registers among them; what it does not print, the theme renders (a 404,
 * or the page a stray address amounts to). Without, the engine's own.
 */
final readonly class SitemapController
{
    public function __construct(
        private Sitemaps $sitemaps,
        /** @var Closure(): Response renders the themed 404 page */
        private Closure $notFound,
        private ?MainQueryBridge $bridge = null,
        /** @var (Closure(Resolution): Response)|null renders the themed page for a resolution */
        private ?Closure $themed = null,
    ) {
    }

    /** The sitemap index. */
    #[Route(Method::Get, '/wp-sitemap.xml', policy: new Policy(Access::Public))]
    public function sitemapIndex(Request $request): Response
    {
        return $this->served(['sitemap' => 'index']) ?? self::xml($this->sitemaps->index());
    }

    /** One sitemap page: a provider's name, its subtype when it has them, and the page. */
    #[Route(Method::Get, '/wp-sitemap-{name:[a-z]+}-{rest:[a-z_0-9-]+}.xml', policy: new Policy(Access::Public))]
    public function sitemap(Request $request, string $name, string $rest): Response
    {
        if (!preg_match('/^(?:(.+)-)?(\d+)$/', $rest, $m)) {
            return ($this->notFound)();
        }
        $served = $this->served(['sitemap' => $name, 'sitemap-subtype' => $m[1], 'paged' => (int) $m[2]]);
        if ($served !== null) {
            return $served;
        }
        $body = $this->sitemaps->page($name, $m[1], (int) $m[2]);
        return $body === null ? ($this->notFound)() : self::xml($body);
    }

    /** The sitemap stylesheet. */
    #[Route(Method::Get, '/wp-sitemap.xsl', policy: new Policy(Access::Public))]
    public function sitemapStylesheet(Request $request): Response
    {
        return $this->served(['sitemap-stylesheet' => 'sitemap']) ?? self::xml(Sitemaps::stylesheet());
    }

    /** The sitemap index stylesheet. */
    #[Route(Method::Get, '/wp-sitemap-index.xsl', policy: new Policy(Access::Public))]
    public function sitemapIndexStylesheet(Request $request): Response
    {
        return $this->served(['sitemap-stylesheet' => 'index']) ?? self::xml(Sitemaps::indexStylesheet());
    }

    /** The query form (?sitemap=, ?sitemap-stylesheet=) on the front page, with plugins loaded. */
    public function queried(Request $request): Response
    {
        $vars = array_filter(['sitemap' => $request->query('sitemap'), 'sitemap-subtype' => $request->query('sitemap-subtype'), 'paged' => $request->query('paged'), 'sitemap-stylesheet' => $request->query('sitemap-stylesheet')], static fn ($value) => is_string($value) && $value !== '');
        return $this->served($vars) ?? ($this->notFound)();
    }

    /**
     * The reference's answer, with plugins loaded: what the sitemaps server
     * printed, else the theme's page on the query as it stands (a 404 when
     * the server made it one). Null without plugins.
     *
     * @param array<string, mixed> $vars
     */
    private function served(array $vars): ?Response
    {
        if (!Runtime::booted() || $this->bridge === null || $this->themed === null) {
            return null;
        }
        $printed = PrintedResponse::stand($this->bridge, $vars);
        if ($printed !== null) {
            return $printed;
        }
        return ($this->themed)(\is_404() ? Resolution::notFound() : Resolution::home());
    }

    private static function xml(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/xml; charset=UTF-8'], $body);
    }
}
