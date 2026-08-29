<?php
/** The main query, query vars, conditional tags, and the loop. */

use Minn\Runtime\Runtime;

/** @internal the main query the page runs on; a CLI boot gets an empty one */
function _minn_main_query(): WP_Query
{
    if (!isset($GLOBALS['wp_the_query']) || !$GLOBALS['wp_the_query'] instanceof WP_Query) {
        $GLOBALS['wp_the_query'] = new WP_Query();
        $GLOBALS['wp_the_query']->posts = [];
        $GLOBALS['wp_the_query']->post_count = 0;
    }
    if (!isset($GLOBALS['wp_query']) || !$GLOBALS['wp_query'] instanceof WP_Query) {
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
    }
    return $GLOBALS['wp_query'];
}

function get_query_var($query_var, $default_value = '')
{
    return _minn_main_query()->get($query_var, $default_value);
}

function set_query_var($query_var, $value)
{
    _minn_main_query()->set($query_var, $value);
}

function get_search_query($escaped = true)
{
    $query = apply_filters('get_search_query', get_query_var('s'));
    return $escaped ? esc_attr($query) : $query;
}

function the_search_query()
{
    echo esc_attr(apply_filters('the_search_query', get_search_query(false)));
}

function get_queried_object()
{
    return _minn_main_query()->get_queried_object();
}

function get_queried_object_id()
{
    return _minn_main_query()->get_queried_object_id();
}

function is_main_query()
{
    return _minn_main_query()->is_main_query();
}

function in_the_loop()
{
    return (bool) _minn_main_query()->in_the_loop;
}

function have_posts()
{
    return _minn_main_query()->have_posts();
}

function the_post()
{
    _minn_main_query()->the_post();
}

function rewind_posts()
{
    _minn_main_query()->rewind_posts();
}

function have_comments()
{
    return _minn_main_query()->have_comments();
}

function the_comment()
{
    _minn_main_query()->the_comment();
}

function is_singular($post_types = '')
{
    return _minn_main_query()->is_singular($post_types);
}

function is_single($post = '')
{
    return _minn_main_query()->is_single($post);
}

function is_page($page = '')
{
    return _minn_main_query()->is_page($page);
}

function is_home()
{
    return _minn_main_query()->is_home();
}

function is_front_page()
{
    return _minn_main_query()->is_front_page();
}

function is_archive()
{
    return _minn_main_query()->is_archive();
}

function is_search()
{
    return _minn_main_query()->is_search();
}

function is_404()
{
    return _minn_main_query()->is_404();
}

function is_author($author = '')
{
    return _minn_main_query()->is_author($author);
}

function is_category($category = '')
{
    return _minn_main_query()->is_category($category);
}

function is_tag($tag = '')
{
    return _minn_main_query()->is_tag($tag);
}

function is_tax($taxonomy = '', $term = '')
{
    return _minn_main_query()->is_tax($taxonomy, $term);
}

function is_date()
{
    return _minn_main_query()->is_date();
}

function is_year()
{
    return _minn_main_query()->is_year();
}

function is_month()
{
    return _minn_main_query()->is_month();
}

function is_day()
{
    return _minn_main_query()->is_day();
}

function is_time()
{
    return _minn_main_query()->is_time();
}

function is_feed($feeds = '')
{
    return _minn_main_query()->is_feed($feeds);
}

function is_comment_feed()
{
    return _minn_main_query()->is_comment_feed();
}

function is_attachment($attachment = '')
{
    return _minn_main_query()->is_attachment($attachment);
}

function is_post_type_archive($post_types = '')
{
    return _minn_main_query()->is_post_type_archive($post_types);
}

function is_paged()
{
    return _minn_main_query()->is_paged();
}

function is_preview()
{
    return _minn_main_query()->is_preview();
}

function is_embed()
{
    return _minn_main_query()->is_embed();
}

function is_privacy_policy()
{
    return _minn_main_query()->is_privacy_policy();
}

function is_trackback()
{
    return _minn_main_query()->is_trackback();
}

function is_robots()
{
    return _minn_main_query()->is_robots();
}

function is_favicon()
{
    return _minn_main_query()->is_favicon();
}

function is_admin_bar_showing()
{
    return apply_filters('show_admin_bar', is_user_logged_in() && !is_admin());
}

function show_admin_bar($show)
{
    Runtime::current()->set('show_admin_bar', (bool) $show);
}

function wp_title($sep = '&raquo;', $display = true, $seplocation = '')
{
    $title = apply_filters('wp_title', '', $sep, $seplocation);
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}

function single_post_title($prefix = '', $display = true)
{
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return null;
    }
    $title = apply_filters('single_post_title', $post->post_title, $post);
    if ($display) {
        echo $prefix . $title;
        return null;
    }
    return $prefix . $title;
}

function get_the_archive_title()
{
    $title = '';
    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        $title = $term instanceof WP_Term ? $term->name : '';
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_search()) {
        $title = 'Search Results for: ' . get_search_query();
    } elseif (is_post_type_archive()) {
        $object = get_queried_object();
        $title = $object instanceof WP_Post_Type ? $object->labels->name : '';
    }
    return apply_filters('get_the_archive_title', $title);
}

function get_the_archive_description()
{
    return apply_filters('get_the_archive_description', is_category() || is_tag() || is_tax() ? term_description() : '');
}
