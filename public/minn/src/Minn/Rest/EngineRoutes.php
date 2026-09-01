<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Router;

/**
 * The engine's own REST routes in the reference's regex form, for the
 * index and for the runtime's server, whose route table plugin code reads
 * to learn what the site answers (a missing /wp/v2/comments there reads as
 * "comments are off").
 */
final class EngineRoutes
{
    /**
     * The router's routes as route => methods, the way the index lists them.
     *
     * @return array<string, list<string>> route => methods
     */
    public static function map(Router $router): array
    {
        $map = [];
        foreach ($router->routes() as $pattern => $methods) {
            foreach (self::forms($pattern) as $route) {
                $map[$route] = array_values(array_unique(array_merge($map[$route] ?? [], $methods)));
            }
        }
        return $map;
    }

    /**
     * A route attribute pattern as the reference writes routes: `{id:\d+}`
     * becomes `(?P<id>\d+)`, a bare capture matches one segment, a `{rest*}`
     * capture the remainder, and an alternation of literals expands to one
     * route per literal.
     * @return list<string>
     */
    public static function forms(string $pattern): array
    {
        $routes = [$pattern];
        while (true) {
            $expanded = [];
            $changed = false;
            foreach ($routes as $route) {
                if (preg_match('/\{(\w+):([a-z_0-9|-]+)\}/', $route, $m) && str_contains($m[2], '|')) {
                    foreach (explode('|', $m[2]) as $literal) {
                        $expanded[] = str_replace($m[0], $literal, $route);
                    }
                    $changed = true;
                } else {
                    $expanded[] = $route;
                }
            }
            $routes = $expanded;
            if (!$changed) {
                break;
            }
        }
        return array_map(static fn (string $route) => preg_replace_callback('/\{(\w+)(?::([^}]+)|\*)?\}/', static function (array $m): string {
            $constraint = isset($m[2]) && $m[2] !== '' ? $m[2] : (str_ends_with($m[0], '*}') ? '.*' : '[^/]+');
            return '(?P<' . $m[1] . '>' . $constraint . ')';
        }, $route), $routes);
    }
}
