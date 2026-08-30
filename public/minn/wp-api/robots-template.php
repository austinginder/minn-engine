<?php
/** The robots meta tag the reference prints from wp_head. */

function wp_robots()
{
    $robots = apply_filters('wp_robots', []);
    $directives = [];
    foreach ((array) $robots as $directive => $value) {
        if ($value === true) {
            $directives[] = $directive;
        } elseif (is_string($value) && $value !== '') {
            $directives[] = $directive . ':' . $value;
        }
    }
    if ($directives === []) {
        return;
    }
    echo "<meta name='robots' content='" . esc_attr(implode(', ', $directives)) . "' />\n";
}

function wp_robots_max_image_preview_large($robots)
{
    if ((int) get_option('blog_public') !== 0) {
        $robots['max-image-preview'] = 'large';
    }
    return $robots;
}

function wp_robots_noindex($robots)
{
    if ((int) get_option('blog_public') === 0) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}

function wp_robots_noindex_embeds($robots)
{
    if (is_embed()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}

function wp_robots_noindex_search($robots)
{
    if (is_search()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}

function wp_robots_no_robots($robots)
{
    $robots['noindex'] = true;
    $robots[(int) get_option('blog_public') === 0 ? 'nofollow' : 'follow'] = true;
    return $robots;
}

function wp_robots_sensitive_page($robots)
{
    $robots['noindex'] = true;
    $robots['noarchive'] = true;
    return $robots;
}
