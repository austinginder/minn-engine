<?php
/** Posts: reads, the lists, the writers, post types, statuses. Behaviour from contracts/fixtures/api/content.json. */

use Minn\Runtime\Registry;
use Minn\Runtime\PostSave;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Slug;
use Minn\Content\PostClasses;
use Minn\Runtime\PostData;
use Minn\Runtime\PostLinks;
use Minn\Runtime\PostRevisions;
use Minn\Runtime\Registered;
use Minn\Runtime\Runtime;
use Minn\Runtime\Pages;
use Minn\Runtime\PostInsert;
use Minn\Runtime\PostLookup;
use Minn\Support\Text;

/** @internal */
function _minn_posts(): Posts
{
    return new Posts(Runtime::current()->db);
}

/** @internal */
/** @internal */
function _minn_post_lookup(): PostLookup
{
    return new PostLookup(Runtime::current()->db);
}

function _minn_post_writer(): PostWriter
{
    return new PostWriter(Runtime::current()->db, _minn_posts(), Runtime::current()->site);
}

function get_post($post = null, $output = OBJECT, $filter = 'raw')
{
    return Minn\Runtime\Records::post($post, $output, $filter);
}

function clean_post_cache($post)
{
    Minn\Runtime\PostCaches::clean($post);
}

function get_the_ID()
{
    return Minn\Runtime\PostTags::id();
}

function get_post_type($post = null)
{
    return Minn\Runtime\PostReads::type($post);
}

function get_post_status($post = null)
{
    return Minn\Runtime\PostReads::status($post);
}

/** One column of a post through sanitize_post_field in the context asked for (probe post-field). */
function get_post_field($field, $post = null, $context = 'display')
{
    return Minn\Runtime\PostReads::field($field, $post, $context);
}

function get_post_mime_type($post = null)
{
    $post = get_post($post);
    return $post === null ? false : (string) $post->post_mime_type;
}

function get_the_title($post = 0)
{
    return Minn\Runtime\PostTags::title($post);
}

function the_title($before = '', $after = '', $display = true)
{
    $title = get_the_title();
    if ($title === '') {
        return null;
    }
    $title = $before . $title . $after;
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}

function the_title_attribute($args = '')
{
    $args = wp_parse_args($args, ['before' => '', 'after' => '', 'echo' => true, 'post' => get_post()]);
    $title = get_the_title($args['post']);
    if ($title === '') {
        return null;
    }
    // Before and after lose their tags with the title (probe post-template-remaining).
    $title = esc_attr(strip_tags($args['before'] . $title . $args['after']));
    if ($args['echo']) {
        echo $title;
        return null;
    }
    return $title;
}

/**
 * The content as the loop shows it (Runtime\PostData): the current page, cut
 * at the more tag with its link unless the whole post is shown; a post
 * named outright has its postdata made afresh; a protected one gives the
 * password form.
 */
function get_the_content($more_link_text = null, $strip_teaser = false, $post = null)
{
    $_post = get_post($post);
    if (!$_post instanceof WP_Post) {
        return '';
    }
    $elements = $post === null && isset($GLOBALS['pages']) ? ['page' => $GLOBALS['page'] ?? 1, 'more' => $GLOBALS['more'] ?? 0, 'pages' => (array) $GLOBALS['pages'], 'multipage' => $GLOBALS['multipage'] ?? 0] : PostData::generate($_post);
    if (post_password_required($_post)) {
        return get_the_password_form($_post);
    }
    return PostData::content($more_link_text, $_post, ['strip_teaser' => (bool) $strip_teaser] + $elements);
}

/** The loop's view of a post (Runtime\PostData): its pages, the page asked for, whether the whole post shows. */
function generate_postdata($post)
{
    $post = get_post($post);
    return $post instanceof WP_Post ? PostData::generate($post) : false;
}

function the_content($more_link_text = null, $strip_teaser = false)
{
    // Under a classic theme the tag runs the engine's whole content pipeline;
    // a bare apply_filters('the_content', ...) elsewhere stays a plain filter run.
    if (Runtime::current()->get('classic_theme')) {
        $post = get_post();
        $content = $post === null ? '' : \Minn\Theme\ClassicContent::render(Minn\Content\PostRecord::fromRow($post->to_array()), $more_link_text);
    } else {
        $content = apply_filters('the_content', get_the_content($more_link_text, $strip_teaser));
    }
    echo str_replace(']]>', ']]&gt;', $content);
}

function get_the_excerpt($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return '';
    }
    if (post_password_required($post)) {
        return 'There is no excerpt because this is a protected post.';
    }
    // The stored excerpt; wp_trim_excerpt (the default here) makes one from the content when there is none.
    return apply_filters('get_the_excerpt', $post->post_excerpt, $post);
}

function the_excerpt()
{
    echo apply_filters('the_excerpt', get_the_excerpt());
}

function has_excerpt($post = 0)
{
    $post = get_post($post);
    return $post !== null && $post->post_excerpt !== '';
}

function post_password_required($post = null)
{
    return Minn\Runtime\PostTags::passwordRequired($post);
}

/**
 * The form a protected post shows instead of its content (captured): it
 * sends the visitor back to the post, and under a block theme its button
 * wears the button block's classes.
 */
/**
 * The password form, as the content shows it before its paragraphs are made;
 * a reader the post's own page sent back with a password cookie set reads
 * why above the field (Content\PasswordGate::invalid).
 */
function get_the_password_form($post = 0)
{
    $post = get_post($post);
    $label = 'pwbox-' . ($post === null ? mt_rand() : $post->ID);
    $permalink = $post === null ? '' : (string) get_permalink($post);
    $invalid = $post === null ? '' : Minn\Content\PasswordGate::invalid($permalink, (string) wp_get_raw_referer(), (string) ($_COOKIE['wp-postpass_' . COOKIEHASH] ?? ''), static fn (): WP_Post => $post);
    $button = '<input type="submit" name="Submit"' . (wp_is_block_theme() ? ' class="wp-block-button__link wp-element-button"' : '') . ' value="' . esc_attr_x('Enter', 'post password form') . '" />';
    $button = wp_is_block_theme() ? '<span class="wp-block-button">' . $button . '</span>' : $button;
    $form = '<form action="' . esc_url(site_url('wp-login.php?action=postpass', 'login_post')) . '" class="post-password-form' . ($invalid === '' ? '' : ' password-form-error') . '" method="post">'
        . '<input type="hidden" name="redirect_to" value="' . esc_attr($permalink) . '" />'
        . ($invalid === '' ? '' : '<div class="post-password-form-invalid-password" role="alert"><p id="error-' . $label . '">' . $invalid . '</p></div>') . "\n\t"
        . '<p>This content is password-protected. To view it, please enter the password below.</p>' . "\n\t"
        . '<p><label for="' . $label . '">Password: <input name="post_password" id="' . $label . '" type="password" spellcheck="false" required size="20"' . ($invalid === '' ? '' : ' aria-describedby="error-' . $label . '"') . ' /></label> ' . $button . '</p></form>' . "\n\t";
    return apply_filters('the_password_form', $form, $post, $invalid);
}

function get_the_date($format = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $format = $format === '' ? get_option('date_format') : $format;
    return apply_filters('get_the_date', get_post_time($format, false, $post, true), $format, $post);
}

function get_the_time($format = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $format = $format === '' ? get_option('time_format') : $format;
    return apply_filters('get_the_time', get_post_time($format, false, $post, true), $format, $post);
}

function get_post_time($format = 'U', $gmt = false, $post = null, $translate = false)
{
    return Minn\Runtime\PostTags::time($format, $gmt, $post, $translate);
}

function get_the_modified_date($format = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $format = $format === '' ? get_option('date_format') : $format;
    return apply_filters('get_the_modified_date', get_post_modified_time($format, false, $post, true), $format, $post);
}

function get_the_modified_time($format = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $format = $format === '' ? get_option('time_format') : $format;
    return apply_filters('get_the_modified_time', get_post_modified_time($format, false, $post, true), $format, $post);
}

function get_post_modified_time($format = 'U', $gmt = false, $post = null, $translate = false)
{
    return Minn\Runtime\PostTags::modifiedTime($format, $gmt, $post, $translate);
}

/** The loop post's date, printed (or returned) only when it starts a new day; the_date filters it either way. */
function the_date($format = '', $before = '', $after = '', $display = true)
{
    $date = '';
    if (is_new_day()) {
        $date = $before . get_the_date($format) . $after;
        $GLOBALS['previousday'] = $GLOBALS['currentday'] ?? null;
    }
    $date = apply_filters('the_date', $date, $format, $before, $after);
    if ($display) {
        echo $date;
        return null;
    }
    return $date;
}

function the_time($format = '')
{
    echo apply_filters('the_time', get_the_time($format), $format);
}

function get_post_ancestors($post)
{
    return Minn\Runtime\TermLookup::postAncestors($post);
}

function get_ancestors($object_id = 0, $object_type = '', $resource_type = '')
{
    return Minn\Runtime\TermLookup::ancestors($object_id, $object_type, $resource_type);
}

function wp_get_post_parent_id($post = null)
{
    $post = get_post($post);
    return $post === null ? false : (int) $post->post_parent;
}

function get_page_by_path($page_path, $output = OBJECT, $post_type = 'page')
{
    return Minn\Runtime\PostReads::pageByPath($page_path, $output, $post_type);
}

function get_page_by_title($page_title, $output = OBJECT, $post_type = 'page')
{
    _deprecated_function(__FUNCTION__, '6.2.0', 'WP_Query');
    $id = _minn_post_lookup()->idByTitle((string) $page_title, array_map('strval', (array) $post_type));
    return $id === null ? null : get_post($id, $output);
}

function get_page_uri($page = 0)
{
    return Minn\Runtime\PostReads::pageUri($page);
}

