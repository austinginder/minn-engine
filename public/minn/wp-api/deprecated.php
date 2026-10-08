<?php
/**
 * The reference's deprecated functions (wp-includes/deprecated.php), outside
 * block supports: each reports its deprecation (function, replacement,
 * version, as the reference names them) and then does what its replacement
 * does, or the little it still does there. Behaviour from
 * contracts/fixtures/api/deprecated.json. The deprecated pluggable functions
 * live in pluggable.php, so a plugin's own still wins.
 */

// The author in the loop: one meta field each, returned or printed.

function get_the_author_description()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'description\')');
    return get_the_author_meta('description');
}

function the_author_description()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'description\')');
    the_author_meta('description');
}

function get_the_author_login()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'login\')');
    return get_the_author_meta('login');
}

function the_author_login()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'login\')');
    the_author_meta('login');
}

function get_the_author_firstname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'first_name\')');
    return get_the_author_meta('first_name');
}

function the_author_firstname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'first_name\')');
    the_author_meta('first_name');
}

function get_the_author_lastname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'last_name\')');
    return get_the_author_meta('last_name');
}

function the_author_lastname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'last_name\')');
    the_author_meta('last_name');
}

function get_the_author_nickname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'nickname\')');
    return get_the_author_meta('nickname');
}

function the_author_nickname()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'nickname\')');
    the_author_meta('nickname');
}

function get_the_author_url()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'url\')');
    return get_the_author_meta('url');
}

function the_author_url()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'url\')');
    the_author_meta('url');
}

function get_the_author_icq()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'icq\')');
    return get_the_author_meta('icq');
}

function the_author_icq()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'icq\')');
    the_author_meta('icq');
}

function get_the_author_aim()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'aim\')');
    return get_the_author_meta('aim');
}

function the_author_aim()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'aim\')');
    the_author_meta('aim');
}

function get_the_author_yim()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'yim\')');
    return get_the_author_meta('yim');
}

function the_author_yim()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'yim\')');
    the_author_meta('yim');
}

function get_the_author_msn()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'msn\')');
    return get_the_author_meta('msn');
}

function the_author_msn()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'msn\')');
    the_author_meta('msn');
}

function get_the_author_ID()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'ID\')');
    return get_the_author_meta('ID');
}

function the_author_ID()
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'the_author_meta(\'ID\')');
    the_author_meta('ID');
}

function get_author_name($auth_id = false)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_the_author_meta(\'display_name\')');
    return get_the_author_meta('display_name', $auth_id);
}

/** The author archive's address, printed too when display is on. */
function get_author_link($display, $author_id, $author_nicename = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_author_posts_url()');
    $link = get_author_posts_url($author_id, $author_nicename);
    if ($display) {
        echo $link;
    }
    return $link;
}

/** The author's feed address, printed too when display is on. */
function get_author_rss_link($display = false, $author_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'get_author_feed_link()');
    $link = get_author_feed_link($author_id);
    if ($display) {
        echo $link;
    }
    return $link;
}

/** A field of the user with that login (the author in the loop when none is named). */
function get_profile($field, $user = false)
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'get_the_author_meta()');
    if ($user) {
        $user = get_user_by('login', $user)->ID ?? null;
    }
    return get_the_author_meta($field, $user);
}

function get_usernumposts($userid)
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'count_user_posts()');
    return count_user_posts($userid);
}

/**
 * A user's meta: the one value stored under a key (a list when there are
 * several, '' when there are none), or every value they have, keys dropped.
 */
function get_usermeta($user_id, $meta_key = '')
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'get_user_meta()');
    $user_id = (int) $user_id;
    if (!$user_id) {
        return false;
    }
    $key = (string) preg_replace('|[^a-z0-9_]|i', '', (string) $meta_key);
    $values = $key !== '' ? (array) get_user_meta($user_id, $key) : array_map('maybe_unserialize', array_merge(...array_values(get_user_meta($user_id) ?: [[]])));
    return match (count($values)) {
        0 => $key === '' ? [] : '',
        1 => $values[0],
        default => $values,
    };
}

/** Stores a user's meta (an empty value deletes it); true when stored, false when the value was already there. */
function update_usermeta($user_id, $meta_key, $meta_value)
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'update_user_meta()');
    $user_id = (int) $user_id;
    if (!$user_id) {
        return false;
    }
    $key = (string) preg_replace('|[^a-z0-9_]|i', '', (string) $meta_key);
    $value = is_string($meta_value) ? stripslashes($meta_value) : $meta_value;
    if (empty(maybe_serialize($value))) {
        return delete_usermeta($user_id, $key);
    }
    return (bool) update_user_meta($user_id, $key, $value);
}

/** Deletes a user's meta under a key (only the matching value when one is given); true for any user. */
function delete_usermeta($user_id, $meta_key, $meta_value = '')
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'delete_user_meta()');
    $user_id = (int) $user_id;
    if (!$user_id) {
        return false;
    }
    $value = is_array($meta_value) || is_object($meta_value) ? serialize($meta_value) : trim((string) $meta_value);
    delete_user_meta($user_id, (string) preg_replace('|[^a-z0-9_]|i', '', (string) $meta_key), $value);
    return true;
}

/** Every meta row of the users, as user_id / meta_key / meta_value objects keyed by user. */
function get_user_metavalues($ids)
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    $ids = array_map('intval', (array) $ids);
    update_meta_cache('user', $ids);
    $objects = array_fill_keys($ids, []);
    foreach ($ids as $id) {
        foreach ((array) get_metadata('user', $id) as $key => $values) {
            foreach ((array) $values as $value) {
                $objects[$id][] = (object) ['user_id' => $id, 'meta_key' => $key, 'meta_value' => $value];
            }
        }
    }
    return $objects;
}

/** A user marked as sanitized for the context; a plain object or array has each field sanitized too. */
function sanitize_user_object($user, $context = 'display')
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    if (is_object($user)) {
        $user->ID ??= 0;
        if (!$user instanceof WP_User) {
            foreach (get_object_vars($user) as $field => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $user->$field = sanitize_user_field($field, $value, $user->ID, $context);
                }
            }
        }
        $user->filter = $context;
        return $user;
    }
    $user = (array) $user + ['ID' => 0];
    foreach ($user as $field => $value) {
        $user[$field] = sanitize_user_field($field, $value, $user['ID'], $context);
    }
    $user['filter'] = $context;
    return $user;
}

