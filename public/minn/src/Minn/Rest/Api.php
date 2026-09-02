<?php

declare(strict_types=1);

namespace Minn\Rest;

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
use Minn\Admin\CoreStatus;
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
        return EngineRoutes::map($this->router);
    }

    /** The API for one request: the shared services and the route table. */
    public static function forRequest(Db $db, Request $request): self
    {
        $s = Services::forRequest($db, $request);
        $router = new Router();
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
            new IndexController($s->site(), $s->permalinks(), $s->url(), $router),
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
        return [...$controllers, $postsController, $postsWrite, new NavigationController($postsController, $postsWrite), new DeclaredPostsController($s->types(), $postsController, $postsWrite)];
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

    /** Resolves a REST route (from the path or from ?rest_route=) to a response. */
    public function handle(string $route): Response
    {
        $request = $this->request->withPath('/' . trim($route, '/'));
        try {
            $response = $this->router->dispatch($request);
            if ($response !== null) {
                $response = $this->embed->decorate($request, $response);
            }
            if ($response === null && Runtime::booted()) {
                $response = RuntimeRoutes::dispatch($request);
            }
            if ($response !== null && Runtime::booted() && $request->path === '/') {
                $response = RuntimeRoutes::mergeIndex($response);
            }
            return $response ?? Reply::error(RestError::noRoute());
        } catch (RestError $error) {
            return Reply::error($error);
        }
    }
}
