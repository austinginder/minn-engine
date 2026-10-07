<?php

declare(strict_types=1);

namespace Minn\Http;

use Attribute;

/**
 * Declares a handler method as a route. The policy lives here, as
 * metadata the router enforces before the handler runs, so the
 * authorization surface of the engine is a grep away; a route with no
 * policy is one the ratchet in the style suite counts down. The parameter
 * sets it reads live here too, judged before the policy is (the reference
 * refuses a bad argument before it refuses a caller), so the REST index can
 * tell a client what a route takes as well as what it requires of them.
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
     * @param list<array<string, array<string, mixed>>> $body the parameter sets this route reads from the JSON body, from Args or the shape that owns them
     * @param string|null $name the route's name in the catalogue; the handler's Class::method when null
     * @param class-string<RouteParams>|null $params the parameters' source when they depend on the captures; $args otherwise
     */
    public function __construct(
        public Method $method,
        public string $pattern,
        public ?Policy $policy = null,
        public array $args = [],
        public bool $index = true,
        public array $body = [],
        public ?string $name = null,
        public ?string $params = null,
    ) {
    }

    /**
     * The parameters this route accepts, as the REST index publishes them:
     * from its params source for these captures (a capture the pattern fixes,
     * such as {base:posts}, counts when none is given), or its fixed sets.
     *
     * @param array<string, string> $captures
     * @return array<string, array<string, mixed>>
     */
    public function arguments(array $captures = []): array
    {
        if ($this->params === null) {
            return Args::merge($this->args);
        }
        $fixed = preg_match_all('/\{(\w+):(\w+)\}/', $this->pattern, $matches, PREG_SET_ORDER) > 0 ? array_column($matches, 2, 1) : [];
        return ($this->params)::for($captures + $fixed);
    }

    /**
     * The parameters this route reads from the JSON body, validated before the caller is judged.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyArguments(): array
    {
        return Args::merge($this->body);
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
