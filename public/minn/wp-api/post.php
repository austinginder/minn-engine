<?php
/** The post object and the small reads plugin code does at load time. A fuller WP_Query comes with the content layer. */

use Minn\Content\Posts;
use Minn\Runtime\Runtime;

function get_post($post = null, $output = OBJECT, $filter = 'raw')
{
    if ($post === null || $post === 0 || $post === '') {
        $post = Runtime::current()->get('post');
        if ($post === null) {
            return null;
        }
    }
    if ($post instanceof WP_Post) {
        $object = $post;
    } elseif (is_object($post) && isset($post->ID)) {
        $object = new WP_Post($post);
    } else {
        $row = (new Posts(Runtime::current()->db))->find((int) $post);
        if ($row === null) {
            return null;
        }
        $object = new WP_Post((object) $row);
    }
    if ($output === ARRAY_A) {
        return $object->to_array();
    }
    if ($output === ARRAY_N) {
        return array_values($object->to_array());
    }
    return $object;
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
    return $post === null ? false : $post->post_status;
}

function get_post_field($field, $post = null, $context = 'display')
{
    $post = get_post($post);
    return $post === null ? '' : ($post->{$field} ?? '');
}

function get_the_title($post = 0)
{
    $post = get_post($post);
    return $post === null ? '' : apply_filters('the_title', $post->post_title, $post->ID);
}

function get_post_types($args = [], $output = 'names', $operator = 'and')
{
    $types = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face'];
    if (isset($args['public']) && $args['public']) {
        $types = ['post', 'page', 'attachment'];
    }
    return array_combine($types, $types);
}

function post_type_exists($post_type)
{
    return isset(get_post_types()[$post_type]);
}

function is_post_type_hierarchical($post_type)
{
    return $post_type === 'page';
}

function get_post_stati($args = [], $output = 'names', $operator = 'and')
{
    $stati = ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed'];
    return array_combine($stati, $stati);
}

function get_post_statuses()
{
    return ['draft' => 'Draft', 'pending' => 'Pending Review', 'private' => 'Private', 'publish' => 'Published'];
}

function get_page_statuses()
{
    return ['draft' => 'Draft', 'pending' => 'Pending Review', 'private' => 'Private', 'publish' => 'Published'];
}

function is_sticky($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $stickies = get_option('sticky_posts');
    return is_array($stickies) && in_array((int) $post_id, array_map('intval', $stickies), true);
}

function wp_get_post_parent_id($post = null)
{
    $post = get_post($post);
    return $post === null ? false : (int) $post->post_parent;
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
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    Runtime::current()->set('post', $post);
    $GLOBALS['post'] = $post;
    do_action_ref_array('the_post', [&$post, null]);
    return true;
}

function wp_reset_postdata()
{
    $main = Runtime::current()->get('main_post');
    Runtime::current()->set('post', $main);
    $GLOBALS['post'] = $main;
}

function wp_reset_query()
{
    wp_reset_postdata();
}
