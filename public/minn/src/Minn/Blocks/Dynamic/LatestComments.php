<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Content\CommentRecord;
use Minn\Blocks\Block;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Rest\UserObject;
use Minn\Support\Html;
use Minn\Support\Kses;

/** core/latest-comments: the newest approved comments with avatar, meta, and a 20-word excerpt. */
final readonly class LatestComments
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
    ) {
    }

    /** The block's HTML. */
    public function render(Block $block, \Minn\Blocks\Renderer $renderer): string
    {
        $count = max(1, (int) $block->attr('commentsToShow', 5));
        $avatars = (bool) $block->attr('displayAvatar', true);
        $dates = (bool) $block->attr('displayDate', true);
        $excerpts = (bool) $block->attr('displayExcerpt', true);
        $comments = CommentRecord::fromRows($this->db->rows(
            "SELECT c.* FROM {$this->db->table('comments')} c
             INNER JOIN {$this->db->table('posts')} p ON p.ID = c.comment_post_ID AND p.post_status = 'publish' AND p.post_password = ''
             WHERE c.comment_approved = '1' AND c.comment_type IN ('', 'comment')
             ORDER BY c.comment_date_gmt DESC LIMIT ?",
            [min(100, $count)],
        ));
        $classes = ($avatars ? 'has-avatars ' : '') . ($dates ? 'has-dates ' : '') . ($excerpts ? 'has-excerpts ' : '') . 'wp-block-latest-comments';
        if ($comments === []) {
            return '<div class="' . $classes . ' no-comments">No comments to show.</div>';
        }
        $items = '';
        foreach ($comments as $comment) {
            $post = $this->posts->find((int) $comment['comment_post_ID']);
            $item = '<li class="wp-block-latest-comments__comment">';
            if ($avatars) {
                if ($renderer->context()->front) {
                    \Minn\Blocks\RenderState::nextImage();
                }
                $hash = hash('sha256', strtolower(trim((string) $comment['comment_author_email'])));
                $item .= "<img alt='' src='https://secure.gravatar.com/avatar/{$hash}?s=48&#038;d=mm&#038;r=g' srcset='https://secure.gravatar.com/avatar/{$hash}?s=96&#038;d=mm&#038;r=g 2x' class='avatar avatar-48 photo wp-block-latest-comments__comment-avatar' height='48' width='48' />";
            }
            $author = Kses::url((string) $comment['comment_author_url']) !== ''
                ? '<a class="wp-block-latest-comments__comment-author" href="' . Html::attr(Kses::url((string) $comment['comment_author_url'])) . '">' . Html::esc((string) $comment['comment_author']) . '</a>'
                : '<span class="wp-block-latest-comments__comment-author">' . Html::esc((string) $comment['comment_author']) . '</span>';
            $link = $post === null ? '' : $this->permalinks->forPost($post) . '#comment-' . (int) $comment['comment_ID'];
            $item .= '<article><footer class="wp-block-latest-comments__comment-meta">' . $author . ' on '
                . '<a class="wp-block-latest-comments__comment-link" href="' . Html::attr($link) . '">' . ($post['post_title'] ?? '') . '</a>';
            if ($dates) {
                $item .= '<time datetime="' . Dates::iso($this->site, (string) $comment['comment_date']) . '" class="wp-block-latest-comments__comment-date">'
                    . Dates::format($this->site, (string) $comment['comment_date']) . '</time>';
            }
            $item .= '</footer>';
            if ($excerpts) {
                $item .= '<div class="wp-block-latest-comments__comment-excerpt"><p>' . self::excerpt((string) $comment['comment_content']) . "</p>\n</div>";
            }
            $items .= $item . '</article></li>';
        }
        return '<ol class="' . $classes . '">' . $items . '</ol>';
    }

    /** The first twenty words, with an ellipsis when there were more. */
    private static function excerpt(string $content): string
    {
        $words = preg_split('/\s+/', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= 20) {
            return implode(' ', $words);
        }
        return implode(' ', array_slice($words, 0, 20)) . '&hellip;';
    }
}
