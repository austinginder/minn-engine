<?php
/** Comments: reads, counts, the writers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Content\CommentClasses;
use Minn\Content\CommentModeration;
use Minn\Content\Comments;
use Minn\Front\CommentList;
use Minn\Runtime\CommentQuery;
use Minn\Runtime\Runtime;

/** @internal */
function _minn_comments(): Comments
{
    return new Comments(Runtime::current()->db);
}

function get_comment($comment = null, $output = OBJECT)
{
    // Any empty id means the comment being read, not "no comment".
    if (empty($comment)) {
        $comment = $GLOBALS['comment'] ?? null;
    }
    if ($comment instanceof WP_Comment) {
        $object = $comment;
    } elseif (is_object($comment) && isset($comment->comment_ID)) {
        $object = new WP_Comment($comment);
    } else {
        $row = (int) $comment > 0 ? _minn_comments()->find((int) $comment) : null;
        if ($row === null) {
            return null;
        }
        $object = new WP_Comment((object) $row->row());
    }
    $object = apply_filters('get_comment', $object);
    if ($output === ARRAY_A) {
        return $object->to_array();
    }
    if ($output === ARRAY_N) {
        return array_values($object->to_array());
    }
    return $object;
}

function get_comments($args = '')
{
    $args = wp_parse_args($args, CommentQuery::DEFAULTS);
    $query = new CommentQuery(Runtime::current()->db);
    if ($args['count']) {
        return $query->count($args);
    }
    $rows = $query->rows($args);
    if ($args['fields'] === 'ids') {
        return array_map(static fn (array $r) => (int) $r['comment_ID'], $rows);
    }
    return apply_filters('the_comments', array_map(static fn (array $r) => new WP_Comment((object) $r), $rows), null);
}

function get_comments_number($post = 0)
{
    $post = get_post($post);
    if ($post === null) {
        $count = 0;
        $post_id = 0;
    } else {
        $count = $post->comment_count;
        $post_id = $post->ID;
    }
    return apply_filters('get_comments_number', $count, $post_id);
}

function comments_number($zero = false, $one = false, $more = false, $post = 0)
{
    echo get_comments_number_text($zero, $one, $more, $post);
}

function get_comments_number_text($zero = false, $one = false, $more = false, $post = 0)
{
    $number = (int) get_comments_number($post);
    if ($number > 1) {
        $output = $more === false ? sprintf('%s Comments', number_format_i18n($number)) : str_replace('%', number_format_i18n($number), (string) $more);
    } elseif ($number === 0) {
        $output = $zero === false ? 'No Comments' : $zero;
    } else {
        $output = $one === false ? '1 Comment' : $one;
    }
    return apply_filters('comments_number', $output, $number);
}

function wp_count_comments($post_id = 0)
{
    $post_id = (int) $post_id;
    $filtered = apply_filters('wp_count_comments', [], $post_id);
    if (!empty($filtered)) {
        return (object) $filtered;
    }
    return (object) (new CommentQuery(Runtime::current()->db))->breakdown($post_id);
}

function wp_insert_comment($commentdata)
{
    $data = wp_unslash((array) $commentdata);
    $now = current_time('mysql');
    $columns = [
        'comment_post_ID' => (int) ($data['comment_post_ID'] ?? 0),
        'comment_author' => (string) ($data['comment_author'] ?? ''),
        'comment_author_email' => (string) ($data['comment_author_email'] ?? ''),
        'comment_author_url' => (string) ($data['comment_author_url'] ?? ''),
        'comment_author_IP' => (string) ($data['comment_author_IP'] ?? ''),
        'comment_date' => (string) ($data['comment_date'] ?? $now),
        'comment_date_gmt' => (string) ($data['comment_date_gmt'] ?? get_gmt_from_date((string) ($data['comment_date'] ?? $now))),
        'comment_content' => (string) ($data['comment_content'] ?? ''),
        'comment_karma' => (int) ($data['comment_karma'] ?? 0),
        'comment_approved' => (string) ($data['comment_approved'] ?? '1'),
        'comment_agent' => (string) ($data['comment_agent'] ?? ''),
        'comment_type' => (string) (($data['comment_type'] ?? '') === '' ? 'comment' : $data['comment_type']),
        'comment_parent' => (int) ($data['comment_parent'] ?? 0),
        'user_id' => (int) ($data['user_id'] ?? 0),
    ];
    $id = _minn_comments()->insert($columns);
    if ($columns['comment_approved'] === '1') {
        wp_update_comment_count($columns['comment_post_ID']);
    }
    wp_cache_delete($columns['comment_post_ID'], 'posts');
    $comment = get_comment($id);
    do_action('wp_insert_comment', $id, $comment);
    return $id;
}

function wp_update_comment_count($post_id, $do_deferred = false)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }
    $post = get_post($post_id);
    if ($post === null) {
        return false;
    }
    $old = (int) $post->comment_count;
    _minn_comments()->recount($post_id);
    wp_cache_delete($post_id, 'posts');
    $new = (int) get_post($post_id)->comment_count;
    do_action('wp_update_comment_count', $post_id, $new, $old);
    do_action("edit_post_{$post->post_type}", $post_id, get_post($post_id));
    do_action('edit_post', $post_id, get_post($post_id));
    return true;
}

function wp_update_comment($commentarr, $wp_error = false)
{
    $data = wp_unslash((array) $commentarr);
    $comment = get_comment((int) ($data['comment_ID'] ?? 0));
    if ($comment === null) {
        return $wp_error ? new WP_Error('invalid_comment_id', 'Invalid comment ID.') : false;
    }
    $columns = apply_filters('wp_update_comment_data', Comments::changedColumns($data, $comment->to_array()), $comment->to_array(), $data);
    if (is_wp_error($columns)) {
        return $wp_error ? $columns : 0;
    }
    if ($columns === []) {
        return 0;
    }
    _minn_comments()->update($comment->comment_ID, $columns);
    wp_update_comment_count((int) $comment->comment_post_ID);
    do_action('edit_comment', $comment->comment_ID, get_comment($comment->comment_ID)->to_array());
    return 1;
}

function wp_set_comment_status($comment_id, $comment_status, $wp_error = false)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    $status = match ((string) $comment_status) {
        'hold', '0' => '0',
        'approve', '1' => '1',
        'spam' => 'spam',
        'trash' => 'trash',
        default => null,
    };
    if ($status === null) {
        return false;
    }
    _minn_comments()->update($comment->comment_ID, ['comment_approved' => $status]);
    wp_update_comment_count((int) $comment->comment_post_ID);
    do_action('wp_set_comment_status', $comment->comment_ID, $comment_status);
    return true;
}

