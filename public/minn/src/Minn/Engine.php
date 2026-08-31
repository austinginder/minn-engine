<?php

declare(strict_types=1);

namespace Minn;

use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Admin\Translations;
use Minn\Admin\AppController;
use Minn\Admin\HiddenIntegrations;
use Minn\Content\Inventory;
use Minn\Admin\Packages;
use Minn\Admin\Updates;
use Minn\Admin\BootPayload;
use Minn\Auth\Authenticated;
use Minn\Auth\AuthCookies;
use Minn\Http\Method;
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
use Minn\Runtime\Plugins;
use Minn\Runtime\Runtime;
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
    /** The WordPress release whose contracts the runtime speaks; wp-includes/version.php says the same. */
    public const WP_VERSION = '7.1';

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

    /**
     * The WordPress runtime for a REST request: the caller the REST layer
     * resolved is the reader, plugins load, and rest_api_init fires so
     * their routes answer after the engine's own.
     */
    private function bootRuntimeForRest(Db $db, Request $request, Api $api): void
    {
        $site = new Site($db);
        $capabilities = $api->caller()->capabilities();
        $readerId = $api->caller()->id();
        Reader::set(new Reader(
            $readerId,
            $readerId > 0 && $capabilities->can($readerId, 'read_private_posts'),
            $readerId > 0 && $capabilities->can($readerId, 'read_private_pages'),
            static fn (int $postId): bool => $readerId > 0 && $capabilities->can($readerId, 'edit_post', $postId),
            '',
            $api->caller()->session()?->token ?? '',
            $readerId > 0 ? $capabilities->rolesOf($readerId) : [],
        ));
        $runtime = Runtime::boot(new Runtime($db, $site, $request, Reader::current(), $capabilities, $this->engineDir, ABSPATH, self::WP_VERSION));
        $runtime->set('permalinks', \Minn\Front\Permalinks::fromDb($db));
        $runtime->set('block_theme', Theme::active($site, \Minn\Front\Permalinks::fromDb($db), ABSPATH . 'wp-content/themes') !== null);
        $runtime->set('engine_routes', static fn (): array => $api->routes());
        Plugins::load($runtime);
        // Creating the server fires rest_api_init once; the plugins' routes register there.
        \rest_get_server();
    }

    private function respond(Db $db): never
    {
        $request = Request::fromGlobals();

        $route = $request->query('rest_route');
        if ($route === null && str_starts_with($request->path, '/wp-json')) {
            $route = substr($request->path, strlen('/wp-json')) ?: '/';
        }
        if ($route !== null) {
            $api = Api::forRequest($db, $request);
            $this->bootRuntimeForRest($db, $request, $api);
            $api->handle($route)->send();
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
        $resolver = Resolver::fromDb($db, $canReadUnpublished);
        $permalinks = $resolver->permalinks();
        $theme = Theme::active($site, $permalinks, ABSPATH . 'wp-content/themes');
        // The WordPress runtime: the site's plugins load as code, then the
        // lifecycle actions fire, before the engine's own extensions register.
        $runtime = Runtime::boot(new Runtime($db, $site, $request, Reader::current(), $capabilities, $this->engineDir, ABSPATH, self::WP_VERSION));
        $runtime->set('block_theme', $theme !== null);
        $runtime->set('theme', $theme);
        $runtime->set('permalinks', $permalinks);
        $runtime->set('engine_routes', static fn (): array => Api::forRequest($db, $request)->routes());
        $classicTheme = $theme === null ? \Minn\Theme\ClassicTheme::active($site, ABSPATH . 'wp-content/themes') : null;
        if ($classicTheme !== null) {
            // The classic head defaults register at setup_theme, ahead of the theme's own wp_head hooks.
            $runtime->set('classic_theme', $classicTheme->stylesheet);
        }
        try {
            $this->frontPipeline($db, $request, $runtime, $site, $users, $sessions, $cookie, $authenticator, $capabilities, $app, $session, $resolver, $permalinks, $theme, $classicTheme);
        } catch (\Minn\Login\ServeLogin) {
            // A hide-login plugin require'd wp-login.php mid-request: the
            // current request gets the sign-in surface, whatever its path.
            $login = new LoginController($site, $permalinks, $authenticator, $sessions, new AuthCookies($db, $cookie), $users, new LoginThrottle($db), new PasswordReset($users), Mailer::forSite($site));
            ($request->method === Method::Post ? $login->signIn($request) : $login->form($request))->send();
        }
    }

    /** The themed front, admin app, and probe pipeline; split out so a mid-request ServeLogin signal can unwind it cleanly. */
    private function frontPipeline(Db $db, Request $request, Runtime $runtime, Site $site, Users $users, Sessions $sessions, Cookie $cookie, Authenticator $authenticator, Capabilities $capabilities, App $app, mixed $session, Resolver $resolver, \Minn\Front\Permalinks $permalinks, ?Theme $theme, ?\Minn\Theme\ClassicTheme $classicTheme): never
    {
        Plugins::load($runtime);
        $seams = new Seams($db, $site, $request, Reader::current());
        (new Loader(ABSPATH . 'wp-content', $site))->register($seams);
        Extensions::set($seams);
        $appearance = new Appearance($users);
        $adminTypes = new AdminTypes(new Types(new RestUrl($permalinks), (new Loader(ABSPATH . 'wp-content', $site))->declaredTypes()), $capabilities);
        $adminOff = App::switchedOff(ABSPATH . 'wp-content', $site);
        $bar = $adminOff ? null : AdminBar::forReader($session instanceof Authenticated ? $session : null, $capabilities, $site, $permalinks, $app, $appearance, $adminTypes);
        $pages = $theme === null ? null : PageRenderer::create($db, $theme, $permalinks, $resolver->perPage(), $bar);
        $classic = $classicTheme === null ? null : \Minn\Theme\ClassicRenderer::create($db, $classicTheme, Theme::forStyles($site, $permalinks, ABSPATH . 'wp-content/themes'), $permalinks, $resolver->perPage(), $bar);

        $posts = new Posts($db);
        $generator = (string) (\Minn\Support\Serialized::field($site->option('_site_transient_update_core'), 'version_checked') ?? '');
        $feeds = new Feeds($db, $site, $posts, new Comments($db), $users, $permalinks, $generator);
        $front = null;
        $cron = new Cron($db, $site, new PostWriter($db, $posts, $site), new Updates($site, new Inventory(ABSPATH . 'wp-content', $site), new Packages($site, ABSPATH . 'wp-content'), ABSPATH . 'wp-content', $permalinks->url('/'), self::WP_VERSION));
        $probes = new ProbeController($site, $posts, $permalinks, $resolver, $feeds, new Sitemaps($db, $site, $permalinks), static function () use (&$front): Response { return $front->notFound(); }, $cron);
        $front = new FrontController($resolver, new Renderer($db, $posts, $permalinks, $resolver->perPage()), $pages, $probes, $cron, $classic);

        $router = (new Router())->register(
            new AssetsController($this->engineDir . '/assets'),
            // The sign-in page sits under /minn-admin/, so it registers ahead of the shell's catch-all.
            new LoginController($site, $permalinks, $authenticator, $sessions, new AuthCookies($db, $cookie), $users, new LoginThrottle($db), new PasswordReset($users), Mailer::forSite($site)),
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version, $appearance, new HiddenIntegrations($users, $capabilities), $posts, $theme !== null, new Translations($users, $site, $app, ABSPATH . 'wp-content')), $authenticator, $capabilities, $permalinks, $this->version, $adminOff),
            $probes,
            new CommentPostController($site, $posts, new Comments($db), $permalinks, $authenticator, $capabilities, new AuthCookies($db, $cookie)),
            $front,
        );
        $response = (new Kernel($router))->handle($request);
        ($response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404))->send();
    }
}
