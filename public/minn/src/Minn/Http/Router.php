<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Minn\RestError;

/**
 * Matches a request to a #[Route] on one of the registered handler
 * objects, has the check refuse a bad argument, has the gate judge the
 * route's policy, and invokes the method with the request plus the named
 * pattern captures. That order is the reference's: an invalid parameter is
 * answered before the caller is looked at. The gate is the one
 * thing a router cannot be built without: a policy nobody judges is a
 * route nobody may call.
 */
final class Router
{
    /** @var list<array{route: Route, handler: object, method: ReflectionMethod}> */
    private array $routes = [];

    /**
     * @param Closure(Policy $policy, Request $request, array<string, string> $captures): void $gate throws when the policy refuses the caller
     * @param Closure(Route $route, Request $request): void|null $check throws when a declared argument is missing or invalid; judged before the gate
     */
    public function __construct(private readonly Closure $gate, private readonly ?Closure $check = null)
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

    /** The routes that list in an index: pattern => methods. @return array<string, list<string>> */
    public function routes(): array
    {
        $index = [];
        foreach ($this->routes as ['route' => $route]) {
            if ($route->index) {
                $index[$route->pattern][] = $route->method->value;
            }
        }
        return $index;
    }

    /**
     * Every registered route as a row: what it is, who it is for, what it
     * takes. The same rows Rest\Catalogue reads from the classes alone.
     *
     * @return list<RouteRow>
     */
    public function table(): array
    {
        $rows = [];
        foreach ($this->routes as ['route' => $route, 'method' => $method]) {
            $rows[] = RouteRow::of($route, $method);
        }
        return $rows;
    }

    /**
     * The methods the caller may use on this path, for the Allow header:
     * every route matching the path, its policy judged for the caller
     * without dispatching. A route that states no policy counts for GET
     * only, until it states one. Empty when nothing matched or nothing
     * is allowed, and the header is then left out, as the reference does.
     *
     * @return list<string> in the reference's order
     */
    public function allowed(Request $request): array
    {
        $allowed = [];
        foreach ($this->routes as ['route' => $route]) {
            $method = $route->method;
            if ($method === Method::Any || $method === Method::Head || !preg_match($route->regex(), $request->path, $captures)) {
                continue;
            }
            $captures = array_filter($captures, is_string(...), ARRAY_FILTER_USE_KEY);
            $allowed[] = match (true) {
                $route->policy === null => $method === Method::Get ? 'GET' : null,
                $route->policy->isPublic() => $method->value,
                default => $this->admits($route->policy, $request, $captures) ? $method->value : null,
            };
        }
        $order = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        return array_values(array_intersect($order, array_unique(array_filter($allowed))));
    }

    /** @param array<string, string> $captures */
    private function admits(Policy $policy, Request $request, array $captures): bool
    {
        try {
            ($this->gate)($policy, $request, $captures);
            return true;
        } catch (RestError | RouteMiss) {
            return false;
        }
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
            if ($this->check !== null) {
                ($this->check)($route, $request);
            }
            try {
                if ($route->policy !== null && !$route->policy->isPublic()) {
                    ($this->gate)($route->policy, $request, $captures);
                }
            } catch (RouteMiss) {
                // The policy declined the route (a {base} naming no declared type): the next one gets its turn.
                continue;
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
