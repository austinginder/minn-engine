<?php
/** Comments: reads, counts, the writers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Runtime\Deferrals;
use Minn\Content\CommentClasses;
use Minn\Content\Comments;
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
    return Minn\Runtime\Records::comment($comment, $output);
}

function get_comments($args = '')
{
    return Minn\Runtime\CommentReads::query($args);
}

function get_comments_number($post = 0)
{
    return Minn\Runtime\PostTags::commentsNumber($post);
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
    return Minn\Runtime\CommentReads::counts($post_id);
}

function wp_insert_comment($commentdata)
{
    return Minn\Runtime\CommentWrites::insert($commentdata);
}

function wp_update_comment_count($post_id, $do_deferred = false)
{
    return Minn\Runtime\CommentWrites::updateCount($post_id, $do_deferred);
}

/** Recounts a post's approved comments now, telling plugins the old and new counts. */
function wp_update_comment_count_now($post_id)
{
    return Minn\Runtime\CommentWrites::updateCountNow($post_id);
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
    return Minn\Runtime\CommentWrites::update($commentarr, $wp_error);
}

function wp_set_comment_status($comment_id, $comment_status, $wp_error = false)
{
    return Minn\Runtime\CommentWrites::setStatus($comment_id, $comment_status, $wp_error);
}

/** What plugins are told when a comment changes status; a comment that keeps its status hears only comment_{status}_{type}. */
function wp_transition_comment_status($new_status, $old_status, $comment)
{
    Minn\Runtime\CommentWrites::transitionStatus($new_status, $old_status, $comment);
}

/** Lets go of comments' cached rows and meta, telling plugins about each. */
function clean_comment_cache($ids)
{
    Minn\Runtime\CommentWrites::cleanCache($ids);
}

/** The trash takes a post's comments with it: each becomes post-trashed, and their statuses are kept to give back. */
function wp_trash_post_comments($post = null)
{
    return Minn\Runtime\CommentWrites::trashPostComments($post);
}

/** A post back from the trash gives its comments their statuses back. */
function wp_untrash_post_comments($post = null)
{
    return Minn\Runtime\CommentWrites::untrashPostComments($post);
}

function wp_delete_comment($comment_id, $force_delete = false)
{
    return Minn\Runtime\CommentWrites::delete($comment_id, $force_delete);
}

function wp_trash_comment($comment_id)
{
    return Minn\Runtime\CommentWrites::trash($comment_id);
}

function wp_untrash_comment($comment_id)
{
    return Minn\Runtime\CommentWrites::untrash($comment_id);
}

function wp_spam_comment($comment_id)
{
    return Minn\Runtime\CommentWrites::spam($comment_id);
}

function wp_unspam_comment($comment_id)
{
    return Minn\Runtime\CommentWrites::unspam($comment_id);
}

function wp_count_terms($args = [], $deprecated = '')
{
    return Minn\Runtime\TermLookup::count($args, $deprecated);
}

function get_comment_author($comment_id = 0)
{
    return Minn\Runtime\CommentTags::author($comment_id);
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
    return Minn\Runtime\CommentReads::lastModified($timezone);
}

/** A comment's link, at the page of comments it is on (Minn\Runtime\CommentPages), through get_comment_link. */
function get_comment_link($comment = null, $args = [])
{
    return Minn\Runtime\CommentTags::link($comment, $args);
}

function get_page_of_comment($comment_id, $args = [])
{
    return Minn\Runtime\CommentPages::pageOf($comment_id, (array) $args);
}

function comments_open($post = null)
{
    return Minn\Runtime\PostTags::commentsOpen($post);
}

function pings_open($post = null)
{
    return Minn\Runtime\PostTags::pingsOpen($post);
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
    return ['approved' => $counts->approved, 'awaiting_moderation' => $counts->moderated, 'spam' => $counts->spam, 'trash' => $counts->trash, 'post-trashed' => $counts->{'post-trashed'}, 'all' => $counts->all, 'total_comments' => $counts->total_comments];
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
    return Minn\Runtime\CommentTags::authorUrl($comment_id);
}

/** Prints a comment author's URL, through comment_url. */
function comment_author_url($comment_id = 0)
{
    $comment = get_comment($comment_id);
    echo apply_filters('comment_url', get_comment_author_url($comment), $comment->comment_ID ?? '');
}

