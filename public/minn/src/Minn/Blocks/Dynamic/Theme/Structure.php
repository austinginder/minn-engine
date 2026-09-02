<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Wrapper;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Content\Site;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Runtime\BlockHooks;
use Minn\Support\Html;
use Minn\Theme\Templates;
use Minn\Theme\Theme;
use Minn\Blocks\RenderState;

/** template-part, pattern, site-title, site-tagline, site-logo. */
final readonly class Structure
{
    public function __construct(
        private Theme $theme,
        private Templates $templates,
        private Site $site,
        private Permalinks $permalinks,
    ) {
    }

    /** Registers this family's blocks with the renderer. */
    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/template-part', $this->templatePart(...));
        $renderer->registerDynamic('core/pattern', $this->pattern(...));
        $renderer->registerDynamic('core/site-title', $this->siteTitle(...));
        $renderer->registerDynamic('core/site-tagline', $this->siteTagline(...));
        $renderer->registerDynamic('core/site-logo', static fn () => '');
    }

    /** The wrapper element follows the part's area: header, footer, or a div. */
    private function templatePart(Block $block, Renderer $renderer): string
    {
        $slug = (string) $block->attr('slug', '');
        $markup = $this->templates->part($slug);
        if ($markup === null || !$renderer->state()->enter('part:' . $slug)) {
            return '';
        }
        $area = (string) $block->attr('area', $this->theme->partArea($slug));
        $tag = (string) $block->attr('tagName', match ($area) { 'header' => 'header', 'footer' => 'footer', default => 'div' });
        if (!in_array($tag, ['header', 'footer', 'div', 'main', 'section', 'article', 'aside', 'nav'], true)) {
            $tag = 'div';
        }
        $markup = BlockHooks::forPart($markup, $slug, $area);
        $inner = $renderer->renderBlocks(Parser::parse($markup));
        $renderer->state()->leave('part:' . $slug);
        $classes = trim($block->className() . ' wp-block-template-part');
        return '<' . $tag . ' class="' . Html::attr($classes) . '">' . $inner . '</' . $tag . '>';
    }

    private function pattern(Block $block, Renderer $renderer): string
    {
        $slug = (string) $block->attr('slug', '');
        // A theme pattern first; a plugin's registered pattern is the
        // fallback, and the registry has already applied its hooks.
        $markup = $this->theme->pattern($slug);
        $registered = $markup === null ? BlockHooks::registeredPattern($slug) : null;
        $markup ??= $registered;
        if ($markup === null || !$renderer->state()->enter('pattern:' . $slug)) {
            return '';
        }
        $meta = $this->theme->patternMeta($slug);
        $markup = $registered !== null ? $markup : BlockHooks::forPattern($markup, $slug, $meta['blockTypes'], $meta['categories']);
        $out = $renderer->renderBlocks(Parser::parse($markup));
        $renderer->state()->leave('pattern:' . $slug);
        return $out;
    }

    private function siteTitle(Block $block, Renderer $renderer): string
    {
        $name = (string) ($this->site->option('blogname') ?? '');
        $level = (int) $block->attr('level', 1);
        $tag = $level === 0 ? 'p' : 'h' . $level;
        $resolution = $renderer->context()->resolution;
        // The posts page is a listing but not the home the site-title link points at.
        // Only a page render is on a page; a render with no request behind it (REST, CLI) marks nothing current.
        $current = $renderer->context()->front && (($resolution->kind === Kind::Home && !$resolution->postsPage) || $resolution->front) && $resolution->paged === 1 ? ' aria-current="page"' : '';
        $link = (bool) $block->attr('isLink', true);
        $inner = $link
            ? '<a href="' . Html::attr($this->permalinks->url('')) . '" target="_self" rel="home"' . $current . '>' . Html::esc($name) . '</a>'
            : Html::esc($name);
        return Wrapper::open($tag, 'wp-block-site-title', $block, styleFirst: true, linkColorClass: true) . $inner . '</' . $tag . '>';
    }

    private function siteTagline(Block $block): string
    {
        $tagline = (string) ($this->site->option('blogdescription') ?? '');
        if ($tagline === '') {
            return '';
        }
        $level = (int) $block->attr('level', 0);
        $tag = $level === 0 ? 'p' : 'h' . $level;
        return Wrapper::open($tag, 'wp-block-site-tagline', $block, styleFirst: true, linkColorClass: true) . Html::esc($tagline) . '</' . $tag . '>';
    }

}
