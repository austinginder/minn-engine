<?php

declare(strict_types=1);

namespace Minn\Rest;

/** The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options. */
final class RouteTable
{
    private const HANDLER_DEFAULTS = ['methods' => [], 'accept_json' => false, 'accept_raw' => false, 'show_in_index' => true, 'args' => []];

    /**
     * Registered endpoints as a route table with their options.
     *
     * @param array<string, mixed> $endpoints route => a handler or a list of handlers plus options
     * @return array{0: array<string, list<array<string, mixed>>>, 1: array<string, array<string, mixed>>} the routes, and the options found per route
     */
    public static function normalise(array $endpoints): array
    {
        $routes = [];
        $options = [];
        foreach ($endpoints as $route => $handlers) {
            if (isset($handlers['callback'])) {
                $handlers = [$handlers];
            }
            $options[$route] = [];
            $kept = [];
            foreach ((array) $handlers as $key => $handler) {
                if (!is_numeric($key)) {
                    $options[$route][$key] = $handler;
                    continue;
                }
                $handler = array_merge(self::HANDLER_DEFAULTS, (array) $handler);
                $methods = is_string($handler['methods']) ? explode(',', $handler['methods']) : (is_array($handler['methods']) ? $handler['methods'] : []);
                $handler['methods'] = [];
                foreach ($methods as $method) {
                    $handler['methods'][strtoupper(trim((string) $method))] = true;
                }
                $kept[$key] = $handler;
            }
            $routes[$route] = $kept;
        }
        return [$routes, $options];
    }
}
