<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Content\TermRecord;
use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Blocks\Dynamic\Dates;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Blocks\Wrapper;
use Minn\Blocks\Styles;
use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Content\PasswordGate;
use Minn\Content\Reader;
use Minn\Extension\Extensions;
use Minn\Front\Kind;
use Minn\Runtime\Runtime;

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

    /** Registers this family's blocks with the renderer. */
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
        $title = Texturize::text(PasswordGate::title($this->previewSource($post, $renderer->context()->resolution) ?? $post));
        if ((bool) $block->attr('isLink', false)) {
            $title = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '" target="' . self::target($block) . '" >' . $title . '</a>';
        }
        return self::open($tag, ['wp-block-post-title', ...Styles::classes($block->attrs)], $block, styleFirst: true) . $title . '</' . $tag . '>';
    }

    /** In a loop the content stops at the more tag with a "(more…)" link; on its own page it runs whole. */
    private function content(Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $post = $context->post();
        if ($post === null) {
            return '';
        }
        $raw = (string) ($this->previewSource($post, $context->resolution) ?? $post)['post_content'];
        if (PasswordGate::is($post)) {
            $raw = PasswordGate::form($post, $this->permalinks->url(''), $this->permalinks->forPost($post));
        }
        // An attachment's page shows the attachment even with nothing written (prepend_attachment, through the_content).
        if (trim($raw) === '' && !($post->type === 'attachment' && Runtime::booted())) {
            return '';
        }
        $more = strpos($raw, '<!--more-->');
        $suffix = '';
        if ($context->inLoop() && $more !== false) {
            $raw = substr($raw, 0, $more);
            $suffix = "\n" . ' <a href="' . Html::attr($this->permalinks->forPost($post) . '#more-' . $post->id) . '" class="more-link"><span aria-label="Continue reading ' . Html::attr(Texturize::text($post->title)) . '">(more&hellip;)</span></a>';
            $content = rtrim(Blocks::render($raw)) . $suffix;
        } else {
            $content = Blocks::render(str_replace('<!--more-->', '<span id="more-' . $post->id . '"></span>', $raw));
        }
        $seams = Extensions::runner();
        if ($seams !== null) {
            $content = $seams->filterContent($content, $post);
        }
        if (Runtime::booted()) {
            $content = Runtime::shortcodes()->apply($content);
            $content = Runtime::contentFilter($content);
        }
        $align = Styles::align($block->attrs);
        return Wrapper::open(
            'div',
            'wp-block-post-content',
            $block,
            styleFirst: true,
            extraClasses: array_values(array_filter(['entry-content', $align])),
            // Without a layout attribute the block still carries its default flow layout classes.
            trailingClasses: Layout::classes('post-content', $block->attrs),
        ) . $content . '</div>';
    }

    private function date(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $local = $post->date;
        $time = '<time datetime="' . Dates::iso($this->site, $local) . '">' . Dates::format($this->site, $local, (string) $block->attr('format', '')) . '</time>';
        if ((bool) $block->attr('isLink', false)) {
            $time = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '">' . $time . '</a>';
        }
        return self::open('div', ['wp-block-post-date', ...Styles::classes($block->attrs)], $block, styleFirst: true, linkColorClass: true) . $time . '</div>';
    }

    private function authorName(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        $user = $post === null ? null : $this->users->find($post->authorId);
        if ($user === null) {
            return '';
        }
        $name = Html::esc((string) $user['display_name']);
        if ((bool) $block->attr('isLink', false)) {
            $name = '<a href="' . Html::attr($this->permalinks->forAuthor($user)) . '" target="' . self::target($block) . '" class="wp-block-post-author-name__link">' . $name . '</a>';
        }
        return self::open('div', ['wp-block-post-author-name', ...Styles::classes($block->attrs)], $block) . $name . '</div>';
    }

    private function excerpt(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $text = PasswordGate::is($post) ? PasswordGate::EXCERPT : trim(strip_tags(Excerpt::render($post)));
        // The reference leaves a space after the text where a "more" link would go.
        return self::open('div', ['wp-block-post-excerpt', ...Styles::classes($block->attrs)], $block, linkColorClass: true)
            . '<p class="wp-block-post-excerpt__excerpt">' . $text . ' </p></div>';
    }

    private function featuredImage(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        $thumbnail = $post === null ? 0 : (int) ($this->posts->meta($post->id, '_thumbnail_id') ?? 0);
        if ($thumbnail === 0) {
            return '';
        }
        $file = $this->posts->meta($thumbnail, '_wp_attached_file');
        if ($file === null) {
            return '';
        }
        $alt = (string) ($this->posts->meta($thumbnail, '_wp_attachment_image_alt') ?? '');
        $isLink = (bool) $block->attr('isLink', false);
        if ($isLink && $alt === '') {
            // A linked image without alt text borrows the post title so the link has a name.
            $alt = trim(strip_tags($post->title));
        }
        // Declared in the reference's fixed order: ratio, height, width, then the fit.
        $style = '';
        if (!empty($block->attrs['aspectRatio'])) {
            $style .= 'aspect-ratio:' . $block->attrs['aspectRatio'] . ';';
        }
        if (!empty($block->attrs['height'])) {
            $style .= 'height:' . $block->attrs['height'] . ';';
        }
        if (!empty($block->attrs['width'])) {
            $style .= 'width:' . $block->attrs['width'] . ';';
        }
        $style .= 'object-fit:' . (string) $block->attr('scale', 'cover') . ';';
        $img = $renderer->images()->featured($thumbnail, $alt, $style);
        if ($img === '') {
            return '';
        }
        if ($isLink) {
            $img = '<a href="' . Html::attr($this->permalinks->forPost($post)) . '" target="_self" >' . $img . '</a>';
        }
        $align = Styles::align($block->attrs);
        return self::open('figure', ['wp-block-post-featured-image', ...($align === null ? [] : [$align]), ...Styles::classes($block->attrs)], $block, styleFirst: true) . $img . '</figure>';
    }

    private function terms(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $taxonomy = (string) $block->attr('term', 'category');
        $terms = $this->posts->terms($post->id, $taxonomy);
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
        $separator = '<span class="wp-block-post-terms__separator">' . Html::esc((string) $block->attr('separator', ', ')) . '</span>';
        return self::open('div', ['taxonomy-' . $taxonomy, 'wp-block-post-terms', ...Styles::classes($block->attrs)], $block, styleFirst: true, blockName: 'core/post-terms')
            . implode($separator, $links) . '</div>';
    }

    /** Off a single post or page there is no neighbour to link: nothing at all. */
    private function navigationLink(Block $block, Renderer $renderer): string
    {
        if (!in_array($renderer->context()->resolution->kind, [Kind::Single, Kind::Page], true)) {
            return '';
        }
        $post = $renderer->context()->post();
        $next = $block->attr('type', 'next') !== 'previous';
        $direction = $next ? 'next' : 'previous';
        $target = match (true) {
            $post === null => null,
            // An attachment's page goes back to the post it belongs to (a loose one to itself), and nowhere forward, as on the reference.
            $post->type === 'attachment' => $next ? null : ($post->parentId > 0 ? $this->posts->find($post->parentId) : $post),
            default => $next ? $this->posts->next($post) : $this->posts->previous($post),
        };
        $inner = '';
        if ($target !== null) {
            $arrow = (string) $block->attr('arrow', 'none');
            if (!in_array($arrow, ['none', 'arrow', 'chevron'], true)) {
                $arrow = 'none';
            }
            $glyph = $arrow === 'chevron' ? ($next ? '›' : '‹') : ($next ? '→' : '←');
            $label = (bool) $block->attr('showTitle', false) ? Texturize::text($target->title) : ucfirst($direction);
            $href = $target->type === 'attachment' ? $this->permalinks->forAttachment($target) : $this->permalinks->forPost($target);
            $link = '<a href="' . Html::attr($href) . '" rel="' . ($next ? 'next' : 'prev') . '">' . $label . '</a>';
            $span = $arrow === 'none' ? '' : '<span class="wp-block-post-navigation-link__arrow-' . $direction . ' is-arrow-' . $arrow . '" aria-hidden="true">' . $glyph . '</span>';
            $inner = $next ? $link . $span : $span . $link;
        }
        return '<div class="post-navigation-link-' . $direction . ' wp-block-post-navigation-link">' . $inner . '</div>';
    }

    private function termRow(string $taxonomy, int $termId): ?TermRecord
    {
        return (new \Minn\Content\Terms(\Minn\Db::current()))->find($taxonomy, $termId);
    }

    /**
     * The wrapper's opening tag. Text alignment lands before the block's own
     * class, font size and custom classes after it.
     *
     * @param list<string> $classes
     */
    /** On a preview, the reader's newest autosave of this post stands in for its stored fields. */
    private function previewSource(PostRecord $post, \Minn\Front\Resolution $resolution): ?PostRecord
    {
        if (!$resolution->preview || $resolution->id() !== $post->id) {
            return null;
        }
        $autosave = $this->posts->newestAutosave($post->id, Reader::current()->userId);
        return $autosave === null ? null : PostRecord::fromRow(['post_content' => $autosave->content, 'post_title' => $autosave->title] + $post->row());
    }

    private static function target(Block $block): string
    {
        return (string) $block->attr('linkTarget', '_self') === '_blank' ? '_blank' : '_self';
    }

    private static function open(string $tag, array $classes, Block $block, bool $styleFirst = false, string $blockName = '', bool $linkColorClass = false): string
    {
        // The wp-block-* entry is the block's own class; the preset classes come from the attributes.
        $blockClass = '';
        $extra = [];
        foreach ($classes as $class) {
            if (str_starts_with($class, 'wp-block-') && $blockClass === '') {
                $blockClass = $class;
            } elseif (!str_starts_with($class, 'has-') && $class !== $block->className()) {
                $extra[] = $class;
            }
        }
        return Wrapper::open($tag, $blockClass, $block, $styleFirst, $blockName, linkColorClass: $linkColorClass, extraClasses: $extra);
    }
}
