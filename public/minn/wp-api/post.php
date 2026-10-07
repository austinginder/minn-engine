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
use Minn\Runtime\Runtime;
use Minn\Runtime\Pages;
use Minn\Runtime\PostInsert;
use Minn\Runtime\PostLookup;

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

/** @internal the insert decisions, with the reference's option, capability, and date helpers handed in */
function _minn_post_insert(): PostInsert
{
    return new PostInsert(
        _minn_post_writer(),
        get_current_user_id(),
        static fn (string $type, string $kind): string => get_default_comment_status($type, $kind),
        static fn (string $type, string $feature): bool => post_type_supports($type, $feature),
        static fn (string $type): bool => current_user_can(get_post_type_object($type)->cap->publish_posts ?? 'publish_posts'),
        static fn (string $date): string => (string) get_gmt_from_date($date),
        static fn (bool $gmt): string => (string) current_time('mysql', $gmt),
    );
}

function _minn_post_writer(): PostWriter
{
    return new PostWriter(Runtime::current()->db, _minn_posts(), Runtime::current()->site);
}

function get_post($post = null, $output = OBJECT, $filter = 'raw')
{
    if ($post === null || $post === 0 || $post === '' || $post === false) {
        $post = $GLOBALS['post'] ?? Runtime::current()->get('post');
        if ($post === null) {
            return null;
        }
    }
    if ($post instanceof WP_Post) {
        $object = $post;
    } elseif ($post instanceof Minn\Content\PostRecord) {
        $object = new WP_Post((object) $post->row());
    } elseif (is_object($post) && isset($post->ID)) {
        $object = new WP_Post($post);
    } elseif (is_array($post) && isset($post['ID'])) {
        $object = new WP_Post((object) $post);
    } else {
        // Each caller gets its own copy: what one plugin changes on its post, the next get_post() does not see.
        $cached = wp_cache_get((int) $post, 'posts', false, $found);
        if ($found && $cached instanceof WP_Post) {
            $object = clone $cached;
        } else {
            $row = (int) $post > 0 ? _minn_posts()->find((int) $post) : null;
            if ($row === null) {
                return null;
            }
            $object = new WP_Post((object) $row->row());
            wp_cache_set((int) $post, clone $object, 'posts');
        }
    }
    $object = $object->filter($filter === null || $filter === '' ? 'raw' : (string) $filter);
    if ($output === ARRAY_A) {
        return $object->to_array();
    }
    if ($output === ARRAY_N) {
        return array_values($object->to_array());
    }
    return $object;
}

function clean_post_cache($post)
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    wp_cache_delete($post->ID, 'posts');
    wp_cache_delete($post->ID, 'post_meta');
    do_action('clean_post_cache', $post->ID, $post);
    if ($post->post_type === 'page') {
        do_action('clean_page_cache', $post->ID);
    }
}

function get_the_ID()
{
    $post = get_post();
    return $post === null ? false : $post->ID;
}

function get_post_type($post = null)
{
    $post = get_post($post);
    return $post === null ? false : $post->post_type;
}

function get_post_status($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $status = $post->post_status;
    if ($post->post_type === 'attachment' && $status === 'inherit') {
        $parent = $post->post_parent > 0 ? get_post($post->post_parent) : null;
        $status = $parent === null ? 'publish' : ($parent->post_status === 'trash' ? get_post_meta($parent->ID, '_wp_trash_meta_status', true) ?: 'publish' : $parent->post_status);
    }
    return apply_filters('get_post_status', $status, $post);
}

/** One column of a post through sanitize_post_field in the context asked for (probe post-field). */
function get_post_field($field, $post = null, $context = 'display')
{
    $post = get_post($post);
    if ($post === null || !isset($post->{$field})) {
        return '';
    }
    return sanitize_post_field((string) $field, $post->{$field}, $post->ID, (string) $context);
}

function get_post_mime_type($post = null)
{
    $post = get_post($post);
    return $post === null ? false : (string) $post->post_mime_type;
}

function get_the_title($post = 0)
{
    $post = get_post($post);
    if ($post === null) {
        return '';
    }
    $title = $post->post_title;
    if (!is_admin()) {
        if ($post->post_password !== '') {
            $title = sprintf(apply_filters('protected_title_format', 'Protected: %s', $post), $title);
        } elseif ($post->post_status === 'private') {
            $title = sprintf(apply_filters('private_title_format', 'Private: %s', $post), $title);
        }
    }
    return apply_filters('the_title', $title, $post->ID);
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
    $title = $args['before'] . esc_attr(strip_tags($title)) . $args['after'];
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
    $post = get_post($post);
    if ($post === null || $post->post_password === '') {
        return apply_filters('post_password_required', false, $post);
    }
    $cookie = Runtime::current()->reader->postPassword;
    $required = $cookie === '' || !Minn\Auth\PortableHash::check($post->post_password, $cookie);
    return apply_filters('post_password_required', $required, $post);
}

/**
 * The form a protected post shows instead of its content (captured): it
 * sends the visitor back to the post, and under a block theme its button
 * wears the button block's classes.
 */
function get_the_password_form($post = 0)
{
    $post = get_post($post);
    $label = 'pwbox-' . ($post === null ? mt_rand() : $post->ID);
    $button = '<input type="submit" name="Submit"' . (wp_is_block_theme() ? ' class="wp-block-button__link wp-element-button"' : '') . ' value="' . esc_attr_x('Enter', 'post password form') . '" />';
    $button = wp_is_block_theme() ? '<span class="wp-block-button">' . $button . '</span>' : $button;
    $form = '<form action="' . esc_url(site_url('wp-login.php?action=postpass', 'login_post')) . '" class="post-password-form" method="post">'
        . '<input type="hidden" name="redirect_to" value="' . esc_attr($post === null ? '' : get_permalink($post)) . '" />' . "\n\t"
        . '<p>This content is password-protected. To view it, please enter the password below.</p>' . "\n\t"
        . '<p><label for="' . $label . '">Password: <input name="post_password" id="' . $label . '" type="password" spellcheck="false" required size="20" /></label> ' . $button . '</p></form>' . "\n\t";
    return apply_filters('the_password_form', $form, $post, '');
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
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $source = $gmt ? $post->post_date_gmt : $post->post_date;
    if ($gmt && str_starts_with($source, '0000')) {
        $source = get_gmt_from_date($post->post_date);
    }
    $datetime = date_create($source, $gmt ? new DateTimeZone('UTC') : wp_timezone());
    if ($datetime === false) {
        return false;
    }
    if ($format === 'U' || $format === 'G') {
        $time = $datetime->getTimestamp();
    } else {
        $time = $translate ? wp_date($format, $datetime->getTimestamp(), $gmt ? new DateTimeZone('UTC') : null) : $datetime->format($format);
    }
    return apply_filters('get_post_time', $time, $format, $gmt);
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
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $source = $gmt ? $post->post_modified_gmt : $post->post_modified;
    $datetime = date_create($source, $gmt ? new DateTimeZone('UTC') : wp_timezone());
    if ($datetime === false) {
        return false;
    }
    $time = $format === 'U' || $format === 'G' ? $datetime->getTimestamp() : ($translate ? wp_date($format, $datetime->getTimestamp(), $gmt ? new DateTimeZone('UTC') : null) : $datetime->format($format));
    return apply_filters('get_post_modified_time', $time, $format, $gmt);
}

