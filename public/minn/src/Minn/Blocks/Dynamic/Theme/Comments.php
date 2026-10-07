<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Content\CommentRecord;
use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Blocks\Dynamic\Dates;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Content\Blocks;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Support\Kses;
use Minn\Content\PasswordGate;
use Minn\Runtime\Runtime;

/** comments, comments-title, comment-template, the comment-* blocks, and the comment form. */
final readonly class Comments
{
    public function __construct(
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
        if ($post === null || PasswordGate::is($post) || ($post->commentStatus !== 'open' && self::count($post) === 0)) {
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
        $count = self::count($post);
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
        // The reference's comment query for a template (build_comment_query_vars_from_block): approved ones and
        // the reader's own held ones, oldest first, threaded and paged as the site says.
        $vars = \build_comment_query_vars_from_block((object) ['context' => ['postId' => $post->id]]);
        $comments = (array) (new \WP_Comment_Query())->query($vars);
        // Newest first, when the site lists them so, is the top level reversed; replies keep their order.
        $comments = $this->site->option('comment_order') === 'desc' ? array_reverse($comments) : $comments;
        $all = self::records($comments, ($vars['hierarchical'] ?? false) === 'threaded');
        return $all === [] ? '' : $this->list($all, 0, 1, $block, $renderer);
    }

    /**
     * The query's comments as records, each followed by its replies when
     * they are threaded; unthreaded, every one stands at the top level.
     *
     * @param list<\WP_Comment> $comments
     * @return list<CommentRecord>
     */
    private static function records(array $comments, bool $threaded): array
    {
        $out = [];
        foreach ($comments as $comment) {
            $out[] = CommentRecord::fromRow(($threaded ? [] : ['comment_parent' => 0]) + get_object_vars($comment));
            if ($threaded) {
                array_push($out, ...self::records(array_values($comment->get_children()), true));
            }
        }
        return $out;
    }

    /** How many comments a post shows: its count, as get_comments_number filters it. */
    private static function count(PostRecord $post): int
    {
        return (int) \apply_filters('get_comments_number', $post->commentCount, $post->id);
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
        Runtime::hooks()->add('comment_form_defaults', $defaults);
        ob_start();
        \comment_form([], $postId);
        $form = (string) ob_get_clean();
        Runtime::hooks()->remove('comment_form_defaults', $defaults);
        $classes = implode(' ', ['comment-respond', 'wp-block-post-comments-form', ...Styles::classes($block->attrs)]);
        return str_replace('class="comment-respond"', 'class="' . Html::attr($classes) . '"', $form);
    }

}
