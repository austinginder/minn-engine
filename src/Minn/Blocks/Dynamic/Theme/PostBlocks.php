<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Dynamic\Dates;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/** The post-* blocks: they render the context's current post. */
final readonly class PostBlocks
{
    public function __construct(
        private Posts $posts,
        private Users $users,
        private Site $site,
        private Permalinks $permalinks,
    ) {
    }

    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/post-title', $this->title(...));
        $renderer->registerDynamic('core/post-content', $this->content(...));
        $renderer->registerDynamic('core/post-date', $this->date(...));
        $renderer->registerDynamic('core/post-author-name', $this->authorName(...));
        $renderer->registerDynamic('core/post-excerpt', $this->excerpt(...));
        $renderer->registerDynamic('core/post-featured-image', $this->featuredImage(...));
        $renderer->registerDynamic('core/post-terms', $this->terms(...));
        $renderer->registerDynamic('core/post-navigation-link', $this->navigationLink(...));
    }

    private function title(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $level = (int) $block->attr('level', 2);
        $tag = $level === 0 ? 'p' : 'h' . $level;
        $title = Texturize::text((string) $post['post_title']);
        if ((bool) $block->attr('isLink', false)) {
            $title = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '" target="' . Html::attr((string) $block->attr('linkTarget', '_self')) . '" >' . $title . '</a>';
        }
        return self::open($tag, ['wp-block-post-title', ...Styles::classes($block->attrs)], $block) . $title . '</' . $tag . '>';
    }

    /** In a loop the content stops at the more tag with a "(more…)" link; on its own page it runs whole. */
    private function content(Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $post = $context->post();
        if ($post === null) {
            return '';
        }
        $raw = (string) $post['post_content'];
        if (trim($raw) === '') {
            return '';
        }
        $more = strpos($raw, '<!--more-->');
        $suffix = '';
        if ($context->inLoop() && $more !== false) {
            $raw = substr($raw, 0, $more);
            $suffix = "\n" . ' <a href="' . Html::attr($this->permalinks->forPost($post) . '#more-' . (int) $post['ID']) . '" class="more-link"><span aria-label="Continue reading ' . Html::attr(Texturize::text((string) $post['post_title'])) . '">(more&hellip;)</span></a>';
            $content = rtrim(Blocks::render($raw)) . $suffix;
        } else {
            $content = Blocks::render(str_replace('<!--more-->', '<span id="more-' . (int) $post['ID'] . '"></span>', $raw));
        }
        $classes = array_values(array_filter([
            'entry-content',
            Styles::align($block->attrs),
            'wp-block-post-content',
            ...Styles::classes($block->attrs),
            ...(isset($block->attrs['layout']) ? Layout::classes('post-content', $block->attrs) : []),
        ]));
        return '<div class="' . implode(' ', $classes) . '">' . $content . '</div>';
    }

    private function date(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $local = (string) $post['post_date'];
        $time = '<time datetime="' . Dates::iso($this->site, $local) . '">' . Dates::format($this->site, $local) . '</time>';
        if ((bool) $block->attr('isLink', false)) {
            $time = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '">' . $time . '</a>';
        }
        return self::open('div', ['wp-block-post-date', ...Styles::classes($block->attrs)], $block, styleFirst: true) . $time . '</div>';
    }

    private function authorName(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        $user = $post === null ? null : $this->users->find((int) $post['post_author']);
        if ($user === null) {
            return '';
        }
        $name = Html::esc((string) $user['display_name']);
        if ((bool) $block->attr('isLink', false)) {
            $name = '<a href="' . Html::attr($this->permalinks->forAuthor($user)) . '" target="' . Html::attr((string) $block->attr('linkTarget', '_self')) . '" class="wp-block-post-author-name__link">' . $name . '</a>';
        }
        return self::open('div', ['wp-block-post-author-name', ...Styles::classes($block->attrs)], $block) . $name . '</div>';
    }

    private function excerpt(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $text = trim(strip_tags(Excerpt::render($post)));
        return self::open('div', ['wp-block-post-excerpt', ...Styles::classes($block->attrs)], $block)
            . '<p class="wp-block-post-excerpt__excerpt">' . $text . '</p></div>';
    }

    private function featuredImage(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        $thumbnail = $post === null ? 0 : (int) ($this->posts->meta((int) $post['ID'], '_thumbnail_id') ?? 0);
        if ($thumbnail === 0) {
            return '';
        }
        $file = $this->posts->meta($thumbnail, '_wp_attached_file');
        if ($file === null) {
            return '';
        }
        $alt = (string) ($this->posts->meta($thumbnail, '_wp_attachment_image_alt') ?? '');
        $img = '<img src="' . Html::attr($this->permalinks->url('/wp-content/uploads/' . $file)) . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image wp-image-' . $thumbnail . '" alt="' . Html::attr($alt) . '"/>';
        $img = $renderer->images()->enrich($img, front: $renderer->context()->front);
        if ((bool) $block->attr('isLink', false)) {
            $img = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '" target="_self" >' . $img . '</a>';
        }
        return self::open('figure', ['wp-block-post-featured-image', ...Styles::classes($block->attrs)], $block, styleFirst: true) . $img . '</figure>';
    }

    private function terms(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $taxonomy = (string) $block->attr('term', 'category');
        $terms = $this->posts->terms((int) $post['ID'], $taxonomy);
        if ($terms === []) {
            return '';
        }
        $links = [];
        foreach ($terms as [$termId, $slug]) {
            $term = $this->termRow($taxonomy, $termId);
            if ($term === null) {
                continue;
            }
            $links[] = '<a href="' . Html::attr($this->permalinks->forTerm($term)) . '" rel="tag">' . Html::esc((string) $term['name']) . '</a>';
        }
        $separator = '<span class="wp-block-post-terms__separator">' . (string) $block->attr('separator', ', ') . '</span>';
        return self::open('div', ['taxonomy-' . $taxonomy, 'wp-block-post-terms', ...Styles::classes($block->attrs)], $block, styleFirst: true, blockName: 'core/post-terms')
            . implode($separator, $links) . '</div>';
    }

    private function navigationLink(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        $next = $block->attr('type', 'next') !== 'previous';
        $direction = $next ? 'next' : 'previous';
        $target = $post === null ? null : $this->posts->adjacent($post, $next);
        $inner = '';
        if ($target !== null) {
            $arrow = $block->attr('arrow', 'none');
            $glyph = $arrow === 'chevron' ? ($next ? '›' : '‹') : ($next ? '→' : '←');
            $label = (bool) $block->attr('showTitle', false) ? Texturize::text((string) $target['post_title']) : ucfirst($direction);
            $link = '<a href="' . Html::attr($this->permalinks->forPost($target)) . '" rel="' . ($next ? 'next' : 'prev') . '">' . $label . '</a>';
            $span = $arrow === 'none' ? '' : '<span class="wp-block-post-navigation-link__arrow-' . $direction . ' is-arrow-' . $arrow . '" aria-hidden="true">' . $glyph . '</span>';
            $inner = $next ? $link . $span : $span . $link;
        }
        return '<div class="post-navigation-link-' . $direction . ' wp-block-post-navigation-link">' . $inner . '</div>';
    }

    private function termRow(string $taxonomy, int $termId): ?array
    {
        return (new \Minn\Content\Terms(\Minn\Db::shared()))->find($taxonomy, $termId);
    }

    /**
     * The wrapper's opening tag. Text alignment lands before the block's own
     * class, font size and custom classes after it.
     *
     * @param list<string> $classes
     */
    private static function open(string $tag, array $classes, Block $block, bool $styleFirst = false, string $blockName = ''): string
    {
        $style = Styles::inline((array) $block->attr('style', []));
        $className = $block->className();
        $classes = array_values(array_filter($classes, static fn (string $c) => $c !== $className && (!str_starts_with($c, 'has-') || str_ends_with($c, '-font-size'))));
        if (!empty($block->attrs['textAlign'])) {
            array_unshift($classes, 'has-text-align-' . $block->attrs['textAlign']);
        }
        if ($className !== '') {
            // A custom class and its numbered style companion lead, before the block's own class.
            $numbered = $blockName === '' ? null : Renderer::numberedStyle($blockName, $className);
            array_splice($classes, 1, 0, array_values(array_filter([$className, $numbered])));
        }
        $classAttr = ' class="' . implode(' ', $classes) . '"';
        $styleAttr = $style === '' ? '' : ' style="' . $style . '"';
        return '<' . $tag . ($styleFirst ? $styleAttr . $classAttr : $classAttr . $styleAttr) . '>';
    }
}
