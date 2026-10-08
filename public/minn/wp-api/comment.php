<?php
/** Comments: reads, counts, the writers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Runtime\Deferrals;
use Minn\Content\CommentClasses;
use Minn\Content\CommentModeration;
use Minn\Content\Comments;
use Minn\Runtime\CommentQuery;
use Minn\Runtime\Runtime;

/** @internal */
function _minn_comments(): Comments
{
    return new Comments(Runtime::current()->db);
}

/** The comment meta core registers on init: a note's status, editable by whoever may edit the comment (probe meta-registry). */
function wp_create_initial_comment_meta()
{
    register_meta('comment', '_wp_note_status', [
        'type' => 'string',
        'description' => __('Note resolution status'),
        'single' => true,
        'show_in_rest' => ['schema' => ['type' => 'string', 'enum' => ['resolved', 'reopen']]],
        'auth_callback' => static fn ($allowed, $meta_key, $object_id) => current_user_can('edit_comment', $object_id),
    ]);
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
    $query = new WP_Comment_Query();
    return $query->query($args);
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
    clean_comment_cache($id);
    $comment = get_comment($id);
    do_action('wp_insert_comment', $id, $comment);
    return $id;
}

function wp_update_comment_count($post_id, $do_deferred = false)
{
    if ($do_deferred) {
        return Deferrals::comments(false);
    }
    // While counting is deferred the recount waits for wp_defer_comment_counting(false).
    return Deferrals::putOffComments((int) $post_id) || wp_update_comment_count_now($post_id);
}

/** Recounts a post's approved comments now, telling plugins the old and new counts. */
function wp_update_comment_count_now($post_id)
{
    $post_id = (int) $post_id;
    $post = $post_id > 0 ? get_post($post_id) : null;
    if ($post === null) {
        return false;
    }
    $old = (int) $post->comment_count;
    // A plugin may supply the count itself (pre_wp_update_comment_count_now); otherwise it is counted.
    $given = apply_filters('pre_wp_update_comment_count_now', null, $old, $post_id);
    $given === null ? _minn_comments()->recount($post_id) : _minn_post_writer()->update($post_id, ['comment_count' => (int) $given]);
    clean_post_cache($post_id);
    $new = (int) get_post($post_id)->comment_count;
    do_action('wp_update_comment_count', $post_id, $new, $old);
    do_action("edit_post_{$post->post_type}", $post_id, get_post($post_id));
    do_action('edit_post', $post_id, get_post($post_id));
    return true;
}

/**
 * A comment changed as the reference changes one (probe rest-comment-save):
 * the stored comment under the change, through wp_filter_comment and
 * comment_save_pre, then wp_update_comment_data (handed the data, the
 * stored comment and the change); the columns written, the post's count,
 * edit_comment with the columns, and the status transition. 0 when
 * nothing changed.
 */
function wp_update_comment($commentarr, $wp_error = false)
{
    $given = wp_unslash((array) $commentarr);
    $comment = get_comment((int) ($given['comment_ID'] ?? 0));
    if ($comment === null) {
        return $wp_error ? new WP_Error('invalid_comment_id', 'Invalid comment ID.') : false;
    }
    $stored = $comment->to_array();
    $commentarr = wp_filter_comment(array_merge($stored, $given));
    $commentarr['comment_content'] = apply_filters('comment_save_pre', $commentarr['comment_content']);
    $data = apply_filters('wp_update_comment_data', $commentarr, $stored, $commentarr);
    if (is_wp_error($data)) {
        return $wp_error ? $data : 0;
    }
    $columns = array_intersect_key((array) $data, array_flip(['comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id']));
    $changed = Comments::changedColumns($columns, $stored);
    if ($changed !== []) {
        _minn_comments()->update($comment->comment_ID, $changed);
    }
    clean_comment_cache($comment->comment_ID);
    wp_update_comment_count((int) $comment->comment_post_ID);
    do_action('edit_comment', $comment->comment_ID, $columns);
    $updated = get_comment($comment->comment_ID);
    wp_transition_comment_status(_minn_comment_status_word($updated->comment_approved), _minn_comment_status_word($comment->comment_approved), $updated);
    return $changed === [] ? 0 : 1;
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
    clean_comment_cache($comment->comment_ID);
    $updated = get_comment($comment->comment_ID);
    do_action('wp_set_comment_status', $comment->comment_ID, $comment_status);
    wp_transition_comment_status(_minn_comment_status_word($status), _minn_comment_status_word($comment->comment_approved), $updated);
    wp_update_comment_count((int) $comment->comment_post_ID);
    return true;
}

/** @internal A stored comment status in the words the transition actions use: approved, unapproved, spam, trash. */
function _minn_comment_status_word(string $stored): string
{
    return match ($stored) {
        '1', 'approve' => 'approved',
        '0', 'hold' => 'unapproved',
        default => $stored,
    };
}

