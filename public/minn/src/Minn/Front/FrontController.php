<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Theme\ClassicRenderer;
use Minn\Theme\PageRenderer;
use Minn\Cron\Cron;

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
        private ?ProbeController $probes = null,
        private ?Cron $cron = null,
        private ?ClassicRenderer $classic = null,
    ) {
    }

    /** The themed (or interim) 404 page. */
    public function notFound(): Response
    {
        $resolution = Resolution::notFound();
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->classic?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, 404);
    }

    #[Route(Method::Any, '/{path*}')]
    public function show(Request $request): Response
    {
        // A scheduled post whose time has come goes live before the page is built.
        if ($this->cron !== null && !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) && $this->cron->due()) {
            $this->cron->run();
        }
        $resolution = $this->resolver->resolve($request, $request->method->canonicalRedirects());
        if ($resolution->kind === Kind::Redirect) {
            return Response::redirect((string) $resolution->location, $resolution->status);
        }
        if ($this->probes !== null && $request->has('feed') && $resolution->kind !== Kind::NotFound) {
            return $this->probes->queryFeed($request, $resolution, (string) $request->query('feed', 'rss2'));
        }
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->classic?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, $resolution->status)
            ->withHeader('Link', '<' . $this->resolver->permalinks()->url('/wp-json/') . '>; rel="https://api.w.org/"')
            ->withHeader('X-Powered-By', 'Minn');
    }
}
