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
add_filter('pre_kses', 'wp_pre_kses_block_attributes', 10, 3);
add_action('init', '_minn_register_core_rss_block');
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
add_filter('the_title', 'capital_P_dangit', 11);
add_filter('the_excerpt', 'wptexturize');
add_filter('the_excerpt', 'convert_smilies');
add_filter('the_excerpt', 'convert_chars');
add_filter('the_excerpt', 'wpautop');
add_filter('the_excerpt', 'shortcode_unautop');
add_filter('the_excerpt', 'wp_replace_insecure_home_url');
add_filter('the_excerpt', 'wp_filter_content_tags', 12);
// A term's fields on the way in and on display, each chain in the reference's order (probe term-sanitize).
add_filter('pre_term_name', 'sanitize_text_field');
add_filter('pre_term_name', 'wp_filter_kses');
add_filter('pre_term_name', '_wp_specialchars', 30);
add_filter('pre_term_slug', 'sanitize_title');
add_filter('wp_update_term_parent', 'wp_check_term_hierarchy_for_loops', 10, 3);
add_filter('term_name', 'wptexturize');
add_filter('term_name', 'convert_chars');
add_filter('term_name', 'esc_html');
add_filter('term_name', '_wp_specialchars', 30);
add_filter('term_name_rss', 'convert_chars');
add_filter('term_description', 'wptexturize');
add_filter('term_description', 'convert_chars');
add_filter('term_description', 'wpautop');
add_filter('term_description', 'shortcode_unautop');
add_filter('the_post_thumbnail_caption', 'wptexturize');
add_filter('the_post_thumbnail_caption', 'convert_smilies');
add_filter('the_post_thumbnail_caption', 'convert_chars');
add_filter('the_content_feed', 'wp_staticize_emoji');
add_filter('the_content_feed', '_oembed_filter_feed_content');
add_filter('the_excerpt_rss', 'ent2ncr', 8);
add_filter('the_excerpt_rss', 'convert_chars');
add_action('wp_head', 'wp_enqueue_scripts', 1);
add_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles', 10);
// A block widget's content runs the block and shortcode pipelines; fitting an
// image out twice changes nothing (wp_filter_content_tags leaves a fitted one be).
add_filter('widget_block_content', 'do_blocks', 9);
add_filter('widget_text', 'balanceTags');
// The shortcodes the reference registers itself.
add_shortcode('wp_caption', 'img_caption_shortcode');
add_shortcode('caption', 'img_caption_shortcode');
add_shortcode('gallery', 'gallery_shortcode');
add_shortcode('audio', 'wp_audio_shortcode');
add_shortcode('video', 'wp_video_shortcode');
$GLOBALS['wp_embed'] ??= new WP_Embed();
add_filter('widget_text_content', [$GLOBALS['wp_embed'], 'run_shortcode'], 8);
add_filter('widget_text_content', [$GLOBALS['wp_embed'], 'autoembed'], 8);
add_filter('widget_text_content', 'wptexturize');
add_filter('widget_text_content', 'wpautop');
add_filter('widget_text_content', 'shortcode_unautop');
add_filter('widget_text_content', 'wp_replace_insecure_home_url');
add_filter('widget_text_content', 'capital_P_dangit', 11);
add_filter('widget_text_content', 'do_shortcode', 11);
add_filter('widget_text_content', 'wp_filter_content_tags', 12);
add_filter('widget_text_content', 'convert_smilies', 20);
add_filter('widget_block_content', [$GLOBALS['wp_embed'], 'run_shortcode'], 8);
add_filter('widget_block_content', [$GLOBALS['wp_embed'], 'autoembed'], 8);
add_filter('widget_block_content', 'do_shortcode', 11);
add_filter('widget_block_content', 'wp_filter_content_tags', 12);
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