function wp_delete_comment($comment_id, $force_delete = false)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    if (!$force_delete && EMPTY_TRASH_DAYS && !in_array($comment->comment_approved, ['trash', 'spam'], true)) {
        return wp_trash_comment($comment->comment_ID);
    }
    do_action('delete_comment', $comment->comment_ID, $comment);
    _minn_comments()->orphanReplies((int) $comment->comment_ID, (int) $comment->comment_parent);
    _minn_comments()->delete((int) $comment->comment_ID);
    do_action('deleted_comment', $comment->comment_ID, $comment);
    wp_update_comment_count((int) $comment->comment_post_ID);
    return true;
}

function wp_trash_comment($comment_id)
{
    if (!EMPTY_TRASH_DAYS) {
        return wp_delete_comment($comment_id, true);
    }
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    do_action('trash_comment', $comment->comment_ID, $comment);
    if (wp_set_comment_status($comment->comment_ID, 'trash')) {
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
        add_comment_meta($comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved);
        add_comment_meta($comment->comment_ID, '_wp_trash_meta_time', time());
        do_action('trashed_comment', $comment->comment_ID, $comment);
        return true;
    }
    return false;
}

function wp_untrash_comment($comment_id)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    $status = (string) (get_comment_meta($comment->comment_ID, '_wp_trash_meta_status', true) ?: '0');
    if (wp_set_comment_status($comment->comment_ID, $status)) {
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
        return true;
    }
    return false;
}

function wp_spam_comment($comment_id)
{
    return wp_set_comment_status($comment_id, 'spam');
}

function wp_count_terms($args = [], $deprecated = '')
{
    if (!is_array($args) || (is_array($args) && !isset($args['taxonomy']) && !empty($deprecated))) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    }
    $args = wp_parse_args($args, ['hide_empty' => false]);
    $args['fields'] = 'count';
    return get_terms($args);
}

function get_comment_author($comment_id = 0)
{
    $comment = get_comment($comment_id);
    $author = $comment === null || $comment->comment_author === '' ? 'Anonymous' : $comment->comment_author;
    return apply_filters('get_comment_author', $author, $comment?->comment_ID, $comment);
}

function get_comment_text($comment_id = 0, $args = [])
{
    $comment = get_comment($comment_id);
    return apply_filters('get_comment_text', $comment === null ? '' : $comment->comment_content, $comment, $args);
}

function get_comment_date($format = '', $comment_id = 0)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return '';
    }
    return apply_filters('get_comment_date', mysql2date($format === '' ? get_option('date_format') : $format, $comment->comment_date), $format, $comment);
}

function get_comment_link($comment = null, $args = [])
{
    $comment = get_comment($comment);
    if ($comment === null) {
        return '';
    }
    return apply_filters('get_comment_link', get_permalink((int) $comment->comment_post_ID) . '#comment-' . $comment->comment_ID, $comment, $args, get_post((int) $comment->comment_post_ID));
}

function comments_open($post = null)
{
    $post = get_post($post);
    return (bool) apply_filters('comments_open', $post !== null && $post->comment_status === 'open', $post?->ID);
}

function pings_open($post = null)
{
    $post = get_post($post);
    return (bool) apply_filters('pings_open', $post !== null && $post->ping_status === 'open', $post?->ID);
}

function wp_comment_reply($position = 1, $checkbox = false, $mode = 'single', $table_row = true)
{
    return '';
}

function wp_defer_comment_counting($defer = null)
{
    return false;
}

function get_comment_count($post_id = 0)
{
    $counts = wp_count_comments($post_id);
    return ['approved' => $counts->approved, 'awaiting_moderation' => $counts->moderated, 'spam' => $counts->spam, 'trash' => $counts->trash, 'post-trashed' => $counts->{'post-trashed'}, 'total_comments' => $counts->total_comments, 'all' => $counts->all];
}

function get_comment_author_IP($comment_id = 0)
{
    $comment = get_comment($comment_id);
    return apply_filters('get_comment_author_IP', (string) ($comment->comment_author_IP ?? ''), (string) ($comment->comment_ID ?? ''), $comment);
}

function comment_author_IP($comment_id = 0)
{
    echo esc_html(get_comment_author_IP($comment_id));
}

function get_comment_author_url($comment_id = 0)
{
    $comment = get_comment($comment_id);
    $url = (string) ($comment->comment_author_url ?? '');
    $url = $url === 'http://' ? '' : $url;
    return apply_filters('get_comment_author_url', $url === '' ? '' : esc_url($url), (string) ($comment->comment_ID ?? ''), $comment);
}

function comment_ID()
{
    echo (string) (get_comment()->comment_ID ?? '');
}

function comment_author($comment_id = 0)
{
    $comment = get_comment($comment_id);
    echo apply_filters('comment_author', get_comment_author($comment), (string) ($comment->comment_ID ?? ''));
}

/** Held comments for a post, or for each of a list of posts. */
function get_pending_comments_num($post_id)
{
    $counts = _minn_comments()->pendingCounts((array) $post_id);
    return is_array($post_id) ? $counts : ($counts[(int) $post_id] ?? 0);
}

function get_approved_comments($post_id, $args = [])
{
    if ((int) $post_id <= 0) {
        return [];
    }
    return get_comments(wp_parse_args($args, ['status' => 1, 'post_id' => (int) $post_id, 'order' => 'ASC']));
}

/** The comment's class list, alternating odd/even and the thread parity at depth one across calls. */
function get_comment_class($css_class = '', $comment_id = null, $post = null)
{
    global $comment_alt, $comment_depth, $comment_thread_alt;
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return [];
    }
    $user = (int) $comment->user_id > 0 ? get_userdata((int) $comment->user_id) : null;
    $post = $user ? get_post($post ?? $comment->comment_post_ID) : null;
    $comment_alt = (int) ($comment_alt ?? 0);
    $comment_depth = (int) ($comment_depth ?? 0) ?: 1;
    $comment_thread_alt = (int) ($comment_thread_alt ?? 0);
    $extra = is_array($css_class) ? $css_class : preg_split('/\s+/', trim((string) $css_class), -1, PREG_SPLIT_NO_EMPTY);
    $classes = CommentClasses::build((string) $comment->comment_type, $user ? sanitize_html_class($user->user_nicename, (string) $comment->user_id) : null, $post && (int) $comment->user_id === (int) $post->post_author, $comment_alt, $comment_depth, $comment_thread_alt, $extra);
    $comment_alt++;
    if ($comment_depth === 1) {
        $comment_thread_alt++;
    }
    return apply_filters('comment_class', array_map('esc_attr', $classes), $css_class, $comment->comment_ID, $comment, $post);
}

