<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Blocks\Context;
use Minn\Blocks\RenderState;
use Minn\Content\Blocks;
use Minn\Content\PostFilter;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\Runtime;
use Minn\Theme\MainQueryBridge;
use Minn\Theme\NotModified;

/**
 * The feeds: the site's, the comments', a post's or an archive's by the
 * path in front of /feed/, and the ?feed= query form on any page.
 */
final readonly class FeedController
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Resolver $resolver,
        private Feeds $feeds,
        /** @var Closure(): Response renders the themed 404 page */
        private Closure $notFound,
    ) {
    }

    /** The site feed in one of its kinds. */
    #[Route(Method::Get, '/feed', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/feed/', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/feed/{kind:rss2|rss|atom|rdf}', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/feed/{kind:rss2|rss|atom|rdf}/', policy: new Policy(Access::Public))]
    public function siteFeed(Request $request, string $kind = 'rss2'): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        return $this->feed(Resolution::home(), $kind, $request);
    }

    /** The site's comments feed in one of its kinds. */
    #[Route(Method::Get, '/comments/feed', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/comments/feed/', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/comments/feed/{kind:rss2|rss|atom|rdf}', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/comments/feed/{kind:rss2|rss|atom|rdf}/', policy: new Policy(Access::Public))]
    public function commentsFeed(Request $request, string $kind = 'rss2'): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        if (!Runtime::booted()) {
            return self::feedResponse($this->feeds->comments(null, $this->permalinks->url('/comments/feed/')), 'rss2');
        }
        return $this->served(Resolution::home(), $kind, ['withcomments' => 1]);
    }

    /** A post's comment feed, or an archive's feed, by resolving the path in front of /feed/. */
    #[Route(Method::Get, '/{path*}/feed', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/{path*}/feed/', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/{path*}/feed/{kind:rss2|rss|atom|rdf}', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/{path*}/feed/{kind:rss2|rss|atom|rdf}/', policy: new Policy(Access::Public))]
    public function pathFeed(Request $request, string $path, string $kind = 'rss2'): Response
    {
        if (!str_ends_with($request->path, '/')) {
            return Response::redirect($this->permalinks->url($request->path . '/'));
        }
        $resolution = $this->resolver->resolve($request->withPath('/' . trim($path, '/') . '/'));
        return $this->feed($resolution, $kind, $request);
    }

    /** The ?feed= query form on any resolvable path: any feed a handler answers, a plugin's own included. */
    public function queryFeed(Request $request, Resolution $resolution, string $kind): Response
    {
        if (!Runtime::booted()) {
            return $this->engineFeed($resolution, in_array($kind, ['atom', 'rdf'], true) ? $kind : 'rss2', $request);
        }
        $asked = array_filter(['withcomments' => $request->query('withcomments'), 'withoutcomments' => $request->query('withoutcomments')], static fn ($value) => $value !== null && $value !== '');
        return $this->served($resolution, $kind, $asked);
    }

    /** A feed for a resolution: through WordPress's feed lifecycle when the runtime is up, the engine's own otherwise. */
    private function feed(Resolution $resolution, string $kind, Request $request): Response
    {
        if ($resolution->kind === Kind::NotFound) {
            return ($this->notFound)();
        }
        return Runtime::booted() ? $this->served($resolution, $kind, []) : $this->engineFeed($resolution, $kind === 'rss' ? 'rss2' : $kind, $request);
    }

    /**
     * A feed as the reference serves it: the main query with the feed asked
     * for (send_headers, wp and template_redirect around it), then do_feed,
     * whose handler prints the feed and its content type.
     *
     * @param array<string, mixed> $vars what the request asks of the feed besides its type
     */
    private function served(Resolution $resolution, string $kind, array $vars): Response
    {
        $level = ob_get_level();
        ob_start();
        try {
            $page = (new MainQueryBridge($this->site, $this->posts, $this->feeds->perFeed()))->stand($resolution, ['feed' => $kind] + $vars);
            // Content renders against the feed's own queried object (a category feed marks its category current), images by the page rules.
            RenderState::current()->reset();
            Blocks::renderer()->withContext(new Context($resolution, $page->posts, count($page->posts), $this->feeds->perFeed(), true));
            \do_feed();
        } catch (\Throwable $failure) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            if ($failure instanceof NotModified) {
                return new Response(304, [], '');
            }
            throw $failure;
        }
        $code = http_response_code();
        return new Response(is_int($code) && $code > 0 ? $code : 200, [], (string) ob_get_clean());
    }

    /** The engine's own rendering, for a request served without the runtime. */
    private function engineFeed(Resolution $resolution, string $kind, Request $request): Response
    {
        $self = $this->permalinks->url($request->path) . $request->queryStringWithout();
        $siteName = htmlspecialchars((string) ($this->site->option('blogname') ?? ''), ENT_QUOTES);
        $record = $resolution->record ?? [];
        $all = PostFilter::all();
        [$filter, $title] = match ($resolution->kind) {
            Kind::Home => [$all, $resolution->postsPage ? htmlspecialchars((string) $record['post_title'], ENT_QUOTES) . ' &#8211; ' . $siteName : $siteName],
            Kind::Category, Kind::Tag, Kind::Taxonomy => [$all->inTerm((int) $record['term_taxonomy_id']), htmlspecialchars((string) $record['name'], ENT_QUOTES) . ' &#8211; ' . $siteName],
            Kind::PostTypeArchive => [PostFilter::types((string) $record['name']), htmlspecialchars((string) ($record['label'] ?? ''), ENT_QUOTES) . ' &#8211; ' . $siteName],
            Kind::Author => [$all->byAuthor((int) ($record['ID'] ?? -1)), htmlspecialchars((string) ($record['display_name'] ?? $resolution->authorName), ENT_QUOTES) . ' &#8211; ' . $siteName],
            Kind::Date => [$all->between(...(Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01'])), $siteName],
            Kind::Search => [$all->matching((string) $resolution->search), $siteName],
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
        $posts = $this->posts->listing($filter, 1, $this->feeds->perFeed())->posts;
        // Images in a feed follow the page rules (eager budget, high priority first).
        RenderState::current()->reset();
        Blocks::renderer()->withContext(new Context($resolution, $posts, count($posts), $this->feeds->perFeed(), true));
        return self::feedResponse($this->feeds->posts($posts, $kind, $self, $title), $kind);
    }

    private static function feedResponse(string $body, string $kind): Response
    {
        return new Response(200, ['Content-Type' => Feeds::contentType($kind)], $body);
    }
}