function the_date($format = '', $before = '', $after = '', $display = true)
{
    $post = get_post();
    if ($post === null) {
        return null;
    }
    $out = $before . get_the_date($format) . $after;
    if ($display) {
        echo $out;
        return null;
    }
    return $out;
}

function the_time($format = '')
{
    echo get_the_time($format);
}

function get_post_ancestors($post)
{
    $post = get_post($post);
    if ($post === null || (int) $post->post_parent === 0 || (int) $post->post_parent === (int) $post->ID) {
        return [];
    }
    $ancestors = [];
    $id = (int) $post->post_parent;
    while ($id > 0 && !in_array($id, $ancestors, true)) {
        $ancestors[] = $id;
        $parent = get_post($id);
        $id = $parent === null ? 0 : (int) $parent->post_parent;
    }
    return $ancestors;
}

function get_ancestors($object_id = 0, $object_type = '', $resource_type = '')
{
    $object_id = (int) $object_id;
    if ($object_id === 0) {
        return apply_filters('get_ancestors', [], $object_id, $object_type, $resource_type);
    }
    if ($resource_type === '') {
        $resource_type = taxonomy_exists($object_type) ? 'taxonomy' : (post_type_exists($object_type) ? 'post_type' : '');
    }
    $ancestors = [];
    if ($resource_type === 'taxonomy') {
        $term = get_term($object_id, $object_type);
        while ($term instanceof WP_Term && $term->parent > 0 && !in_array($term->parent, $ancestors, true)) {
            $ancestors[] = $term->parent;
            $term = get_term($term->parent, $object_type);
        }
    } elseif ($resource_type === 'post_type') {
        $ancestors = get_post_ancestors($object_id);
    }
    return apply_filters('get_ancestors', $ancestors, $object_id, $object_type, $resource_type);
}

function wp_get_post_parent_id($post = null)
{
    $post = get_post($post);
    return $post === null ? false : (int) $post->post_parent;
}

function get_page_by_path($page_path, $output = OBJECT, $post_type = 'page')
{
    $segments = array_values(array_filter(explode('/', trim((string) $page_path, '/')), static fn ($s) => $s !== ''));
    if ($segments === []) {
        return null;
    }
    // One type looks among attachments too, as the reference does; a list looks among exactly its types.
    $types = is_array($post_type) ? array_values(array_map('strval', $post_type)) : [(string) $post_type, 'attachment'];
    $found = _minn_posts()->byTypedPath($segments, $types);
    return $found === null ? null : get_post($found->id, $output);
}

function get_page_by_title($page_title, $output = OBJECT, $post_type = 'page')
{
    $id = _minn_post_lookup()->idByTitle((string) $page_title, array_map('strval', (array) $post_type));
    return $id === null ? null : get_post($id, $output);
}

function get_page_uri($page = 0)
{
    $page = $page instanceof WP_Post ? $page : get_post($page);
    if ($page === null) {
        return false;
    }
    $uri = $page->post_name;
    foreach (get_post_ancestors($page) as $parent) {
        $ancestor = get_post($parent);
        if ($ancestor !== null && $ancestor->post_name !== '') {
            $uri = $ancestor->post_name . '/' . $uri;
        }
    }
    return apply_filters('get_page_uri', $uri, $page);
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
    }
    return 0;
}

function get_post_custom($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    return get_post_meta((int) $post_id);
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

function get_edit_post_link($post = 0, $context = 'display')
{
    $post = get_post($post);
    if ($post === null || !current_user_can('edit_post', $post->ID)) {
        return null;
    }
    $sep = $context === 'display' ? '&amp;' : '&';
    $type = get_post_type_object($post->post_type);
    if ($type === null) {
        return null;
    }
    $link = $type->_edit_link ? admin_url(sprintf($type->_edit_link . $sep . 'action=edit', $post->ID)) : '';
    return apply_filters('get_edit_post_link', $link, $post->ID, $context);
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
    $post = $post instanceof WP_Post ? $post : get_post($post);
    if ($post === null) {
        return false;
    }
    $sample = ($post->filter ?? '') === 'sample';
    return match (true) {
        $post->post_type === 'page' => get_page_link($post, $leavename, $sample),
        $post->post_type === 'attachment' => get_attachment_link($post, $leavename),
        in_array($post->post_type, get_post_types(['_builtin' => false]), true) => get_post_permalink($post, $leavename, $sample),
        default => PostLinks::post($post, $leavename ? PostLinks::LEAVE_NAME : 0),
    };
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
    $post = $post instanceof WP_Post ? $post : get_post($post);
    return $post === null ? false : PostLinks::custom($post, _minn_link_flags($leavename, $sample));
}

/** A page's address: the front page is home; else its own, through page_link. */
function get_page_link($post = 0, $leavename = false, $sample = false)
{
    $post = $post instanceof WP_Post ? $post : get_post($post);
    if ($post === null) {
        return false;
    }
    $front = get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $post->ID;
    $link = $front ? home_url('/') : _get_page_link($post, $leavename, $sample);
    return apply_filters('page_link', $link, $post->ID, $sample);
}

/** A page's own address, through _get_page_link (Runtime\PostLinks). */
function _get_page_link($post = 0, $leavename = false, $sample = false)
{
    $post = $post instanceof WP_Post ? $post : get_post($post);
    // No such page: the plain address with no id, as the reference answers it.
    return $post === null ? apply_filters('_get_page_link', home_url('/?page_id='), null) : PostLinks::page($post, _minn_link_flags($leavename, $sample));
}

/** An attachment's address, through attachment_link (Runtime\PostLinks). */
function get_attachment_link($post = null, $leavename = false)
{
    $post = $post instanceof WP_Post ? $post : get_post($post);
    return $post === null ? false : PostLinks::attachment($post, _minn_link_flags($leavename, false));
}

/** The address an editor shows with the slug to edit, through get_sample_permalink (Runtime\PostLinks). */
function get_sample_permalink($post, $title = null, $name = null)
{
    $post = get_post($post);
    return $post === null ? ['', ''] : PostLinks::sample($post, $title === null ? null : (string) $title, $name === null ? null : (string) $name);
}

function get_post_type_archive_link($post_type)
{
    $type = get_post_type_object($post_type);
    if ($type === null || !$type->has_archive) {
        return false;
    }
    // A named archive (has_archive "shop") lives at that name; otherwise at the type's rewrite slug.
    $slug = is_string($type->has_archive) && $type->has_archive !== '' ? $type->has_archive : (is_array($type->rewrite) && !empty($type->rewrite['slug']) ? $type->rewrite['slug'] : $type->name);
    return apply_filters('post_type_archive_link', home_url('/' . $slug . '/'), $post_type);
}

function wp_get_shortlink($id = 0, $context = 'post', $allow_slugs = true)
{
    $shortlink = apply_filters('pre_get_shortlink', false, $id, $context, $allow_slugs);
    if ($shortlink !== false) {
        return $shortlink;
    }
    $post = $context === 'query' ? (is_singular() ? get_post(get_queried_object_id()) : null) : ($context === 'post' ? get_post($id) : null);
    $type = $post === null ? null : get_post_type_object($post->post_type);
    $front = $post !== null && $post->post_type === 'page' && get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === (int) $post->ID;
    $shortlink = $front ? home_url('/') : ($type !== null && $type->public ? home_url('?p=' . $post->ID) : '');
    return apply_filters('get_shortlink', $shortlink, $id, $context, $allow_slugs);
}

function wp_shortlink_header()
{
    $shortlink = wp_get_shortlink(0, 'query');
    if (headers_sent() || empty($shortlink) || PHP_SAPI === 'cli') {
        return;
    }
    header('Link: <' . $shortlink . '>; rel=shortlink', false);
}

function get_adjacent_post($in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category')
{
    $post = get_post();
    if ($post === null) {
        return null;
    }
    $row = $previous ? _minn_posts()->previous(Minn\Content\PostRecord::fromRow($post->to_array())) : _minn_posts()->next(Minn\Content\PostRecord::fromRow($post->to_array()));
    return $row === null ? null : get_post((int) $row['ID']);
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
    $post = get_post($post);
    $args = wp_parse_args($args, ['order' => 'DESC', 'orderby' => 'date ID', 'check_enabled' => true]);
    if ($post === null || ($args['check_enabled'] && !wp_revisions_enabled($post))) {
        return [];
    }
    $rows = _minn_post_lookup()->revisionsOf($post->ID);
    $rows = strtoupper((string) $args['order']) === 'ASC' ? array_reverse($rows) : $rows;
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['ID']] = new WP_Post((object) $row);
    }
    return $out;
}