function comment_class($css_class = '', $comment = null, $post = null, $display = true)
{
    $attribute = 'class="' . implode(' ', get_comment_class($css_class, $comment, $post)) . '"';
    if ($display) {
        echo $attribute;
        return null;
    }
    return $attribute;
}

/** The column limits a comment must fit: author 245, email 100, url 200, content 65525. */
function wp_check_comment_data_max_lengths($comment_data)
{
    $limits = ['comment_author' => [245, 'comment_author_column_length', 'Your name is too long.'], 'comment_author_email' => [100, 'comment_author_email_column_length', 'Your email address is too long.'], 'comment_author_url' => [200, 'comment_author_url_column_length', 'Your URL is too long.'], 'comment_content' => [65525, 'comment_content_column_length', 'Your comment is too long.']];
    foreach ($limits as $field => [$max, $code, $message]) {
        if (isset($comment_data[$field]) && mb_strlen((string) $comment_data[$field], '8bit') > $max) {
            return new WP_Error($code, '<strong>Error:</strong> ' . $message, 200);
        }
    }
    return true;
}

/** Each field through its pre_comment_* filter, marked filtered. */
function wp_filter_comment($commentdata)
{
    $map = ['comment_author' => 'pre_comment_author_name', 'comment_author_email' => 'pre_comment_author_email', 'comment_author_url' => 'pre_comment_author_url', 'comment_content' => 'pre_comment_content', 'comment_author_IP' => 'pre_comment_user_ip', 'comment_agent' => 'pre_comment_user_agent'];
    if (isset($commentdata['user_ID'])) {
        $commentdata['user_id'] = apply_filters('pre_user_id', $commentdata['user_ID']);
    } elseif (isset($commentdata['user_id'])) {
        $commentdata['user_id'] = apply_filters('pre_user_id', $commentdata['user_id']);
    }
    foreach ($map as $field => $filter) {
        if (isset($commentdata[$field])) {
            $commentdata[$field] = apply_filters($filter, $commentdata[$field]);
        }
    }
    $commentdata['filtered'] = true;
    return $commentdata;
}

/** 1, 0, "spam" or "trash" for a comment that may be stored; a duplicate or a flood refuses (WP_Error or wp_die). */
function wp_allow_comment($commentdata, $wp_error = false)
{
    $userId = (int) ($commentdata['user_id'] ?? 0);
    $moderator = $userId > 0 && user_can($userId, 'moderate_comments');
    $moderation = new CommentModeration(_minn_comments());
    $refusal = $moderation->refusal($commentdata, $moderator);
    if ($refusal === 'comment_duplicate') {
        do_action('comment_duplicate_trigger', $commentdata);
        $message = apply_filters('comment_duplicate_message', 'Duplicate comment detected; it looks as though you&#8217;ve already said that!');
        return $wp_error ? new WP_Error('comment_duplicate', $message, 409) : wp_die($message, 409);
    }
    if ($refusal === 'comment_flood') {
        do_action('comment_flood_trigger', time() - CommentModeration::FLOOD_SECONDS, time());
        $message = apply_filters('comment_flood_message', 'You are posting comments too quickly. Slow down.');
        return $wp_error ? new WP_Error('comment_flood', $message, 429) : wp_die($message, 429);
    }
    $approved = (int) $moderation->approval($moderator, (string) ($commentdata['comment_author'] ?? ''), (string) ($commentdata['comment_author_email'] ?? ''), static fn (string $n) => Runtime::current()->site->option($n));
    if (wp_check_comment_disallowed_list((string) ($commentdata['comment_author'] ?? ''), (string) ($commentdata['comment_author_email'] ?? ''), (string) ($commentdata['comment_author_url'] ?? ''), (string) ($commentdata['comment_content'] ?? ''), (string) ($commentdata['comment_author_IP'] ?? ''), (string) ($commentdata['comment_agent'] ?? ''))) {
        $approved = 'trash';
    }
    return apply_filters('pre_comment_approved', $approved, $commentdata);
}

/** approved, unapproved, spam, trash, or false for an unknown comment. */
function wp_get_comment_status($comment_id)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    return match ((string) $comment->comment_approved) { '1' => 'approved', '0' => 'unapproved', 'spam' => 'spam', 'trash' => 'trash', default => false };
}

/** The remembered commenter from the comment_author_* cookies, or the signed-in user. */
function wp_get_current_commenter()
{
    $hash = md5((string) get_option('siteurl'));
    $commenter = ['comment_author' => (string) ($_COOKIE['comment_author_' . $hash] ?? ''), 'comment_author_email' => (string) ($_COOKIE['comment_author_email_' . $hash] ?? ''), 'comment_author_url' => (string) ($_COOKIE['comment_author_url_' . $hash] ?? '')];
    return apply_filters('wp_get_current_commenter', $commenter);
}

/** Pages of comments at the per-page setting; threaded counts top-level comments only. */
function get_comment_pages_count($comments = null, $per_page = null, $threaded = null)
{
    $comments ??= $GLOBALS['wp_query']->comments ?? [];
    if (empty($comments)) {
        return 0;
    }
    $per_page = (int) ($per_page ?? get_option('comments_per_page'));
    if ($per_page === 0) {
        $per_page = (int) get_option('comments_per_page');
    }
    if ($per_page === 0) {
        return 1;
    }
    $threaded ??= (bool) get_option('thread_comments');
    $count = $threaded ? count(array_filter($comments, static fn ($c) => (int) ($c->comment_parent ?? 0) === 0)) : count($comments);
    return (int) ceil($count / $per_page);
}

/** @internal the arguments wp_list_comments resolves */
function _minn_list_comments_args($args): array
{
    $defaults = ['walker' => null, 'max_depth' => '', 'style' => 'ul', 'callback' => null, 'end-callback' => null, 'type' => 'all', 'page' => '', 'per_page' => '', 'avatar_size' => 32, 'reverse_top_level' => null, 'reverse_children' => '', 'format' => 'html5', 'short_ping' => false, 'echo' => true];
    $args = wp_parse_args($args, $defaults);
    $args = apply_filters('wp_list_comments_args', $args);
    if ($args['max_depth'] === '' || $args['max_depth'] === null) {
        $args['max_depth'] = get_option('thread_comments') ? (int) get_option('thread_comments_depth') : -1;
    }
    if ($args['reverse_top_level'] === null) {
        $args['reverse_top_level'] = get_option('comment_order') === 'desc';
    }
    return $args;
}

