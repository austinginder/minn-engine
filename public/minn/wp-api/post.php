<?php
/** Posts: reads, the lists, the writers, post types, statuses. Behaviour from contracts/fixtures/api/content.json. */

use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Slug;
use Minn\Runtime\Runtime;

/** @internal */
function _minn_posts(): Posts
{
    return new Posts(Runtime::current()->db);
}

/** @internal */
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
    } elseif (is_object($post) && isset($post->ID)) {
        $object = new WP_Post($post);
    } elseif (is_array($post) && isset($post['ID'])) {
        $object = new WP_Post((object) $post);
    } else {
        $cached = wp_cache_get((int) $post, 'posts', false, $found);
        if ($found && $cached instanceof WP_Post) {
            $object = $cached;
        } else {
            $row = (int) $post > 0 ? _minn_posts()->find((int) $post) : null;
            if ($row === null) {
                return null;
            }
            $object = new WP_Post((object) $row);
            wp_cache_set((int) $post, $object, 'posts');
        }
    }
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

function get_post_field($field, $post = null, $context = 'display')
{
    $post = get_post($post);
    if ($post === null || !isset($post->{$field})) {
        return '';
    }
    return $post->{$field};
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

function get_the_content($more_link_text = null, $strip_teaser = false, $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return '';
    }
    if (post_password_required($post)) {
        return get_the_password_form($post);
    }
    return $post->post_content;
}

