<?php

declare(strict_types=1);

namespace Minn\Rest;

/** The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link. */
final class RouteIndex
{
    private const SCHEMA_KEYWORDS = ['default', 'enum', 'description', 'type', 'items', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern', 'format', 'properties', 'additionalProperties', 'oneOf', 'anyOf', 'minItems', 'maxItems', 'uniqueItems'];

    /**
     * One route's endpoints as a route table describes them in the index
     * (methods, batching, parameters); none for a route the table lacks.
     *
     * @param array{0: array<string, list<array<string, mixed>>>, 1: array<string, array<string, mixed>>} $table the routes and each route's options
     * @param callable(string): string $restUrl
     * @return list<array<string, mixed>>
     */
    public static function endpointsIn(string $route, array $table, callable $restUrl): array
    {
        $handlers = $table[0][$route] ?? null;
        if (!is_array($handlers)) {
            return [];
        }
        return (array) (self::describe($route, $handlers, (array) ($table[1][$route] ?? []), 'view', $restUrl)['endpoints'] ?? []);
    }

    /**
     * One route as the index describes it, or null when hidden.
     *
     * @param list<array<string, mixed>> $callbacks the route's handler groups
     * @param array<string, mixed> $options the route's non-numeric options (namespace, schema)
     * @param callable(string): string $restUrl
     * @return array<string, mixed>|null null when no endpoint shows in the index
     */
    public static function describe(string $route, array $callbacks, array $options, string $context, callable $restUrl): ?array
    {
        $data = ['namespace' => (string) ($options['namespace'] ?? ''), 'methods' => [], 'endpoints' => []];
        if (isset($options['schema']) && $context === 'help') {
            $data['schema'] = \Minn\Runtime\PluginCall::run($options['schema']);
        }
        foreach ($callbacks as $callback) {
            if (empty($callback['show_in_index'])) {
                continue;
            }
            $methods = array_keys($callback['methods']);
            $data['methods'] = [...$data['methods'], ...$methods];
            $endpoint = ['methods' => $methods];
            // A route registered with allow_batch beside its handlers lists it on each one.
            $batch = $callback['allow_batch'] ?? $options['allow_batch'] ?? null;
            if (!empty($batch)) {
                $endpoint['allow_batch'] = $batch;
            }
            if (isset($callback['args'])) {
                $endpoint['args'] = [];
                foreach ($callback['args'] as $name => $spec) {
                    $endpoint['args'][$name] = self::argument($spec);
                }
            }
            $data['endpoints'][] = $endpoint;
            if (!str_contains($route, '(?P<')) {
                $data['_links'] = ['self' => [['href' => $restUrl($route)]]];
            }
        }
        $data['methods'] = array_values(array_unique($data['methods']));
        return $data['methods'] === [] ? null : $data;
    }

    /** The published shape keeps the argument's own key order and puts `required` last. */
    private static function argument(mixed $spec): array
    {
        if (is_string($spec)) {
            $spec = [$spec => 0];
        } elseif (!is_array($spec)) {
            $spec = [];
        }
        $published = [];
        foreach ($spec as $keyword => $value) {
            if (in_array($keyword, self::SCHEMA_KEYWORDS, true)) {
                $published[$keyword] = $value;
            }
        }
        $published['required'] = !empty($spec['required']);
        return $published;
    }
}