/** The classic comment list: the reference's html5 item markup, threaded by the settings. */
function wp_list_comments($args = [], $comments = null)
{
    $args = _minn_list_comments_args($args);
    $comments ??= $GLOBALS['wp_query']->comments ?? [];
    if (empty($comments)) {
        return null;
    }
    $GLOBALS['comment_alt'] = 0;
    $GLOBALS['comment_thread_alt'] = 0;
    $GLOBALS['comment_depth'] = 1;
    $rows = array_map(static fn ($c) => (array) get_comment($c), $comments);
    if ($args['type'] !== 'all') {
        $wanted = $args['type'] === 'pings' ? ['pingback', 'trackback'] : [$args['type'] === 'comment' ? 'comment' : $args['type']];
        $rows = array_values(array_filter($rows, static fn (array $r) => in_array($r['comment_type'] === '' ? 'comment' : $r['comment_type'], $wanted, true)));
    }
    $item = static function (array $row, int $depth) use ($args): string {
        $GLOBALS['comment_depth'] = $depth;
        $GLOBALS['comment'] = get_comment((int) $row['comment_ID']);
        if ($args['callback'] !== null) {
            ob_start();
            $args['callback']($GLOBALS['comment'], $args, $depth);
            return (string) ob_get_clean();
        }
        return _minn_html5_comment($GLOBALS['comment'], $args, $depth);
    };
    $close = static function (array $row, int $depth) use ($args): string {
        if ($args['end-callback'] !== null) {
            ob_start();
            $args['end-callback'](get_comment((int) $row['comment_ID']), $args, $depth);
            return (string) ob_get_clean();
        }
        return "\t\t</li><!-- #comment-## -->\n";
    };
    $output = CommentList::render($rows, $args, $item, $close);
    $GLOBALS['comment_depth'] = 1;
    if ($args['echo']) {
        echo $output;
        return null;
    }
    return $output;
}