function the_content($more_link_text = null, $strip_teaser = false)
{
    $content = apply_filters('the_content', get_the_content($more_link_text, $strip_teaser));
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
    $excerpt = $post->post_excerpt;
    if ($excerpt === '') {
        $excerpt = trim(wp_strip_all_tags(Excerpt::render($post->to_array())));
        $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return apply_filters('get_the_excerpt', $excerpt, $post);
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

function get_the_password_form($post = 0)
{
    $post = get_post($post);
    $label = 'pwbox-' . ($post === null ? mt_rand() : $post->ID);
    $form = '<form action="' . esc_url(site_url('wp-login.php?action=postpass', 'login_post')) . '" class="post-password-form" method="post"><p>This content is password protected. To view it please enter your password below:</p><p><label for="' . $label . '">Password: <input name="post_password" id="' . $label . '" type="password" spellcheck="false" size="20" /></label> <input type="submit" name="Submit" value="' . esc_attr_x('Enter', 'post password form') . '" /></p></form>';
    return apply_filters('the_password_form', $form, $post);
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
    $types = (array) $post_type;
    if (!in_array('page', $types, true) || count($types) !== 1) {
        $row = _minn_posts()->findByName(end($segments), $types, false);
        if ($row === null || count($segments) > 1) {
            return null;
        }
        return get_post((int) $row['ID'], $output);
    }
    $row = _minn_posts()->pageByPath($segments, false);
    return $row === null ? null : get_post((int) $row['ID'], $output);
}

function get_page_by_title($page_title, $output = OBJECT, $post_type = 'page')
{
    $db = Runtime::current()->db;
    $types = (array) $post_type;
    $id = $db->value("SELECT ID FROM {$db->table('posts')} WHERE post_title = ? AND post_type IN (" . implode(',', array_fill(0, count($types), '?')) . ') ORDER BY ID ASC LIMIT 1', [(string) $page_title, ...$types]);
    return $id === null ? null : get_post((int) $id, $output);
}

function get_page_uri($page = 0)
{
    $page = get_post($page);
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
        $page = _minn_posts()->pageByPath($segments, false);
        if ($page !== null) {
            return (int) $page['ID'];
        }
        $post = _minn_posts()->findByName(end($segments), ['post'], false);
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

function get_permalink($post = 0, $leavename = false)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $permalinks = Runtime::current()->get('permalinks');
    $link = $permalinks === null ? home_url('/?p=' . $post->ID) : $permalinks->forPost($post->to_array());
    if ($post->post_type === 'page') {
        return apply_filters('page_link', $link, $post->ID, $post->post_status !== 'publish' || $leavename);
    }
    if ($post->post_type === 'attachment') {
        return apply_filters('attachment_link', $link, $post->ID);
    }
    if ($post->post_type !== 'post') {
        return apply_filters('post_type_link', $link, $post, $leavename, $post->post_status !== 'publish');
    }
    return apply_filters('post_link', $link, $post, $leavename);
}

function get_the_permalink($post = 0, $leavename = false)
{
    return get_permalink($post, $leavename);
}

function the_permalink($post = 0)
{
    echo esc_url(apply_filters('the_permalink', get_permalink($post), $post));
}

function get_post_permalink($post = 0, $leavename = false, $sample = false)
{
    return get_permalink($post, $leavename);
}

function get_page_link($post = 0, $leavename = false, $sample = false)
{
    return get_permalink($post, $leavename);
}

function get_attachment_link($post = null, $leavename = false)
{
    return get_permalink($post, $leavename);
}

function get_post_type_archive_link($post_type)
{
    $type = get_post_type_object($post_type);
    if ($type === null || !$type->has_archive) {
        return false;
    }
    $slug = is_array($type->rewrite) && !empty($type->rewrite['slug']) ? $type->rewrite['slug'] : ($type->has_archive === true ? $type->name : (string) $type->has_archive);
    return apply_filters('post_type_archive_link', home_url('/' . $slug . '/'), $post_type);
}

function wp_get_shortlink($id = 0, $context = 'post', $allow_slugs = true)
{
    $post = get_post($id);
    return $post === null ? '' : home_url('?p=' . $post->ID);
}

function get_adjacent_post($in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category')
{
    $post = get_post();
    if ($post === null) {
        return null;
    }
    $row = _minn_posts()->adjacent($post->to_array(), !$previous);
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

function wp_get_post_revisions($post = 0, $args = null)
{
    $post = get_post($post);
    if ($post === null) {
        return [];
    }
    $db = Runtime::current()->db;
    $rows = $db->rows("SELECT * FROM {$db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_status = 'inherit' ORDER BY post_date DESC, ID DESC", [$post->ID]);
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
    return post_type_supports(get_post_type($post), 'revisions');
}

function wp_revisions_to_keep($post)
{
    return defined('WP_POST_REVISIONS') && WP_POST_REVISIONS === false ? 0 : (defined('WP_POST_REVISIONS') && is_int(WP_POST_REVISIONS) ? WP_POST_REVISIONS : -1);
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
    return true;
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
    $defaults = ['child_of' => 0, 'sort_order' => 'ASC', 'sort_column' => 'post_title', 'hierarchical' => 1, 'exclude' => [], 'include' => [], 'meta_key' => '', 'meta_value' => '', 'authors' => '', 'parent' => -1, 'exclude_tree' => [], 'number' => '', 'offset' => 0, 'post_type' => 'page', 'post_status' => 'publish'];
    $parsed = wp_parse_args($args, $defaults);
    $query = ['post_type' => $parsed['post_type'], 'post_status' => $parsed['post_status'], 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'];
    $columns = ['post_title' => 'title', 'menu_order' => 'menu_order', 'post_date' => 'date', 'post_modified' => 'modified', 'ID' => 'ID', 'post_author' => 'author', 'post_name' => 'name', 'post_parent' => 'parent'];
    $orderby = [];
    foreach (preg_split('/[\s,]+/', trim((string) $parsed['sort_column']), -1, PREG_SPLIT_NO_EMPTY) as $column) {
        $key = $columns[$column] ?? $columns['post_' . $column] ?? null;
        if ($key !== null) {
            $orderby[$key] = strtoupper((string) $parsed['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
        }
    }
    if ($orderby !== []) {
        $query['orderby'] = $orderby;
    }
    if ((int) $parsed['parent'] >= 0) {
        $query['post_parent'] = (int) $parsed['parent'];
    }
    if (!empty($parsed['include'])) {
        $query['post__in'] = wp_parse_id_list($parsed['include']);
    }
    if (!empty($parsed['exclude'])) {
        $query['post__not_in'] = wp_parse_id_list($parsed['exclude']);
    }
    if ($parsed['meta_key'] !== '') {
        $query['meta_key'] = $parsed['meta_key'];
        $query['meta_value'] = $parsed['meta_value'];
    }
    if ($parsed['authors'] !== '') {
        $query['author'] = $parsed['authors'];
    }
    $query['ignore_sticky_posts'] = true;
    $query['no_found_rows'] = true;
    $pages = (new WP_Query())->query($query);
    if ($parsed['hierarchical'] && (int) $parsed['parent'] < 0) {
        // Parents first, each followed by its own subtree, as the reference orders a page tree.
        $byParent = [];
        foreach ($pages as $page) {
            $byParent[(int) $page->post_parent][] = $page;
        }
        $known = array_map(static fn (WP_Post $p) => $p->ID, $pages);
        $walk = static function (int $parent) use (&$walk, &$byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $page) {
                $out[] = $page;
                array_push($out, ...$walk($page->ID));
            }
            return $out;
        };
        $roots = [];
        foreach ($pages as $page) {
            if (!in_array((int) $page->post_parent, $known, true)) {
                $roots[] = (int) $page->post_parent;
            }
        }
        $ordered = [];
        foreach (array_unique($roots) as $root) {
            array_push($ordered, ...$walk($root));
        }
        $pages = $ordered;
    }
    if ((int) $parsed['child_of'] > 0) {
        $pages = _minn_page_descendants($pages, (int) $parsed['child_of']);
    }
    foreach ((array) $parsed['exclude_tree'] as $tree) {
        $excluded = array_map(static fn (WP_Post $p) => $p->ID, _minn_page_descendants($pages, (int) $tree));
        $excluded[] = (int) $tree;
        $pages = array_values(array_filter($pages, static fn (WP_Post $p) => !in_array($p->ID, $excluded, true)));
    }
    if ((int) $parsed['offset'] > 0 || $parsed['number'] !== '') {
        $pages = array_slice($pages, (int) $parsed['offset'], $parsed['number'] === '' ? null : (int) $parsed['number']);
    }
    return apply_filters('get_pages', array_values($pages), $parsed);
}

/** @internal every page under one ancestor, in list order */
function _minn_page_descendants(array $pages, int $parent): array
{
    $out = [];
    $wanted = [$parent];
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($pages as $page) {
            if (in_array((int) $page->post_parent, $wanted, true) && !in_array($page, $out, true)) {
                $out[] = $page;
                $wanted[] = (int) $page->ID;
                $changed = true;
            }
        }
    }
    return $out;
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
    $db = Runtime::current()->db;
    $rows = $db->rows("SELECT post_status, COUNT(*) AS num_posts FROM {$db->table('posts')} WHERE post_type = ? GROUP BY post_status", [$type]);
    $counts = array_fill_keys(array_keys(get_post_stati()), 0);
    foreach ($rows as $row) {
        $counts[$row['post_status']] = (string) $row['num_posts'];
    }
    return apply_filters('wp_count_posts', (object) $counts, $type, $perm);
}

function wp_count_attachments($mime_type = '')
{
    $db = Runtime::current()->db;
    $rows = $db->rows("SELECT post_mime_type, COUNT(*) AS num_posts FROM {$db->table('posts')} WHERE post_type = 'attachment' AND post_status != 'trash' GROUP BY post_mime_type");
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['post_mime_type']] = (string) $row['num_posts'];
    }
    $counts['trash'] = (string) $db->value("SELECT COUNT(*) FROM {$db->table('posts')} WHERE post_type = 'attachment' AND post_status = 'trash'");
    return (object) $counts;
}

/** @internal the columns the posts table takes, filled from a postarr */
function _minn_post_columns(array $postarr, ?WP_Post $existing): array
{
    $now = current_time('mysql');
    $nowGmt = current_time('mysql', true);
    $columns = [];
    foreach (['post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type'] as $column) {
        if (array_key_exists($column, $postarr)) {
            $columns[$column] = is_array($postarr[$column]) ? implode("\n", $postarr[$column]) : (string) $postarr[$column];
        }
    }
    if ($existing === null) {
        $columns += [
            'post_author' => (string) get_current_user_id(),
            'post_content' => '',
            'post_title' => '',
            'post_excerpt' => '',
            'post_status' => 'draft',
            'comment_status' => (string) (get_option('default_comment_status') ?: 'open'),
            'ping_status' => (string) (get_option('default_ping_status') ?: 'open'),
            'post_password' => '',
            'post_name' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'post_parent' => '0',
            'guid' => '',
            'menu_order' => '0',
            'post_type' => 'post',
            'post_mime_type' => '',
        ];
    }
    return $columns;
}

function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
{
    $postarr = wp_unslash((array) $postarr);
    $writer = _minn_post_writer();
    $update = !empty($postarr['ID']);
    $existing = $update ? get_post((int) $postarr['ID']) : null;
    if ($update && $existing === null) {
        return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    }
    $postarr = apply_filters('wp_insert_post_data', $postarr, $postarr, $postarr, $update);
    $columns = _minn_post_columns($postarr, $existing);
    $type = (string) ($columns['post_type'] ?? $existing->post_type);
    $title = (string) ($columns['post_title'] ?? $existing?->post_title ?? '');
    $content = (string) ($columns['post_content'] ?? $existing?->post_content ?? '');
    $excerpt = (string) ($columns['post_excerpt'] ?? $existing?->post_excerpt ?? '');
    $maybeEmpty = $title === '' && $content === '' && $excerpt === '' && post_type_supports($type, 'editor') && post_type_supports($type, 'title') && post_type_supports($type, 'excerpt');
    if (apply_filters('wp_insert_post_empty_content', $maybeEmpty, $postarr)) {
        return $wp_error ? new WP_Error('empty_content', 'Content, title, and excerpt are empty.') : 0;
    }
    $status = (string) ($columns['post_status'] ?? $existing->post_status);
    if ($type === 'attachment' && !in_array($status, ['inherit', 'private', 'trash', 'auto-draft'], true)) {
        $status = 'inherit';
    }
    if ($status === 'publish' && !post_type_exists($type) === false && !current_user_can(get_post_type_object($type)->cap->publish_posts ?? 'publish_posts') && get_current_user_id() > 0) {
        $status = 'pending';
    }
    $now = current_time('mysql');
    $nowGmt = current_time('mysql', true);
    $date = (string) ($columns['post_date'] ?? '');
    if ($date === '' || str_starts_with($date, '0000-00-00')) {
        $date = $existing !== null && !str_starts_with($existing->post_date, '0000') ? $existing->post_date : $now;
    }
    $dateGmt = (string) ($columns['post_date_gmt'] ?? '');
    if ($dateGmt === '' || str_starts_with($dateGmt, '0000-00-00')) {
        $dateGmt = in_array($status, ['draft', 'pending', 'auto-draft'], true) ? '0000-00-00 00:00:00' : ($existing !== null && !str_starts_with($existing->post_date_gmt, '0000') && !array_key_exists('post_date', $columns) ? $existing->post_date_gmt : get_gmt_from_date($date));
    }
    if ($status === 'publish' && strtotime($dateGmt . ' UTC') > time() + MINUTE_IN_SECONDS) {
        $status = 'future';
    }
    $columns['post_status'] = $status;
    $columns['post_date'] = $date;
    $columns['post_date_gmt'] = $dateGmt;
    $columns['post_modified'] = $now;
    $columns['post_modified_gmt'] = $nowGmt;
    $slug = (string) ($columns['post_name'] ?? $existing?->post_name ?? '');
    if ($slug === '' && !in_array($status, ['draft', 'pending', 'auto-draft'], true)) {
        $slug = Slug::sanitize($title);
    } elseif ($slug !== '' && (!$update || $slug !== $existing->post_name)) {
        $slug = Slug::sanitize($slug);
    }
    if ($slug !== '') {
        $slug = $writer->uniqueSlug($slug, $update ? $existing->ID : 0);
    }
    $columns['post_name'] = $slug;
    $columns['post_parent'] = (string) (int) ($columns['post_parent'] ?? $existing?->post_parent ?? 0);
    $columns['menu_order'] = (string) (int) ($columns['menu_order'] ?? $existing?->menu_order ?? 0);
    $columns['post_author'] = (string) (int) ($columns['post_author'] ?? $existing?->post_author ?? get_current_user_id());
    $previousStatus = $existing?->post_status ?? 'new';
    if ($update) {
        do_action('pre_post_update', $existing->ID, $columns);
        $writer->update($existing->ID, $columns);
        $id = $existing->ID;
    } else {
        $id = $writer->insert($columns);
        if ($columns['guid'] === '') {
            $writer->update($id, ['guid' => home_url('/?p=' . $id)]);
        }
    }
    wp_cache_delete($id, 'posts');
    $post = get_post($id);
    if (!empty($postarr['post_category']) || (!$update && $type === 'post' && $status !== 'auto-draft')) {
        $categories = array_values(array_filter(array_map('intval', (array) ($postarr['post_category'] ?? []))));
        if ($categories === [] && $type === 'post' && in_array('category', get_object_taxonomies($type), true) && (!$update || empty($postarr['post_category']))) {
            $categories = [(int) get_option('default_category')];
        }
        if ($categories !== [] && in_array('category', get_object_taxonomies($type), true)) {
            wp_set_post_categories($id, $categories);
        }
    }
    if (isset($postarr['tags_input']) && in_array('post_tag', get_object_taxonomies($type), true)) {
        wp_set_post_tags($id, $postarr['tags_input']);
    }
    if (!empty($postarr['tax_input']) && is_array($postarr['tax_input'])) {
        foreach ($postarr['tax_input'] as $taxonomy => $terms) {
            wp_set_post_terms($id, $terms, (string) $taxonomy);
        }
    }
    if (isset($postarr['meta_input']) && is_array($postarr['meta_input'])) {
        foreach ($postarr['meta_input'] as $key => $value) {
            update_post_meta($id, (string) $key, $value);
        }
    }
    if (isset($postarr['page_template'])) {
        if ($postarr['page_template'] === '' || $postarr['page_template'] === 'default') {
            delete_post_meta($id, '_wp_page_template');
        } else {
            update_post_meta($id, '_wp_page_template', $postarr['page_template']);
        }
    }
    $writer->recountTaxonomiesOf($id);
    if ($fire_after_hooks) {
        wp_after_insert_post($post, $update, $existing);
    }
    if (in_array($type, ['post', 'page'], true) && post_type_supports($type, 'revisions') && $update) {
        wp_save_post_revision($id);
    }
    return $id;
}

function wp_after_insert_post($post, $update, $post_before)
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    $previous = $post_before instanceof WP_Post ? $post_before->post_status : 'new';
    wp_transition_post_status($post->post_status, $previous, $post);
    do_action("save_post_{$post->post_type}", $post->ID, $post, $update);
    do_action('save_post', $post->ID, $post, $update);
    do_action('wp_insert_post', $post->ID, $post, $update);
    do_action('wp_after_insert_post', $post, $update, $post_before);
}

function wp_transition_post_status($new_status, $old_status, $post)
{
    do_action('transition_post_status', $new_status, $old_status, $post);
    do_action("{$old_status}_to_{$new_status}", $post);
    do_action("{$new_status}_{$post->post_type}", $post->ID, $post, $old_status);
}

function wp_save_post_revision($post_id)
{
    $post = get_post($post_id);
    if ($post === null) {
        return null;
    }
    $before = array_keys(wp_get_post_revisions($post_id));
    _minn_post_writer()->maybeSaveRevision($post->ID, get_current_user_id());
    $after = array_keys(wp_get_post_revisions($post_id));
    $new = array_values(array_diff($after, $before));
    if ($new === []) {
        return null;
    }
    $revision = get_post($new[0]);
    wp_transition_post_status('inherit', 'new', $revision);
    do_action('save_post_revision', $revision->ID, $revision, false);
    do_action('save_post', $revision->ID, $revision, false);
    do_action('wp_insert_post', $revision->ID, $revision, false);
    do_action('wp_after_insert_post', $revision, false, null);
    do_action('_wp_put_post_revision', $revision->ID, $revision);
    return $revision->ID;
}

function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true)
{
    $postarr = is_object($postarr) ? get_object_vars($postarr) : (array) $postarr;
    $post = get_post((int) ($postarr['ID'] ?? 0));
    if ($post === null) {
        return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    }
    $existing = $post->to_array();
    $merged = array_merge($existing, wp_unslash($postarr));
    $merged['post_category'] = $postarr['post_category'] ?? null;
    if ($merged['post_category'] === null) {
        unset($merged['post_category']);
    }
    if (isset($merged['tags_input'])) {
        // Given explicitly by the caller only.
    } else {
        unset($merged['tags_input']);
    }
    if (!isset($postarr['tags_input'])) {
        unset($merged['tags_input']);
    }
    if (isset($postarr['post_date']) && !isset($postarr['post_date_gmt'])) {
        $merged['post_date_gmt'] = '';
    }
    if ($merged['post_type'] === 'attachment' && isset($postarr['post_status']) && $postarr['post_status'] !== 'trash') {
        $merged['post_status'] = 'inherit';
    }
    return wp_insert_post(wp_slash($merged), $wp_error, $fire_after_hooks);
}

function wp_publish_post($post)
{
    $post = get_post($post);
    if ($post === null || $post->post_status === 'publish') {
        return;
    }
    $old = $post->post_status;
    _minn_post_writer()->update($post->ID, ['post_status' => 'publish']);
    wp_cache_delete($post->ID, 'posts');
    $post = get_post($post->ID);
    _minn_post_writer()->recountTaxonomiesOf($post->ID);
    wp_transition_post_status('publish', $old, $post);
    do_action("edit_post_{$post->post_type}", $post->ID, $post);
    do_action('edit_post', $post->ID, $post);
    do_action("save_post_{$post->post_type}", $post->ID, $post, true);
    do_action('save_post', $post->ID, $post, true);
    do_action('wp_insert_post', $post->ID, $post, true);
    do_action('wp_after_insert_post', $post, true, null);
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
    add_post_meta($post->ID, '_wp_trash_meta_status', $post->post_status);
    add_post_meta($post->ID, '_wp_trash_meta_time', (string) time());
    _minn_post_writer()->update($post->ID, ['post_status' => 'trash']);
    wp_cache_delete($post->ID, 'posts');
    _minn_post_writer()->recountTaxonomiesOf($post->ID);
    wp_transition_post_status('trash', $post->post_status, get_post($post->ID));
    do_action('trashed_post', $post->ID, $post->post_status);
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
    _minn_post_writer()->update($post->ID, ['post_status' => $new]);
    wp_cache_delete($post->ID, 'posts');
    _minn_post_writer()->recountTaxonomiesOf($post->ID);
    wp_transition_post_status($new, 'trash', get_post($post->ID));
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
    $check = apply_filters('pre_delete_post', null, $post, $force_delete);
    if ($check !== null) {
        return $check;
    }
    do_action('before_delete_post', $post->ID, $post);
    foreach (wp_get_post_revisions($post->ID) as $revision) {
        wp_delete_post_revision($revision);
    }
    $db = Runtime::current()->db;
    if ($post->post_type === 'page') {
        $db->execute("UPDATE {$db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = 'page'", [(int) $post->post_parent, $post->ID]);
    }
    $db->execute("UPDATE {$db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = 'attachment'", [(int) $post->post_parent, $post->ID]);
    do_action('delete_post', $post->ID, $post);
    $taxonomies = _minn_post_writer()->taxonomiesOf($post->ID);
    _minn_post_writer()->destroy($post->ID);
    foreach ($taxonomies as $taxonomy) {
        _minn_post_writer()->recount($taxonomy);
    }
    wp_cache_delete($post->ID, 'posts');
    wp_cache_delete($post->ID, 'post_meta');
    do_action('deleted_post', $post->ID, $post);
    do_action('after_delete_post', $post->ID, $post);
    return $post;
}

function wp_delete_post_revision($revision)
{
    $revision = get_post($revision);
    if ($revision === null) {
        return $revision;
    }
    do_action('before_delete_post', $revision->ID, $revision);
    do_action('delete_post', $revision->ID, $revision);
    _minn_post_writer()->destroy($revision->ID);
    wp_cache_delete($revision->ID, 'posts');
    do_action('deleted_post', $revision->ID, $revision);
    do_action('wp_delete_post_revision', $revision->ID, $revision);
    return $revision;
}

function wp_delete_attachment($post_id, $force_delete = false)
{
    return wp_delete_post($post_id, true);
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
    return Runtime::registry()->postType((string) $post_type)['supports'] ?? [];
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
    return sprintf('<a href="%1$s" title="%2$s" rel="author">%3$s</a>', esc_url(get_author_posts_url($author->ID)), esc_attr(sprintf('Posts by %s', get_the_author())), get_the_author());
}

function count_user_posts($userid, $post_type = 'post', $public_only = false)
{
    $db = Runtime::current()->db;
    $types = (array) $post_type;
    $statuses = $public_only ? ['publish'] : ['publish', 'private'];
    $count = $db->value("SELECT COUNT(*) FROM {$db->table('posts')} WHERE post_author = ? AND post_type IN (" . implode(',', array_fill(0, count($types), '?')) . ') AND post_status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')', [(int) $userid, ...$types, ...$statuses]);
    return apply_filters('get_usernumposts', (string) (int) $count, $userid, $post_type, $public_only);
}

function get_the_author_link($use_title_attr = true)
{
    return get_the_author_posts_link();
}

function is_multi_author()
{
    return false;
}