function list_authors($optioncount = false, $exclude_admin = true, $show_fullname = false, $hide_empty = true, $feed = '', $feed_image = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_authors()');
    return wp_list_authors(compact('optioncount', 'exclude_admin', 'show_fullname', 'hide_empty', 'feed', 'feed_image'));
}

/** Whether the login and password authenticate. */
function user_pass_ok($user_login, $user_pass)
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'wp_authenticate()');
    return !is_wp_error(wp_authenticate($user_login, $user_pass));
}

function create_user($username, $password, $email)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'wp_create_user()');
    return wp_create_user($username, $password, $email);
}

/** Whether the current user belongs to the site. */
function is_blog_user($blog_id = 0)
{
    _deprecated_function(__FUNCTION__, '3.3.0', 'is_user_member_of_blog()');
    return is_user_member_of_blog(get_current_user_id(), $blog_id);
}

// The old permission checks, by user level: 1 drafts, 2 posts, 5 dates, 10 everything.

/** @internal a user's old numeric level, 0 for nobody */
function _minn_user_level($user_id): int
{
    $user = get_userdata((int) $user_id);
    return $user ? (int) $user->user_level : 0;
}

function user_can_create_post($user_id, $blog_id = 1, $category_id = 'None')
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return _minn_user_level($user_id) > 1;
}

function user_can_create_draft($user_id, $blog_id = 1, $category_id = 'None')
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return _minn_user_level($user_id) >= 1;
}

/** Whether the user may edit the post: their own unless published at a low level, or one by a lower level, or anything at level 10. */
function user_can_edit_post($user_id, $post_id, $blog_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    $post = get_post($post_id);
    $level = _minn_user_level($user_id);
    $own = $post && (int) $user_id === (int) $post->post_author;
    return ($own && !($post->post_status === 'publish' && $level < 2)) || $level > _minn_user_level($post->post_author ?? 0) || $level >= 10;
}

function user_can_delete_post($user_id, $post_id, $blog_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return user_can_edit_post($user_id, $post_id, $blog_id);
}

function user_can_set_post_date($user_id, $blog_id = 1, $category_id = 'None')
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return _minn_user_level($user_id) > 4 && user_can_create_post($user_id, $blog_id, $category_id);
}

function user_can_edit_post_date($user_id, $post_id, $blog_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return _minn_user_level($user_id) > 4 && user_can_edit_post($user_id, $post_id, $blog_id);
}

function user_can_edit_post_comments($user_id, $post_id, $blog_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return user_can_edit_post($user_id, $post_id, $blog_id);
}

function user_can_delete_post_comments($user_id, $post_id, $blog_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    return user_can_edit_post_comments($user_id, $post_id, $blog_id);
}

/** Whether the user may edit the other: a higher level, level 9 or more, or themselves. */
function user_can_edit_user($user_id, $other_user)
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'current_user_can()');
    $level = _minn_user_level($user_id);
    return $level > _minn_user_level($other_user) || $level > 8 || (int) $user_id === (int) $other_user;
}

// Posts and their links.

/** A post as the old array: ID, Author_ID, Date, Content, Excerpt, Title, Category, then its status and ping fields. */
function get_postdata($postid)
{
    _deprecated_function(__FUNCTION__, '1.5.1', 'get_post()');
    $post = get_post($postid);
    return ['ID' => $post?->ID, 'Author_ID' => $post?->post_author, 'Date' => $post?->post_date, 'Content' => $post?->post_content, 'Excerpt' => $post?->post_excerpt, 'Title' => $post?->post_title, 'Category' => $post?->post_category, 'post_status' => $post?->post_status, 'comment_status' => $post?->comment_status, 'ping_status' => $post?->ping_status, 'post_password' => $post?->post_password, 'to_ping' => $post?->to_ping, 'pinged' => $post?->pinged, 'post_type' => $post?->post_type, 'post_name' => $post?->post_name];
}

function wp_get_single_post($postid = 0, $mode = OBJECT)
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'get_post()');
    return get_post($postid, $mode);
}

function wp_get_post_cats($blogid = '1', $post_id = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_get_post_categories()');
    return wp_get_post_categories($post_id);
}

function wp_set_post_cats($blogid = '1', $post_id = 0, $post_categories = [])
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_set_post_categories()');
    return wp_set_post_categories($post_id, $post_categories);
}

/** Nothing: a post's ancestors are worked out when asked for. */
function _get_post_ancestors(&$post)
{
    _deprecated_function(__FUNCTION__, '3.5.0');
}

function post_permalink($post = 0)
{
    _deprecated_function(__FUNCTION__, '4.4.0', 'get_permalink()');
    return get_permalink($post);
}

function permalink_link()
{
    _deprecated_function(__FUNCTION__, '1.2.0', 'the_permalink()');
    the_permalink();
}

function permalink_single_rss($deprecated = '')
{
    _deprecated_function(__FUNCTION__, '2.3.0', 'the_permalink_rss()');
    the_permalink_rss();
}

/** Prints " sticky" for a sticky post. */
function sticky_class($post_id = null)
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'post_class()');
    if (is_sticky($post_id)) {
        echo ' sticky';
    }
}

function link_pages($before = '<br />', $after = '<br />', $next_or_number = 'number', $nextpagelink = 'next page', $previouspagelink = 'previous page', $pagelink = '%', $more_file = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_link_pages()');
    return wp_link_pages(compact('before', 'after', 'next_or_number', 'nextpagelink', 'previouspagelink', 'pagelink', 'more_file'));
}

/**
 * Prints the post's content for a feed: its links as numbered footnotes,
 * or escaped (encode 1), or stripped to text (encode 2, and whenever it is
 * cut); a cut keeps that many words, with an ellipsis when it dropped some.
 */
