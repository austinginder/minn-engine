<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Refusal;

/** Finds the registered handler for a method and path among the runtime's route table. */
final class RouteMatch
{
    /**
     * The handler for a method and path across the registered namespaces, or the refusal.
     *
     * @param list<string> $namespaces the registered namespaces
     * @param callable(string): array<string, list<array<string, mixed>>> $routesFor the (filtered) routes of one namespace, or all when given ''
     * @return array{route: string, handler: array<string, mixed>, params: array<string, string>, defaults: array<string, mixed>}|Refusal
     */
    public static function find(array $namespaces, callable $routesFor, string $method, string $path): array|Refusal
    {
        $tables = [];
        foreach ($namespaces as $namespace) {
            if (str_starts_with(trim($path, '/'), $namespace)) {
                $tables[] = $routesFor($namespace);
            }
        }
        $routes = $tables === [] ? $routesFor('') : array_merge(...$tables);
        foreach ($routes as $route => $handlers) {
            if (preg_match('@^' . $route . '$@i', $path, $matches) !== 1) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($handlers as $handler) {
                if (empty($handler['methods'][$method])) {
                    continue;
                }
                if (!is_callable($handler['callback'])) {
                    return new Refusal('rest_invalid_handler', 'The handler for the route is invalid.', ['status' => 500]);
                }
                $defaults = [];
                foreach ((array) ($handler['args'] ?? []) as $name => $options) {
                    if (isset($options['default'])) {
                        $defaults[$name] = $options['default'];
                    }
                }
                return ['route' => (string) $route, 'handler' => $handler, 'params' => $params, 'defaults' => $defaults];
            }
        }
        return new Refusal('rest_no_route', 'No route was found matching the URL and request method.', ['status' => 404]);
    }
}
