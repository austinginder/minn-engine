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
        $bases = $this->types->declaredBases();
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
                $namespace = preg_match('#^/([^/]+/(?:v\d+|\d+\.\d+))#', $route, $m) ? $m[1] : '';
                if ($namespace !== '' && !in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                    $routes['/' . $namespace] = ['namespace' => $namespace, 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['namespace' => ['default' => $namespace, 'required' => false], 'context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/' . $namespace)]]]];
                }
                $methods = array_values(array_unique(array_map(static fn (string $m) => $m === '*' ? 'GET' : $m, $methods)));
                $routes[$route] = ['namespace' => $namespace, 'methods' => $methods, 'endpoints' => [['methods' => $methods, 'args' => $arguments[$route] ?? []]]];
                if (!str_contains($route, '(?P<')) {
                    $routes[$route]['_links'] = ['self' => [['href' => $this->url->to($route)]]];
                }
            }
        }
        return ['namespaces' => $namespaces, 'routes' => $routes];
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
