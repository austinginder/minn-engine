<?php
// The embed page's template tags and its head and footer steps (the template itself is theme-compat/embed.php).

use Minn\Front\EmbedCard;

function get_embed_template()
{
    $object = get_queried_object();
    $templates = [];
    if (!empty($object->post_type)) {
        $format = get_post_format($object);
        if ($format) {
            $templates[] = "embed-{$object->post_type}-{$format}.php";
        }
        $templates[] = "embed-{$object->post_type}.php";
    }
    $templates[] = 'embed.php';
    return get_query_template('embed', $templates);
}

function enqueue_embed_scripts()
{
    do_action('enqueue_embed_scripts');
}

/** The embed card's stylesheet, inline; a plugin that took print_embed_styles off embed_head keeps it off. */
function wp_enqueue_embed_styles()
{
    if (!has_action('embed_head', 'print_embed_styles')) {
        return;
    }
    remove_action('embed_head', 'print_embed_styles');
    wp_register_style('wp-embed-template', false);
    wp_add_inline_style('wp-embed-template', (string) file_get_contents(MINN_ENGINE_DIR . '/assets/wp-embed-template.min.css'));
    wp_enqueue_style('wp-embed-template');
}

function print_embed_styles()
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_enqueue_embed_styles');
    echo '<style>' . file_get_contents(MINN_ENGINE_DIR . '/assets/wp-embed-template.min.css') . "</style>\n";
}

function print_embed_scripts()
{
    wp_print_inline_script_tag(file_get_contents(MINN_ENGINE_DIR . '/assets/wp-embed-template.min.js') . "\n//# sourceURL=" . includes_url('js/wp-embed-template.min.js'));
}

function the_excerpt_embed()
{
    echo apply_filters('the_excerpt_embed', get_the_excerpt());
}

/** An attachment's embed shows the attachment itself as its excerpt. */
function wp_embed_excerpt_attachment($content)
{
    return is_attachment() ? prepend_attachment('') : $content;
}

function the_embed_site_title()
{
    echo EmbedCard::siteTitle();
}

function print_embed_comments_button()
{
    echo EmbedCard::commentsButton();
}

function print_embed_sharing_button()
{
    echo EmbedCard::sharingButton();
}

function print_embed_sharing_dialog()
{
    echo EmbedCard::sharingDialog();
}

/** The locale's own stylesheet (a theme's {locale}.css, or rtl.css), when there is one. */
function locale_stylesheet()
{
    $uri = get_locale_stylesheet_uri();
    if ($uri) {
        printf('<link rel="stylesheet" href="%s" media="screen" />', $uri);
    }
}

/** @internal the embed card's featured image block when it has this shape ('rectangular' above the title, 'square' beside it) */
function _minn_embed_featured_image($thumbnail, $shape)
{
    echo EmbedCard::featuredImage($thumbnail, (string) $shape);
}

/** Kept on wp_head for plugins that look for it; the host script is queued where an embed card appears (wp_maybe_enqueue_oembed_host_js). */
function wp_oembed_add_host_js()
{
}

/** An embed card queues the script that sizes it, when the host script is wanted on this page; the HTML comes back as it was (probe theme-symbols). */
function wp_maybe_enqueue_oembed_host_js($html)
{
    if (has_action('wp_head', 'wp_oembed_add_host_js') && preg_match('/<blockquote\s[^>]*?wp-embedded-content/', (string) $html)) {
        wp_enqueue_script('wp-embed');
    }
    return $html;
}

/** A video address embedded as a [video] shortcode (with the size asked for, when both sides are), through wp_embed_handler_video. */
function wp_embed_handler_video($matches, $attr, $url, $rawattr)
{
    $size = !empty($rawattr['width']) && !empty($rawattr['height']) ? sprintf('width="%d" height="%d" ', (int) $rawattr['width'], (int) $rawattr['height']) : '';
    return apply_filters('wp_embed_handler_video', sprintf('[video %s src="%s" /]', $size, esc_url($url)), $attr, $url, $rawattr);
}
