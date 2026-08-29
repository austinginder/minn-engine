<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Authenticated;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Auth\Nonce;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Support\Html;

/**
 * Serves Minn Admin from the engine: the path-routed shell (every
 * sub-path renders the same page), the app's assets, and the one
 * admin-ajax action app.js uses to refresh its nonce.
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
    ) {
    }

    /** Requires a signed-in user who can edit content; otherwise the login form. */
    #[Route(Method::Get, '/minn-admin')]
    #[Route(Method::Get, '/minn-admin/{rest*}')]
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
        return Response::html($this->render($this->payload->build($session)))
            ->withHeader('X-Powered-By', 'Minn Engine/' . $this->engineVersion);
    }

    #[Route(Method::Get, '/minn-admin-asset/{path*}')]
    public function asset(Request $request, string $path): Response
    {
        $asset = $this->app->asset($path);
        if ($asset === null) {
            return new Response(404);
        }
        [$file, $type] = $asset;
        return new Response(200, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=300'], (string) file_get_contents($file));
    }

    #[Route(Method::Any, '/wp-admin/admin-ajax.php')]
    public function ajax(Request $request): Response
    {
        $action = $request->form['action'] ?? $request->query('action') ?? '';
        if ($action !== 'rest-nonce') {
            return new Response(400, [], '-1');
        }
        $session = $this->authenticator->session($request->cookies);
        $body = $session instanceof Authenticated ? Nonce::create($session->id(), $session->token) : '0';
        return Response::html($body);
    }

    /**
     * The shell reproduces the structure of Minn Admin's own template.php
     * (an MIT file): pre-paint theme, inline window.MINN, mount point, app.js.
     */
    private function render(array $boot): string
    {
        $name = Html::esc((string) ($boot['site']['name'] ?? 'Site'));
        $cssVersion = Html::esc($this->app->assetVersion('assets/css/app.css'));
        $jsVersion = Html::esc($this->app->assetVersion('assets/js/app.js'));
        $json = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en" dir="ltr" data-theme="dark">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <title>Minn Admin — {$name}</title>
        <link rel="stylesheet" href="/minn-admin-asset/assets/css/app.css?ver={$cssVersion}">
        <script>
        try {
        \tvar stored = localStorage.getItem( 'minn-theme' );
        \tif ( ! stored ) { localStorage.setItem( 'minn-theme', 'system' ); stored = 'system'; }
        \tvar follow = stored === 'system';
        \tif ( follow && window.matchMedia ) {
        \t\tvar mq = window.matchMedia( '(prefers-color-scheme: light)' );
        \t\tdocument.documentElement.setAttribute( 'data-theme', mq.matches ? 'light' : 'dark' );
        \t\tmq.addEventListener( 'change', function ( e ) {
        \t\t\tif ( localStorage.getItem( 'minn-theme' ) === 'system' ) {
        \t\t\t\tdocument.documentElement.setAttribute( 'data-theme', e.matches ? 'light' : 'dark' );
        \t\t\t\tdocument.dispatchEvent( new CustomEvent( 'minn-theme-change' ) );
        \t\t\t}
        \t\t} );
        \t} else if ( stored === 'light' || stored === 'dark' ) {
        \t\tdocument.documentElement.setAttribute( 'data-theme', stored );
        \t}
        } catch ( e ) {}
        window.MINN = {$json};
        </script>
        </head>
        <body>
        <div id="minn-app"><div class="minn-boot-spinner"></div></div>
        <script src="/minn-admin-asset/assets/js/app.js?ver={$jsVersion}"></script>
        </body>
        </html>
        HTML;
    }
}