function url_to_postid($url)
{
    $url = apply_filters('url_to_postid', (string) $url);
    $url = (string) preg_replace('/#.*$/', '', $url);
    $home = home_url();
    if (preg_match('/[?&]p=(\d+)/', $url, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/[?&]page_id=(\d+)/', $url, $m)) {
        return (int) $m[1];
    }
    if (str_starts_with($url, 'http')) {
        if (!str_starts_with($url, $home)) {
            return 0;
        }
        $url = substr($url, strlen($home));
    }
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if ($path === '') {
        return 0;
    }
    $permalinks = Runtime::current()->get('permalinks');
    if ($permalinks !== null) {
        $segments = explode('/', $path);
        $page = _minn_posts()->pageByPathAnyStatus($segments);
        if ($page !== null) {
            return (int) $page['ID'];
        }
        $post = _minn_posts()->findByNameAnyStatus(end($segments), ['post']);
        if ($post !== null && $permalinks->forPost($post) === rtrim($home, '/') . '/' . $path . '/') {
            return (int) $post['ID'];
        }
        // An attachment answers to its own address (a page path finds one too on the reference).
        $attachment = _minn_posts()->findByNameAnyStatus(end($segments), ['attachment']);
        if ($attachment !== null && untrailingslashit((string) get_permalink((int) $attachment['ID'])) === rtrim($home, '/') . '/' . $path) {
            return (int) $attachment['ID'];
        }
    }
    return 0;
}

function get_post_custom($post_id = 0)
{
    return Minn\Runtime\MetaData::postCustom($post_id);
}

function get_post_custom_keys($post_id = 0)
{
    $custom = get_post_custom($post_id);
    if (!is_array($custom) || $custom === []) {
        return null;
    }
    return array_keys($custom);
}

function get_post_custom_values($key = '', $post_id = 0)
{
    if ($key === '') {
        return null;
    }
    $custom = get_post_custom($post_id);
    return $custom[$key] ?? null;
}

/** One of the current post's custom fields: its value, or its values when it has several; false when it has none (probe post-template-more). */
function post_custom($key = '')
{
    $custom = get_post_custom();
    if (!isset($custom[$key])) {
        return false;
    }
    return count($custom[$key]) === 1 ? $custom[$key][0] : $custom[$key];
}

/**
 * The current post's visible custom fields as a list (deprecated in
 * 6.0.2): each key and its trimmed values joined by commas, escaped, through
 * the_meta_key; nothing when none is visible.
 */
function the_meta()
{
    _deprecated_function(__FUNCTION__, '6.0.2', 'get_post_meta()');
    $items = '';
    foreach ((array) get_post_custom_keys() as $key) {
        if (is_protected_meta($key, 'post')) {
            continue;
        }
        $value = implode(', ', array_map('trim', (array) get_post_custom_values($key)));
        $items .= apply_filters('the_meta_key', sprintf("<li><span class='post-meta-key'>%s:</span> %s</li>\n", esc_html($key), esc_html($value)), $key, $value);
    }
    if ($items !== '') {
        echo "<ul class='post-meta'>\n{$items}</ul>\n";
    }
}

/** An attachment's link, printed: at full size for a truthy $fullsize, thumbnail otherwise; anything in $deprecated is a 2.5.0 notice. */
function the_attachment_link($post = 0, $fullsize = false, $deprecated = false, $permalink = false)
{
    if (!empty($deprecated)) {
        _deprecated_argument(__FUNCTION__, '2.5.0');
    }
    echo wp_get_attachment_link($post, $fullsize ? 'full' : 'thumbnail', $permalink);
}

function get_edit_post_link($post = 0, $context = 'display')
{
    return Minn\Runtime\EditLinks::post($post, $context);
}

function get_delete_post_link($post = 0, $deprecated = '', $force_delete = false)
{
    $post = get_post($post);
    if ($post === null || !current_user_can('delete_post', $post->ID)) {
        return null;
    }
    $action = $force_delete || !EMPTY_TRASH_DAYS ? 'delete' : 'trash';
    $link = add_query_arg('action', $action, admin_url(sprintf('post.php?post=%d', $post->ID)));
    return apply_filters('get_delete_post_link', wp_nonce_url($link, "$action-post_{$post->ID}"), $post->ID, $force_delete);
}

/** A post's address, by its kind (Runtime\PostLinks); a post object handed in is used as it is. */
function get_permalink($post = 0, $leavename = false)
{
    return PostLinks::permalink($post, $leavename);
}

/** @internal the link flags of a leavename and sample pair */
function _minn_link_flags($leavename, $sample): int
{
    return ($leavename ? PostLinks::LEAVE_NAME : 0) | ($sample ? PostLinks::SAMPLE : 0);
}

function get_the_permalink($post = 0, $leavename = false)
{
    return get_permalink($post, $leavename);
}

function the_permalink($post = 0)
{
    echo esc_url(apply_filters('the_permalink', get_permalink($post), $post));
}

/** A plugin type's address by its pattern or query var (Runtime\PostLinks), through post_type_link. */
function get_post_permalink($post = 0, $leavename = false, $sample = false)
{
    return PostLinks::customLink($post, $leavename, $sample);
}

/** A page's address: the front page is home; else its own, through page_link. */
function get_page_link($post = 0, $leavename = false, $sample = false)
{
    return PostLinks::pageLink($post, $leavename, $sample);
}

/** A page's own address, through _get_page_link (Runtime\PostLinks). */
function _get_page_link($post = 0, $leavename = false, $sample = false)
{
    return PostLinks::pageAddress($post, $leavename, $sample);
}

/** An attachment's address, through attachment_link (Runtime\PostLinks). */
function get_attachment_link($post = null, $leavename = false)
{
    return PostLinks::attachmentLink($post, $leavename);
}

/** The address an editor shows with the slug to edit, through get_sample_permalink (Runtime\PostLinks). */
function get_sample_permalink($post, $title = null, $name = null)
{
    $post = get_post($post);
    return $post === null ? ['', ''] : PostLinks::sample($post, $title === null ? null : (string) $title, $name === null ? null : (string) $name);
}

/** A post type's archive address, false for a type with none (Runtime\ArchiveLinks). */
function get_post_type_archive_link($post_type)
{
    return Minn\Runtime\ArchiveLinks::archive((string) $post_type);
}

/** A post type's archive feed, false for a type with no archive (Runtime\ArchiveLinks). */
function get_post_type_archive_feed_link($post_type, $feed = '')
{
    return Minn\Runtime\ArchiveLinks::feed((string) $post_type, (string) $feed);
}

function wp_get_shortlink($id = 0, $context = 'post', $allow_slugs = true)
{
    return Minn\Runtime\EditLinks::shortlink($id, $context, $allow_slugs);
}

function wp_shortlink_header()
{
    $shortlink = wp_get_shortlink(0, 'query');
    if (headers_sent() || empty($shortlink) || PHP_SAPI === 'cli') {
        return;
    }
    header('Link: <' . $shortlink . '>; rel=shortlink', false);
}

/**
 * The post before or after the current one (probe filter-inputs), as the
 * reference finds it: its JOIN, WHERE and ORDER BY written as the reference
 * writes them, each handed to its filter (get_{previous|next}_post_join,
 * _where and _sort; the excluded terms through _excluded_terms first) and
 * the query run as they come back, so a plugin can narrow it (a
 * translation plugin to its language). Published posts, and private ones a
 * signed-in reader may read (every one with read_private_posts, else their
 * own); kept to the current post's terms in $taxonomy when asked, and out of
 * the excluded terms. Null with no current post or no such taxonomy, an
 * empty string when no post qualifies (probe link-template-remaining).
 */
function get_adjacent_post($in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category')
{
    $post = get_post();
    if ($post === null) {
        return null;
    }
    if ($in_same_term && !taxonomy_exists($taxonomy)) {
        return null;
    }
    $adjacent = $previous ? 'previous' : 'next';
    $excluded_terms = !empty($excluded_terms) && !is_array($excluded_terms) ? explode(',', (string) $excluded_terms) : $excluded_terms;
    $excluded_terms = apply_filters("get_{$adjacent}_post_excluded_terms", $excluded_terms);
    $excluded = array_values(array_filter(array_map('intval', (array) $excluded_terms)));
    $terms = [];
    if ($in_same_term) {
        if (!is_object_in_taxonomy($post->post_type, $taxonomy)) {
            return '';
        }
        $terms = array_values(array_diff(array_map('intval', (array) wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids'])), $excluded));
        if ($terms === []) {
            return '';
        }
    }
    $private = 'none';
    if (is_user_logged_in()) {
        $type = get_post_type_object($post->post_type);
        $private = $type && current_user_can($type->cap->read_private_posts) ? 'all' : 'own';
    }
    [$join, $where, $sort] = _minn_posts()->adjacentClauses(Minn\Content\PostRecord::fromRow($post->to_array()), $adjacent, (string) $taxonomy, $terms, $excluded, $private, get_current_user_id());
    $join = apply_filters("get_{$adjacent}_post_join", $join, $in_same_term, $excluded_terms, $taxonomy, $post);
    $where = apply_filters("get_{$adjacent}_post_where", $where, $in_same_term, $excluded_terms, $taxonomy, $post);
    $sort = apply_filters("get_{$adjacent}_post_sort", $sort, $post, $previous ? 'DESC' : 'ASC');
    $found = _minn_posts()->adjacentId((string) $join, (string) $where, (string) $sort);
    return $found > 0 ? get_post($found) : '';
}

/**
 * @internal The post before or after another, as get_adjacent_post() finds
 * it with that one current (the post-navigation-link block's neighbour),
 * kept to its terms in $taxonomy when one is named.
 */
function _minn_adjacent_post(int $postId, bool $previous, string $taxonomy = ''): ?WP_Post
{
    $current = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = get_post($postId);
    $found = get_adjacent_post($taxonomy !== '', '', $previous, $taxonomy !== '' ? $taxonomy : 'category');
    $GLOBALS['post'] = $current;
    return $found instanceof WP_Post ? $found : null;
}

/**
 * The first (or last) post, as a one-post list: kept to the current post's
 * terms, and out of the excluded ones, when asked (probe deprecated); null
 * off a single post, on an attachment, or for a taxonomy that does not exist.
 */
function get_boundary_post($in_same_term = false, $excluded_terms = '', $start = true, $taxonomy = 'category')
{
    $post = get_post();
    if (!$post || !is_single() || is_attachment() || !taxonomy_exists($taxonomy)) {
        return null;
    }
    $args = ['posts_per_page' => 1, 'order' => $start ? 'ASC' : 'DESC', 'update_post_term_cache' => false, 'update_post_meta_cache' => false];
    $excluded = array_map('intval', is_array($excluded_terms) ? $excluded_terms : (empty($excluded_terms) ? [] : explode(',', (string) $excluded_terms)));
    if ($in_same_term || $excluded !== []) {
        $own = $in_same_term ? array_map('intval', (array) wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids'])) : [];
        $args['tax_query'] = [['taxonomy' => $taxonomy, 'terms' => array_merge($own, array_map(static fn (int $id) => -$id, array_diff($excluded, $own)))]];
    }
    return get_posts($args);
}

function get_previous_post($in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    return get_adjacent_post($in_same_term, $excluded_terms, true, $taxonomy);
}

function get_next_post($in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    return get_adjacent_post($in_same_term, $excluded_terms, false, $taxonomy);
}

function wp_is_post_revision($post)
{
    $post = get_post($post);
    if ($post === null || $post->post_type !== 'revision') {
        return false;
    }
    return (int) $post->post_parent;
}

function wp_is_post_autosave($post)
{
    $post = get_post($post);
    if ($post === null || $post->post_type !== 'revision' || !preg_match('/^\d+-autosave/', (string) $post->post_name)) {
        return false;
    }
    return (int) $post->post_parent;
}

/** A post's revisions, newest first unless asked otherwise; none while its revisions are switched off (unless check_enabled is false). */
function wp_get_post_revisions($post = 0, $args = null)
{
    return Minn\Runtime\PostRevisions::all($post, $args);
}

/** A post's newest revision and how many it has; an error for no post, or one whose revisions are off. */
function wp_get_latest_revision_id_and_total_count($post = 0)
{
    $post = get_post($post);
    if (!$post) {
        return new WP_Error('invalid_post', __('Invalid post.'));
    }
    if (!wp_revisions_enabled($post)) {
        return new WP_Error('revisions_not_enabled', __('Revisions not enabled.'));
    }
    $query = new WP_Query();
    $revisions = $query->query(['post_parent' => $post->ID, 'fields' => 'ids', 'post_type' => 'revision', 'post_status' => 'inherit', 'order' => 'DESC', 'orderby' => 'date ID', 'posts_per_page' => 1, 'ignore_sticky_posts' => true]);
    return $revisions ? ['latest_id' => $revisions[0], 'count' => $query->found_posts] : ['latest_id' => 0, 'count' => 0];
}

/** The revisions screen for a post's latest revision, for those who may edit it; null without revisions. */
function wp_get_post_revisions_url($post = 0)
{
    $post = get_post($post);
    if (!$post instanceof WP_Post) {
        return null;
    }
    if ($post->post_type === 'revision') {
        return get_edit_post_link($post);
    }
    $revisions = wp_get_latest_revision_id_and_total_count($post->ID);
    return is_array($revisions) && $revisions['count'] > 0 ? get_edit_post_link($revisions['latest_id']) : null;
}

function wp_revisions_enabled($post)
{
    return Minn\Runtime\PostRevisions::enabled($post);
}

/**
 * The fields a revision keeps, through _wp_post_revision_fields (whose
 * default adds footnotes); the filtered list is what the next call starts
 * from, as on the reference.
 */
function _wp_post_revision_fields($post = [], $deprecated = false)
{
    return Minn\Runtime\PostRevisions::fields($post, $deprecated);
}

/** A revision's row: the post's revision fields, its parent, inherit, revision, its name, and the post's modified time as its date. */
function _wp_post_revision_data($post = [], $autosave = false)
{
    return Minn\Runtime\PostRevisions::data($post, $autosave);
}

/** A revision, or with $autosave the autosave ({id}-autosave-v1), of a post or of the post fields given, written through wp_insert_post, then _wp_put_post_revision. */
function _wp_put_post_revision($post = null, $autosave = false)
{
    $source = PostRevisions::source($post);
    return empty($source['ID']) ? null : PostRevisions::put(_wp_post_revision_data($source, (bool) $autosave));
}

/** The meta keys a type's revisions keep: those registered with revisions_enabled, through wp_post_revision_meta_keys. */
function wp_post_revision_meta_keys($post_type)
{
    $keys = [];
    foreach (get_registered_meta_keys('post', (string) $post_type) + get_registered_meta_keys('post') as $key => $args) {
        if (!empty($args['revisions_enabled'])) {
            $keys[] = (string) $key;
        }
    }
    return apply_filters('wp_post_revision_meta_keys', array_values(array_unique($keys)), $post_type);
}

/** The default on wp_save_post_revision_post_has_changed: a revisioned meta value that differs is a change. */
function wp_check_revisioned_meta_fields_have_changed($post_has_changed, $last_revision, $post)
{
    foreach (wp_post_revision_meta_keys($post->post_type) as $meta_key) {
        if (get_post_meta($post->ID, $meta_key) !== get_post_meta($last_revision->ID, $meta_key)) {
            return true;
        }
    }
    return $post_has_changed;
}

/** The default on _wp_put_post_revision: the post's revisioned meta copied onto the revision. */
function wp_save_revisioned_meta_fields($revision_id, $post_id)
{
    $post_type = get_post_type($post_id);
    foreach ($post_type ? wp_post_revision_meta_keys($post_type) : [] as $meta_key) {
        foreach (metadata_exists('post', (int) $post_id, $meta_key) ? get_post_meta((int) $post_id, $meta_key) : [] as $value) {
            add_metadata('post', (int) $revision_id, $meta_key, wp_slash($value));
        }
    }
}

/** The default on _wp_post_revision_fields: footnotes are revisioned. */
function wp_add_footnotes_to_revision($fields)
{
    $fields['footnotes'] = 'Footnotes';
    return $fields;
}

/** Whitespace evened out for comparing: trimmed, line breaks as one newline, runs of spaces and tabs as one space. */
function normalize_whitespace($str)
{
    return Text::normalizeWhitespace($str);
}

/**
 * How many revisions a post keeps (captured): WP_POST_REVISIONS (all, -1,
 * when unset or true), none for a type without revision support, then
 * wp_revisions_to_keep and wp_{type}_revisions_to_keep.
 */
function wp_revisions_to_keep($post)
{
    return Minn\Runtime\PostRevisions::toKeep($post);
}

/** Whether a post is sticky, through is_sticky (handed the post's id). */
function is_sticky($post_id = 0)
{
    $post_id = absint($post_id) ?: get_the_ID();
    $stickies = get_option('sticky_posts');
    return (bool) apply_filters('is_sticky', is_array($stickies) && in_array((int) $post_id, array_map('intval', $stickies), true), $post_id);
}

/** Adds the post to sticky_posts, then post_stuck with its id; nothing when it already is sticky (probe rest-sticky). */
function stick_post($post_id)
{
    Minn\Runtime\PostWrites::stick($post_id);
}

/** Takes the post out of sticky_posts, then post_unstuck with its id; nothing when it was not sticky (probe rest-sticky). */
function unstick_post($post_id)
{
    Minn\Runtime\PostWrites::unstick($post_id);
}

function get_post_thumbnail_id($post = null)
{
    return Minn\Runtime\PostTags::thumbnailId($post);
}

/** The links between a split post's pages (Front\PageLinks), through wp_link_pages_args, wp_link_pages_link and wp_link_pages; echoed and returned. */
function wp_link_pages($args = '')
{
    $defaults = ['before' => '<p class="post-nav-links">' . __('Pages:'), 'after' => '</p>', 'link_before' => '', 'link_after' => '', 'aria_current' => 'page', 'next_or_number' => 'number', 'separator' => ' ', 'nextpagelink' => __('Next page'), 'previouspagelink' => __('Previous page'), 'pagelink' => '%', 'echo' => 1];
    $parsed = apply_filters('wp_link_pages_args', wp_parse_args($args, $defaults));
    $output = Minn\Front\PageLinks::render($parsed, (int) ($GLOBALS['page'] ?? 1), (int) ($GLOBALS['numpages'] ?? 1), (int) ($GLOBALS['more'] ?? 0), _wp_link_page(...), static fn (string $link, int $i): string => (string) apply_filters('wp_link_pages_link', $link, $i));
    $html = apply_filters('wp_link_pages', $output, $args);
    if (!empty($parsed['echo'])) {
        echo $html;
    }
    return $html;
}

/** The opening anchor of a split post's page $i: the post's address for the first, /2/ after it with pretty permalinks, ?page=2 without them or for a post not yet published. */
function _wp_link_page($i)
{
    $post = get_post();
    $url = get_permalink();
    if ((int) $i > 1) {
        $plain = (string) get_option('permalink_structure') === '' || in_array($post?->post_status, ['draft', 'pending'], true);
        $url = $plain ? add_query_arg('page', (int) $i, $url) : trailingslashit($url) . user_trailingslashit((string) (int) $i, 'single_paged');
    }
    // A preview's page links stay in the preview (probe post-template-remaining).
    if (is_preview()) {
        $url = add_query_arg('preview', 'true', $url);
    }
    return '<a href="' . esc_url($url) . '" class="post-page-numbers">';
}

/** Whether a post has a featured image, through has_post_thumbnail (handed the post as given and the image's id). */
function has_post_thumbnail($post = null)
{
    return Minn\Runtime\PostTags::hasThumbnail($post);
}

function setup_postdata($post)
{
    return Minn\Runtime\PostData::setup($post);
}

/** @internal the globals the loop's template tags read, from generate_postdata */
function _minn_postdata_globals(WP_Post $post): void
{
    Minn\Runtime\PostData::globals($post);
}

function wp_reset_postdata()
{
    $query = $GLOBALS['wp_query'] ?? null;
    if ($query instanceof WP_Query) {
        $query->reset_postdata();
    }
}

function wp_reset_query()
{
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] ?? ($GLOBALS['wp_query'] ?? null);
    wp_reset_postdata();
}

function query_posts($query)
{
    $GLOBALS['wp_query'] = new WP_Query();
    return $GLOBALS['wp_query']->query($query);
}

function get_posts($args = null)
{
    return Minn\Runtime\PostReads::query($args);
}

function get_pages($args = [])
{
    return Pages::get(wp_parse_args($args));
}

/** @internal every page under one ancestor, in list order */
function _minn_page_descendants(array $pages, int $parent): array
{
    return Pages::descendants($pages, $parent);
}

function get_children($args = '', $output = OBJECT)
{
    return Minn\Runtime\PostReads::children($args, $output);
}

function wp_get_recent_posts($args = [], $output = ARRAY_A)
{
    if (is_numeric($args)) {
        $args = ['numberposts' => absint($args)];
    }
    $parsed = wp_parse_args($args, ['numberposts' => 10, 'offset' => 0, 'category' => 0, 'orderby' => 'post_date', 'order' => 'DESC', 'include' => '', 'exclude' => '', 'meta_key' => '', 'meta_value' => '', 'post_type' => 'post', 'post_status' => 'draft, publish, future, pending, private', 'suppress_filters' => true]);
    $posts = get_posts($parsed);
    if ($output === ARRAY_A) {
        return array_map(static fn (WP_Post $p) => $p->to_array(), $posts);
    }
    return $posts;
}

function wp_count_posts($type = 'post', $perm = '')
{
    if (!post_type_exists($type)) {
        return new stdClass();
    }
    $counts = array_fill_keys(array_keys(get_post_stati()), 0);
    foreach (_minn_post_lookup()->countByStatus((string) $type) as $status => $count) {
        $counts[$status] = (string) $count;
    }
    return apply_filters('wp_count_posts', (object) $counts, $type, $perm);
}

function wp_count_attachments($mime_type = '')
{
    return apply_filters('wp_count_attachments', (object) array_map('strval', _minn_post_lookup()->countAttachments()), $mime_type);
}

function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
{
    return Minn\Runtime\PostWrites::insert($postarr, $wp_error, $fire_after_hooks);
}

function wp_after_insert_post($post, $update, $post_before)
{
    Minn\Runtime\PostWrites::afterInsert($post, $update, $post_before);
}

/** The revision of an update, saved after the update's terms and meta, unless the site unhooked revisions from post_updated. */
function wp_save_post_revision_on_insert($post_id, $post, $update)
{
    if (!$update || !has_action('post_updated', 'wp_save_post_revision')) {
        return;
    }
    wp_save_post_revision($post_id);
}

/** A published post that changed its slug keeps the old one on record (_wp_old_slug), as the REST writer does. */
function wp_check_for_changed_slugs($post_id, $post, $post_before)
{
    if ($post->post_status !== 'publish' || is_post_type_hierarchical($post->post_type)) {
        return;
    }
    _minn_post_writer()->rememberOld((int) $post_id, '_wp_old_slug', (string) $post_before->post_name, (string) $post->post_name);
    wp_cache_delete((int) $post_id, 'post_meta');
}

/** A post that stayed published while its day changed keeps the old day on record (_wp_old_date). */
function wp_check_for_changed_dates($post_id, $post, $post_before)
{
    if ($post->post_status !== 'publish' || $post_before->post_status !== 'publish' || is_post_type_hierarchical($post->post_type)) {
        return;
    }
    _minn_post_writer()->rememberOld((int) $post_id, '_wp_old_date', substr((string) $post_before->post_date, 0, 10), substr((string) $post->post_date, 0, 10));
    wp_cache_delete((int) $post_id, 'post_meta');
}

/**
 * The status change announced (probe patch-security). Since 7.1.3 the named
 * hooks fire only for names that exist, so a stored status cannot forge
 * another action's name: {old}_to_{new} when the new status is registered
 * and the old one is too (or is "new"), {new}_{type} when the new status and
 * the post type are registered.
 */
function wp_transition_post_status($new_status, $old_status, $post)
{
    Minn\Runtime\PostWrites::transitionStatus($new_status, $old_status, $post);
}

/**
 * A revision of the post when one is called for (Runtime\PostRevisions).
 * Hooked to post_updated it stands aside: the revision of an update is
 * saved from wp_after_insert_post, once the terms and meta are in;
 * unhooking it from post_updated is how a site turns revisions off.
 */
function wp_save_post_revision($post_id)
{
    $post = doing_action('post_updated') ? null : get_post($post_id);
    return $post === null ? null : PostRevisions::save($post);
}

/**
 * Puts a revision's fields (all the revisioned ones, or those named) back
 * on its post through wp_update_post, which saves a revision of its own
 * (probe plugin-queue). The post's id; what wp_get_post_revision answered
 * when it is not a revision; false when no field was left to restore.
 */
function wp_restore_post_revision($revision, $fields = null)
{
    $revision = wp_get_post_revision($revision, ARRAY_A);
    if (!$revision) {
        return $revision;
    }
    if (!is_array($fields)) {
        $fields = array_keys(_wp_post_revision_fields($revision));
    }
    $update = array_intersect_key($revision, array_flip($fields));
    if ($update === []) {
        return false;
    }
    $update['ID'] = $revision['post_parent'];
    $post_id = wp_update_post(wp_slash($update));
    if (!$post_id || is_wp_error($post_id)) {
        return $post_id;
    }
    update_post_meta($post_id, '_edit_last', get_current_user_id());
    do_action('wp_restore_post_revision', $post_id, $revision['ID']);
    return $post_id;
}

/** Every value of one meta key added from one post (a revision too) to another, which keeps its own. */
function _wp_copy_post_meta($source_post_id, $target_post_id, $meta_key)
{
    foreach (get_post_meta($source_post_id, $meta_key) as $meta_value) {
        add_metadata('post', $target_post_id, $meta_key, wp_slash($meta_value));
    }
}

/** The post type's revisioned meta put back from a revision: each key's values on the post replaced by the revision's, or removed when it has none. */
function wp_restore_post_revision_meta($post_id, $revision_id)
{
    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return;
    }
    foreach (wp_post_revision_meta_keys($post_type) as $meta_key) {
        delete_metadata('post', $post_id, $meta_key);
        _wp_copy_post_meta($revision_id, $post_id, $meta_key);
    }
}

/** A post's newest autosave (by date), by one author or, given 0, any; false when there is none. */
function wp_get_post_autosave($post_id, $user_id = 0)
{
    $autosave = _minn_posts()->newestAutosave((int) $post_id, (int) $user_id);
    return $autosave === null ? false : get_post($autosave->id);
}

/** The preview link's check, on init: with preview_id and preview_nonce, a bad nonce dies (403), a good one sets the preview up (the_preview). */
function _show_post_preview()
{
    if (!isset($_GET['preview_id'], $_GET['preview_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_GET['preview_nonce'], 'post_preview_' . (int) $_GET['preview_id'])) {
        wp_die(__('Sorry, you are not allowed to preview drafts.'), 403);
    }
    add_filter('the_preview', '_set_preview');
}

/** The previewed post: the newest autosave's title, content and excerpt (any author's) on the object handed in, and the preview's meta and terms filters. */
function _set_preview($post)
{
    if (!is_object($post)) {
        return $post;
    }
    $preview = wp_get_post_autosave($post->ID);
    if ($preview instanceof WP_Post) {
        $post->post_content = $preview->post_content;
        $post->post_title = $preview->post_title;
        $post->post_excerpt = $preview->post_excerpt;
    }
    add_filter('get_the_terms', '_wp_preview_terms_filter', 10, 3);
    add_filter('get_post_metadata', '_wp_preview_post_thumbnail_filter', 10, 3);
    add_filter('get_post_metadata', '_wp_preview_meta_filter', 10, 4);
    return $post;
}

/** A previewed post's revisioned meta read from its newest autosave; any other read as it was. */
function _wp_preview_meta_filter($value, $object_id, $meta_key, $single)
{
    $post = get_post();
    if (!$post || (int) $post->ID !== (int) $object_id || !in_array($meta_key, wp_post_revision_meta_keys($post->post_type), true)) {
        return $value;
    }
    $preview = wp_get_post_autosave($post->ID);
    return $preview instanceof WP_Post ? get_post_meta($preview->ID, $meta_key, $single) : $value;
}

/** A previewed post's featured image from the request's _thumbnail_id (as a string, "" when not positive), when preview_id names it. */
function _wp_preview_post_thumbnail_filter($value, $post_id, $meta_key)
{
    $post = get_post();
    if (!$post || (int) $post->ID !== (int) $post_id || $meta_key !== '_thumbnail_id' || empty($_REQUEST['_thumbnail_id']) || empty($_REQUEST['preview_id']) || (int) $post->ID !== (int) $_REQUEST['preview_id']) {
        return $value;
    }
    $thumbnail_id = (int) $_REQUEST['_thumbnail_id'];
    return $thumbnail_id <= 0 ? '' : (string) $thumbnail_id;
}

/** A previewed post's format from the request's post_format: none for standard, the format's term when it exists. */
function _wp_preview_terms_filter($terms, $post_id, $taxonomy)
{
    $post = get_post();
    if (!$post || (int) $post->ID !== (int) $post_id || $taxonomy !== 'post_format' || empty($_REQUEST['post_format'])) {
        return $terms;
    }
    if ($_REQUEST['post_format'] === 'standard') {
        return [];
    }
    $term = get_term_by('slug', 'post-format-' . sanitize_key($_REQUEST['post_format']), 'post_format');
    return $term ? [$term] : $terms;
}

/**
 * Revisions named before 3.6 ({id}-revision, {id}-autosave) given their -v1
 * names. When the newest was one of those and differs from the post (or
 * there are none), a revision of the post itself is added. Always true.
 */
function _wp_upgrade_revisions_of_post($post, $revisions)
{
    global $wpdb;
    $newest = reset($revisions);
    foreach ($revisions as $revision) {
        if (!str_contains((string) $revision->post_name, '-v1')) {
            $wpdb->update($wpdb->posts, ['post_name' => $revision->post_name . '-v1'], ['ID' => $revision->ID]);
            clean_post_cache($revision->ID);
        }
    }
    if ($newest === false || (!str_contains((string) $newest->post_name, '-v1') && _minn_revision_differs($post, $newest))) {
        _wp_put_post_revision($post);
    }
    return true;
}

/** @internal Whether a revision's revisioned fields differ from the post's. */
function _minn_revision_differs(WP_Post $post, WP_Post $revision): bool
{
    foreach (array_keys(_wp_post_revision_fields($post)) as $field) {
        if ((string) $post->$field !== (string) $revision->$field) {
            return true;
        }
    }
    return false;
}

/** Posts into the object cache, each only where none is cached yet. */
function update_post_cache(&$posts)
{
    if (!$posts) {
        return;
    }
    $data = [];
    foreach ($posts as $post) {
        $post = empty($post->filter) || $post->filter !== 'raw' ? sanitize_post($post, 'raw') : $post;
        $data[$post->ID] = $post;
    }
    wp_cache_add_multiple($data, 'posts');
}

/** A wp_untrash_post_status callback: a post comes back from the trash in the status it had. */
function wp_untrash_post_set_previous_status($new_status, $post_id, $previous_status)
{
    return $previous_status;
}

function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true)
{
    return Minn\Runtime\PostWrites::update($postarr, $wp_error, $fire_after_hooks);
}

function wp_publish_post($post)
{
    Minn\Runtime\PostWrites::publish($post);
}

/** The publish_future_post event's work: a scheduled post whose time has come goes out; one early is scheduled again for its time. */
function check_and_publish_future_post($post)
{
    Minn\Runtime\PostWrites::publishFuture($post);
}

/** The default on future_post and future_page: a scheduled post is put on the cron calendar for its time, which is how WordPress publishes it. */
function _future_post_hook($deprecated, $post)
{
    wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
    wp_schedule_single_event(strtotime(get_gmt_from_date($post->post_date) . ' GMT'), 'publish_future_post', [$post->ID]);
}

/** The first default on transition_post_status: a post that stops being scheduled leaves the cron calendar. */
/**
 * The default on transition_post_status (probe post-remaining): a post
 * becoming published gets its permalink as its guid when it has none (the
 * cached copy is left as it was) and runs the deprecated private_to_published;
 * every transition clears the post's scheduled publishing, which
 * _future_post_hook sets again for a future post.
 */
function _transition_post_status($new_status, $old_status, $post)
{
    if ($old_status !== 'publish' && $new_status === 'publish') {
        if (get_the_guid($post->ID) === '') {
            _minn_post_writer()->update((int) $post->ID, ['guid' => get_permalink($post->ID)]);
        }
        do_action_deprecated('private_to_published', [$post->ID], '2.3.0', 'private_to_publish');
    }
    wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
}

/** The default on publish_post and publish_page: a site that has published something is no longer fresh. */
function _delete_option_fresh_site()
{
    update_option('fresh_site', '0', false);
}

/** The default on wp_trash_post and before_delete_post: a page leaving the site stops being the front or posts page, and any post leaving stops being sticky (probe post-remaining). */
function _reset_front_page_settings_for_post($post_id)
{
    $post = get_post($post_id);
    if ($post === null) {
        return;
    }
    if ($post->post_type === 'page') {
        if ((int) get_option('page_on_front') === $post->ID) {
            update_option('show_on_front', 'posts');
            update_option('page_on_front', 0);
        }
        if ((int) get_option('page_for_posts') === $post->ID) {
            update_option('page_for_posts', 0);
        }
    }
    unstick_post($post->ID);
}

/** The default on before_delete_post: a deleted privacy policy page is no longer the site's policy. */
function _reset_privacy_policy_page_for_post($post_id)
{
    $post = get_post($post_id);
    if ($post !== null && $post->post_type === 'page' && (int) get_option('wp_page_for_privacy_policy') === $post->ID) {
        update_option('wp_page_for_privacy_policy', 0);
    }
}

/** The default on save_post and delete_post: the calendar is drawn again. */
function delete_get_calendar_cache()
{
    wp_cache_delete('get_calendar', 'calendar');
}

function wp_trash_post($post_id = 0)
{
    return Minn\Runtime\PostWrites::trash($post_id);
}

function wp_untrash_post($post_id = 0)
{
    return Minn\Runtime\PostWrites::untrash($post_id);
}

function wp_delete_post($post_id = 0, $force_delete = false)
{
    return Minn\Runtime\PostWrites::delete($post_id, $force_delete);
}

function wp_delete_post_revision($revision)
{
    return Minn\Runtime\PostRevisions::delete($revision);
}


function wp_set_post_categories($post_id = 0, $post_categories = [], $append = false)
{
    return Minn\Runtime\PostWrites::setCategories($post_id, $post_categories, $append);
}

function wp_set_post_tags($post_id = 0, $tags = '', $append = false)
{
    return Minn\Runtime\PostWrites::setTags($post_id, $tags, $append);
}

function get_post_type_object($post_type)
{
    return Registered::postType($post_type);
}

function post_type_exists($post_type)
{
    return Registered::postTypeExists($post_type);
}

function is_post_type_hierarchical($post_type)
{
    return Registered::hierarchicalType($post_type);
}

function is_post_type_viewable($post_type)
{
    return Registered::viewableType($post_type);
}

function is_post_status_viewable($post_status)
{
    return Registered::viewableStatus($post_status);
}

function is_post_publicly_viewable($post = null)
{
    return Minn\Runtime\PostReads::publiclyViewable($post);
}

function get_post_types($args = [], $output = 'names', $operator = 'and')
{
    return Registered::postTypes($args, $output, $operator);
}

function register_post_type($post_type, $args = [])
{
    $post_type = sanitize_key((string) $post_type);
    if ($post_type === '' || strlen($post_type) > 20) {
        _doing_it_wrong(__FUNCTION__, 'Post type names must be between 1 and 20 characters in length.', '4.2.0');
        return new WP_Error('post_type_length_invalid', 'Post type names must be between 1 and 20 characters in length.');
    }
    // The object does each step itself, in the reference's order (probe type-object-methods).
    $object = new WP_Post_Type($post_type, $args);
    $object->add_supports();
    $object->add_rewrite_rules();
    $object->register_meta_boxes();
    Runtime::registry()->adoptPostType($object);
    $object->add_hooks();
    $object->register_taxonomies();
    do_action('registered_post_type', $post_type, $object);
    do_action("registered_post_type_{$post_type}", $post_type, $object);
    return $object;
}

function unregister_post_type($post_type)
{
    if (!post_type_exists($post_type)) {
        return new WP_Error('invalid_post_type', 'Invalid post type.');
    }
    $object = get_post_type_object($post_type);
    if ($object->_builtin) {
        return new WP_Error('invalid_post_type', 'Unregistering a built-in post type is not allowed');
    }
    $object->remove_supports();
    $object->remove_rewrite_rules();
    $object->unregister_meta_boxes();
    $object->remove_hooks();
    $object->unregister_taxonomies();
    Runtime::registry()->unregisterPostType((string) $post_type);
    do_action('unregistered_post_type', $post_type);
    return true;
}

/** A type's full labels from its own, then post_type_labels_{$name} (Runtime\Registry::labels; probe post-remaining). */
function get_post_type_labels($post_type_object)
{
    $name = (string) ($post_type_object->name ?? '');
    $labels = Runtime::registry()->labels((array) ($post_type_object->labels ?? []), isset($post_type_object->label) ? (string) $post_type_object->label : null, empty($post_type_object->hierarchical) ? 'post' : 'page', $name);
    return apply_filters("post_type_labels_{$name}", (object) $labels);
}

/** A type's capability names (Runtime\Registry::capabilities; probe post-remaining); a mapped type's meta capabilities are recorded. */
function get_post_type_capabilities($args)
{
    $caps = Minn\Runtime\Registry::capabilities($args->capability_type ?? 'post', (array) ($args->capabilities ?? []), !empty($args->map_meta_cap));
    if (!empty($args->map_meta_cap)) {
        _post_type_meta_capabilities($caps);
    }
    return (object) $caps;
}

function get_all_post_type_supports($post_type)
{
    return Runtime::registry()->supports((string) $post_type);
}

function post_type_supports($post_type, $feature)
{
    return Registered::supports($post_type, $feature);
}

function add_post_type_support($post_type, $feature, ...$args)
{
    foreach ((array) $feature as $one) {
        Runtime::registry()->addSupport((string) $post_type, (string) $one, $args);
    }
}

function remove_post_type_support($post_type, $feature)
{
    Runtime::registry()->removeSupport((string) $post_type, (string) $feature);
}

function get_post_types_by_support($feature, $operator = 'and')
{
    $features = (array) $feature;
    $out = [];
    foreach (Runtime::registry()->postTypes() as $name => $row) {
        $supports = array_keys(Runtime::registry()->supports((string) $name));
        $matches = count(array_intersect($features, $supports));
        if (($operator === 'and' && $matches === count($features)) || ($operator === 'or' && $matches > 0) || ($operator === 'not' && $matches === 0)) {
            $out[] = $name;
        }
    }
    return $out;
}

function get_post_status_object($post_status)
{
    return Registered::status($post_status);
}

function get_post_stati($args = [], $output = 'names', $operator = 'and')
{
    return Registered::statuses($args, $output, $operator);
}

function register_post_status($post_status, $args = [])
{
    $post_status = sanitize_key((string) $post_status);
    $row = Runtime::registry()->registerStatus($post_status, (array) $args);
    return (object) $row;
}

function get_post_statuses()
{
    return ['draft' => 'Draft', 'pending' => 'Pending Review', 'private' => 'Private', 'publish' => 'Published'];
}

function get_page_statuses()
{
    return ['draft' => 'Draft', 'private' => 'Private', 'publish' => 'Published'];
}

function get_available_post_statuses($type = 'post')
{
    $counts = wp_count_posts($type);
    return array_keys(array_filter(get_object_vars($counts)));
}

function wp_get_post_categories($post_id = 0, $args = [])
{
    return Minn\Runtime\PostReads::categories($post_id, $args);
}

function wp_get_post_tags($post_id = 0, $args = [])
{
    return wp_get_post_terms($post_id, 'post_tag', $args);
}

function wp_get_post_terms($post_id = 0, $taxonomy = 'post_tag', $args = [])
{
    return wp_get_object_terms((int) $post_id, $taxonomy, wp_parse_args($args, ['fields' => 'all']));
}

function wp_set_post_terms($post_id = 0, $terms = '', $taxonomy = 'post_tag', $append = false)
{
    return Minn\Runtime\PostWrites::setTerms($post_id, $terms, $taxonomy, $append);
}

function get_the_author_meta($field = '', $user_id = false)
{
    $user = $user_id === false ? ($GLOBALS['authordata'] ?? null) : get_userdata((int) $user_id);
    if (!$user instanceof WP_User) {
        return '';
    }
    // An empty field names nothing: '' rather than every meta value (probe template-tags-remaining).
    $value = $field === '' ? '' : ($field === 'ID' ? $user->ID : $user->get($field));
    if ($field === 'ID') {
        $value = (int) $user->ID;
    }
    if (in_array($field, ['login', 'pass', 'nicename', 'email', 'url', 'registered', 'activation_key', 'status'], true)) {
        $value = $user->get('user_' . $field);
    }
    return apply_filters("get_the_author_{$field}", $value === null || $value === false ? '' : $value, $user_id === false ? $user->ID : $user_id, $user_id === false ? $user->ID : false);
}

function the_author_meta($field = '', $user_id = false)
{
    echo apply_filters("the_author_{$field}", get_the_author_meta($field, $user_id), $user_id);
}

function get_the_author($deprecated = '')
{
    $author = $GLOBALS['authordata'] ?? null;
    return apply_filters('the_author', $author instanceof WP_User ? $author->display_name : null);
}

function the_author($deprecated = '', $deprecated_echo = true)
{
    echo get_the_author();
    return get_the_author();
}

function get_author_posts_url($author_id, $author_nicename = '')
{
    $author_id = (int) $author_id;
    if ($author_nicename === '') {
        $user = get_userdata($author_id);
        $author_nicename = $user ? $user->user_nicename : '';
    }
    $permalinks = Runtime::current()->get('permalinks');
    $link = $permalinks !== null && $permalinks->isPretty() ? home_url('/author/' . ($author_nicename === '' ? '' : $author_nicename . '/')) : home_url('/?author=' . $author_id);
    return apply_filters('author_link', $link, $author_id, $author_nicename);
}

function get_the_author_posts_link()
{
    $author = $GLOBALS['authordata'] ?? null;
    if (!$author instanceof WP_User) {
        return '';
    }
    $name = get_the_author();
    $link = sprintf('<a href="%1$s" rel="author">%2$s</a>', esc_url(get_author_posts_url($author->ID)), $name);
    // The link, the author's name and the title it once carried (probe hook-inputs).
    return apply_filters('the_author_posts_link', $link, $name, sprintf(__('Posts by %s'), $name));
}

/** How many posts of its type the current post's author has published; 0 with no post (probe theme-symbols). */
function get_the_author_posts()
{
    $post = get_post();
    return $post ? (int) count_user_posts($post->post_author, $post->post_type) : 0;
}

function count_user_posts($userid, $post_type = 'post', $public_only = false)
{
    $count = _minn_post_lookup()->countByAuthor((int) $userid, array_map('strval', (array) $post_type), $public_only ? ['publish'] : ['publish', 'private']);
    return apply_filters('get_usernumposts', (string) $count, $userid, $post_type, $public_only);
}

/**
 * The author's website as a link (rel="author external", titled "Visit
 * {name}'s website" unless asked not to), or just the name when the author
 * has no website, through the_author_link.
 */
function get_the_author_link($use_title_attr = true)
{
    $url = get_the_author_meta('url');
    if (!$url) {
        return get_the_author();
    }
    $title = $use_title_attr ? ' title="' . esc_attr(sprintf(__('Visit %s&#8217;s website'), get_the_author())) . '"' : '';
    $link = sprintf('<a href="%1$s"%2$s rel="author external">%3$s</a>', esc_url($url), $title, get_the_author());
    return apply_filters('the_author_link', $link, $url, $GLOBALS['authordata'] ?? null);
}

/** Prints the author's website link, or the name. */
function the_author_link($use_title_attr = true)
{
    echo get_the_author_link($use_title_attr);
}

/** Prints how many posts of its type the current post's author has published. */
function the_author_posts()
{
    echo get_the_author_posts();
}

/** Whether more than one author has published posts (kept in the is_multi_author transient until a post changes status). */
function is_multi_author()
{
    $multi = get_transient('is_multi_author');
    if ($multi === false) {
        $multi = _minn_post_lookup()->publishingAuthors('post', 2) > 1 ? 1 : 0;
        set_transient('is_multi_author', $multi);
    }
    return apply_filters('is_multi_author', (bool) $multi);
}

/** Whether the block editor edits a post type: it must exist and be visible in REST; attachments and revisions never are. */
function use_block_editor_for_post_type($post_type)
{
    $type = get_post_type_object($post_type);
    $use = $type !== null && !empty($type->show_in_rest) && $post_type !== 'attachment' && $post_type !== 'revision';
    return (bool) apply_filters('use_block_editor_for_post_type', $use, $post_type);
}

/** The post meta core registers on init: a pattern's sync status (probe meta-registry). */
function wp_create_initial_post_meta()
{
    register_meta('post', 'wp_pattern_sync_status', [
        'sanitize_callback' => 'sanitize_text_field',
        'single' => true,
        'type' => 'string',
        'show_in_rest' => ['schema' => ['type' => 'string', 'enum' => ['partial', 'unsynced']]],
        'object_subtype' => 'wp_block',
    ]);
}

function register_post_meta($post_type, $meta_key, array $args)
{
    $args['object_subtype'] = $post_type;
    return register_meta('post', $meta_key, $args);
}

function unregister_post_meta($post_type, $meta_key)
{
    return unregister_meta_key('post', $meta_key, $post_type);
}

function delete_post_meta_by_key($post_meta_key)
{
    return delete_metadata('post', null, $post_meta_key, '', true);
}

function get_the_guid($post = 0)
{
    $post = get_post($post);
    $post_guid = isset($post->guid) ? $post->guid : '';
    $post_id = isset($post->ID) ? $post->ID : 0;
    return apply_filters('get_the_guid', $post_guid, $post_id);
}

function the_guid($post = 0)
{
    $post = get_post($post);
    $post_guid = isset($post->guid) ? get_the_guid($post) : '';
    $post_id = isset($post->ID) ? $post->ID : 0;
    echo apply_filters('the_guid', $post_guid, $post_id);
}

function wp_get_post_revision(&$post, $output = OBJECT, $filter = 'raw')
{
    $post = get_post($post, OBJECT, $filter);
    if (!$post) {
        return $post;
    }
    if ($post->post_type !== 'revision') {
        return null;
    }
    if ($output === OBJECT) {
        return $post;
    }
    if ($output === ARRAY_A) {
        return get_object_vars($post);
    }
    if ($output === ARRAY_N) {
        return array_values(get_object_vars($post));
    }
    return $post;
}

function the_ID()
{
    echo (int) get_the_ID();
}

/**
 * Every field of a post, object or array, through sanitize_post_field for
 * the context, then marked with it; a post already in that context is
 * returned as it is.
 */
function sanitize_post($post, $context = 'display')
{
    return Minn\Runtime\PostFields::sanitize($post, $context);
}

/** A post column as the reference hands it out: raw, escaped for edit forms, cast for the integer columns. */
function sanitize_post_field($field, $value, $post_id, $context = 'display')
{
    return Minn\Runtime\PostFields::value($field, $value, $post_id, $context);
}

/**
 * @internal one post column through the context's hooks and escaping, as
 * probed: a post_* column runs edit_post_X then X_edit_pre, pre_post_X then
 * X_save_pre, and X for display; any other runs edit_post_Y, pre_post_Y then
 * Y_pre, post_Y. The edit context escapes the four text columns for a
 * textarea (the content stays as it is for the rich editor) and the rest for
 * an attribute.
 */
function _minn_sanitize_post_value(string $field, $value, $post_id, string $context)
{
    return Minn\Runtime\PostFields::value($field, $value, $post_id, $context);
}

/** The article's class list; see Minn\Content\PostClasses for the order. */
function get_post_class($css_class = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return [];
    }
    $extra = is_array($css_class) ? $css_class : preg_split('/\s+/', trim((string) $css_class), -1, PREG_SPLIT_NO_EMPTY);
    // In the reference's order (probe filter-inputs-2): the featured image (while the theme supports them), then
    // sticky, then the public taxonomies through post_class_taxonomies (handed the classes so far), whose terms
    // add their own classes.
    $required = post_password_required($post);
    $format = post_type_supports($post->post_type, 'post-formats') ? (string) (get_post_format($post) ?: '') : null;
    $thumbnail = $post->post_type !== 'attachment' && current_theme_supports('post-thumbnails') && has_post_thumbnail($post->ID);
    $sticky = is_sticky($post->ID) && is_home() && !is_paged();
    $build = static fn (array $terms): array => PostClasses::build(
        (array) $post,
        array_map(static fn ($c) => PostClasses::htmlClass((string) $c), $extra),
        $format,
        $thumbnail,
        $sticky,
        $required,
        $post->post_password !== '',
        $terms,
    );
    $terms = [];
    foreach ((array) apply_filters('post_class_taxonomies', get_taxonomies(['public' => true]), $post->ID, $build([]), $extra) as $taxonomy) {
        if (!is_object_in_taxonomy($post->post_type, (string) $taxonomy)) {
            continue;
        }
        foreach ((array) (get_the_terms($post->ID, (string) $taxonomy) ?: []) as $term) {
            $terms[] = ['taxonomy' => $term->taxonomy, 'slug' => (string) $term->slug, 'term_id' => (int) $term->term_id];
        }
    }
    return array_unique(apply_filters('post_class', $build($terms), $extra, $post->ID));
}

/** The body's class list: the tokens the classic renderer stood for this page, the caller's extras, then the body_class filter. */
/**
 * The body classes: the themed page's, or off the front the main query's
 * (Theme\BodyClasses::forView), then the classes given, a list as it is and
 * a string split on spaces, through body_class (probe post-template-remaining).
 */
function get_body_class($css_class = '')
{
    $stored = Runtime::current()->get('classic_body_classes');
    $classes = is_array($stored) ? array_map('strval', $stored) : _minn_query_body_classes();
    $extra = is_array($css_class) ? array_map('strval', $css_class) : preg_split('/\s+/', trim((string) $css_class), -1, PREG_SPLIT_NO_EMPTY);
    array_push($classes, ...array_filter((array) $extra, static fn ($c) => $c !== ''));
    return array_unique(array_map('esc_attr', (array) apply_filters('body_class', $classes, $extra)));
}

function body_class($css_class = '')
{
    echo 'class="' . esc_attr(implode(' ', get_body_class($css_class))) . '"';
}

function post_class($css_class = '', $post = null)
{
    echo 'class="' . esc_attr(implode(' ', get_post_class($css_class, $post))) . '"';
}

/** The page template file a page chose, "" for the default, false when there is no post. */
function get_page_template_slug($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $template = (string) get_post_meta($post->ID, '_wp_page_template', true);
    return $template === 'default' ? '' : $template;
}

/** True on a singular view whose page template is (one of) the names given; "default" means none. */
function is_page_template($template = '')
{
    if (!is_singular()) {
        return false;
    }
    $slug = (string) get_page_template_slug(get_queried_object_id());
    if ($template === '') {
        return $slug !== '';
    }
    foreach ((array) $template as $name) {
        if ($name === $slug || ($name === 'default' && $slug === '') || (str_contains((string) $name, '.') && basename((string) $name) === basename($slug))) {
            return true;
        }
    }
    return false;
}

/** The posts, their meta and their terms read in one query each for a list (rows the engine already holds are not read again). */
function _prime_post_caches($ids, $update_term_cache = true, $update_meta_cache = true)
{
    Minn\Runtime\PostReads::prime($ids, $update_term_cache, $update_meta_cache);
}

function update_post_caches(&$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true)
{
    Minn\Runtime\PostReads::updateCaches($posts, $post_type, $update_term_cache, $update_meta_cache);
}

function update_post_thumbnail_cache($wp_query = null)
{
    return null;
}

/** An attachment page shows the file's own link ahead of its content; any other post passes through. */
/** An attachment's content after its player (audio, video) or its medium-sized link, through prepend_attachment (probe post-template-remaining). */
function prepend_attachment($content)
{
    $post = get_post();
    if (!$post || $post->post_type !== 'attachment') {
        return $content;
    }
    if (wp_attachment_is('video', $post)) {
        $meta = wp_get_attachment_metadata(get_the_ID());
        $atts = ['src' => wp_get_attachment_url()];
        if (!empty($meta['width']) && !empty($meta['height'])) {
            $atts['width'] = (int) $meta['width'];
            $atts['height'] = (int) $meta['height'];
        }
        $shown = wp_video_shortcode($atts);
    } elseif (wp_attachment_is('audio', $post)) {
        $shown = wp_audio_shortcode(['src' => wp_get_attachment_url()]);
    } else {
        $shown = '<p class="attachment">' . wp_get_attachment_link(0, 'medium', false) . '</p>';
    }
    return apply_filters('prepend_attachment', $shown) . "\n" . $content;
}

/** @internal The body classes the main query stands for, off the front (probe post-template-remaining). */
function _minn_query_body_classes(): array
{
    $query = $GLOBALS['wp_query'] ?? null;
    if (!$query instanceof WP_Query) {
        return [];
    }
    $object = $query->get_queried_object();
    $singular = $query->is_singular() && $object instanceof WP_Post ? ($query->is_page() ? 'page' : (string) $object->post_type) : null;
    $template = $singular === null ? '' : (string) get_page_template_slug($object);
    $parent = get_template() !== get_stylesheet() ? get_template() : null;
    $facts = new Minn\Theme\BodyFacts(loggedIn: is_user_logged_in(), customLogo: has_custom_logo(), embedResponsive: current_theme_supports('responsive-embeds') !== false);
    return Minn\Theme\BodyClasses::forView($singular, (new Minn\Theme\QueryClasses(Runtime::current()->db))->of($query), $template === '' ? null : $template, get_stylesheet(), $parent, $facts);
}

/** The newest published post's date (probe plugin-queue): local for 'blog', GMT for 'gmt', GMT with microseconds for 'server'; false for another timezone or an unknown type. */
function get_lastpostdate($timezone = 'server', $post_type = 'any')
{
    return Minn\Runtime\PostReads::lastDate($timezone, $post_type);
}

/** The newest change to a published post: its modified stamp, or its date when that is later. */
function get_lastpostmodified($timezone = 'server', $post_type = 'any')
{
    return Minn\Runtime\PostReads::lastModified($timezone, $post_type);
}

/**
 * The newest published date (or modified stamp) in a timezone (probe
 * post-more): local for 'blog', GMT for 'gmt', GMT with microseconds for
 * 'server'; among the public types, or of one public type. False for another
 * field or timezone, a type that is not public, or a list of types.
 */
function _get_last_post_time($timezone, $field, $post_type = 'any')
{
    return Minn\Runtime\PostReads::lastTime($timezone, $field, $post_type);
}

/** Every descendant page of one page within the caller's own list, preorder. */
function get_page_children($page_id, $pages)
{
    return \Minn\Support\Lists::descendants(
        array_values((array) $pages),
        (int) $page_id,
        static fn ($p) => (int) (is_object($p) ? $p->ID : ($p['ID'] ?? 0)),
        static fn ($p) => (int) (is_object($p) ? $p->post_parent : ($p['post_parent'] ?? 0)),
    );
}

/** A slug unique within the posts table, the writer's own -2 counting. */
/** A slug no other post holds, through the reference's slug filters; drafts, pending posts and revisions keep theirs. */
function wp_unique_post_slug($slug, $post_id, $post_status, $post_type, $post_parent)
{
    return Minn\Runtime\PostWrites::uniqueSlug($slug, $post_id, $post_status, $post_type, $post_parent);
}

function the_author_posts_link($deprecated = '')
{
    echo get_the_author_posts_link();
}

function get_extended($post)
{
    return Minn\Content\MoreTag::split((string) $post);
}

function get_page($page, $output = OBJECT, $filter = 'raw')
{
    return get_post($page, $output, $filter);
}

function get_post_datetime($post = null, $field = 'date', $source = 'local')
{
    $post = get_post($post);
    if (!$post) {
        return false;
    }
    $column = ($field === 'modified' ? 'post_modified' : 'post_date') . ($source === 'gmt' ? '_gmt' : '');
    $time = (string) ($post->$column ?? '');
    if ($time === '' || str_starts_with($time, '0000-00-00')) {
        return false;
    }
    $zone = $source === 'gmt' ? new DateTimeZone('UTC') : wp_timezone();
    return date_create_immutable_from_format('Y-m-d H:i:s', $time, $zone) ?: false;
}

function post_exists($title, $content = '', $date = '', $type = '', $status = '')
{
    $types = $type === '' ? array_values(get_post_types(['public' => true])) : [(string) $type];
    return _minn_post_lookup()->idByTitle((string) $title, $types) ?? 0;
}

function _truncate_post_slug($slug, $length = 200)
{
    return Minn\Content\Slug::truncate((string) $slug, (int) $length);
}

function use_block_editor_for_post($post)
{
    $post = get_post($post);
    $use = $post ? use_block_editor_for_post_type($post->post_type) : false;
    return (bool) apply_filters('use_block_editor_for_post', $use, $post);
}

function update_postmeta_cache($post_ids)
{
    return update_meta_cache('post', $post_ids);
}

/**
 * A user's row in the object cache, with its login, email and nicename
 * pointing at the id (probe pluggable-remaining). The engine reads users from
 * the database; this is for the plugins that read the cache, and
 * clean_user_cache empties it again.
 */
function update_user_caches($user)
{
    if (!is_object($user) || empty($user->ID)) {
        return false;
    }
    $row = $user instanceof WP_User ? $user->data : $user;
    wp_cache_add((int) $row->ID, $row, 'users');
    wp_cache_add((string) $row->user_login, (int) $row->ID, 'userlogins');
    wp_cache_add((string) $row->user_email, (int) $row->ID, 'useremail');
    wp_cache_add((string) $row->user_nicename, (int) $row->ID, 'userslugs');
    return null;
}

function set_post_type($post_id = 0, $post_type = 'post')
{
    $type = sanitize_post_field('post_type', (string) $post_type, (int) $post_id, 'db');
    $changed = _minn_post_writer()->setType((int) $post_id, $type);
    if ($changed) {
        clean_post_cache((int) $post_id);
    }
    return $changed ? 1 : 0;
}

function the_modified_date($format = '', $before = '', $after = '', $display = true)
{
    $date = $before . get_the_modified_date($format) . $after;
    $date = apply_filters('the_modified_date', $date, $format, $before, $after);
    if (!$display) {
        return $date;
    }
    echo $date;
}

function the_modified_time($format = '')
{
    echo apply_filters('the_modified_time', get_the_modified_time($format), $format);
}

/** The pages walked as a list, by the caller's walker or Walker_Page, each parent among them known to have children. */
function walk_page_tree($pages, $depth, $current_page, $args)
{
    $walker = empty($args['walker']) ? new Walker_Page() : $args['walker'];
    foreach ((array) $pages as $page) {
        if ($page->post_parent) {
            $args['pages_with_children'][$page->post_parent] = true;
        }
    }
    return $walker->walk($pages, $depth, $args, $current_page);
}

/** The pages walked as options, by the caller's walker or Walker_PageDropdown. */
function walk_page_dropdown_tree(...$args)
{
    $walker = empty($args[2]['walker']) ? new Walker_PageDropdown() : $args[2]['walker'];
    return $walker->walk(...$args);
}

function get_post_mime_types()
{
    $types = [];
    foreach (Minn\Media\Kind::POST_MIME_TYPES as $pattern => [$label, $manage, $singular, $plural]) {
        $types[$pattern] = [__($label), __($manage), _n_noop($singular, $plural)];
    }
    return apply_filters('post_mime_types', $types);
}

/** wp_insert_post_parent's default: no parent that would make the post its own ancestor (0 instead). */
function wp_check_post_hierarchy_for_loops($post_parent, $post_id)
{
    $post_parent = (int) $post_parent;
    $post_id = (int) $post_id;
    if ($post_parent === 0 || $post_id === 0) {
        return $post_parent;
    }
    $seen = [];
    for ($ancestor = $post_parent; $ancestor > 0 && !isset($seen[$ancestor]); $ancestor = (int) wp_get_post_parent_id($ancestor)) {
        if ($ancestor === $post_id) {
            return 0;
        }
        $seen[$ancestor] = true;
    }
    return $post_parent;
}

/**
 * pre_wp_unique_post_slug's default: a template's slug is the engine's own
 * concern (Minn\Rest\TemplatesController keeps it unique per theme), so
 * this hands every slug back as it came.
 */
/**
 * A template's slug kept unique within its theme (probe template-family):
 * another template or part of the same type and theme already holding it
 * pushes it to -2, -3 and on. Its own slug is no clash. Other post types, and
 * a slug something has already settled, are left as given.
 */
function wp_filter_wp_template_unique_post_slug($override_slug, $slug, $post_id, $post_status, $post_type)
{
    if ($override_slug !== null || !in_array($post_type, ['wp_template', 'wp_template_part'], true)) {
        return $override_slug;
    }
    $terms = $post_id ? get_the_terms((int) $post_id, 'wp_theme') : false;
    $theme = is_array($terms) && $terms !== [] ? $terms[0]->name : get_stylesheet();
    $taken = static fn (string $candidate): bool => get_posts(['post_type' => $post_type, 'name' => $candidate, 'post_status' => 'any', 'post__not_in' => [(int) $post_id], 'fields' => 'ids', 'numberposts' => 1, 'no_found_rows' => true,
        'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme]]]) !== [];
    $candidate = (string) $slug;
    for ($n = 2; $taken($candidate); $n++) {
        $candidate = "{$slug}-{$n}";
    }
    return $candidate;
}

/** wp_insert_post_data's default, for the customizer's changesets, which Minn does not keep: the data as it came. */
function _wp_customize_changeset_filter_insert_post_data($post_data, $supplied_post_data)
{
    return $post_data;
}

/** The cache key for a type's post counts: a user who may not read its private posts counts only what they can read, so gets their own. */
function _count_posts_cache_key($type = 'post', $perm = '')
{
    $key = 'posts-' . $type;
    $object = get_post_type_object((string) $type);
    if ($perm === 'readable' && is_user_logged_in() && $object !== null && !current_user_can($object->cap->read_private_posts)) {
        $key .= '_' . $perm . '_' . get_current_user_id();
    }
    return $key;
}

/** A post's date (or modified date) as a Unix timestamp, or false. */
function get_post_timestamp($post = null, $field = 'date')
{
    $datetime = get_post_datetime($post, $field);
    return $datetime === false ? false : $datetime->getTimestamp();
}

function wp_post_mime_type_where($post_mime_types, $table_alias = '')
{
    return Minn\Runtime\PostReads::mimeTypeWhere($post_mime_types, $table_alias);
}

/** @internal a post format archive asks for the format's term and the types the format applies to */
function _post_format_request($qvs)
{
    if (!isset($qvs['post_format'])) {
        return $qvs;
    }
    $slugs = get_post_format_slugs();
    if (isset($slugs[$qvs['post_format']])) {
        $qvs['post_format'] = 'post-format-' . $slugs[$qvs['post_format']];
    }
    $taxonomy = get_taxonomy('post_format');
    if (!is_admin() && $taxonomy) {
        $qvs['post_type'] = $taxonomy->object_type;
    }
    return $qvs;
}

/** @internal a format's term reads by the format's name (probe post-format-terms) */
function _post_format_get_term($term)
{
    if (isset($term->slug)) {
        $term->name = get_post_format_string(preg_replace('/^post-format-/', '', (string) $term->slug));
    }
    return $term;
}

/** @internal format terms in a term list read by their format's names; a list of names is read as format slugs (probe post-format-terms) */
function _post_format_get_terms($terms, $taxonomies, $args)
{
    if (!in_array('post_format', (array) $taxonomies, true)) {
        return $terms;
    }
    foreach ((array) $terms as $order => $term) {
        if (($args['fields'] ?? '') === 'names') {
            $terms[$order] = get_post_format_string(preg_replace('/^post-format-/', '', (string) $term));
        } elseif (isset($term->taxonomy) && $term->taxonomy === 'post_format') {
            $terms[$order] = _post_format_get_term($term);
        }
    }
    return $terms;
}

/** @internal format terms among a post's terms read by their format's names (probe post-format-terms) */
function _post_format_wp_get_object_terms($terms)
{
    foreach ((array) $terms as $order => $term) {
        if (isset($term->taxonomy) && $term->taxonomy === 'post_format') {
            $terms[$order] = _post_format_get_term($term);
        }
    }
    return $terms;
}

function get_posts_by_author_sql($post_type, $full = true, $post_author = null, $public_only = false)
{
    return Minn\Runtime\PostReads::byAuthorSql($post_type, $full, $post_author, $public_only);
}

/** @internal The feed link after a listed author or user (feed or feed_image given): an image link, or the feed text in brackets. */
function _minn_author_feed_link(WP_User $author, string $name, array $args): string
{
    if (empty($args['feed_image']) && empty($args['feed'])) {
        return '';
    }
    $image = !empty($args['feed_image']);
    $alt = empty($args['feed']) ? '' : ' alt="' . esc_attr($args['feed']) . '"';
    $label = $image ? '<img src="' . esc_url($args['feed_image']) . '" style="border: none;"' . $alt . ' />' : (empty($args['feed']) ? $name : $args['feed']);
    $feed = '<a href="' . get_author_feed_link($author->ID, $args['feed_type']) . '">' . $label . '</a>';
    return ' ' . ($image ? $feed : '(' . $feed . ')');
}

/**
 * The site's authors (probe plugin-queue): links to their posts, or names
 * alone (html false), as li items or a comma list (style none), by name,
 * with post counts, full names and feed links when asked. The account
 * shown as "admin" is left out unless asked for, and so are authors with
 * no published posts. Echoed unless echo is false.
 */
function wp_list_authors($args = '')
{
    $args = wp_parse_args($args, ['orderby' => 'name', 'order' => 'ASC', 'number' => '', 'optioncount' => false, 'exclude_admin' => true, 'show_fullname' => false, 'hide_empty' => true, 'feed' => '', 'feed_image' => '', 'feed_type' => '', 'echo' => true, 'style' => 'list', 'html' => true, 'exclude' => '', 'include' => '']);
    $query = apply_filters('wp_list_authors_args', wp_array_slice_assoc($args, ['orderby', 'order', 'number', 'exclude', 'include']) + ['fields' => 'ids'], $args);
    $counts = apply_filters('pre_wp_list_authors_post_counts_query', false, $args);
    if (!is_array($counts)) {
        $viewer = is_user_logged_in() && !current_user_can('read_private_posts') ? get_current_user_id() : -1;
        $counts = _minn_post_lookup()->countsByAuthor(['post'], current_user_can('read_private_posts') ? ['publish', 'private'] : ['publish'], $viewer);
    }
    $items = [];
    foreach (get_users($query) as $id) {
        $author = get_userdata($id);
        $posts = $author ? (int) ($counts[$author->ID] ?? 0) : 0;
        if (!$author || ($args['exclude_admin'] && $author->display_name === 'admin') || (!$posts && $args['hide_empty'])) {
            continue;
        }
        $name = $args['show_fullname'] && $author->first_name && $author->last_name ? "{$author->first_name} {$author->last_name}" : $author->display_name;
        $items[] = $args['html'] ? _minn_list_author_item($author, $name, $posts, $args) : $name;
    }
    $output = $args['html'] && $args['style'] === 'list' ? implode('', $items) : implode(', ', $items);
    if (!$args['echo']) {
        return $output;
    }
    echo $output;
}

/** @internal one author's entry in wp_list_authors: the link, the feed link, the count, in an li for the list style */
function _minn_list_author_item(WP_User $author, string $name, int $posts, array $args): string
{
    $link = '<a href="' . esc_url(get_author_posts_url($author->ID, $author->user_nicename)) . '">' . esc_html($name) . '</a>' . _minn_author_feed_link($author, $name, $args);
    if ($args['optioncount']) {
        $link .= ' (' . $posts . ')';
    }
    return $args['style'] === 'list' ? '<li>' . $link . '</li>' : $link;
}

/** Registers the core post types and statuses again, as WordPress does at init: their objects come out the same, and plugins hear each registered again. */
function create_initial_post_types()
{
    // The captured rows keep the mapped capabilities under cap and the supports beside them (Registry::supports);
    // registering reads them as capabilities and supports (none at all as false, so no defaults are added).
    foreach (Runtime::registry()->postTypes() as $name => $row) {
        if (!empty($row['_builtin'])) {
            $supports = [];
            foreach (Runtime::registry()->supports($name) as $feature => $args) {
                $args === true ? $supports[] = $feature : $supports[$feature] = $args;
            }
            register_post_type($name, ['capabilities' => (array) ($row['cap'] ?? []), 'supports' => $supports === [] ? false : $supports] + $row);
        }
    }
    foreach (Runtime::registry()->statuses() as $name => $row) {
        if (!empty($row['_builtin'])) {
            register_post_status($name, $row);
        }
    }
}

/** A post's local date, from the GMT one (or now) when it has none; false for a date that does not exist. */
function wp_resolve_post_date($post_date = '', $post_date_gmt = '')
{
    if (empty($post_date) || $post_date === '0000-00-00 00:00:00') {
        $post_date = empty($post_date_gmt) || $post_date_gmt === '0000-00-00 00:00:00' ? current_time('mysql') : get_date_from_gmt($post_date_gmt);
    }
    $valid = wp_checkdate((int) substr($post_date, 5, 2), (int) substr($post_date, 8, 2), (int) substr($post_date, 0, 4), $post_date);
    return $valid ? $post_date : false;
}

/** Posts' authors keep no cache here to fill. */
function update_post_author_caches($posts)
{
}

/** The posts' last-changed time moved on. */
function wp_cache_set_posts_last_changed()
{
    wp_cache_set_last_changed('posts');
}

/** A post's parent post, or null when it has none. */
function get_post_parent($post = null)
{
    $post = get_post($post);
    return !empty($post->post_parent) ? get_post($post->post_parent) : null;
}

/** Every page's id, whatever its status, as stored (Content\Posts). */
function get_all_page_ids()
{
    return _minn_posts()->idsOfType('page');
}

/** An attachment's term cache let go when asked, then clean_attachment_cache. */
function clean_attachment_cache($id, $clean_terms = false)
{
    if ($clean_terms) {
        clean_object_term_cache((int) $id, 'attachment');
    }
    do_action('clean_attachment_cache', (int) $id);
}

/** A post's meta rows by key, then id: key, value, the row's id and the post's (Runtime\Meta). */
function has_meta($post_id)
{
    return _minn_meta()->listing('post', (int) $post_id);
}

/** Deprecated since 2.8: get_the_author_meta('email'). */
function get_the_author_email()
{
    _deprecated_function(__FUNCTION__, '2.8.0', "get_the_author_meta('email')");
    return get_the_author_meta('email');
}

/** Deprecated since 2.8: the_author_meta('email'). */
function the_author_email()
{
    _deprecated_function(__FUNCTION__, '2.8.0', "the_author_meta('email')");
    the_author_meta('email');
}

/** Who last edited a post (the one in the loop by default; its _edit_last), by display name through the_modified_author; null when nobody is recorded. */
function get_the_modified_author($post = null)
{
    $last = get_post_meta((int) (get_post($post)->ID ?? 0), '_edit_last', true);
    if (!$last) {
        return null;
    }
    $user = get_userdata($last);
    return apply_filters('the_modified_author', $user ? $user->display_name : '');
}

/** Prints get_the_modified_author. */
function the_modified_author()
{
    echo get_the_modified_author();
}

/** The addresses a post has pinged, through get_pung; false for no post. */
function get_pung($post)
{
    $post = get_post($post);
    return $post ? apply_filters('get_pung', preg_split('/\s/', trim((string) $post->pinged))) : false;
}

/** The addresses a post has still to ping, cleaned, through get_to_ping; false for no post. */
function get_to_ping($post)
{
    $post = get_post($post);
    return $post ? apply_filters('get_to_ping', preg_split('/\s/', sanitize_trackback_urls($post->to_ping), -1, PREG_SPLIT_NO_EMPTY)) : false;
}

/** The format version a revision's or autosave's name carries (-v1), or 0; false for something that is not a post. */
function _wp_get_post_revision_version($revision)
{
    $revision = is_object($revision) ? get_object_vars($revision) : $revision;
    if (!is_array($revision)) {
        return false;
    }
    return preg_match('/^\d+-(?:autosave|revision)-v(\d+)$/', (string) ($revision['post_name'] ?? ''), $m) ? (int) $m[1] : 0;
}

/** Whether a post has a parent post. */
function has_post_parent($post = null)
{
    return (bool) get_post_parent($post);
}

/** Pages by id under one (the root by default), each with its slug, parents before children; pages whose parent is not among them are left out. */
function get_page_hierarchy(&$pages, $page_id = 0)
{
    $children = [];
    foreach ((array) $pages as $page) {
        $children[(int) $page->post_parent][] = $page;
    }
    $result = [];
    $walk = static function (int $parent) use (&$walk, &$result, $children): void {
        foreach ($children[$parent] ?? [] as $page) {
            $result[$page->ID] = $page->post_name;
            $walk((int) $page->ID);
        }
    };
    $walk((int) $page_id);
    return $result;
}

/** A post meta row deleted by its id. */
function delete_meta($mid)
{
    return delete_metadata_by_mid('post', (int) $mid);
}

/** Every post meta key in use, once each (Runtime\Meta). */
function get_meta_keys()
{
    return _minn_meta()->keys('post');
}

/** The statuses a personal data request moves through, with their labels. */
function _wp_privacy_statuses()
{
    return ['request-pending' => _x('Pending', 'request status'), 'request-confirmed' => _x('Confirmed', 'request status'), 'request-failed' => _x('Failed', 'request status'), 'request-completed' => _x('Completed', 'request status')];
}

/** A post type's (or taxonomy's) labels from its own and the defaults (Content\ObjectLabels). */
function _get_custom_object_labels($data_object, $nohier_vs_hier_defaults)
{
    $defaults = array_map(static fn ($pair) => !empty($data_object->hierarchical) ? $pair[1] : $pair[0], (array) $nohier_vs_hier_defaults);
    $label = isset($data_object->label) ? (string) $data_object->label : null;
    return (object) Minn\Content\ObjectLabels::build((array) ($data_object->labels ?? []), $label, (string) ($data_object->name ?? ''), $defaults);
}

/** Walks a children map (parent id => pages) depth first from a page, recording each page's slug by id. */
function _page_traverse_name($page_id, &$children, &$result)
{
    foreach ((array) ($children[$page_id] ?? []) as $child) {
        $result[$child->ID] = $child->post_name;
        _page_traverse_name($child->ID, $children, $result);
    }
}

/** Records a type's own names for the three meta capabilities (read_post, edit_post, delete_post) in $post_type_meta_caps. */
function _post_type_meta_capabilities($capabilities = null)
{
    global $post_type_meta_caps;
    foreach ((array) $capabilities as $core => $custom) {
        if (in_array($core, ['read_post', 'delete_post', 'edit_post'], true)) {
            $post_type_meta_caps[$custom] = $core;
        }
    }
}

/** Caches the parent ids of the posts not yet cached (post_parent:{id} in the posts group), from one query. */
function _prime_post_parent_id_caches($ids)
{
    global $wpdb;
    $missing = array_values(array_filter(array_unique(array_map('intval', (array) $ids)), static fn (int $id): bool => $id > 0 && wp_cache_get('post_parent:' . $id, 'posts') === false));
    if ($missing === []) {
        return;
    }
    foreach ((array) $wpdb->get_results("SELECT ID, post_parent FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $missing) . ')') as $row) {
        wp_cache_set('post_parent:' . (int) $row->ID, (int) $row->post_parent, 'posts');
    }
}

/** Caches the parent posts of the posts given. */
function update_post_parent_caches($posts)
{
    $parents = array_unique(array_filter(array_map(static fn ($post): int => (int) ($post->post_parent ?? 0), (array) $posts)));
    foreach ($parents as $parent) {
        $post = get_post($parent);
        if ($post instanceof WP_Post) {
            wp_cache_add($parent, $post, 'posts');
        }
    }
}

/** The mime types a post type's posts carry, each once (pre_get_available_post_mime_types may answer first). */
function get_available_post_mime_types($type = 'attachment')
{
    global $wpdb;
    $types = apply_filters('pre_get_available_post_mime_types', null, $type);
    if (!is_array($types)) {
        $types = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type != ''", $type));
    }
    return $types;
}

/** The first line of each of a post's enclosure meta values (its URL), through get_enclosed. */
function get_enclosed($post_id)
{
    $enclosed = [];
    foreach ((array) (get_post_custom($post_id)['enclosure'] ?? []) as $enclosure) {
        $enclosed[] = trim(explode("\n", (string) $enclosure)[0]);
    }
    return apply_filters('get_enclosed', $enclosed, $post_id);
}

/** The SQL that limits a query to the posts of a type (or types) the reader may see: published, and private ones they may read. */
function get_private_posts_cap_sql($post_type)
{
    return get_posts_by_author_sql($post_type, false);
}

/** Whether a post can be embedded: its type is viewable; is_post_embeddable has the last word. */
function is_post_embeddable($post = null)
{
    $post = get_post($post);
    if (!$post) {
        return false;
    }
    return (bool) apply_filters('is_post_embeddable', is_post_type_viewable($post->post_type), $post);
}

/** Gives a post's slug the __trashed suffix (once), keeping the slug it wanted; the slug it now has. */
function wp_add_trashed_suffix_to_post_name_for_post($post)
{
    return Minn\Runtime\PostWrites::suffixTrashedPost($post);
}

/** Gives every trashed post with a slug (but the one named) the __trashed suffix, so a live post may take it. */
function wp_add_trashed_suffix_to_post_name_for_trashed_posts($post_name, $post_id = 0)
{
    Minn\Runtime\PostWrites::suffixTrashed($post_name, $post_id);
}

/** Deletes the auto-drafts dated more than a week ago. */
function wp_delete_auto_drafts()
{
    global $wpdb;
    foreach ((array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND DATE_SUB(NOW(), INTERVAL 7 DAY) > post_date") as $id) {
        wp_delete_post((int) $id, true);
    }
}

/** Queues the posts' terms for one term meta read; the engine reads term meta directly, so there is nothing to queue. */
function wp_queue_posts_for_term_meta_lazyload($posts)
{
}

/** Tags added to a post's own, kept (probe placeholder-helpers): the terms it now has; false without a post. */
function wp_add_post_tags($post_id = 0, $tags = '')
{
    return wp_set_post_tags($post_id, $tags, true);
}