/** @internal one comment's html5 markup, as the reference's walker prints it */
function _minn_html5_comment(WP_Comment $comment, array $args, int $depth): string
{
    $tag = $args['style'] === 'div' ? 'div' : 'li';
    $type = $comment->comment_type === '' ? 'comment' : $comment->comment_type;
    if ($type === 'pingback' || $type === 'trackback') {
        return "\t\t<{$tag} id=\"comment-{$comment->comment_ID}\" " . comment_class($args['has_children'] ?? false ? 'parent' : '', $comment, null, false) . ">\n"
            . "\t\t\t<div class=\"comment-body\">\n\t\t\t\t" . ($args['short_ping'] ? 'Pingback:' : 'Pingback:') . ' ' . get_comment_author_link($comment) . "\t\t\t</div>\n";
    }
    $avatar = (int) $args['avatar_size'] !== 0 ? (string) get_avatar($comment, $args['avatar_size']) : '';
    $moderation = $comment->comment_approved === '0' ? "\t\t\t\t\t<em class=\"comment-awaiting-moderation\">Your comment is awaiting moderation.</em>\n" : '';
    $reply = (string) get_comment_reply_link(array_merge($args, ['add_below' => 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth'], 'before' => '<div class="reply">', 'after' => '</div>']), $comment);
    ob_start();
    comment_text($comment, $args);
    $text = (string) ob_get_clean();
    $metaText = sprintf('%1$s at %2$s', get_comment_date('', $comment), get_comment_time('', false, true, $comment));
    return "\t\t<{$tag} id=\"comment-{$comment->comment_ID}\" " . comment_class('', $comment, null, false) . ">\n"
        . "\t\t\t<article id=\"div-comment-{$comment->comment_ID}\" class=\"comment-body\">\n"
        . "\t\t\t\t<footer class=\"comment-meta\">\n"
        . "\t\t\t\t\t<div class=\"comment-author vcard\">\n"
        . "\t\t\t\t\t\t" . $avatar . "\t\t\t\t\t\t" . '<b class="fn">' . get_comment_author_link($comment) . '</b> <span class="says">says:</span>' . "\t\t\t\t\t</div><!-- .comment-author -->\n\n"
        . "\t\t\t\t\t<div class=\"comment-metadata\">\n"
        . "\t\t\t\t\t\t" . '<a href="' . esc_url(get_comment_link($comment, $args)) . '"><time datetime="' . get_comment_time('c', false, true, $comment) . '">' . $metaText . '</time></a>' . "\t\t\t\t\t</div><!-- .comment-metadata -->\n\n"
        . "\t\t\t\t\t" . $moderation . "\t\t\t\t</footer><!-- .comment-meta -->\n\n"
        . "\t\t\t\t<div class=\"comment-content\">\n"
        . "\t\t\t\t\t" . $text . "\t\t\t\t</div><!-- .comment-content -->\n\n"
        . "\t\t\t\t" . $reply . "\t\t\t</article><!-- .comment-body -->\n";
}

function get_comment_time($format = '', $gmt = false, $translate = true, $comment_id = 0)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return '';
    }
    $date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
    $format = $format === '' ? get_option('time_format') : $format;
    return apply_filters('get_comment_time', mysql2date($format, $date, $translate), $format, $gmt, $translate, $comment);
}

function comment_text($comment_id = 0, $args = [])
{
    $comment = get_comment($comment_id);
    echo apply_filters('comment_text', get_comment_text($comment, $args), $comment, $args);
}

function get_comment_ID()
{
    $comment = get_comment();
    return apply_filters('get_comment_ID', (string) ($comment->comment_ID ?? ''), $comment);
}

/** The author's name, linked to their URL when they gave one. */
function get_comment_author_link($comment_id = 0)
{
    $comment = get_comment($comment_id);
    $url = get_comment_author_url($comment);
    $author = get_comment_author($comment);
    if ($url === '' || $url === 'http://') {
        $link = $author;
    } else {
        $rel = 'ugc';
        if (!str_starts_with($url, home_url())) {
            $rel .= ' external nofollow';
        }
        $link = '<a href="' . $url . '" class="url" rel="' . $rel . '">' . $author . '</a>';
    }
    return apply_filters('get_comment_author_link', $link, $author, (string) ($comment->comment_ID ?? ''));
}

/** The hidden fields a comment form posts: the post and the comment replied to. */
function get_comment_id_fields($post = null)
{
    $post = get_post($post);
    $post_id = $post ? (int) $post->ID : 0;
    $reply_to = isset($_GET['replytocom']) ? (int) $_GET['replytocom'] : 0;
    $result = "<input type='hidden' name='comment_post_ID' value='{$post_id}' id='comment_post_ID' />\n";
    $result .= "<input type='hidden' name='comment_parent' id='comment_parent' value='{$reply_to}' />\n";
    return apply_filters('comment_id_fields', $result, $post_id, $reply_to);
}

function comment_id_fields($post = null)
{
    echo get_comment_id_fields($post);
}

/** The post's link to its comments, or to the form when it has none. */
function get_comments_link($post = 0)
{
    $post = get_post($post);
    $link = $post ? get_permalink($post) . (get_comments_number($post) > 0 ? '#comments' : '#respond') : '';
    return apply_filters('get_comments_link', $link, $post ? (int) $post->ID : 0);
}

/** A page of a post's comments: comment-page-N/ under a pretty permalink, ?cpage=N otherwise. */
function get_comments_pagenum_link($pagenum = 1, $max_page = 0)
{
    $pagenum = (int) $pagenum;
    $permalink = get_permalink();
    $bare = Minn\Front\ListingLinks::bareCommentPage((string) get_option('default_comments_page'), (int) $max_page);
    if ($permalink === '' || $pagenum === $bare) {
        $result = $permalink;
    } elseif ($GLOBALS['wp_rewrite']->using_permalinks()) {
        $result = user_trailingslashit(trailingslashit($permalink) . 'comment-page-' . $pagenum, 'commentpaged');
    } else {
        $result = add_query_arg('cpage', $pagenum, $permalink);
    }
    return apply_filters('get_comments_pagenum_link', $result . '#comments');
}

/** The reply link for a comment, within the depth allowed and while comments stay open. */
function get_comment_reply_link($args = [], $comment = null, $post = null)
{
    $args = wp_parse_args($args, ['add_below' => 'comment', 'respond_id' => 'respond', 'reply_text' => 'Reply', 'reply_to_text' => 'Reply to %s', 'login_text' => 'Log in to Reply', 'max_depth' => 0, 'depth' => 0, 'before' => '', 'after' => '']);
    if ((int) $args['depth'] === 0 || (int) $args['max_depth'] <= (int) $args['depth']) {
        return null;
    }
    $comment = get_comment($comment);
    $post = get_post($post ?? ($comment->comment_post_ID ?? null));
    if (!$comment || !$post || !comments_open($post->ID)) {
        return false;
    }
    if (get_option('comment_registration') && !is_user_logged_in()) {
        $link = sprintf('<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>', esc_url(wp_login_url(get_permalink())), $args['login_text']);
    } else {
        $author = get_comment_author($comment);
        $label = sprintf($args['reply_to_text'], $author);
        $href = esc_url(add_query_arg(['replytocom' => $comment->comment_ID, 'unapproved' => false, 'moderation-hash' => false], get_permalink($post->ID))) . '#' . $args['respond_id'];
        $link = sprintf('<a rel="nofollow" class="comment-reply-link" href="%s" data-commentid="%d" data-postid="%d" data-belowelement="%s" data-respondelement="%s" data-replyto="%s" aria-label="%s">%s</a>', $href, $comment->comment_ID, $post->ID, $args['add_below'] . '-' . $comment->comment_ID, $args['respond_id'], esc_attr($label), esc_attr($label), $args['reply_text']);
    }
    return apply_filters('comment_reply_link', $args['before'] . $link . $args['after'], $args, $comment, $post);
}

function comment_reply_link($args = [], $comment = null, $post = null)
{
    echo get_comment_reply_link($args, $comment, $post);
}

/** The link that cancels a reply: the page without its replytocom, hidden unless one is replying. */
function get_cancel_comment_reply_link($link_text = '', $post = null)
{
    $link_text = $link_text === '' ? 'Click here to cancel reply.' : $link_text;
    $style = isset($_GET['replytocom']) ? '' : ' style="display:none;"';
    $link = esc_html(remove_query_arg(['replytocom', 'unapproved', 'moderation-hash'])) . '#respond';
    $markup = '<a rel="nofollow" id="cancel-comment-reply-link" href="' . $link . '"' . $style . '>' . $link_text . '</a>';
    return apply_filters('cancel_comment_reply_link', $markup, $link, $link_text);
}

/** The form heading: a reply to a named comment, or the plain title. */
function comment_form_title($no_reply_text = false, $reply_text = false, $link_to_parent = true, $post = null)
{
    $no_reply_text = $no_reply_text === false ? 'Leave a Reply' : $no_reply_text;
    $reply_text = $reply_text === false ? 'Leave a Reply to %s' : $reply_text;
    $reply_to = isset($_GET['replytocom']) ? get_comment((int) $_GET['replytocom']) : null;
    if ($reply_to === null) {
        echo $no_reply_text;
        return;
    }
    $author = $link_to_parent ? '<a href="#comment-' . $reply_to->comment_ID . '">' . get_comment_author($reply_to) . '</a>' : get_comment_author($reply_to);
    printf($reply_text, $author);
}

function get_next_comments_link($label = '', $max_page = 0, $page = null)
{
    if (!is_singular()) {
        return null;
    }
    $page = (int) ($page ?? (get_query_var('cpage') ?: 1));
    $max_page = (int) $max_page ?: (int) ($GLOBALS['wp_query']->max_num_comment_pages ?? 0) ?: (int) get_comment_pages_count();
    $next = Minn\Front\ListingLinks::neighbours($page, $max_page)[1];
    if ($next === null) {
        return null;
    }
    return Minn\Front\ListingLinks::anchor(
        esc_url(get_comments_pagenum_link($next, $max_page)),
        (string) apply_filters('next_comments_link_attributes', ''),
        Minn\Front\ListingLinks::label((string) $label, 'Newer Comments &raquo;'),
    );
}

function get_previous_comments_link($label = '', $page = null)
{
    if (!is_singular()) {
        return null;
    }
    $page = (int) ($page ?? (get_query_var('cpage') ?: 1));
    $previous = Minn\Front\ListingLinks::neighbours($page, PHP_INT_MAX)[0];
    if ($previous === null) {
        return null;
    }
    return Minn\Front\ListingLinks::anchor(
        esc_url(get_comments_pagenum_link($previous)),
        (string) apply_filters('previous_comments_link_attributes', ''),
        Minn\Front\ListingLinks::label((string) $label, '&laquo; Older Comments'),
    );
}

function next_comments_link($label = '', $max_page = 0)
{
    echo get_next_comments_link($label, $max_page);
}

function previous_comments_link($label = '')
{
    echo get_previous_comments_link($label);
}

/** Numbered links over a post's comment pages; nothing with a single page. */
function paginate_comments_links($args = [])
{
    if (!is_singular()) {
        return null;
    }
    $page = (int) get_query_var('cpage') ?: 1;
    $defaults = ['base' => add_query_arg('cpage', '%#%'), 'format' => '', 'total' => (int) ($GLOBALS['wp_query']->max_num_comment_pages ?? 0), 'current' => $page, 'echo' => true, 'add_fragment' => '#comments'];
    if ($GLOBALS['wp_rewrite']->using_permalinks()) {
        $defaults['base'] = user_trailingslashit(trailingslashit(get_permalink()) . 'comment-page-%#%', 'commentpaged');
    }
    $args = wp_parse_args($args, $defaults);
    $links = paginate_links($args);
    if ($args['echo'] && ($args['type'] ?? 'plain') === 'plain') {
        echo $links;
    }
    return $links;
}

/** The post's comments into the main query, then the theme's comments.php or the engine's own. */
function comments_template($file = '/comments.php', $separate_comments = false)
{
    global $wp_query, $withcomments, $post, $comment, $user_ID;
    if (!(is_single() || is_page() || $withcomments) || empty($post)) {
        return;
    }
    if (post_password_required()) {
        return;
    }
    $rows = get_comments(['post_id' => (int) $post->ID, 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'status' => 'approve', 'include_unapproved' => is_user_logged_in() ? [get_current_user_id()] : [], 'no_found_rows' => false]);
    $wp_query->comments = apply_filters('comments_array', $rows, (int) $post->ID);
    $wp_query->comment_count = count($wp_query->comments);
    $wp_query->max_num_comment_pages = get_comment_pages_count($wp_query->comments);
    if ($separate_comments) {
        $wp_query->comments_by_type = separate_comments($wp_query->comments);
    }
    $theme_template = locate_template([ltrim((string) $file, '/')]);
    $include = apply_filters('comments_template', $theme_template !== '' ? $theme_template : MINN_ENGINE_DIR . '/wp-api/theme-compat/comments.php');
    require $include;
}

/** Comments grouped by type: comment, trackback, pingback, pings. */
function separate_comments(&$comments)
{
    $groups = ['comment' => [], 'trackback' => [], 'pingback' => [], 'pings' => []];
    foreach ((array) $comments as $comment) {
        $type = $comment->comment_type === '' ? 'comment' : $comment->comment_type;
        $type = isset($groups[$type]) ? $type : 'comment';
        $groups[$type][] = $comment;
        if ($type === 'trackback' || $type === 'pingback') {
            $groups['pings'][] = $comment;
        }
    }
    return $groups;
}

/** @internal the default author, email, url (and cookies) fields */
function _minn_comment_form_fields(): array
{
    $commenter = wp_get_current_commenter();
    $req = (bool) get_option('require_name_email');
    $required = $req ? ' <span class="required">*</span>' : '';
    $attr = $req ? ' required' : '';
    $fields = [
        'author' => '<p class="comment-form-author"><label for="author">Name' . $required . '</label> <input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" maxlength="245" autocomplete="name"' . $attr . ' /></p>',
        'email' => '<p class="comment-form-email"><label for="email">Email' . $required . '</label> <input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"' . $attr . ' /></p>',
        'url' => '<p class="comment-form-url"><label for="url">Website</label> <input id="url" name="url" type="url" value="' . esc_attr($commenter['comment_author_url']) . '" size="30" maxlength="200" autocomplete="url" /></p>',
    ];
    if (get_option('show_comments_cookies_opt_in')) {
        $fields['cookies'] = _minn_comment_cookies_field();
    }
    return $fields;
}

/** @internal the cookies consent field, checked for a remembered commenter */
function _minn_comment_cookies_field(): string
{
    $consent = empty(wp_get_current_commenter()['comment_author_email']) ? '' : ' checked="checked"';
    return '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' /> <label for="wp-comment-cookies-consent">Save my name, email, and website in this browser for the next time I comment.</label></p>';
}

/** @internal the comment form's arguments after the defaults and filters */
function _minn_comment_form_args($args, WP_Post $post): array
{
    $user = wp_get_current_user();
    $req = (bool) get_option('require_name_email');
    $block = (bool) Runtime::current()->get('block_theme', false);
    $custom_button = is_array($args) && (isset($args['submit_button']) || isset($args['submit_field']));
    $defaults = [
        'fields' => apply_filters('comment_form_default_fields', _minn_comment_form_fields()),
        'comment_field' => '<p class="comment-form-comment"><label for="comment">Comment <span class="required">*</span></label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required></textarea></p>',
        'must_log_in' => '<p class="must-log-in">You must be <a href="' . esc_url(wp_login_url(get_permalink($post->ID))) . '">logged in</a> to post a comment.</p>',
        'logged_in_as' => '<p class="logged-in-as"><a href="' . esc_url(get_edit_user_link()) . '" aria-label="Logged in as ' . esc_attr($user->display_name) . '. Edit your profile.">Logged in as ' . esc_html($user->display_name) . '.</a> <a href="' . esc_url(wp_logout_url(get_permalink($post->ID))) . '">Log out?</a></p>',
        'comment_notes_before' => '<p class="comment-notes"><span id="email-notes">Your email address will not be published.</span> ' . ($req ? '<span class="required-field-message">Required fields are marked <span class="required">*</span></span>' : '') . '</p>',
        'comment_notes_after' => '',
        'action' => site_url('/wp-comments-post.php'),
        'id_form' => 'commentform',
        'id_submit' => 'submit',
        'class_container' => 'comment-respond',
        'class_form' => 'comment-form',
        'class_submit' => $block && !$custom_button ? 'submit wp-block-button__link wp-element-button' : 'submit',
        'name_submit' => 'submit',
        'title_reply' => 'Leave a Reply',
        'title_reply_to' => 'Leave a Reply to %s',
        'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
        'title_reply_after' => '</h3>',
        'cancel_reply_before' => ' <small>',
        'cancel_reply_after' => '</small>',
        'cancel_reply_link' => 'Cancel reply',
        'label_submit' => 'Post Comment',
        'submit_button' => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />',
        'submit_field' => $block ? '<p class="form-submit wp-block-button">%1$s %2$s</p>' : '<p class="form-submit">%1$s %2$s</p>',
        'format' => 'xhtml',
    ];
    $args = wp_parse_args($args, apply_filters('comment_form_defaults', $defaults));
    return array_merge($defaults, $args);
}

/** The comment form, or nothing (and comment_form_comments_closed) when the post takes no comments. */
function comment_form($args = [], $post = null)
{
    $post = get_post($post);
    if (!$post) {
        return;
    }
    if (!comments_open($post->ID)) {
        do_action('comment_form_comments_closed');
        return;
    }
    $args = _minn_comment_form_args($args, $post);
    do_action('comment_form_before');
    echo "\t" . '<div id="respond" class="' . esc_attr($args['class_container']) . '">' . "\n\t\t" . $args['title_reply_before'];
    comment_form_title($args['title_reply'], $args['title_reply_to'], true, $post->ID);
    if (get_option('thread_comments')) {
        echo $args['cancel_reply_before'] . get_cancel_comment_reply_link($args['cancel_reply_link'], $post->ID) . $args['cancel_reply_after'];
    }
    echo $args['title_reply_after'];
    if (get_option('comment_registration') && !is_user_logged_in()) {
        echo $args['must_log_in'];
        do_action('comment_form_must_log_in_after');
    } else {
        _minn_comment_form_body($args, $post);
    }
    echo "\t" . '</div><!-- #respond -->' . "\n\t";
    do_action('comment_form_after');
}

/** @internal the form element with its fields and submit */
function _minn_comment_form_body(array $args, WP_Post $post): void
{
    printf('<form action="%s" method="post" id="%s" class="%s"%s>', esc_url($args['action']), esc_attr($args['id_form']), esc_attr($args['class_form']), $args['format'] === 'html5' ? ' novalidate' : '');
    do_action('comment_form_top');
    if (is_user_logged_in()) {
        echo apply_filters('comment_form_logged_in', $args['logged_in_as'], wp_get_current_commenter(), wp_get_current_user()->display_name);
        do_action('comment_form_logged_in_after', wp_get_current_commenter(), wp_get_current_user()->display_name);
    } else {
        echo $args['comment_notes_before'];
    }
    $fields = ['comment' => $args['comment_field']];
    if (!is_user_logged_in()) {
        $fields += (array) $args['fields'];
    }
    if (!isset($fields['cookies']) && get_option('show_comments_cookies_opt_in')) {
        $fields['cookies'] = _minn_comment_cookies_field();
    }
    $fields = apply_filters('comment_form_fields', $fields);
    $names = array_keys($fields);
    $first = true;
    foreach ($fields as $name => $field) {
        if ($name === 'comment') {
            echo apply_filters('comment_form_field_comment', $field);
            echo $args['comment_notes_after'];
            continue;
        }
        if ($first) {
            do_action('comment_form_before_fields');
            $first = false;
        }
        echo apply_filters("comment_form_field_{$name}", $field) . "\n";
        if ($name === end($names)) {
            do_action('comment_form_after_fields');
        }
    }
    $button = sprintf($args['submit_button'], esc_attr($args['name_submit']), esc_attr($args['id_submit']), esc_attr($args['class_submit']), esc_attr($args['label_submit']));
    $button = apply_filters('comment_form_submit_button', $button, $args);
    $field = sprintf($args['submit_field'], $button, get_comment_id_fields($post->ID));
    echo apply_filters('comment_form_submit_field', $field, $args);
    do_action('comment_form', $post->ID);
    echo '</form>';
}

function get_default_comment_status($post_type = 'post', $comment_type = 'comment')
{
    // A page starts closed; another type follows the site's default when it supports comments (or trackbacks, for pings).
    $feature = in_array($comment_type, ['pingback', 'trackback'], true) ? 'trackbacks' : 'comments';
    $option = $feature === 'comments' ? 'default_comment_status' : 'default_ping_status';
    $status = $post_type !== 'page' && post_type_supports($post_type, $feature) && get_option($option) === 'open' ? 'open' : 'closed';
    return apply_filters('get_default_comment_status', $status, $post_type, $comment_type);
}

function wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent)
{
    _deprecated_function(__FUNCTION__, '5.5.0', 'wp_check_comment_disallowed_list()');
    return wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent);
}