function wp_get_post_revisions_url($post = 0)
{
    return null;
}

function wp_revisions_enabled($post)
{
    return wp_revisions_to_keep($post) !== 0;
}

/**
 * The fields a revision keeps, through _wp_post_revision_fields (whose
 * default adds footnotes); the filtered list is what the next call starts
 * from, as on the reference.
 */
function _wp_post_revision_fields($post = [], $deprecated = false)
{
    $post = is_array($post) ? $post : (get_post($post)?->to_array() ?? []);
    $runtime = Runtime::current();
    $fields = (array) $runtime->get('revision_fields', ['post_title' => 'Title', 'post_content' => 'Content', 'post_excerpt' => 'Excerpt']);
    $fields = (array) apply_filters('_wp_post_revision_fields', $fields, $post);
    $runtime->set('revision_fields', $fields);
    return array_diff_key($fields, array_flip(['ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status', 'post_type', 'comment_count', 'post_author']));
}

/** A revision's row: the post's revision fields, its parent, inherit, revision, its name, and the post's modified time as its date. */
function _wp_post_revision_data($post = [], $autosave = false)
{
    $post = _minn_revision_source($post);
    $data = array_intersect_key($post, _wp_post_revision_fields($post));
    $id = (int) ($post['ID'] ?? 0);
    return $data + ['post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'revision', 'post_name' => $autosave ? "{$id}-autosave-v1" : "{$id}-revision-v1", 'post_date' => $post['post_modified'] ?? '', 'post_date_gmt' => $post['post_modified_gmt'] ?? ''];
}

/** @internal a post as a revision's row is made from: its own fields, as an (array) cast gives them */
function _minn_revision_source($post): array
{
    if (is_array($post)) {
        return $post;
    }
    $post = is_object($post) ? $post : get_post($post);
    return is_object($post) ? get_object_vars($post) : [];
}

/** A revision written through wp_insert_post, then _wp_put_post_revision. */
function _wp_put_post_revision($post = null, $autosave = false)
{
    $post = get_post($post);
    return $post === null ? null : PostRevisions::put($post);
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
    $str = str_replace("\r", "\n", trim((string) $str));
    return (string) preg_replace(['/\n+/', '/[ \t]+/'], ["\n", ' '], $str);
}

/**
 * How many revisions a post keeps (captured): WP_POST_REVISIONS (all, -1,
 * when unset or true), none for a type without revision support, then
 * wp_revisions_to_keep and wp_{type}_revisions_to_keep.
 */
function wp_revisions_to_keep($post)
{
    $post = get_post($post);
    $num = defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS : true;
    $num = $num === true ? -1 : (int) $num;
    if ($post === null || !post_type_supports($post->post_type, 'revisions')) {
        $num = 0;
    }
    $num = apply_filters('wp_revisions_to_keep', $num, $post);
    return (int) apply_filters("wp_{$post?->post_type}_revisions_to_keep", $num, $post);
}

function is_sticky($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $stickies = get_option('sticky_posts');
    return is_array($stickies) && in_array((int) $post_id, array_map('intval', $stickies), true);
}

function stick_post($post_id)
{
    $stickies = get_option('sticky_posts');
    $stickies = is_array($stickies) ? array_map('intval', $stickies) : [];
    if (in_array((int) $post_id, $stickies, true)) {
        return;
    }
    $stickies[] = (int) $post_id;
    update_option('sticky_posts', $stickies);
}

function unstick_post($post_id)
{
    $stickies = get_option('sticky_posts');
    $stickies = is_array($stickies) ? array_map('intval', $stickies) : [];
    update_option('sticky_posts', array_values(array_diff($stickies, [(int) $post_id])));
}

function get_post_thumbnail_id($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $id = get_post_meta($post->ID, '_thumbnail_id', true);
    return $id === '' ? 0 : (int) $id;
}

/** @internal a live post left without a slug (no title to make one from) takes its id, made free (probe editor-styles) */
function _minn_post_slug_from_id(int $id, array $columns, string $type): void
{
    if ((string) ($columns['post_name'] ?? '') !== '' || PostSave::keepsSlug((string) $columns['post_status'], $type)) {
        return;
    }
    $slug = wp_unique_post_slug((string) sanitize_title((string) ($columns['post_title'] ?? ''), (string) $id), $id, (string) $columns['post_status'], $type, (int) ($columns['post_parent'] ?? 0));
    _minn_post_writer()->update($id, ['post_name' => $slug]);
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
    return '<a href="' . esc_url($url) . '" class="post-page-numbers">';
}

function has_post_thumbnail($post = null)
{
    return (bool) get_post_thumbnail_id($post);
}

function setup_postdata($post)
{
    $query = $GLOBALS['wp_query'] ?? null;
    if ($query instanceof WP_Query) {
        return $query->setup_postdata($post);
    }
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $GLOBALS['post'] = $post;
    Runtime::current()->set('post', $post);
    _minn_postdata_globals($post);
    return true;
}

/** @internal the globals the loop's template tags read, from generate_postdata */
function _minn_postdata_globals(WP_Post $post): void
{
    $elements = PostData::generate($post);
    foreach (['id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'] as $name) {
        $GLOBALS[$name] = $elements[$name];
    }
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
    $defaults = ['numberposts' => 5, 'category' => 0, 'orderby' => 'date', 'order' => 'DESC', 'include' => [], 'exclude' => [], 'meta_key' => '', 'meta_value' => '', 'post_type' => 'post', 'suppress_filters' => true];
    $parsed = wp_parse_args($args, $defaults);
    if (empty($parsed['post_status'])) {
        $parsed['post_status'] = $parsed['post_type'] === 'attachment' ? 'inherit' : 'publish';
    }
    if (!empty($parsed['numberposts']) && empty($parsed['posts_per_page'])) {
        $parsed['posts_per_page'] = $parsed['numberposts'];
    }
    if (!empty($parsed['category'])) {
        $parsed['cat'] = $parsed['category'];
    }
    if (!empty($parsed['include'])) {
        $ids = wp_parse_id_list($parsed['include']);
        $parsed['posts_per_page'] = count($ids);
        $parsed['post__in'] = $ids;
    } elseif (!empty($parsed['exclude'])) {
        $parsed['post__not_in'] = wp_parse_id_list($parsed['exclude']);
    }
    $parsed['ignore_sticky_posts'] = true;
    $parsed['no_found_rows'] = true;
    unset($parsed['numberposts'], $parsed['category'], $parsed['include'], $parsed['exclude']);
    return (new WP_Query())->query($parsed);
}

function get_pages($args = [])
{
    $parsed = wp_parse_args($args, Pages::DEFAULTS);
    $query = Pages::queryArgs($parsed, empty($parsed['include']) ? [] : wp_parse_id_list($parsed['include']), empty($parsed['exclude']) ? [] : wp_parse_id_list($parsed['exclude']));
    $pages = Pages::arrange((new WP_Query())->query($query), $parsed);
    return apply_filters('get_pages', $pages, $parsed);
}

/** @internal every page under one ancestor, in list order */
function _minn_page_descendants(array $pages, int $parent): array
{
    return Pages::descendants($pages, $parent);
}

function get_children($args = '', $output = OBJECT)
{
    if (is_numeric($args) || is_object($args)) {
        $args = ['post_parent' => is_object($args) ? (int) $args->ID : (int) $args];
    }
    $parsed = wp_parse_args($args, ['post_parent' => 0, 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1]);
    if (empty($parsed['post_parent'])) {
        $current = get_post();
        if ($current === null) {
            return [];
        }
        $parsed['post_parent'] = $current->ID;
    }
    $children = get_posts($parsed);
    $out = [];
    foreach ($children as $child) {
        $out[$child->ID] = $output === ARRAY_A ? $child->to_array() : ($output === ARRAY_N ? array_values($child->to_array()) : $child);
    }
    return $out;
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
    return (object) array_map('strval', _minn_post_lookup()->countAttachments());
}

/** @internal the columns the posts table takes, filled from a postarr */
function _minn_post_columns(array $postarr, ?WP_Post $existing): array
{
    return _minn_post_insert()->columns($postarr, $existing?->to_array());
}

function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
{
    $given = (array) $postarr;
    $update = !empty($given['ID']);
    $existing = $update ? get_post((int) $given['ID']) : null;
    if ($update && $existing === null) {
        return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    }
    // The reference's order (probe post-insert-filters): each column through its db context, the empty check,
    // the parent, the post's own dates, status and slug, the slug's filters, wp_insert_post_data.
    $sanitized = PostSave::sanitized($given);
    $insert = _minn_post_insert();
    $before = $existing?->to_array();
    $columns = $insert->columns((array) wp_unslash($sanitized), $before);
    $type = (string) ($columns['post_type'] ?? $existing->post_type);
    if (PostSave::refusesEmpty($sanitized, $type)) {
        return $wp_error ? new WP_Error('empty_content', 'Content, title, and excerpt are empty.') : 0;
    }
    $postId = (int) ($existing?->ID ?? 0);
    $columns['post_parent'] = (string) PostSave::parent($sanitized, $postId);
    $columns = PostSave::guidAndSlug($insert->resolve($columns, $before), $postId, $type, _minn_post_writer()->slugs());
    $columns = array_replace($columns, PostSave::data($columns, $sanitized, $given, $postId));
    _minn_post_before_save($columns, $postId);
    // A new post without a guid takes its address as it stands (probe insert-defaults): pretty when live, ?p= or ?page_id= when not.
    $id = $insert->persist($columns, $existing?->ID, static fn (int $id): string => (string) get_permalink($id));
    _minn_post_slug_from_id($id, $columns, $type);
    wp_cache_delete($id, 'posts');
    _minn_post_inputs($id, (array) wp_unslash($given), $type, $columns['post_status'], $update);
    _minn_post_writer()->recountTaxonomiesOf($id);
    if ($type === 'attachment') {
        return _minn_attachment_saved($id, $given, $existing, (bool) $fire_after_hooks);
    }
    $post = _minn_post_saved($id, $update, $existing);
    if ($fire_after_hooks) {
        // The revision is saved from wp_after_insert_post (priority 9), as on the reference.
        wp_after_insert_post($post, $update, $existing);
    }
    return $id;
}

/**
 * @internal An attachment's save ends as the reference's does (probe
 * rest-media-save): its file recorded, then add_attachment, or
 * edit_attachment and attachment_updated; no save_post actions; the after
 * hooks when asked.
 */
function _minn_attachment_saved(int $id, array $postarr, ?WP_Post $post_before, bool $fire_after_hooks): int
{
    if (!empty($postarr['file'])) {
        update_attached_file($id, (string) $postarr['file']);
    }
    clean_post_cache($post_before ?? $id);
    if ($post_before !== null) {
        do_action('edit_attachment', $id);
        do_action('attachment_updated', $id, get_post($id), $post_before);
    } else {
        do_action('add_attachment', $id);
    }
    if ($fire_after_hooks) {
        wp_after_insert_post(get_post($id), $post_before !== null, $post_before);
    }
    return $id;
}

/** @internal A save sets a post's categories again, as the reference's insert does, telling plugins even when nothing changes. */
function _minn_post_keep_categories(WP_Post $post): void
{
    if (is_object_in_taxonomy($post->post_type, 'category')) {
        wp_set_post_categories($post->ID, wp_get_post_categories($post->ID));
    }
}

/** @internal What the reference tells plugins before a post's row is written: pre_post_insert for a new post, pre_post_update for one that exists. */
function _minn_post_before_save(array $data, int $post_id = 0): void
{
    if ($post_id > 0) {
        do_action('pre_post_update', $post_id, $data);
        return;
    }
    do_action('pre_post_insert', $data);
}

/**
 * @internal What the reference tells plugins once a post's row and its own
 * terms are written, in its order: the caches let go, the status
 * transition, the edit actions for an update, the save actions.
 * wp_after_insert_post is the caller's to fire, after whatever else it
 * writes (the REST controllers' terms and meta).
 */
function _minn_post_saved(int $post_id, bool $update, ?WP_Post $post_before): ?WP_Post
{
    // The reference lets go of the copy it had cached, which still holds the post as it was.
    clean_post_cache($post_before ?? $post_id);
    $post = get_post($post_id);
    if ($post === null) {
        return null;
    }
    wp_transition_post_status($post->post_status, $post_before instanceof WP_Post ? $post_before->post_status : 'new', $post);
    if ($update && $post_before instanceof WP_Post) {
        do_action("edit_post_{$post->post_type}", $post->ID, $post);
        do_action('edit_post', $post->ID, $post);
        do_action('post_updated', $post->ID, $post, $post_before);
    }
    do_action("save_post_{$post->post_type}", $post->ID, $post, $update);
    do_action('save_post', $post->ID, $post, $update);
    do_action('wp_insert_post', $post->ID, $post, $update);
    return $post;
}

/** @internal the terms, meta, and template a postarr carries beside the columns */
function _minn_post_inputs(int $id, array $postarr, string $type, string $status, bool $update): void
{
    $categories = PostInsert::categories($postarr, $type, $status, $update, get_object_taxonomies($type), (int) get_option('default_category'));
    if ($categories !== null) {
        wp_set_post_categories($id, $categories);
    }
    if (isset($postarr['tags_input']) && in_array('post_tag', get_object_taxonomies($type), true)) {
        wp_set_post_tags($id, $postarr['tags_input']);
    }
    foreach (is_array($postarr['tax_input'] ?? null) ? $postarr['tax_input'] : [] as $taxonomy => $terms) {
        wp_set_post_terms($id, $terms, (string) $taxonomy);
    }
    foreach (is_array($postarr['meta_input'] ?? null) ? $postarr['meta_input'] : [] as $key => $value) {
        update_post_meta($id, (string) $key, $value);
    }
    if (isset($postarr['page_template'])) {
        if ($postarr['page_template'] === '' || $postarr['page_template'] === 'default') {
            delete_post_meta($id, '_wp_page_template');
        } else {
            update_post_meta($id, '_wp_page_template', $postarr['page_template']);
        }
    }
}

function wp_after_insert_post($post, $update, $post_before)
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    do_action('wp_after_insert_post', $post->ID, $post, $update, $post_before);
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

function wp_transition_post_status($new_status, $old_status, $post)
{
    do_action('transition_post_status', $new_status, $old_status, $post);
    do_action("{$old_status}_to_{$new_status}", $post);
    do_action("{$new_status}_{$post->post_type}", $post->ID, $post, $old_status);
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

function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true)
{
    $postarr = is_object($postarr) ? get_object_vars($postarr) : (array) $postarr;
    $post = get_post((int) ($postarr['ID'] ?? 0));
    if ($post === null) {
        return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    }
    $existing = $post->to_array();
    // The stored post under the given fields, its categories among them, as the reference merges it.
    $merged = array_merge($existing, wp_unslash($postarr));
    if (isset($postarr['post_date']) && !isset($postarr['post_date_gmt'])) {
        $merged['post_date_gmt'] = '';
    }
    // An attachment is saved through wp_insert_attachment, the stored fields (its tags among them) carried whole.
    if ($merged['post_type'] === 'attachment') {
        return wp_insert_attachment(wp_slash($merged), false, 0, $wp_error, $fire_after_hooks);
    }
    if (isset($merged['tags_input'])) {
        // Given explicitly by the caller only.
    } else {
        unset($merged['tags_input']);
    }
    if (!isset($postarr['tags_input'])) {
        unset($merged['tags_input']);
    }
    return wp_insert_post(wp_slash($merged), $wp_error, $fire_after_hooks);
}

function wp_publish_post($post)
{
    $before = get_post($post);
    if ($before === null || $before->post_status === 'publish') {
        return;
    }
    $columns = ['post_status' => 'publish'];
    // A floating date settles when the post goes out.
    if ($before->post_date_gmt === '0000-00-00 00:00:00') {
        $columns['post_date_gmt'] = get_gmt_from_date($before->post_date);
    }
    _minn_post_writer()->update($before->ID, $columns);
    clean_post_cache($before);
    $post = get_post($before->ID);
    wp_transition_post_status('publish', $before->post_status, $post);
    do_action("edit_post_{$post->post_type}", $post->ID, $post);
    do_action('edit_post', $post->ID, $post);
    do_action("save_post_{$post->post_type}", $post->ID, $post, true);
    do_action('save_post', $post->ID, $post, true);
    do_action('wp_insert_post', $post->ID, $post, true);
    wp_after_insert_post($post, true, $before);
}

/** The publish_future_post event's work: a scheduled post whose time has come goes out; one early is scheduled again for its time. */
function check_and_publish_future_post($post)
{
    $post = get_post($post);
    if ($post === null || $post->post_status !== 'future') {
        return;
    }
    $time = strtotime($post->post_date_gmt . ' GMT');
    if ($time > time()) {
        wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
        wp_schedule_single_event($time, 'publish_future_post', [$post->ID]);
        return;
    }
    return wp_publish_post($post->ID);
}

/** The default on future_post and future_page: a scheduled post is put on the cron calendar for its time, which is how WordPress publishes it. */
function _future_post_hook($deprecated, $post)
{
    wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
    wp_schedule_single_event(strtotime(get_gmt_from_date($post->post_date) . ' GMT'), 'publish_future_post', [$post->ID]);
}

/** The first default on transition_post_status: a post that stops being scheduled leaves the cron calendar. */
function _transition_post_status($new_status, $old_status, $post)
{
    if ($old_status === 'future' && $new_status !== 'future') {
        wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
    }
}

/** The default on publish_post and publish_page: a site that has published something is no longer fresh. */
function _delete_option_fresh_site()
{
    update_option('fresh_site', '0', false);
}

/** The default on wp_trash_post and before_delete_post: a page that stops existing stops being the front page or the posts page. */
function _reset_front_page_settings_for_post($post_id)
{
    $post = get_post($post_id);
    if ($post === null || $post->post_type !== 'page') {
        return;
    }
    if ((int) get_option('page_on_front') === $post->ID) {
        update_option('show_on_front', 'posts');
        update_option('page_on_front', 0);
    }
    if ((int) get_option('page_for_posts') === $post->ID) {
        update_option('page_for_posts', 0);
    }
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
    $post = get_post($post_id);
    if ($post === null || $post->post_status === 'trash') {
        return false;
    }
    $check = apply_filters('pre_trash_post', null, $post, $post->post_status);
    if ($check !== null) {
        return $check;
    }
    do_action('wp_trash_post', $post->ID, $post->post_status);
    // The status, the time and the slug it had are kept for the way back; then the save itself, as wp_update_post makes it.
    add_post_meta($post->ID, '_wp_trash_meta_status', $post->post_status);
    add_post_meta($post->ID, '_wp_trash_meta_time', time());
    add_post_meta($post->ID, '_wp_desired_post_slug', $post->post_name);
    clean_post_cache($post->ID);
    _minn_post_before_save(['post_status' => 'trash', 'post_name' => $post->post_name . '__trashed'] + $post->to_array(), $post->ID);
    _minn_post_writer()->trash(_minn_posts()->find($post->ID));
    _minn_post_keep_categories($post);
    $trashed = _minn_post_saved($post->ID, true, $post);
    wp_after_insert_post($trashed, true, $post);
    wp_trash_post_comments($post->ID);
    do_action('trashed_post', $post->ID, $post->post_status);
    // The reference answers with the post as it was before the trash.
    return $post;
}

function wp_untrash_post($post_id = 0)
{
    $post = get_post($post_id);
    if ($post === null || $post->post_status !== 'trash') {
        return false;
    }
    $previous = (string) get_post_meta($post->ID, '_wp_trash_meta_status', true);
    $check = apply_filters('pre_untrash_post', null, $post, $previous);
    if ($check !== null) {
        return $check;
    }
    do_action('untrash_post', $post->ID, $previous);
    $new = $previous === 'attachment' ? 'inherit' : apply_filters('wp_untrash_post_status', 'draft', $post->ID, $previous);
    delete_post_meta($post->ID, '_wp_trash_meta_status');
    delete_post_meta($post->ID, '_wp_trash_meta_time');
    // The slug it had comes back, whatever status it returns to.
    $desired = (string) get_post_meta($post->ID, '_wp_desired_post_slug', true);
    $columns = ['post_status' => $new];
    if ($desired !== '' && str_ends_with((string) $post->post_name, '__trashed')) {
        $columns['post_name'] = _minn_post_writer()->uniqueSlug($desired, $post->ID, $post->post_type, (int) $post->post_parent);
    }
    delete_post_meta($post->ID, '_wp_desired_post_slug');
    _minn_post_before_save($columns + $post->to_array(), $post->ID);
    _minn_post_writer()->update($post->ID, $columns);
    _minn_post_keep_categories($post);
    $restored = _minn_post_saved($post->ID, true, $post);
    wp_after_insert_post($restored, true, $post);
    wp_untrash_post_comments($post->ID);
    do_action('untrashed_post', $post->ID, $previous);
    return $post;
}

function wp_delete_post($post_id = 0, $force_delete = false)
{
    $post = get_post($post_id);
    if ($post === null) {
        return $post;
    }
    if (!$force_delete && in_array($post->post_type, ['post', 'page'], true) && $post->post_status !== 'trash' && EMPTY_TRASH_DAYS) {
        return wp_trash_post($post_id);
    }
    if ($post->post_type === 'attachment') {
        return wp_delete_attachment($post_id, $force_delete);
    }
    $check = apply_filters('pre_delete_post', null, $post, $force_delete);
    if ($check !== null) {
        return $check;
    }
    do_action('before_delete_post', $post->ID, $post);
    delete_post_meta($post->ID, '_wp_trash_meta_status');
    delete_post_meta($post->ID, '_wp_trash_meta_time');
    wp_delete_object_term_relationships($post->ID, get_object_taxonomies($post->post_type));
    _minn_post_writer()->reparentChildren($post->ID, (int) $post->post_parent, $post->post_type === 'page' ? ['page', 'attachment'] : ['attachment']);
    foreach (wp_get_post_revisions($post->ID, ['check_enabled' => false]) as $revision) {
        wp_delete_post_revision($revision);
    }
    foreach (_minn_comments()->idsOf($post->ID) as $comment_id) {
        wp_delete_comment($comment_id, true);
    }
    _minn_post_delete_meta($post->ID);
    return _minn_post_remove($post);
}

/** @internal Each meta row of a post removed by its id, as the reference removes them before the post, with the hooks of each. */
function _minn_post_delete_meta(int $post_id): void
{
    foreach (_minn_meta()->rowsOf('post', $post_id) as $row) {
        delete_metadata_by_mid('post', (int) $row['meta_id']);
    }
}

/** @internal The row itself, between the delete actions, typed and untyped, and the caches let go after. */
function _minn_post_remove(WP_Post $post): WP_Post
{
    do_action("delete_post_{$post->post_type}", $post->ID, $post);
    do_action('delete_post', $post->ID, $post);
    _minn_post_writer()->destroy($post->ID);
    do_action("deleted_post_{$post->post_type}", $post->ID, $post);
    do_action('deleted_post', $post->ID, $post);
    clean_post_cache($post);
    do_action('after_delete_post', $post->ID, $post);
    return $post;
}

function wp_delete_post_revision($revision)
{
    $revision = get_post($revision);
    if ($revision === null || $revision->post_type !== 'revision') {
        return $revision;
    }
    $removed = wp_delete_post($revision->ID);
    if ($removed) {
        do_action('wp_delete_post_revision', $revision->ID, $revision);
    }
    return $removed;
}


function wp_set_post_categories($post_id = 0, $post_categories = [], $append = false)
{
    $post_id = (int) $post_id;
    $type = get_post_type($post_id);
    $categories = array_values(array_filter(array_map('intval', (array) $post_categories)));
    if ($categories === [] && $type === 'post' && !$append) {
        $categories = [(int) get_option('default_category')];
    }
    return wp_set_post_terms($post_id, $categories, 'category', $append);
}

function wp_set_post_tags($post_id = 0, $tags = '', $append = false)
{
    return wp_set_post_terms($post_id, $tags, 'post_tag', $append);
}

function get_post_type_object($post_type)
{
    if (!is_scalar($post_type)) {
        return null;
    }
    $row = Runtime::registry()->postType((string) $post_type);
    return $row === null ? null : new WP_Post_Type((string) $post_type, $row);
}

function post_type_exists($post_type)
{
    return is_scalar($post_type) && Runtime::registry()->postType((string) $post_type) !== null;
}

function is_post_type_hierarchical($post_type)
{
    $row = is_scalar($post_type) ? Runtime::registry()->postType((string) $post_type) : null;
    return $row !== null && !empty($row['hierarchical']);
}

function is_post_type_viewable($post_type)
{
    if (is_scalar($post_type)) {
        $post_type = get_post_type_object($post_type);
    }
    if (!is_object($post_type)) {
        return false;
    }
    $viewable = $post_type->publicly_queryable || ($post_type->_builtin && $post_type->public);
    return (bool) apply_filters('is_post_type_viewable', $viewable, $post_type);
}

function is_post_status_viewable($post_status)
{
    if (is_scalar($post_status)) {
        $post_status = get_post_status_object($post_status);
    }
    if (!is_object($post_status) || $post_status->internal || $post_status->protected) {
        return false;
    }
    return (bool) apply_filters('is_post_status_viewable', $post_status->publicly_queryable || ($post_status->_builtin && $post_status->public), $post_status);
}

function is_post_publicly_viewable($post = null)
{
    $post = get_post($post);
    return $post !== null && is_post_type_viewable($post->post_type) && is_post_status_viewable(get_post_status($post));
}

function get_post_types($args = [], $output = 'names', $operator = 'and')
{
    $objects = [];
    foreach (Runtime::registry()->postTypes() as $name => $row) {
        $objects[$name] = new WP_Post_Type($name, $row);
    }
    $filtered = wp_filter_object_list($objects, $args, $operator, $output === 'names' ? 'name' : false);
    return $filtered;
}

function register_post_type($post_type, $args = [])
{
    $post_type = sanitize_key((string) $post_type);
    if ($post_type === '' || strlen($post_type) > 20) {
        _doing_it_wrong(__FUNCTION__, 'Post type names must be between 1 and 20 characters in length.', '4.2.0');
        return new WP_Error('post_type_length_invalid', 'Post type names must be between 1 and 20 characters in length.');
    }
    $args = apply_filters('register_post_type_args', (array) $args, $post_type);
    $row = Runtime::registry()->registerPostType($post_type, $args);
    if (is_array($row['rewrite']) && Registry::settlesRewrites()) {
        // Its address pattern, under the front of the structure at the time unless it opts out (probe registry-rewrites).
        add_permastruct($post_type, "{$row['rewrite']['slug']}/%{$post_type}%", ['with_front' => $row['rewrite']['with_front'], 'ep_mask' => $row['rewrite']['ep_mask'], 'feed' => $row['rewrite']['feeds']]);
    }
    $object = new WP_Post_Type($post_type, $row);
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
    Runtime::registry()->unregisterPostType((string) $post_type);
    remove_permastruct((string) $post_type);
    do_action('unregistered_post_type', $post_type);
    return true;
}

function get_post_type_labels($post_type_object)
{
    return $post_type_object->labels;
}

function get_post_type_capabilities($args)
{
    return $args->cap ?? new stdClass();
}

function get_all_post_type_supports($post_type)
{
    return Runtime::registry()->supports((string) $post_type);
}

function post_type_supports($post_type, $feature)
{
    return isset(get_all_post_type_supports($post_type)[$feature]);
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
        $supports = array_keys($row['supports'] ?? []);
        $matches = count(array_intersect($features, $supports));
        if (($operator === 'and' && $matches === count($features)) || ($operator === 'or' && $matches > 0) || ($operator === 'not' && $matches === 0)) {
            $out[] = $name;
        }
    }
    return $out;
}