/** A link to a comment author's URL; its text the one given, or the URL without its http:// and http://www. and one trailing slash. */
function get_comment_author_url_link($link_text = '', $before = '', $after = '', $comment = 0)
{
    $url = get_comment_author_url($comment);
    $text = $link_text;
    if ($text === '') {
        $text = str_replace(['http://www.', 'http://'], '', $url);
        $text = str_ends_with($text, '/') ? substr($text, 0, -1) : $text;
    }
    return apply_filters('get_comment_author_url_link', $before . '<a href="' . $url . '" rel="external">' . $text . '</a>' . $after);
}

/** Prints get_comment_author_url_link. */
function comment_author_url_link($link_text = '', $before = '', $after = '', $comment = 0)
{
    echo get_comment_author_url_link($link_text, $before, $after, $comment);
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
    return Minn\Runtime\CommentChecks::maxLengths($comment_data);
}

/** Each field through its pre_comment_* filter, in the reference's order (the user first, the email last), marked filtered. */
function wp_filter_comment($commentdata)
{
    return Minn\Runtime\CommentChecks::filter($commentdata);
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
    return Minn\Runtime\CommentChecks::allow($commentdata, $wp_error);
}

/**
 * A comment's approval as the reference judges it from its data (probe
 * comment-helpers), once as it arrives and again once the pre_comment_*
 * filters have had it: the post's author and a moderator are approved;
 * anyone else's comment passes check_comment or is held, and the disallowed
 * words send it to the trash; pre_comment_approved has the last word. The
 * data may be slashed.
 */
function wp_check_comment_data($comment_data)
{
    return Minn\Runtime\CommentChecks::approval($comment_data);
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
    return Minn\Runtime\CommentChecks::create($commentdata, $wp_error);
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
    return Minn\Runtime\CommentChecks::status($comment_id);
}

/** The remembered commenter from the comment_author_* cookies, or the signed-in user. */
function wp_get_current_commenter()
{
    return Minn\Runtime\CommentChecks::currentCommenter();
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
    return Minn\Runtime\CommentTags::time($format, $gmt, $translate, $comment_id);
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
        // The rel words through comment_author_link_rel, outside links external and nofollow too (probe filter-inputs-2).
        $rel = esc_attr(implode(' ', (array) apply_filters('comment_author_link_rel', str_starts_with($url, home_url()) ? ['ugc'] : ['ugc', 'external', 'nofollow'], $comment)));
        $link = '<a href="' . $url . '" class="url"' . ($rel !== '' ? ' rel="' . $rel . '"' : '') . '>' . $author . '</a>';
    }
    return apply_filters('get_comment_author_link', $link, $author, (string) ($comment->comment_ID ?? ''));
}

/** The hidden fields a comment form posts: the post and the comment replied to. */
function get_comment_id_fields($post = null)
{
    $post = get_post($post);
    $post_id = $post ? (int) $post->ID : 0;
    $reply_to = _get_comment_reply_id($post_id);
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
    return Minn\Runtime\CommentTags::postLink($post);
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
    $args = wp_parse_args($args, ['add_below' => 'comment', 'respond_id' => 'respond', 'reply_text' => _x('Reply', 'verb'), 'reply_to_text' => __('Reply to %s'), 'login_text' => __('Log in to Reply'), 'max_depth' => 0, 'depth' => 0, 'before' => '', 'after' => '', 'show_reply_to_text' => false]);
    if ((int) $args['depth'] === 0 || (int) $args['max_depth'] <= (int) $args['depth']) {
        return null;
    }
    $comment = get_comment($comment);
    $post = get_post($post ?? ($comment->comment_post_ID ?? null));
    if (!$comment || !$post || !comments_open($post->ID)) {
        return false;
    }
    $args = apply_filters('comment_reply_link_args', $args, $comment, $post);
    if (get_option('comment_registration') && !is_user_logged_in()) {
        $link = sprintf('<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>', esc_url(wp_login_url(get_permalink())), $args['login_text']);
    } else {
        $author = get_comment_author($comment);
        $label = sprintf($args['reply_to_text'], $author);
        $page = Minn\Runtime\CommentPages::link((string) get_permalink($post->ID), Minn\Runtime\CommentPages::shown(get_query_var('cpage')));
        $href = esc_url(add_query_arg(['replytocom' => $comment->comment_ID, 'unapproved' => false, 'moderation-hash' => false], $page)) . '#' . $args['respond_id'];
        $link = sprintf('<a rel="nofollow" class="comment-reply-link" href="%s" data-commentid="%d" data-postid="%d" data-belowelement="%s" data-respondelement="%s" data-replyto="%s" aria-label="%s">%s</a>', $href, $comment->comment_ID, $post->ID, $args['add_below'] . '-' . $comment->comment_ID, $args['respond_id'], esc_attr($label), esc_attr($label), $args['show_reply_to_text'] ? $label : $args['reply_text']);
    }
    return apply_filters('comment_reply_link', $args['before'] . $link . $args['after'], $args, $comment, $post);
}

