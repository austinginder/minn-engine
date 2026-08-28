<?php

declare(strict_types=1);

namespace Minn;

use Minn\Admin\App;
use Minn\Admin\AppController;
use Minn\Admin\BootPayload;
use Minn\Auth\Authenticated;
use Minn\Auth\AuthCookies;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Auth\Cookie;
use Minn\Auth\Sessions;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Front\FrontController;
use Minn\Front\Renderer;
use Minn\Front\Resolver;
use Minn\Http\Kernel;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Router;
use Minn\Login\LoginController;
use Minn\Rest\Api;

/**
 * The engine's front door. An unmodified wp-config.php ends by requiring
 * wp-settings.php, which is the engine's own boot file; it hands off here.
 * REST is dispatched first (from the path or from ?rest_route=), then the
 * admin, the login endpoint, and finally the public site.
 */
final readonly class Engine
{
    public function __construct(
        private string $version,
        private string $rootDir,
    ) {
    }

    public function serve(): never
    {
        $db = Db::shared();
        $request = Request::fromGlobals();

        $route = $request->query('rest_route');
        if ($route !== null) {
            Api::forRequest($db, $request)->handle($route)->send();
        }
        if (str_starts_with($request->path, '/wp-json')) {
            Api::forRequest($db, $request)->handle(substr($request->path, strlen('/wp-json')) ?: '/')->send();
        }

        $users = new Users($db);
        $site = new Site($db);
        $sessions = new Sessions($users);
        $cookie = new Cookie($db, $users, $sessions);
        $authenticator = new Authenticator($cookie, $users);
        $capabilities = Capabilities::fromDb($db);
        $app = new App($this->rootDir . '/minn-admin-app');

        $canReadUnpublished = static function (array $post) use ($authenticator, $capabilities, $request): bool {
            $session = $authenticator->session($request->cookies);
            return $session instanceof Authenticated && $capabilities->can($session->id(), 'edit_post', (int) $post['ID']);
        };
        $resolver = Resolver::fromDb($db, $canReadUnpublished);
        $permalinks = $resolver->permalinks();

        $router = (new Router())->register(
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version), $authenticator, $capabilities, $permalinks, $this->version),
            new LoginController($site, $permalinks, $authenticator, $sessions, new AuthCookies($db, $cookie)),
            new FrontController($resolver, new Renderer($db, new Posts($db), $permalinks, $resolver->perPage())),
        );
        $response = (new Kernel($router))->handle($request);
        ($response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404))->send();
    }
}
