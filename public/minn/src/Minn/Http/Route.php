<?php

declare(strict_types=1);

namespace Minn\Http;

use Attribute;

/**
 * Declares a handler method as a route. The policy lives here, as
 * metadata the router enforces before the handler runs, so the
 * authorization surface of the engine is a grep away; a route with no
 * policy is one the ratchet in the style suite counts down. The parameter
 * sets it reads live here too, so the REST index can tell a client what a
 * route takes as well as what it requires of them.
 *
 * Patterns: "/wp/v2/posts/{id}" captures one segment, "{id:\d+}" constrains
 * it, and "/{path*}" captures the rest of the path (slashes included).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    /**
     * @param list<array<string, array<string, mixed>>> $args the parameter sets this route reads, from Args
     * @param bool $index whether the route is listed in the REST index (a route the index spells itself is not)
     */
    public function __construct(
        public Method $method,
        public string $pattern,
        public ?Policy $policy = null,
        public array $args = [],
        public bool $index = true,
    ) {
    }

    /**
     * The parameters this route accepts, as the REST index publishes them.
     *
     * @return array<string, array<string, mixed>>
     */
    public function arguments(): array
    {
        return Args::merge($this->args);
    }

    /** The pattern as a regular expression with named captures. */
    public function regex(): string
    {
        $parts = preg_split('/\{(\w+(?:\*|:[^}]+)?)\}/', $this->pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $regex .= preg_quote($part, '#');
                continue;
            }
            // A constrained capture is never the catch-all, however its
            // constraint ends: "{slug:[a-z-]*}" is a slug, not the rest of the path.
            if (str_contains($part, ':')) {
                [$name, $constraint] = explode(':', $part, 2);
                $regex .= "(?P<{$name}>{$constraint})";
            } elseif (str_ends_with($part, '*')) {
                $regex .= '(?P<' . rtrim($part, '*') . '>.*)';
            } else {
                $regex .= "(?P<{$part}>[^/]+)";
            }
        }
        return "#^{$regex}$#";
    }
}
