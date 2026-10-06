<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Auth\Authenticator;
use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Http\RouteMiss;
use Minn\Http\Router;

/**
 * The API index at /wp-json/: the site facts monitors read (name, url,
 * home, namespaces) and the routes this engine serves, described from its
 * own router rather than the reference's full schema; and the index of one
 * namespace at /wp-json/{namespace}, the same routes narrowed.
 */
final readonly class IndexController
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Router $router,
        private Types $types,
    ) {
    }

    /** The REST index: namespaces, routes, and the site's description. */
    #[Route(Method::Get, '/', policy: new Policy(Access::Public))]
    public function index(Request $request): Response
    {
        ['namespaces' => $namespaces, 'routes' => $routes] = $this->catalogue();
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

    /**
     * One namespace's index, as the reference answers /wp-json/wp/v2 (or oembed/1.0): the
     * namespace, its routes (the namespace root among them), and the link
     * up to the root index. A namespace the engine does not serve is left
     * to the runtime, whose plugins may own it.
     */
    #[Route(Method::Get, '/{namespace:[a-z0-9-]+/(?:v\d+|\d+\.\d+)}', policy: new Policy(Access::Public), index: false)]
    public function namespaceIndex(Request $request, string $namespace): Response
    {
        ['namespaces' => $namespaces, 'routes' => $routes] = $this->catalogue();
        if (!in_array($namespace, $namespaces, true)) {
            throw new RouteMiss();
        }
        $own = array_filter($routes, static fn (string $route): bool => $route === '/' . $namespace || str_starts_with($route, '/' . $namespace . '/'), ARRAY_FILTER_USE_KEY);
        return Reply::item([
            'namespace' => $namespace,
            'routes' => $own,
            '_links' => ['up' => [['href' => $this->url->to('/')]]],
        ], Fields::fromQuery($request->query));
    }

    /**
     * The routes the engine lists, in the reference's shape, with the
     * namespaces they fall under: every route's methods and the parameters
     * it declares, gathered per concrete route from the attributes so the
     * index tells a client what really works on it.
     *
     * @return array{namespaces: list<string>, routes: array<string, array<string, mixed>>}
     */
    private function catalogue(): array
    {
        $bases = $this->types->declaredBases();
        $routes = ['/' => ['namespace' => '', 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/')]]]]];
        $namespaces = [];
        $arguments = [];
        foreach ($this->router->table() as $row) {
            foreach (EngineRoutes::forms($row->pattern, $bases) as $form) {
                $arguments[$form] = array_merge($arguments[$form] ?? [], $row->toArray()['args']);
            }
        }
        foreach ($this->router->routes() as $pattern => $methods) {
            foreach (EngineRoutes::forms($pattern, $bases) as $route) {
                if ($route === '/') {
                    continue;
                }
                $namespace = preg_match('#^/([^/]+/(?:v\d+|\d+\.\d+))#', $route, $m) ? $m[1] : '';
                if ($namespace !== '' && !in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                    $routes['/' . $namespace] = ['namespace' => $namespace, 'methods' => ['GET'], 'endpoints' => [['methods' => ['GET'], 'args' => ['namespace' => ['default' => $namespace, 'required' => false], 'context' => ['default' => 'view', 'required' => false]]]], '_links' => ['self' => [['href' => $this->url->to('/' . $namespace)]]]];
                }
                $methods = array_values(array_unique(array_map(static fn (string $m) => $m === '*' ? 'GET' : $m, $methods)));
                $routes[$route] = ['namespace' => $namespace, 'methods' => $methods, 'endpoints' => [['methods' => $methods, 'args' => $arguments[$route] ?? []]]];
                if (!str_contains($route, '(?P<')) {
                    $routes[$route]['_links'] = ['self' => [['href' => $this->url->to($route)]]];
                }
            }
        }
        return ['namespaces' => $namespaces, 'routes' => $routes];
    }
}
