<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Admin\Diagnostics;
use Minn\Admin\LanguageController;
use Minn\Admin\Translations;
use Minn\Admin\Logs;
use Minn\Admin\SystemController;
use Minn\Admin\ManageController;
use Minn\Admin\SessionsController;
use Minn\Admin\CoreStatus;
use Minn\Admin\Dashboard;
use Minn\Admin\Notifications;
use Minn\Admin\V1Controller;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Auth\Sessions;
use Minn\Content\Comments;
use Minn\Content\Inventory;
use Minn\Content\Menus;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Revisions;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Content\Users;
use Minn\Db;
use Minn\Extension\Loader;
use Minn\Front\Permalinks;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Router;
use Minn\Media\Images;
use Minn\Media\Uploads;
use Minn\RestError;

/**
 * The REST API: wires the controllers for one request and dispatches a
 * route. Errors thrown anywhere underneath become the reference's error
 * payload under the reference's headers.
 */
final readonly class Api
{
    public function __construct(
        private Db $db,
        private Request $request,
        private Caller $caller,
        private Router $router,
        private PostObject $postObject,
        private TermObject $termObject,
        private UserObject $userObject,
        private Types $types,
    ) {
    }

    public static function forRequest(Db $db, Request $request): self
    {
        $users = new Users($db);
        $posts = new Posts($db);
        $terms = new Terms($db);
        $site = new Site($db);
        $writer = new PostWriter($db, $posts, $site);
        $permalinks = Permalinks::fromDb($db);
        $url = new RestUrl($permalinks);
        $capabilities = Capabilities::fromDb($db);
        $caller = new Caller($request, Authenticator::fromDb($db), $capabilities);
        $postObject = new PostObject($db, $posts, $users, $permalinks, $url, $caller);
        $termObject = new TermObject($db, $permalinks, $url, $caller);
        $userObject = new UserObject($db, $users, $permalinks, $url, $caller);
        $contentDir = rtrim(ABSPATH, '/') . '/wp-content';
        $loader = new Loader($contentDir, $site);
        $types = new Types($url, $loader->declaredTypes());
        $taxonomies = new Taxonomies($url);
        $uploads = new Uploads($site, $permalinks, ABSPATH . 'wp-content/uploads');
        $mediaObject = new MediaObject($posts, $uploads, $permalinks, $url, $caller);
        $commentObject = new CommentObject(new Comments($db), $posts, $permalinks, $url, $caller);

        $dashboard = new Dashboard($db, $site, $users, $capabilities, ABSPATH . 'wp-content/uploads');
        $notifications = new Notifications($db, $site, $users, $capabilities, $dashboard);
        $menus = new Menus($db, $posts, $terms, $permalinks, $writer, $site);

        $router = new Router();
        $router->register(
            new IndexController($site, $permalinks, $url, $router),
            new V1Controller($db, $site, $posts, $writer, $permalinks, $dashboard, $notifications, new CoreStatus($site), new AdminTypes($types, $capabilities), $caller),
            new TermsController($db, $terms, $site, $termObject, $caller),
            new UsersController($db, $users, $site, $userObject, $url, $caller, $capabilities->roles()),
            new TypesController($types),
            new TaxonomiesController($taxonomies, $caller),
            new SearchController($db, $types, $permalinks, $url, $caller),
            new PluginsController($site, new Inventory($contentDir, $site), $loader, $url, $caller),
            new SessionsController($users, new Sessions($users), $caller),
            new ManageController($db, $site, $types, $taxonomies, $loader, new Inventory($contentDir, $site), $permalinks, new App(MINN_ENGINE_DIR . '/admin'), new Appearance($users), $caller, $contentDir),
            new LanguageController(new Translations($users, $site, new App(MINN_ENGINE_DIR . '/admin'), $contentDir), $users, $site, $capabilities, $caller),
            new SystemController(
                new Diagnostics($db, $site, $permalinks, new Inventory($contentDir, $site), $loader, new Logs(rtrim(ABSPATH, '/')), MINN_ENGINE_VERSION, rtrim(ABSPATH, '/')),
                new Logs(rtrim(ABSPATH, '/')),
                $caller,
            ),
            new SettingsController(new Settings($site), $caller),
            new CommentsController(new Comments($db), $posts, $site, $commentObject, $caller),
            new RevisionsController($posts, new Revisions($db, $writer, $site), $url, $caller),
            new MediaController($db, $posts, $writer, $site, $uploads, new Images($site), $mediaObject, $caller),
            new MenusController($menus, new MenuObject($menus, $url, $caller), new MenuItemObject($url, $caller), $caller, $url),
        );
        $postsController = new PostsController($db, $posts, $postObject, $caller);
        $postsWrite = new PostsWriteController($posts, $writer, $site, $postObject, $url, $caller);
        $router->register($postsController, $postsWrite);
        $router->register(new DeclaredPostsController($types, $postsController, $postsWrite));
        return new self($db, $request, $caller, $router, $postObject, $termObject, $userObject, $types);
    }

    public function caller(): Caller
    {
        return $this->caller;
    }

    public function postObject(): PostObject
    {
        return $this->postObject;
    }

    public function termObject(): TermObject
    {
        return $this->termObject;
    }

    public function userObject(): UserObject
    {
        return $this->userObject;
    }

    public function types(): Types
    {
        return $this->types;
    }

    /** Resolves a REST route (from the path or from ?rest_route=) to a response. */
    public function handle(string $route): Response
    {
        $request = $this->request->withPath('/' . trim($route, '/'));
        try {
            return $this->router->dispatch($request) ?? Reply::error(RestError::noRoute());
        } catch (RestError $error) {
            return Reply::error($error);
        }
    }
}