function comment_reply_link($args = [], $comment = null, $post = null)
{
    echo get_comment_reply_link($args, $comment, $post);
}

/** The link that opens a post's comment form (a sign-in link when commenting needs an account), or false while its comments are closed. */
function get_post_reply_link($args = [], $post = null)
{
    $args = wp_parse_args($args, ['add_below' => 'post', 'respond_id' => 'respond', 'reply_text' => 'Leave a Comment', 'login_text' => 'Log in to leave a Comment', 'before' => '', 'after' => '']);
    $post = get_post($post);
    if (!$post || !comments_open($post->ID)) {
        return false;
    }
    if (get_option('comment_registration') && !is_user_logged_in()) {
        $link = sprintf('<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>', esc_url(wp_login_url()), $args['login_text']);
    } else {
        $move = sprintf('return addComment.moveForm( "%1$s-%2$d", "0", "%3$s", "%2$d" )', $args['add_below'], $post->ID, $args['respond_id']);
        $link = sprintf("<a rel='nofollow' class='comment-reply-link' href='%s' onclick='%s'>%s</a>", get_permalink($post->ID) . '#' . $args['respond_id'], $move, $args['reply_text']);
    }
    return apply_filters('post_comments_link', $args['before'] . $link . $args['after'], $post);
}

/** Prints get_post_reply_link. */
function post_reply_link($args = [], $post = null)
{
    echo get_post_reply_link($args, $post);
}

/** The comment ?replytocom= names: a positive number naming an approved comment on the post, else 0. */
function _get_comment_reply_id($post = null)
{
    $post = get_post($post);
    $asked = $_GET['replytocom'] ?? null;
    if (!$post || !is_numeric($asked) || (int) $asked <= 0) {
        return 0;
    }
    $comment = get_comment((int) $asked);
    if (!$comment || (int) $comment->comment_post_ID !== (int) $post->ID || (string) $comment->comment_approved !== '1') {
        return 0;
    }
    return (int) $asked;
}

/** Prints the comment's type: the comment, trackback or pingback text given, "Comment" etc. by default. */
function comment_type($comment_text = false, $trackback_text = false, $pingback_text = false)
{
    echo match (get_comment_type()) {
        'trackback' => $trackback_text === false ? 'Trackback' : $trackback_text,
        'pingback' => $pingback_text === false ? 'Pingback' : $pingback_text,
        default => $comment_text === false ? 'Comment' : $comment_text,
    };
}

/** The address that takes trackbacks for the current post: trackback/ under a pretty permalink, the siteurl option's wp-trackback.php?p= otherwise. */
function get_trackback_url()
{
    $url = get_option('permalink_structure')
        ? trailingslashit(get_permalink()) . user_trailingslashit('trackback', 'single_trackback')
        : get_option('siteurl') . '/wp-trackback.php?p=' . get_the_ID();
    return apply_filters('trackback_url', $url);
}

/** Prints the trackback address; given false it returns it instead (deprecated since 2.5.0). */
function trackback_url($deprecated_echo = true)
{
    if ($deprecated_echo !== true) {
        _deprecated_argument(__FUNCTION__, '2.5.0', 'Call get_trackback_url() for the address without printing it.');
    }
    if (!$deprecated_echo) {
        return get_trackback_url();
    }
    echo get_trackback_url();
}

