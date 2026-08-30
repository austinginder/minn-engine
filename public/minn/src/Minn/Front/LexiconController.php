<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * /lexicon/ is the interactive glossary, sourced from contracts/lexicon.md.
 * /lexicon.md is the same file, raw. Missing file is a 404: the page is
 * engine documentation, not a WordPress route.
 */
final readonly class LexiconController
{
    public const PATH = '/lexicon/';

    public function __construct(
        private string $engineDir,
        private Permalinks $permalinks,
        private Site $site,
        private string $themesDir,
    ) {
    }

    #[Route(Method::Get, '/lexicon')]
    public function redirect(Request $request): Response
    {
        return Response::redirect($this->permalinks->url(self::PATH), 301);
    }

    #[Route(Method::Get, '/lexicon.md')]
    public function markdown(Request $request): Response
    {
        $path = Lexicon::locate($this->engineDir);
        if ($path === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Lexicon not found.');
        }
        return new Response(200, ['Content-Type' => 'text/markdown; charset=utf-8'], (string) file_get_contents($path));
    }

    #[Route(Method::Get, self::PATH)]
    public function page(Request $request): Response
    {
        $path = Lexicon::locate($this->engineDir);
        if ($path === null) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Lexicon not found.');
        }
        $themeDir = is_file($this->themesDir . '/minn-site/style.css')
            ? $this->themesDir . '/minn-site'
            : null;
        $html = (new LexiconPage(
            Lexicon::fromFile($path),
            $this->permalinks,
            (string) ($this->site->option('blogname') ?? 'Minn Engine'),
            $themeDir,
            $this->permalinks->url('/wp-content/themes/minn-site'),
        ))->html();
        return Response::html($html)->withHeader('X-Powered-By', 'Minn Engine');
    }
}
