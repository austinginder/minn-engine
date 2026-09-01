<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Content\Site;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Content\PasswordGate;

/** core/latest-posts: the newest published posts as a list, optionally dated. */
final readonly class LatestPosts
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Permalinks $permalinks,
    ) {
    }

    public function render(Block $block): string
    {
        $count = max(1, min(100, (int) $block->attr('postsToShow', 5)));
        $withDates = (bool) $block->attr('displayPostDate', false);
        $posts = PostRecord::fromRows($this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'
             ORDER BY post_date DESC LIMIT ?",
            [$count],
        ));
        $items = [];
        foreach ($posts as $post) {
            $item = '<li><a class="wp-block-latest-posts__post-title" href="' . Html::attr($this->permalinks->forPost($post)) . '">'
                . ($post['post_title'] === '' ? '(no title)' : Html::esc(PasswordGate::title($post))) . '</a>';
            if ($withDates) {
                $item .= '<time datetime="' . Dates::iso($this->site, (string) $post['post_date']) . '" class="wp-block-latest-posts__post-date">'
                    . Dates::format($this->site, (string) $post['post_date']) . '</time>';
            }
            $items[] = $item . '</li>';
        }
        $classes = 'wp-block-latest-posts__list' . ($withDates ? ' has-dates' : '')
            . ' wp-block-latest-posts is-layout-flow wp-block-latest-posts-is-layout-flow';
        return '<ul class="' . $classes . '">' . implode("\n", $items) . "\n</ul>";
    }
}