/** Prints the current post's trackback discovery block (RDF), except to the W3C validator. */
function trackback_rdf($deprecated = '')
{
    if ($deprecated !== '') {
        _deprecated_argument(__FUNCTION__, '2.5.0');
    }
    if (stripos((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'W3C_Validator') !== false) {
        return;
    }
    echo "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n\t\t\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n\t\t\txmlns:trackback=\"http://madskills.com/public/xml/rss/module/trackback/\">\n\t\t<rdf:Description rdf:about=\"";
    the_permalink();
    echo "\"\n    dc:identifier=\"";
    the_permalink();
    echo "\"\n    dc:title=\"" . the_title_attribute(['echo' => false]) . "\"\n    trackback:ping=\"";
    trackback_url();
    echo "\" />\n</rdf:RDF>";
}

/** The link that cancels a reply: the page without its replytocom, hidden unless it names a comment the post takes replies to. */
function get_cancel_comment_reply_link($link_text = '', $post = null)
{
    $link_text = $link_text === '' ? 'Click here to cancel reply.' : $link_text;
    $style = _get_comment_reply_id($post) > 0 ? '' : ' style="display:none;"';
    $link = esc_html(remove_query_arg(['replytocom', 'unapproved', 'moderation-hash'])) . '#respond';
    $markup = '<a rel="nofollow" id="cancel-comment-reply-link" href="' . $link . '"' . $style . '>' . $link_text . '</a>';
    return apply_filters('cancel_comment_reply_link', $markup, $link, $link_text);
}

/** The form heading: a reply to a named comment, or the plain title. */
function comment_form_title($no_reply_text = false, $reply_text = false, $link_to_parent = true, $post = null)
{
    $no_reply_text = $no_reply_text === false ? __('Leave a Reply') : $no_reply_text;
    $reply_text = $reply_text === false ? __('Leave a Reply to %s') : $reply_text;
    $reply_id = _get_comment_reply_id($post);
    $reply_to = $reply_id > 0 ? get_comment($reply_id) : null;
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
    $required = $req ? ' <span class="required">' . esc_html__('*') . '</span>' : '';
    $attr = $req ? ' required' : '';
    $fields = [
        'author' => '<p class="comment-form-author"><label for="author">' . __('Name') . $required . '</label> <input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" maxlength="245" autocomplete="name"' . $attr . ' /></p>',
        'email' => '<p class="comment-form-email"><label for="email">' . __('Email') . $required . '</label> <input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"' . $attr . ' /></p>',
        'url' => '<p class="comment-form-url"><label for="url">' . __('Website') . '</label> <input id="url" name="url" type="url" value="' . esc_attr($commenter['comment_author_url']) . '" size="30" maxlength="200" autocomplete="url" /></p>',
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
    return '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' /> <label for="wp-comment-cookies-consent">' . __('Save my name, email, and website in this browser for the next time I comment.') . '</label></p>';
}

/** @internal the comment form's arguments after the defaults and filters */
function _minn_comment_form_args($args, WP_Post $post): array
{
    $user = wp_get_current_user();
    $req = (bool) get_option('require_name_email');
    $block = (bool) Runtime::current()->get('block_theme', false);
    $custom_button = is_array($args) && (isset($args['submit_button']) || isset($args['submit_field']));
    $marked = $req ? '<span class="required-field-message">' . sprintf(__('Required fields are marked %s'), '<span class="required">' . esc_html__('*') . '</span>') . '</span>' : '';
    $defaults = [
        'fields' => apply_filters('comment_form_default_fields', _minn_comment_form_fields()),
        'comment_field' => '<p class="comment-form-comment"><label for="comment">' . _x('Comment', 'noun') . ' <span class="required">' . esc_html__('*') . '</span></label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required></textarea></p>',
        'must_log_in' => '<p class="must-log-in">' . sprintf(__('You must be <a href="%s">logged in</a> to post a comment.'), esc_url(wp_login_url(get_permalink($post->ID)))) . '</p>',
        // As the reference writes it: the name, the profile and log-out links, and the required-fields note.
        'logged_in_as' => '<p class="logged-in-as">' . sprintf(__('Logged in as %1$s. <a href="%2$s">Edit your profile</a>. <a href="%3$s">Log out?</a>'), esc_html($user->display_name), get_edit_profile_url(), wp_logout_url(apply_filters('the_permalink', get_permalink($post->ID), $post->ID))) . ' ' . $marked . '</p>',
        'comment_notes_before' => '<p class="comment-notes"><span id="email-notes">' . __('Your email address will not be published.') . '</span> ' . $marked . '</p>',
        'comment_notes_after' => '',
        'action' => site_url('/wp-comments-post.php'),
        'id_form' => 'commentform',
        'id_submit' => 'submit',
        'class_container' => 'comment-respond',
        'class_form' => 'comment-form',
        'class_submit' => $block && !$custom_button ? 'submit wp-block-button__link wp-element-button' : 'submit',
        'name_submit' => 'submit',
        'title_reply' => __('Leave a Reply'),
        'title_reply_to' => __('Leave a Reply to %s'),
        'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
        'title_reply_after' => '</h3>',
        'cancel_reply_before' => ' <small>',
        'cancel_reply_after' => '</small>',
        'cancel_reply_link' => __('Cancel reply'),
        'label_submit' => __('Post Comment'),
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
    return Minn\Runtime\CommentChecks::defaultStatus($post_type, $comment_type);
}

function wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent)
{
    _deprecated_function(__FUNCTION__, '5.5.0', 'wp_check_comment_disallowed_list()');
    return wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent);
}

/** True when any line of the disallowed_keys option appears in the comment's fields (a word match, or an IP prefix). */
function wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent)
{
    return Minn\Runtime\CommentChecks::disallowed($author, $email, $url, $comment, $user_ip, $user_agent);
}