function the_content_rss($more_link_text = '(more...)', $stripteaser = 0, $more_file = '', $cut = 0, $encode_html = 0)
{
    _deprecated_function(__FUNCTION__, '2.9.0', 'the_content_feed()');
    $content = apply_filters('the_content_rss', get_the_content($more_link_text, $stripteaser));
    $encode_html = $cut && !$encode_html ? 2 : (int) $encode_html;
    $cut = $encode_html === 1 ? 0 : (int) $cut;
    $content = match ($encode_html) {
        1 => esc_html($content),
        0 => make_url_footnote($content),
        2 => strip_tags($content),
        default => $content,
    };
    if ($cut) {
        $words = explode(' ', $content);
        $content = implode('', array_map(static fn ($word) => $word . ' ', array_slice($words, 0, $cut))) . (count($words) > $cut ? '...' : '');
    }
    echo str_replace(']]>', ']]&gt;', $content);
}

/** @internal the old neighbour link: the label, then the title when asked, linked, in place of % in the format */
function _minn_old_adjacent_post(bool $previous, $format, $label, $title, $in_same_cat, $excluded_categories): void
{
    $post = get_adjacent_post(!empty($in_same_cat) && $in_same_cat !== 'no', $excluded_categories, $previous);
    if (!$post) {
        return;
    }
    $string = '<a href="' . get_permalink($post->ID) . '">' . $label . ($title === 'yes' ? apply_filters('the_title', $post->post_title, $post->ID) : '') . '</a>';
    echo str_replace('%', $string, (string) $format);
}

function previous_post($format = '%', $previous = 'previous post: ', $title = 'yes', $in_same_cat = 'no', $limitprev = 1, $excluded_categories = '')
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'previous_post_link()');
    _minn_old_adjacent_post(true, $format, $previous, $title, $in_same_cat, $excluded_categories);
}

function next_post($format = '%', $next = 'next post: ', $title = 'yes', $in_same_cat = 'no', $limitnext = 1, $excluded_categories = '')
{
    _deprecated_function(__FUNCTION__, '2.0.0', 'next_post_link()');
    _minn_old_adjacent_post(false, $format, $next, $title, $in_same_cat, $excluded_categories);
}

/** @internal a head link to a post, its title (%title, %date) through the_title */
function _minn_post_rel_link(string $rel, WP_Post $post, string $title): string
{
    $title = str_replace(['%title', '%date'], [$post->post_title, mysql2date(get_option('date_format'), $post->post_date)], $title);
    return "<link rel='{$rel}' title='" . esc_attr(apply_filters('the_title', $title, $post->ID)) . "' href='" . get_permalink($post) . "' />\n";
}

/** The head link to the first (or last) post, through start_post_rel_link / end_post_rel_link; null off a single post. */
function get_boundary_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '', $start = true)
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    $post = get_boundary_post($in_same_cat, $excluded_categories, $start)[0] ?? null;
    if (!$post) {
        return null;
    }
    $post->post_title = $post->post_title !== '' ? $post->post_title : ($start ? __('First Post') : __('Last Post'));
    $boundary = $start ? 'start' : 'end';
    return apply_filters("{$boundary}_post_rel_link", _minn_post_rel_link($boundary, $post, (string) $title));
}

function start_post_rel_link($title = '%title', $in_same_cat = false, $excluded_categories = '')
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    echo get_boundary_post_rel_link($title, $in_same_cat, $excluded_categories, true);
}

/** The head link to the site's front page, through index_rel_link. */
function get_index_rel_link()
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    $link = "<link rel='index' title='" . esc_attr(get_bloginfo('name', 'display')) . "' href='" . esc_url(user_trailingslashit(get_bloginfo('url', 'display'))) . "' />\n";
    return apply_filters('index_rel_link', $link);
}

function index_rel_link()
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    echo get_index_rel_link();
}

/** The head link up to the current post's parent, through parent_post_rel_link; null for a post with none. */
function get_parent_post_rel_link($title = '%title')
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    $parent = empty($GLOBALS['post']->post_parent) ? null : get_post($GLOBALS['post']->post_parent);
    if (!$parent) {
        return null;
    }
    return apply_filters('parent_post_rel_link', _minn_post_rel_link('up', $parent, (string) $title));
}

function parent_post_rel_link($title = '%title')
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    echo get_parent_post_rel_link($title);
}

/** The post's comments feed address. */
function comments_rss()
{
    _deprecated_function(__FUNCTION__, '2.2.0', 'get_post_comments_feed_link()');
    return esc_url(get_post_comments_feed_link());
}

function comments_rss_link($link_text = 'Comments RSS')
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'post_comments_feed_link()');
    post_comments_feed_link($link_text);
}

/** A comment as an array. */
function get_commentdata($comment_id, $no_cache = 0, $include_unapproved = false)
{
    _deprecated_function(__FUNCTION__, '2.7.0', 'get_comment()');
    return get_comment($comment_id, ARRAY_A);
}

/** Nothing: the comments popup is gone. */
function comments_popup_script()
{
    _deprecated_function(__FUNCTION__, '4.5.0');
}

function get_comments_popup_template()
{
    _deprecated_function(__FUNCTION__, '4.5.0');
    return '';
}

function is_comments_popup()
{
    _deprecated_function(__FUNCTION__, '4.5.0');
    return false;
}

function get_paged_template()
{
    _deprecated_function(__FUNCTION__, '4.7.0');
    return get_query_template('paged');
}

// Attachments, the old way: an icon for anything, the title as the fallback.

/**
 * An attachment's icon and the file behind it: its thumbnail, else the
 * image itself, else its type's icon (as SVG) beside the theme's images;
 * false when there is no post or nothing to show.
 */
function get_attachment_icon_src($id = 0, $fullsize = false)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_get_attachment_image_src()');
    $post = get_post((int) $id);
    if (!$post) {
        return false;
    }
    if (!$fullsize && ($src = wp_get_attachment_thumb_url($post->ID))) {
        return [$src, wp_basename($src)];
    }
    if (wp_attachment_is_image($post->ID)) {
        $src = wp_get_attachment_url($post->ID);
        return $src ? [$src, get_attached_file($post->ID)] : false;
    }
    $src = wp_mime_type_icon($post->ID, '.svg');
    return $src ? [$src, apply_filters('icon_dir', get_template_directory() . '/images') . '/' . wp_basename($src)] : false;
}

