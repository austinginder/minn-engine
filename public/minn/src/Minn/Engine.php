<?php

declare(strict_types=1);

namespace Minn;

use Minn\I18n\Gettext;
use Minn\Content\PostRecord;
use Minn\Admin\AdminPlugin;
use Minn\Admin\App;
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
use Minn\Front\AssetsController;
use Minn\Front\ProbeController;
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
use Minn\Rest\Responses;
use Minn\Runtime\CronEvents;
use Minn\Runtime\Handoff;
use Minn\Theme\PageRenderer;
use Minn\Theme\Theme;
use Minn\Auth\Salts;
use Minn\Auth\PasswordReset;
use Minn\Cron\Cron;
use Minn\Content\Reader;
use Minn\Front\CommentPostController;
use Minn\Front\EntryScriptsController;
use Minn\Extension\Extensions;
use Minn\Runtime\AjaxController;
use Minn\Runtime\DebugMode;
use Minn\Runtime\Plugins;
use Minn\Runtime\Recovery;
use Minn\Runtime\Options;
use Minn\Runtime\WpApiMode;
use Minn\Front\FrontParts;
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
    public const WP_VERSION = '7.1.4';

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
        // The server variables made whole before the request is read, so the engine routes the path plugins see.
        Request::fixServerVars();
        $request = Request::fromGlobals();
        // admin-ajax.php (and an upload) declares DOING_AJAX before the reference's boot reads the debug constants.
        Failure::install(DebugMode::defined() + (AjaxController::doing($request) ? ['DOING_AJAX' => true] : []), ['HTTP_ACCEPT' => $request->header('accept'), 'CONTENT_TYPE' => $request->header('content-type')]);
        $this->answer($request)->send();
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
        Runtime::storeOptions($db);
        Runtime::options()->prime(Options::EVERY_REQUEST);
        try {
            $response = $this->handle($db, $request);
            // A HEAD answer goes out without its body; inside the process (rest_do_request) the body stays, as on the reference.
            return $request->method === Method::Head ? $response->withoutBody() : $response;
        } catch (\Throwable $e) {
            return self::restRoute($request) === null ? Failure::report($e) : Failure::reportJson($e);
        }
    }

    /** The REST route a request names, in the path or in ?rest_route=, or null when it is not a REST request. */
    /** The reference's entry scripts that never run the main query, so never answer ?rest_route=. */
    private const SCRIPTS_WITHOUT_REST = ['/wp-cron.php', '/wp-login.php', '/wp-comments-post.php', '/xmlrpc.php', '/wp-links-opml.php', '/wp-mail.php'];

    private static function restRoute(Request $request): ?string
    {
        // ?rest_route= is REST only where the reference's script runs the main query (the front controller,
        // signup, activation, trackback); its other scripts answer as themselves (the 2026-10-23 sweep).
        $scripted = str_starts_with($request->path, '/wp-admin/') || in_array($request->path, self::SCRIPTS_WITHOUT_REST, true);
        $route = $scripted ? null : $request->query('rest_route');
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
        AdminPlugin::settle($this->engineDir, ABSPATH . 'wp-content', Runtime::options());
        $runtime->set('permalinks', $permalinks);
        // The active theme belongs in the container on this path too: a
        // plugin asking for the site's templates over REST gets nothing
        // without it, and the front end had it all along.
        $runtime->set('block_theme', $theme !== null);
        $runtime->set('theme', $theme);
        $runtime->set('engine_routes', static fn (): array => $api->routes());
        // An application password's sign-in is heard again once plugins load (wp_validate_application_password), where they may refuse it.
        $session = $api->caller()->session();
        if ($session?->applicationPassword !== null) {
            $runtime->set('application_password_auth', ['user' => $session->id(), 'item' => $session->applicationPassword, 'password' => Authenticator::basicCredentials($context->request)[1] ?? '']);
        }
        // MINN_WP_API: REST is never a public page, so 'auto' loads the site's plugins here and 'off' only Minn Admin's.
        $mode = WpApiMode::configured();
        Plugins::load($runtime, $mode);
        self::recheckNonce($api, $runtime);
        self::adoptSettledUser($api, $runtime);
        // The extensions register here too: their blocks take part (REST answers and the editor's previews draw them), and their REST routes answer after the engine's.
        $seams = new Seams($db, $site, $context->request, $runtime->reader);
        (new Loader(ABSPATH . 'wp-content', $site))->register($seams);
        Extensions::setBlocks($seams);
        $api->adopt(...$seams->registrations()->rest);
        // As the reference's request parsing leaves things for rest_api_loaded:
        // the route among the query vars, and REST_REQUEST (hook-trace).
        \_minn_rewrite();
        $GLOBALS['wp']->query_vars['rest_route'] = $route;
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }
        // Creating the server fires rest_api_init once; the plugins' routes register there.
        Responses::server();
        // The old switch is still heard, with a notice; the API answers whatever it says, as the reference's does.
        Runtime::hooks()->filterDeprecated('rest_enabled', [true], '4.7.0', 'rest_authentication_errors', sprintf(Gettext::text('The REST API can no longer be completely disabled, the %s filter can be used to restrict access to the API, instead.'), 'rest_authentication_errors'));
    }

    /**
     * A plugin's nonce lifetime (nonce_life) is heard only once plugins
     * load: a cookie caller's nonce is checked again under it, as the
     * reference checks it. A caller the default lifetime refused was nobody
     * while plugins loaded; when the plugin's lifetime proves the nonce, that
     * user reads the request from here (unless plugin code named someone).
     */
    private static function recheckNonce(Api $api, Runtime $runtime): void
    {
        $caller = $api->caller();
        if (Runtime::hooks()->has('nonce_life') === false || !$caller->cookieBound()) {
            return;
        }
        $refused = $caller->id() === 0;
        $caller->recheck();
        $session = $caller->session();
        if ($refused && $session !== null && $runtime->reader->userId === 0) {
            $runtime->identify(Reader::forUser($session->id(), $runtime->capabilities, $runtime->reader->postPassword, $session->token));
            $runtime->set('current_user', null);
            unset($GLOBALS['current_user']);
        }
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
        // A sign-in cookie that settled the user (wp_rest_auth_cookie) binds the request to its nonce whoever
        // else the request named: with none, the request is nobody, as on the reference (security review 2026-10-22).
        $fromCookie = ($GLOBALS['wp_rest_auth_cookie'] ?? null) === true;
        if (!$caller->cookieBound() && !$fromCookie) {
            $api->actingAs($settled, $runtime->reader->sessionToken);
            return;
        }
        if (!$caller->cookieBound()) {
            $caller->resolveAnonymous();
        } elseif ($caller->id() > 0) {
            $caller->resolveInvalidNonce();
        }
        Handoff::becomeUser(0);
        $runtime->identify(Reader::anonymous($runtime->reader->postPassword));
    }

    /** The response the request's own surface produces: REST, then the admin, the login endpoint, and the public site. */
    /**
     * With the database in hand, a fatal during boot can be blamed on the
     * extension it came from. The window in which this applies is opened by
     * Plugins::load. A visitor's request only notes the failure, and only
     * when it carries no query, body, upload or cookie: its path and headers
     * are still the visitor's, and an extension that fails on some input
     * fails for that input alone (security review 2026-10-22; Austin,
     * 2026-10-24). On the first note inside the window the engine asks the
     * site itself, once (Recovery::check); that request carries its token,
     * and when it fails at boot on the same extension, the extension is
     * paused and the next request comes back without it.
     */
    private static function armRecoveryPause(Db $db, Request $request): void
    {
        Failure::onFatal(static function (array $error) use ($db, $request): void {
            $token = $request->header(Recovery::CHECK_HEADER);
            if ($token === null && !self::plain($request)) {
                return;
            }
            $recovery = new Recovery(new Site($db), ABSPATH . 'wp-content');
            $realpaths = array_flip(array_filter((array) ($GLOBALS['wp_plugin_paths'] ?? []), 'is_string'));
            unset($realpaths['']);
            $blamed = $recovery->blameFailure($error['file'], $error['trace'] ?? [], $realpaths);
            if ($blamed === null) {
                return;
            }
            if ($token !== null) {
                if ($recovery->confirmed($blamed, $token) && $recovery->pause($blamed, $error)) {
                    error_log(sprintf('Minn Engine: paused %s %s: it failed while booting, and again on the engine\'s own request; resume it with wp minn recovery resume', $blamed['kind'], $blamed['name']));
                }
                return;
            }
            if ($recovery->strike($blamed)) {
                return;
            }
            error_log(sprintf('Minn Engine: %s %s failed while booting; asking the site once without the request\'s input, which pauses it if it fails there too', $blamed['kind'], $blamed['name']));
            $unanswered = $recovery->check($blamed);
            if ($unanswered !== null) {
                error_log(sprintf('Minn Engine: the site did not answer its own check on %s %s (%s), so it is not paused', $blamed['kind'], $blamed['name'], $unanswered));
            }
        });
    }

    /** Whether a request carries nothing a visitor chose but its path: a GET or HEAD with no query, body, upload or cookie. */
    private static function plain(Request $request): bool
    {
        return in_array($request->method, [Method::Get, Method::Head], true)
            && $request->query === [] && $request->form === [] && $request->files === [] && $request->cookies === [] && $request->body === '';
    }

    private function handle(Db $db, Request $request): Response
    {
        // WordPress's own browser files, from the Minn Legacy Pack while it is active, before any plugin loads.
        if (\Minn\Runtime\LegacyPack::claims($request->path)) {
            $active = Runtime::options()->get('active_plugins');
            $file = \Minn\Runtime\LegacyPack::file(ABSPATH . 'wp-content', $request->path, is_array($active) ? array_values($active) : []);
            if ($file !== null) {
                return \Minn\Runtime\LegacyPack::response($file);
            }
        }
        self::armRecoveryPause($db, $request);

        \Minn\Runtime\EarlyRequest::markThemed($request);

        // One context for the request, whichever surface answers it; each
        // surface resolves its own reader and asks for a context carrying it.
        $site = new Site($db);
        $capabilities = Capabilities::fromDb($db);
        $context = new Context($db, $site, $request, Reader::anonymous(), $capabilities, $this->engineDir, ABSPATH, self::WP_VERSION);

        $route = self::restRoute($request);
        if ($route !== null) {
            $api = Api::forRequest($db, $request);
            $this->bootRuntimeForRest($context, $api, '/' . ltrim($route, '/'));
            return $api->withServerHeaders($api->withDiscovery($api->handle($route)), $route);
        }

        $users = new Users($db);
        $sessions = new Sessions($users);
        $cookie = new Cookie($db, $users, $sessions);
        $authenticator = new Authenticator($cookie, $users);
        $app = AdminPlugin::app($this->engineDir, ABSPATH . 'wp-content');

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
        // MINN_WP_API: under 'auto' and 'off' a public page an extension route answers needs no runtime at all.
        // While Minn Admin's maintenance mode is on, every request boots in full so its holds answer (none is spared).
        $mode = self::maintenance() ? WpApiMode::On : WpApiMode::configured();
        $parts = null;
        if ($mode->sparesRuntime($request)) {
            $parts = FrontParts::build($db, $site, $request, $reader, $capabilities, $users, $app, $session, $permalinks, ABSPATH . 'wp-content');
            $spared = $this->withoutRuntime($request, $parts, self::frontGate($authenticator, $capabilities, $request));
            if ($spared !== null) {
                return $spared;
            }
        }
        // The WordPress runtime: the site's plugins load as code, then the
        // lifecycle actions fire, before the engine's own extensions register.
        $runtime = Runtime::boot(new Runtime($context, isAdmin: \Minn\Runtime\AjaxController::claims($request) || \Minn\Runtime\AdminActionController::claims($request)));
        // Minn Admin's plugin copy is laid once per engine version, before plugins load.
        AdminPlugin::settle($this->engineDir, ABSPATH . 'wp-content', Runtime::options());
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
            return $this->frontPipeline($context, $request, $runtime, $users, $cookie, $authenticator, $app, $session, $resolver, $permalinks, $theme, $classicTheme, $mode, $parts);
        } catch (\Minn\Login\ServeLogin) {
            // A hide-login plugin require'd wp-login.php mid-request: the
            // current request gets the sign-in surface, whatever its path.
            $login = new LoginController($site, $permalinks, $authenticator, new \Minn\Auth\SignIn(new AuthCookies($db, $cookie), new LoginThrottle($db)), $users, new PasswordReset($users));
            return $request->method === Method::Post ? $login->signIn($request) : $login->form($request);
        }
    }

    /** The themed front, admin app, and probe pipeline; split out so a mid-request ServeLogin signal can unwind it cleanly. */
    private function frontPipeline(Context $context, Request $request, Runtime $runtime, Users $users, Cookie $cookie, Authenticator $authenticator, App $app, mixed $session, Resolver $resolver, \Minn\Front\Permalinks $permalinks, ?Theme $theme, ?\Minn\Theme\ClassicTheme $classicTheme, WpApiMode $mode, ?FrontParts $parts): Response
    {
        $db = $context->db;
        $site = $context->site;
        $capabilities = $context->capabilities;
        $plugins = $mode->loadsPlugins($request);
        Plugins::load($runtime, $mode);
        // The parts a spared page built before the boot stand, unless plugin code named another reader since.
        if ($parts === null || $parts->seams->reader->userId !== $runtime->reader->userId) {
            $parts = FrontParts::build($db, $site, $request, $runtime->reader, $capabilities, $users, $app, $session, $permalinks, ABSPATH . 'wp-content');
        }
        $seams = $parts->registered();
        Extensions::set($seams);
        [$appearance, $adminOff, $bar] = [$parts->appearance, $parts->adminOff, $parts->bar];
        $pages = $theme === null ? null : PageRenderer::create($db, $theme, $permalinks, $resolver->perPage(), $bar);
        // A block theme's own PHP templates (a hybrid, a child's page.php) render through the classic renderer when the hierarchy picks one.
        $templateTheme = $classicTheme ?? ($theme === null ? null : \Minn\Theme\ClassicTheme::ofBlockTheme($theme));
        $classic = $templateTheme === null ? null : \Minn\Theme\ClassicRenderer::create($db, $templateTheme, Theme::forStyles($site, $permalinks, ABSPATH . 'wp-content/themes'), $permalinks, $resolver->perPage(), $bar);

        $posts = new Posts($db);
        $front = null;
        // The runtime is booted for this request, so the cron option's due
        // hooks fire through the facade: on every wp-cron.php hit, and after
        // the response of a front request that found something due. The
        // reference fires them in a request of their own where DOING_CRON is
        // set, and a callback may ask for it.
        // Without the site's plugins (MINN_WP_API) their callbacks are not there, so the events wait for a request that has them.
        $fireDueEvents = !$plugins ? null : static function (): int {
            if (!\function_exists('wp_cron')) {
                return 0;
            }
            \defined('DOING_CRON') || \define('DOING_CRON', true);
            return (int) CronEvents::run();
        };
        $cron = Cron::create($db, $site, ABSPATH . 'wp-content', $fireDueEvents);
        $notFound = static function () use (&$front): Response { return $front->notFound(); };
        $themed = static function (\Minn\Front\Resolution $resolution) use (&$front): Response { return $front->themed($resolution); };
        $feedController = new \Minn\Front\FeedController($site);
        $bridge = new \Minn\Theme\MainQueryBridge($resolver->perPage());
        $sitemapController = new \Minn\Front\SitemapController($notFound, $bridge, $themed);
        $front = new FrontController($resolver, $bridge, new Renderer($db, $permalinks), $pages, $feedController, $cron, $classic, $sitemapController, new \Minn\Theme\EmbedRenderer($bridge));

        $router = (new Router(self::frontGate($authenticator, $capabilities, $request)))->register(...[
            new AssetsController($this->engineDir . '/assets'),
            // The sign-in page sits under /minn-admin/, so it registers ahead of the shell's catch-all.
            new LoginController($site, $permalinks, $authenticator, new \Minn\Auth\SignIn(new AuthCookies($db, $cookie), new LoginThrottle($db)), $users, new PasswordReset($users)),
            new AppController($app, new BootPayload($site, $permalinks, $capabilities, $app, $this->version, $appearance, new HiddenIntegrations($users, $capabilities), $siteIcon = new SiteIcon($site, $posts, $permalinks), $theme !== null, new Translations($users, $site, $app, ABSPATH . 'wp-content')), $authenticator, $capabilities, $permalinks, $this->version, $siteIcon, $adminOff),
            new \Minn\Runtime\AjaxController(),
            new \Minn\Runtime\AdminActionController($cookie, $permalinks),
            new ProbeController($site, $permalinks, new SiteIcon($site, $posts, $permalinks), $cron, $bridge),
            $sitemapController,
            new CommentPostController(),
            new EntryScriptsController($resolver, $bridge),
            // An extension's own pages: after every engine route (FRONT_ROUTES), before the themed front's catch-all.
            // Under maintenance only someone Minn Admin lets in reaches them; anyone else meets the themed front's hold.
            ...(self::maintenance() && !$capabilities->can($runtime->reader->userId, 'edit_posts') ? [] : $seams->registrations()->routes),
            $front,
        ]);
        $response = (new Kernel($router))->handle($request);
        return $response ?? Response::html('<!doctype html><title>Not Found</title><p>Not found.', 404);
    }

    /** Whether Minn Admin's maintenance mode is on: the option set and Minn Admin active (its holds decide who it keeps out). */
    private static function maintenance(): bool
    {
        $options = Runtime::options();
        if (in_array((string) $options->raw('minn_admin_maintenance'), ['', '0'], true)) {
            return false;
        }
        // Only then the list the plugin loader reads, and Minn Admin in whatever folder it was unpacked to (minn-admin-main/).
        $active = array_filter((array) $options->filtered('active_plugins'), static fn (mixed $plugin): bool => is_string($plugin) && basename($plugin) === 'minn-admin.php');
        return $active !== [];
    }

    /** The engine's routes a public page meets ahead of the extensions' own, in the front pipeline's order. */
    private const FRONT_ROUTES = [AssetsController::class, LoginController::class, AppController::class, AjaxController::class, \Minn\Runtime\AdminActionController::class, ProbeController::class, \Minn\Front\SitemapController::class, CommentPostController::class, EntryScriptsController::class];

    /**
     * A public page an extension route answers, with no runtime: no facade,
     * no plugins, no boot hooks (MINN_WP_API 'auto' or 'off'). Null when an
     * engine route claims the path or no extension route answers it; the
     * request then boots as any other, on the same registered extensions.
     */
    private function withoutRuntime(Request $request, FrontParts $parts, \Closure $gate): ?Response
    {
        $routes = $parts->registered()->registrations()->routes;
        $router = (new Router($gate))->register(...$routes);
        // The engine's own routes are read only when an extension's matches: a themed page skips the reflection.
        if ($router->matching($request) === [] || Router::claims($request, ...self::FRONT_ROUTES)) {
            return null;
        }
        return (new Kernel($router))->handle($request);
    }

    /**
     * The front's gate: its routes are public or judge their own session; a
     * policy that asks for more is refused outright rather than judged half-way.
     */
    private static function frontGate(Authenticator $authenticator, Capabilities $capabilities, Request $request): \Closure
    {
        return static function (\Minn\Http\Policy $policy) use ($authenticator, $capabilities, $request): void {
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
    }
}
