<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Content\Site;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Theme\Templates;
use Minn\Theme\Theme;

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
        if ($markup === null) {
            return '';
        }
        $area = (string) $block->attr('area', $this->theme->partArea($slug));
        $tag = (string) $block->attr('tagName', match ($area) { 'header' => 'header', 'footer' => 'footer', default => 'div' });
        $inner = $renderer->renderBlocks(Parser::parse($markup));
        return '<' . $tag . ' class="wp-block-template-part">' . $inner . '</' . $tag . '>';
    }

    private function pattern(Block $block, Renderer $renderer): string
    {
        $markup = $this->theme->pattern((string) $block->attr('slug', ''));
        return $markup === null ? '' : $renderer->renderBlocks(Parser::parse($markup));
    }

    private function siteTitle(Block $block, Renderer $renderer): string
    {
        $name = (string) ($this->site->option('blogname') ?? '');
        $level = (int) $block->attr('level', 1);
        $tag = $level === 0 ? 'p' : 'h' . $level;
        $resolution = $renderer->context()->resolution;
        $current = $resolution->kind === Kind::Home && $resolution->paged === 1 ? ' aria-current="page"' : '';
        $classes = implode(' ', ['wp-block-site-title', ...\Minn\Blocks\Styles::classes($block->attrs)]);
        $link = (bool) $block->attr('isLink', true);
        $inner = $link
            ? '<a href="' . Html::attr($this->permalinks->url('')) . '" target="_self" rel="home"' . $current . '>' . Html::esc($name) . '</a>'
            : Html::esc($name);
        return '<' . $tag . ' class="' . $classes . '">' . $inner . '</' . $tag . '>';
    }

    private function siteTagline(Block $block): string
    {
        $tagline = (string) ($this->site->option('blogdescription') ?? '');
        if ($tagline === '') {
            return '';
        }
        $level = (int) $block->attr('level', 0);
        $tag = $level === 0 ? 'p' : 'h' . $level;
        $classes = implode(' ', ['wp-block-site-tagline', ...\Minn\Blocks\Styles::classes($block->attrs)]);
        return '<' . $tag . ' class="' . $classes . '">' . Html::esc($tagline) . '</' . $tag . '>';
    }
}
