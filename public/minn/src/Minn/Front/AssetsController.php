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
    private const TYPES = ['css' => 'text/css', 'js' => 'application/javascript'];

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
