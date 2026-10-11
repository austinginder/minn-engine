<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Router;

/**
 * The routes the engine serves, described in the reference's shape for the
 * index and for an OPTIONS request: every route's namespace, its methods
 * and the parameters it declares, gathered per concrete route from the
 * attributes so a client learns what really works on it, with the
 * namespaces they fall under. Routes are keyed as the reference spells them
 * ('/wp/v2/posts/(?P<id>[\d]+)').
 */
final readonly class RouteCatalogue
{
    /** The routes the reference's server registers itself, outside any namespace. */
    private const SERVER_ROUTES = ['/batch/v1'];

    public function __construct(private Router $router, private Types $types, private RestUrl $url)
    {
    }

    /**
     * Every route the engine serves, the root and each namespace's index
     * among them, and the namespaces.
     *
     * @return array{namespaces: list<string>, routes: array<string, array<string, mixed>>}
     */
    public function all(): array
    {
        $bases = $this->types->routeBases();
        $routes = ['/' => ['namespace' => '', 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/')]]]]];
        $namespaces = [];
        $arguments = [];
        foreach ($this->router->table() as $row) {
            foreach (EngineRoutes::forms($row->pattern, $bases) as $form) {
                $arguments[$form] = array_merge($arguments[$form] ?? [], $row->argsFor($form));
            }
        }
        foreach ($this->router->routes() as $pattern => $methods) {
            foreach (EngineRoutes::forms($pattern, $bases) as $route) {
                if ($route === '/') {
                    continue;
                }
                $namespace = self::namespaceOf($route);
                if ($namespace !== '' && !in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                    $routes['/' . $namespace] = ['namespace' => $namespace, 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['namespace' => ['default' => $namespace, 'required' => false], 'context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/' . $namespace)]]]];
                }
                $methods = array_map(static fn (string $m) => $m === '*' ? 'GET' : $m, $methods);
                // Two patterns can make one route (a list's GET and its create's POST): its methods are both's.
                $methods = array_values(array_unique([...($routes[$route]['methods'] ?? []), ...$methods]));
                $routes[$route] = ['namespace' => $namespace, 'methods' => $methods, 'endpoints' => [['methods' => $methods, 'args' => $arguments[$route] ?? []]]];
                if (!str_contains($route, '(?P<')) {
                    $routes[$route]['_links'] = ['self' => [['href' => $this->url->to($route)]]];
                }
            }
        }
        return ['namespaces' => $namespaces, 'routes' => $routes];
    }

    /**
     * The namespace a route is listed under: its first two segments when the
     * second is a version. The routes the reference's server registers
     * itself, the batch route among them, belong to none, so none is listed
     * for them and their path answers no namespace index.
     */
    public static function namespaceOf(string $route): string
    {
        if (in_array($route, self::SERVER_ROUTES, true)) {
            return '';
        }
        return preg_match('#^/([^/]+/(?:v\d+|\d+\.\d+))#', $route, $m) === 1 ? $m[1] : '';
    }

    /**
     * The first route whose pattern takes the path, as the reference finds
     * the route an OPTIONS request describes; null when none does.
     *
     * @return array<string, mixed>|null
     */
    public function describing(string $path): ?array
    {
        foreach ($this->all()['routes'] as $route => $entry) {
            if (preg_match('@^' . $route . '$@i', $path) === 1) {
                return $entry;
            }
        }
        return null;
    }
}
