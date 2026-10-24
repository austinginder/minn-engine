<?php

declare(strict_types=1);

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Router;
use Minn\Runtime\WpApiMode;

/**
 * MINN_WP_API's request reading (Minn on its own): which requests are public
 * pages, and what each mode loads for one. The modes against a real site are
 * tests/wp-api-modes.test.php.
 */
$request = static fn (string $path, Method $method = Method::Get, array $query = []): Request => new Request($method, $path, $query, [], [], '', true, 'unit.test');
$public = static fn (Request $r): bool => WpApiMode::publicPage($r);

return [
    'a GET or HEAD for the site is a public page' => static fn () => $public($request('/'))
        && $public($request('/notes/a-note/'))
        && $public($request('/feed/'))
        && $public($request('/play/', Method::Head))
        && $public($request('/?s=quoin', Method::Get, ['s' => 'quoin'])),
    'a POST, the admin, REST and the entry scripts are not' => static fn () => !$public($request('/', Method::Post))
        && !$public($request('/wp-admin/'))
        && !$public($request('/wp-admin/admin-ajax.php'))
        && !$public($request('/minn-admin'))
        && !$public($request('/minn-admin/login'))
        && !$public($request('/wp-json/wp/v2/posts'))
        && !$public($request('/', Method::Get, ['rest_route' => '/wp/v2/posts']))
        && !$public($request('/wp-login.php'))
        && !$public($request('/wp-cron.php'))
        && !$public($request('/wp-comments-post.php'))
        && !$public($request('/xmlrpc.php')),
    'a path that only starts like the admin is a public page' => static fn () => $public($request('/minn-administration/'))
        && $public($request('/wp-adminer/')),
    'on loads everything everywhere and spares nothing' => static fn () => WpApiMode::On->loadsPlugins($request('/'))
        && WpApiMode::On->loadsMinnAdmin($request('/'))
        && !WpApiMode::On->sparesRuntime($request('/')),
    'auto keeps plugins off public pages alone' => static fn () => !WpApiMode::Auto->loadsPlugins($request('/'))
        && !WpApiMode::Auto->loadsMinnAdmin($request('/'))
        && WpApiMode::Auto->sparesRuntime($request('/'))
        && WpApiMode::Auto->loadsPlugins($request('/wp-cron.php'))
        && WpApiMode::Auto->loadsPlugins($request('/', Method::Post))
        && WpApiMode::Auto->loadsMinnAdmin($request('/wp-json/minn-admin/v1/overview')),
    'off loads Minn Admin alone, and only off public pages' => static fn () => !WpApiMode::Off->loadsPlugins($request('/wp-json/wp/v2/posts'))
        && !WpApiMode::Off->loadsPlugins($request('/wp-cron.php'))
        && WpApiMode::Off->loadsMinnAdmin($request('/wp-json/minn-admin/v1/overview'))
        && !WpApiMode::Off->loadsMinnAdmin($request('/'))
        && WpApiMode::Off->sparesRuntime($request('/'))
        && !WpApiMode::Off->sparesRuntime($request('/wp-login.php')),
    'with no constant defined the mode is on' => static fn () => defined('MINN_WP_API') || WpApiMode::configured() === WpApiMode::On,
    'a route is claimed from its class alone, by method and path' => static fn () => Router::claims($request('/wp-sitemap.xml'), Minn\Front\SitemapController::class)
        && !Router::claims($request('/wp-sitemap.xml', Method::Post), Minn\Front\SitemapController::class)
        && !Router::claims($request('/play/'), Minn\Front\SitemapController::class, Minn\Front\ProbeController::class),
];
