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
// User fields, as the reference sanitises them on the way in (pre_user_*)
// and out (user_*); sanitize_user_field() runs these by context.
foreach (['display_name', 'first_name', 'last_name', 'nickname'] as $minnUserField) {
    add_filter("pre_user_{$minnUserField}", 'sanitize_text_field');
    add_filter("pre_user_{$minnUserField}", 'wp_filter_kses');
    add_filter("pre_user_{$minnUserField}", '_wp_specialchars', 30);
    add_filter("user_{$minnUserField}", '_wp_specialchars', 30);
}
unset($minnUserField);
add_filter('pre_user_description', 'wp_filter_kses');
add_filter('pre_user_email', 'trim');
add_filter('pre_user_email', 'sanitize_email');
add_filter('pre_user_email', 'wp_filter_kses');
add_filter('user_email', 'sanitize_email');
add_filter('pre_user_url', 'wp_strip_all_tags');
add_filter('pre_user_url', 'sanitize_url');
add_filter('pre_user_url', 'wp_filter_kses');
add_filter('user_url', 'esc_url');
add_filter('the_title', 'wptexturize');
add_filter('the_title', 'convert_chars');
add_filter('the_title', 'trim');
add_filter('the_excerpt', 'wptexturize');
add_filter('the_excerpt', 'convert_smilies');
add_filter('the_excerpt', 'convert_chars');
add_filter('the_excerpt', 'wpautop');
add_action('wp_head', 'wp_enqueue_scripts', 1);
add_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles', 10);
// A block widget's content runs the block and shortcode pipelines. The
// reference adds wp_filter_content_tags here to fit out images; the engine
// renders them through Blocks\ImageTags on the way out instead, so adding
// it would fit the same images twice.
add_filter('widget_block_content', 'do_blocks', 9);
// The text widget's content pipeline. wp_filter_content_tags stays out for the
// reason given above; wp_replace_insecure_home_url the engine does not have.
add_filter('widget_text', 'balanceTags');
// The shortcodes the reference registers itself.
add_shortcode('wp_caption', 'img_caption_shortcode');
add_shortcode('caption', 'img_caption_shortcode');
add_shortcode('gallery', 'gallery_shortcode');
add_shortcode('audio', 'wp_audio_shortcode');
add_shortcode('video', 'wp_video_shortcode');
add_filter('widget_text_content', static fn ($content) => $GLOBALS['wp_embed']->run_shortcode($content), 8);
add_filter('widget_text_content', static fn ($content) => $GLOBALS['wp_embed']->autoembed($content), 8);
add_filter('widget_text_content', 'wptexturize');
add_filter('widget_text_content', 'wpautop');
add_filter('widget_text_content', 'shortcode_unautop');
add_filter('widget_text_content', 'capital_P_dangit', 11);
add_filter('widget_text_content', 'do_shortcode', 11);
add_filter('widget_text_content', 'convert_smilies', 20);
add_filter('widget_block_content', 'do_shortcode', 11);
// A classic theme gets the reference's wp_head defaults, registered before its own hooks.
add_action('setup_theme', '_minn_classic_head_defaults', 1);
add_action('wp_head', 'wp_maybe_inline_styles', 1);
add_action('wp_head', 'wp_print_styles', 8);
add_action('wp_head', '_minn_print_engine_styles', 8);
add_action('wp_head', 'wp_print_head_scripts', 9);
add_action('wp_footer', 'wp_print_speculation_rules', 10);
add_action('wp_footer', 'wp_print_footer_scripts', 20);
add_action('wp_print_footer_scripts', '_wp_footer_scripts', 10);
add_filter('option_blog_charset', '_wp_specialchars');
// The robots meta directives, in the reference's registration order (it decides the directive order in the tag).
add_filter('wp_robots', 'wp_robots_noindex');
add_filter('wp_robots', 'wp_robots_noindex_embeds');
add_filter('wp_robots', 'wp_robots_noindex_search');
add_filter('wp_robots', 'wp_robots_max_image_preview_large');

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

// The authenticate chain and the comment field filters.
add_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
add_filter('authenticate', 'wp_authenticate_email_password', 20, 3);
add_filter('pre_comment_author_name', 'sanitize_text_field');
add_filter('pre_comment_author_name', '_wp_specialchars', 30);
add_filter('pre_comment_author_email', 'trim');
add_filter('pre_comment_author_email', 'sanitize_email');
add_filter('pre_comment_author_url', 'strip_tags');
add_filter('pre_comment_author_url', 'trim');
add_filter('pre_comment_author_url', 'wp_filter_kses');
add_filter('pre_comment_author_url', 'esc_url_raw');

// Comment text on display.
add_filter('comment_text', 'wptexturize');
add_filter('comment_text', 'convert_chars');
add_filter('comment_text', 'make_clickable', 9);
add_filter('comment_text', 'force_balance_tags', 25);
add_filter('comment_text', 'convert_smilies', 20);
add_filter('comment_text', 'wpautop', 30);

// Embeds run before the paragraphs do, as the reference orders them.
$GLOBALS['wp_embed'] ??= new WP_Embed();
add_filter('the_content', [$GLOBALS['wp_embed'], 'run_shortcode'], 8);
add_filter('the_content', [$GLOBALS['wp_embed'], 'autoembed'], 8);

// Comments close on old posts when Discussion says so; the connectors registry fills at init 15.
add_filter('comments_open', '_close_comments_for_old_post', 10, 2);
// Widgets register at init 1, as the reference schedules them, so a theme's
// sidebar has its widgets by the time it renders.
add_action('init', 'wp_widgets_init', 1);
add_action('init', '_wp_connectors_init', 15);
add_action('init', '_wp_register_default_connector_settings', 20);
add_action('init', '_wp_connectors_pass_default_keys_to_ai_client', 20);