/** An attachment's icon as an image tag titled by the post, held to attachment_max_dims when the file is there; through attachment_icon. */
function get_attachment_icon($id = 0, $fullsize = false, $max_dims = false)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_get_attachment_image()');
    $post = get_post((int) $id);
    $icon = $post ? get_attachment_icon_src($post->ID, $fullsize) : false;
    if (!$icon) {
        return false;
    }
    [$src, $file] = $icon;
    $constraint = '';
    $max_dims = apply_filters('attachment_max_dims', $max_dims);
    if ($max_dims && file_exists($file)) {
        [$maxWidth, $maxHeight] = $max_dims;
        [$width, $height] = wp_getimagesize($file) ?: [0, 1];
        if ($width > $maxWidth || $height > $maxHeight) {
            $constraint = $width / $height >= $maxWidth / $maxHeight ? "width='{$maxWidth}' " : "height='{$maxHeight}' ";
        }
    }
    $title = esc_attr($post->post_title);
    return apply_filters('attachment_icon', "<img src='{$src}' title='{$title}' alt='{$title}' {$constraint}/>", $post->ID);
}

/** An attachment's icon, else its title; through attachment_innerHTML. */
function get_attachment_innerHTML($id = 0, $fullsize = false, $max_dims = false)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_get_attachment_image()');
    $post = get_post((int) $id);
    if (!$post) {
        return false;
    }
    $inner = get_attachment_icon($post->ID, $fullsize, $max_dims);
    return $inner ?: apply_filters('attachment_innerHTML', esc_attr($post->post_title), $post->ID);
}

/** An attachment's icon linked to its file (or its page), titled by it; "Missing Attachment" for anything else. */
function get_the_attachment_link($id = 0, $fullsize = false, $max_dims = false, $permalink = false)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_get_attachment_link()');
    $post = get_post((int) $id);
    $url = $post && $post->post_type === 'attachment' ? wp_get_attachment_url($post->ID) : false;
    if (!$url) {
        return __('Missing Attachment');
    }
    $url = $permalink ? get_attachment_link($post->ID) : $url;
    return "<a href='{$url}' title='" . esc_attr($post->post_title) . "'>" . get_attachment_innerHTML($post->ID, $fullsize, $max_dims) . '</a>';
}

/** An image attachment's old thumbnail file, when its metadata names one that exists; through wp_get_attachment_thumb_file. */
function wp_get_attachment_thumb_file($post_id = 0)
{
    _deprecated_function(__FUNCTION__, '6.1.0');
    $post = get_post((int) $post_id);
    if (!$post || $post->post_type !== 'attachment') {
        return false;
    }
    $thumb = wp_get_attachment_metadata($post->ID)['thumb'] ?? '';
    $file = get_attached_file($post->ID);
    $thumbfile = $thumb !== '' ? str_replace(wp_basename($file), $thumb, $file) : '';
    return $thumbfile !== '' && file_exists($thumbfile) ? apply_filters('wp_get_attachment_thumb_file', $thumbfile, $post->ID) : false;
}

// Categories.

/** The category's feed address, printed too when display is on. */
function get_category_rss_link($display = false, $cat_id = 1)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'get_category_feed_link()');
    $link = get_category_feed_link($cat_id, 'rss2');
    if ($display) {
        echo $link;
    }
    return $link;
}

function get_catname($cat_id)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_cat_name()');
    return get_cat_name($cat_id);
}

/** A category's descendants' ids, each between before and after, children after their parent. */
function get_category_children($id, $before = '/', $after = '', $visited = [])
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'get_term_children()');
    if (!$id) {
        return '';
    }
    $chain = '';
    foreach ((array) get_all_category_ids() as $cat_id) {
        $category = $cat_id == $id ? null : get_category($cat_id);
        if ($category && $category->parent == $id && !in_array($category->term_id, $visited)) {
            $visited[] = $category->term_id;
            $chain .= $before . $category->term_id . $after . get_category_children($category->term_id, $before, $after);
        }
    }
    return $chain;
}

/** The post's first category id, printed too when display is on. */
function the_category_ID($display = true)
{
    _deprecated_function(__FUNCTION__, '0.71', 'get_the_category()');
    $cat = get_the_category()[0]->term_id ?? null;
    if ($display) {
        echo $cat;
    }
    return $cat;
}

/**
 * The heading for a change of category between posts. It compares the
 * first category's category_id, which a category no longer has, so it
 * prints nothing, as on the reference.
 */
function the_category_head($before = '', $after = '')
{
    global $currentcat, $previouscat;
    _deprecated_function(__FUNCTION__, '0.71', 'get_the_category_by_ID()');
    $first = get_the_category()[0] ?? null;
    $currentcat = $first->category_id ?? null;
    if ($currentcat != $previouscat) {
        echo $before . get_the_category_by_ID($currentcat) . $after;
        $previouscat = $currentcat;
    }
}

function get_archives($type = '', $limit = '', $format = 'html', $before = '', $after = '', $show_post_count = false)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_get_archives()');
    return wp_get_archives(compact('type', 'limit', 'format', 'before', 'after', 'show_post_count'));
}

function list_cats($optionall = 1, $all = 'All', $sort_column = 'ID', $sort_order = 'asc', $file = '', $list = true, $optiondates = 0, $optioncount = 0, $hide_empty = 1, $use_desc_for_title = 1, $children = false, $child_of = 0, $categories = 0, $recurse = 0, $feed = '', $feed_image = '', $exclude = '', $hierarchical = false)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_categories()');
    return wp_list_cats(compact('optionall', 'all', 'sort_column', 'sort_order', 'file', 'list', 'optiondates', 'optioncount', 'hide_empty', 'use_desc_for_title', 'children', 'child_of', 'categories', 'recurse', 'feed', 'feed_image', 'exclude', 'hierarchical'));
}

/** The old category list's arguments under their new names, untitled; any optionall shows the "all" item. */
function wp_list_cats($args = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_categories()');
    $r = wp_parse_args($args);
    foreach (['all' => 'show_option_all', 'sort_column' => 'orderby', 'sort_order' => 'order', 'optiondates' => 'show_last_update', 'optioncount' => 'show_count'] as $old => $new) {
        if (isset($r[$old]) && ($old !== 'all' || isset($r['optionall']))) {
            $r[$new] = $r[$old];
        }
    }
    if (isset($r['list'])) {
        $r['style'] = $r['list'] ? 'list' : 'break';
    }
    $r['title_li'] = '';
    return wp_list_categories($r);
}

