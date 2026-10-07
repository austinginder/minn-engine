<?php

declare(strict_types=1);

namespace Minn\Http;

use ReflectionMethod;

/**
 * One line of the route table: what a route is, who it is for, and what it
 * takes, read from the attribute and the handler method alone, so the table
 * can be built from the classes without a request, a database, or a site.
 * This is the row the REST index, the docs, and contracts/api/routes.json
 * are written from.
 */
final readonly class RouteRow
{
    /**
     * @param array<string, array<string, mixed>> $args the query parameters the route reads
     * @param array<string, array<string, mixed>> $body the JSON body parameters it reads
     * @param class-string<RouteParams>|null $params where the parameters come from when they depend on the path
     */
    public function __construct(
        public string $method,
        public string $pattern,
        public string $name,
        public string $handler,
        public string $summary,
        public ?Policy $policy,
        public array $args,
        public array $body,
        public bool $index,
        public ?string $params = null,
        public string $regex = '',
    ) {
    }

    /** The row for one attribute on one handler method; the name defaults to the handler. */
    public static function of(Route $route, ReflectionMethod $method): self
    {
        $handler = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        return new self(
            method: $route->method->value,
            pattern: $route->pattern,
            name: $route->name ?? $handler,
            handler: $handler,
            summary: self::summary((string) $method->getDocComment()),
            policy: $route->policy,
            args: $route->arguments(),
            body: $route->bodyArguments(),
            index: $route->index,
            params: $route->params,
            regex: $route->regex(),
        );
    }

    /**
     * The parameters as the index publishes them for one concrete route the
     * pattern takes (a {base} route's parameters are that base's).
     *
     * @return array<string, array<string, mixed>>
     */
    public function argsFor(string $route): array
    {
        $args = $this->args;
        if ($this->params !== null && preg_match($this->regex, $route, $captures) === 1) {
            $args = ($this->params)::for(array_filter($captures, is_string(...), ARRAY_FILTER_USE_KEY));
        }
        return array_map(static fn (array $arg): array => array_diff_key($arg, [Args::HANDLER_VALIDATES => true]), $args);
    }

    /** The row as data, for the JSON catalogue. @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'pattern' => $this->pattern,
            'name' => $this->name,
            'handler' => $this->handler,
            'summary' => $this->summary,
            'policy' => $this->policy?->toArray(),
            'args' => array_map(static fn (array $arg): array => array_diff_key($arg, [Args::HANDLER_VALIDATES => true]), $this->args),
            'body' => $this->body,
            'index' => $this->index,
        ];
    }

    /** The first sentence of a docblock, the way the API docs read it. */
    private static function summary(string $doc): string
    {
        $text = trim((string) preg_replace(['#^/\*\*#', '#\*/$#', '#^\s*\*\s?#m'], '', $doc));
        $text = (string) preg_replace('/\s+/', ' ', explode("\n\n", $text)[0]);
        $text = (string) preg_replace('/\s*@\w.*$/', '', $text);
        return preg_match('/^(.*?\.)(\s|$)/', $text, $m) === 1 ? $m[1] : $text;
    }
}
