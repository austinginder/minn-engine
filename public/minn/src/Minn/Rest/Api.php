<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\Authenticated;
use Minn\Admin\AdminTypes;
use Minn\Admin\LanguageController;
use Minn\Admin\UpdatesController;
use Minn\Admin\SystemController;
use Minn\Admin\BundleController;
use Minn\Admin\EditorController;
use Minn\Admin\OverviewController;
use Minn\Admin\PreferencesController;
use Minn\Admin\SiteController;
use Minn\Admin\StructureController;
use Minn\Admin\ThemesController;
use Minn\Admin\PackagesController;
use Minn\Admin\RenderController;
use Minn\Admin\SessionsController;
use Minn\Ops\CoreStatus;
use Minn\Admin\V1Controller;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Router;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * The REST API: wires the controllers for one request and dispatches a
 * route. Errors thrown anywhere underneath become the reference's error
 * payload under the reference's headers.
 */
final readonly class Api
{
    public function __construct(
        private Request $request,
        private Router $router,
        private Services $services,
        private Embed $embed,
    ) {
    }

    /**
     * The engine's routes in the reference's index form.
     *
     * @return array<string, list<string>> the engine's routes in the reference's form, route => methods
     */
    public function routes(): array
    {
        return EngineRoutes::map($this->router, $this->services->types()->routeBases());
    }

    /** The API for one request: the shared services and the route table. */
    public static function forRequest(Db $db, Request $request): self
    {
        $s = Services::forRequest($db, $request);
        // Once plugins are loaded (the runtime boots after this is built), the REST server's filters run around Minn's own routes as they run around every route on the reference.
        $router = new Router((new PolicyGate($s->caller(), $s->subjects(), $s->types()))->closure(), (new ArgCheck($s->schema()))->closure(), new RuntimeEnvelope($s->types()));
        $router->register(...self::controllers($s, $router));
        return new self($request, $router, $s, new Embed($router, $s->types(), $s->taxonomies()));
    }

    /**
     * The route table: every controller, built from the shared services.
     * Templates join only under a block theme.
     *
     * @return list<object>
     */
    private static function controllers(Services $s, Router $router): array
    {
        $caller = $s->caller();
        $terms = new TermsController($s->db(), $s->terms(), $s->site(), $s->termObject(), $caller);
        $postsController = new PostsController($s->db(), $s->posts(), $s->postObject(), $caller);
        $postsWrite = new PostsWriteController($s->posts(), $s->writer(), $s->site(), $s->postObject(), $s->url(), $caller);
        $controllers = [
            new IndexController($s->site(), $s->permalinks(), $s->url(), new RouteCatalogue($router, $s->types(), $s->url())),
            new AbilitiesController($s->url(), $caller),
            new V1Controller($s->db(), $s->notifications(), new CoreStatus($s->site()), new AdminTypes($s->types(), $s->capabilities()), $caller),
            new OverviewController($s->db(), $s->site(), $s->dashboard(), $s->users(), $caller),
            new EditorController($s->writer(), $caller),
            new SiteController($s->db(), $s->site(), $s->posts(), $s->permalinks(), $caller),
            $terms,
            new UsersController($s->db(), $s->users(), $s->site(), $s->userObject(), $s->url(), $caller, $s->capabilities()->roles()),
            new ApplicationPasswordsController($s->users(), $s->site(), $s->applicationPasswords(), $s->url(), $caller, $s->schema()),
            new TypesController($s->types()),
            new StatusesController($s->url(), $caller),
            new InstalledThemesController($s->url(), $caller),
            new BlockTypesController($s->url(), $caller),
            new BlockRendererController($s->schema(), $caller),
            new OEmbedController($caller),
            new SidebarsController($s->url(), $caller),
            new WidgetsController(new WidgetObject($s->url()), $caller),
            new BatchController($router, static function (Request $sub) use ($s): Response {
                // Each request of a batch is answered as it would be on its own, as the same caller.
                $api = self::forRequest($s->db(), $sub);
                $session = $s->caller()->session();
                if ($session !== null) {
                    $api->actingAs($session->id(), $session->token);
                }
                return $api->handle($sub->path);
            }, new ArgCheck($s->schema()), $s->types()),
            new TaxonomiesController($s->taxonomies(), $caller),
            new SearchController($s->db(), $s->types(), $s->permalinks(), $s->url(), $caller),
            new PluginsController($s->site(), $s->inventory(), $s->loader(), $s->url(), $caller, $s->packages(), $s->contentDir()),
            new SessionsController($s->users(), $s->sessions(), $caller),
            new StructureController($s->db(), $s->types(), $s->taxonomies(), $caller),
            new ThemesController($s->site(), $s->permalinks(), $s->updates(), $caller, $s->contentDir()),
            new PreferencesController($s->appearance(), $s->hiddenIntegrations(), $caller),
            new BundleController($s->app(), $caller),
            new LanguageController($s->translations(), $s->users(), $s->site(), $s->capabilities(), $caller),
            new PackagesController($s->packages(), $s->site(), $caller),
            new UpdatesController($s->updates(), $caller),
            new RenderController($s->db(), $s->site(), $s->posts(), $s->permalinks(), $caller, $s->contentDir() . '/themes'),
            new SystemController($s->diagnostics(), $s->logs(), $caller),
            new SettingsController(new Settings($s->site()), $caller, new LiveSettings($s->schema())),
            new CommentsController($s->comments(), $s->posts(), $s->site(), $s->commentObject(), $caller),
            new GlobalStylesController($s->userStyles(), $s->themeStyles(), new GlobalStylesObject($s->revisions(), $s->url(), $caller), $s->revisions(), $caller),
            new RevisionsController($s->posts(), $s->revisions(), $s->url(), $caller),
            new MediaController($s->posts(), $s->mediaWriter(), $s->mediaObject(), $caller, $postsController),
            new MenusController($s->menus(), new MenuObject($s->menus(), $s->url(), $caller), new MenuItemObject($s->url(), $caller), $caller, $s->url()),
        ];
        $templates = $s->templates();
        $templateWriter = $s->templateWriter();
        if ($templates !== null && $templateWriter !== null) {
            $controllers[] = new TemplatesController($templates, $templateWriter, new TemplateObject($templates, $s->posts(), $s->url(), $caller), $caller);
        }
        return [...$controllers, $postsController, $postsWrite, new NavigationController($postsController, $postsWrite), new BlocksController($postsController, $postsWrite, $s->posts(), $caller), new DeclaredPostsController($s->types(), $postsController, $postsWrite), new DeclaredTermsController($terms)];
    }

    /** Who is making this request. */
    public function caller(): Caller
    {
        return $this->services->caller();
    }

    /** The wp/v2 post shape. */
    public function postObject(): PostObject
    {
        return $this->services->postObject();
    }

    /** The wp/v2 term shape. */
    public function termObject(): TermObject
    {
        return $this->services->termObject();
    }

    /** The wp/v2 user shape. */
    public function userObject(): UserObject
    {
        return $this->services->userObject();
    }

    /** The post types the surface knows. */
    public function types(): Types
    {
        return $this->services->types();
    }

    /**
     * Runs the request as a user already proven by the outer request, for
     * the in-process calls plugin code makes, or as the user plugin code
     * settled (determine_current_user; 0 for nobody); a user who no longer
     * exists leaves the caller as the request itself resolves it.
     */
    public function actingAs(int $userId, string $token): self
    {
        $user = $userId > 0 ? $this->services->users()->find($userId) : null;
        if ($user !== null) {
            $this->services->caller()->resolveAs(new Authenticated($user, $token));
        } elseif ($userId === 0) {
            $this->services->caller()->resolveAnonymous();
        }
        return $this;
    }

    /** Resolves a REST route (from the path or from ?rest_route=) to a response. */
    public function handle(string $route): Response
    {
        $request = $this->request->withPath('/' . trim($route, '/'));
        try {
            // Plugin code gets the reference's say before the engine's routes: an
            // authentication refusal, a pre-dispatch answer, or a removed endpoint.
            if (Runtime::booted()) {
                RuntimePrepare::answering(RuntimeRoutes::wpRequest($request));
            }
            $response = Runtime::booted() ? RuntimeRoutes::gate($request) : null;
            if ($response === null) {
                try {
                    $response = $this->engineResponse($request);
                } catch (RestError $error) {
                    return $this->withAllow($request, Reply::error($error));
                }
                $response = $response === null ? null : $this->withAllow($request, $response);
            }
            if ($response === null && Runtime::booted()) {
                $response = RuntimeRoutes::dispatch($request);
            }
            if ($response !== null && Runtime::booted() && ($request->path === '/' || preg_match('#^/[a-z0-9-]+/(?:v\d+|\d+\.\d+)$#', $request->path) === 1)) {
                $response = RuntimeRoutes::mergeIndex($response);
            }
            $response ??= Reply::error(RestError::noRoute());
            return Runtime::booted() ? RuntimeRoutes::serve($request, $response) : $response;
        } catch (RestError $error) {
            return Reply::error($error);
        }
    }

    /** A REST answer as it leaves over HTTP: pointing at the API's root, unless it carries a Link header of its own (a batch's parts do not). */
    public function withDiscovery(Response $response): Response
    {
        $links = array_change_key_case($response->headers);
        return isset($links['link']) ? $response : $response->withHeader('Link', '<' . $this->services->url()->to('/') . '>; rel="https://api.w.org/"');
    }

    /**
     * The engine's own answer to a route, or null when no engine route
     * takes it; the runtime's table is never consulted. This is what the
     * runtime's server calls for a core route, so a route the engine
     * declines cannot bounce between the two. $as is the caller's own
     * request object, which the route's parameters are set on.
     */
    public function handleEngineOnly(string $route, ?\WP_REST_Request $as = null): ?Response
    {
        $request = $this->request->withPath('/' . trim($route, '/'));
        if ($as !== null) {
            RuntimeRoutes::adopt($request, $as);
        }
        try {
            $response = $this->engineResponse($request);
            return $response === null ? null : $this->withAllow($request, $response);
        } catch (RestError $error) {
            return $this->withAllow($request, Reply::error($error));
        }
    }

    private function engineResponse(Request $request): ?Response
    {
        if ($request->method === Method::Options) {
            return $this->options($request);
        }
        $response = $this->router->dispatch($request);
        return $response === null ? null : $this->withPageLinks($request, $this->embed->decorate($request, $response));
    }

    /**
     * A collection's Link header as the reference sends it: the previous page
     * (no further than the last) and the next, each the collection's URL
     * with the request's own parameters and the page swapped in.
     */
    private function withPageLinks(Request $request, Response $response): Response
    {
        $pages = $response->headers['X-WP-TotalPages'] ?? null;
        if ($pages === null || $response->status !== 200 || !in_array($request->method, [Method::Get, Method::Head], true) || !Runtime::booted()) {
            return $response;
        }
        $pages = (int) $pages;
        $page = max(1, (int) ($request->query['page'] ?? 1));
        // The users list reads its page from the offset, which a given offset sets.
        if ($request->path === '/wp/v2/users' && isset($request->query['offset'])) {
            $page = (int) ceil((int) $request->query['offset'] / max(1, (int) ($request->query['per_page'] ?? 10)) + 1);
        }
        // The parameters as the request object holds them (in process, as the caller set them).
        $base = \add_query_arg(\urlencode_deep(RuntimeRoutes::wpRequest($request)->get_query_params()), $this->services->url()->to($request->path));
        $links = [];
        if ($page > 1) {
            $links[] = '<' . \add_query_arg('page', min($page - 1, $pages), $base) . '>; rel="prev"';
        }
        if ($pages > $page) {
            $links[] = '<' . \add_query_arg('page', $page + 1, $base) . '>; rel="next"';
        }
        return $links === [] ? $response : $response->withHeader('Link', implode(', ', $links));
    }

    /**
     * An OPTIONS request as the reference answers it: the description of
     * the first route whose pattern takes the path, whoever asks (the Allow
     * header says what the caller may do); null when no engine route takes
     * it, or when a plugin removed rest_handle_options_request (which is
     * what answers OPTIONS on the reference).
     */
    private function options(Request $request): ?Response
    {
        if (Runtime::booted() && \has_filter('rest_pre_dispatch', 'rest_handle_options_request') === false) {
            return null;
        }
        $entry = (new RouteCatalogue($this->router, $this->services->types(), $this->services->url()))->describing($request->path);
        return $entry === null ? null : Reply::item($entry, null);
    }

    /**
     * The Allow header as the reference sends it: the methods the caller
     * may use on the matched path, and no header when there are none.
     */
    private function withAllow(Request $request, Response $response): Response
    {
        $methods = $this->router->allowed($request);
        return $methods === [] ? $response->withoutHeader('Allow') : $response->withHeader('Allow', implode(', ', $methods));
    }
}