/** True when any line of the disallowed_keys option appears in the comment's fields (a word match, or an IP prefix). */
function wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent)
{
    do_action('wp_check_comment_disallowed_list', $author, $email, $url, $comment, $user_ip, $user_agent);
    $keys = trim((string) get_option('disallowed_keys'));
    if ($keys === '') {
        return false;
    }
    foreach (explode("\n", $keys) as $word) {
        $word = trim($word);
        if ($word === '') {
            continue;
        }
        $pattern = '#' . preg_quote($word, '#') . '#iu';
        foreach ([$author, $email, $url, $comment, $user_ip, $user_agent] as $field) {
            if (preg_match($pattern, (string) $field)) {
                return true;
            }
        }
    }
    return false;
}

function _close_comments_for_old_post($open, $post_id)
{
    $post = get_post($post_id ?: null);
    $closer = new Minn\Runtime\CommentCloser((bool) get_option('close_comments_for_old_posts'), (int) get_option('close_comments_days_old'));
    return $closer->open((bool) $open, $post instanceof WP_Post ? $post->to_array() : null, time());
}

/**
 * Whether a new comment may be approved outright, the reference's option
 * gauntlet: manual moderation, the link budget, the moderation keys, and
 * the previously-approved-author requirement (both probe cases refused
 * under the dev defaults because the authors had no approved history).
 */