function get_post_status_object($post_status)
{
    $row = is_scalar($post_status) ? Runtime::registry()->status((string) $post_status) : null;
    return $row === null ? null : (object) $row;
}

function get_post_stati($args = [], $output = 'names', $operator = 'and')
{
    $objects = [];
    foreach (Runtime::registry()->statuses() as $name => $row) {
        $objects[$name] = (object) $row;
    }
    return wp_filter_object_list($objects, $args, $operator, $output === 'names' ? 'name' : false);
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
    $args = wp_parse_args($args, ['fields' => 'ids']);
    $terms = wp_get_object_terms((int) $post_id, 'category', $args);
    return is_wp_error($terms) ? [] : $terms;
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
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }
    if (empty($terms)) {
        $terms = [];
    }
    if (!is_array($terms)) {
        $terms = array_values(array_filter(array_map('trim', explode(',', str_replace('，', ',', (string) $terms))), static fn ($t) => $t !== ''));
    }
    if (is_taxonomy_hierarchical($taxonomy)) {
        $terms = array_values(array_unique(array_map('intval', $terms)));
    }
    return wp_set_object_terms($post_id, $terms, $taxonomy, $append);
}

function get_the_author_meta($field = '', $user_id = false)
{
    $user = $user_id === false ? ($GLOBALS['authordata'] ?? null) : get_userdata((int) $user_id);
    if (!$user instanceof WP_User) {
        return '';
    }
    $value = $field === 'ID' ? $user->ID : $user->get($field);
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
    return sprintf('<a href="%1$s" rel="author">%2$s</a>', esc_url(get_author_posts_url($author->ID)), get_the_author());
}

