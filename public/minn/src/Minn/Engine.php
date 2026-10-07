<?php

declare(strict_types=1);

namespace Minn;

use Minn\Content\PostRecord;
use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Admin\Translations;
use Minn\Admin\AppController;
use Minn\Admin\HiddenIntegrations;
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
use Minn\Content\SiteIcon;
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
use Minn\Content\Reader;
use Minn\Front\CommentPostController;
use Minn\Extension\Extensions;
use Minn\Runtime\Plugins;
use Minn\Runtime\Recovery;
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

    /**
     * The front door: answer the request that arrived and send it. This is
     * the only place the engine touches the world, so everything under it
     * is a function of a request, callable more than once in a process.
     */
    public function serve(): void
    {
        Failure::install();
        $this->answer(Request::fromGlobals())->send();
    }

    /**
     * The response for one request, whatever happens: a database that
     * cannot be reached, salts that are not set, or a failure anywhere
     * underneath, each answered in the language the request asked in.
     */
    public function answer(Request $request): Response
    {
        try {
            $db = Db::shared();
        } catch (\mysqli_sql_exception $e) {
            error_log('Minn Engine: database connection failed: ' . $e->getMessage());
            return Failure::databaseUnavailable();
        }
        if (!Salts::configured()) {
            error_log('Minn Engine: wp-config.php is missing its unique keys and salts (AUTH_KEY … NONCE_SALT); refusing to sign anything.');
            return Failure::internal();
        }
        try {
            $response = $this->handle($db, $request);
            // A HEAD answer goes out without its body; inside the process (rest_do_request) the body stays, as on the reference.
            return $request->method === Method::Head ? $response->withoutBody() : $response;
        } catch (\Throwable $e) {
            return self::restRoute($request) === null ? Failure::report($e) : Failure::reportJson($e);
        }
    }

    /** The REST route a request names, in the path or in ?rest_route=, or null when it is not a REST request. */
    private static function restRoute(Request $request): ?string
    {
        $route = $request->query('rest_route');
        if ($route === null && str_starts_with($request->path, '/wp-json')) {
            $route = substr($request->path, strlen('/wp-json')) ?: '/';
        }
        return $route;
    }

    /**
     * The WordPress runtime for a REST request: the caller the REST layer
     * resolved is the reader, plugins load, and rest_api_init fires so
     * their routes answer after the engine's own.
     */
    private function bootRuntimeForRest(Context $context, Api $api, string $route): void
    {
        $reader = Reader::forUser($api->caller()->id(), $context->capabilities, '', $api->caller()->session()?->token ?? '');
        $site = $context->site;
        $db = $context->db;
        $permalinks = \Minn\Front\Permalinks::fromDb($db);
        $theme = Theme::active($site, $permalinks, $context->themesDir());
        $runtime = Runtime::boot(new Runtime($context->withReader($reader)));
        $runtime->set('permalinks', $permalinks);
        // The active theme belongs in the container on this path too: a
        // plugin asking for the site's templates over REST gets nothing
        // without it, and the front end had it all along.
        $runtime->set('block_theme', $theme !== null);
        $runtime->set('theme', $theme);
        $runtime->set('engine_routes', static fn (): array => $api->routes());
        Plugins::load($runtime);
        self::adoptSettledUser($api, $runtime);
        // As the reference's request parsing leaves things for rest_api_loaded:
        // the route among the query vars, and REST_REQUEST (hook-trace).
        \_minn_rewrite();
        $GLOBALS['wp']->query_vars['rest_route'] = $route;
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }
        // Creating the server fires rest_api_init once; the plugins' routes register there.
        \rest_get_server();
    }

    /**
     * The user plugin code settled (determine_current_user) as the REST
     * caller, as the reference's cookie check leaves it: a request a
     * plugin signs in by its own token is that user; a sign-in cookie
     * vouches only for its own user (another one fails its nonce, and
     * without a nonce the request is nobody, plugin code included).
     */
    private static function adoptSettledUser(Api $api, Runtime $runtime): void
    {
        $caller = $api->caller();
        $settled = $runtime->reader->userId;
        if ($settled === $caller->id()) {
            return;
        }
        if (!$caller->cookieBound()) {
            $api->actingAs($settled, $runtime->reader->sessionToken);
            return;
        }
        if ($caller->id() > 0) {
            $caller->resolveInvalidNonce();
        }
        \wp_set_current_user(0);
        $runtime->identify(Reader::anonymous($runtime->reader->postPassword));
    }

    /** The response the request's own surface produces: REST, then the admin, the login endpoint, and the public site. */
    private function handle(Db $db, Request $request): Response
    {
        // With the database in hand, a fatal during boot can be blamed on
        // the extension it came from; the second inside ten minutes pauses
        // it, so the next request comes back without it. The window in
        // which this applies is opened by Plugins::load.
        Failure::onFatal(static function (array $error) use ($db): void {
            $recovery = new Recovery(new Site($db), ABSPATH . 'wp-content');
            $realpaths = Runtime::booted() ? (array) Runtime::current()->get('plugin_realpaths', []) : [];
            $blamed = $recovery->blame($error['file'], $realpaths);
            if ($blamed === null) {
                return;
            }
            if (!$recovery->strike($blamed)) {
                error_log(sprintf('Minn Engine: %s %s failed while booting; a second failure within ten minutes pauses it', $blamed['kind'], $blamed['name']));
                return;
            }
            if (!$recovery->pause($blamed, $error)) {
                return;
            }
            error_log(sprintf('Minn Engine: paused %s %s after a second fatal error while booting; resume it with wp minn recovery resume', $blamed['kind'], $blamed['name']));
        });

        // One context for the request, whichever surface answers it; each
        // surface resolves its own reader and asks for a context carrying it.
        $site = new Site($db);
        $capabilities = Capabilities::fromDb($db);
        $context = new Context($db, $site, $request, Reader::anonymous(), $capabilities, $this->engineDir, ABSPATH, self::WP_VERSION);

        $route = self::restRoute($request);
        if ($route !== null) {
            $api = Api::forRequest($db, $request);
            $this->bootRuntimeForRest($context, $api, '/' . ltrim($route, '/'));
            return $api->withDiscovery($api->handle($route));
        }

        $users = new Users($db);
        $sessions = new Sessions($users);
        $cookie = new Cookie($db, $users, $sessions);
        $authenticator = new Authenticator($cookie, $users);
        $app = new App($this->engineDir . '/admin');

        $session = $authenticator->session($request->cookies);
        $reader = Reader::forUser(
            $session instanceof Authenticated ? $session->id() : 0,
            $capabilities,
            (string) ($request->cookies['wp-postpass_' . md5((string) ($site->option('siteurl') ?? ''))] ?? ''),
            $session instanceof Authenticated ? $session->token : '',
        );
        $context = $context->withReader($reader);
        // The runtime boots below with this context, and Reader::current() reads it from there.
        // The reader plugins settle (determine_current_user) is the one asked, not the session's.
        $canReadUnpublished = static fn (PostRecord $post): bool => Reader::current()->canEdit((int) $post['ID']);
        $resolver = Resolver::fromDb($db, $canReadUnpublished);
        $permalinks = $resolver->permalinks();
        $theme = Theme::active($site, $permalinks, $context->themesDir());
        // The WordPress runtime: the site's plugins load as code, then the
        // lifecycle actions fire, before the engine's own extensions register.
        $runtime = Runtime::boot(new Runtime($context, isAdmin: \Minn\Runtime\AjaxController::claims($request)));
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
            return $this->frontPipeline($context, $request, $runtime, $users, $sessions, $cookie, $authenticator, $app, $session, $resolver, $permalinks, $theme, $classicTheme);
        } catch (\Minn\Login\ServeLogin) {
            // A hide-login plugin require'd wp-login.php mid-request: the
            // current request gets the sign-in surface, whatever its path.
            $login = new LoginController($site, $permalinks, $authenticator, new \Minn\Auth\SignIn($sessions, new AuthCookies($db, $cookie), new LoginThrottle($db)), $users, new PasswordReset($users), Mailer::forSite($site));
            return $request->method === Method::Post ? $login->signIn($request) : $login->form($request);
        }
    }

    /** The themed front, admin app, and probe pipeline; split out so a mid-request ServeLogin signal can unwind it cleanly. */
    private function frontPipeline(Context $context, Request $request, Runtime $runtime, Users $users, Sessions $sessions, Cookie $cookie, Authenticator $authenticator, App $app, mixed $session, Resolver $resolver, \Minn\Front\Permalinks $permalinks, ?Theme $theme, ?\Minn\Theme\ClassicTheme $classicTheme): Response
    {
        $db = $context->db;
        $site = $context->site;
        $capabilities = $context->capabilities;
        Plugins::load($runtime);
        $seams = new Seams($db, $site, $request, $runtime->reader);
        $loader = new Loader(ABSPATH . 'wp-content', $site);
        $loader->register($seams);
        Extensions::set($seams);
        $appearance = new Appearance($users);
        $adminTypes = new AdminTypes(new Types(new RestUrl($permalinks), $loader->declaredTypes()), $capabilities);
        $adminOff = App::switchedOff(ABSPATH . 'wp-content', $site);
        $bar = $adminOff ? null : AdminBar::forReader($session instanceof Authenticated ? $session : null, $capabilities, $site, $permalinks, $app, $appearance, $adminTypes);
        $pages = $theme === null ? null : PageRenderer::create($db, $theme, $permalinks, $resolver->perPage(), $bar);
        $classic = $classicTheme === null ? null : \Minn\Theme\ClassicRenderer::create($db, $classicTheme, Theme::forStyles($site, $permalinks, ABSPATH . 'wp-content/themes'), $permalinks, $resolver->perPage(), $bar);

        $posts = new Posts($db);
        $generator = (string) (\Minn\Support\Serialized::field($site->option('_site_transient_update_core'), 'version_checked') ?? '');
        $feeds = new Feeds($db, $site, $posts, new Comments($db), $users, $permalinks, $generator);
        $front = null;
        // The runtime is booted for this request, so the cron option's due
        // hooks fire through the facade: on every wp-cron.php hit, and after
        // the response of a front request that found something due. The
        // reference fires them in a request of their own where DOING_CRON is
        // set, and a callback may ask for it.
        $fireDueEvents = static function (): int {
            if (!\function_exists('wp_cron')) {
                return 0;
            }
            \defined('DOING_CRON') || \define('DOING_CRON', true);
            return (int) \wp_cron();
        };
        $cron = Cron::create($db, $site, ABSPATH . 'wp-content', $permalinks->url('/'), self::WP_VERSION, $fireDueEvents);
        $notFound = static function () use (&$front): Response { return $front->notFound(); };
        $feedController = new \Minn\Front\FeedController($site, $posts, $permalinks, $resolver, $feeds, $notFound);
        $front = new FrontController($resolver, new Renderer($db, $posts, $permalinks, $resolver->perPage()), $pages, $feedController, $cron, $classic);

        // The front's routes are public or judge their own session; a policy that asks
        // for more is refused outright rather than judged half-way.
        $gate = static function (\Minn\Http\Policy $policy) use ($authenticator, $capabilities, $request): void {
            $session = $authenticator->session($request->cookies);
            $userId = $session instanceof Authenticated ? $session->id() : 0;
            if ($userId === 0) {
                throw new \Minn\RestError($policy->signIn, $policy->signInMessage, 401);
            }
            foreach ($policy->capabilities() as $capability) {
                if (!$capabilities->can($userId, $capability)) {
                    throw new \Minn\RestError($policy->refuse, $policy->message, 403);
                }
            }
        };
        $router = (new Router($gate))->register(
            new AssetsController($this->engineDir . '/assets'),
            // The sign-in page sits under /minn-admin/, so it registers ahead of the shell's catch-all.
            new LoginController($site, $permalinks, $authenticator, new \Minn\Auth\SignIn($sessions, new AuthCookies($db, $cookie), new LoginThrottle($db)), $users, new PasswordReset($users), Mailer::forSite($site)),
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version, $appearance, new HiddenIntegrations($users, $capabilities), new SiteIcon($site, $posts, $permalinks), $theme !== null, new Translations($users, $site, $app, ABSPATH . 'wp-content')), $authenticator, $capabilities, $permalinks, $this->version, $adminOff),
            new \Minn\Runtime\AjaxController(),
            new ProbeController($site, $permalinks, new SiteIcon($site, $posts, $permalinks), $cron),
            new \Minn\Front\SitemapController(new Sitemaps($db, $site, $permalinks), $notFound),
            $feedController,
            new CommentPostController($site, $posts, new Comments($db), $permalinks, $authenticator, $capabilities, new AuthCookies($db, $cookie)),
            $front,
        );
        $response = (new Kernel($router))->handle($request);
        return $response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404);
    }
}