/**
 * The category dropdown with the old arguments. The reference passes them
 * on as a query string whose leading "?" swallows the first, so the "all"
 * option never shows; it is left out here to the same effect.
 */
function dropdown_cats($optionall = 1, $all = 'All', $orderby = 'ID', $order = 'asc', $show_last_update = 0, $show_count = 0, $hide_empty = 1, $optionnone = false, $selected = 0, $exclude = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_dropdown_categories()');
    $show_option_none = $optionnone ? _x('None', 'Categories dropdown (show_option_none parameter)') : '';
    return wp_dropdown_categories(compact('show_option_none', 'orderby', 'order', 'show_last_update', 'show_count', 'hide_empty', 'selected', 'exclude'));
}

// The links manager, the old way.

/**
 * The links of a link category (every link for -1) in the old markup: each
 * between before and after, its description and rating after it when
 * asked, the last update in its title when shown; sorted descending when
 * the order starts with "_"; printed unless display is off.
 */
function get_links($category = -1, $before = '', $after = '<br />', $between = ' ', $show_images = true, $orderby = 'name', $show_description = true, $show_rating = false, $limit = -1, $show_updated = 1, $display = true)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    $descending = str_starts_with((string) $orderby, '_');
    $rows = get_bookmarks(['category' => $category == -1 ? '' : $category, 'orderby' => $descending ? substr((string) $orderby, 1) : $orderby, 'order' => $descending ? 'DESC' : 'ASC', 'show_updated' => $show_updated, 'limit' => $limit]);
    if (!$rows) {
        return null;
    }
    $output = '';
    foreach ($rows as $row) {
        $recent = $show_updated && !empty($row->recently_updated);
        $desc = esc_attr(sanitize_bookmark_field('link_description', $row->link_description, $row->link_id, 'display'));
        $updated = _minn_bookmark_updated($row, $show_updated);
        $anchor = _minn_bookmark_anchor($row, ['show_images' => $show_images, 'show_description' => 0, 'show_name' => 0, 'link_before' => '', 'link_after' => ''], $desc . ($updated === null ? '' : ' (' . __('Last updated') . ' ' . $updated . ')'));
        $output .= $before . ($recent ? get_option('links_recently_updated_prepend') . $anchor . get_option('links_recently_updated_append') : $anchor)
            . ($show_description && $desc !== '' ? $between . $desc : '') . ($show_rating ? $between . get_linkrating($row) : '') . "{$after}\n";
    }
    if (!$display) {
        return $output;
    }
    echo $output;
}

function get_links_withrating($category = -1, $before = '', $after = '<br />', $between = ' ', $show_images = true, $orderby = 'id', $show_description = true, $limit = -1, $show_updated = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    get_links($category, $before, $after, $between, $show_images, $orderby, $show_description, true, $limit, $show_updated);
}

/** @internal the id of the link category with that name, -1 when there is none */
function _minn_link_category_id($name): int
{
    $category = get_term_by('name', $name, 'link_category');
    return $category ? (int) $category->term_id : -1;
}

function get_linksbyname($cat_name = 'noname', $before = '', $after = '<br />', $between = ' ', $show_images = true, $orderby = 'id', $show_description = true, $show_rating = false, $limit = -1, $show_updated = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    get_links(_minn_link_category_id($cat_name), $before, $after, $between, $show_images, $orderby, $show_description, $show_rating, $limit, $show_updated);
}

function get_linksbyname_withrating($cat_name = 'noname', $before = '', $after = '<br />', $between = ' ', $show_images = true, $orderby = 'id', $show_description = true, $limit = -1, $show_updated = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    get_linksbyname($cat_name, $before, $after, $between, $show_images, $orderby, $show_description, true, $limit, $show_updated);
}

/** Prints every link category that has links, its name as a heading over its links. */
function get_links_list($order = 'name')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_bookmarks()');
    $order = strtolower((string) $order);
    $descending = str_starts_with($order, '_');
    $cats = get_categories(['type' => 'link', 'orderby' => $descending ? substr($order, 1) : $order, 'order' => $descending ? 'DESC' : 'ASC', 'hierarchical' => 0]);
    foreach ((array) $cats as $cat) {
        echo '  <li id="linkcat-' . $cat->term_id . '" class="linkcat"><h2>' . apply_filters('link_category', $cat->name) . "</h2>\n\t<ul>\n";
        get_links($cat->term_id, '<li>', '</li>', "\n", true, 'name', false);
        echo "\n\t</ul>\n</li>\n";
    }
}

/** A link category's links as a list (a limit of 0 finds none). */
function get_linkobjects($category = 0, $orderby = 'name', $limit = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    return array_values((array) get_bookmarks(['category' => $category, 'orderby' => $orderby, 'limit' => $limit]));
}

function get_linkobjectsbyname($cat_name = 'noname', $orderby = 'name', $limit = -1)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmarks()');
    return get_linkobjects(_minn_link_category_id($cat_name), $orderby, $limit);
}

/** A link's rating for display. */
function get_linkrating($link)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'sanitize_bookmark_field()');
    return sanitize_bookmark_field('link_rating', $link->link_rating, $link->link_id ?? null, 'display');
}

/** The name of a link's first category as a post category, which a link category is not: null. */
function get_linkcatname($id = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_category()');
    $cats = (int) $id ? wp_get_link_cats((int) $id) : [];
    if (empty($cats) || !is_array($cats)) {
        return '';
    }
    $category = get_category((int) $cats[0]);
    return $category instanceof WP_Term ? $category->name : null;
}

/**
 * The links in the old defaults. A bare argument is meant as a category id,
 * but the reference appends it to itself as a query string ("5?category=5")
 * and the key comes back mangled, so every link shows; the same here.
 */
function wp_get_links($args = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_bookmarks()');
    if (!str_contains((string) $args, '=')) {
        $args = '';
    }
    return wp_list_bookmarks(wp_parse_args($args, ['after' => '<br />', 'before' => '', 'between' => ' ', 'categorize' => 0, 'category' => '', 'echo' => true, 'limit' => -1, 'orderby' => 'name', 'show_description' => true, 'show_images' => true, 'show_rating' => false, 'show_updated' => true, 'title_li' => '']));
}

