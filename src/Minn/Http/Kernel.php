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

    public function handle(Request $request): ?Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (RestError $error) {
            return Response::json($error->payload(), $error->status);
        }
    }
}
