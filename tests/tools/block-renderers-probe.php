<?php
/**
 * Core block renderers the engine owed (probe block-renderers): the site
 * logo (none; an image scaled to a width; linked or not, in a new tab; the
 * site name as its text alternative), footnotes for a post (its notes, none,
 * a post without the meta, no post at all), and the legacy widget (a widget
 * by base, raw settings, encoded settings signed and not, a registered
 * widget by id, an unknown base, nothing). Each callback is called by name outside a render, so no
 * block-supports classes apply. The probe's posts and options are removed or
 * put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$saved = ['site_logo' => get_option('site_logo', null), 'widget_search' => get_option('widget_search', null), 'sidebars_widgets' => get_option('sidebars_widgets', null)];
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
$try = static function (Closure $run) use ($mask) {
    try {
        return $mask($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$block = static fn (string $name, array $attrs = [], array $context = []) => new WP_Block(['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], $context);
if (!did_action('widgets_init')) {
    wp_widgets_init();
}

update_option('site_logo', 0);
$logo = ['none' => $try(static fn () => render_block_core_site_logo(['width' => 120, 'isLink' => true, 'linkTarget' => '_self']))];
$upload = wp_get_upload_dir();
$image = wp_insert_attachment(['post_title' => 'zz br logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], $upload['basedir'] . '/2026/10/zz-br-logo.png');
if (!is_int($image) || $image < 2) {
    fwrite(STDERR, "block-renderers: the logo could not be made\n");
    exit(1);
}
$made[] = $image;
$ids[$image] = 'logo';
wp_update_attachment_metadata($image, ['width' => 400, 'height' => 200, 'file' => '2026/10/zz-br-logo.png', 'sizes' => ['medium' => ['file' => 'zz-br-logo-300x150.png', 'width' => 300, 'height' => 150, 'mime-type' => 'image/png']]]);
update_option('site_logo', $image);
foreach ([
    'linked, a width' => ['width' => 120, 'isLink' => true, 'linkTarget' => '_self'],
    'not linked' => ['width' => 120, 'isLink' => false, 'linkTarget' => '_self'],
    'a new tab' => ['width' => 120, 'isLink' => true, 'linkTarget' => '_blank'],
    'no width' => ['isLink' => true, 'linkTarget' => '_self'],
    'a width past the image' => ['width' => 800, 'isLink' => true, 'linkTarget' => '_self'],
    'a class and an alignment' => ['width' => 120, 'isLink' => true, 'linkTarget' => '_self', 'className' => 'zz-logo', 'align' => 'center'],
] as $label => $attrs) {
    $logo[$label] = $try(static fn () => render_block_core_site_logo($attrs));
}
update_post_meta($image, '_wp_attachment_image_alt', 'ZZ alt text');
$logo['an image with its own alt'] = $try(static fn () => render_block_core_site_logo(['width' => 120, 'isLink' => true, 'linkTarget' => '_self']));
$say('the site logo', $logo);

$noted = wp_insert_post(['post_title' => 'zz br notes', 'post_status' => 'publish', 'post_content' => 'x']);
$plain = wp_insert_post(['post_title' => 'zz br plain', 'post_status' => 'publish', 'post_content' => 'x']);
$locked = wp_insert_post(['post_title' => 'zz br locked', 'post_status' => 'publish', 'post_content' => 'x', 'post_password' => 'zz-secret']);
foreach ([$noted, $plain, $locked] as $id) {
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "block-renderers: a post could not be made\n");
        exit(1);
    }
    $made[] = $id;
}
$notes = wp_slash(wp_json_encode([['id' => 'zz-note-one', 'content' => 'A <em>note</em>.'], ['id' => 'zz-note-two', 'content' => 'Another &amp; more.']]));
update_post_meta($noted, 'footnotes', $notes);
update_post_meta($locked, 'footnotes', $notes);
update_post_meta($plain, 'footnotes', '');
$say('footnotes', [
    $try(static fn () => render_block_core_footnotes([], '', $block('core/footnotes', [], ['postId' => $noted, 'postType' => 'post']))),
    $try(static fn () => render_block_core_footnotes([], '', $block('core/footnotes', [], ['postId' => $plain, 'postType' => 'post']))),
    $try(static fn () => render_block_core_footnotes([], '', $block('core/footnotes', [], ['postId' => $locked, 'postType' => 'post']))),
    $try(static fn () => render_block_core_footnotes([], '', $block('core/footnotes', [], ['postId' => 999999, 'postType' => 'post']))),
    $try(static fn () => render_block_core_footnotes([], '', $block('core/footnotes', [], []))),
]);

update_option('widget_search', [4 => ['title' => 'ZZ Find'], '_multiwidget' => 1]);
$say('the legacy widget', [
    $try(static fn () => render_block_core_legacy_widget(['idBase' => 'search', 'instance' => []])),
    $try(static fn () => render_block_core_legacy_widget(['idBase' => 'search', 'instance' => ['raw' => ['title' => 'ZZ Raw title']]])),
    $try(static fn () => render_block_core_legacy_widget(['idBase' => 'text', 'instance' => ['raw' => ['title' => 'ZZ Text', 'text' => 'Some <b>text</b>.', 'filter' => true, 'visual' => true]]])),
    (static function () use ($try) {
        // An instance as the widget-types encode route hands it out: the serialized settings in base64, signed with wp_hash.
        $serialized = serialize(['title' => 'ZZ Encoded title']);
        return [
            $try(static fn () => render_block_core_legacy_widget(['idBase' => 'search', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)]])),
            $try(static fn () => render_block_core_legacy_widget(['idBase' => 'search', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => 'zz-wrong']])),
        ];
    })(),
    $try(static fn () => render_block_core_legacy_widget(['id' => 'search-4'])),
    $try(static fn () => render_block_core_legacy_widget(['id' => 'search-99'])),
    $try(static fn () => render_block_core_legacy_widget(['idBase' => 'zz-unknown'])),
    $try(static fn () => render_block_core_legacy_widget([])),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