/** The links of the named link category in the old defaults. */
function wp_get_linksbyname($category, $args = '')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_bookmarks()');
    return wp_list_bookmarks(wp_parse_args($args, ['after' => '<br />', 'before' => '', 'categorize' => 0, 'category_after' => '', 'category_before' => '', 'category_name' => $category, 'show_description' => 1, 'title_li' => '']));
}

/** Nothing: the links popup is gone. */
function links_popup_script($text = 'Links', $width = 400, $height = 400, $file = 'links.all.php', $count = true)
{
    _deprecated_function(__FUNCTION__, '2.1.0');
}

function get_autotoggle($id = 0)
{
    _deprecated_function(__FUNCTION__, '2.1.0');
    return 0;
}

// Text.

/** A preformatted block with its line breaks and paragraphs taken out (given a match, its two parts and a closing tag). */
function clean_pre($matches)
{
    _deprecated_function(__FUNCTION__, '3.4.0');
    $text = is_array($matches) ? ($matches[1] ?? '') . ($matches[2] ?? '') . '</pre>' : $matches;
    return str_replace(['<br />', '<br/>', '<br>', '<p>', '</p>'], ['', '', '', "\n", ''], (string) $text);
}

function format_to_post($content)
{
    _deprecated_function(__FUNCTION__, '3.9.0');
    return $content;
}

function js_escape($text)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'esc_js()');
    return esc_js($text);
}

/** The text with its &{...}; JavaScript entities removed. */
function wp_kses_js_entities($content)
{
    _deprecated_function(__FUNCTION__, '4.7.0');
    return preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $content);
}

/** The text with each link replaced by its text and a number, the numbered addresses listed after (relative ones made absolute). */
function make_url_footnote($content)
{
    _deprecated_function(__FUNCTION__, '2.9.0');
    preg_match_all('/<a(.+?)href=\"(.+?)\"(.*?)>(.+?)<\/a>/', (string) $content, $matches);
    $summary = "\n";
    foreach ($matches[0] as $i => $match) {
        $number = '[' . ($i + 1) . ']';
        $url = preg_match('#^https?://#i', $matches[2][$i]) ? $matches[2][$i] : get_option('home') . $matches[2][$i];
        $content = str_replace($match, $matches[4][$i] . ' ' . $number, (string) $content);
        $summary .= "\n{$number} {$url}";
    }
    return strip_tags((string) $content) . $summary;
}

function translate_with_context($text, $domain = 'default')
{
    _deprecated_function(__FUNCTION__, '2.9.0', '_x()');
    return before_last_bar(translate($text, $domain));
}

function _nc($single, $plural, $number, $domain = 'default')
{
    _deprecated_function(__FUNCTION__, '2.9.0', '_nx()');
    return before_last_bar(_n($single, $plural, $number, $domain));
}

function __ngettext_noop(...$args)
{
    _deprecated_function(__FUNCTION__, '2.8.0', '_n_noop()');
    return _n_noop(...$args);
}

/** The count, as given (it never reported its deprecation). */
function default_topic_count_text($count)
{
    return $count;
}

/** A byte count in the largest whole unit it reaches (B to TB), e.g. "2KB". */
function wp_convert_bytes_to_hr($bytes)
{
    _deprecated_function(__FUNCTION__, '3.6.0', 'size_format()');
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $log = log($bytes, KB_IN_BYTES);
    $power = (int) $log;
    $size = KB_IN_BYTES ** ($log - $power);
    return is_nan($size) || !isset($units[$power]) ? $bytes . $units[0] : $size . $units[$power];
}

function _search_terms_tidy($t)
{
    _deprecated_function(__FUNCTION__, '3.7.0');
    return trim($t, "\"'\n\r ");
}

/** The text, its %uXXXX escapes made entities for old Internet Explorer. */
function funky_javascript_fix($text)
{
    global $is_macIE, $is_winIE;
    _deprecated_function(__FUNCTION__, '3.0.0');
    if ($is_winIE || $is_macIE) {
        $text = preg_replace_callback('/\%u([0-9A-F]{4,4})/', 'funky_javascript_callback', $text);
    }
    return $text;
}

/** A %uXXXX escape as a numeric entity (it never reported its deprecation). */
function funky_javascript_callback($matches)
{
    return '&#' . base_convert($matches[1], 16, 10) . ';';
}

/** Links opened in a new window. */
function popuplinks($text)
{
    _deprecated_function(__FUNCTION__, '4.5.0');
    return preg_replace('/<a (.+?)>/i', "<a $1 target='_blank' rel='external'>", $text);
}

// Options, terms and the old registries.

function get_settings($option)
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_option()');
    return get_option($option);
}

function get_alloptions()
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'wp_load_alloptions()');
    return wp_load_alloptions();
}

function _wp_register_meta_args_whitelist($args, $default_args)
{
    _deprecated_function(__FUNCTION__, '5.5.0', '_wp_register_meta_args_allowed_list()');
    return _wp_register_meta_args_allowed_list($args, $default_args);
}

function is_taxonomy($taxonomy)
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'taxonomy_exists()');
    return taxonomy_exists($taxonomy);
}

/** Whether the term exists, at the top level unless a parent is named. */
function is_term($term, $taxonomy = '', $parent = 0)
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'term_exists()');
    return term_exists($term, $taxonomy, $parent);
}

function update_category_cache()
{
    _deprecated_function(__FUNCTION__, '3.1.0');
    return true;
}

function clean_page_cache($id)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'clean_post_cache()');
    clean_post_cache($id);
}

function update_page_cache(&$pages)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'update_post_cache()');
    update_post_cache($pages);
}

/** Terms compared by id. */
function _usort_terms_by_ID($a, $b)
{
    _deprecated_function(__FUNCTION__, '4.7.0', 'wp_list_sort()');
    return $a->term_id <=> $b->term_id;
}

/** Terms compared by name. */
function _usort_terms_by_name($a, $b)
{
    _deprecated_function(__FUNCTION__, '4.7.0', 'wp_list_sort()');
    return strcmp($a->name, $b->name);
}

