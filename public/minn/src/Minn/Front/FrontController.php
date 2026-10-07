<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Theme\ClassicRenderer;
use Minn\Theme\PageRenderer;
use Minn\Cron\Cron;
use Minn\Runtime\Runtime;
use Minn\Theme\EmbedRenderer;

/**
 * The public site. One catch-all route: resolve the URL, then either
 * redirect or render, through the active block theme when there is one,
 * the classic PHP template runner when the theme is classic, and the
 * interim template otherwise.
 */
final readonly class FrontController
{
    public function __construct(
        private Resolver $resolver,
        private Renderer $renderer,
        private ?PageRenderer $theme = null,
        private ?FeedController $feeds = null,
        private ?Cron $cron = null,
        private ?ClassicRenderer $classic = null,
        private ?SitemapController $sitemaps = null,
        private ?EmbedRenderer $embeds = null,
    ) {
    }

    /** The themed (or interim) 404 page. */
    public function notFound(): Response
    {
        return $this->themed(Resolution::notFound());
    }

    /** The themed (or interim) page for a resolution, under its status. */
    public function themed(Resolution $resolution): Response
    {
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->classic?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, $resolution->status);
    }

    /** The public page for any path; when scheduled work is due, the run follows the response. */
    #[Route(Method::Any, '/{path*}', policy: new Policy(Access::Public))]
    public function show(Request $request): Response
    {
        $response = $this->page($request);
        if ($this->cron === null || (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) || !$this->cron->due()) {
            return $response;
        }
        // The reference spawns a background request for what is due; the
        // engine does the same work in this process once the page is sent.
        $cron = $this->cron;
        return $response->afterSend(static function () use ($cron): void {
            $cron->run();
        });
    }

    private function page(Request $request): Response
    {
        $resolution = $this->resolver->resolve($request);
        if ($resolution->kind === Kind::Redirect) {
            return Response::redirect((string) $resolution->location, $resolution->status);
        }
        if ($this->embeds !== null && Runtime::booted() && EmbedRenderer::asked($request, $resolution)) {
            $core = $this->renderer->bodyClasses($resolution);
            $classes = Runtime::current()->get('block_theme', false) ? $this->theme?->themeClasses($resolution, $core) : $this->classic?->themeClasses($resolution, $core);
            return $this->embeds->render($resolution, $classes ?? $core)->withHeader('X-Powered-By', 'Minn');
        }
        if ($this->sitemaps !== null && Runtime::booted() && ($request->has('sitemap') || $request->has('sitemap-stylesheet'))) {
            return $this->sitemaps->queried($request);
        }
        if ($this->feeds !== null && $request->has('feed') && $resolution->kind !== Kind::NotFound) {
            return $this->feeds->queryFeed($request, $resolution, (string) $request->query('feed', 'rss2'));
        }
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->classic?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        $response = Response::html($html, $resolution->status)->withHeader('X-Powered-By', 'Minn');
        // Once the front-end steps ran, the template_redirect actions sent the Link headers (a plugin may have removed them).
        return Runtime::booted() && Runtime::current()->get('front_lifecycle') === true
            ? $response
            : $response->withHeader('Link', '<' . $this->resolver->permalinks()->url('/wp-json/') . '>; rel="https://api.w.org/"');
    }
}
