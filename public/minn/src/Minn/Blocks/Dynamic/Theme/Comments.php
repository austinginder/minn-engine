<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Dynamic\Dates;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Content\Blocks;
use Minn\Content\Comments as CommentStore;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Support\Kses;
use Minn\Content\PasswordGate;

/** comments, comments-title, comment-template, the comment-* blocks, and the comment form. */
final readonly class Comments
{
    public function __construct(
        private Db $db,
        private CommentStore $comments,
        private Site $site,
        private Permalinks $permalinks,
    ) {
    }

    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/comments', $this->comments(...));
        $renderer->registerDynamic('core/comments-title', $this->title(...));
        $renderer->registerDynamic('core/comment-template', $this->template(...));
        $renderer->registerDynamic('core/avatar', $this->avatar(...));
        $renderer->registerDynamic('core/comment-date', $this->date(...));
        $renderer->registerDynamic('core/comment-author-name', $this->authorName(...));
        $renderer->registerDynamic('core/comment-content', $this->content(...));
        $renderer->registerDynamic('core/comment-edit-link', static fn () => '');
        $renderer->registerDynamic('core/comment-reply-link', $this->replyLink(...));
        $renderer->registerDynamic('core/comments-pagination', static fn () => '');
        $renderer->registerDynamic('core/post-comments-form', $this->form(...));
    }

    /** Nothing at all when the post has no comments and takes none. */
    private function comments(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null || PasswordGate::is($post) || ($post->commentStatus !== 'open' && $this->approved($post->id) === [])) {
            return '';
        }
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        return $out;
    }

    private function title(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null) {
            return '';
        }
        $count = count($this->approved($post->id));
        if ($count === 0) {
            return '';
        }
        $title = Texturize::text('"' . Html::esc($post->title) . '"');
        $text = $count === 1 ? 'One response to ' . $title : $count . ' responses to ' . $title;
        $level = (int) $block->attr('level', 2);
        $classes = implode(' ', ['wp-block-comments-title', ...Styles::classes($block->attrs)]);
        return '<h' . $level . ' id="comments" class="' . Html::attr($classes) . '">' . $text . '</h' . $level . '>';
    }

    private function template(Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $post = $context->post();
        if ($post === null) {
            return '';
        }
        $all = $this->approved($post->id);
        if ($all === []) {
            return '';
        }
        return $this->list($all, 0, 1, $block, $renderer);
    }

    /** @param list<array> $all */
    private function list(array $all, int $parent, int $depth, Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $items = '';
        $position = 0;
        foreach ($all as $comment) {
            if ((int) $comment['comment_parent'] !== $parent) {
                continue;
            }
            $position++;
            $context->withComment($comment);
            $inner = '';
            $index = 0;
            foreach ($block->innerContent as $chunk) {
                $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
            }
            $context->withComment(null);
            $parity = $position % 2 === 1 ? 'even' : 'odd';
            $classes = ['comment', $parity];
            if ($depth === 1) {
                $classes[] = 'thread-' . $parity;
            }
            $classes[] = 'depth-' . $depth;
            $children = $this->list($all, (int) $comment['comment_ID'], $depth + 1, $block, $renderer);
            $items .= '<li id="comment-' . (int) $comment['comment_ID'] . '" class="' . implode(' ', $classes) . '">' . $inner . $children . '</li>';
        }
        return $items === '' ? '' : '<ol class="wp-block-comment-template">' . $items . '</ol>';
    }

    private function avatar(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        $size = (int) $block->attr('size', 96);
        $hash = hash('sha256', strtolower(trim((string) $comment['comment_author_email'])));
        $author = Html::attr((string) $comment['comment_author']);
        return '<div class="wp-block-avatar"><img alt=\'' . $author . ' Avatar\' src=\'https://secure.gravatar.com/avatar/' . $hash . '?s=' . $size . '&#038;d=mm&#038;r=g\' srcset=\'https://secure.gravatar.com/avatar/' . $hash . '?s=' . ($size * 2) . '&#038;d=mm&#038;r=g 2x\' class=\'avatar avatar-' . $size . ' photo wp-block-avatar__image\' height=\'' . $size . '\' width=\'' . $size . '\' decoding=\'async\'/></div>';
    }

    private function date(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $post = $renderer->context()->post();
        if ($comment === null || $post === null) {
            return '';
        }
        $local = (string) $comment['comment_date'];
        $link = $this->permalinks->forPost($post) . '#comment-' . (int) $comment['comment_ID'];
        return '<div class="wp-block-comment-date"><time datetime="' . Dates::iso($this->site, $local) . '"><a href="' . Html::attr($link) . '">' . Dates::format($this->site, $local) . '</a></time></div>';
    }

    private function authorName(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        $name = Html::esc((string) $comment['comment_author']);
        $url = Kses::url((string) $comment['comment_author_url']);
        if ($url !== '') {
            $name = '<a rel="external nofollow ugc" href="' . Html::attr($url) . '" target="_self" >' . $name . '</a>';
        }
        return '<div class="wp-block-comment-author-name">' . $name . '</div>';
    }

    private function content(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        return '<div class="wp-block-comment-content">' . Blocks::paragraphs((string) $comment['comment_content']) . '</div>';
    }

    private function replyLink(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $post = $renderer->context()->post();
        if ($comment === null || $post === null || $post->commentStatus !== 'open') {
            return '';
        }
        $id = (int) $comment['comment_ID'];
        $author = Html::attr((string) $comment['comment_author']);
        $href = $this->permalinks->forPost($post) . '?replytocom=' . $id . '#respond';
        return '<div class="wp-block-comment-reply-link"><a rel="nofollow" class="comment-reply-link" href="' . Html::attr($href) . '" data-commentid="' . $id . '" data-postid="' . $post->id . '" data-belowelement="comment-' . $id . '" data-respondelement="respond" data-replyto="Reply to ' . $author . '" aria-label="Reply to ' . $author . '">Reply</a></div>';
    }

    private function form(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null || $post->commentStatus !== 'open' || PasswordGate::is($post)) {
            return '';
        }
        $path = (string) parse_url($this->permalinks->forPost($post), PHP_URL_PATH);
        $action = $this->permalinks->url('/wp-comments-post.php');
        return "\t" . '<div id="respond" class="comment-respond wp-block-post-comments-form">' . "\n\t\t"
            . '<h3 id="reply-title" class="comment-reply-title">Leave a Reply <small><a rel="nofollow" id="cancel-comment-reply-link" href="' . Html::attr($path . '#respond') . '" style="display:none;">Cancel reply</a></small></h3>'
            . '<form action="' . Html::attr($action) . '" method="post" id="commentform" class="comment-form">'
            . '<p class="comment-notes"><span id="email-notes">Your email address will not be published.</span> <span class="required-field-message">Required fields are marked <span class="required">*</span></span></p>'
            . '<p class="comment-form-comment"><label for="comment">Comment <span class="required">*</span></label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required></textarea></p>'
            . '<p class="comment-form-author"><label for="author">Name <span class="required">*</span></label> <input id="author" name="author" type="text" value="" size="30" maxlength="245" autocomplete="name" required /></p>' . "\n"
            . '<p class="comment-form-email"><label for="email">Email <span class="required">*</span></label> <input id="email" name="email" type="email" value="" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email" required /></p>' . "\n"
            . '<p class="comment-form-url"><label for="url">Website</label> <input id="url" name="url" type="url" value="" size="30" maxlength="200" autocomplete="url" /></p>' . "\n"
            . '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" /> <label for="wp-comment-cookies-consent">Save my name, email, and website in this browser for the next time I comment.</label></p>' . "\n"
            . '<p class="form-submit wp-block-button"><input name="submit" type="submit" id="submit" class="wp-block-button__link wp-element-button" value="Post Comment" /> <input type=\'hidden\' name=\'comment_post_ID\' value=\'' . $post->id . '\' id=\'comment_post_ID\' />' . "\n"
            . '<input type=\'hidden\' name=\'comment_parent\' id=\'comment_parent\' value=\'0\' />' . "\n"
            . '</p></form>' . "\t" . '</div><!-- #respond -->';
    }

    /** @return list<array> approved plain comments, oldest first */
    private function approved(int $postId): array
    {
        return $this->db->rows(
            "SELECT * FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_approved = '1' AND comment_type IN ('', 'comment')
             ORDER BY comment_date_gmt ASC, comment_ID ASC",
            [$postId],
        );
    }
}