function check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type)
{
    if ((string) get_option('comment_moderation') === '1') {
        return false;
    }
    $max = (int) get_option('comment_max_links');
    if ($max > 0 && preg_match_all('#(https?://|<a [^>]*href)#i', (string) $comment) >= $max) {
        return false;
    }
    foreach (explode("\n", (string) get_option('moderation_keys')) as $word) {
        $word = trim($word);
        if ($word !== '' && preg_match('#' . preg_quote($word, '#') . '#i', $author . ' ' . $email . ' ' . $url . ' ' . $comment . ' ' . $user_ip . ' ' . $user_agent)) {
            return false;
        }
    }
    if ((string) get_option('comment_previously_approved') === '1') {
        return _minn_comments()->hasApprovedByEmail((string) $email);
    }
    return true;
}

/** Mails the moderation queue notice to the site admin; gated by the notify_moderator filter over the option. */
function wp_new_comment_notify_moderator($comment_id)
{
    $comment = get_comment($comment_id);
    $notify = (bool) apply_filters('notify_moderator', (string) get_option('moderation_notify') === '1', (int) $comment_id);
    if ($comment === null || !$notify) {
        return false;
    }
    $post = get_post((int) $comment->comment_post_ID);
    $subject = '[' . wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES) . '] Please moderate: "' . ($post->post_title ?? '') . '"';
    $message = sprintf("A new comment on the post \"%s\" is waiting for your approval\n%s\n\n", $post->post_title ?? '', get_permalink($post))
        . sprintf("Author: %s\nEmail: %s\n\nComment:\n%s\n\n", $comment->comment_author, $comment->comment_author_email, $comment->comment_content)
        . 'Moderate it here: ' . admin_url('comment.php?action=approve&c=' . (int) $comment_id) . "\n";
    return (bool) wp_mail((string) get_option('admin_email'), $subject, $message);
}

