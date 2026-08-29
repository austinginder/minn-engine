<?php
/**
 * The registrations the reference makes before any plugin loads, so that
 * removing one from plugin code has the same effect here. The content
 * pipeline (blocks, texturize, autop, shortcodes) is the engine's own and
 * is not listed: the_content fires on the rendered output for plugin
 * callbacks only.
 */

add_filter('sanitize_title', 'sanitize_title_with_dashes', 10, 3);
add_filter('sanitize_user', 'strip_tags');
add_filter('sanitize_user', 'trim');
add_filter('sanitize_user', 'wp_strip_all_tags');
add_filter('pre_kses', 'wp_pre_kses_less_than');
add_filter('the_title', 'wptexturize');
add_filter('the_title', 'convert_chars');
add_filter('the_title', 'trim');
add_filter('the_excerpt', 'wptexturize');
add_filter('the_excerpt', 'convert_smilies');
add_filter('the_excerpt', 'convert_chars');
add_filter('the_excerpt', 'wpautop');
add_action('wp_head', 'wp_enqueue_scripts', 1);
add_action('wp_head', 'wp_print_styles', 8);
add_action('wp_head', '_minn_print_engine_styles', 8);
add_action('wp_head', 'wp_print_head_scripts', 9);
add_action('wp_footer', '_wp_footer_scripts', 20);
add_filter('option_blog_charset', '_wp_specialchars');

// The author name, term description, and link description are always filtered.
add_filter('pre_comment_author_name', 'wp_filter_kses');
add_filter('pre_term_description', 'wp_filter_kses');
add_filter('pre_link_description', 'wp_filter_kses');
// The footnotes meta the editor writes is registered for every post type that supports the editor.
// (The built-in types are known at load; a plugin's type registered at init does not get it, unlike the reference.)
if (Minn\Runtime\Runtime::booted()) {
    foreach (get_post_types(['show_in_rest' => true]) as $type) {
        if (post_type_supports($type, 'editor')) {
            register_post_meta($type, 'footnotes', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'revisions_enabled' => true]);
        }
    }
}

// The kses tables as the globals plugin code reads directly.
if (Minn\Runtime\Runtime::booted()) {
    $GLOBALS['allowedposttags'] = _minn_kses_table()['post'];
    $GLOBALS['allowedtags'] = _minn_kses_table()['data'];
    $GLOBALS['allowedentitynames'] = _minn_kses_table()['entities'];
    $GLOBALS['allowedxmlentitynames'] = ['amp', 'lt', 'gt', 'apos', 'quot'];
}
