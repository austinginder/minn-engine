<?php

declare(strict_types=1);

namespace Minn\Http;

use Attribute;

/**
 * Declares a handler method as a route. The capability requirement lives
 * here, as metadata the router enforces before the handler runs, so the
 * authorization surface of the engine is a grep away.
 *
 * Patterns: "/wp/v2/posts/{id}" captures one segment, "{id:\d+}" constrains
 * it, and "/{path*}" captures the rest of the path (slashes included).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    public function __construct(
        public Method $method,
        public string $pattern,
        public ?string $cap = null,
    ) {
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
            if (str_ends_with($part, '*')) {
                $regex .= '(?P<' . rtrim($part, '*') . '>.*)';
            } elseif (str_contains($part, ':')) {
                [$name, $constraint] = explode(':', $part, 2);
                $regex .= "(?P<{$name}>{$constraint})";
            } else {
                $regex .= "(?P<{$part}>[^/]+)";
            }
        }
        return "#^{$regex}$#";
    }
}
