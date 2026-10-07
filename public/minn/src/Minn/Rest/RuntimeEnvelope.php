<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Http\Args;
use Minn\Http\Envelope;
use Minn\Http\Matched;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * The REST server's filters around one of Minn's own routes, as the
 * reference runs them around every route (contracts/runtime.md, "The REST
 * server's envelope"): rest_request_before_callbacks is handed the argument
 * check's error or null and may refuse; the route's permission is the
 * next refusal; rest_dispatch_request may answer in place of the route;
 * rest_request_after_callbacks sees whatever came of it and may change it.
 * A value other than an error from the first filter does not answer: the
 * route still runs. With nothing hooked on any of the three, the route
 * answers as it would without plugins, unconverted; and before the runtime
 * boots (the API is built first) there are no plugins to ask.
 */
final readonly class RuntimeEnvelope implements Envelope
{
    private const FILTERS = ['rest_request_before_callbacks', 'rest_dispatch_request', 'rest_request_after_callbacks'];

    /** @param Types $types the declared types, for naming a {base} route as the reference does */
    public function __construct(private Types $types)
    {
    }

    /** The route's answer with the server's filters around it, or as the route gives it when no plugin can hear. */
    public function around(Matched $matched, Request $request, ?RestError $invalid, ?RestError $refusal, Closure $invoke): Response
    {
        if (!Runtime::booted()) {
            return $this->plain($invalid, $refusal, $invoke);
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        $route = $this->routeName($matched, $request);
        $handler = $this->handler($matched, $refusal);
        // The URL parameters are the reference route's own captures (Minn's {base} is its own).
        $captures = array_intersect_key($matched->captures, array_flip(preg_match_all('/\(\?P<(\w+)>/', $route, $names) > 0 ? $names[1] : []));
        $wpRequest->set_url_params($invalid === null ? self::typed($captures) : $captures);
        $wpRequest->set_default_params(self::defaults((array) $handler['args']));
        $wpRequest->set_attributes($handler);
        RuntimeRoutes::matched($request, $route, $handler);
        if (!array_filter(self::FILTERS, static fn (string $filter): bool => \has_filter($filter))) {
            return $this->plain($invalid, $refusal, $invoke);
        }
        $response = $invalid === null ? null : RuntimeRoutes::toWpError($invalid);
        $response = \apply_filters('rest_request_before_callbacks', $response, $handler, $wpRequest);
        if (!\is_wp_error($response) && $refusal !== null) {
            $response = RuntimeRoutes::toWpError($refusal);
        }
        if (!\is_wp_error($response)) {
            $answer = \apply_filters('rest_dispatch_request', null, $wpRequest, $route, $handler);
            $response = $answer ?? self::run($invoke);
            // A core write answers in the edit context, and says so on the request plugins see next (a HEAD is a read).
            if ($answer === null && !in_array($request->method, [Method::Get, Method::Head], true) && str_starts_with($route, '/wp/v2/') && $route !== '/wp/v2/settings' && !\is_wp_error($response)) {
                $wpRequest->set_param('context', 'edit');
            }
        }
        $response = \apply_filters('rest_request_after_callbacks', $response, $handler, $wpRequest);
        return RuntimeRoutes::fromWp($response);
    }

    /** The route answering as it does without plugins: the refusals thrown in the router's own order. */
    private function plain(?RestError $invalid, ?RestError $refusal, Closure $invoke): Response
    {
        if ($invalid !== null) {
            throw $invalid;
        }
        if ($refusal !== null) {
            throw $refusal;
        }
        return $invoke();
    }

    /** The route's answer as the server's filters hand it on: a response object, or the error it refused with. */
    private static function run(Closure $invoke): \WP_REST_Response|\WP_Error
    {
        try {
            return RuntimeRoutes::toWp($invoke());
        } catch (RestError $error) {
            return RuntimeRoutes::toWpError($error);
        }
    }

    /** The route as the reference names it in its table: the form of the pattern this request matched. */
    private function routeName(Matched $matched, Request $request): string
    {
        $bases = isset($matched->captures['base']) ? [$matched->captures['base']] : $this->types->declaredBases();
        foreach (EngineRoutes::forms($matched->route->pattern, $bases) as $form) {
            if (preg_match('#^' . str_replace('#', '\#', $form) . '$#', $request->path) === 1) {
                return $form;
            }
        }
        return $matched->route->pattern;
    }

    /**
     * The handler as plugins read it from the filters: its methods, its
     * arguments as the index publishes them, and callables for the route
     * and its permission.
     *
     * @return array<string, mixed>
     */
    private function handler(Matched $matched, ?RestError $refusal): array
    {
        $args = array_map(static fn (array $arg): array => array_diff_key($arg, [Args::HANDLER_VALIDATES => true]), $matched->route->arguments($matched->captures)) ?: $matched->route->bodyArguments();
        return [
            'methods' => array_fill_keys($matched->methods, true),
            'accept_json' => false,
            'accept_raw' => false,
            'show_in_index' => $matched->route->index,
            'args' => $args,
            'callback' => [$matched->handler, $matched->method],
            'permission_callback' => static fn () => $refusal === null ? true : RuntimeRoutes::toWpError($refusal),
        ];
    }

    /** @param array<string, mixed> $args @return array<string, mixed> */
    private static function defaults(array $args): array
    {
        $defaults = [];
        foreach ($args as $name => $arg) {
            if (is_array($arg) && array_key_exists('default', $arg)) {
                $defaults[$name] = $arg['default'];
            }
        }
        return $defaults;
    }

    /**
     * The captures as the reference holds them once its arguments passed:
     * a number is an integer.
     *
     * @param array<string, string> $captures
     * @return array<string, int|string>
     */
    private static function typed(array $captures): array
    {
        return array_map(static fn (string $value): int|string => ctype_digit($value) && $value !== '' && strlen($value) < 19 ? (int) $value : $value, $captures);
    }
}