/** Menu items compared by the property in $_menu_item_sort_prop: numerically when both are numbers, else as text; 0 with no property. */
function _sort_nav_menu_items($a, $b)
{
    global $_menu_item_sort_prop;
    _deprecated_function(__FUNCTION__, '4.7.0', 'wp_list_sort()');
    $prop = $_menu_item_sort_prop;
    if (empty($prop) || !isset($a->$prop, $b->$prop) || $a->$prop == $b->$prop) {
        return 0;
    }
    if ((int) $a->$prop == $a->$prop && (int) $b->$prop == $b->$prop) {
        return (int) $a->$prop < (int) $b->$prop ? -1 : 1;
    }
    return strcmp((string) $a->$prop, (string) $b->$prop);
}

/** Nothing (it never reported its deprecation). */
function _save_post_hook()
{
}

/** Whether a plugin page is being shown. */
function is_plugin_page()
{
    global $plugin_page;
    _deprecated_function(__FUNCTION__, '3.1.0');
    return isset($plugin_page);
}

/** Whether TinyMCE ships with the engine: it does not. */
function rich_edit_exists()
{
    global $wp_rich_edit_exists;
    _deprecated_function(__FUNCTION__, '3.9.0');
    $wp_rich_edit_exists ??= file_exists(ABSPATH . WPINC . '/js/tinymce/tinymce.js');
    return $wp_rich_edit_exists;
}

function unregister_sidebar_widget($id)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'wp_unregister_sidebar_widget()');
    return wp_unregister_sidebar_widget($id);
}

function unregister_widget_control($id)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'wp_unregister_widget_control()');
    return wp_unregister_widget_control($id);
}

/** A translation file for the domain in the languages folder, remembered per domain until reset. */
function _get_path_to_translation($domain, $reset = false)
{
    static $found = [];
    _deprecated_function(__FUNCTION__, '6.1.0', 'WP_Textdomain_Registry');
    if ($reset === true) {
        $found = [];
    }
    return $found[$domain] ??= _get_path_to_translation_from_lang_dir($domain);
}

/** The domain's translation for the current locale in the plugins, then the themes, languages folder; false when neither has it. */
function _get_path_to_translation_from_lang_dir($domain)
{
    static $files = null;
    _deprecated_function(__FUNCTION__, '6.1.0', 'WP_Textdomain_Registry');
    $files ??= [...(glob(WP_LANG_DIR . '/plugins/*.mo') ?: []), ...(glob(WP_LANG_DIR . '/themes/*.mo') ?: [])];
    $file = "{$domain}-" . determine_locale() . '.mo';
    foreach (['plugins', 'themes'] as $folder) {
        if (in_array(WP_LANG_DIR . "/{$folder}/{$file}", $files, true)) {
            return WP_LANG_DIR . "/{$folder}/{$file}";
        }
    }
    return false;
}

// The head, robots and requests.

/** Robots told not to index a private site. */
function noindex()
{
    _deprecated_function(__FUNCTION__, '5.7.0', 'wp_robots_noindex()');
    if ('0' == get_option('blog_public')) {
        wp_no_robots();
    }
}

/** The robots meta tag: noindex, and nofollow too on a private site. */
function wp_no_robots()
{
    _deprecated_function(__FUNCTION__, '5.7.0', 'wp_robots_no_robots()');
    echo get_option('blog_public') ? "<meta name='robots' content='noindex,follow' />\n" : "<meta name='robots' content='noindex,nofollow' />\n";
}

/** The robots and referrer meta tags for a page with private details. */
function wp_sensitive_page_meta()
{
    _deprecated_function(__FUNCTION__, '5.7.0', 'wp_robots_sensitive_page()');
    echo "\t<meta name='robots' content='noindex,noarchive' />\n\t";
    wp_strict_cross_origin_referrer();
}

/** Nothing: Windows Live Writer is gone. */
function wlwmanifest_link()
{
    _deprecated_function(__FUNCTION__, '6.3.0');
}

/** The theme supports automatic feed links; turning them off only stops the extra feeds. */
function automatic_feed_links($add = true)
{
    _deprecated_function(__FUNCTION__, '3.0.0', "add_theme_support( 'automatic-feed-links' )");
    if ($add) {
        add_theme_support('automatic-feed-links');
    } else {
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}

/** No Press This bookmarklet; through shortcut_link. */
function get_shortcut_link()
{
    _deprecated_function(__FUNCTION__, '4.9.0');
    return apply_filters('shortcut_link', '');
}

/** The style that sizes auto-sized images before they load, unless wp_img_tag_add_auto_sizes turns them off. */
function wp_print_auto_sizes_contain_css_fix()
{
    _deprecated_function(__FUNCTION__, '6.9.0', 'wp_enqueue_img_auto_sizes_contain_css_fix');
    if (apply_filters('wp_img_tag_add_auto_sizes', true)) {
        echo "\t<style>img:is([sizes=\"auto\" i], [sizes^=\"auto,\" i]) { contain-intrinsic-size: 3000px 1500px }</style>\n\t";
    }
}

/** Nothing: the engine writes the skip link into the page itself (Theme\PageRenderer). */
function the_block_template_skip_link()
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_enqueue_block_template_skip_link()');
}

/** Whether the address answers over HTTPS (200 or 401). */
function url_is_accessable_via_ssl($url)
{
    _deprecated_function(__FUNCTION__, '4.0.0');
    $response = wp_remote_get(set_url_scheme($url, 'https'));
    return !is_wp_error($response) && in_array((int) wp_remote_retrieve_response_code($response), [200, 401], true);
}

/**
 * The headers of a safe request to the address (a HEAD that follows up to
 * five redirects itself), or, given a file, a GET whose body is written
 * there; false when the request fails.
 */
function wp_get_http($url, $file_path = false, $red = 1)
{
    _deprecated_function(__FUNCTION__, '4.4.0', 'WP_Http');
    if ($red > 5) {
        return false;
    }
    $method = $file_path ? 'GET' : 'HEAD';
    $response = wp_safe_remote_request($url, ['method' => $method, 'redirection' => 5]);
    if (is_wp_error($response)) {
        return false;
    }
    $headers = wp_remote_retrieve_headers($response);
    $headers['response'] = wp_remote_retrieve_response_code($response);
    if ($method === 'HEAD' && in_array($headers['response'], [301, 302]) && isset($headers['location'])) {
        return wp_get_http($headers['location'], $file_path, ++$red);
    }
    if ($file_path && ($out = fopen($file_path, 'w'))) {
        fwrite($out, wp_remote_retrieve_body($response));
        fclose($out);
        clearstatcache();
    }
    return $headers;
}

