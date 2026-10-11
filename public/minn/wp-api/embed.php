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
    echo "\t<style>\n\t\t" . file_get_contents(MINN_ENGINE_DIR . '/assets/wp-embed-template.min.css') . "\t</style>\n\t";
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

// Embed helpers (probe embed-helpers).

/** The default embed handlers (plugins_loaded 0): YouTube embed addresses, then audio and video files at 9999; none under load_default_embeds false. */
function wp_maybe_load_embeds()
{
    if (!apply_filters('load_default_embeds', true)) {
        return;
    }
    wp_embed_register_handler('youtube_embed_url', '#https?://(www\\.)?youtube\\.com/(?:v|embed)/([^/]+)#i', 'wp_embed_handler_youtube');
    wp_embed_register_handler('audio', '#^https?://.+?\\.(' . implode('|', wp_get_audio_extensions()) . ')$#i', apply_filters('wp_audio_embed_handler', 'wp_embed_handler_audio'), 9999);
    wp_embed_register_handler('video', '#^https?://.+?\\.(' . implode('|', wp_get_video_extensions()) . ')$#i', apply_filters('wp_video_embed_handler', 'wp_embed_handler_video'), 9999);
}

/** A YouTube embed address embedded as its watch address, through wp_embed_handler_youtube. */
function wp_embed_handler_youtube($matches, $attr, $url, $rawattr)
{
    $embed = $GLOBALS['wp_embed']->autoembed(sprintf('https://youtube.com/watch?v=%s', urlencode((string) ($matches[2] ?? ''))));
    return apply_filters('wp_embed_handler_youtube', $embed, $attr, $url, $rawattr);
}

/** An audio address embedded as an [audio] shortcode, through wp_embed_handler_audio. */
function wp_embed_handler_audio($matches, $attr, $url, $rawattr)
{
    return apply_filters('wp_embed_handler_audio', sprintf('[audio src="%s" /]', esc_url($url)), $attr, $url, $rawattr);
}

/** json or xml; anything else is json. */
function wp_oembed_ensure_format($format)
{
    return in_array($format, ['json', 'xml'], true) ? $format : 'json';
}

/** oembed/1.0's embed and proxy routes (rest_api_init), served by WP_oEmbed_Controller. */
function wp_oembed_register_route()
{
    $controller = new WP_oEmbed_Controller();
    $controller->register_routes();
}

/**
 * An oEmbed answer made safe to print (oembed_dataparse 10): for a rich or
 * video answer from a provider the site does not trust, only the first
 * blockquote and the last empty iframe are kept, through kses, and both
 * get a fresh secret (_minn_oembed_secured); false when there is no iframe.
 */
function wp_filter_oembed_result($result, $data, $url)
{
    if ($result === false || !is_object($data) || !in_array($data->type ?? null, ['rich', 'video'], true) || _wp_oembed_get_object()->get_provider($url, ['discover' => false]) !== false) {
        return $result;
    }
    $allowed = ['a' => ['href' => true], 'blockquote' => [], 'iframe' => array_fill_keys(['src', 'width', 'height', 'frameborder', 'marginwidth', 'marginheight', 'scrolling', 'title'], true)];
    if (!preg_match('|(<blockquote>.*?</blockquote>)?.*(<iframe.*?></iframe>)|ms', wp_kses((string) $result, $allowed), $content)) {
        return false;
    }
    return _minn_oembed_secured($content[1] . $content[2]);
}

/** @internal an embed's blockquote and iframe tied to one new secret: the iframe sandboxed, hidden beside a blockquote, its src carrying the secret */
function _minn_oembed_secured(string $html): string
{
    $secret = wp_generate_password(10, false);
    $quoted = str_starts_with($html, '<blockquote>');
    $html = str_replace('<blockquote>', sprintf('<blockquote class="wp-embedded-content" data-secret="%s">', $secret), $html);
    return (string) preg_replace_callback('/<iframe([^>]*)>/', static function (array $m) use ($secret, $quoted): string {
        $attributes = preg_replace_callback('/ src="([^"]*)"/', static fn (array $src) => sprintf(' src="%1$s" data-secret="%2$s"', esc_attr(esc_url_raw(html_entity_decode($src[1], ENT_QUOTES) . '#?secret=' . $secret)), $secret), $m[1]);
        return '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"' . ($quoted ? ' style="position: absolute; visibility: hidden;"' : '') . $attributes . '>';
    }, $html, 1);
}

/** One of the site's own posts embedded without a request (pre_oembed_result 10): its oEmbed data made into markup through oembed_dataparse; any other address keeps the result given. */
function wp_filter_pre_oembed_result($result, $url, $args)
{
    $data = get_oembed_response_data_for_url($url, $args);
    return $data ? _wp_oembed_get_object()->data2html((object) $data, $url) : $result;
}

/** @internal the global embed object, made once without the constructor: the defaults hook it themselves, in the reference's order */
function _minn_wp_embed(): WP_Embed
{
    return $GLOBALS['wp_embed'] ??= (new ReflectionClass(WP_Embed::class))->newInstanceWithoutConstructor();
}
