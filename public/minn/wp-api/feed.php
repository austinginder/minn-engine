<?php
// Feed helpers plugin code calls; the engine renders its own feeds.

function feed_content_type($type = '')
{
    if ($type === '') {
        $type = get_default_feed();
    }
    $types = ['rss' => 'application/rss+xml', 'rss2' => 'application/rss+xml', 'rss-http' => 'text/xml', 'atom' => 'application/atom+xml', 'rdf' => 'application/rdf+xml'];
    return apply_filters('feed_content_type', $types[$type] ?? 'application/octet-stream', $type);
}

function get_bloginfo_rss($show = '')
{
    // Observed: the value is HTML-escaped whole, tags included, and typographic quotes stay.
    return apply_filters('get_bloginfo_rss', esc_html(get_bloginfo($show)), $show);
}

function bloginfo_rss($show = '')
{
    echo apply_filters('bloginfo_rss', get_bloginfo_rss($show), $show);
}

function the_permalink_rss()
{
    echo esc_url(apply_filters('the_permalink_rss', get_permalink()));
}

function self_link()
{
    $host = wp_parse_url(home_url());
    echo esc_url(apply_filters('self_link', set_url_scheme('http://' . ($_SERVER['HTTP_HOST'] ?? ($host['host'] ?? '')) . wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))));
}

/** The post title for a feed item, filtered but otherwise plain (probed). */
function get_the_title_rss($post = 0)
{
    return apply_filters('the_title_rss', get_the_title($post), $post);
}

/** The rendered content for a feed item: the full pipeline, CDATA close escaped, then the feed filter. */
function get_the_content_feed($feed_type = null)
{
    if ($feed_type === null) {
        $feed_type = 'rss2';
    }
    $post = get_post();
    if ($post === null) {
        return '';
    }
    $content = \Minn\Theme\ClassicContent::render($post->to_array(), null);
    $content = str_replace(']]>', ']]&gt;', $content);
    return apply_filters('the_content_feed', $content, $feed_type);
}
