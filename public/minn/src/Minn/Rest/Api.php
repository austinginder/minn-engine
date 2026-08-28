<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Admin\AdminTypes;
use Minn\Admin\CoreStatus;
use Minn\Admin\Dashboard;
use Minn\Admin\Notifications;
use Minn\Admin\V1Controller;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Revisions;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Content\Users;
use Minn\Db;
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
        $types = new Types($url);
        $uploads = new Uploads($site, $permalinks, ABSPATH . 'wp-content/uploads');
        $mediaObject = new MediaObject($posts, $uploads, $permalinks, $url, $caller);
        $commentObject = new CommentObject(new Comments($db), $posts, $permalinks, $url, $caller);

        $dashboard = new Dashboard($db, $site, $users, $capabilities, ABSPATH . 'wp-content/uploads');
        $notifications = new Notifications($db, $site, $users, $capabilities, $dashboard);

        $router = new Router();
        $router->register(
            new IndexController($site, $permalinks, $url, $router),
            new V1Controller($db, $site, $posts, $writer, $permalinks, $dashboard, $notifications, new CoreStatus($site), new AdminTypes($types, $capabilities), $caller),
            new PostsController($db, $posts, $postObject, $caller),
            new PostsWriteController($posts, $writer, $site, $postObject, $url, $caller),
            new TermsController($db, $terms, $site, $termObject, $caller),
            new UsersController($db, $users, $site, $userObject, $url, $caller),
            new TypesController($types),
            new SettingsController(new Settings($site), $caller),
            new CommentsController(new Comments($db), $posts, $site, $commentObject, $caller),
            new RevisionsController($posts, new Revisions($db, $writer, $site), $url, $caller),
            new MediaController($db, $posts, $writer, $site, $uploads, new Images($site), $mediaObject, $caller),
        );
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
