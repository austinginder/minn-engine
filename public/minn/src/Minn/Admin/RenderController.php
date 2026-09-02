<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Blocks;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;
use Minn\Theme\GlobalStyles;
use Minn\Theme\Templates;
use Minn\Theme\Theme;

/**
 * The editor's island previews: block markup rendered by the same
 * renderer the public site uses, with the stylesheets that site loads
 * (the engine's block stylesheet, the theme's, and the theme.json rules)
 * so a preview looks like the page will.
 */
final readonly class RenderController
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Caller $caller,
        private string $themesDir,
    ) {
    }

    /** Renders blocks for the editor's preview. */
    #[Route(Method::Post, '/minn-admin/v1/render-blocks', policy: new Policy(Access::Floor))]
    public function render(Request $request): Response
    {
        $blocks = $request->json()['blocks'] ?? null;
        if (!is_array($blocks)) {
            throw new RestError('invalid_blocks', 'Expected an array of block markup strings.', 400);
        }
        $rendered = [];
        foreach (array_slice($blocks, 0, 100) as $raw) {
            $rendered[] = Blocks::render((string) $raw);
        }
        return Reply::answer($request, ['rendered' => $rendered, 'styles' => $this->styles()]);
    }

    /** The stylesheets previews are scoped under: the engine's own, the theme's, and theme.json inline. */
    #[Route(Method::Get, '/minn-admin/v1/editor-styles', policy: new Policy(Access::Floor))]
    public function editorStyles(Request $request): Response
    {
        return Reply::answer($request, $this->styles());
    }

    /** @return array{urls: list<string>, inline: string} */
    private function styles(): array
    {
        $urls = [$this->permalinks->url('/minn/assets/blocks.css')];
        $inline = '';
        $theme = Theme::active($this->site, $this->permalinks, $this->themesDir);
        if ($theme !== null) {
            $styles = new GlobalStyles($theme, (new Templates($this->db, $this->posts, $theme))->userStyles());
            $inline = trim($styles->fontFaces() . "\n" . $styles->css());
            $themeStyle = $theme->styleUri();
            if ($themeStyle !== null) {
                $urls[] = $themeStyle;
            }
        }
        return ['urls' => $urls, 'inline' => $inline];
    }

}
