<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\Authenticator;
use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Http\Router;

/**
 * The API index at /wp-json/: the site facts monitors read (name, url,
 * home, namespaces) and the routes this engine serves, described from its
 * own router rather than the reference's full schema.
 */
final readonly class IndexController
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Router $router,
    ) {
    }

    /** The REST index: namespaces, routes, and the site's description. */
    #[Route(Method::Get, '/', policy: new Policy(Access::Public))]
    public function index(Request $request): Response
    {
        $routes = ['/' => ['namespace' => '', 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/')]]]]];
        $namespaces = [];
        foreach ($this->router->routes() as $pattern => $methods) {
            foreach (EngineRoutes::forms($pattern) as $route) {
            if ($route === '/') {
                continue;
            }
            $namespace = preg_match('#^/([^/]+/v\d+)#', $route, $m) ? $m[1] : '';
            if ($namespace !== '' && !in_array($namespace, $namespaces, true)) {
                $namespaces[] = $namespace;
                $routes['/' . $namespace] = ['namespace' => $namespace, 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['namespace' => ['default' => $namespace, 'required' => false], 'context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/' . $namespace)]]]];
            }
            $methods = array_values(array_unique(array_map(static fn (string $m) => $m === '*' ? 'GET' : $m, $methods)));
            $routes[$route] = ['namespace' => $namespace, 'methods' => $methods, 'endpoints' => [['methods' => $methods, 'args' => []]]];
            if (!str_contains($route, '(?P<')) {
                $routes[$route]['_links'] = ['self' => [['href' => $this->url->to($route)]]];
            }
            }
        }
        return Reply::item([
            'name' => (string) ($this->site->option('blogname') ?? ''),
            'description' => (string) ($this->site->option('blogdescription') ?? ''),
            'url' => (string) ($this->site->option('siteurl') ?? ''),
            'home' => $this->permalinks->url(''),
            'gmt_offset' => (string) ($this->site->option('gmt_offset') ?? '0'),
            'timezone_string' => (string) ($this->site->option('timezone_string') ?? ''),
            'page_for_posts' => (int) ($this->site->option('page_for_posts') ?? 0),
            'page_on_front' => (int) ($this->site->option('page_on_front') ?? 0),
            'show_on_front' => (string) ($this->site->option('show_on_front') ?? 'posts'),
            'namespaces' => $namespaces,
            'authentication' => Authenticator::applicationPasswordsAvailable($request) ? ['application-passwords' => ['endpoints' => ['authorization' => $this->permalinks->url('/wp-admin/authorize-application.php')]]] : [],
            'routes' => $routes,
            'site_logo' => (int) ($this->site->option('site_logo') ?? 0),
            'site_icon' => (int) ($this->site->option('site_icon') ?? 0),
            'site_icon_url' => '',
            '_links' => ['help' => [['href' => 'https://developer.wordpress.org/rest-api/']]],
        ], Fields::fromQuery($request->query));
    }
}
