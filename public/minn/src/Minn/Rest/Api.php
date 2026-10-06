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
        return EngineRoutes::map($this->router, $this->services->types()->declaredBases());
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
        $postsController = new PostsController($s->db(), $s->posts(), $s->postObject(), $caller);
        $postsWrite = new PostsWriteController($s->posts(), $s->writer(), $s->site(), $s->postObject(), $s->url(), $caller);
        $controllers = [
            new IndexController($s->site(), $s->permalinks(), $s->url(), $router, $s->types()),
            new AbilitiesController($s->url(), $caller),
            new V1Controller($s->db(), $s->notifications(), new CoreStatus($s->site()), new AdminTypes($s->types(), $s->capabilities()), $caller),
            new OverviewController($s->db(), $s->site(), $s->dashboard(), $s->users(), $caller),
            new EditorController($s->writer(), $caller),
            new SiteController($s->db(), $s->site(), $s->posts(), $s->permalinks(), $caller),
            new TermsController($s->db(), $s->terms(), $s->site(), $s->termObject(), $caller),
            new UsersController($s->db(), $s->users(), $s->site(), $s->userObject(), $s->url(), $caller, $s->capabilities()->roles()),
            new ApplicationPasswordsController($s->users(), $s->site(), $s->applicationPasswords(), $s->url(), $caller, $s->schema()),
            new TypesController($s->types()),
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
            new SettingsController(new Settings($s->site()), $caller),
            new CommentsController($s->comments(), $s->posts(), $s->site(), $s->commentObject(), $caller),
            new GlobalStylesController($s->userStyles(), $s->themeStyles(), new GlobalStylesObject($s->revisions(), $s->url(), $caller), $s->revisions(), $caller),
            new RevisionsController($s->posts(), $s->revisions(), $s->url(), $caller),
            new MediaController($s->db(), $s->posts(), $s->mediaWriter(), $s->mediaObject(), $caller),
            new MenusController($s->menus(), new MenuObject($s->menus(), $s->url(), $caller), new MenuItemObject($s->url(), $caller), $caller, $s->url()),
        ];
        $templates = $s->templates();
        $templateWriter = $s->templateWriter();
        if ($templates !== null && $templateWriter !== null) {
            $controllers[] = new TemplatesController($templates, $templateWriter, new TemplateObject($templates, $s->posts(), $s->url(), $caller), $caller);
        }
        return [...$controllers, $postsController, $postsWrite, new NavigationController($postsController, $postsWrite), new BlocksController($postsController, $postsWrite, $s->posts(), $caller), new DeclaredPostsController($s->types(), $postsController, $postsWrite)];
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
     * the in-process calls plugin code makes; a user who no longer exists
     * leaves the caller as the request itself resolves it.
     */
    public function actingAs(int $userId, string $token): self
    {
        $user = $this->services->users()->find($userId);
        if ($user !== null) {
            $this->services->caller()->resolveAs(new Authenticated($user, $token));
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
            if ($response !== null && Runtime::booted() && ($request->path === '/' || preg_match('#^/[a-z0-9-]+/v\d+$#', $request->path) === 1)) {
                $response = RuntimeRoutes::mergeIndex($response);
            }
            $response ??= Reply::error(RestError::noRoute());
            return Runtime::booted() ? RuntimeRoutes::serve($request, $response) : $response;
        } catch (RestError $error) {
            return Reply::error($error);
        }
    }

    /**
     * The engine's own answer to a route, or null when no engine route
     * takes it; the runtime's table is never consulted. This is what the
     * runtime's server calls for a core route, so a route the engine
     * declines cannot bounce between the two.
     */
    public function handleEngineOnly(string $route): ?Response
    {
        try {
            return $this->engineResponse($this->request->withPath('/' . trim($route, '/')));
        } catch (RestError $error) {
            return Reply::error($error);
        }
    }

    private function engineResponse(Request $request): ?Response
    {
        $response = $this->router->dispatch($request);
        return $response === null ? null : $this->embed->decorate($request, $response);
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