/** Mails the post's author about a new comment; gated by the notify_post_author filter over the option. */
function wp_new_comment_notify_postauthor($comment_id)
{
    $comment = get_comment($comment_id);
    $notify = (bool) apply_filters('notify_post_author', (string) get_option('comments_notify') === '1', (int) $comment_id);
    if ($comment === null || !$notify) {
        return false;
    }
    $post = get_post((int) $comment->comment_post_ID);
    $author = $post === null ? null : get_userdata((int) $post->post_author);
    if ($author === false || $author === null || (int) ($comment->user_id ?? 0) === (int) $post->post_author || (string) $author->user_email === '') {
        return false;
    }
    $subject = '[' . wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES) . '] Comment: "' . $post->post_title . '"';
    $message = sprintf("New comment on your post \"%s\"\n", $post->post_title)
        . sprintf("Author: %s\nEmail: %s\n\nComment:\n%s\n\n", $comment->comment_author, $comment->comment_author_email, $comment->comment_content)
        . 'See all comments on this post here: ' . get_permalink($post) . "#comments\n";
    return (bool) wp_mail((string) $author->user_email, $subject, $message);
}

/**
 * The admin URL for editing one comment. Display context escapes the
 * ampersand, as the reference's captured value does; there is no
 * /wp-admin/ on the engine, but a theme still prints the link for an
 * editor and the URL has to be the one tooling expects.
 */
function get_edit_comment_link($comment_id = 0, $context = 'display')
{
    $comment = get_comment($comment_id);
    if (!$comment instanceof WP_Comment || !current_user_can('edit_comment', $comment->comment_ID)) {
        return null;
    }
    $sep = $context === 'display' ? '&amp;' : '&';
    $location = admin_url('comment.php?action=editcomment') . $sep . 'c=' . $comment->comment_ID;
    return apply_filters('get_edit_comment_link', $location, (int) $comment->comment_ID, $context);
}

function edit_comment_link($text = null, $before = '', $after = '')
{
    $comment = get_comment();
    if (!$comment instanceof WP_Comment || !current_user_can('edit_comment', $comment->comment_ID)) {
        return;
    }
    $text ??= 'Edit This';
    $link = '<a class="comment-edit-link" href="' . esc_url((string) get_edit_comment_link($comment)) . '">' . $text . '</a>';
    echo $before . apply_filters('edit_comment_link', $link, (int) $comment->comment_ID, $text) . $after;
}

/** The bare URL of a post's comments area, echoed for a theme to wrap. */
function comments_link($deprecated = '', $deprecated_2 = '')
{
    echo esc_url(get_comments_link());
}

function comments_popup_link($zero = false, $one = false, $more = false, $css_class = '', $none = false)
{
    $number = get_comments_number();
    if ($number === 0 && !comments_open() && !pings_open()) {
        if ($none !== false) {
            echo '<span' . ($css_class !== '' ? ' class="' . esc_attr($css_class) . '"' : '') . '>' . ($none === '' ? 'Comments Off' : $none) . '</span>';
        }
        return;
    }
    // Only the defaults carry the screen-reader title; a theme that supplies
    // its own wording is left alone.
    if ($zero === false && $one === false && $more === false) {
        $text = get_comments_number_text();
        $text .= '<span class="screen-reader-text"> on ' . get_the_title() . '</span>';
        echo '<a href="' . esc_url(get_comments_link()) . '">' . $text . '</a>';
        return;
    }
    echo Minn\Front\ListingLinks::anchor(
        esc_url(get_comments_link()),
        $css_class !== '' ? 'class="' . esc_attr($css_class) . '" ' : '',
        get_comments_number_text($zero === false ? '' : $zero, $one === false ? '' : $one, $more === false ? '' : $more),
    );
}

function get_the_comments_navigation($args = [])
{
    if ((int) ($GLOBALS['wp_query']->max_num_comment_pages ?? 0) <= 1) {
        return '';
    }
    $aria = Minn\Front\PostNavigation::ariaLabel((array) $args, 'Comments');
    $args = wp_parse_args($args, ['prev_text' => 'Older comments', 'next_text' => 'Newer comments', 'screen_reader_text' => 'Comments navigation', 'aria_label' => 'Comments', 'class' => 'comment-navigation']);
    $links = '';
    $previous = get_previous_comments_link($args['prev_text']);
    if ($previous) {
        $links .= '<div class="nav-previous">' . $previous . '</div>';
    }
    $next = get_next_comments_link($args['next_text']);
    if ($next) {
        $links .= '<div class="nav-next">' . $next . '</div>';
    }
    return $links === '' ? '' : _navigation_markup($links, $args['class'], $args['screen_reader_text'], $aria);
}

function the_comments_navigation($args = [])
{
    echo get_the_comments_navigation($args);
}

function get_the_comments_pagination($args = [])
{
    $aria = Minn\Front\PostNavigation::ariaLabel((array) $args, 'Comments pagination');
    $args = wp_parse_args($args, ['screen_reader_text' => 'Comments pagination', 'aria_label' => 'Comments pagination', 'class' => 'comments-pagination']);
    $args['echo'] = false;
    if (!isset($args['type']) || $args['type'] === 'array') {
        $args['type'] = 'plain';
    }
    $links = paginate_comments_links($args);
    return $links ? _navigation_markup($links, $args['class'], $args['screen_reader_text'], $aria) : '';
}

function the_comments_pagination($args = [])
{
    echo get_the_comments_pagination($args);
}

function comment_author_link($comment_id = 0)
{
    echo get_comment_author_link($comment_id);
}

function comment_date($format = '', $comment_id = 0)
{
    echo get_comment_date($format, $comment_id);
}

function comment_time($format = '', $comment_id = 0)
{
    echo get_comment_time($format);
}

function wp_get_comment_fields_max_lengths()
{
    return apply_filters('wp_get_comment_fields_max_lengths', _minn_comments()->fieldLengths());
}
