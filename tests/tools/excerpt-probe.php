<?php
/**
 * How an excerpt is made from a post's blocks and what plugins are asked:
 * excerpt_remove_blocks over containers, lists, images, classic and HTML
 * blocks, with the allowed lists as they come and as a plugin changes them;
 * get_the_excerpt for a post with blocks, one with a more tag, one with a
 * hand-written excerpt, and one a plugin shortens (excerpt_length,
 * excerpt_more); the hooks each asks, in order. Same protocol as
 * api-probe.php; the posts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$blocks = implode("\n\n", [
    '<!-- wp:paragraph --><p>First <strong>para</strong>graph.</p><!-- /wp:paragraph -->',
    '<!-- wp:image --><figure class="wp-block-image"><img src="x.jpg" alt="Alt"/><figcaption>Cap</figcaption></figure><!-- /wp:image -->',
    '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>In a group.</p><!-- /wp:paragraph --><!-- wp:image --><figure class="wp-block-image"><img src="y.jpg"/></figure><!-- /wp:image --><!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>Nested heading</h2><!-- /wp:heading --></div><!-- /wp:group --></div><!-- /wp:group -->',
    '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Left.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:code --><pre class="wp-block-code"><code>code()</code></pre><!-- /wp:code --></div><!-- /wp:column --></div><!-- /wp:columns -->',
    '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Item one</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
    '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quoted.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
    '<!-- wp:html --><div>Raw html</div><!-- /wp:html -->',
    '<!-- wp:media-text --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Beside media.</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->',
    '<p>Classic text.</p>',
    '<!-- wp:details --><details class="wp-block-details"><summary>Sum</summary><!-- wp:paragraph --><p>Hidden.</p><!-- /wp:paragraph --></details><!-- /wp:details -->',
]);
$say('excerpt_remove_blocks', excerpt_remove_blocks($blocks));
$wrappers = static fn (array $list): array => array_values(array_diff($list, ['core/group']));
add_filter('excerpt_allowed_wrapper_blocks', $wrappers);
$say('without group as a wrapper', excerpt_remove_blocks($blocks));
remove_filter('excerpt_allowed_wrapper_blocks', $wrappers);
$allowed = static fn (array $list): array => [...$list, 'core/code', 'core/details'];
add_filter('excerpt_allowed_blocks', $allowed);
$say('with code and details allowed', excerpt_remove_blocks($blocks));
remove_filter('excerpt_allowed_blocks', $allowed);

$made = [];
$excerpt = static function (string $label, array $postarr, array $filters = []) use (&$made, $say): void {
    $id = (int) wp_insert_post($postarr + ['post_status' => 'publish', 'post_title' => $label]);
    $made[] = $id;
    foreach ($filters as $hook => $callback) {
        add_filter($hook, $callback);
    }
    $seen = [];
    $recorder = static function (string $hook) use (&$seen): void {
        if (in_array($hook, ['get_the_excerpt', 'content_pagination', 'excerpt_allowed_wrapper_blocks', 'excerpt_allowed_blocks', 'the_content', 'excerpt_length', 'excerpt_more', 'wp_trim_words', 'wp_trim_excerpt', 'the_excerpt'], true)) {
            $seen[] = $hook;
        }
    };
    $post = get_post($id);
    $GLOBALS['post'] = $post;
    setup_postdata($post);
    add_action('all', $recorder);
    $text = get_the_excerpt($post);
    $rendered = apply_filters('the_excerpt', $text);
    remove_action('all', $recorder);
    foreach ($filters as $hook => $callback) {
        remove_filter($hook, $callback);
    }
    $say($label, ['get_the_excerpt' => $text, 'the_excerpt' => $rendered, 'hooks' => $seen]);
};
$excerpt('zz excerpt blocks', ['post_content' => $blocks]);
$excerpt('zz excerpt more', ['post_content' => "<!-- wp:paragraph --><p>Before the more.</p><!-- /wp:paragraph -->\n\n<!-- wp:more --><!--more--><!-- /wp:more -->\n\n<!-- wp:paragraph --><p>After the more.</p><!-- /wp:paragraph -->"]);
$excerpt('zz excerpt written', ['post_content' => '<p>Body</p>', 'post_excerpt' => 'A hand-written excerpt & "quotes".']);
$excerpt('zz excerpt shortened', ['post_content' => '<p>' . implode(' ', array_map(static fn ($n) => "word{$n}", range(1, 30))) . '</p>'], ['excerpt_length' => static fn () => 5, 'excerpt_more' => static fn () => ' (more)']);
$excerpt('zz excerpt shortcode', ['post_content' => '<p>Text [gallery ids="1"] after.</p>']);
foreach ($made as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