/** What plugins are told when a comment changes status; a comment that keeps its status hears only comment_{status}_{type}. */
function wp_transition_comment_status($new_status, $old_status, $comment)
{
    $new = _minn_comment_status_word((string) $new_status);
    $old = _minn_comment_status_word((string) $old_status);
    if ($new !== $old) {
        do_action('transition_comment_status', $new, $old, $comment);
        do_action("comment_{$old}_to_{$new}", $comment);
    }
    $type = $comment->comment_type === '' ? 'comment' : $comment->comment_type;
    do_action("comment_{$new}_{$type}", $comment->comment_ID, $comment);
}

/** Lets go of comments' cached rows and meta, telling plugins about each. */
function clean_comment_cache($ids)
{
    foreach ((array) $ids as $id) {
        wp_cache_delete((int) $id, 'comment');
        wp_cache_delete((int) $id, 'comment_meta');
        do_action('clean_comment_cache', (int) $id);
    }
}

/** The trash takes a post's comments with it: each becomes post-trashed, and their statuses are kept to give back. */
function wp_trash_post_comments($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    do_action('trash_post_comments', $post->ID);
    $statuses = _minn_comments()->statusesOf($post->ID);
    if ($statuses === []) {
        return;
    }
    add_post_meta($post->ID, '_wp_trash_meta_comments_status', $statuses);
    _minn_comments()->setStatusOf(array_keys($statuses), 'post-trashed');
    clean_comment_cache(array_keys($statuses));
    do_action('trashed_post_comments', $post->ID, $statuses);
    return count($statuses);
}

/** A post back from the trash gives its comments their statuses back. */
function wp_untrash_post_comments($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    $statuses = get_post_meta($post->ID, '_wp_trash_meta_comments_status', true);
    if (!is_array($statuses) || $statuses === []) {
        return true;
    }
    do_action('untrash_post_comments', $post->ID);
    foreach (array_keys(array_flip(array_map('strval', $statuses))) as $status) {
        _minn_comments()->setStatusOf(array_map('intval', array_keys($statuses, $status, false)), $status);
    }
    clean_comment_cache(array_keys($statuses));
    delete_post_meta($post->ID, '_wp_trash_meta_comments_status');
    do_action('untrashed_post_comments', $post->ID);
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
    clean_comment_cache($comment->comment_ID);
    do_action('wp_set_comment_status', $comment->comment_ID, 'delete');
    wp_transition_comment_status('delete', $comment->comment_approved, $comment);
    // Only an approved comment was counted.
    if ($comment->comment_approved === '1') {
        wp_update_comment_count((int) $comment->comment_post_ID);
    }
    return true;
}

function wp_trash_comment($comment_id)
{
    return EMPTY_TRASH_DAYS ? _minn_comment_set_aside($comment_id, 'trash', 'trash_comment', 'trashed_comment') : wp_delete_comment($comment_id, true);
}

function wp_untrash_comment($comment_id)
{
    return _minn_comment_restore($comment_id, 'untrash_comment', 'untrashed_comment');
}

function wp_spam_comment($comment_id)
{
    return _minn_comment_set_aside($comment_id, 'spam', 'spam_comment', 'spammed_comment');
}

function wp_unspam_comment($comment_id)
{
    return _minn_comment_restore($comment_id, 'unspam_comment', 'unspammed_comment');
}

/**
 * @internal a comment moved to the trash or spam as the reference moves it
 * (probe placeholders-a): the first action, the status change, the status
 * it had kept aside with the time, the second action
 */
function _minn_comment_set_aside($comment_id, string $status, string $before, string $after): bool
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    do_action($before, $comment->comment_ID, $comment);
    if (!wp_set_comment_status($comment->comment_ID, $status)) {
        return false;
    }
    delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
    delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
    add_comment_meta($comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved);
    add_comment_meta($comment->comment_ID, '_wp_trash_meta_time', time());
    do_action($after, $comment->comment_ID, $comment);
    return true;
}

/** @internal a comment back from the trash or spam to the status kept aside (held when none was), between the two actions */
function _minn_comment_restore($comment_id, string $before, string $after): bool
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    do_action($before, $comment->comment_ID, $comment);
    $status = (string) (get_comment_meta($comment->comment_ID, '_wp_trash_meta_status', true) ?: '0');
    if (!wp_set_comment_status($comment->comment_ID, $status)) {
        return false;
    }
    delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
    delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
    do_action($after, $comment->comment_ID, $comment);
    return true;
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

