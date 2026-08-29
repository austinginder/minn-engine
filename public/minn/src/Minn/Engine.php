<?php

declare(strict_types=1);

namespace Minn;

use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Admin\Translations;
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
use Minn\Front\AdminBar;
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
use Minn\Rest\RestUrl;
use Minn\Rest\Types;
use Minn\Theme\PageRenderer;
use Minn\Theme\Theme;
use Minn\Auth\Salts;
use Minn\Auth\PasswordReset;
use Minn\Mail\Mailer;
use Minn\Cron\Cron;
use Minn\Content\PostWriter;
use Minn\Content\Reader;
use Minn\Front\CommentPostController;
use Minn\Extension\Extensions;
use Minn\Extension\Loader;
use Minn\Extension\Seams;

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

        $session = $authenticator->session($request->cookies);
        $readerId = $session instanceof Authenticated ? $session->id() : 0;
        Reader::set(new Reader(
            $readerId,
            $readerId > 0 && $capabilities->can($readerId, 'read_private_posts'),
            $readerId > 0 && $capabilities->can($readerId, 'read_private_pages'),
            static fn (int $postId): bool => $readerId > 0 && $capabilities->can($readerId, 'edit_post', $postId),
            (string) ($request->cookies['wp-postpass_' . md5((string) ($site->option('siteurl') ?? ''))] ?? ''),
            $session instanceof Authenticated ? $session->token : '',
            $readerId > 0 ? $capabilities->rolesOf($readerId) : [],
        ));
        $canReadUnpublished = static fn (array $post): bool => Reader::current()->canEdit((int) $post['ID']);
        $seams = new Seams($db, $site, $request, Reader::current());
        (new Loader(ABSPATH . 'wp-content', $site))->register($seams);
        Extensions::set($seams);
        $resolver = Resolver::fromDb($db, $canReadUnpublished);
        $permalinks = $resolver->permalinks();
        $theme = Theme::active($site, $permalinks, ABSPATH . 'wp-content/themes');
        $appearance = new Appearance($users);
        $adminTypes = new AdminTypes(new Types(new RestUrl($permalinks), (new Loader(ABSPATH . 'wp-content', $site))->declaredTypes()), $capabilities);
        $adminOff = App::switchedOff(ABSPATH . 'wp-content', $site);
        $bar = $adminOff ? null : AdminBar::forReader($session instanceof Authenticated ? $session : null, $capabilities, $site, $permalinks, $app, $appearance, $adminTypes);
        $pages = $theme === null ? null : PageRenderer::create($db, $theme, $permalinks, $resolver->perPage(), $bar);

        $posts = new Posts($db);
        $generator = (string) (\Minn\Support\Serialized::field($site->option('_site_transient_update_core'), 'version_checked') ?? '');
        $feeds = new Feeds($db, $site, $posts, new Comments($db), $users, $permalinks, $generator);
        $front = null;
        $cron = new Cron($db, $site, new PostWriter($db, $posts, $site));
        $probes = new ProbeController($site, $posts, $permalinks, $resolver, $feeds, new Sitemaps($db, $site, $permalinks), static function () use (&$front): Response { return $front->notFound(); }, $cron);
        $front = new FrontController($resolver, new Renderer($db, $posts, $permalinks, $resolver->perPage()), $pages, $probes, $cron);

        $router = (new Router())->register(
            new AssetsController($this->engineDir . '/assets'),
            // The sign-in page sits under /minn-admin/, so it registers ahead of the shell's catch-all.
            new LoginController($site, $permalinks, $authenticator, $sessions, new AuthCookies($db, $cookie), $users, new LoginThrottle($db), new PasswordReset($users), Mailer::forSite($site)),
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version, $appearance, $theme !== null, new Translations($users, $site, $app, ABSPATH . 'wp-content')), $authenticator, $capabilities, $permalinks, $this->version, $adminOff),
            $probes,
            new CommentPostController($site, $posts, new Comments($db), $permalinks, $authenticator, $capabilities, new AuthCookies($db, $cookie)),
            $front,
        );
        $response = (new Kernel($router))->handle($request);
        ($response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404))->send();
    }
}
