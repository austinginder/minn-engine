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

    /** The namespace the reference registers before any plugin's. */
    private const FIRST = 'oembed/1.0';

    /** The core namespaces the reference registers after the plugins', in its order. */
    private const CORE = ['wp/v2', 'wp-site-health/v1', 'wp-block-editor/v1', 'wp-abilities/v1'];

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
        $endpoints = self::endpoints($this->router->table(), $bases);
        foreach (array_keys($this->router->routes()) as $pattern) {
            foreach (EngineRoutes::forms($pattern, $bases) as $route) {
                if ($route === '/') {
                    continue;
                }
                $namespace = self::namespaceOf($route);
                if ($namespace !== '' && !in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                    $routes['/' . $namespace] = ['namespace' => $namespace, 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['namespace' => ['default' => $namespace, 'required' => false], 'context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/' . $namespace)]]]];
                }
                $routes[$route] = ['namespace' => $namespace, 'methods' => array_merge(...array_column($endpoints[$route] ?? [], 'methods')), 'endpoints' => $endpoints[$route] ?? []];
                if (!str_contains($route, '(?P<')) {
                    $routes[$route]['_links'] = ['self' => [['href' => $this->url->to($route)]]];
                }
            }
        }
        return ['namespaces' => $namespaces, 'routes' => $routes];
    }

    /**
     * Each concrete route's endpoints as the reference lists them: one per
     * handler and set of parameters (an update's POST, PUT and PATCH are
     * one), the methods in the reference's order, the endpoints ordered by
     * their first method. A method an earlier pattern already answers on
     * the route is left to it, as the router leaves it.
     *
     * @param list<RouteRow> $rows
     * @param list<string> $bases
     * @return array<string, list<array{methods: list<string>, args: array<string, mixed>}>>
     */
    private static function endpoints(array $rows, array $bases): array
    {
        $groups = [];
        $taken = [];
        foreach ($rows as $row) {
            if (!$row->index) {
                continue;
            }
            $method = $row->method === '*' ? 'GET' : $row->method;
            foreach (EngineRoutes::forms($row->pattern, $bases) as $route) {
                if (isset($taken[$route][$method])) {
                    continue;
                }
                $taken[$route][$method] = true;
                $args = $row->argsFor($route);
                $key = $row->handler . "\0" . serialize($args);
                $groups[$route][$key]['methods'][] = $method;
                $groups[$route][$key]['batch'] = $row->allowBatch;
                $groups[$route][$key]['args'] = $args;
            }
        }
        $endpoints = [];
        foreach ($groups as $route => $group) {
            $listed = array_map(static function (array $endpoint): array {
                usort($endpoint['methods'], static fn (string $a, string $b): int => self::rank($a) <=> self::rank($b));
                return ['methods' => $endpoint['methods']] + ($endpoint['batch'] !== null ? ['allow_batch' => $endpoint['batch']] : []) + ['args' => $endpoint['args']];
            }, array_values($group));
            usort($listed, static fn (array $a, array $b): int => self::rank($a['methods'][0]) <=> self::rank($b['methods'][0]));
            $endpoints[$route] = $listed;
        }
        return $endpoints;
    }

    /** A method's place in the reference's order. */
    private static function rank(string $method): int
    {
        $at = array_search($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true);
        return $at === false ? 5 : $at;
    }

    /**
     * The namespaces in the order the reference's index lists them, which
     * is the order they were registered in: oEmbed's first, then each
     * plugin's (Minn Admin's among them) as they came, then core's own.
     *
     * @param list<string> $namespaces
     * @return list<string>
     */
    public static function ordered(array $namespaces): array
    {
        $first = array_values(array_intersect([self::FIRST], $namespaces));
        $core = array_values(array_intersect(self::CORE, $namespaces));
        $plugins = array_values(array_diff($namespaces, $first, $core));
        return [...$first, ...$plugins, ...$core];
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
     * the route an OPTIONS request describes, with its description; null
     * when none does.
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    public function describing(string $path): ?array
    {
        foreach ($this->all()['routes'] as $route => $entry) {
            if (preg_match('@^' . $route . '$@i', $path) === 1) {
                return [(string) $route, $entry];
            }
        }
        return null;
    }
}
