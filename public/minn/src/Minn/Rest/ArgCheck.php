<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Closure;
use Minn\Http\Request;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Refusal;

/**
 * Judges a route's declared arguments against the request before the
 * policy is judged, the way the reference does: a required parameter that
 * did not arrive is rest_missing_callback_param; the shared collection
 * parameters (context, page, per_page, search) are judged first and a
 * refusal among them is answered alone; then every other invalid one is
 * listed in rest_invalid_param, in the order the route declares them, with
 * the schema's own refusal under details. The query is read against the
 * route's args, the JSON body against its body set, and a JSON body that
 * does not parse is refused on every route first. An argument its handler
 * judges (Args::HANDLER_VALIDATES) is left to the handler.
 */
final readonly class ArgCheck
{
    public function __construct(private Schema $schema)
    {
    }

    /** The check as the router takes it. */
    public function closure(): Closure
    {
        return fn (Route $route, Request $request): mixed => $this->check($route, $request);
    }

    /** Throws the refusal the declared arguments earn, or returns. */
    public function check(Route $route, Request $request): void
    {
        $this->json($request);
        $query = $route->arguments();
        $shared = array_intersect_key($query, array_flip(Args::SHARED));
        $own = array_diff_key($query, $shared);
        $body = $route->bodyArguments();
        $this->missing($shared, $request->query);
        $this->round([[$shared, $request->query]]);
        $sources = [[$own, $request->query]];
        if ($body !== []) {
            $sources[] = [$body, $request->json()];
        }
        foreach ($sources as [$args, $values]) {
            $this->missing($args, $values);
        }
        $this->round($sources);
    }

    /** A JSON body that does not parse is refused on every route, before its arguments are read; an empty one is not a body. */
    private function json(Request $request): void
    {
        if (!str_starts_with(strtolower((string) $request->header('content-type')), 'application/json') || trim($request->body) === '') {
            return;
        }
        json_decode($request->body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RestError('rest_invalid_json', 'Invalid JSON body passed.', 400, ['json_error_code' => json_last_error(), 'json_error_message' => json_last_error_msg()]);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $args
     * @param array<string, mixed> $values
     */
    private function missing(array $args, array $values): void
    {
        $missing = [];
        foreach ($args as $name => $schema) {
            if (($schema['required'] ?? false) === true && !array_key_exists($name, $values)) {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw RestError::missingParams($missing);
        }
    }

    /** @param list<array{0: array<string, array<string, mixed>>, 1: array<string, mixed>}> $sources */
    private function round(array $sources): void
    {
        $invalid = [];
        $details = [];
        foreach ($sources as [$args, $values]) {
            foreach ($args as $name => $schema) {
                if (!array_key_exists($name, $values) || ($schema[Args::HANDLER_VALIDATES] ?? false) === true) {
                    continue;
                }
                $verdict = $this->schema->validate($values[$name], $schema, $name);
                if ($verdict instanceof Refusal) {
                    $invalid[$name] = $verdict->message;
                    $details[$name] = ['code' => $verdict->code, 'message' => $verdict->message, 'data' => $verdict->data];
                }
            }
        }
        if ($invalid !== []) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): ' . implode(', ', array_keys($invalid)), 400, ['params' => $invalid, 'details' => $details]);
        }
    }
}
