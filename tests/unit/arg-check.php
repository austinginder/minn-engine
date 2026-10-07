<?php

declare(strict_types=1);

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Route;
use Minn\Rest\ArgCheck;
use Minn\Rest\PostCollectionParams;
use Minn\Rest\Schema;
use Minn\RestError;

/**
 * The argument check: the reference's order and shapes for a bad
 * parameter, pinned without a site. The shared collection parameters are
 * judged first and answered alone; the route's own follow, listed in
 * declaration order; a required one that did not arrive is missing; the
 * JSON body is read against the body set; a handler-judged one is skipped.
 */
$schema = new Schema(
    static fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL),
    static fn (int|float $n): string => number_format((float) $n),
    static fn (string $f, mixed $v): mixed => $v,
);
$check = new ArgCheck($schema);
$request = static fn (array $query, string $body = ''): Request => new Request(Method::Get, '/x', $query, [], [], $body, false, 'unit.test', []);
$refusal = static function (Route $route, Request $request) use ($check): ?array {
    try {
        $check->check($route, $request);
        return null;
    } catch (RestError $e) {
        return $e->payload();
    }
};
$posts = new Route(Method::Get, '/wp/v2/{base:posts}', params: PostCollectionParams::class);
$settings = new Route(Method::Post, '/wp/v2/settings', body: [['posts_per_page' => ['type' => 'integer'], 'name' => ['type' => 'string', 'required' => true]]]);

return [
    'a good request passes' => static fn (): bool|string => $refusal($posts, $request(['per_page' => '5', 'orderby' => 'title', 'include' => '1,2'])) === null ? true : 'refused',
    'a shared parameter is answered alone, before the route\'s own' => static function () use ($refusal, $posts, $request): bool|string {
        $payload = $refusal($posts, $request(['per_page' => 'x', 'page' => '0', 'orderby' => 'bogus']));
        return ($payload['message'] ?? '') === 'Invalid parameter(s): page, per_page'
            && ($payload['data']['params'] ?? null) === ['page' => 'page must be greater than or equal to 1', 'per_page' => 'per_page is not of type integer.']
            && ($payload['data']['details']['per_page'] ?? null) === ['code' => 'rest_invalid_type', 'message' => 'per_page is not of type integer.', 'data' => ['param' => 'per_page']]
            ? true : json_encode($payload);
    },
    'the route\'s own parameters are listed together in declaration order' => static function () use ($refusal, $posts, $request): bool|string {
        $payload = $refusal($posts, $request(['orderby' => 'bogus', 'include' => 'a', 'order' => 'up']));
        return ($payload['message'] ?? '') === 'Invalid parameter(s): include, order, orderby'
            && ($payload['data']['params']['include'] ?? '') === 'include[0] is not of type integer.'
            && ($payload['data']['params']['order'] ?? '') === 'order is not one of asc and desc.'
            && str_ends_with((string) ($payload['data']['params']['orderby'] ?? ''), 'slug, include_slugs, and title.')
            ? true : json_encode($payload);
    },
    'a handler-judged argument is left to the handler' => static fn (): bool|string => $refusal($posts, $request(['status' => 'bogus'])) === null ? true : 'refused',
    'an empty context is refused, an empty id list is not' => static function () use ($refusal, $posts, $request): bool|string {
        $context = $refusal($posts, $request(['context' => '']));
        return ($context['data']['params']['context'] ?? '') === 'context is not one of view, embed, and edit.' && $refusal($posts, $request(['include' => ''])) === null ? true : json_encode($context);
    },
    'the body is read against the body set, and a required key that did not arrive is missing' => static function () use ($refusal, $settings, $request): bool|string {
        $invalid = $refusal($settings, $request([], '{"posts_per_page":"x","name":"n"}'));
        $missing = $refusal($settings, $request([], '{"posts_per_page":5}'));
        return ($invalid['data']['params'] ?? null) === ['posts_per_page' => 'posts_per_page is not of type integer.']
            && ($missing['code'] ?? '') === 'rest_missing_callback_param' && ($missing['data']['params'] ?? null) === ['name']
            ? true : json_encode([$invalid, $missing]);
    },
];
