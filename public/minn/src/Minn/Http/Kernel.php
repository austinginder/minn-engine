<?php

declare(strict_types=1);

namespace Minn\Http;

use Minn\RestError;

/**
 * The edge. Turns a request into a response through the router and turns
 * a RestError thrown anywhere underneath into the WordPress error shape.
 */
final readonly class Kernel
{
    public function __construct(private Router $router)
    {
    }

    /** Routes the request; a thrown failure becomes its response, and null means no route matched. */
    public function handle(Request $request): ?Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (RestError $error) {
            $response = Response::json($error->payload(), $error->status);
        }
        return $response === null ? null : self::harden($request, $response);
    }

    /**
     * Headers every response carries: no content sniffing anywhere; the
     * sign-in and admin pages refuse framing and keep their referrer to
     * themselves, as the reference's do.
     */
    private static function harden(Request $request, Response $response): Response
    {
        if (!isset($response->headers['X-Content-Type-Options'])) {
            $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        }
        if (str_starts_with($request->path, '/wp-login.php') || str_starts_with($request->path, '/minn-admin')) {
            $response = $response
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0, no-store, private');
        }
        return $response;
    }
}
