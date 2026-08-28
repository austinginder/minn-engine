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
use Minn\Auth\LoginThrottle;
use Minn\Auth\Sessions;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Content\Comments;
use Minn\Front\AssetsController;
use Minn\Front\Feeds;
use Minn\Front\ProbeController;
use Minn\Front\Sitemaps;
use Minn\Front\FrontController;
use Minn\Front\Renderer;
use Minn\Front\Resolver;
use Minn\Http\Failure;
use Minn\Http\Kernel;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Router;
use Minn\Login\LoginController;
use Minn\Rest\Api;
use Minn\Theme\PageRenderer;
use Minn\Theme\Theme;
use Minn\Auth\Salts;
use Minn\Auth\PasswordReset;
use Minn\Mail\Mailer;
use Minn\Cron\Cron;
use Minn\Content\PostWriter;

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
        /** the minn/ folder: the engine's own files (assets, data, the admin bundle) */
        private string $engineDir,
    ) {
    }

    public function serve(): never
    {
        Failure::install();
        try {
            $db = Db::shared();
        } catch (\mysqli_sql_exception $e) {
            error_log('Minn Engine: database connection failed: ' . $e->getMessage());
            Failure::databaseUnavailable()->send();
        }
        if (!Salts::configured()) {
            error_log('Minn Engine: wp-config.php is missing its unique keys and salts (AUTH_KEY … NONCE_SALT); refusing to sign anything.');
            Failure::internal()->send();
        }
        try {
            $this->respond($db);
        } catch (\Throwable $e) {
            Failure::report($e)->send();
        }
    }

    private function respond(Db $db): never
    {
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
        $app = new App($this->engineDir . '/admin');

        $canReadUnpublished = static function (array $post) use ($authenticator, $capabilities, $request): bool {
            $session = $authenticator->session($request->cookies);
            return $session instanceof Authenticated && $capabilities->can($session->id(), 'edit_post', (int) $post['ID']);
        };
        $resolver = Resolver::fromDb($db, $canReadUnpublished);
        $permalinks = $resolver->permalinks();
        $theme = Theme::active($site, $permalinks, ABSPATH . 'wp-content/themes');
        $pages = $theme === null ? null : PageRenderer::create($db, $theme, $permalinks, $resolver->perPage());

        $posts = new Posts($db);
        $generator = (string) (\Minn\Support\Serialized::field($site->option('_site_transient_update_core'), 'version_checked') ?? '');
        $feeds = new Feeds($db, $site, $posts, new Comments($db), $users, $permalinks, $generator);
        $front = null;
        $cron = new Cron($db, $site, new PostWriter($db, $posts, $site));
        $probes = new ProbeController($site, $posts, $permalinks, $resolver, $feeds, new Sitemaps($db, $site, $permalinks), static function () use (&$front): Response { return $front->notFound(); }, $cron);
        $front = new FrontController($resolver, new Renderer($db, $posts, $permalinks, $resolver->perPage()), $pages, $probes, $cron);

        $router = (new Router())->register(
            new AssetsController($this->engineDir . '/assets'),
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version), $authenticator, $capabilities, $permalinks, $this->version),
            new LoginController($site, $permalinks, $authenticator, $sessions, new AuthCookies($db, $cookie), $users, new LoginThrottle($db), new PasswordReset($users), Mailer::forSite($site)),
            $probes,
            $front,
        );
        $response = (new Kernel($router))->handle($request);
        ($response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404))->send();
    }
}
