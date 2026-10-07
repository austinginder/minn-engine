<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\Router;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * batch/v1 as the reference answers it (probe rest-batch): up to 25
 * writes (POST, PUT, PATCH, DELETE) in one request, each answered in turn
 * as {body, status, headers}, the whole as 207. Only routes that take part
 * in batches may be asked: posts, pages and the other post types shown in
 * REST (not media, templates, global styles or fonts), the taxonomies,
 * users, widgets, and a plugin's routes that opt in with allow_batch. Any
 * other route answers rest_batch_not_allowed with its Allow header, a path
 * with no route rest_no_route. With require-all-validate, every request's
 * arguments are judged first and one failure answers the whole batch with
 * the failures alone (null for the rest), nothing written.
 */
final readonly class BatchController
{
    private const BODY = [
        'validation' => ['type' => 'string', 'enum' => ['require-all-validate', 'normal'], 'default' => 'normal', 'required' => false],
        'requests' => [
            'required' => true,
            'type' => 'array',
            'maxItems' => 25,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'method' => ['type' => 'string', 'enum' => ['POST', 'PUT', 'PATCH', 'DELETE'], 'default' => 'POST'],
                    'path' => ['type' => 'string', 'required' => true],
                    'body' => ['type' => 'object', 'properties' => [], 'additionalProperties' => true],
                    'headers' => ['type' => 'object', 'properties' => [], 'additionalProperties' => ['type' => ['string', 'array'], 'items' => ['type' => 'string']]],
                ],
            ],
        ],
    ];

    /** Post types served by controllers that do not take part in batches. */
    private const NOT_BATCHED = ['attachment', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face'];

    /** @param Closure(Request): Response $dispatch answers one request as the REST API would on its own */
    public function __construct(private Router $router, private Closure $dispatch, private ArgCheck $args, private Types $types)
    {
    }

    /** The requests answered in turn. */
    #[Route(Method::Post, '/batch/v1', policy: new Policy(Access::Public), body: [self::BODY])]
    public function batch(Request $request): Response
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
        $body = $request->json();
        $subs = [];
        $early = [];
        foreach (array_values((array) $body['requests']) as $i => $entry) {
            $subs[$i] = self::subRequest($request, (array) $entry);
            $early[$i] = $this->refusal($subs[$i]);
        }
        if (($body['validation'] ?? 'normal') === 'require-all-validate') {
            $failed = array_map(fn (int $i) => $early[$i] ?? $this->invalid($subs[$i]), array_keys($subs));
            if (array_filter($failed) !== []) {
                return Reply::item(['failed' => 'validation', 'responses' => $failed], null, 207);
            }
        }
        $responses = [];
        foreach ($subs as $i => $sub) {
            $responses[] = $early[$i] ?? self::envelope(($this->dispatch)($sub));
        }
        return Reply::item(['responses' => $responses], null, 207);
    }

    /** A request a batch may not carry, as its envelope: no route, or a route that does not take part. @return array<string, mixed>|null */
    private function refusal(Request $sub): ?array
    {
        $engine = array_filter($this->router->matching($sub), fn (array $match): bool => $this->claims($match[0], $match[1]));
        if ($engine !== []) {
            return $this->batchablePath($sub->path) ? null : self::error(new RestError('rest_batch_not_allowed', 'The requested route does not support batch requests.', 400), $this->router->allowed($sub));
        }
        $wpRequest = new \WP_REST_Request($sub->method->value, $sub->path);
        $matched = RuntimeRoutes::server()->match_request_to_handler($wpRequest);
        if ($matched instanceof \WP_Error) {
            return self::error(RestError::noRoute(), []);
        }
        if (!empty($matched[1]['allow_batch']['v1'])) {
            return null;
        }
        return self::error(new RestError('rest_batch_not_allowed', 'The requested route does not support batch requests.', 400), RuntimeRoutes::allowedMethods((string) $matched[0], $wpRequest));
    }

    /** A request whose arguments do not validate, as its envelope (no headers); null when they do. @return array<string, mixed>|null */
    private function invalid(Request $sub): ?array
    {
        foreach ($this->router->matching($sub) as [$route, $captures]) {
            if ($this->claims($route, $captures)) {
                try {
                    $this->args->check($route, $sub);
                    return null;
                } catch (RestError $error) {
                    return self::error($error, []);
                }
            }
        }
        $wpRequest = RuntimeRoutes::wpRequest($sub);
        $matched = RuntimeRoutes::server()->match_request_to_handler($wpRequest);
        if ($matched instanceof \WP_Error) {
            return null;
        }
        $wpRequest->set_attributes((array) $matched[1]);
        $valid = $wpRequest->has_valid_params();
        if (!\is_wp_error($valid)) {
            return null;
        }
        $response = \rest_convert_error_to_response($valid);
        return ['body' => RuntimeRoutes::server()->response_to_data($response, false), 'status' => $response->get_status(), 'headers' => []];
    }

    /** A {base} route answers only for a type an extension declared, as the gate's Access::Type has it. @param array<string, string> $captures */
    private function claims(Route $route, array $captures): bool
    {
        if ($route->policy?->access !== Access::Type) {
            return true;
        }
        $slug = $this->types->slugForRestBase((string) ($captures['base'] ?? ''));
        return $slug !== null && $this->types->isDeclared($slug);
    }

    /** Whether an engine path belongs to a route that takes part in batches. */
    private function batchablePath(string $path): bool
    {
        if (!preg_match('#^/wp/v2/([\w-]+)(?:/([\w-]+))?$#', $path, $m)) {
            return false;
        }
        $bases = ['users' => '\d+', 'widgets' => '[\w-]+'];
        foreach (\get_post_types(['show_in_rest' => true], 'objects') as $type) {
            if (!in_array($type->name, self::NOT_BATCHED, true)) {
                $bases[(string) ($type->rest_base ?: $type->name)] = '\d+';
            }
        }
        foreach (\get_taxonomies(['show_in_rest' => true], 'objects') as $taxonomy) {
            $bases[(string) ($taxonomy->rest_base ?: $taxonomy->name)] = '\d+';
        }
        return isset($bases[$m[1]]) && (!isset($m[2]) || preg_match('#^' . $bases[$m[1]] . '$#', $m[2]) === 1);
    }

    /** One entry of the batch as a request of its own: its method, path and query, its body as JSON, the caller's session. @param array<string, mixed> $entry */
    private static function subRequest(Request $parent, array $entry): Request
    {
        $path = (string) ($entry['path'] ?? '');
        $parsed = parse_url($path) ?: [];
        parse_str((string) ($parsed['query'] ?? ''), $query);
        $headers = array_intersect_key($parent->headers, ['x-wp-nonce' => true, 'authorization' => true, 'cookie' => true]);
        foreach ((array) ($entry['headers'] ?? []) as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? implode(',', $value) : (string) $value;
        }
        $body = isset($entry['body']) ? (string) json_encode($entry['body']) : '';
        if ($body !== '') {
            $headers['content-type'] = 'application/json';
        }
        $method = Method::tryFrom(strtoupper((string) ($entry['method'] ?? 'POST'))) ?? Method::Post;
        return new Request($method, (string) ($parsed['path'] ?? $path), $query, $headers, $parent->cookies, $body, $parent->secure, $parent->host);
    }

    /** @param list<string> $allow @return array<string, mixed> */
    private static function error(RestError $error, array $allow): array
    {
        return ['body' => $error->payload(), 'status' => $error->status, 'headers' => $allow === [] ? [] : ['Allow' => implode(', ', $allow)]];
    }

    /** A sub-request's answer as {body, status, headers}: the headers its route set, not the server's own. @return array<string, mixed> */
    private static function envelope(Response $response): array
    {
        $headers = array_diff_key($response->headers, Reply::HEADERS, ['Vary' => true, 'X-Robots-Tag' => true]);
        return ['body' => json_decode($response->body, true), 'status' => $response->status, 'headers' => $headers];
    }
}
