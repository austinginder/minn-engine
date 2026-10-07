<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Content\CommentRecord;
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
use Minn\Content\Reader;
use Minn\Auth\Salts;
use Minn\Runtime\Runtime;

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

    /** Registers this family's blocks with the renderer. */
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
        $all = $this->visible($post->id);
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
            if ($comment->parentId !== $parent) {
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
            // The reference counts from zero: the first is even, and an odd one is "alt" as well.
            $odd = $position % 2 === 0;
            $classes = ['comment', ...($odd ? ['odd', 'alt'] : ['even'])];
            if ($depth === 1) {
                array_push($classes, ...($odd ? ['thread-odd', 'thread-alt'] : ['thread-even']));
            }
            $classes[] = 'depth-' . $depth;
            $children = $this->list($all, $comment->id, $depth + 1, $block, $renderer);
            $items .= '<li id="comment-' . $comment->id . '" class="' . implode(' ', $classes) . '">' . $inner . $children . '</li>';
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
        $hash = hash('sha256', strtolower(trim($comment->authorEmail)));
        $author = Html::attr($comment->author);
        return '<div class="wp-block-avatar"><img alt=\'' . $author . ' Avatar\' src=\'https://secure.gravatar.com/avatar/' . $hash . '?s=' . $size . '&#038;d=mm&#038;r=g\' srcset=\'https://secure.gravatar.com/avatar/' . $hash . '?s=' . ($size * 2) . '&#038;d=mm&#038;r=g 2x\' class=\'avatar avatar-' . $size . ' photo wp-block-avatar__image\' height=\'' . $size . '\' width=\'' . $size . '\' decoding=\'async\'/></div>';
    }

    private function date(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $post = $renderer->context()->post();
        if ($comment === null || $post === null) {
            return '';
        }
        $local = $comment->date;
        $link = $this->permalinks->forPost($post) . '#comment-' . $comment->id;
        return '<div class="wp-block-comment-date"><time datetime="' . Dates::iso($this->site, $local) . '"><a href="' . Html::attr($link) . '">' . Dates::format($this->site, $local) . '</a></time></div>';
    }

    private function authorName(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        $name = Html::esc($comment->author);
        $url = Kses::url($comment->authorUrl);
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
        // A held comment, shown only to its author, says so first.
        $held = $comment->approved === '0' ? '<p><em class="comment-awaiting-moderation">Your comment is awaiting moderation.</em></p>' : '';
        return '<div class="wp-block-comment-content">' . $held . Blocks::paragraphs($comment->content) . '</div>';
    }

    private function replyLink(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $post = $renderer->context()->post();
        if ($comment === null || $post === null || $post->commentStatus !== 'open') {
            return '';
        }
        $id = $comment->id;
        $author = Html::attr($comment->author);
        $href = $this->permalinks->forPost($post) . '?replytocom=' . $id . '#respond';
        return '<div class="wp-block-comment-reply-link"><a rel="nofollow" class="comment-reply-link" href="' . Html::attr($href) . '" data-commentid="' . $id . '" data-postid="' . $post->id . '" data-belowelement="comment-' . $id . '" data-respondelement="respond" data-replyto="Reply to ' . $author . '" aria-label="Reply to ' . $author . '">Reply</a></div>';
    }

    private function form(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null || $post->commentStatus !== 'open' || PasswordGate::is($post)) {
            return '';
        }
        return $this->commentForm($post->id, $block);
    }

    /**
     * The form is comment_form()'s, as the reference's block renders it: the block theme's button in its defaults, the
     * block's classes on the respond wrapper, and every comment_form hook a
     * plugin uses to add its fields (a spam plugin's hidden check, a
     * consent box) fired on the way.
     */
    private function commentForm(int $postId, Block $block): string
    {
        $defaults = static function (array $fields): array {
            $fields['submit_button'] = '<input name="%1$s" type="submit" id="%2$s" class="wp-block-button__link wp-element-button" value="%4$s" />';
            $fields['submit_field'] = '<p class="form-submit wp-block-button">%1$s %2$s</p>';
            return $fields;
        };
        \add_filter('comment_form_defaults', $defaults);
        ob_start();
        \comment_form([], $postId);
        $form = (string) ob_get_clean();
        \remove_filter('comment_form_defaults', $defaults);
        $classes = implode(' ', ['comment-respond', 'wp-block-post-comments-form', ...Styles::classes($block->attrs)]);
        return str_replace('class="comment-respond"', 'class="' . Html::attr($classes) . '"', $form);
    }

    /**
     * The approved plain comments and the reader's own held ones, oldest
     * first: held comments by the signed-in account, by the email the
     * commenter cookie remembers, or by the email of the held comment a
     * link names with its moderation hash (wp_hash of its GMT date).
     *
     * @return list<CommentRecord>
     */
    private function visible(int $postId): array
    {
        $reader = Reader::current();
        $request = Runtime::current()->request;
        $emails = array_filter([$reader->userId > 0 ? '' : (string) ($request?->cookies['comment_author_email_' . md5((string) $this->site->option('siteurl'))] ?? ''), $this->linkedEmail($request?->query ?? [])]);
        $own = $reader->userId > 0 ? ['user_id = ?'] : [];
        foreach ($emails as $ignored) {
            $own[] = 'comment_author_email = ?';
        }
        $held = $own === [] ? '' : " OR (comment_approved = '0' AND (" . implode(' OR ', $own) . '))';
        return CommentRecord::fromRows($this->db->rows(
            "SELECT * FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_type IN ('', 'comment') AND (comment_approved = '1'{$held})
             ORDER BY comment_date_gmt ASC, comment_ID ASC",
            [$postId, ...($reader->userId > 0 ? [$reader->userId] : []), ...array_values($emails)],
        ));
    }

    /** The author email of the held comment ?unapproved= names, when ?moderation-hash= proves the link came from posting it in the last ten minutes. @param array<string, mixed> $query */
    private function linkedEmail(array $query): string
    {
        $id = (int) ($query['unapproved'] ?? 0);
        $hash = (string) ($query['moderation-hash'] ?? '');
        if ($id <= 0 || $hash === '') {
            return '';
        }
        $row = $this->db->row("SELECT * FROM {$this->db->table('comments')} WHERE comment_ID = ? AND comment_approved = '0'", [$id]);
        $held = $row === null ? null : CommentRecord::fromRow($row);
        $fresh = $held !== null && (int) strtotime($held->dateGmt . ' UTC') + 600 > time();
        return $fresh && hash_equals(Salts::hash($held->dateGmt, 'auth'), $hash) ? $held->authorEmail : '';
    }

    /** @return list<CommentRecord> approved plain comments, oldest first */
    private function approved(int $postId): array
    {
        return CommentRecord::fromRows($this->db->rows(
            "SELECT * FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_approved = '1' AND comment_type IN ('', 'comment')
             ORDER BY comment_date_gmt ASC, comment_ID ASC",
            [$postId],
        ));
    }
}