function wp_timezone_supported()
{
    _deprecated_function(__FUNCTION__, '3.2.0');
    return true;
}

function gzip_compression()
{
    _deprecated_function(__FUNCTION__, '2.5.0');
    return false;
}

function debug_fopen($filename, $mode)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'error_log()');
    return false;
}

/** The message to the error log, when $debug is on. */
function debug_fwrite($fp, $message)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'error_log()');
    if (!empty($GLOBALS['debug'])) {
        error_log($message);
    }
}

function debug_fclose($fp)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'error_log()');
}

/** Whether GD can edit images of the type. */
function gd_edit_image_support($mime_type)
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'wp_image_editor_supports()');
    $types = ['image/jpeg' => ['IMG_JPG', 'imagecreatefromjpeg'], 'image/png' => ['IMG_PNG', 'imagecreatefrompng'], 'image/gif' => ['IMG_GIF', 'imagecreatefromgif'], 'image/webp' => ['IMG_WEBP', 'imagecreatefromwebp'], 'image/avif' => ['IMG_AVIF', 'imagecreatefromavif']];
    if (!isset($types[$mime_type])) {
        return false;
    }
    [$flag, $reader] = $types[$mime_type];
    return function_exists('imagetypes') ? defined($flag) && (imagetypes() & constant($flag)) !== 0 : function_exists($reader);
}

function wp_explain_nonce($action)
{
    _deprecated_function(__FUNCTION__, '3.4.1', 'wp_nonce_ays()');
    return __('Are you sure you want to do this?');
}

function force_ssl_login($force = null)
{
    _deprecated_function(__FUNCTION__, '4.4.0', 'force_ssl_admin()');
    return force_ssl_admin($force);
}

/** Nothing: theme previews by query string are gone. */
function preview_theme()
{
    _deprecated_function(__FUNCTION__, '4.3.0');
}

function _preview_theme_template_filter()
{
    _deprecated_function(__FUNCTION__, '4.3.0');
    return '';
}

function _preview_theme_stylesheet_filter()
{
    _deprecated_function(__FUNCTION__, '4.3.0');
    return '';
}

function preview_theme_ob_filter($content)
{
    _deprecated_function(__FUNCTION__, '4.3.0');
    return $content;
}

function preview_theme_ob_filter_callback($matches)
{
    _deprecated_function(__FUNCTION__, '4.3.0');
    return '';
}

/** Nothing: register_globals is gone. */
function wp_unregister_GLOBALS()
{
    _deprecated_function(__FUNCTION__, '5.5.0');
}

/** The comments' meta, queued for loading. */
function wp_queue_comments_for_comment_meta_lazyload($comments)
{
    _deprecated_function(__FUNCTION__, '6.3.0', 'wp_lazyload_comment_meta()');
    $ids = [];
    foreach (is_array($comments) ? $comments : [] as $comment) {
        if ($comment instanceof WP_Comment) {
            $ids[] = $comment->comment_ID;
        }
    }
    wp_lazyload_comment_meta($ids);
}

/** An image tag given decoding (async unless wp_img_tag_add_decoding_attr says otherwise), when its src is double-quoted. */
function wp_img_tag_add_decoding_attr($image, $context)
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_img_tag_add_loading_optimization_attrs()');
    if (!str_contains($image, ' src="')) {
        return $image;
    }
    $value = apply_filters('wp_img_tag_add_decoding_attr', 'async', $image, $context);
    return in_array($value, ['async', 'sync', 'auto'], true) ? str_replace('<img ', '<img decoding="' . esc_attr($value) . '" ', $image) : $image;
}

/** Nothing: Google Video is gone. */
function wp_embed_handler_googlevideo($matches, $attr, $url, $rawattr)
{
    _deprecated_function(__FUNCTION__, '4.6.0');
    return '';
}

/** The clauses unchanged, the filter taken off: searching attachments by file name is wp_allow_query_attachment_by_filename now. */
function _filter_query_attachment_filenames($clauses)
{
    _deprecated_function(__FUNCTION__, '6.0.3', 'add_filter( "wp_allow_query_attachment_by_filename", "__return_true" )');
    remove_filter('posts_clauses', __FUNCTION__);
    return $clauses;
}

/** The template the front page resolves to: a static front page, else the home template. */
function _resolve_home_block_template()
{
    _deprecated_function(__FUNCTION__, '6.2.0');
    $front = get_option('page_on_front');
    if (get_option('show_on_front') === 'page' && $front) {
        return ['postType' => 'page', 'postId' => $front];
    }
    $template = resolve_block_template('home', ['front-page', 'home', 'index'], '');
    return $template ? ['postType' => 'wp_template', 'postId' => $template->id] : null;
}

/** Nothing: the block's view script is a module that already imports the Interactivity API. */
function block_core_file_ensure_interactivity_dependency()
{
    _deprecated_function(__FUNCTION__, '6.5.0', 'wp_register_script_module');
}

/** Nothing: the block's view script is a module that already imports the Interactivity API. */
function block_core_image_ensure_interactivity_dependency()
{
    _deprecated_function(__FUNCTION__, '6.5.0', 'wp_register_script_module');
}

/** Nothing: the block's view script is a module that already imports the Interactivity API. */
function block_core_query_ensure_interactivity_dependency()
{
    _deprecated_function(__FUNCTION__, '6.5.0', 'wp_register_script_module');
}

/** The block unchanged: directives are processed as blocks render. */
function wp_interactivity_process_directives_of_interactive_blocks($parsed_block)
{
    _deprecated_function(__FUNCTION__, '6.6.0');
    return $parsed_block;
}

function _excerpt_render_inner_columns_blocks($columns, $allowed_blocks)
{
    _deprecated_function(__FUNCTION__, '5.8.0', '_excerpt_render_inner_blocks()');
    return _excerpt_render_inner_blocks($columns, $allowed_blocks);
}

/** The old loop's step: the query moves to its next post and sets it up. */
function start_wp()
{
    global $wp_query;
    _deprecated_function(__FUNCTION__, '1.5.0', __('new WordPress Loop'));
    $wp_query->next_post();
    setup_postdata(get_post());
}
