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
