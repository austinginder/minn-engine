<?php
/** The robots meta tag the reference prints from wp_head. */

function wp_robots()
{
    $robots = apply_filters('wp_robots', []);
    $directives = [];
    // A string is directive:value, even when empty; anything else counts when truthy (probe robots-template).
    foreach ((array) $robots as $directive => $value) {
        if (is_string($value)) {
            $directives[] = $directive . ':' . $value;
        } elseif ($value) {
            $directives[] = $directive;
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
    // A private site is kept out entirely: noindex, nofollow (probe robots-template).
    return (int) get_option('blog_public') === 0 ? wp_robots_no_robots($robots) : $robots;
}

function wp_robots_noindex_embeds($robots)
{
    return is_embed() ? wp_robots_no_robots($robots) : $robots;
}

function wp_robots_noindex_search($robots)
{
    return is_search() ? wp_robots_no_robots($robots) : $robots;
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

/** The referrer meta tag that sends only the origin to other sites (probe deprecated). */
function wp_strict_cross_origin_referrer()
{
    echo "\t<meta name='referrer' content='strict-origin-when-cross-origin' />\n\t";
}
