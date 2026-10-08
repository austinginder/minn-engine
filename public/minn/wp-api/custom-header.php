<?php
/**
 * The custom header and custom background, as classic themes read them
 * (probe custom-header): the header image (a chosen one, the theme's
 * default with %s for its folder, a random default or upload, or none),
 * the header text colour and whether it shows, the header video, the
 * markup themes print, and the background image, colour and the style the
 * wp_head callback prints. The supports' defaults are merged at wp_loaded
 * (Runtime\ThemeSupports::justInTime), which also hooks their wp_head
 * callbacks.
 */

use Minn\Runtime\Runtime;

/** The header image's address; false when there is none or it was removed. */
function get_header_image()
{
    $url = get_theme_mod('header_image', get_theme_support('custom-header', 'default-image'));
    if ($url === 'remove-header') {
        return false;
    }
    if (is_random_header_image()) {
        $url = get_random_header_image();
    }
    $url = apply_filters('get_header_image', $url);
    return is_string($url) ? sanitize_url(set_url_scheme(trim($url))) : false;
}

function header_image()
{
    $image = get_header_image();
    if ($image) {
        echo esc_url($image);
    }
}

function has_header_image()
{
    return (bool) get_header_image();
}

function get_header_textcolor()
{
    return get_theme_mod('header_textcolor', get_theme_support('custom-header', 'default-text-color'));
}

function header_textcolor()
{
    echo get_header_textcolor();
}

/** Whether the site title and tagline show over the header: the theme must support header text, and "blank" hides it. */
function display_header_text()
{
    if (!current_theme_supports('custom-header', 'header-text')) {
        return false;
    }
    return get_theme_mod('header_textcolor', get_theme_support('custom-header', 'default-text-color')) !== 'blank';
}

/** The header's data: the chosen image's, a random default's, or the theme's default; its size from the support. */
function get_custom_header()
{
    $folders = [get_template_directory_uri(), get_stylesheet_directory_uri()];
    if (is_random_header_image()) {
        $data = _get_random_header_data();
    } else {
        $data = get_theme_mod('header_image_data');
        if (!$data && current_theme_supports('custom-header', 'default-image')) {
            $url = vsprintf((string) get_theme_support('custom-header', 'default-image'), $folders);
            $data = ['url' => $url, 'thumbnail_url' => $url];
            foreach ((array) ($GLOBALS['_wp_default_headers'] ?? []) as $header) {
                if (vsprintf((string) ($header['url'] ?? ''), $folders) === $url) {
                    $data = ['url' => $url, 'thumbnail_url' => vsprintf((string) ($header['thumbnail_url'] ?? ''), $folders)] + $header;
                    break;
                }
            }
        }
    }
    return (object) wp_parse_args($data, [
        'url' => '',
        'thumbnail_url' => '',
        'width' => get_theme_support('custom-header', 'width'),
        'height' => get_theme_support('custom-header', 'height'),
        'video' => get_theme_support('custom-header', 'video'),
    ]);
}

/** The header image as an img tag, its size from the header data, the loading attributes a header image earns; '' when there is none. */
function get_header_image_tag($attr = [])
{
    $header = get_custom_header();
    $header->url = get_header_image();
    if (!$header->url) {
        return '';
    }
    $alt = '';
    if (!empty($header->attachment_id)) {
        $stored = get_post_meta($header->attachment_id, '_wp_attachment_image_alt', true);
        $alt = is_string($stored) ? $stored : '';
    }
    $attr = wp_parse_args($attr, ['src' => $header->url, 'width' => absint($header->width), 'height' => absint($header->height), 'alt' => $alt]);
    if (empty($attr['srcset']) && !empty($header->attachment_id)) {
        $meta = get_post_meta($header->attachment_id, '_wp_attachment_metadata', true);
        $size = [absint($header->width), absint($header->height)];
        $srcset = is_array($meta) ? wp_calculate_image_srcset($size, $header->url, $meta, $header->attachment_id) : false;
        if ($srcset) {
            $attr['srcset'] = $srcset;
            $attr['sizes'] ??= wp_calculate_image_sizes($size, $header->url, $meta, $header->attachment_id);
        }
    }
    $attr = array_merge($attr, wp_get_loading_optimization_attributes('img', $attr, 'get_header_image_tag'));
    foreach (['loading', 'fetchpriority', 'decoding'] as $name) {
        if (isset($attr[$name]) && !$attr[$name]) {
            unset($attr[$name]);
        }
    }
    $attr = array_map('esc_attr', apply_filters('get_header_image_tag_attributes', $attr, $header));
    $html = '<img';
    foreach ($attr as $name => $value) {
        $html .= ' ' . $name . '="' . $value . '"';
    }
    return apply_filters('get_header_image_tag', $html . ' />', $header, $attr);
}

