<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Runtime\BlockFilters;
use Minn\Admin\Format;
use Minn\Content\CommentRecord;
use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\Posts;
use Minn\Content\RowCache;
use Minn\I18n\Gettext;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Content\Texturize;
use Minn\Front\Permalinks;
use Minn\Html;
use Minn\Objects\CommentsQuery;
use Minn\Runtime\BlockQueries;
use Minn\Runtime\Handoff;
use Minn\Support\Escape;
use Minn\Content\PasswordGate;
use Minn\Runtime\Avatar;
use Minn\Runtime\Runtime;

/** comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments. */
final readonly class Comments
{
    /** The global the comment loop's depth is kept in, as get_comment_class and the reply link read it. */
    private const DEPTH = 'comment_depth';

    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Posts $posts,
        private Users $users,
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
        $renderer->registerDynamic('core/comment-reply-link', $this->replyLink(...));
        $renderer->registerDynamic('core/post-comments-form', $this->form(...));
        $renderer->registerDynamic('core/post-comments', $this->legacy(...));
    }

    /** Nothing at all when the post has no comments and takes none. */
    private function comments(Block $block, Renderer $renderer): string
    {
        if (!empty($block->attrs['legacy'])) {
            return $this->legacy($block, $renderer);
        }
        $post = $renderer->context()->post();
        if ($post === null || PasswordGate::is($post) || ($post->commentStatus !== 'open' && self::count($post) === 0)) {
            return '';
        }
        $instance = $renderer->ownInstance();
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        // It goes through render_block once as it renders, and again as its render ends (probe block-instances).
        return $instance === null ? $out : BlockFilters::rendered($out, (array) $instance->parsed_block, $instance);
    }

    /**
     * The comments as a classic theme prints them (comments_template(), the
     * theme's comments.php or core's own), in the block's wrapper: the legacy
     * post-comments block (Twenty Twenty-Two's single template), and a
     * comments block saved before it held inner blocks (legacy). A legacy
     * comments block keeps post-comments' class ahead of its own. Nothing
     * when the post takes no comments and has none; a protected post gets
     * the template's own notice.
     */
    private function legacy(Block $block, Renderer $renderer): string
    {
        $post = $renderer->context()->post();
        if ($post === null || ($post->commentStatus !== 'open' && self::count($post) === 0)) {
            return '';
        }
        $legacy = $block->name === 'core/comments';
        $align = Styles::align($block->attrs);
        $extra = [...($legacy ? ['wp-block-post-comments'] : []), ...($align === null ? [] : [$align])];
        return Wrapper::open('div', $legacy ? 'wp-block-comments' : 'wp-block-post-comments', $block, extraClasses: $extra) . \_minn_legacy_comments() . '</div>';
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
        // "One response to “Title”", or less of it as showCommentsCount and showPostTitle ask: each its own message.
        $title = Texturize::text(Html::esc($post->title));
        $text = match ([(bool) $block->attr('showCommentsCount', true), (bool) $block->attr('showPostTitle', true), $count === 1]) {
            [true, true, true] => sprintf(Gettext::text('One response to &#8220;%s&#8221;'), $title),
            [true, true, false] => sprintf(Gettext::plural('%1$s response to &#8220;%2$s&#8221;', '%1$s responses to &#8220;%2$s&#8221;', $count), Format::number($count), $title),
            [true, false, true] => Gettext::text('One response'),
            [true, false, false] => sprintf(Gettext::plural('%s response', '%s responses', $count), Format::number($count)),
            [false, true, true] => sprintf(Gettext::text('Response to &#8220;%s&#8221;'), $title),
            [false, true, false] => sprintf(Gettext::text('Responses to &#8220;%s&#8221;'), $title),
            [false, false, true] => Gettext::text('Response'),
            default => Gettext::text('Responses'),
        };
        $level = (int) $block->attr('level', 2);
        $classes = implode(' ', ['wp-block-comments-title', ...Styles::classes($block->attrs)]);
        return '<h' . $level . ' id="comments" class="' . Html::attr($classes) . '">' . $text . '</h' . $level . '>';
    }

    /**
     * The comment query a comment template's vars ask for, run once a
     * request: the template and its pagination links ask for the same one,
     * and the reference answers the repeat from its cache. A comment write
     * drops it (RowCache).
     *
     * @param array<string, mixed> $vars
     */
    public static function query(array $vars): CommentsQuery
    {
        return RowCache::remember('comment_queries', md5(serialize($vars)), static fn (): CommentsQuery => Handoff::newCommentQuery($vars));
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
        // Whether the post takes comments is asked as the template renders.
        Handoff::commentTag('open', $post->id);
        $vars = BlockQueries::comments((object) ['context' => ['postId' => $post->id]]);
        $comments = (array) self::query($vars)->comments;
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
        return (int) Runtime::hooks()->filter('get_comments_number', [$post->commentCount, $post->id]);
    }

    /** @param list<array> $all */
    private function list(array $all, int $parent, int $depth, Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $items = '';
        foreach ($all as $comment) {
            if ($comment->parentId !== $parent) {
                continue;
            }
            $context->withComment($comment);
            $GLOBALS[self::DEPTH] = $depth;
            // Inside a comment template of the comment's own, its id all the context it has (probe block-instances).
            $inner = $renderer->renderItem($block->toArray(), ['commentId' => $comment->id], static function () use ($block, $renderer): string {
                $inner = '';
                $index = 0;
                foreach ($block->innerContent as $chunk) {
                    $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
                }
                return $inner;
            });
            // Its classes through get_comment_class, whose odd and even run on across the request.
            $classes = (array) Handoff::commentTag('class', '', $comment->id);
            $context->withComment(null);
            $children = $this->list($all, $comment->id, $depth + 1, $block, $renderer);
            $items .= '<li id="comment-' . $comment->id . '" class="' . implode(' ', $classes) . '">' . $inner . $children . '</li>';
        }
        // Replies sit in a plain list inside their parent's item (probe comment-blocks).
        return $items === '' ? '' : ($depth === 1 ? '<ol class="wp-block-comment-template">' : '<ol>') . $items . '</ol>';
    }

    /** The comment author's avatar in a comment loop; elsewhere the post author's, linked to their posts when asked. */
    private function avatar(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $size = (int) $block->attr('size', 96);
        if ($comment === null) {
            return $this->authorAvatar($block, $renderer, $size);
        }
        // Through get_avatar for the comment, as the reference's render callback asks it (probe comment-blocks).
        $img = Handoff::avatar(Handoff::commentInstance($comment->id), $size, '', sprintf(Gettext::text('%s Avatar'), $comment->author), ['class' => 'wp-block-avatar__image']);
        return '<div class="wp-block-avatar">' . (is_string($img) ? $img : '') . '</div>';
    }

    private function authorAvatar(Block $block, Renderer $renderer, int $size): string
    {
        $post = $renderer->context()->post();
        $user = $post === null ? null : $this->users->find($post->authorId);
        if ($user === null) {
            return '';
        }
        $url = 'https://secure.gravatar.com/avatar/' . Avatar::hash($user->email);
        $img = "<img alt='" . Html::attr(sprintf(Gettext::text('%s Avatar'), $user->displayName)) . "' src='{$url}?s={$size}&#038;d=mm&#038;r=g' srcset='{$url}?s=" . ($size * 2) . "&#038;d=mm&#038;r=g 2x' class='avatar avatar-{$size} photo wp-block-avatar__image' height='{$size}' width='{$size}' loading='lazy' decoding='async'/>";
        if ((bool) $block->attr('isLink', false)) {
            $img = '<a href="' . Html::attr($this->permalinks->forAuthor($user)) . '" target="' . Html::attr((string) $block->attr('linkTarget', '_self')) . '"  class="wp-block-avatar__link">' . $img . '</a>';
        }
        return Wrapper::open('div', 'wp-block-avatar', $block) . $img . '</div>';
    }

    /** When the comment was written, in the format asked for (else the site's), linked to the comment unless isLink is off. */
    private function date(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        $post = $comment === null ? null : $this->commentPost($comment, $renderer);
        if ($comment === null || $post === null) {
            return '';
        }
        // Through get_comment_date and get_comment_link (probe comment-blocks).
        $text = (string) Handoff::commentTag('date', (string) $block->attr('format', ''), $comment->id);
        if ((bool) $block->attr('isLink', true)) {
            $text = '<a href="' . Html::attr(Escape::url((string) Handoff::commentTag('link', $comment->id))) . '">' . $text . '</a>';
        }
        return '<div class="wp-block-comment-date"><time datetime="' . Html::attr((string) Handoff::commentTag('date', 'c', $comment->id)) . '">' . $text . '</time></div>';
    }

    /** The post a comment belongs to: the loop's, or looked up when the comment came by itself (a comment block's render callback). */
    private function commentPost(CommentRecord $comment, Renderer $renderer): ?PostRecord
    {
        $post = $renderer->context()->post();
        return $post !== null && $post->id === $comment->postId ? $post : $this->posts->find($comment->postId);
    }

    private function authorName(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        // Read through get_comment_author and get_comment_author_url, linked unless isLink is off (probe comment-blocks).
        $name = (string) Handoff::commentTag('author', $comment->id);
        $url = (string) Handoff::commentTag('authorUrl', $comment->id);
        if ($url !== '' && (bool) $block->attr('isLink', true)) {
            $name = '<a rel="external nofollow ugc" href="' . Html::attr(Escape::url($url)) . '" target="' . Html::attr((string) $block->attr('linkTarget', '_self')) . '" >' . $name . '</a>';
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
        // The text through get_comment_text and comment_text, its defaults with it (links made, paragraphs).
        return '<div class="wp-block-comment-content">' . $held . (string) Handoff::commentTag('text', $comment->id) . '</div>';
    }

    private function replyLink(Block $block, Renderer $renderer): string
    {
        $comment = $renderer->context()->comment();
        if ($comment === null) {
            return '';
        }
        // Through get_comment_reply_link, at the comment's depth, as deep as the site threads, and not at all when it
        // does not thread (probe comment-blocks).
        $options = Runtime::options();
        $deepest = $options->filtered('thread_comments') ? (int) $options->filtered('thread_comments_depth') : -1;
        $link = (string) Handoff::commentTag('replyLink', ['depth' => max(1, (int) ($GLOBALS[self::DEPTH] ?? 1)), 'max_depth' => $deepest], $comment->id);
        return $link === '' ? '' : '<div class="wp-block-comment-reply-link">' . $link . '</div>';
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
        $form = \_minn_post_comments_form($postId);
        Runtime::hooks()->remove('comment_form_defaults', $defaults);
        $classes = implode(' ', ['comment-respond', 'wp-block-post-comments-form', ...Styles::classes($block->attrs)]);
        return str_replace('class="comment-respond"', 'class="' . Html::attr($classes) . '"', $form);
    }

}