function count_user_posts($userid, $post_type = 'post', $public_only = false)
{
    $count = _minn_post_lookup()->countByAuthor((int) $userid, array_map('strval', (array) $post_type), $public_only ? ['publish'] : ['publish', 'private']);
    return apply_filters('get_usernumposts', (string) $count, $userid, $post_type, $public_only);
}

function get_the_author_link($use_title_attr = true)
{
    return get_the_author_posts_link();
}

function is_multi_author()
{
    return false;
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
    if (is_object($post)) {
        if (isset($post->filter) && $post->filter === $context) {
            return $post;
        }
        $id = (int) ($post->ID ?? 0);
        foreach (array_keys(get_object_vars($post)) as $field) {
            if ($field !== 'filter') {
                $post->{$field} = sanitize_post_field($field, $post->{$field}, $id, $context);
            }
        }
        $post->filter = $context;
        return $post;
    }
    if (!is_array($post)) {
        return $post;
    }
    if (($post['filter'] ?? null) === $context) {
        return $post;
    }
    $id = (int) ($post['ID'] ?? 0);
    foreach ($post as $field => $value) {
        $post[$field] = $field === 'filter' ? $value : sanitize_post_field($field, $value, $id, $context);
    }
    $post['filter'] = $context;
    return $post;
}

