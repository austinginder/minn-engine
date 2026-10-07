<?php
/**
 * Plugin Name: Minn test embed
 * Description: Fixture for the embed-template suite, loaded by the engine and the reference alike. A request that carries X-Minn-Embed naming a run the suite opened (wp-content/minn-embed/<run>.open exists) gets a plugin on every seam of the embed page, as sharing and branding plugins are: a head tag and a style (embed_head, enqueue_embed_scripts), marks on the content, the meta, the footer, the excerpt and the site's name, and the template, image and shape filters heard with what they were handed (appended to <run>.log). With X-Minn-Embed-Mode "square" the plugin asks for square images. Without such a run the header does nothing.
 * License: MIT
 */

$minnEmbedRun = (string) ($_SERVER['HTTP_X_MINN_EMBED'] ?? '');
$minnEmbedDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-embed';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnEmbedRun) !== 1 || !is_file("{$minnEmbedDir}/{$minnEmbedRun}.open")) {
    return;
}
$minnEmbedHeard = static function (string $what) use ($minnEmbedDir, $minnEmbedRun): void {
    file_put_contents("{$minnEmbedDir}/{$minnEmbedRun}.log", str_replace(home_url(), '{site}', $what) . "\n", FILE_APPEND);
};
$minnEmbedMode = (string) ($_SERVER['HTTP_X_MINN_EMBED_MODE'] ?? 'markers');

add_action('embed_head', static function (): void {
    echo '<meta name="zz-embed-head" content="1">' . "\n";
});
add_action('enqueue_embed_scripts', static function () use ($minnEmbedHeard): void {
    $minnEmbedHeard('enqueue_embed_scripts ' . json_encode([is_embed(), is_404()]));
    wp_enqueue_style('zz-embed', 'https://zz-embed.example/zz-embed.css', [], '1');
});
add_action('embed_content', static function (): void {
    echo '<p class="zz-embed-content">Zz content</p>';
});
add_action('embed_content_meta', static function (): void {
    echo '<span class="zz-embed-meta"></span>';
}, 5);
add_action('embed_footer', static function (): void {
    echo '<span class="zz-embed-footer"></span>';
}, 15);
add_filter('the_excerpt_embed', static fn ($excerpt) => $excerpt . '<span class="zz-excerpt"></span>');
add_filter('embed_site_title_html', static fn ($html) => '<span class="zz-site">' . $html . '</span>');
add_filter('embed_template_hierarchy', static function ($templates) use ($minnEmbedHeard) {
    $minnEmbedHeard('embed_template_hierarchy ' . json_encode($templates));
    return $templates;
});
add_filter('template_include', static function ($template) use ($minnEmbedHeard) {
    $minnEmbedHeard('template_include ' . (is_embed() ? basename((string) $template) : '(not an embed)'));
    return $template;
}, 99);
add_filter('embed_thumbnail_id', static function ($id) use ($minnEmbedHeard) {
    $minnEmbedHeard('embed_thumbnail_id ' . ($id ? get_the_title($id) : '0'));
    return $id;
});
add_filter('embed_thumbnail_image_size', static function ($size, $id) use ($minnEmbedHeard) {
    $minnEmbedHeard('embed_thumbnail_image_size ' . json_encode($size) . ' ' . get_the_title($id));
    return $size;
}, 10, 2);
add_filter('embed_thumbnail_image_shape', static function ($shape, $id) use ($minnEmbedHeard, $minnEmbedMode) {
    $minnEmbedHeard('embed_thumbnail_image_shape ' . $shape . ' ' . get_the_title($id));
    return $minnEmbedMode === 'square' ? 'square' : $shape;
}, 10, 2);
