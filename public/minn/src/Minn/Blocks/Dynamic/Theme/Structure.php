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
use Minn\Content\Texturize;
use Minn\Support\Escape;
use Minn\Runtime\Handoff;
use Minn\Runtime\QueryAnswers;
use Minn\Runtime\TemplateFiles;
use Minn\Runtime\Records;
use Minn\Runtime\Runtime;
use Minn\Html;
use Minn\Runtime\Themes;
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
        // The site logo is the facade's (minn_block_renderers): it is made of get_custom_logo.
    }

    /** Tags a part may be wrapped in: tagName names one of these or the area's tag stands (security review 2026-08-28, finding 4). */
    private const PART_TAGS = ['header', 'footer', 'div', 'main', 'section', 'article', 'aside', 'nav'];

    /**
     * A template part, as the reference renders one (probe
     * template-part-lookup): the part found, the tag its area wraps it in
     * asked for, its shortcodes run before its blocks are rendered, then
     * texturized, its smilies converted and its images and iframes fitted
     * out for the part's area; wrapped in tagName when it names a tag the
     * engine allows, else the area's.
     */
    private function templatePart(Block $block, Renderer $renderer): string
    {
        $slug = (string) $block->attr('slug', '');
        if (!Runtime::booted()) {
            return $this->unbooted($block, $slug, $renderer);
        }
        $found = $this->lookedUp($block, $slug);
        if ($found === null || !$renderer->state()->enter('part:' . $slug)) {
            return '';
        }
        [$markup, $area] = $found;
        $areaTag = self::areaTag($area);
        $markup = Runtime::shortcodes()->apply((string) Runtime::shortcodes()->unautop($markup), 'run');
        $inner = $renderer->renderBlocks(self::blocksOf($markup));
        $renderer->state()->leave('part:' . $slug);
        $inner = (string) Handoff::convertSmilies(Texturize::apply($inner));
        $inner = $renderer->images()->content($inner, "template_part_{$area}");
        $given = (string) $block->attr('tagName', '');
        $tag = $given === '' ? $areaTag : (string) Escape::attr(Escape::tag($given));
        $tag = in_array($tag, self::PART_TAGS, true) ? $tag : $areaTag;
        $classes = trim($block->className() . ' wp-block-template-part');
        return '<' . $tag . ' class="' . Escape::attr($classes) . '">' . $inner . '</' . $tag . '>';
    }

    /**
     * A part's blocks, parsed as do_blocks parses them: by the parser
     * block_parser_class names, the engine's own unless a plugin swaps in
     * another.
     *
     * @return list<Block>
     */
    private static function blocksOf(string $markup): array
    {
        $class = Runtime::hooks()->filter('block_parser_class', ['WP_Block_Parser']);
        if ($class === 'WP_Block_Parser' || !is_string($class) || !class_exists($class)) {
            return Parser::parse($markup);
        }
        return array_values(array_map(static fn (mixed $parsed): Block => Block::fromArray((array) $parsed), (array) (new $class())->parse($markup)));
    }

    /** The tag an area's parts are wrapped in, from the areas core knows; a div for any other. */
    private static function areaTag(string $area): string
    {
        foreach ((array) TemplateFiles::partAreas() as $definition) {
            if (is_array($definition) && ($definition['area'] ?? null) === $area && isset($definition['area_tag'])) {
                $tag = (string) $definition['area_tag'];
                return in_array($tag, self::PART_TAGS, true) ? $tag : 'div';
            }
        }
        return 'div';
    }

    /** A part with no runtime to ask: the saved part, else the theme's file, in the tag tagName or its area names. */
    private function unbooted(Block $block, string $slug, Renderer $renderer): string
    {
        $markup = $this->templates->partSource($slug)[2];
        if ($markup === null || !$renderer->state()->enter('part:' . $slug)) {
            return '';
        }
        $area = (string) $block->attr('area', $this->theme->partArea($slug));
        $inner = $renderer->renderBlocks(Parser::parse($markup));
        $renderer->state()->leave('part:' . $slug);
        $tag = (string) $block->attr('tagName', match ($area) { 'header' => 'header', 'footer' => 'footer', default => 'div' });
        $tag = in_array($tag, self::PART_TAGS, true) ? $tag : 'div';
        $classes = trim($block->className() . ' wp-block-template-part');
        return '<' . $tag . ' class="' . Html::attr($classes) . '">' . $inner . '</' . $tag . '>';
    }

    /**
     * A part's markup and area as the reference finds them, with what it
     * tells plugins on the way (probe template-part-lookup): only a part of
     * the active theme is looked for, first as a saved part through
     * WP_Query (so pre_get_posts can steer the lookup and loop_no_results
     * hears a miss; the engine answers it from the theme's saved templates
     * unless a plugin changes it), its template made from the post (the
     * area its term names), then the theme's file through
     * get_block_file_template; the file looked for again, and each found or
     * missing one announced. Null when there is none.
     *
     * @return array{0: string, 1: string}|null
     */
    private function lookedUp(Block $block, string $slug): ?array
    {
        $stylesheet = Themes::stylesheet();
        if ($slug === '') {
            return null;
        }
        $theme = isset($block->attrs['theme']) ? (string) $block->attrs['theme'] : (string) Themes::stylesheet();
        if ($theme !== $stylesheet) {
            return null;
        }
        $id = $theme . '//' . $slug;
        $this->templates->load();
        $lookup = Handoff::newQuery();
        QueryAnswers::expect($lookup, fn (): array => $this->templates->savedPartIds($slug));
        $lookup->query(['post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name__in' => [$slug], 'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme]], 'posts_per_page' => 1, 'no_found_rows' => true, 'lazy_load_term_meta' => false]);
        if ($lookup->have_posts()) {
            $post = $lookup->next_post();
            $template = TemplateFiles::fromPost($post);
            if (is_object($template) && !Records::isError($template)) {
                $markup = (string) $template->content;
                Runtime::hooks()->action('render_block_core_template_part_post', [$id, $block->attrs, $post, $markup]);
                return [$markup, (string) ($template->area ?? 'uncategorized')];
            }
        }
        $template = TemplateFiles::fileTemplate($id, 'wp_template_part');
        $file = TemplateFiles::file('wp_template_part', $slug);
        if (is_object($template) && isset($template->content) && (string) $template->content !== '') {
            $markup = (string) $template->content;
            Runtime::hooks()->action('render_block_core_template_part_file', [$id, $block->attrs, $file['path'] ?? '', $markup]);
            return [$markup, (string) ($template->area ?? 'uncategorized')];
        }
        Runtime::hooks()->action('render_block_core_template_part_none', [$id, $block->attrs, '']);
        return null;
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
