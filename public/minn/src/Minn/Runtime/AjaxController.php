<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * admin-ajax.php, the endpoint plugins post their front-end work to: a form
 * submitted without a reload, a spam check, a background job. The action a
 * request names runs the handlers registered as wp_ajax_{action} for a
 * signed-in visitor and wp_ajax_nopriv_{action} for anyone else, answered as
 * the reference answers them (contracts/runtime.md "admin-ajax.php").
 *
 * It is an admin request: is_admin() is true while the plugins load and
 * admin_init fires before the handler. A handler usually ends the request
 * itself (wp_send_json, wp_die, exit), so the endpoint's headers go out
 * before it runs; one that returns is followed by the reference's "0".
 */
final readonly class AjaxController
{
    public const PATH = '/wp-admin/admin-ajax.php';

    /** The core actions the endpoint answers itself, under the prefix for who is asking: the action => the handler. */
    private const CORE = [
        'wp_ajax_nopriv_' => ['heartbeat' => 'wp_ajax_nopriv_heartbeat'],
        'wp_ajax_' => ['rest-nonce' => 'wp_ajax_rest_nonce', 'heartbeat' => 'wp_ajax_heartbeat'],
    ];

    /** The filters the reference's admin includes add once plugins have loaded: the hook, the callback. */
    private const ADMIN_FILTERS = [
        ['wp_refresh_nonces', 'wp_refresh_heartbeat_nonces'],
    ];

    /** Whether a request is for the endpoint, which the runtime boots as an admin request. */
    public static function claims(Request $request): bool
    {
        return $request->path === self::PATH;
    }

    /** Runs the handlers registered for the action the request names. */
    #[Route(Method::Any, self::PATH, policy: new Policy(Access::Public))]
    public function dispatch(Request $request): Response
    {
        $origin = $this->allowedOrigin();
        if ($request->method === Method::Options) {
            return $origin === []
                ? new Response(403, ['Content-Type' => 'text/html; charset=UTF-8'])
                : new Response(200, $origin + ['Content-Type' => 'text/html; charset=UTF-8']);
        }
        $headers = $origin + [
            'Content-Type' => 'text/html; charset=' . \get_option('blog_charset'),
            'X-Robots-Tag' => 'noindex',
        ] + \wp_get_nocache_headers();
        $action = $this->action($request);
        if ($action === null) {
            return new Response(400, $headers, '0');
        }
        // From here a callback may end the request itself, so what the
        // endpoint says about the response is said before any of them runs.
        (new Response(200, $headers + ['X-Content-Type-Options' => 'nosniff']))->sendHead();
        foreach (self::ADMIN_FILTERS as [$hook, $callback]) {
            \add_filter($hook, $callback);
        }
        $output = Runtime::capture('admin_init');
        $prefix = \is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_';
        $this->registerCore($prefix);
        $hook = $prefix . $action;
        if (!Runtime::hooks()->has($hook)) {
            return new Response(400, [], $output . '0');
        }
        return new Response(200, [], $output . Runtime::capture($hook) . '0');
    }

    /** The action the request names, the posted field over the query's, or null when there is none to run. */
    private function action(Request $request): ?string
    {
        $action = $request->form['action'] ?? $request->query['action'] ?? null;
        return is_string($action) && $action !== '' ? $action : null;
    }

    /** The two cross-origin headers, for an Origin that is this site's own address; empty for any other. */
    private function allowedOrigin(): array
    {
        $origin = (string) \get_http_origin();
        if ($origin === '' || !\is_allowed_http_origin($origin)) {
            return [];
        }
        return ['Access-Control-Allow-Origin' => $origin, 'Access-Control-Allow-Credentials' => 'true'];
    }

    /** The reference registers its own actions after admin_init, only those for who is asking, ahead of a plugin's. */
    private function registerCore(string $prefix): void
    {
        foreach (self::CORE[$prefix] as $action => $handler) {
            Runtime::hooks()->add($prefix . $action, $handler, 1);
        }
    }
}
