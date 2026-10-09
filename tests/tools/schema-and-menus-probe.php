<?php
/**
 * theme.json migrated to the current schema, and a classic menu turned into
 * navigation blocks (probe schema-and-menus): the v1 settings renamed, v2's
 * presets keeping the theme's sizes in charge, a missing or other version,
 * and a menu's published items as links and submenus with their attributes.
 * The probe's menus, post and term are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['menus' => [], 'posts' => [], 'terms' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
    }
});

$cases = [
    'v1' => ['version' => 1, 'settings' => ['color' => ['custom' => false], 'border' => ['customRadius' => true, 'customColor' => true], 'spacing' => ['customPadding' => true, 'customMargin' => true], 'typography' => ['customFontSize' => true, 'customLineHeight' => true, 'dropCap' => false]], 'styles' => ['color' => ['background' => 'red']]],
    'v1 blocks' => ['version' => 1, 'settings' => ['blocks' => ['core/group' => ['spacing' => ['customPadding' => false]], 'core/quote' => ['border' => ['customRadius' => false]]], 'typography' => ['customLineHeight' => false]], 'styles' => ['blocks' => ['core/paragraph' => ['color' => ['text' => 'blue']]]]],
    'v1 target already set' => ['version' => 1, 'settings' => ['typography' => ['customLineHeight' => true, 'lineHeight' => false]]],
    'v1 as text' => ['version' => '1', 'settings' => ['typography' => ['customLineHeight' => true]]],
    'v1 with v2 presets' => ['version' => 1, 'settings' => ['typography' => ['fontSizes' => [['slug' => 's', 'size' => '1px']]]]],
    'v2 font sizes' => ['version' => 2, 'settings' => ['appearanceTools' => true, 'typography' => ['fontSizes' => [['slug' => 's', 'size' => '12px', 'name' => 'S']]]], 'styles' => []],
    'v2 spacing sizes' => ['version' => 2, 'settings' => ['spacing' => ['spacingSizes' => [['slug' => '1', 'size' => '1rem']]]]],
    'v2 spacing scale' => ['version' => 2, 'settings' => ['spacing' => ['spacingScale' => ['steps' => 5]]]],
    'v2 defaults said' => ['version' => 2, 'settings' => ['typography' => ['fontSizes' => [], 'defaultFontSizes' => true], 'spacing' => ['spacingSizes' => [], 'defaultSpacingSizes' => true]]],
    'v2 block sizes' => ['version' => 2, 'settings' => ['blocks' => ['core/paragraph' => ['typography' => ['fontSizes' => [['slug' => 'x', 'size' => '1px']]]]]]],
    'v2 nothing' => ['version' => 2, 'settings' => ['spacing' => ['padding' => true]]],
    'v3' => ['version' => 3, 'settings' => ['typography' => ['customLineHeight' => true, 'fontSizes' => []]]],
    'no version' => ['settings' => ['color' => ['custom' => true]], 'styles' => ['color' => ['text' => 'red']]],
    'version 0' => ['version' => 0, 'settings' => ['typography' => ['customLineHeight' => true]]],
    'version 4' => ['version' => 4, 'settings' => ['typography' => ['customLineHeight' => true]]],
    'empty' => [],
];
$say('theme.json migrated', array_map(static fn (array $json) => [WP_Theme_JSON_Schema::migrate($json), WP_Theme_JSON_Schema::migrate($json, 'custom'), WP_Theme_JSON_Schema::migrate($json, 'blocks')], $cases));

$host = (string) parse_url(home_url(), PHP_URL_HOST);
$say('a classic menu as blocks', (static function () use (&$made, $host) {
    $made['menus'][] = $menu = (int) wp_create_nav_menu('Zz Convert Probe');
    $made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'Zz Linked', 'post_name' => 'zz-linked-probe', 'post_status' => 'publish', 'post_content' => 'x']);
    $term = wp_insert_term('Zz Conv Cat', 'category', ['slug' => 'zz-conv-cat-probe']);
    $made['terms'][] = $category = (int) ($term['term_id'] ?? 0);
    $a = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Custom <b>A</b>', 'menu-item-url' => 'https://zz.example/a?x=1&y=2', 'menu-item-status' => 'publish', 'menu-item-target' => '_blank', 'menu-item-xfn' => 'nofollow', 'menu-item-classes' => 'one two', 'menu-item-attr-title' => 'Tip "q"', 'menu-item-description' => 'Desc']);
    $b = wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => $post, 'menu-item-object' => 'post', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $a]);
    wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => $category, 'menu-item-object' => 'category', 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $b]);
    wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Draft item', 'menu-item-url' => 'https://zz.example/d', 'menu-item-status' => 'draft']);
    wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Last', 'menu-item-url' => '/relative', 'menu-item-status' => 'publish', 'menu-item-classes' => '']);
    $markup = WP_Classic_To_Block_Menu_Converter::convert(wp_get_nav_menu_object($menu));
    $made['menus'][] = $empty = (int) wp_create_nav_menu('Zz Empty Probe');
    $error = static fn ($value) => is_wp_error($value) ? [$value->get_error_code(), $value->get_error_message()] : $value;
    return [
        is_string($markup) ? str_replace([(string) $post, (string) $category, $host], ['{post}', '{category}', '{host}'], $markup) : $markup,
        WP_Classic_To_Block_Menu_Converter::convert(wp_get_nav_menu_object($empty)),
        $error(WP_Classic_To_Block_Menu_Converter::convert(null)),
        $error(WP_Classic_To_Block_Menu_Converter::convert((object) ['term_id' => 0])),
        $error(WP_Classic_To_Block_Menu_Converter::convert('zz')),
    ];
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