/** When an approved comment last arrived, in GMT ('gmt', and the server's own, which is GMT here) or the site's time ('blog'); false with none. */
function get_lastcommentmodified($timezone = 'server')
{
    global $wpdb;
    $timezone = strtolower((string) $timezone);
    $value = $wpdb->get_var('SELECT ' . ($timezone === 'blog' ? 'comment_date' : 'comment_date_gmt') . " FROM {$wpdb->comments} WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
    return apply_filters('get_lastcommentmodified', $value === null ? false : $value, $timezone);
}

/** A comment's link, at the page of comments it is on (Minn\Runtime\CommentPages), through get_comment_link. */
function get_comment_link($comment = null, $args = [])
{
    $comment = get_comment($comment);
    $args = wp_parse_args(is_array($args) ? $args : ['page' => $args], ['type' => 'all', 'page' => '', 'per_page' => '', 'max_depth' => '', 'cpage' => null]);
    $page = Minn\Runtime\CommentPages::linkPage($comment, $args);
    $link = Minn\Runtime\CommentPages::link((string) get_permalink($comment?->comment_post_ID), $page) . '#comment-' . $comment?->comment_ID;
    return apply_filters('get_comment_link', $link, $comment, $args, $page);
}

function get_page_of_comment($comment_id, $args = [])
{
    return Minn\Runtime\CommentPages::pageOf($comment_id, (array) $args);
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

/** Whether comment counts are put off (Runtime\Deferrals); turning it off counts what was. */
function wp_defer_comment_counting($defer = null)
{
    return Deferrals::comments($defer);
}

/** The post a comment by this author at this time is on (the site's time, or "gmt"), as a string; null when there is none (probe admin-terms). */
function comment_exists($comment_author, $comment_date, $timezone = 'blog')
{
    global $wpdb;
    $column = $timezone === 'gmt' ? 'comment_date_gmt' : 'comment_date';
    return $wpdb->get_var($wpdb->prepare("SELECT comment_post_ID FROM {$wpdb->comments} WHERE comment_author = %s AND {$column} = %s", stripslashes((string) $comment_author), stripslashes((string) $comment_date)));
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
    $max = wp_get_comment_fields_max_lengths();
    $limits = ['comment_author' => ['comment_author_column_length', 'Your name is too long.'], 'comment_author_email' => ['comment_author_email_column_length', 'Your email address is too long.'], 'comment_author_url' => ['comment_author_url_column_length', 'Your URL is too long.'], 'comment_content' => ['comment_content_column_length', 'Your comment is too long.']];
    foreach ($limits as $field => [$code, $message]) {
        if (isset($comment_data[$field], $max[$field]) && mb_strlen((string) $comment_data[$field], '8bit') > (int) $max[$field]) {
            return new WP_Error($code, '<strong>Error:</strong> ' . $message, 200);
        }
    }
    return true;
}

/** Each field through its pre_comment_* filter, in the reference's order (the user first, the email last), marked filtered. */
function wp_filter_comment($commentdata)
{
    $map = ['comment_agent' => 'pre_comment_user_agent', 'comment_author' => 'pre_comment_author_name', 'comment_content' => 'pre_comment_content', 'comment_author_IP' => 'pre_comment_user_ip', 'comment_author_url' => 'pre_comment_author_url', 'comment_author_email' => 'pre_comment_author_email'];
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

/**
 * 1, 0, "spam" or "trash" for a comment that may be stored, in the
 * reference's order (contracts/runtime.md "The comment form"): a duplicate,
 * which duplicate_comment_id may name or clear, refuses with 409;
 * check_comment_flood (its legacy callback attaches the database check)
 * and wp_is_comment_flood refuse a flood with 429; then the approval.
 * WP_Error with $wp_error, else wp_die, for a refusal.
 */
function wp_allow_comment($commentdata, $wp_error = false)
{
    $data = (array) $commentdata;
    $field = static fn (string $key): string => (string) wp_unslash($data[$key] ?? '');
    $found = _minn_comments()->duplicateId((int) ($data['comment_post_ID'] ?? 0), $field('comment_author'), $field('comment_author_email'), $field('comment_content'), (int) ($data['user_id'] ?? 0));
    // The id as the database answers it: a string.
    $dupe = apply_filters('duplicate_comment_id', $found === null ? null : (string) $found, $data);
    if ($dupe) {
        do_action('comment_duplicate_trigger', $data);
        $message = apply_filters('comment_duplicate_message', 'Duplicate comment detected; it looks as though you&#8217;ve already said that!');
        return $wp_error ? new WP_Error('comment_duplicate', $message, 409) : wp_die($message, 409);
    }
    do_action('check_comment_flood', $field('comment_author_IP'), $field('comment_author_email'), $field('comment_date_gmt'), $wp_error);
    if (apply_filters('wp_is_comment_flood', false, $field('comment_author_IP'), $field('comment_author_email'), $field('comment_date_gmt'), $wp_error)) {
        $message = apply_filters('comment_flood_message', 'You are posting comments too quickly. Slow down.');
        return $wp_error ? new WP_Error('comment_flood', $message, 429) : wp_die($message, 429);
    }
    return _minn_comment_approval($data);
}

/**
 * @internal a comment's approval as the reference judges it, once as it
 * arrives and again once the pre_comment_* filters have had it: the post's
 * author and a moderator are approved; anyone else's comment passes
 * check_comment or is held, and the disallowed words send it to the trash;
 * pre_comment_approved has the last word.
 */
function _minn_comment_approval(array $data)
{
    $field = static fn (string $key): string => (string) wp_unslash($data[$key] ?? '');
    $userId = (int) ($data['user_id'] ?? 0);
    $post = get_post((int) ($data['comment_post_ID'] ?? 0));
    if ($userId > 0 && (($post !== null && (int) $post->post_author === $userId) || user_can($userId, 'moderate_comments'))) {
        $approved = 1;
    } else {
        $approved = check_comment($field('comment_author'), $field('comment_author_email'), $field('comment_author_url'), $field('comment_content'), $field('comment_author_IP'), $field('comment_agent'), $field('comment_type')) ? 1 : 0;
        if (wp_check_comment_disallowed_list($field('comment_author'), $field('comment_author_email'), $field('comment_author_url'), $field('comment_content'), $field('comment_author_IP'), $field('comment_agent'))) {
            $approved = defined('EMPTY_TRASH_DAYS') && !EMPTY_TRASH_DAYS ? 'spam' : 'trash';
        }
    }
    return apply_filters('pre_comment_approved', $approved, $data);
}

/**
 * A comment from outside, stored the way the comment form stores one:
 * preprocess_comment; the ids, user, parent, address, agent and dates
 * filled in; wp_allow_comment; the pre_comment_* filters; the approval
 * judged again on what they left; wp_insert_comment; comment_post (where
 * the moderator and the post's author are told). The id, or a WP_Error
 * with $wp_error (false without).
 */
function wp_new_comment($commentdata, $wp_error = false)
{
    $data = _minn_comment_defaults((array) apply_filters('preprocess_comment', $commentdata));
    $approved = wp_allow_comment($data, $wp_error);
    if (is_wp_error($approved)) {
        return $wp_error ? $approved : false;
    }
    $data['comment_approved'] = $approved;
    $data = wp_filter_comment($data);
    $data['comment_approved'] = _minn_comment_approval($data);
    if (is_wp_error($data['comment_approved'])) {
        return $wp_error ? $data['comment_approved'] : false;
    }
    $id = wp_insert_comment($data);
    if (!$id) {
        return $wp_error ? new WP_Error('db_insert_error', 'Could not insert comment into the database.') : false;
    }
    do_action('comment_post', (int) $id, $data['comment_approved'], $data);
    return (int) $id;
}

/** @internal what wp_new_comment fills in: integer ids, the user from user_ID, a parent only when it is approved or held, the request's address and agent, the dates now, the type "comment". */
function _minn_comment_defaults(array $data): array
{
    if (isset($data['user_ID'])) {
        $data['user_ID'] = (int) $data['user_ID'];
        $data['user_id'] = $data['user_ID'];
    }
    $data['comment_post_ID'] = (int) ($data['comment_post_ID'] ?? 0);
    $data['user_id'] = (int) ($data['user_id'] ?? 0);
    $parent = (int) ($data['comment_parent'] ?? 0);
    $data['comment_parent'] = $parent > 0 && in_array(wp_get_comment_status($parent), ['approved', 'unapproved'], true) ? $parent : 0;
    $request = Runtime::current()->request;
    $data['comment_author_IP'] = (string) preg_replace('/[^0-9a-fA-F:., ]/', '', (string) ($data['comment_author_IP'] ?? ($request?->remoteAddress ?? '')));
    $data['comment_agent'] = substr((string) ($data['comment_agent'] ?? ($request?->header('user-agent') ?? '')), 0, 254);
    $data['comment_date'] ??= current_time('mysql');
    $data['comment_date_gmt'] ??= current_time('mysql', true);
    $data['comment_type'] = (string) ($data['comment_type'] ?? '') === '' ? 'comment' : $data['comment_type'];
    return $data;
}

/** The legacy callback on check_comment_flood: attaches the database flood check to wp_is_comment_flood, so a plugin that unhooks it switches flood checks off. */
function check_comment_flood_db()
{
    add_filter('wp_is_comment_flood', 'wp_check_comment_flood', 10, 5);
}

/**
 * Whether this commenter commented too recently: an administrator or a
 * moderator never floods; otherwise their last comment in the past hour
 * (by user when signed in, else by address, or by email) goes through
 * comment_flood_filter (wp_throttle_comment_flood: fifteen seconds), and a
 * flood fires comment_flood_trigger.
 */
function wp_check_comment_flood($is_flood, $ip, $email, $date, $avoid_die = false)
{
    if ($is_flood === true || current_user_can('manage_options') || current_user_can('moderate_comments')) {
        return $is_flood === true;
    }
    $now = $date !== '' ? (int) strtotime($date . ' UTC') : time();
    $last = _minn_comments()->lastCommentTime(get_current_user_id(), (string) $ip, (string) $email, gmdate('Y-m-d H:i:s', $now - HOUR_IN_SECONDS));
    if ($last === null || !apply_filters('comment_flood_filter', false, $last, $now)) {
        return false;
    }
    do_action('comment_flood_trigger', $last, $now);
    if ($avoid_die) {
        return true;
    }
    return wp_die(apply_filters('comment_flood_message', 'You are posting comments too quickly. Slow down.'), 429);
}

/** comment_flood_filter's default: fifteen seconds between comments. */
function wp_throttle_comment_flood($block, $time_lastcomment, $time_newcomment)
{
    return $block ? $block : ((int) $time_newcomment - (int) $time_lastcomment) < 15;
}

/**
 * Remembers a signed-out commenter for a year in three cookies (secure
 * when the site is https), or forgets them when they did not consent;
 * comment_cookie_lifetime may change the year.
 */
function wp_set_comment_cookies($comment, $user, $cookies_consent = true)
{
    if ($user instanceof WP_User && $user->exists()) {
        return;
    }
    $hash = defined('COOKIEHASH') ? COOKIEHASH : md5(site_url());
    $secure = parse_url(home_url(), PHP_URL_SCHEME) === 'https';
    $path = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';
    $expires = $cookies_consent === false ? time() - YEAR_IN_SECONDS : time() + (int) apply_filters('comment_cookie_lifetime', YEAR_IN_SECONDS);
    $values = $cookies_consent === false ? [' ', ' ', ' '] : [(string) $comment->comment_author, (string) $comment->comment_author_email, esc_url((string) $comment->comment_author_url)];
    foreach (['comment_author_', 'comment_author_email_', 'comment_author_url_'] as $i => $name) {
        setcookie($name . $hash, $values[$i], ['expires' => $expires, 'path' => $path, 'domain' => $domain, 'secure' => $secure]);
    }
}

/** The comment form's submission (wp-comments-post.php), as Minn\Runtime\CommentForm handles it: the stored comment, or the WP_Error the form answers with. */
function wp_handle_comment_submission($comment_data)
{
    return \Minn\Runtime\CommentForm::submit((array) $comment_data);
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

/**
 * Pages of comments at the page size: one when comments are not paged,
 * top-level comments only while threaded. Asked with nothing, the main
 * query's own count once comments_template has paged it.
 */
function get_comment_pages_count($comments = null, $per_page = null, $threaded = null)
{
    global $wp_query;
    if ($comments === null && $per_page === null && $threaded === null && !empty($wp_query->max_num_comment_pages)) {
        return $wp_query->max_num_comment_pages;
    }
    $comments ??= $wp_query->comments ?? [];
    if (empty($comments)) {
        return 0;
    }
    if (!get_option('page_comments')) {
        return 1;
    }
    $per_page = (int) ($per_page ?? get_query_var('comments_per_page')) ?: (int) get_option('comments_per_page');
    if ($per_page === 0) {
        return 1;
    }
    $threaded ??= get_option('thread_comments');
    $count = $threaded ? (new Walker_Comment())->get_number_of_root_elements($comments) : count($comments);
    return (int) ceil($count / $per_page);
}

/** @internal the arguments wp_list_comments resolves before it picks the comments: no paging unless a page size is given or set */
function _minn_list_comments_args($args): array
{
    $defaults = ['walker' => null, 'max_depth' => '', 'style' => 'ul', 'callback' => null, 'end-callback' => null, 'type' => 'all', 'page' => '', 'per_page' => '', 'avatar_size' => 32, 'reverse_top_level' => null, 'reverse_children' => '', 'format' => current_theme_supports('html5', 'comment-list') ? 'html5' : 'xhtml', 'short_ping' => false, 'echo' => true];
    $args = apply_filters('wp_list_comments_args', wp_parse_args($args, $defaults));
    if ($args['per_page'] === '' && get_option('page_comments')) {
        $args['per_page'] = get_query_var('comments_per_page');
    }
    if (empty($args['per_page'])) {
        $args['per_page'] = 0;
        $args['page'] = 0;
    }
    if ($args['max_depth'] === '' || $args['max_depth'] === null) {
        $args['max_depth'] = get_option('thread_comments') ? (int) get_option('thread_comments_depth') : -1;
    }
    if ($args['reverse_top_level'] === null) {
        $args['reverse_top_level'] = get_option('comment_order') === 'desc';
    }
    return $args;
}

/**
 * The classic comment list: the comments given (or the query's), all or
 * one type, a page of them walked by the theme's walker or Walker_Comment
 * (probe comment-walker). The query's comments are already its page, so
 * they are not paged again, and their links name the page shown.
 */
function wp_list_comments($args = [], $comments = null)
{
    global $wp_query, $comment_alt, $comment_depth, $comment_thread_alt, $in_comment_loop;
    $args = _minn_list_comments_args($args);
    $listed = $comments ?? ($wp_query->comments ?? []);
    if ($comments === null && !empty($wp_query->max_num_comment_pages)) {
        $args['cpage'] = Minn\Runtime\CommentPages::shown(get_query_var('cpage'));
        $args['page'] = 0;
        $args['per_page'] = 0;
    }
    if ($args['type'] !== 'all') {
        $listed = separate_comments($listed)[$args['type']] ?? [];
    }
    if (empty($listed)) {
        return null;
    }
    $args['page'] = (int) ($args['page'] === '' ? get_query_var('cpage') : $args['page']);
    if ($args['page'] === 0 && (int) $args['per_page'] !== 0) {
        $args['page'] = 1;
    }
    [$comment_alt, $comment_thread_alt, $comment_depth, $in_comment_loop] = [0, 0, 1, true];
    $walker = $args['walker'] ?: new Walker_Comment();
    $output = $walker->paged_walk((array) $listed, $args['max_depth'], $args['page'], $args['per_page'], $args);
    $in_comment_loop = false;
    if ($args['echo']) {
        echo $output;
        return null;
    }
    return $output;
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
        $page = Minn\Runtime\CommentPages::link((string) get_permalink($post->ID), Minn\Runtime\CommentPages::shown(get_query_var('cpage')));
        $href = esc_url(add_query_arg(['replytocom' => $comment->comment_ID, 'unapproved' => false, 'moderation-hash' => false], $page)) . '#' . $args['respond_id'];
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

/**
 * The post's comments into the main query, then the theme's comments.php
 * or the engine's own (probe comments-template): the approved ones and the
 * visitor's own held ones, threaded as the settings say. Paged, it is one
 * page of top-level comments with their replies; with none asked for and
 * the newest first, the last page, which becomes the page shown. A post
 * behind a password is the template's to refuse. The template sees
 * $comments and the remembered commenter's details.
 */
function comments_template($file = '/comments.php', $separate_comments = false)
{
    global $wp_query, $withcomments, $post, $comment, $user_ID, $overridden_cpage;
    if (!(is_single() || is_page() || $withcomments) || empty($post)) {
        return;
    }
    $commenter = wp_get_current_commenter();
    [$comment_author, $comment_author_email, $comment_author_url] = [$commenter['comment_author'], $commenter['comment_author_email'], esc_url($commenter['comment_author_url'])];
    $args = apply_filters('comments_template_query_args', _minn_comments_template_args($post));
    $query = new WP_Comment_Query($args);
    $comments = apply_filters('comments_array', _minn_comments_flattened($query->comments, $args), (int) $post->ID);
    $wp_query->comments = $comments;
    $wp_query->comment_count = count($comments);
    $wp_query->max_num_comment_pages = $query->max_num_pages;
    $wp_query->comments_by_type = $separate_comments ? separate_comments($comments) : [];
    $comments_by_type = $wp_query->comments_by_type;
    $overridden_cpage = false;
    if ((string) get_query_var('cpage') === '' && $wp_query->max_num_comment_pages > 1) {
        set_query_var('cpage', get_option('default_comments_page') === 'newest' ? get_comment_pages_count() : 1);
        $overridden_cpage = true;
    }
    if (!defined('COMMENTS_TEMPLATE')) {
        define('COMMENTS_TEMPLATE', true);
    }
    $theme_template = locate_template([ltrim((string) $file, '/')]);
    require apply_filters('comments_template', $theme_template !== '' ? $theme_template : MINN_ENGINE_DIR . '/wp-api/theme-compat/comments.php');
}

/** @internal the query comments_template runs: one page of top-level comments when paged, the last page found by counting them when the newest come first */
function _minn_comments_template_args(WP_Post $post): array
{
    $args = ['orderby' => 'comment_date_gmt', 'order' => 'ASC', 'status' => 'approve', 'post_id' => $post->ID, 'no_found_rows' => false, 'hierarchical' => get_option('thread_comments') ? 'threaded' : false];
    $own = is_user_logged_in() ? get_current_user_id() : wp_get_unapproved_comment_author_email();
    if ($own) {
        $args['include_unapproved'] = [$own];
    }
    if (!get_option('page_comments')) {
        return $args;
    }
    $per_page = (int) get_query_var('comments_per_page') ?: (int) get_option('comments_per_page');
    $page = (int) get_query_var('cpage');
    $args['number'] = $per_page;
    if ($page || get_option('default_comments_page') === 'oldest') {
        $args['offset'] = max(0, $page - 1) * $per_page;
        return $args;
    }
    $count = ['count' => true, 'orderby' => false, 'post_id' => $post->ID, 'status' => 'approve'] + ($args['hierarchical'] ? ['parent' => 0] : []) + array_intersect_key($args, ['include_unapproved' => true]);
    $top = (int) (new WP_Comment_Query())->query(apply_filters('comments_template_top_level_query_args', $count));
    $args['offset'] = ((int) ceil($top / max(1, $per_page)) - 1) * $per_page;
    return $args;
}

/** @internal a threaded query's comments as the walker takes them: each top-level comment, then its replies depth first */
function _minn_comments_flattened(array $comments, array $args): array
{
    if (empty($args['hierarchical'])) {
        return $comments;
    }
    $flat = [];
    foreach ($comments as $top) {
        array_push($flat, $top, ...array_values($top->get_children(['format' => 'flat', 'status' => $args['status'] ?? 'approve', 'orderby' => $args['orderby'] ?? ''])));
    }
    return $flat;
}

/** Comments grouped by type (comment, trackback, pingback, any other type under its own name), pings being trackbacks and pingbacks together. */
function separate_comments(&$comments)
{
    $groups = ['comment' => [], 'trackback' => [], 'pingback' => [], 'pings' => []];
    foreach ((array) $comments as $comment) {
        $type = empty($comment->comment_type) ? 'comment' : $comment->comment_type;
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
    if (get_option('show_comments_cookies_opt_in') && has_action('set_comment_cookies', 'wp_set_comment_cookies')) {
        $fields['cookies'] = _minn_comment_cookies_field();
    }
    return $fields;
}

/** @internal the cookies consent field, checked for a remembered commenter */
function _minn_comment_cookies_field(): string
{
    $consent = empty(wp_get_current_commenter()['comment_author_email']) ? '' : ' checked';
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
        // As the reference writes it: the name, the profile and log-out links, and the required-fields note.
        'logged_in_as' => '<p class="logged-in-as">Logged in as ' . esc_html($user->display_name) . '. <a href="' . get_edit_profile_url() . '">Edit your profile</a>. <a href="' . wp_logout_url(apply_filters('the_permalink', get_permalink($post->ID), $post->ID)) . '">Log out?</a> ' . ($req ? '<span class="required-field-message">Required fields are marked <span class="required">*</span></span>' : '') . '</p>',
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
        // A signed-out reader's fields always offer the consent box, a plugin's own fields included.
        if (!isset($fields['cookies']) && get_option('show_comments_cookies_opt_in') && has_action('set_comment_cookies', 'wp_set_comment_cookies')) {
            $fields['cookies'] = _minn_comment_cookies_field();
        }
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
 * Whether a comment needs no moderation: off when moderation is on; the
 * links counted in the comment as comment_text shows it
 * (comment_max_links_url may recount) against comment_max_links; the
 * moderation words; and, when asked, an earlier approved comment from the
 * same author.
 */
function check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type)
{
    if ((string) get_option('comment_moderation') === '1') {
        return false;
    }
    $shown = (string) apply_filters('comment_text', (string) $comment, null, []);
    $max = (int) get_option('comment_max_links');
    if ($max > 0 && (int) apply_filters('comment_max_links_url', preg_match_all('/<a [^>]*href/i', $shown), $url, $comment) >= $max) {
        return false;
    }
    foreach (explode("\n", (string) get_option('moderation_keys')) as $word) {
        $word = trim($word);
        if ($word !== '' && preg_match('#' . preg_quote($word, '#') . '#iu', $author . ' ' . $email . ' ' . $url . ' ' . $comment . ' ' . $user_ip . ' ' . $user_agent)) {
            return false;
        }
    }
    if ((string) get_option('comment_previously_approved') === '1' && !in_array($comment_type, ['trackback', 'pingback'], true)) {
        return (string) $author !== '' && (string) $email !== '' && _minn_comments()->previouslyApproved((string) $author, (string) $email);
    }
    return true;
}

/** comment_post's first callback: a held comment is the moderator's to see (notify_moderator may say otherwise), sent by wp_notify_moderator. */
function wp_new_comment_notify_moderator($comment_id)
{
    $comment = get_comment($comment_id);
    $notify = apply_filters('notify_moderator', $comment !== null && (string) $comment->comment_approved === '0', (int) $comment_id);
    return $notify ? wp_notify_moderator((int) $comment_id) : false;
}

/** comment_post's second callback: the post's author hears of an approved comment when comments_notify is on (notify_post_author may say otherwise). */
function wp_new_comment_notify_postauthor($comment_id)
{
    $comment = get_comment($comment_id);
    $notify = apply_filters('notify_post_author', get_option('comments_notify'), (int) $comment_id);
    if (!$notify || $comment === null || (string) $comment->comment_approved !== '1') {
        return false;
    }
    return wp_notify_postauthor((int) $comment_id);
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

/** The default on transition_comment_status: the last-modified time of comments is asked again. */
function _clear_modified_cache_on_transition_comment_status($new_status, $old_status)
{
    if ($new_status === 'approved' || $old_status === 'approved') {
        foreach (['server', 'gmt', 'blog'] as $timezone) {
            wp_cache_delete("lastcommentmodified:{$timezone}", 'timeinfo');
        }
    }
}

/** The author email of the held comment ?unapproved= names, when ?moderation-hash= (wp_hash of its GMT date) proves the link came from posting it in the last ten minutes; "" otherwise. */
function wp_get_unapproved_comment_author_email()
{
    $query = Runtime::current()->request?->query ?? [];
    $comment = isset($query['unapproved'], $query['moderation-hash']) ? get_comment((int) $query['unapproved']) : null;
    if (!$comment instanceof WP_Comment || (string) $comment->comment_approved !== '0' || (int) strtotime($comment->comment_date_gmt . ' UTC') + 600 <= time()) {
        return '';
    }
    return hash_equals(wp_hash($comment->comment_date_gmt), (string) $query['moderation-hash']) ? (string) $comment->comment_author_email : '';
}

/**
 * comment_form's default: for a user who may post unfiltered HTML, the
 * nonce wp_handle_comment_submission checks before leaving their comment
 * unfiltered, named so it is sent only from the page itself (an inline
 * script renames it outside a frame).
 */
function wp_comment_form_unfiltered_html_nonce()
{
    $post = get_post();
    if ($post === null || !current_user_can('unfiltered_html')) {
        return;
    }
    wp_nonce_field('unfiltered-html-comment_' . $post->ID, '_wp_unfiltered_html_comment_disabled', false);
    wp_print_inline_script_tag("(function(){if(window===window.parent){document.getElementById('_wp_unfiltered_html_comment_disabled').name='_wp_unfiltered_html_comment';}})();\n//# sourceURL=wp_comment_form_unfiltered_html_nonce");
}

/** A comment's first twenty words (comment_excerpt_length), tags and line breaks gone; "Password protected" on a post behind a password. */
function get_comment_excerpt($comment_id = 0)
{
    $comment = get_comment($comment_id);
    $text = post_password_required($comment->comment_post_ID) ? __('Password protected') : strip_tags(str_replace(["\n", "\r"], ' ', $comment->comment_content));
    $length = (int) apply_filters('comment_excerpt_length', (int) _x('20', 'comment_excerpt_length'));
    return apply_filters('get_comment_excerpt', wp_trim_words($text, $length, '&hellip;'), $comment->comment_ID, $comment);
}

function comment_excerpt($comment_id = 0)
{
    $comment = get_comment($comment_id);
    echo apply_filters('comment_excerpt', get_comment_excerpt($comment), $comment->comment_ID);
}

/** The comment statuses by name, as the moderation screens label them. */
function get_comment_statuses()
{
    return ['hold' => __('Unapproved'), 'approve' => _x('Approved', 'comment status'), 'spam' => _x('Spam', 'comment status'), 'trash' => _x('Trash', 'comment status')];
}

/** A comment author's email address, through get_comment_author_email. */
function get_comment_author_email($comment_id = 0)
{
    $comment = get_comment($comment_id);
    return apply_filters('get_comment_author_email', $comment?->comment_author_email, $comment?->comment_ID ?? $comment_id, $comment);
}

/** Prints a comment author's email address, through author_email. */
function comment_author_email($comment_id = 0)
{
    $comment = get_comment($comment_id);
    echo apply_filters('author_email', get_comment_author_email($comment), $comment?->comment_ID);
}

/** A mailto link to a comment's author (the address scrambled by antispambot), its text the address or the one given; "" without an address. */
function get_comment_author_email_link($link_text = '', $before = '', $after = '', $comment = null)
{
    $comment = get_comment($comment);
    $email = apply_filters('comment_email', $comment?->comment_author_email, $comment);
    if (empty($email) || $email === '@') {
        return '';
    }
    $email = antispambot($email);
    return $before . sprintf('<a href="%1$s">%2$s</a>', esc_url('mailto:' . $email), esc_html($link_text !== '' ? $link_text : $email)) . $after;
}

/** Prints get_comment_author_email_link. */
function comment_author_email_link($link_text = '', $before = '', $after = '', $comment = null)
{
    echo get_comment_author_email_link($link_text, $before, $after, $comment);
}

/** Prints the link that cancels a reply. */
function cancel_comment_reply_link($link_text = '')
{
    echo get_cancel_comment_reply_link($link_text);
}

/** Comments keep no cache here to fill. */
function update_comment_cache($comments, $update_meta_cache = true)
{
}
