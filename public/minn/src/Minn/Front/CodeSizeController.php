<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * /code-size/ compares how much code ships in WordPress core and in Minn,
 * from contracts/code-size.json; /code-size.json is the report raw. Engine
 * documentation, not a WordPress route: a missing report is a 404.
 */
final readonly class CodeSizeController
{
    public const PATH = '/code-size/';

    public function __construct(
        private string $engineDir,
        private Permalinks $permalinks,
        private Site $site,
        private string $themesDir,
    ) {
    }

    #[Route(Method::Get, '/code-size')]
    public function redirect(Request $request): Response
    {
        return Response::redirect($this->permalinks->url(self::PATH), 301);
    }

    #[Route(Method::Get, '/code-size.json')]
    public function json(Request $request): Response
    {
        $path = CodeSize::locate($this->engineDir);
        if ($path === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Code-size report not found.');
        }
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], (string) file_get_contents($path));
    }

    #[Route(Method::Get, self::PATH)]
    public function page(Request $request): Response
    {
        $path = CodeSize::locate($this->engineDir);
        if ($path === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Code-size report not found.');
        }
        $html = (new CodeSizePage(
            CodeSize::fromFile($path),
            $this->permalinks,
            (string) ($this->site->option('blogname') ?? 'Minn'),
            new SiteChrome(SiteChrome::locate($this->themesDir)),
            $this->permalinks->url('/wp-content/themes/minn-site'),
        ))->html();
        return Response::html($html)->withHeader('X-Powered-By', 'Minn Engine');
    }
}
