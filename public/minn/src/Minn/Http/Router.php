<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use Minn\RestError;
use ReflectionClass;
use ReflectionMethod;

/**
 * Matches a request to a #[Route] on one of the registered handler
 * objects, enforces the declared capability, and invokes the method with
 * the request plus the named pattern captures.
 */
final class Router
{
    /** @var list<array{route: Route, handler: object, method: ReflectionMethod}> */
    private array $routes = [];

    /** @param Closure(string $cap, Request $request): void $gate throws RestError when the capability is missing */
    public function __construct(private readonly ?Closure $gate = null)
    {
    }

    /** Registers every #[Route] method of the given handlers; returns the router for chaining. */
    public function register(object ...$handlers): self
    {
        foreach ($handlers as $handler) {
            $class = new ReflectionClass($handler);
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $this->routes[] = [
                        'route' => $attribute->newInstance(),
                        'handler' => $handler,
                        'method' => $method,
                    ];
                }
            }
        }
        return $this;
    }

    /** The registered routes, for an index: pattern => methods. @return array<string, list<string>> */
    public function routes(): array
    {
        $index = [];
        foreach ($this->routes as ['route' => $route]) {
            $index[$route->pattern][] = $route->method->value;
        }
        return $index;
    }

    /** Null when nothing matched, so the caller can fall through. */
    public function dispatch(Request $request): ?Response
    {
        foreach ($this->routes as ['route' => $route, 'handler' => $handler, 'method' => $method]) {
            if (!$request->method->matches($route->method)) {
                continue;
            }
            if (!preg_match($route->regex(), $request->path, $captures)) {
                continue;
            }
            if ($route->cap !== null) {
                ($this->gate ?? throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403))
                    ($route->cap, $request);
            }
            $wanted = array_map(static fn ($p) => $p->getName(), $method->getParameters());
            $arguments = array_intersect_key(
                array_filter($captures, is_string(...), ARRAY_FILTER_USE_KEY),
                array_flip($wanted),
            );
            $response = $method->invoke($handler, $request, ...$arguments);
            return $request->method === Method::Head ? $response->withoutBody() : $response;
        }
        return null;
    }
}
