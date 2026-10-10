<?php
/**
 * The site logo, footnotes and legacy widget as pages render them (probe
 * block-renderers-rendered): each block run through do_blocks, so the block
 * supports' wrapper classes and styles apply. The site logo with a logo set
 * (a width, no width, unlinked, aligned, a class, a new tab), footnotes in a
 * post's loop, and a legacy widget by base with encoded settings. The
 * probe's posts and options are removed or put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$saved = ['site_logo' => get_option('site_logo', null)];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $saved, $pings): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($saved as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
// The engine registers its renderers and widgets on init, which its probe runner has not fired (the reference has).
if (!did_action('init')) {
    do_action('init');
}
$render = static fn (string $markup) => $mask(trim(do_blocks($markup)));

$upload = wp_get_upload_dir();
$image = wp_insert_attachment(['post_title' => 'zz brr logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], $upload['basedir'] . '/2026/10/zz-brr-logo.png');
if (!is_int($image) || $image < 2) {
    fwrite(STDERR, "block-renderers-rendered: the logo could not be made\n");
    exit(1);
}
$made[] = $image;
$ids[$image] = 'logo';
wp_update_attachment_metadata($image, ['width' => 400, 'height' => 200, 'file' => '2026/10/zz-brr-logo.png', 'sizes' => []]);
update_option('site_logo', $image);
$say('the site logo', array_map($render, [
    '<!-- wp:site-logo {"width":120} /-->',
    '<!-- wp:site-logo /-->',
    '<!-- wp:site-logo {"width":120,"isLink":false} /-->',
    '<!-- wp:site-logo {"width":120,"align":"center","className":"zz-logo"} /-->',
    '<!-- wp:site-logo {"width":120,"linkTarget":"_blank"} /-->',
    '<!-- wp:site-logo {"width":120,"style":{"border":{"radius":"8px"}}} /-->',
]));

$noted = wp_insert_post(['post_title' => 'zz brr notes', 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>Cited.</p><!-- /wp:paragraph -->']);
if (!is_int($noted) || $noted < 2) {
    fwrite(STDERR, "block-renderers-rendered: a post could not be made\n");
    exit(1);
}
$made[] = $noted;
$ids[$noted] = 'notes';
update_post_meta($noted, 'footnotes', wp_slash(wp_json_encode([['id' => 'zz-r-one', 'content' => 'First note.'], ['id' => 'zz-r-two', 'content' => 'Second <em>note</em>.']])));
global $wp_query, $wp_the_query, $post;
$wp_the_query = $wp_query = new WP_Query(['p' => $noted]);
$wp_query->the_post();
$say('footnotes in a post', array_map($render, [
    '<!-- wp:footnotes /-->',
    '<!-- wp:footnotes {"className":"zz-notes","style":{"typography":{"fontSize":"12px"}}} /-->',
]));
wp_reset_postdata();

if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$serialized = serialize(['title' => 'ZZ Rendered title']);
$encoded = wp_json_encode(['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)]);
$say('the legacy widget', array_map($render, [
    '<!-- wp:legacy-widget {"idBase":"search","instance":[]} /-->',
    '<!-- wp:legacy-widget {"idBase":"search","instance":' . $encoded . '} /-->',
    '<!-- wp:legacy-widget {"idBase":"zz-unknown"} /-->',
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
