<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Cookie;
use Minn\Content\Reader;
use Minn\Front\Permalinks;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\NoCache;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * The wp-admin pages a plugin answers itself. The admin is Minn Admin, so
 * the engine draws no wp-admin screen, but a page builder opens its editor
 * from one: Elementor's "Edit with Elementor" is post.php?post=N&action=elementor,
 * whose handler (admin_action_elementor) prints a whole page of its own and
 * ends the request before any screen is drawn.
 *
 * A request to a wp-admin page that names an action boots as an admin
 * request, as the reference's does. The admin cookie (scoped to /wp-admin)
 * is asked for, for the user the request is read as; without it the
 * visitor signs in and comes back. admin_init fires, then the action's
 * handlers. When no plugin handles the action, or its handlers return, the
 * request lands in Minn Admin, where every other wp-admin address goes.
 */
final readonly class AdminActionController
{
    private const PAGE = '#^/wp-admin/[a-z0-9-]+\.php$#';

    public function __construct(
        private Cookie $cookie,
        private Permalinks $permalinks,
    ) {
    }

    /** Whether a request is for a wp-admin page with an action to run, which the runtime boots as an admin request. */
    public static function claims(Request $request): bool
    {
        return $request->path !== AjaxController::PATH && preg_match(self::PAGE, $request->path) === 1 && self::action($request) !== null;
    }

    /** Runs the handlers a plugin hooked for the action, or sends the visitor on. */
    #[Route(Method::Any, '/wp-admin/{page:[a-z0-9-]+\.php}', policy: new Policy(Access::Public))]
    public function dispatch(Request $request): Response
    {
        $admin = Response::redirect($this->permalinks->url('/minn-admin/'), 302);
        $action = self::claims($request) ? self::action($request) : null;
        if ($action === null) {
            return $admin;
        }
        $session = $this->cookie->admin($request->cookies, $request->secure);
        if ($session === null || $session->id() !== Reader::current()->userId) {
            $back = $this->permalinks->url($request->path) . $request->queryStringWithout();
            return Response::redirect($this->permalinks->url('/wp-login.php') . '?' . http_build_query(['redirect_to' => $back, 'reauth' => '1']), 302);
        }
        // What admin_init prints stays held with the page: a handler that ends
        // the request sends both, one that returns sends neither.
        ob_start();
        Runtime::hooks()->action('admin_init', []);
        $hook = "admin_action_{$action}";
        if (!Runtime::hooks()->has($hook)) {
            ob_end_clean();
            return $admin;
        }
        // A handler usually prints its own page and ends the request, so the
        // headers an admin page sends are set first; they go out with the
        // first byte the handler prints.
        (new Response(200, [
            'Content-Type' => 'text/html; charset=' . Runtime::options()->filtered('blog_charset'),
            'X-Frame-Options' => 'SAMEORIGIN',
        ] + NoCache::headers()))->sendHead();
        Runtime::hooks()->action($hook, []);
        // The handlers returned: the reference would draw the page's screen,
        // which on Minn is Minn Admin.
        ob_end_clean();
        return $admin;
    }

    /** The action the request names, the posted field over the query's, or null when there is none. */
    private static function action(Request $request): ?string
    {
        $action = $request->form['action'] ?? $request->query['action'] ?? null;
        return is_string($action) && $action !== '' ? $action : null;
    }
}
