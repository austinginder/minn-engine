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
use Minn\RestError;
use Minn\Runtime\AttachmentImages;
use Minn\Runtime\Runtime;

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
        private RouteCatalogue $catalogue,
        private Caller $caller,
    ) {
    }

    /** The REST index: namespaces, routes, and the site's description. */
    #[Route(Method::Get, '/', policy: new Policy(Access::Public))]
    public function index(Request $request): Response
    {
        ['namespaces' => $namespaces, 'routes' => $routes] = $this->catalogue->all();
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
            'namespaces' => RouteCatalogue::ordered($namespaces),
            'authentication' => Authenticator::applicationPasswordsAvailable($request) ? ['application-passwords' => ['endpoints' => ['authorization' => $this->permalinks->url('/wp-admin/authorize-application.php')]]] : [],
            'routes' => $routes,
        ] + $this->imageSettings($request) + [
            'site_logo' => (int) ($this->site->option('site_logo') ?? 0),
            'site_icon' => (int) ($this->site->option('site_icon') ?? 0),
            'site_icon_url' => '',
            '_links' => ['help' => [['href' => 'https://developer.wordpress.org/rest-api/']]] + $this->themeLink(),
        ], Fields::fromQuery($request->query));
    }

    /**
     * How the browser processes images before upload, for someone who may
     * upload when it does: the registered sizes, the big-image threshold,
     * whether metadata is stripped and the deepest bit depth kept, each
     * through its filter as the editor hears it.
     *
     * @return array<string, mixed>
     */
    private function imageSettings(Request $request): array
    {
        if (!AttachmentImages::browserProcessing($request->secure ? 'https' : 'http', $request->host) || !$this->caller->can('upload_files')) {
            return [];
        }
        $hooks = Runtime::hooks();
        return [
            'image_sizes' => AttachmentImages::subsizes(),
            'image_size_threshold' => $hooks->filter('big_image_size_threshold', [2560, [0, 0], '', 0]),
            'image_strip_meta' => $hooks->filter('image_strip_meta', [true]),
            'image_max_bit_depth' => $hooks->filter('image_max_bit_depth', [16, 16]),
        ];
    }

    /**
     * The active theme's link, for someone who may switch themes, manage
     * the network's or edit posts, as the reference links it (with the
     * curies its relation needs).
     *
     * @return array<string, mixed>
     */
    private function themeLink(): array
    {
        if (!$this->caller->can('switch_themes') && !$this->caller->can('manage_network_themes') && !$this->caller->can('edit_posts')) {
            return [];
        }
        $theme = $this->url->to('/wp/v2/themes/' . (string) ($this->site->option('stylesheet') ?? ''));
        return ['wp:active-theme' => [Links::item('wp:active-theme', $theme, [])], 'curies' => RestUrl::curies()];
    }

    /**
     * One namespace's index, as the reference answers /wp-json/wp/v2 (or oembed/1.0): the
     * namespace, its routes (the namespace root among them), and the link
     * up to the root index. A namespace the engine does not serve is left
     * to the runtime, whose plugins may own it. The namespace argument the
     * index lists takes the path's place when it is sent, as every path
     * argument does on the reference: one naming no namespace the engine
     * serves is rest_invalid_namespace.
     */
    #[Route(Method::Get, '/{namespace:[a-z0-9-]+/(?:v\d+|\d+\.\d+)}', policy: new Policy(Access::Public), index: false)]
    public function namespaceIndex(Request $request, string $namespace): Response
    {
        ['namespaces' => $namespaces, 'routes' => $routes] = $this->catalogue->all();
        if (!in_array($namespace, $namespaces, true)) {
            throw new RouteMiss();
        }
        $asked = $request->query['namespace'] ?? $namespace;
        if (is_string($asked) && $asked !== $namespace) {
            if (!in_array($asked, $namespaces, true)) {
                throw new RestError('rest_invalid_namespace', 'The specified namespace could not be found.', 404);
            }
            $namespace = $asked;
        }
        $own = array_filter($routes, static fn (string $route): bool => $route === '/' . $namespace || str_starts_with($route, '/' . $namespace . '/'), ARRAY_FILTER_USE_KEY);
        return Reply::item([
            'namespace' => $namespace,
            'routes' => $own,
            '_links' => ['up' => [['href' => $this->url->to('/')]]],
        ], Fields::fromQuery($request->query));
    }
}
