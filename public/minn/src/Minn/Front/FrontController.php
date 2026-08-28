<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Theme\PageRenderer;

/**
 * The public site. One catch-all route: resolve the URL, then either
 * redirect or render, through the active block theme when there is one
 * and the interim template otherwise.
 */
final readonly class FrontController
{
    public function __construct(
        private Resolver $resolver,
        private Renderer $renderer,
        private ?PageRenderer $theme = null,
        private ?ProbeController $probes = null,
    ) {
    }

    /** The themed (or interim) 404 page. */
    public function notFound(): Response
    {
        $resolution = Resolution::notFound();
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, 404);
    }

    #[Route(Method::Get, '/{path*}')]
    public function show(Request $request): Response
    {
        $resolution = $this->resolver->resolve($request);
        if ($resolution->kind === Kind::Redirect) {
            return Response::redirect((string) $resolution->location, $resolution->status);
        }
        if ($this->probes !== null && $request->has('feed') && $resolution->kind !== Kind::NotFound) {
            return $this->probes->queryFeed($request, $resolution, (string) $request->query('feed', 'rss2'));
        }
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, $resolution->status)
            ->withHeader('Link', '<' . $this->resolver->permalinks()->url('/wp-json/') . '>; rel="https://api.w.org/"')
            ->withHeader('X-Powered-By', 'Minn Engine');
    }
}
