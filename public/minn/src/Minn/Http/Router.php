<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use ReflectionClass;
use ReflectionMethod;

/**
 * Matches a request to a #[Route] on one of the registered handler
 * objects, has the gate judge the route's policy, and invokes the method
 * with the request plus the named pattern captures. The gate is the one
 * thing a router cannot be built without: a policy nobody judges is a
 * route nobody may call.
 */
final class Router
{
    /** @var list<array{route: Route, handler: object, method: ReflectionMethod}> */
    private array $routes = [];

    /** @param Closure(Policy $policy, Request $request, array<string, string> $captures): void $gate throws when the policy refuses the caller */
    public function __construct(private readonly Closure $gate)
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

    /**
     * Every registered route with its policy, for the docs and the ratchet.
     *
     * @return list<array{method: string, pattern: string, policy: ?Policy, handler: string}>
     */
    public function table(): array
    {
        $rows = [];
        foreach ($this->routes as ['route' => $route, 'handler' => $handler, 'method' => $method]) {
            $rows[] = ['method' => $route->method->value, 'pattern' => $route->pattern, 'policy' => $route->policy, 'handler' => $handler::class . '::' . $method->getName()];
        }
        return $rows;
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
            $captures = array_filter($captures, is_string(...), ARRAY_FILTER_USE_KEY);
            if ($route->policy !== null && !$route->policy->isPublic()) {
                ($this->gate)($route->policy, $request, $captures);
            }
            $wanted = array_map(static fn ($p) => $p->getName(), $method->getParameters());
            $arguments = array_intersect_key($captures, array_flip($wanted));
            try {
                $response = $method->invoke($handler, $request, ...$arguments);
            } catch (RouteMiss) {
                // The handler declined: the next matching route gets its turn.
                continue;
            }
            return $request->method === Method::Head ? $response->withoutBody() : $response;
        }
        return null;
    }
}
