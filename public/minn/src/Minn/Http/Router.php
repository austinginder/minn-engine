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
     * @param Envelope|null $envelope what runs around a matched route instead of the router throwing its refusals itself
     */
    public function __construct(private readonly Closure $gate, private readonly ?Closure $check = null, private readonly ?Envelope $envelope = null)
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
     * every route matching the path (a route with no captures that names
     * the path exactly claims it alone), its policy judged for the caller
     * without dispatching. A route that states no policy counts for GET
     * only, until it states one. Empty when nothing matched or nothing
     * is allowed, and the header is then left out, as the reference does.
     *
     * @return list<string> in the reference's order
     */
    public function allowed(Request $request): array
    {
        $allowed = [];
        // A route spelled without captures claims its own path: /templates/lookup is not also a template id.
        $literal = null;
        foreach ($this->routes as ['route' => $route]) {
            if (self::literal($route) === $request->path) {
                $literal = $request->path;
                break;
            }
        }
        foreach ($this->routes as ['route' => $route]) {
            $method = $route->method;
            if ($method === Method::Any || $method === Method::Head || !preg_match($route->regex(), $request->path, $captures) || ($literal !== null && self::literal($route) !== $literal)) {
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

    /**
     * The routes a request's method and path match, policies unjudged, in
     * the order dispatch tries them, with their captures: what a batch
     * reads to tell a route that does not take part from no route at all.
     *
     * @return list<array{0: Route, 1: array<string, string>}>
     */
    public function matching(Request $request): array
    {
        $out = [];
        foreach ($this->routes as ['route' => $route]) {
            if ($request->method->matches($route->method) && preg_match($route->regex(), $request->path, $captures)) {
                $out[] = [$route, array_filter($captures, is_string(...), ARRAY_FILTER_USE_KEY)];
            }
        }
        return $out;
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
            $arguments = self::arguments($method, $captures);
            $invoke = static fn (): Response => $method->invoke($handler, $request, ...$arguments);
            try {
                $response = $this->envelope === null
                    ? $this->answer($route, $request, $captures, $invoke)
                    : $this->enveloped(new Matched($route, $handler, $method->getName(), $captures, $this->methodsOf($handler, $method->getName(), $route->pattern, $request->method)), $request, $invoke);
            } catch (RouteMiss) {
                // The policy or the handler declined the route (a {base} naming no declared type): the next one gets its turn.
                continue;
            }
            return $response;
        }
        return null;
    }

    /**
     * The captures a handler takes, by parameter name; a capture spelled as
     * the reference spells it (user_id) binds to its camelCase parameter.
     *
     * @param array<string, string> $captures
     * @return array<string, string>
     */
    private static function arguments(ReflectionMethod $method, array $captures): array
    {
        $wanted = array_map(static fn ($p) => $p->getName(), $method->getParameters());
        $arguments = [];
        foreach ($captures as $name => $value) {
            $camel = lcfirst(str_replace('_', '', ucwords($name, '_')));
            if (in_array($camel, $wanted, true)) {
                $arguments[$camel] = $value;
            }
        }
        return $arguments;
    }

    /**
     * @param array<string, string> $captures
     * @param Closure(): Response $invoke
     */
    private function answer(Route $route, Request $request, array $captures, Closure $invoke): Response
    {
        if ($this->check !== null) {
            ($this->check)($route, $request, $captures);
        }
        if ($route->policy !== null && !$route->policy->isPublic()) {
            ($this->gate)($route->policy, $request, $captures);
        }
        return $invoke();
    }

    /**
     * The refusals judged up front and handed over unthrown. The policy is
     * judged even past a bad argument, because the envelope may clear that;
     * a policy that declines the route throws before anything hears of it.
     *
     * @param Closure(): Response $invoke
     */
    private function enveloped(Matched $matched, Request $request, Closure $invoke): Response
    {
        $invalid = null;
        try {
            if ($this->check !== null) {
                ($this->check)($matched->route, $request, $matched->captures);
            }
        } catch (RestError $error) {
            $invalid = $error;
        }
        $refusal = null;
        try {
            if ($matched->route->policy !== null && !$matched->route->policy->isPublic()) {
                ($this->gate)($matched->route->policy, $request, $matched->captures);
            }
        } catch (RestError $error) {
            $refusal = $error;
        }
        return $this->envelope->around($matched, $request, $invalid, $refusal, $invoke);
    }

    /**
     * The methods one handler answers on one pattern alongside the request's,
     * grouped as the reference registers them: reading alone, deleting
     * alone, and creating or editing together (POST, PUT, PATCH).
     *
     * @return list<string>
     */
    private function methodsOf(object $handler, string $name, string $pattern, Method $asked): array
    {
        $group = static fn (Method $m): string => match ($m) { Method::Get, Method::Head, Method::Any => 'read', Method::Delete => 'delete', default => 'write' };
        $methods = [];
        foreach ($this->routes as ['route' => $route, 'handler' => $other, 'method' => $method]) {
            if ($other === $handler && $method->getName() === $name && $route->pattern === $pattern && $route->method !== Method::Head && $group($route->method) === $group($asked)) {
                $methods[] = $route->method === Method::Any ? 'GET' : $route->method->value;
            }
        }
        return array_values(array_unique($methods));
    }

    /** The one path a route spells, a capture it fixes ({base:media}) read as its word; null for a route with a real capture. */
    private static function literal(Route $route): ?string
    {
        $path = (string) preg_replace('/\{\w+:(\w+)\}/', '$1', $route->pattern);
        return str_contains($path, '{') ? null : $path;
    }
}
