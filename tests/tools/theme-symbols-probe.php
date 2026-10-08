<?php
/**
 * The small symbols the popular themes call (probe theme-symbols): what
 * get_media_embedded_in_content finds (and filtered to some types),
 * get_user_count, what wp_maybe_enqueue_oembed_host_js answers and
 * enqueues, the deprecated wp_img_tag_add_loading_attr, get_the_author_posts
 * for a post, and WP_Block_Parser's parse beside parse_blocks. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$content = "<p>Intro</p>\n<video controls src=\"a.mp4\"><source src=\"a.webm\"></video>\n<audio src=\"b.mp3\" />\n<iframe src=\"https://example.com/e\" width=\"10\"></iframe>\n<embed src=\"c.swf\" />\n<object data=\"d\"><param name=\"x\"></object>\n<img src=\"e.jpg\">\n<VIDEO>upper</VIDEO>\n<div><iframe src=\"f\"></iframe></div>";
$say('media in content', get_media_embedded_in_content($content));
$say('media in content, video and iframe only', get_media_embedded_in_content($content, ['video', 'iframe']));
$say('media in content, a type that is not allowed', get_media_embedded_in_content($content, ['img', 'video']));
$narrow = static fn (array $types): array => ['audio'];
add_filter('media_embedded_in_content_allowed_types', $narrow);
$say('media in content, the filter narrowing the types', get_media_embedded_in_content($content));
remove_filter('media_embedded_in_content_allowed_types', $narrow);
$say('media in none', get_media_embedded_in_content(''));

$say('get_user_count', get_user_count() === (int) get_option('user_count', -1) ? 'the user_count option' : get_user_count());

$queued = static fn (): bool => wp_script_is('wp-embed', 'enqueued');
$say('oEmbed host script: plain html', [wp_maybe_enqueue_oembed_host_js('<p>Hello</p>'), $queued()]);
$say('oEmbed host script: an embed card', [wp_maybe_enqueue_oembed_host_js('<blockquote class="wp-embedded-content"><a href="x">x</a></blockquote>'), $queued()]);
$say('oEmbed host script: hooked to wp_head', has_action('wp_head', 'wp_oembed_add_host_js'));
wp_dequeue_script('wp-embed');

$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
$say('wp_img_tag_add_loading_attr', [wp_img_tag_add_loading_attr('<img src="a.jpg" width="100" height="100">', 'the_content'), wp_img_tag_add_loading_attr('<img src="a.jpg" loading="eager">', 'the_content'), wp_img_tag_add_loading_attr('<img src="a.jpg">', 'zz_context'), $deprecated]);

$contexts = [];
foreach (['the_content', 'the_post_thumbnail', 'wp_get_attachment_image', 'widget_text_content', 'widget_block_content', 'template_part_header', 'template_part_footer', 'get_avatar', 'zz_context', 'do_shortcode', 'the_excerpt', 'template', 'comment_text'] as $context) {
    $contexts[$context] = wp_get_loading_optimization_attributes('img', ['width' => 10, 'height' => 10], $context);
}
$say('loading attributes by context, outside the loop', $contexts);

$author = (int) get_users(['number' => 1, 'orderby' => 'ID', 'fields' => 'ID'])[0];
$post = get_posts(['author' => $author, 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1])[0] ?? null;
$GLOBALS['post'] = $post;
$say('get_the_author_posts', $post ? (get_the_author_posts() === (int) count_user_posts($author, 'post') ? 'count_user_posts of the post\'s author' : get_the_author_posts()) : 'no post');
$GLOBALS['post'] = null;
$say('get_the_author_posts, no post', get_the_author_posts());

$doc = "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\">One</p>\n<!-- /wp:paragraph -->\n\nfree text\n<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:separator /--></div><!-- /wp:group -->";
$parser = new WP_Block_Parser();
$parsed = $parser->parse($doc);
$say('WP_Block_Parser parse', $parsed);
$say('WP_Block_Parser parse is parse_blocks', $parsed === parse_blocks($doc));
$say('WP_Block_Parser after parse', ['document' => $parser->document === $doc, 'offset' => $parser->offset, 'output' => count((array) $parser->output), 'stack' => count((array) $parser->stack)]);
$say('the block parser class', apply_filters('block_parser_class', 'WP_Block_Parser'));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
