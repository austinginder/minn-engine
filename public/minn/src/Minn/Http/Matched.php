<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * A route the router matched to a request and whose policy it judged: the
 * route, the object and method that answer it, the pattern's captures, and
 * every method the same handler answers on that pattern (the reference
 * lists POST, PUT and PATCH together for one edit handler).
 */
final readonly class Matched
{
    /**
     * @param array<string, string> $captures
     * @param list<string> $methods
     */
    public function __construct(
        public Route $route,
        public object $handler,
        public string $method,
        public array $captures,
        public array $methods,
    ) {
    }
}
