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
    ) {
    }

    #[Route(Method::Get, '/{path*}')]
    public function show(Request $request): Response
    {
        $resolution = $this->resolver->resolve($request);
        if ($resolution->kind === Kind::Redirect) {
            return Response::redirect((string) $resolution->location, $resolution->status);
        }
        $html = $this->theme?->render($resolution, $this->renderer->bodyClasses($resolution), $this->renderer->title($resolution))
            ?? $this->renderer->render($resolution);
        return Response::html($html, $resolution->status)
            ->withHeader('X-Powered-By', 'Minn Engine/' . (defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : ''));
    }
}
