<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/** The engine's own static assets, served under a reserved path. */
final readonly class AssetsController
{
    private const TYPES = ['css' => 'text/css', 'js' => 'application/javascript'];

    public function __construct(private string $assetsDir)
    {
    }

    #[Route(Method::Get, '/minn-engine/{file:[a-z0-9.-]+}')]
    public function asset(Request $request, string $file): Response
    {
        // The wp.* packages live in assets/wp and serve as wp-{name}.js.
        $path = realpath($this->assetsDir . '/' . (str_starts_with($file, 'wp-') ? 'wp/' . substr($file, 3) : $file));
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($path === false || !str_starts_with($path, realpath($this->assetsDir) . '/') || !isset(self::TYPES[$ext])) {
            return new Response(404);
        }
        return new Response(200, ['Content-Type' => self::TYPES[$ext], 'Cache-Control' => 'public, max-age=300'], (string) file_get_contents($path));
    }

    /** The MIT libraries the engine ships, served at the paths the reference registers them under. */
    #[Route(Method::Get, '/wp-includes/js/jquery/{file:[a-z0-9.-]+\.js}')]
    public function jquery(Request $request, string $file): Response
    {
        $path = realpath($this->assetsDir . '/vendor/jquery/' . $file);
        if ($path === false || !str_starts_with($path, realpath($this->assetsDir . '/vendor/jquery') . '/')) {
            return new Response(404);
        }
        return new Response(200, ['Content-Type' => self::TYPES['js'], 'Cache-Control' => 'public, max-age=86400'], (string) file_get_contents($path));
    }
}