// The term description and link description are always filtered (a comment author's name below, in its chain).
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
add_filter('authenticate', 'wp_authenticate_spam_check', 99);
// A comment's fields on the way in, each chain in the reference's order (probe comment-fields); kses on the content comes from kses_init.
add_filter('pre_comment_author_name', 'sanitize_text_field');
add_filter('pre_comment_author_name', 'wp_filter_kses');
add_filter('pre_comment_author_name', '_wp_specialchars', 30);
add_filter('pre_comment_author_url', 'wp_strip_all_tags');
add_filter('pre_comment_author_url', 'sanitize_url');
add_filter('pre_comment_author_url', 'wp_filter_kses');
add_filter('pre_comment_author_email', 'trim');
add_filter('pre_comment_author_email', 'sanitize_email');
add_filter('pre_comment_author_email', 'wp_filter_kses');
add_filter('pre_comment_content', 'convert_invalid_entities');
add_filter('pre_comment_content', '_wp_kses_sanitize_note_mention_classes', 11);
add_filter('pre_comment_content', 'wp_rel_ugc', 15);
add_filter('pre_comment_content', 'balanceTags', 50);
// The comment form: the legacy flood hook attaches the database check, a new comment notifies, the commenter is remembered.
add_action('check_comment_flood', 'check_comment_flood_db', 10, 4);
add_filter('comment_flood_filter', 'wp_throttle_comment_flood', 10, 3);
add_action('comment_post', 'wp_new_comment_notify_moderator');
add_action('comment_post', 'wp_new_comment_notify_postauthor');
add_action('set_comment_cookies', 'wp_set_comment_cookies', 10, 3);
add_action('comment_form', 'wp_comment_form_unfiltered_html_nonce');
// A post's columns on the way in, as the reference registers them (probe post-insert-filters); kses and the custom-CSS strip come from kses_init.
add_filter('content_save_pre', 'convert_invalid_entities');
add_filter('content_save_pre', 'balanceTags', 50);
add_filter('title_save_pre', 'trim');
add_filter('excerpt_save_pre', 'convert_invalid_entities');
add_filter('excerpt_save_pre', 'balanceTags', 50);
add_filter('pre_post_status', 'sanitize_key');
add_filter('pre_post_guid', 'wp_strip_all_tags');
add_filter('pre_post_guid', 'sanitize_url');
add_filter('pre_post_guid', 'wp_filter_kses');
add_filter('pre_post_mime_type', 'sanitize_mime_type');
add_filter('wp_insert_post_data', '_wp_customize_changeset_filter_insert_post_data', 10, 2);
add_filter('wp_insert_post_parent', 'wp_check_post_hierarchy_for_loops', 10, 2);
add_filter('pre_wp_unique_post_slug', 'wp_filter_wp_template_unique_post_slug', 10, 5);
// The capabilities no role lists, granted from the ones that imply them (probe: the reference's user_has_cap defaults).
add_filter('user_has_cap', 'wp_maybe_grant_install_languages_cap', 1);
add_filter('user_has_cap', 'wp_maybe_grant_resume_extensions_caps', 1);
add_filter('user_has_cap', 'wp_maybe_grant_site_health_caps', 1, 4);
// The two big image sizes, registered before any plugin's.
add_action('plugins_loaded', '_wp_add_additional_image_sizes', 0);
// What a user may post unfiltered is settled once WordPress is up and again whenever the user changes.
add_action('init', 'kses_init');
add_action('set_current_user', 'kses_init');

// Comment text on display.
add_filter('comment_text', 'wptexturize');
add_filter('comment_text', 'convert_chars');
add_filter('comment_text', 'make_clickable', 9);
add_filter('comment_text', 'force_balance_tags', 25);
add_filter('comment_text', 'convert_smilies', 20);
add_filter('comment_text', 'wpautop', 30);
add_filter('comment_text', 'capital_P_dangit', 31);