/** A post column as the reference hands it out: raw, escaped for edit forms, cast for the integer columns. */
function sanitize_post_field($field, $value, $post_id, $context = 'display')
{
    $field = (string) $field;
    if (in_array($field, ['ID', 'post_parent', 'menu_order'], true)) {
        // The integer columns stay integers in every context, escaped or not.
        return (int) _minn_sanitize_post_value($field, (int) $value, $post_id, (string) $context);
    }
    return _minn_sanitize_post_value($field, $value, $post_id, (string) $context);
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
    $bare = str_starts_with($field, 'post_') ? substr($field, 5) : null;
    if ($context === 'raw') {
        return $value;
    }
    if ($context === 'db') {
        return $bare === null ? apply_filters("{$field}_pre", apply_filters("pre_post_{$field}", $value)) : apply_filters("{$bare}_save_pre", apply_filters("pre_{$field}", $value));
    }
    if ($context === 'edit') {
        $value = $bare === null ? apply_filters("edit_post_{$field}", $value, $post_id) : apply_filters("{$bare}_edit_pre", apply_filters("edit_{$field}", $value, $post_id), $post_id);
        if (!is_scalar($value)) {
            return $value;
        }
        if ($field === 'post_content') {
            return format_to_edit((string) $value, user_can_richedit());
        }
        return in_array($field, ['post_title', 'post_excerpt', 'post_password'], true) ? format_to_edit((string) $value) : esc_attr((string) $value);
    }
    $value = apply_filters($bare === null ? "post_{$field}" : $field, $value, $post_id, $context);
    if ($context === 'attribute') {
        return esc_attr((string) $value);
    }
    return $context === 'js' ? esc_js((string) $value) : $value;
}

