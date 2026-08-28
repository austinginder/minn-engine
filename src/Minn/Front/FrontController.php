<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * The public site. One catch-all route: resolve the URL, then either
 * redirect or render.
 */
final readonly class FrontController
{
    public function __construct(
        private Resolver $resolver,
        private Renderer $renderer,
    ) {
    }

    #[Route(Method::Get, '/{path*}')]
    public function show(Request $request): Response
    {
        $resolution = $this->resolver->resolve($request);
        if ($resolution->kind === Kind::Redirect) {
            return Response::redirect((string) $resolution->location, $resolution->status);
        }
        return Response::html($this->renderer->render($resolution), $resolution->status)
            ->withHeader('X-Powered-By', 'Minn Engine/' . (defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : ''));
    }
}
