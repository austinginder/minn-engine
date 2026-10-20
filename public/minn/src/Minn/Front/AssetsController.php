<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;

/** The engine's own static assets, served under a reserved path. */
final readonly class AssetsController
{
    /** Media types as the web server sends the reference's static files (scripts as text/javascript, with the charset). */
    private const TYPES = ['css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'svg' => 'image/svg+xml'];

    /**
     * The media player's files, by the names the reference registers them
     * under: MediaElement.js's own (MIT, in vendor/mediaelement) and the
     * engine's start-up, skin and migration script in their place.
     */
    private const PLAYER_FILES = [
        'mediaelement-and-player.min.js' => 'vendor/mediaelement/mediaelement-and-player.min.js',
        'mediaelementplayer-legacy.min.css' => 'vendor/mediaelement/mediaelementplayer-legacy.min.css',
        'mejs-controls.svg' => 'vendor/mediaelement/mejs-controls.svg',
        'renderers/vimeo.min.js' => 'vendor/mediaelement/renderers/vimeo.min.js',
        'wp-mediaelement.min.js' => 'mediaelement/wp-mediaelement.js',
        'wp-mediaelement.min.css' => 'mediaelement/wp-mediaelement.css',
        'mediaelement-migrate.min.js' => 'mediaelement/mediaelement-migrate.js',
    ];

    /** The embed scripts, by the reference addresses a page and a card's sourceURL name them at. */
    private const EMBED_SCRIPTS = ['wp-embed.min.js' => 'embed-host.js', 'wp-embed-template.min.js' => 'embed-card.js'];

    public function __construct(private string $assetsDir)
    {
    }

    /** One engine asset file. */
    #[Route(Method::Get, '/minn/assets/{path*}', policy: new Policy(Access::Public))]
    public function asset(Request $request, string $path): Response
    {
        $relative = str_replace('..', '', $path);
        $full = realpath($this->assetsDir . '/' . $relative);
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if ($full === false || !str_starts_with($full, realpath($this->assetsDir) . '/') || !isset(self::TYPES[$ext])) {
            return new Response(404);
        }
        return new Response(200, ['Content-Type' => self::TYPES[$ext], 'Cache-Control' => 'public, max-age=300'], (string) file_get_contents($full));
    }

    /** The engine's embed scripts at the addresses the reference serves its own from. */
    #[Route(Method::Get, '/wp-includes/js/{file:wp-embed(?:-template)?\.min\.js}', policy: new Policy(Access::Public))]
    public function embedScript(Request $request, string $file): Response
    {
        return new Response(200, ['Content-Type' => self::TYPES['js'], 'Cache-Control' => 'public, max-age=86400'], (string) file_get_contents($this->assetsDir . '/' . self::EMBED_SCRIPTS[$file]));
    }

    /** The media player, at the paths the reference registers it under (wp_default_scripts, wp_default_styles). */
    #[Route(Method::Get, '/wp-includes/js/mediaelement/{file:(?:renderers/)?[a-z0-9.-]+\.(?:js|css|svg)}', policy: new Policy(Access::Public))]
    public function player(Request $request, string $file): Response
    {
        $relative = self::PLAYER_FILES[$file] ?? null;
        if ($relative === null) {
            return new Response(404);
        }
        $type = self::TYPES[strtolower(pathinfo($relative, PATHINFO_EXTENSION))];
        return new Response(200, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=86400'], (string) file_get_contents($this->assetsDir . '/' . $relative));
    }

    /** The MIT libraries the engine ships, served at the paths the reference registers them under. */
    #[Route(Method::Get, '/wp-includes/js/jquery/{file:[a-z0-9.-]+\.js}', policy: new Policy(Access::Public))]
    public function jquery(Request $request, string $file): Response
    {
        $path = realpath($this->assetsDir . '/vendor/jquery/' . $file);
        if ($path === false || !str_starts_with($path, realpath($this->assetsDir . '/vendor/jquery') . '/')) {
            return new Response(404);
        }
        return new Response(200, ['Content-Type' => self::TYPES['js'], 'Cache-Control' => 'public, max-age=86400'], (string) file_get_contents($path));
    }
}
