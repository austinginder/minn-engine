<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Html;
use Minn\Content\PasswordGate;
use Minn\Objects\Post;
use Minn\Blocks\RenderState;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\Users;
use Minn\I18n\Gettext;
use Minn\Runtime\Handoff;
use Minn\Runtime\MainQuery;
use Minn\Runtime\PostTags;
use Minn\Runtime\Runtime;

/**
 * core/latest-posts: the newest published posts as a list or a grid, each with
 * its featured image, title, author, date and excerpt or whole content as the
 * block asks (probe latest-posts).
 */
final readonly class LatestPosts
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Users $users,
    ) {
    }

    /**
     * The block's HTML. The posts come from WP_Query with the reference's
     * arguments (so pre_get_posts and the posts_* filters shape them), walked
     * as the reference walks them: the_post for each (loop_start and loop_end
     * around them, loop_no_results when there are none), and the main
     * query's post set up again after, found or not.
     */
    public function render(Block $block): string
    {
        $withDates = (bool) $block->attr('displayPostDate', false);
        $withAuthor = (bool) $block->attr('displayAuthor', false);
        $grid = $block->attr('postLayout', 'list') === 'grid';
        $query = Handoff::newQuery(self::args($block));
        $items = '';
        while ($query->have_posts()) {
            $query->the_post();
            if (!$query->post instanceof Post) {
                continue;
            }
            $post = PostRecord::fromPost($query->post);
            $link = $this->permalinks->forPost($post);
            $item = '<li>' . $this->featuredImage($block, $query->post, $post, $link)
                . '<a class="wp-block-latest-posts__post-title" href="' . Html::attr($link) . '">'
                . ($post->title === '' ? '(no title)' : Html::esc(PasswordGate::title($post))) . '</a>';
            if ($withAuthor) {
                $item .= '<div class="wp-block-latest-posts__post-author">' . sprintf(Gettext::text('by %s'), Html::esc($this->users->find($post->authorId)?->displayName ?? '')) . '</div>';
            }
            if ($withDates) {
                $item .= '<time datetime="' . Dates::iso($this->site, $post->date) . '" class="wp-block-latest-posts__post-date">'
                    . Dates::format($this->site, $post->date) . '</time>';
            }
            $items .= $item . $this->words($block, $query->post, $post, $link) . "</li>\n";
        }
        MainQuery::current()->reset_postdata();
        $own = ['wp-block-latest-posts__list'];
        if ($grid) {
            $own[] = 'is-grid';
            // A column count only when the block names one.
            if (isset($block->attrs['columns'])) {
                $own[] = 'columns-' . (int) $block->attrs['columns'];
            }
        }
        if ($withDates) {
            $own[] = 'has-dates';
        }
        if ($withAuthor) {
            $own[] = 'has-author';
        }
        if (!empty($block->attrs['align'])) {
            $own[] = 'align' . Styles::slug((string) $block->attrs['align']);
        }
        return Wrapper::open('ul', 'wp-block-latest-posts', $block, extraClasses: $own, trailingClasses: ['is-layout-flow', 'wp-block-latest-posts-is-layout-flow']) . $items . '</ul>';
    }

    /** The post's featured image, in its own div: its size, its alignment, its width and height as limits, a link when asked; '' without one. */
    private function featuredImage(Block $block, Post $object, PostRecord $post, string $link): string
    {
        if (!(bool) $block->attr('displayFeaturedImage', false)) {
            return '';
        }
        $width = (int) $block->attr('featuredImageSizeWidth', 0);
        $height = (int) $block->attr('featuredImageSizeHeight', 0);
        $style = ($width > 0 ? "max-width:{$width}px;" : '') . ($height > 0 ? "max-height:{$height}px;" : '');
        $image = (string) Handoff::postThumbnail($object, (string) $block->attr('featuredImageSizeSlug', 'thumbnail'), ['style' => $style]);
        if ($image === '') {
            return '';
        }
        if ((bool) $block->attr('addLinkToFeaturedImage', false)) {
            $image = '<a href="' . Html::attr($link) . '" aria-label="' . Html::attr(PasswordGate::title($post)) . '">' . $image . '</a>';
        }
        $align = (string) $block->attr('featuredImageAlign', '');
        return '<div class="' . Html::attr('wp-block-latest-posts__featured-image' . ($align !== '' ? ' align' . Styles::slug($align) : '')) . '">' . $image . '</div>';
    }

    /**
     * The excerpt, or the whole content, when the block shows either. The
     * excerpt is the post's own, through get_the_excerpt with the block's
     * length as excerpt_length; a generated one that was cut ends in "…" and
     * a Read more link in place of " [&hellip;]".
     */
    private function words(Block $block, Post $object, PostRecord $post, string $link): string
    {
        if (!(bool) $block->attr('displayPostContent', false)) {
            return '';
        }
        $full = $block->attr('displayPostContentRadio', 'excerpt') === 'full_post';
        // A post behind a password the reader has not given shows one sentence in either place (probe latest-posts).
        if (PostTags::passwordRequired($object)) {
            return '<div class="wp-block-latest-posts__post-' . ($full ? 'full-content' : 'excerpt') . '">' . Gettext::text('This content is password protected.') . '</div>';
        }
        if ($full) {
            $raw = $post->content;
            $content = RenderState::current()->postContent($post->id, static fn (): string => str_contains($raw, '<!-- wp:') ? Runtime::blockContentFilter($raw) : Runtime::classicContentFilter($raw));
            return '<div class="wp-block-latest-posts__post-full-content">' . $content . '</div>';
        }
        $length = (int) $block->attr('excerptLength', 55);
        $asked = static fn (): int => $length;
        Runtime::hooks()->add('excerpt_length', $asked, 20);
        try {
            $excerpt = (string) Handoff::excerpt($object);
            $allowed = (int) Runtime::hooks()->filter('excerpt_length', [$length]);
        } finally {
            Runtime::hooks()->remove('excerpt_length', $asked, 20);
        }
        if (str_ends_with($excerpt, ' [&hellip;]') && $allowed <= $length) {
            $excerpt = substr($excerpt, 0, -11) . "\u{2026} " . '<a class="wp-block-latest-posts__read-more" href="' . Html::attr($link) . '" rel="noopener">'
                . Gettext::text('Read more') . '<span class="screen-reader-text">: ' . Html::esc(PasswordGate::title($post)) . '</span></a>';
        }
        return '<div class="wp-block-latest-posts__post-excerpt">' . $excerpt . '</div>';
    }

    /**
     * The query the block asks for: its count, published posts in its order,
     * stickies not lifted, no total counted; its categories and author when
     * it names them.
     *
     * @return array<string, mixed>
     */
    private static function args(Block $block): array
    {
        $args = [
            'posts_per_page' => max(1, min(100, (int) $block->attr('postsToShow', 5))),
            'post_status' => 'publish',
            'order' => (string) $block->attr('order', 'desc'),
            'orderby' => (string) $block->attr('orderBy', 'date'),
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ];
        $categories = array_values(array_filter(array_map(static fn ($category) => is_array($category) ? (int) ($category['id'] ?? 0) : 0, (array) $block->attr('categories', [])), static fn (int $id) => $id > 0));
        if ($categories !== []) {
            $args['category__in'] = $categories;
        }
        $author = (int) $block->attr('selectedAuthor', 0);
        if ($author > 0) {
            $args['author'] = $author;
        }
        return $args;
    }
}