function _close_comments_for_old_post($open, $post_id)
{
    $post = get_post($post_id ?: null);
    $closer = new Minn\Runtime\CommentCloser((bool) get_option('close_comments_for_old_posts'), (int) get_option('close_comments_days_old'));
    // The post's own columns: its type and date are all the rule reads (to_array would fetch its terms besides).
    return $closer->open((bool) $open, $post instanceof WP_Post ? get_object_vars($post) : null, time());
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
    return Minn\Runtime\CommentChecks::passes($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type);
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
    // With no comments yet the link goes to the form, through respond_link; the anchor's own attributes come
    // through comments_popup_link_attributes (probe filter-inputs-2).
    $href = (int) $number === 0 ? (string) apply_filters('respond_link', get_permalink() . '#respond', get_the_ID()) : get_comments_link();
    $attributes = (string) apply_filters('comments_popup_link_attributes', '');
    if ($zero === false && $one === false && $more === false) {
        $text = get_comments_number_text();
        $text .= '<span class="screen-reader-text"> on ' . get_the_title() . '</span>';
        echo '<a href="' . esc_url($href) . '"' . ($attributes !== '' ? ' ' . $attributes : '') . '>' . $text . '</a>';
        return;
    }
    echo Minn\Front\ListingLinks::anchor(
        esc_url($href),
        ($css_class !== '' ? 'class="' . esc_attr($css_class) . '" ' : '') . $attributes,
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
    return Minn\Runtime\CommentChecks::fieldMaxLengths();
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
/**
 * The address of the reader whose held comment the page may show (probe
 * comment-remaining): from a held comment's link in $_GET (its hash right,
 * under ten minutes old), or else from the commenter's cookie.
 */
function wp_get_unapproved_comment_author_email()
{
    return Minn\Runtime\CommentChecks::unapprovedEmail();
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

/** Queues comments so the first read of one's meta loads them all. */
function wp_lazyload_comment_meta(array $comment_ids)
{
    wp_metadata_lazyloader()->queue_objects('comment', $comment_ids);
}

function update_comment_cache($comments, $update_meta_cache = true)
{
}

/** Comments keep no cache here to prime. */
function _prime_comment_caches($comment_ids, $update_meta_cache = true)
{
}

/** Adds the comments exporter to the privacy tools' list (wp_privacy_personal_data_exporters). */
function wp_register_comment_personal_data_exporter($exporters)
{
    $exporters['wordpress-comments'] = ['exporter_friendly_name' => __('WordPress Comments'), 'callback' => 'wp_comments_personal_data_exporter'];
    return $exporters;
}

/** Adds the comments eraser to the privacy tools' list (wp_privacy_personal_data_erasers). */
function wp_register_comment_personal_data_eraser($erasers)
{
    $erasers['wordpress-comments'] = ['eraser_friendly_name' => __('WordPress Comments'), 'callback' => 'wp_comments_personal_data_eraser'];
    return $erasers;
}

/**
 * A person's comments for a personal data export (probe privacy), 500 a
 * page in id order, held ones included: each comment's author, address,
 * link, IP, browser, local date, content and where it is, a field without a
 * value left out. A page before the first holds nothing.
 */
function wp_comments_personal_data_exporter($email_address, $page = 1)
{
    $number = 500;
    if ((int) $page < 1) {
        return ['data' => [], 'done' => true];
    }
    $comments = get_comments(['author_email' => $email_address, 'number' => $number, 'paged' => (int) $page, 'orderby' => 'comment_ID', 'order' => 'ASC', 'update_comment_meta_cache' => false]);
    $items = [];
    foreach ($comments as $comment) {
        $link = get_comment_link($comment);
        $items[] = Minn\Content\PersonalData::item('comments', __('Comments'), __('User&#8217;s comment data.'), 'comment-' . $comment->comment_ID, [
            __('Comment Author') => $comment->comment_author,
            __('Comment Author Email') => $comment->comment_author_email,
            __('Comment Author URL') => $comment->comment_author_url,
            __('Comment Author IP') => $comment->comment_author_IP,
            __('Comment Author User Agent') => $comment->comment_agent,
            __('Comment Date') => $comment->comment_date,
            __('Comment Content') => $comment->comment_content,
            __('Comment URL') => '<a href="' . esc_url($link) . '" target="_blank">' . esc_html($link) . '</a>',
        ]);
    }
    return ['data' => $items, 'done' => count($comments) < $number];
}

/**
 * Anonymises a person's comments for a personal data erasure (probe
 * privacy), 500 a page: the author becomes "Anonymous", the address, link
 * and browser go, the IP loses its tail and the account link is cut; the
 * content stays. A comment wp_anonymize_comment answers with anything but
 * true is kept, and its message (or the default) reported. The rows are
 * written directly, so edit_comment does not fire.
 */
function wp_comments_personal_data_eraser($email_address, $page = 1)
{
    global $wpdb;
    $result = ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
    if (empty($email_address)) {
        return $result;
    }
    $number = 500;
    $comments = get_comments(['author_email' => $email_address, 'number' => $number, 'paged' => (int) $page, 'orderby' => 'comment_ID', 'order' => 'ASC', 'update_comment_meta_cache' => false]);
    foreach ($comments as $comment) {
        $anonymized = ['comment_agent' => '', 'comment_author' => __('Anonymous'), 'comment_author_email' => '', 'comment_author_IP' => wp_privacy_anonymize_data('ip', $comment->comment_author_IP), 'comment_author_url' => '', 'user_id' => 0];
        $answer = apply_filters('wp_anonymize_comment', true, $comment, $anonymized);
        if ($answer !== true) {
            $result['items_retained'] = true;
            $result['messages'][] = is_string($answer) && $answer !== '' ? $answer : sprintf(__('Comment %d contains personal data but could not be anonymized.'), $comment->comment_ID);
            continue;
        }
        if ($wpdb->update($wpdb->comments, $anonymized, ['comment_ID' => $comment->comment_ID])) {
            $result['items_removed'] = true;
        }
        clean_comment_cache($comment->comment_ID);
    }
    $result['done'] = count($comments) < $number;
    return $result;
}

/** The commenter cookies a returning visitor sends, made safe in $_COOKIE: each through its pre_comment_author_* filter, unslashed and escaped for an attribute. */
function sanitize_comment_cookies()
{
    foreach (['comment_author_' => 'pre_comment_author_name', 'comment_author_email_' => 'pre_comment_author_email', 'comment_author_url_' => 'pre_comment_author_url'] as $prefix => $hook) {
        $key = $prefix . COOKIEHASH;
        if (isset($_COOKIE[$key])) {
            $_COOKIE[$key] = esc_attr(wp_unslash(apply_filters($hook, $_COOKIE[$key])));
        }
    }
}

/** Marks the comments as changed, so cached comment queries are asked again. */
function wp_cache_set_comments_last_changed()
{
    wp_cache_set_last_changed('comment');
}

/**
 * Closes comments and pings on the post a single query found once it is
 * older than the Discussion setting allows (on the_posts): only the first
 * post, only a type close_comments_for_post_types lists (posts alone by
 * default), and only on the objects handed over.
 */
function _close_comments_for_old_posts($posts, $query)
{
    if (empty($posts) || !$query instanceof WP_Query || !$query->is_singular() || !($posts[0] ?? null) instanceof WP_Post || !get_option('close_comments_for_old_posts')) {
        return $posts;
    }
    $closer = new Minn\Runtime\CommentCloser(true, (int) get_option('close_comments_days_old'));
    if ($closer->closes((string) $posts[0]->post_type, (string) $posts[0]->post_date_gmt, (array) apply_filters('close_comments_for_post_types', ['post']), time())) {
        $posts[0]->comment_status = 'closed';
        $posts[0]->ping_status = 'closed';
    }
    return $posts;
}

/**
 * Gives comments stored without a type the type "comment", newest first, a
 * batch at a time (wp_update_comment_type_batch_size, 100; on
 * wp_update_comment_type_batch). A batch that changed some schedules the
 * next; one that finds none marks the update finished.
 */
function _wp_batch_update_comment_type()
{
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type = '' ORDER BY comment_ID DESC LIMIT %d", (int) apply_filters('wp_update_comment_type_batch_size', 100)));
    if (!$ids) {
        update_option('finished_updating_comment_type', true);
        return;
    }
    $wpdb->query("UPDATE {$wpdb->comments} SET comment_type = 'comment' WHERE comment_ID IN (" . implode(',', array_map('intval', $ids)) . ')');
    clean_comment_cache(array_map('intval', $ids));
    wp_schedule_single_event(time() + 2 * MINUTE_IN_SECONDS, 'wp_update_comment_type_batch');
}

/** Schedules the comment-type update while it is unfinished and not already scheduled (on admin_init). */
function _wp_check_for_scheduled_update_comment_type()
{
    if (!get_option('finished_updating_comment_type') && !wp_next_scheduled('wp_update_comment_type_batch')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'wp_update_comment_type_batch');
    }
}

/** Takes the block editor's note markers out of a rendered block, keeping what they wrapped (on render_block). */
function wp_strip_inline_note_markers($block_content)
{
    return Minn\Content\NoteMarkers::strip((string) $block_content);
}

/** Tells a post's author of a new note made over the REST API (on rest_insert_comment) while notes notify (wp_notes_notify); other comments are told elsewhere. */
function wp_new_comment_via_rest_notify_postauthor($comment)
{
    if ($comment instanceof WP_Comment && $comment->comment_type === 'note' && get_option('wp_notes_notify')) {
        wp_new_comment_notify_postauthor((int) $comment->comment_ID);
    }
}

/** Mails a user that a note mentions them: who wrote it, on which post, what it says, and where to edit the post. True when the mail went. */
function wp_send_note_notification($user, $comment, $post)
{
    if (!$user instanceof WP_User || !$comment instanceof WP_Comment || !$post instanceof WP_Post) {
        return false;
    }
    $switched = switch_to_user_locale($user->ID);
    $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
    $subject = sprintf(__('[%1$s] You were mentioned in a note on "%2$s"'), $site, $post->post_title);
    $message = sprintf(__('%1$s mentioned you in a note on "%2$s".'), $comment->comment_author, $post->post_title) . "\n\n" . $comment->comment_content . "\n\n" . sprintf(__('Edit This: %s'), admin_url('post.php?post=' . $post->ID . '&action=edit'));
    $sent = wp_mail($user->user_email, $subject, $message, 'Content-Type: text/plain; charset="UTF-8"');
    if ($switched) {
        restore_previous_locale();
    }
    return $sent;
}

/**
 * @internal The form a post-comments-form block renders: comment_form()'s
 * markup, with comment-reply enqueued while replies are threaded (the reply
 * links move this form), as the reference's block enqueues it.
 */
function _minn_post_comments_form(int $postId): string
{
    if (get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
    ob_start();
    comment_form([], $postId);
    return (string) ob_get_clean();
}