function the_header_image_tag($attr = [])
{
    echo get_header_image_tag($attr);
}

function has_custom_header()
{
    return has_header_image() || (has_header_video() && is_header_video_active());
}

/** The header video's address: an uploaded video's, or the external one; false when there is neither. */
function get_header_video_url()
{
    $id = absint(get_theme_mod('header_video'));
    $url = $id ? wp_get_attachment_url($id) : get_theme_mod('external_header_video');
    $url = apply_filters('get_header_video_url', $url);
    if (!$id && !$url) {
        return false;
    }
    return sanitize_url(set_url_scheme((string) $url));
}

function the_header_video_url()
{
    $video = get_header_video_url();
    if ($video) {
        echo esc_url($video);
    }
}

function has_header_video()
{
    return (bool) get_header_video_url();
}

/** Whether the header video plays here: the theme supports it and its video-active-callback says so (no callback: everywhere). */
function is_header_video_active()
{
    if (!get_theme_support('custom-header', 'video')) {
        return false;
    }
    $callback = get_theme_support('custom-header', 'video-active-callback');
    $show = empty($callback) || !is_callable($callback) ? true : call_user_func($callback);
    return apply_filters('is_header_video_active', $show);
}

/** What the header video script is told: the video, its poster and size, and its words. */
function get_header_video_settings()
{
    $header = get_custom_header();
    $video = get_header_video_url();
    $type = wp_check_filetype((string) $video, wp_get_mime_types());
    $settings = [
        'mimeType' => '',
        'posterUrl' => get_header_image(),
        'videoUrl' => $video,
        'width' => absint($header->width),
        'height' => absint($header->height),
        'minWidth' => 900,
        'minHeight' => 500,
        'l10n' => ['pause' => __('Pause'), 'play' => __('Play'), 'pauseSpeak' => __('Video is paused.'), 'playSpeak' => __('Video is playing.')],
    ];
    if (preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#', (string) $video)) {
        $settings['mimeType'] = 'video/x-youtube';
    } elseif (!empty($type['type'])) {
        $settings['mimeType'] = $type['type'];
    }
    return apply_filters('header_video_settings', $settings);
}

function get_custom_header_markup()
{
    if (!has_custom_header() && !is_customize_preview()) {
        return '';
    }
    return sprintf('<div id="wp-custom-header" class="wp-custom-header">%s</div>', get_header_image_tag());
}

/** The header markup printed, and the video script queued with its settings when a video plays. */
function the_custom_header_markup()
{
    $markup = get_custom_header_markup();
    if ($markup === '') {
        return;
    }
    echo $markup;
    if (is_header_video_active() && (has_header_video() || is_customize_preview())) {
        wp_enqueue_script('wp-custom-header');
        wp_localize_script('wp-custom-header', '_wpCustomHeaderSettings', get_header_video_settings());
    }
}

/** Whether the header image is picked at random ('any', 'default' or 'uploaded'). */
function is_random_header_image($type = 'any')
{
    $mod = get_theme_mod('header_image', get_theme_support('custom-header', 'default-image'));
    if ($type === 'any') {
        return $mod === 'random-default-image' || $mod === 'random-uploaded-image' || (empty($mod) && get_random_header_image() !== '');
    }
    return $mod === "random-{$type}-image" || ($type === 'default' && empty($mod) && get_random_header_image() !== '');
}

function get_random_header_image()
{
    $header = _get_random_header_data();
    return empty($header->url) ? '' : $header->url;
}

/** One header picked at random (once a request) from the uploads or the registered defaults, as the header_image mod says. */
function _get_random_header_data()
{
    $runtime = Runtime::current();
    $picked = $runtime->get('random_header');
    if ($picked !== null) {
        return $picked;
    }
    $mod = get_theme_mod('header_image', '');
    $defaults = (array) ($GLOBALS['_wp_default_headers'] ?? []);
    $headers = match (true) {
        $mod === 'random-uploaded-image' => get_uploaded_header_images(),
        $defaults !== [] && ($mod === 'random-default-image' || current_theme_supports('custom-header', 'random-default')) => $defaults,
        default => [],
    };
    if ($headers === []) {
        return new stdClass();
    }
    $picked = (object) $headers[array_rand($headers)];
    $folders = [get_template_directory_uri(), get_stylesheet_directory_uri()];
    $picked->url = vsprintf((string) ($picked->url ?? ''), $folders);
    $picked->thumbnail_url = vsprintf((string) ($picked->thumbnail_url ?? ''), $folders);
    $runtime->set('random_header', $picked);
    return $picked;
}