/** The article's class list; see Minn\Content\PostClasses for the order. */
function get_post_class($css_class = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return [];
    }
    $extra = is_array($css_class) ? $css_class : preg_split('/\s+/', trim((string) $css_class), -1, PREG_SPLIT_NO_EMPTY);
    $terms = [];
    foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
        if (empty($taxonomy->public)) {
            continue;
        }
        foreach ((array) (get_the_terms($post->ID, $taxonomy->name) ?: []) as $term) {
            $terms[] = ['taxonomy' => $term->taxonomy, 'slug' => (string) $term->slug, 'term_id' => (int) $term->term_id];
        }
    }
    $classes = PostClasses::build(
        (array) $post,
        array_map(static fn ($c) => PostClasses::htmlClass((string) $c), $extra),
        post_type_supports($post->post_type, 'post-formats') ? (string) (get_post_format($post) ?: '') : null,
        $post->post_type !== 'attachment' && has_post_thumbnail($post),
        is_sticky($post->ID) && is_home() && !is_paged(),
        post_password_required($post),
        $post->post_password !== '',
        $terms,
    );
    return array_unique(apply_filters('post_class', $classes, $extra, $post->ID));
}

/** The body's class list: the tokens the classic renderer stood for this page, the caller's extras, then the body_class filter. */
function get_body_class($css_class = '')
{
    $classes = array_map('strval', (array) Runtime::current()->get('classic_body_classes', []));
    $extra = is_array($css_class) ? $css_class : preg_split('/\s+/', trim((string) $css_class), -1, PREG_SPLIT_NO_EMPTY);
    $extra = array_map(static fn ($c) => PostClasses::htmlClass((string) $c), (array) $extra);
    array_push($classes, ...array_filter($extra));
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

/** The engine reads rows on demand; the reference's cache primers have nothing to fill here. */
function _prime_post_caches($ids, $update_term_cache = true, $update_meta_cache = true)
{
    return null;
}

function update_post_caches(&$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true)
{
    return null;
}

function update_post_thumbnail_cache($wp_query = null)
{
    return null;
}

/** An attachment page shows the file's own link ahead of its content; any other post passes through. */
function prepend_attachment($content)
{
    $post = get_post();
    if (!$post || $post->post_type !== 'attachment') {
        return $content;
    }
    $link = wp_get_attachment_link(0, 'medium', false);
    $link = apply_filters('prepend_attachment', $link);
    return '<p class="attachment">' . $link . "</p>\n" . $content;
}

/** The newest modification among published content, format Y-m-d H:i:s; the server variant carries microseconds, as probed. */
function get_lastpostmodified($timezone = 'server', $post_type = 'any')
{
    $type = (string) $post_type;
    if ($type !== 'any' && get_post_type_object($type) === null) {
        return false;
    }
    $value = strtolower((string) $timezone) === 'gmt' ? _minn_posts()->lastModifiedGmt($type === 'any' ? null : $type) : _minn_posts()->lastModified($type === 'any' ? null : $type);
    if ($value === null) {
        return false;
    }
    if (strtolower((string) $timezone) === 'server') {
        $value .= '.000000';
    }
    return apply_filters('get_lastpostmodified', $value, $timezone, $post_type);
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
    if (PostSave::keepsSlug((string) $post_status, (string) $post_type)) {
        return $slug;
    }
    return PostSave::slugFilters((string) $slug, (int) $post_id, (string) $post_status, (string) $post_type, (int) $post_parent, _minn_post_writer()->slugs());
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

function update_user_caches($user)
{
    // Users are read straight from the database; there is nothing to cache.
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

function walk_page_tree($pages, $depth, $current_page, $args)
{
    $walker = empty($args['walker']) ? new Walker_Page() : $args['walker'];
    return $walker->walk($pages, $depth, $args, $current_page);
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
function wp_filter_wp_template_unique_post_slug($override_slug, $slug, $post_id, $post_status, $post_type)
{
    return $override_slug;
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
    return \Minn\Query\MimeWhere::sql(is_array($post_mime_types) ? array_values($post_mime_types) : (string) $post_mime_types, (string) $table_alias);
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

function get_posts_by_author_sql($post_type, $full = true, $post_author = null, $public_only = false)
{
    $types = [];
    foreach ((array) $post_type as $type) {
        $object = get_post_type_object($type);
        if ($object) {
            $cap = apply_filters_deprecated('pub_priv_sql_capability', [''], '3.2.0');
            $types[] = ['type' => (string) $type, 'readPrivate' => (bool) ($cap ?: current_user_can($object->cap->read_private_posts))];
        }
    }
    return Minn\Query\AuthorPostsSql::sql($types, is_user_logged_in() ? get_current_user_id() : 0, $full ? 'full' : '', $post_author === null ? null : (int) $post_author, $public_only ? 'public' : '');
}
