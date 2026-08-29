<?php
/**
 * The filters the reference registers before any plugin loads, so that
 * removing one from plugin code has the same effect here.
 */

add_filter('sanitize_title', 'sanitize_title_with_dashes', 10, 3);
add_filter('sanitize_user', 'strip_tags');
add_filter('sanitize_user', 'trim');
add_filter('sanitize_user', 'wp_strip_all_tags');
add_filter('pre_kses', 'wp_pre_kses_less_than');
add_filter('the_title', 'wptexturize');
add_filter('the_title', 'convert_chars');
add_filter('the_title', 'trim');
add_filter('the_content', 'do_blocks', 9);
add_filter('the_content', 'wptexturize');
add_filter('the_content', 'convert_smilies', 20);
add_filter('the_content', 'wpautop');
add_filter('the_content', 'shortcode_unautop');
add_filter('the_content', 'prepend_attachment');
add_filter('the_content', 'wp_replace_insecure_home_url');
add_filter('the_content', 'do_shortcode', 11);
add_filter('the_content', 'wp_filter_content_tags', 12);
add_filter('the_excerpt', 'wptexturize');
add_filter('the_excerpt', 'convert_smilies');
add_filter('the_excerpt', 'convert_chars');
add_filter('the_excerpt', 'wpautop');
add_filter('the_excerpt', 'shortcode_unautop');
add_filter('widget_text_content', 'wptexturize');
add_filter('widget_text_content', 'wpautop');
add_filter('widget_text_content', 'do_shortcode', 11);
add_filter('comment_text', 'wptexturize');
add_filter('comment_text', 'convert_chars');
add_filter('comment_text', 'make_clickable', 9);
add_filter('comment_text', 'wpautop', 30);
add_filter('pre_comment_content', 'wp_kses_data');
add_filter('option_blog_charset', '_wp_specialchars');