/** The images uploaded as headers for the active theme, by attachment id. */
function get_uploaded_header_images()
{
    $images = [];
    foreach (get_posts(['post_type' => 'attachment', 'meta_key' => '_wp_attachment_is_custom_header', 'meta_value' => get_option('stylesheet'), 'orderby' => 'none', 'nopaging' => true]) as $header) {
        $url = sanitize_url((string) wp_get_attachment_url($header->ID));
        $meta = (array) wp_get_attachment_metadata($header->ID);
        $images[$header->ID] = array_filter([
            'attachment_id' => $header->ID,
            'url' => $url,
            'thumbnail_url' => $url,
            'alt_text' => get_post_meta($header->ID, '_wp_attachment_image_alt', true),
            'attachment_parent' => $meta['attachment_parent'] ?? '',
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
        ], static fn ($value) => $value !== null);
    }
    return $images;
}

function register_default_headers($headers)
{
    $GLOBALS['_wp_default_headers'] = array_merge((array) ($GLOBALS['_wp_default_headers'] ?? []), (array) $headers);
}

/** Registered default headers taken away by name; true or false for one, a list of them for several. */
function unregister_default_headers($header)
{
    if (is_array($header)) {
        return array_map('unregister_default_headers', $header);
    }
    if (!isset($GLOBALS['_wp_default_headers'][$header])) {
        return false;
    }
    unset($GLOBALS['_wp_default_headers'][$header]);
    return true;
}

function get_background_image()
{
    return get_theme_mod('background_image', get_theme_support('custom-background', 'default-image'));
}

function background_image()
{
    echo get_background_image();
}

function get_background_color()
{
    return get_theme_mod('background_color', get_theme_support('custom-background', 'default-color'));
}

function background_color()
{
    echo get_background_color();
}

/**
 * The custom background's style for wp_head: the colour unless it is the
 * theme's default, the image with its position, size, repeat and
 * attachment (each kept to the values a background allows); nothing when
 * there is neither.
 */
function _custom_background_cb()
{
    $image = set_url_scheme(get_background_image());
    $color = get_background_color();
    if ($color === get_theme_support('custom-background', 'default-color')) {
        $color = false;
    }
    if (!$image && !$color) {
        if (is_customize_preview()) {
            printf('<style%s id="custom-background-css"></style>', current_theme_supports('html5', 'style') ? '' : ' type="text/css"');
        }
        return;
    }
    $style = $color ? "background-color: #{$color};" : '';
    if ($image) {
        $mod = static function (string $name, string $default, array $allowed): string {
            $value = get_theme_mod("background_{$name}", get_theme_support('custom-background', "default-{$name}"));
            return in_array($value, $allowed, true) ? $value : $default;
        };
        $style .= ' background-image: url("' . sanitize_url($image) . '");';
        $style .= ' background-position: ' . $mod('position_x', 'left', ['left', 'center', 'right']) . ' ' . $mod('position_y', 'top', ['top', 'center', 'bottom']) . ';';
        $style .= ' background-size: ' . $mod('size', 'auto', ['auto', 'contain', 'cover']) . ';';
        $style .= ' background-repeat: ' . $mod('repeat', 'repeat', ['repeat-x', 'repeat-y', 'repeat', 'no-repeat']) . ';';
        $style .= ' background-attachment: ' . $mod('attachment', 'scroll', ['fixed', 'scroll']) . ';';
    }
    printf("<style%s id=\"custom-background-css\">\nbody.custom-background { %s }\n</style>\n", current_theme_supports('html5', 'style') ? '' : ' type="text/css"', trim($style));
}

function add_custom_image_header($wp_head_callback, $admin_head_callback, $admin_preview_callback = '')
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'add_theme_support( \'custom-header\', $args )');
    $args = ['wp-head-callback' => $wp_head_callback, 'admin-head-callback' => $admin_head_callback];
    if ($admin_preview_callback) {
        $args['admin-preview-callback'] = $admin_preview_callback;
    }
    return add_theme_support('custom-header', $args);
}

function remove_custom_image_header()
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'remove_theme_support( \'custom-header\' )');
    return remove_theme_support('custom-header');
}

function add_custom_background($wp_head_callback = '', $admin_head_callback = '', $admin_preview_callback = '')
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'add_theme_support( \'custom-background\', $args )');
    $args = array_filter(['wp-head-callback' => $wp_head_callback, 'admin-head-callback' => $admin_head_callback, 'admin-preview-callback' => $admin_preview_callback]);
    return add_theme_support('custom-background', $args);
}

function remove_custom_background()
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'remove_theme_support( \'custom-background\' )');
    return remove_theme_support('custom-background');
}
