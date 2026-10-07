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
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\Runtime;
use Minn\Theme\MainQueryBridge;
use Minn\Theme\NotModified;

/**
 * The feeds: the site's, the comments', a post's or an archive's by the
 * path in front of /feed/, and the ?feed= query form on any page. Each is
 * WordPress's feed lifecycle: the main query with the feed asked for, then
 * do_feed, whose handler prints the feed and its content type.
 */
final readonly class FeedController
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Resolver $resolver,
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
        return $this->feed(Resolution::home(), $kind);
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
        if (Runtime::current()->get(PluginRules::MATCHED) === true) {
            // A plugin's rule decided the address: the feed rides along as typed, as the rule's own feed var has it.
            $typed = preg_match('#/feed/(rss2|rss|atom|rdf)/?$#', $request->path, $m) === 1 ? $m[1] : 'feed';
            Runtime::current()->set(PluginRules::STATE, PluginRules::stashed() + ['feed' => $typed]);
            if ($resolution->kind !== Kind::NotFound) {
                return $this->served($resolution, $kind, ['feed' => $typed]);
            }
        }
        return $this->feed($resolution, $kind);
    }

    /** The ?feed= query form on any resolvable path: any feed a handler answers, a plugin's own included. */
    public function queryFeed(Request $request, Resolution $resolution, string $kind): Response
    {
        $asked = array_filter(['withcomments' => $request->query('withcomments'), 'withoutcomments' => $request->query('withoutcomments')], static fn ($value) => $value !== null && $value !== '');
        return $this->served($resolution, $kind, $asked);
    }

    /** A feed for a resolution, or the themed 404 when the path names nothing. */
    private function feed(Resolution $resolution, string $kind): Response
    {
        return $resolution->kind === Kind::NotFound ? ($this->notFound)() : $this->served($resolution, $kind, []);
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
            $page = (new MainQueryBridge($this->site, $this->posts, $this->perFeed()))->stand($resolution, $vars + ['feed' => $kind]);
            // Content renders against the feed's own queried object (a category feed marks its category current), images by the page rules.
            RenderState::current()->reset();
            Blocks::renderer()->withContext(new Context($resolution, $page->posts, count($page->posts), $this->perFeed(), true));
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

    /** How many items a feed carries (posts_per_rss). */
    private function perFeed(): int
    {
        return max(1, (int) ($this->site->option('posts_per_rss') ?? 10));
    }
}
