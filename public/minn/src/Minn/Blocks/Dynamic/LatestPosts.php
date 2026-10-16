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
use Minn\Runtime\Handoff;
use Minn\Runtime\MainQuery;

/** core/latest-posts: the newest published posts as a list, optionally dated. */
final readonly class LatestPosts
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
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
        $query = Handoff::newQuery(self::args($block));
        $items = '';
        while ($query->have_posts()) {
            $query->the_post();
            if (!$query->post instanceof Post) {
                continue;
            }
            $post = PostRecord::fromPost($query->post);
            $item = '<li><a class="wp-block-latest-posts__post-title" href="' . Html::attr($this->permalinks->forPost($post)) . '">'
                . ($post->title === '' ? '(no title)' : Html::esc(PasswordGate::title($post))) . '</a>';
            if ($withDates) {
                $item .= '<time datetime="' . Dates::iso($this->site, $post->date) . '" class="wp-block-latest-posts__post-date">'
                    . Dates::format($this->site, $post->date) . '</time>';
            }
            $items .= $item . "</li>\n";
        }
        MainQuery::current()->reset_postdata();
        $classes = 'wp-block-latest-posts__list' . ($withDates ? ' has-dates' : '')
            . ' wp-block-latest-posts is-layout-flow wp-block-latest-posts-is-layout-flow';
        return '<ul class="' . $classes . '">' . $items . '</ul>';
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
