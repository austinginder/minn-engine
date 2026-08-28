<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Blocks\Context;
use Minn\Blocks\RenderState;
use Minn\Content\Blocks;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * The surface monitors, crawlers, and hosting checks hit that is not a
 * page: feeds, sitemaps, robots.txt, the XML-RPC and cron endpoints, and
 * the admin entry point.
 */
final readonly class ProbeController
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Resolver $resolver,
        private Feeds $feeds,
        private Sitemaps $sitemaps,
        /** @var Closure(): Response renders the themed 404 page */
        private Closure $notFound,
    ) {
    }

    #[Route(Method::Get, '/robots.txt')]
    public function robots(Request $request): Response
    {
        $public = ($this->site->option('blog_public') ?? '1') !== '0';
        $body = $public
            ? "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . $this->permalinks->url('/wp-sitemap.xml') . "\n"
            : "User-agent: *\nDisallow: /\n";
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    /** XML-RPC is not served; GET answers the way the reference does, POST is refused outright. */
    #[Route(Method::Any, '/xmlrpc.php')]
    public function xmlrpc(Request $request): Response
    {
        if ($request->method === Method::Post) {
            return new Response(403, ['Content-Type' => 'text/plain;charset=UTF-8'], 'XML-RPC services are disabled on this site.');
        }
        return new Response(405, ['Content-Type' => 'text/plain;charset=UTF-8', 'Allow' => 'POST'], 'XML-RPC server accepts POST requests only.');
    }

    #[Route(Method::Any, '/wp-cron.php')]
    public function cron(Request $request): Response
    {
        return Response::html('');
    }

    /** The admin is Minn Admin; the reference's admin path lands there. */
    #[Route(Method::Get, '/wp-admin')]
    #[Route(Method::Get, '/wp-admin/{rest*}')]
    public function admin(Request $request): Response
    {
        return Response::redirect($this->permalinks->url('/minn-admin/'), 302);
    }

    #[Route(Method::Get, '/favicon.ico')]
    public function favicon(Request $request): Response
    {
        $icon = (int) ($this->site->option('site_icon') ?? 0);
        $file = $icon > 0 ? $this->posts->meta($icon, '_wp_attached_file') : null;
        if ($file === null) {
            return new Response(404);
        }
        return Response::redirect($this->permalinks->url('/wp-content/uploads/' . $file), 302);
    }

    #[Route(Method::Get, '/wp-sitemap.xml')]
    public function sitemapIndex(Request $request): Response
    {
        return self::xml($this->sitemaps->index());
    }

    #[Route(Method::Get, '/wp-sitemap-{type:posts|taxonomies|users}-{rest:[a-z_0-9-]+}.xml')]
    public function sitemap(Request $request, string $type, string $rest): Response
    {
        if (!preg_match('/^(?:(.+)-)?(\d+)$/', $rest, $m)) {
            return ($this->notFound)();
        }
        $body = $this->sitemaps->page($type, $m[1], (int) $m[2]);
        return $body === null ? ($this->notFound)() : self::xml($body);
    }

    #[Route(Method::Get, '/wp-sitemap.xsl')]
    public function sitemapStylesheet(Request $request): Response
    {
        return self::xml(Sitemaps::stylesheet(false));
    }

    #[Route(Method::Get, '/wp-sitemap-index.xsl')]
    public function sitemapIndexStylesheet(Request $request): Response
    {
        return self::xml(Sitemaps::stylesheet(true));
    }

    #[Route(Method::Get, '/feed')]
    #[Route(Method::Get, '/feed/')]
    #[Route(Method::Get, '/feed/{kind:rss2|rss|atom|rdf}')]
    #[Route(Method::Get, '/feed/{kind:rss2|rss|atom|rdf}/')]
    public function siteFeed(Request $request, string $kind = 'rss2'): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        return $this->feed(Resolution::home(), $kind === 'rss' ? 'rss2' : $kind, $request);
    }

    #[Route(Method::Get, '/comments/feed')]
    #[Route(Method::Get, '/comments/feed/')]
    public function commentsFeed(Request $request): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        return self::feedResponse($this->feeds->comments(null, $this->permalinks->url('/comments/feed/')), 'rss2');
    }

    /** A post's comment feed, or an archive's feed, by resolving the path in front of /feed/. */
    #[Route(Method::Get, '/{path*}/feed')]
    #[Route(Method::Get, '/{path*}/feed/')]
    #[Route(Method::Get, '/{path*}/feed/{kind:rss2|rss|atom|rdf}')]
    #[Route(Method::Get, '/{path*}/feed/{kind:rss2|rss|atom|rdf}/')]
    public function pathFeed(Request $request, string $path, string $kind = 'rss2'): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        $resolution = $this->resolver->resolve($request->withPath('/' . trim($path, '/') . '/'));
        return $this->feed($resolution, $kind === 'rss' ? 'rss2' : $kind, $request);
    }

    /** The ?feed= query form on any resolvable path. */
    public function queryFeed(Request $request, Resolution $resolution, string $kind): Response
    {
        return $this->feed($resolution, in_array($kind, ['atom', 'rdf'], true) ? $kind : 'rss2', $request);
    }

    private function feed(Resolution $resolution, string $kind, Request $request): Response
    {
        $self = $this->permalinks->url($request->path) . $request->queryStringWithout();
        $siteName = htmlspecialchars((string) ($this->site->option('blogname') ?? ''), ENT_QUOTES);
        $record = $resolution->record ?? [];
        [$filter, $title] = match ($resolution->kind) {
            Kind::Home => [[], $siteName],
            Kind::Category, Kind::Tag => [['term' => (int) $record['term_taxonomy_id']], htmlspecialchars((string) $record['name'], ENT_QUOTES) . ' &#8211; ' . $siteName],
            Kind::Author => [['author' => (int) ($record['ID'] ?? -1)], htmlspecialchars((string) ($record['display_name'] ?? $resolution->authorName), ENT_QUOTES) . ' &#8211; ' . $siteName],
            Kind::Date => [array_combine(['from', 'to'], Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01']), $siteName],
            Kind::Search => [['search' => (string) $resolution->search], $siteName],
            Kind::Single, Kind::Page => [null, ''],
            default => [null, null],
        };
        if ($title === null) {
            return ($this->notFound)();
        }
        if ($filter === null) {
            return self::feedResponse($this->feeds->comments($record, $self), 'rss2');
        }
        // Feeds run in date order; sticky posts get no special place. Content
        // renders against the feed's own queried object (a category feed marks
        // its category current).
        $posts = $this->posts->listing($filter, 1, $this->feeds->perFeed())['posts'];
        // Images in a feed follow the page rules (eager budget, high priority first).
        RenderState::reset();
        Blocks::renderer()->withContext(new Context($resolution, $posts, count($posts), $this->feeds->perFeed(), true));
        return self::feedResponse($this->feeds->posts($posts, $kind, $self, $title), $kind);
    }

    private static function feedResponse(string $body, string $kind): Response
    {
        return new Response(200, ['Content-Type' => Feeds::contentType($kind)], $body);
    }

    private static function xml(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/xml; charset=UTF-8'], $body);
    }
}
