<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Authenticated;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Content\SiteIcon;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Json;
use Minn\Html;
use Minn\Runtime\Runtime;

/**
 * Serves Minn Admin from the engine: the path-routed shell (every
 * sub-path renders the same page) and the app's assets. The nonce refresh
 * app.js asks admin-ajax.php for is the reference's own rest-nonce action
 * (Runtime\AjaxController).
 */
final readonly class AppController
{
    public function __construct(
        private App $app,
        private BootPayload $payload,
        private Authenticator $authenticator,
        private Capabilities $capabilities,
        private Permalinks $permalinks,
        private string $engineVersion,
        private SiteIcon $icon,
        /** the site has deactivated the Minn Admin plugin: the engine runs without an admin */
        private bool $off = false,
    ) {
    }

    /** Requires a signed-in user who can edit content; otherwise the login form. */
    #[Route(Method::Get, '/minn-admin', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/minn-admin/{rest*}', policy: new Policy(Access::Public))]
    public function shell(Request $request): Response
    {
        $session = $this->authenticator->session($request->cookies);
        if (!$session instanceof Authenticated) {
            $current = ($request->secure ? 'https' : 'http') . '://' . ($request->host ?: 'localhost') . $request->path . $request->queryStringWithout();
            return Response::redirect($this->permalinks->url('/minn-admin/login?redirect_to=' . rawurlencode($current)), 302);
        }
        if (!$this->capabilities->can($session->id(), 'edit_posts')) {
            return Response::html('<!doctype html><title>Not allowed</title><p>You do not have permission to access this admin.', 403);
        }
        if (!$this->app->installed()) {
            return Response::html('<!doctype html><title>Minn Admin not installed</title><p>The Minn Admin app is not linked into this engine.', 500);
        }
        if ($this->off) {
            return Response::html($this->offPage());
        }
        return Response::html($this->render($this->payload->build($session)))
            ->withHeader('X-Powered-By', 'Minn/' . $this->engineVersion);
    }

    /** The engine without an admin: the site keeps serving; the command that brings the admin back is named. */
    private function offPage(): string
    {
        $site = Html::esc((string) ($this->payload->siteName()));
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow"><title>Minn Admin is off — {$site}</title>
        <style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0b0d;color:#ececed;font:15px/1.6 "Hanken Grotesk","Helvetica Neue",sans-serif}
        main{width:420px;background:#151518;border:1px solid #242429;border-radius:14px;padding:28px}h1{font-size:19px;margin:0 0 10px}p{margin:0 0 12px;color:#9d9da7}
        code{display:block;background:#0b0b0d;border:1px solid #31313a;border-radius:8px;padding:10px 12px;color:#ececed;font:13px ui-monospace,Menlo,monospace}a{color:#8a80f8}</style></head>
        <body><main><h1>Minn Admin is off</h1>
        <p>{$site} is running Minn Engine without an admin interface. The site, its REST API, cron and command line keep working.</p>
        <p>Turn the admin back on from the command line:</p><code>wp plugin activate minn-admin</code>
        <p style="margin-top:14px"><a href="/">Back to the site</a></p></main></body></html>
        HTML;
    }

    /**
     * The tab's icon, chosen as Minn Admin's own shell chooses it: the site
     * icon when there is one, else a brand mark a theme or plugin offers on
     * minn_admin_favicon, else Minn's "m" in the viewer's accent (Minn
     * Admin's own drawing when it runs as code, its default accent if not).
     * Without one the browser asks for /favicon.ico.
     */
    private function favicon(): string
    {
        $icons = trim($this->icon->links());
        if ($icons !== '') {
            return $icons;
        }
        $mark = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="15" fill="#6e62f5"/><text x="32" y="45" text-anchor="middle" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif" font-size="42" font-weight="700" fill="#ffffff">m</text></svg>');
        // minn_admin_favicon and the accent-coloured drawing are Minn Admin's own, there when it runs as code.
        if (class_exists('Minn_Admin', false) && method_exists('Minn_Admin', 'default_favicon_uri')) {
            try {
                $offered = Runtime::hooks()->filter('minn_admin_favicon', ['']);
                if (is_string($offered) && preg_match('#^(?:https?:)?//|^/(?!/)#i', $offered) === 1) {
                    return '<link rel="icon" href="' . Html::attr($offered) . '">';
                }
                $mark = (string) \Minn_Admin::default_favicon_uri();
            } catch (\Throwable) {
                // The plugin's drawing needs the runtime; the default mark stands in.
            }
        }
        return '<link rel="icon" type="image/svg+xml" href="' . Html::attr($mark) . '">';
    }

    /** One file of the app bundle, with its content type and caching headers. */
    #[Route(Method::Get, '/minn/admin/assets/{path*}', policy: new Policy(Access::Public))]
    public function asset(Request $request, string $path): Response
    {
        $asset = $this->app->asset('assets/' . ltrim($path, '/'));
        if ($asset === null) {
            return new Response(404);
        }
        [$file, $type] = $asset;
        return new Response(200, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=300'], (string) file_get_contents($file));
    }

    /**
     * The shell reproduces the structure of Minn Admin's own template.php
     * (an MIT file): pre-paint theme, inline window.MINN, the colour scheme
     * painted before app.js (a 'site' scheme takes the site default's
     * palette), mount point, app.js.
     */
    private function render(array $boot): string
    {
        $name = Html::esc((string) ($boot['site']['name'] ?? 'Site'));
        $cssVersion = Html::esc($this->app->assetVersion('assets/css/app.css'));
        $jsVersion = Html::esc($this->app->assetVersion('assets/js/app.js'));
        $path = Html::attr($this->app->path());
        $json = Json::encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        // A device with no saved choice starts in the site's default mode; nothing is saved until the person picks.
        $siteMode = Json::encode((string) ($boot['siteAppearance']['mode'] ?? 'system'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $lang = Html::attr(str_replace('_', '-', (string) ($boot['locale'] ?? 'en_US')));
        $dir = !empty($boot['rtl']) ? 'rtl' : 'ltr';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}" dir="{$dir}" data-theme="dark">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <title>Minn Admin — {$name}</title>
        {$this->favicon()}
        <link rel="stylesheet" href="{$path}/assets/css/app.css?ver={$cssVersion}">
        <script>
        try {
        \tvar siteMode = {$siteMode};
        \tvar stored = localStorage.getItem( 'minn-theme' ) || siteMode;
        \tvar follow = stored !== 'light' && stored !== 'dark';
        \tif ( follow && window.matchMedia ) {
        \t\tvar mq = window.matchMedia( '(prefers-color-scheme: light)' );
        \t\tdocument.documentElement.setAttribute( 'data-theme', mq.matches ? 'light' : 'dark' );
        \t\tmq.addEventListener( 'change', function ( e ) {
        \t\t\tvar pref = localStorage.getItem( 'minn-theme' ) || siteMode;
        \t\t\tif ( pref !== 'light' && pref !== 'dark' ) {
        \t\t\t\tdocument.documentElement.setAttribute( 'data-theme', e.matches ? 'light' : 'dark' );
        \t\t\t\tdocument.dispatchEvent( new CustomEvent( 'minn-theme-change' ) );
        \t\t\t}
        \t\t} );
        \t} else {
        \t\tdocument.documentElement.setAttribute( 'data-theme', stored );
        \t}
        } catch ( e ) {}
        window.MINN = {$json};
        (function () {
        \ttry {
        \t\tvar ap = ( window.MINN.user && window.MINN.user.appearance ) || { scheme: 'site' };
        \t\tif ( ap.scheme === 'site' ) {
        \t\t\tvar site = window.MINN.siteAppearance || {};
        \t\t\tap = { scheme: site.scheme || 'minn', custom: site.custom, font: ap.font };
        \t\t}
        \t\tvar root = document.documentElement;
        \t\troot.setAttribute( 'data-scheme', ap.scheme || 'minn' );
        \t\troot.setAttribute( 'data-font', ap.font === 'wordpress' ? 'wordpress' : 'minn' );
        \t\tif ( ap.scheme !== 'custom' ) {
        \t\t\treturn;
        \t\t}
        \t\tvar mode = root.getAttribute( 'data-theme' ) || 'dark';
        \t\tvar tokens = ( ap.custom && ap.custom[ mode ] ) || {};
        \t\tvar vars = { bg: '--bg', bg2: '--bg2', panel: '--panel', panel2: '--panel2', hover: '--hover', border: '--border', border2: '--border2', text: '--text', text2: '--text2', text3: '--text3', accent: '--accent', accent2: '--accent2', accentFg: '--accent-fg' };
        \t\tfor ( var k in vars ) {
        \t\t\tif ( tokens[ k ] ) root.style.setProperty( vars[ k ], tokens[ k ] );
        \t\t}
        \t\tvar m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec( tokens.accent || '' );
        \t\tif ( m ) {
        \t\t\troot.style.setProperty( '--accent-soft', 'rgba(' + parseInt( m[1], 16 ) + ',' + parseInt( m[2], 16 ) + ',' + parseInt( m[3], 16 ) + ',' + ( mode === 'light' ? 0.10 : 0.15 ) + ')' );
        \t\t}
        \t} catch ( e ) {}
        })();
        </script>
        </head>
        <body>
        <div id="minn-app"><div class="minn-boot-spinner"></div></div>
        <script src="{$path}/assets/js/app.js?ver={$jsVersion}"></script>
        </body>
        </html>
        HTML;
    }
}