// the_content, as the reference fills it: block hooks and embeds first, then
// the blocks, the typography, the paragraphs, the shortcodes, the images,
// the smilies. The engine's own rendering runs only what it has not already
// done (Runtime::contentFilter); a plugin running the filter gets them all.
$GLOBALS['wp_embed'] ??= new WP_Embed();
add_filter('the_content', 'apply_block_hooks_to_content_from_post_object', 8);
add_filter('the_content', [$GLOBALS['wp_embed'], 'run_shortcode'], 8);
add_filter('the_content', [$GLOBALS['wp_embed'], 'autoembed'], 8);
add_filter('the_content', 'do_blocks', 9);
add_filter('the_content', 'wptexturize');
add_filter('the_content', 'wpautop');
add_filter('the_content', 'shortcode_unautop');
add_filter('the_content', 'prepend_attachment');
add_filter('the_content', 'wp_replace_insecure_home_url');
add_filter('the_content', 'capital_P_dangit', 11);
add_filter('the_content', 'do_shortcode', 11);
add_filter('the_content', 'wp_filter_content_tags', 12);
add_filter('the_content', 'convert_smilies', 20);

// Comments close on old posts when Discussion says so; the connectors registry fills at init 15.
add_filter('comments_open', '_close_comments_for_old_post', 10, 2);
// Widgets register at init 1, as the reference schedules them, so a theme's
// sidebar has its widgets by the time it renders.
add_action('init', 'wp_widgets_init', 1);
add_action('init', '_wp_connectors_init', 15);
add_action('init', '_wp_register_default_connector_settings', 20);
add_action('init', '_wp_connectors_pass_default_keys_to_ai_client', 20);

// The REST API's own filters go on when the server starts, after the plugins' (see rest_api_default_filters).
add_action('rest_api_init', 'rest_api_default_filters', 10, 1);
// Revisions and the old-address records hang off the update hooks, as on the reference: a plugin that
// unhooks wp_save_post_revision from post_updated turns revisions off.
add_action('post_updated', 'wp_save_post_revision', 10, 1);
add_action('post_updated', 'wp_check_for_changed_slugs', 12, 3);
add_action('post_updated', 'wp_check_for_changed_dates', 12, 3);
add_action('wp_after_insert_post', 'wp_save_post_revision_on_insert', 9, 3);
// What the reference says on an admin request (admin-ajax.php is the only
// one the engine answers), and the heartbeat's sign-in report.
add_action('admin_init', 'wp_admin_headers');
add_action('admin_init', 'send_frame_options_header', 10, 0);
add_filter('heartbeat_send', 'wp_auth_check');
add_filter('heartbeat_nopriv_send', 'wp_auth_check');
// A post's life, as the reference's defaults hear it, in its order.
add_action('transition_post_status', '_transition_post_status', 5, 3);
add_action('transition_post_status', '_update_term_count_on_transition_post_status', 10, 3);
add_action('transition_post_status', '_wp_auto_add_pages_to_menu', 10, 3);
add_action('transition_post_status', '__clear_multi_author_cache');
add_action('transition_post_status', 'block_core_calendar_update_has_published_post_on_transition_post_status', 10, 3);
add_action('publish_post', '_delete_option_fresh_site', 0);
add_action('publish_page', '_delete_option_fresh_site', 0);
add_action('future_post', '_future_post_hook', 5, 2);
add_action('future_page', '_future_post_hook', 5, 2);
add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
add_action('save_post', 'delete_get_calendar_cache');
add_action('wp_trash_post', '_reset_front_page_settings_for_post');
add_action('before_delete_post', '_reset_front_page_settings_for_post');
add_action('before_delete_post', '_reset_privacy_policy_page_for_post');
add_action('delete_post', '_wp_delete_post_menu_item');
add_action('delete_post', 'delete_get_calendar_cache');
add_action('delete_post', 'block_core_calendar_update_has_published_post_on_delete');
add_action('delete_attachment', '_delete_attachment_theme_mod');
add_action('transition_comment_status', '_clear_modified_cache_on_transition_comment_status', 10, 2);
add_action('profile_update', 'default_password_nag_edit_user', 10, 2);
add_action('user_register', 'wp_maybe_update_user_counts', 10, 0);
add_action('deleted_user', 'wp_maybe_update_user_counts', 10, 0);
add_action('set_user_role', 'wp_cache_set_users_last_changed');
