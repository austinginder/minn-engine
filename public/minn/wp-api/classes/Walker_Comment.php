<?php
/**
 * The comment list's walker (probe comment-walker): wp_list_comments hands
 * it the comments, and themes subclass it (Twenty Nineteen and Twenty
 * Twenty do) to change one comment's markup. Levels open as an ol, a ul,
 * or nothing for div; a reply past the depth limit follows its parent on
 * the parent's level; each comment is the callback's, a short ping line,
 * or the html5 or xhtml markup the reference prints. A held comment shows
 * to anyone but its commenter as a preview: the author unlinked, the text
 * without markup, and (in html5) no reply link.
 */
class Walker_Comment extends Walker
{
    public $tree_type = 'comment';
    public $db_fields = ['parent' => 'comment_parent', 'id' => 'comment_ID'];

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= match ($args['style'] ?? 'ul') { 'div' => '', 'ol' => "<ol class=\"children\">\n", default => "<ul class=\"children\">\n" };
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= match ($args['style'] ?? 'ul') { 'div' => '', 'ol' => "</ol><!-- .children -->\n", default => "</ul><!-- .children -->\n" };
    }

    /** At the depth limit, replies follow their parent on its level instead of waiting at the end as orphans. */
    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output)
    {
        if (!$element) {
            return;
        }
        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
        $id = $element->{$this->db_fields['id']};
        if ($max_depth <= $depth + 1 && isset($children_elements[$id])) {
            foreach ($children_elements[$id] as $child) {
                $this->display_element($child, $children_elements, $max_depth, $depth, $args, $output);
            }
            unset($children_elements[$id]);
        }
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $comment = $data_object;
        ++$depth;
        $GLOBALS['comment_depth'] = $depth;
        $GLOBALS['comment'] = $comment;
        ob_start();
        if (!empty($args['callback'])) {
            call_user_func($args['callback'], $comment, $args, $depth);
            $output .= ob_get_clean();
            return;
        }
        $previews = $comment->comment_type === 'comment';
        if ($previews) {
            add_filter('comment_text', [$this, 'filter_comment_text'], 40, 2);
        }
        if (in_array($comment->comment_type, ['pingback', 'trackback'], true) && !empty($args['short_ping'])) {
            $this->ping($comment, $depth, $args);
        } elseif (($args['format'] ?? '') === 'html5') {
            $this->html5_comment($comment, $depth, $args);
        } else {
            $this->comment($comment, $depth, $args);
        }
        if ($previews) {
            remove_filter('comment_text', [$this, 'filter_comment_text'], 40);
        }
        $output .= ob_get_clean();
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
        if (!empty($args['end-callback'])) {
            ob_start();
            call_user_func($args['end-callback'], $data_object, $args, $depth);
            $output .= ob_get_clean();
            return;
        }
        $output .= ($args['style'] ?? '') === 'div' ? "</div><!-- #comment-## -->\n" : "</li><!-- #comment-## -->\n";
    }

    /** A held comment's text without its markup, unless the commenter is the one looking. */
    public function filter_comment_text($comment_text, $comment)
    {
        return $comment && $comment->comment_approved === '0' && !$this->previewed() ? wp_kses($comment_text, []) : $comment_text;
    }

    protected function ping($comment, $depth, $args)
    {
        $tag = ($args['style'] ?? '') === 'div' ? 'div' : 'li';
        echo "\t\t<{$tag} id=\"comment-", get_comment_ID(), '" ', comment_class('', $comment, null, false), ">\n\t\t\t<div class=\"comment-body\">\n\t\t\t\tPingback: ", get_comment_author_link($comment), ' ';
        edit_comment_link('Edit', '<span class="edit-link">', '</span>');
        echo "\t\t\t</div>\n\t\t";
    }

    protected function comment($comment, $depth, $args)
    {
        $div = ($args['style'] ?? '') === 'div';
        $id = get_comment_ID();
        $held = $comment->comment_approved === '0';
        $avatar = (int) $args['avatar_size'] !== 0 ? (string) get_avatar($comment, $args['avatar_size']) : '';
        $author = $held && !$this->previewed() ? get_comment_author($comment) : get_comment_author_link($comment);
        echo "\t\t<", $div ? 'div' : 'li', ' ', comment_class($this->has_children ? 'parent' : '', $comment, null, false), " id=\"comment-{$id}\">\n";
        echo $div ? '' : "\t\t\t\t<div id=\"div-comment-{$id}\" class=\"comment-body\">\n";
        echo "\t\t\t\t<div class=\"comment-author vcard\">\n\t\t\t", $avatar, "\t\t\t", sprintf('%s <span class="says">says:</span>', '<cite class="fn">' . $author . '</cite>'), "\t\t</div>\n";
        echo $held ? "\t\t\t\t<em class=\"comment-awaiting-moderation\">" . $this->heldNote() . "</em>\n\t\t<br />\n" : '';
        echo "\t\t\n\t\t<div class=\"comment-meta commentmetadata\">\n\t\t\t", '<a href="', esc_url(get_comment_link($comment, $args)), '">', sprintf('%1$s at %2$s', get_comment_date('', $comment), get_comment_time()), '</a>';
        edit_comment_link('(Edit)', ' &nbsp;&nbsp;', '');
        echo "\t\t</div>\n\n\t\t";
        comment_text($comment, array_merge($args, ['add_below' => $div ? 'comment' : 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth']]));
        echo "\n\t\t";
        comment_reply_link(array_merge($args, ['add_below' => $div ? 'comment' : 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth'], 'before' => '<div class="reply">', 'after' => '</div>']));
        echo "\n", $div ? '' : "\t\t\t\t</div>\n", "\t\t\t\t";
    }

    protected function html5_comment($comment, $depth, $args)
    {
        $id = get_comment_ID();
        $held = $comment->comment_approved === '0';
        $previewed = $this->previewed();
        $avatar = (int) $args['avatar_size'] !== 0 ? (string) get_avatar($comment, $args['avatar_size']) : '';
        $author = $held && !$previewed ? get_comment_author($comment) : get_comment_author_link($comment);
        echo "\t\t<", ($args['style'] ?? '') === 'div' ? 'div' : 'li', " id=\"comment-{$id}\" ", comment_class($this->has_children ? 'parent' : '', $comment, null, false), ">\n";
        echo "\t\t\t<article id=\"div-comment-{$id}\" class=\"comment-body\">\n\t\t\t\t<footer class=\"comment-meta\">\n\t\t\t\t\t<div class=\"comment-author vcard\">\n";
        echo "\t\t\t\t\t\t", $avatar, "\t\t\t\t\t\t", sprintf('%s <span class="says">says:</span>', '<b class="fn">' . $author . '</b>'), "\t\t\t\t\t</div><!-- .comment-author -->\n\n";
        echo "\t\t\t\t\t<div class=\"comment-metadata\">\n\t\t\t\t\t\t", sprintf('<a href="%s"><time datetime="%s">%s</time></a>', esc_url(get_comment_link($comment, $args)), get_comment_time('c'), sprintf('%1$s at %2$s', get_comment_date('', $comment), get_comment_time()));
        edit_comment_link('Edit', ' <span class="edit-link">', '</span>');
        echo "\t\t\t\t\t</div><!-- .comment-metadata -->\n\n\t\t\t\t\t";
        echo $held ? "\t\t\t\t\t<em class=\"comment-awaiting-moderation\">" . $this->heldNote() . "</em>\n\t\t\t\t\t" : '';
        echo "\t\t\t\t</footer><!-- .comment-meta -->\n\n\t\t\t\t<div class=\"comment-content\">\n\t\t\t\t\t";
        comment_text();
        echo "\t\t\t\t</div><!-- .comment-content -->\n\n\t\t\t\t";
        if ($comment->comment_approved === '1' || $previewed) {
            comment_reply_link(array_merge($args, ['add_below' => 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth'], 'before' => '<div class="reply">', 'after' => '</div>']));
        }
        echo "\t\t\t</article><!-- .comment-body -->\n\t\t";
    }

    /** Whoever is looking left a comment here before (the commenter cookie): their held comment shows in full. */
    private function previewed(): bool
    {
        return !empty(wp_get_current_commenter()['comment_author']);
    }

    private function heldNote(): string
    {
        return __('Your comment is awaiting moderation. This is a preview; your comment will be visible after it has been approved.');
    }
}
