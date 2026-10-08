<?php
/**
 * The navigation family's helpers (probe navigation-blocks): colors and
 * font sizes from attributes and context, submenu visibility, the layout's
 * custom properties, the interactivity directives a submenu and the overlay
 * close button get, the block tree checks, the fallback menu, menu items
 * to blocks, the link variations, the page list's nesting, the home link
 * and the shared item helpers. Navigation posts a fallback creates are
 * deleted at the end. Same protocol as api-probe.php.
 */

$log = [];
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    // Navigation posts a fallback creates, and the menu items the run adds, are numbered by the database.
    return is_string($value) ? (string) preg_replace(['/("ref":)\d+/', '/(modal-)\d+/'], ['$1{id}', '$1{n}'], $value) : $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!did_action('init')) {
    do_action('init');
}
global $wpdb;
$highest = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
$menu_items = [];
register_shutdown_function(static function () use (&$highest, &$menu_items): void {
    global $wpdb;
    foreach ($wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('wp_navigation', 'nav_menu_item') AND ID > %d", $highest)) as $id) {
        if (is_numeric($id) && (int) $id > $highest) {
            wp_delete_post((int) $id, true);
        }
    }
});
$block = static fn (string $name, array $attrs = [], array $inner = [], string $html = '') => ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => $html, 'innerContent' => $html === '' ? array_fill(0, count($inner), null) : [$html]];

// Navigation: colors, font sizes, submenu visibility, layout properties.
$say('block_core_navigation_build_css_colors', array_map(static fn ($a) => $try(static fn () => block_core_navigation_build_css_colors($a)), [[], ['textColor' => 'accent', 'backgroundColor' => 'base'], ['customTextColor' => '#111', 'customBackgroundColor' => '#eee'], ['style' => ['color' => ['text' => '#222', 'background' => '#ddd']]], ['overlayTextColor' => 'base', 'overlayBackgroundColor' => 'contrast'], ['customOverlayTextColor' => '#333', 'customOverlayBackgroundColor' => '#ccc'], ['textColor' => 'accent', 'customTextColor' => '#111']]));
$say('block_core_navigation_build_css_font_sizes', array_map(static fn ($a) => $try(static fn () => block_core_navigation_build_css_font_sizes($a)), [[], ['fontSize' => 'large'], ['style' => ['typography' => ['fontSize' => '20px']]], ['fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '20px']]]]));
$say('block_core_navigation_get_submenu_visibility', array_map(static fn ($a) => $try(static fn () => block_core_navigation_get_submenu_visibility($a)), [[], ['showSubmenuIcon' => false], ['openSubmenusOnClick' => true], ['submenuVisibility' => 'always'], ['submenuVisibility' => 'click'], ['submenuVisibility' => 'hover', 'openSubmenusOnClick' => true]]));
$say('block_core_navigation_get_layout_custom_property_declarations', array_map(static fn ($l) => $try(static fn () => block_core_navigation_get_layout_custom_property_declarations($l)), [[], ['type' => 'flex', 'justifyContent' => 'center'], ['orientation' => 'vertical', 'justifyContent' => 'right'], ['orientation' => 'vertical'], ['orientation' => 'vertical', 'justifyContent' => 'center', 'verticalAlignment' => 'bottom'], ['flexWrap' => 'nowrap', 'justifyContent' => 'space-between', 'verticalAlignment' => 'top'], ['justifyContent' => 'left', 'orientation' => 'horizontal', 'verticalAlignment' => 'bottom'], ['justifyContent' => 'bogus']]));

// Overlay markup.
$say('block_core_navigation_overlay_html_has_close_block', array_map(static fn ($h) => $try(static fn () => block_core_navigation_overlay_html_has_close_block($h)), ['<div><button class="wp-block-navigation-overlay-close">x</button></div>', '<div>nothing</div>', '<!-- wp:navigation-overlay-close /-->', '<span class="x wp-block-navigation-overlay-close y">z</span>', '', '<button class="x wp-block-navigation-overlay-close">x</button>', '<div class="wp-block-navigation-overlay-close">x</div>', '<BUTTON CLASS="wp-block-navigation-overlay-close">x</BUTTON>', '<button class="wp-block-navigation-overlay-close-x">x</button>']));
$say('block_core_navigation_set_overlay_image_fetch_priority', array_map(static fn ($h) => $try(static fn () => block_core_navigation_set_overlay_image_fetch_priority($h)), ['<img src="a.jpg"><img src="b.jpg" fetchpriority="high">', '<p>x</p><figure><img loading="lazy" src="c.jpg"></figure>', '']));
$say('block_core_navigation_add_directives_to_overlay_close', array_map(static function (string $html) use ($try) {
    return $try(static function () use ($html) {
        $tags = new WP_HTML_Tag_Processor($html);
        $returned = block_core_navigation_add_directives_to_overlay_close($tags);
        return [$tags->get_updated_html(), is_object($returned) ? get_class($returned) : $returned];
    });
}, ['<button class="wp-block-navigation-overlay-close" type="button">x</button><div>y</div>', '<button class="x">no</button>', '<div><button class="a wp-block-navigation-overlay-close">1</button><button class="wp-block-navigation-overlay-close">2</button></div>']));
$submenu = '<ul><li class="wp-block-navigation-item has-child wp-block-navigation-submenu"><a class="wp-block-navigation-item__content" href="#">P</a><button aria-label="P submenu" class="wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle">v</button><ul class="wp-block-navigation__submenu-container"><li class="wp-block-navigation-item has-child"><a href="#">C</a><button class="wp-block-navigation-submenu__toggle">v</button><ul class="wp-block-navigation__submenu-container"><li>g</li></ul></li></ul></li></ul>';
$say('block_core_navigation_add_directives_to_submenu', array_map(static function (array $attrs) use ($try, $submenu) {
    return $try(static function () use ($attrs, $submenu) {
        $tags = new WP_HTML_Tag_Processor($submenu);
        $returned = block_core_navigation_add_directives_to_submenu($tags, $attrs);
        return [$tags->get_updated_html(), is_object($returned) ? get_class($returned) : $returned];
    });
}, [[], ['openSubmenusOnClick' => true], ['showSubmenuIcon' => false], ['submenuVisibility' => 'always']]));
$say('block_core_navigation_add_support_classes_to_container', array_map(static fn (array $b) => $try(static fn () => block_core_navigation_add_support_classes_to_container('<nav class="wp-block-navigation has-text-color"><div class="wp-block-navigation__responsive-container">x</div><ul class="wp-block-navigation__container">y</ul></nav>', $b)), [$block('core/navigation'), $block('core/navigation', ['textColor' => 'accent', 'fontSize' => 'large', 'layout' => ['type' => 'flex', 'orientation' => 'vertical']]), $block('core/navigation', ['style' => ['color' => ['text' => '#123'], 'typography' => ['fontSize' => '20px']], 'className' => 'zz']), $block('core/group', ['textColor' => 'accent'])]));
$say('block_core_navigation_typographic_presets_backcompatibility', array_map(static fn ($b) => $try(static fn () => block_core_navigation_typographic_presets_backcompatibility($b)), [$block('core/navigation', ['style' => ['typography' => ['fontSize' => 'var:preset|font-size|large', 'textDecoration' => 'var:preset|text-decoration|underline', 'fontStyle' => 'var:preset|font-style|italic', 'fontWeight' => 'var:preset|font-weight|700', 'textTransform' => 'var:preset|text-transform|uppercase']]]), $block('core/navigation', ['fontSize' => 'large']), $block('core/paragraph', ['style' => ['typography' => ['textDecoration' => 'var:preset|text-decoration|underline']]])]));

// The block tree.
$tree = [$block('core/group', [], [$block('core/navigation-link', ['label' => 'A', 'id' => 7, 'kind' => 'post-type']), $block('core/navigation', [], [$block('core/navigation-link', ['label' => 'B', 'id' => 8])])]), $block('core/navigation-submenu', ['id' => 9], [$block('core/navigation-link', ['id' => 10])])];
$list = new WP_Block_List($tree);
$kinds = [$block('core/group', [], [$block('core/navigation-link', ['id' => 7, 'kind' => 'post-type']), $block('core/navigation', [], [$block('core/navigation-link', ['id' => 8, 'kind' => 'post-type'])])]), $block('core/navigation-submenu', ['id' => 9, 'kind' => 'post-type'], [$block('core/navigation-link', ['id' => 10, 'kind' => 'post-type']), $block('core/page-list')]), $block('core/navigation-link', ['id' => 11, 'kind' => 'taxonomy'])];
$typed = new WP_Block_List($kinds);
$say('block_core_navigation_block_contains_core_navigation', [$try(static fn () => block_core_navigation_block_contains_core_navigation($list)), $try(static fn () => block_core_navigation_block_contains_core_navigation(new WP_Block_List([$block('core/paragraph')]))), $try(static fn () => block_core_navigation_block_contains_core_navigation(new WP_Block_List([])))]);
$say('block_core_navigation_block_tree_has_block_type', [
    $try(static fn () => block_core_navigation_block_tree_has_block_type($tree, 'core/navigation')),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($tree, 'core/navigation', ['core/group'])),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($tree, 'core/image')),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($list, 'core/navigation-link')),
    $try(static fn () => block_core_navigation_block_tree_has_block_type([], 'core/navigation')),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($typed, 'core/navigation', ['core/group'])),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($typed, 'core/page-list', ['core/navigation-submenu'])),
    $try(static fn () => block_core_navigation_block_tree_has_block_type($typed, 'core/page-list')),
]);
$say('block_core_navigation_get_post_ids', [$try(static fn () => block_core_navigation_get_post_ids($list)), $try(static fn () => block_core_navigation_get_post_ids($typed)), $try(static fn () => block_core_navigation_get_post_ids(new WP_Block_List([])))]);
$say('block_core_navigation_from_block_get_post_ids', [$try(static fn () => block_core_navigation_from_block_get_post_ids(new WP_Block($block('core/navigation-submenu', ['id' => 21], [$block('core/navigation-link', ['id' => 22]), $block('core/navigation-link', ['label' => 'none'])])))), $try(static fn () => block_core_navigation_from_block_get_post_ids(new WP_Block($block('core/navigation-link', ['id' => 23])))), $try(static fn () => block_core_navigation_from_block_get_post_ids(new WP_Block($kinds[1])))]);

// Menus as blocks.
$say('block_core_navigation_parse_blocks_from_menu_items', $try(static function () {
    $item = static fn (int $id, int $parent, string $title, string $url, array $extra = []) => (object) array_merge(['ID' => $id, 'db_id' => $id, 'menu_item_parent' => $parent, 'title' => $title, 'url' => $url, 'object' => 'custom', 'object_id' => $id, 'type' => 'custom', 'target' => '', 'attr_title' => '', 'description' => '', 'classes' => [''], 'xfn' => ''], $extra);
    $items = [$item(1, 0, 'Home', 'https://x.example/'), $item(2, 0, 'About', 'https://x.example/about/', ['object' => 'page', 'type' => 'post_type', 'object_id' => 5, 'target' => '_blank', 'attr_title' => 'Tip', 'classes' => ['zz-a', 'zz-b'], 'description' => 'Desc', 'xfn' => 'nofollow']), $item(3, 2, 'Team', 'https://x.example/team/', ['object' => 'category', 'type' => 'taxonomy', 'object_id' => 6])];
    $by_parent = [];
    foreach ($items as $menu_item) {
        $by_parent[$menu_item->menu_item_parent][] = $menu_item;
    }
    return [block_core_navigation_parse_blocks_from_menu_items($by_parent[0], $by_parent), block_core_navigation_parse_blocks_from_menu_items([], [])];
}));
$say('the classic menu fallback', $try(static function () use (&$menu_items) {
    $menu = block_core_navigation_get_classic_menu_fallback();
    $blocks = $menu ? block_core_navigation_get_classic_menu_fallback_blocks($menu) : null;
    return [$menu instanceof WP_Term ? [$menu->name, $menu->taxonomy] : $menu, is_string($blocks) ? preg_replace('/"id":\d+/', '"id":{id}', $blocks) : $blocks];
}));
$say('block_core_navigation_get_most_recently_published_navigation', $try(static function () {
    $post = block_core_navigation_get_most_recently_published_navigation();
    return $post instanceof WP_Post ? [$post->post_type, $post->post_status, $post->post_title] : $post;
}));
$say('block_core_navigation_get_fallback_blocks', $try(static fn () => array_map(static fn ($b) => is_array($b) ? $b['blockName'] : $b, (array) block_core_navigation_get_fallback_blocks())));
$say('block_core_navigation_maybe_use_classic_menu_fallback', $try(static function () {
    $post = block_core_navigation_maybe_use_classic_menu_fallback();
    return $post instanceof WP_Post ? [$post->post_type, $post->post_status, $post->post_title, preg_replace('/"id":\d+/', '"id":{id}', $post->post_content)] : $post;
}));

// Navigation links and submenus.
$say('block_core_navigation_link_build_css_colors', array_map(static fn ($c) => $try(static fn () => block_core_navigation_link_build_css_colors(...$c)), [
    [[], [], false],
    [['textColor' => 'accent', 'backgroundColor' => 'base'], [], false],
    [['customTextColor' => '#111', 'customBackgroundColor' => '#eee'], [], false],
    [['overlayTextColor' => 'base', 'overlayBackgroundColor' => 'contrast', 'textColor' => 'accent'], [], true],
    [['customOverlayTextColor' => '#333', 'customOverlayBackgroundColor' => '#ccc'], [], true],
    [['textColor' => 'accent'], ['style' => ['color' => ['text' => '#123']]], false],
]));
$say('block_core_navigation_link_build_css_font_sizes', array_map(static fn ($c) => $try(static fn () => block_core_navigation_link_build_css_font_sizes($c)), [[], ['fontSize' => 'large'], ['style' => ['typography' => ['fontSize' => '20px']]], ['fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '20px']]]]));
$say('block_core_navigation_link_maybe_urldecode', array_map(static fn ($u) => $try(static fn () => block_core_navigation_link_maybe_urldecode($u)), ['https://x.example/a%20b/', 'https://x.example/%E2%9C%93/', 'https://x.example/plain/', 'https://x.example/?q=a%26b', '%', '', 'https://x.example/?q=a%2526b', 'https://x.example/p%2520q/?s=%25E2%259C%2593', 'https://x.example/?q=%2520&r=1', 'https://x.example/?q[]=%2520', 'https://x.example/?q=', 'https://x.example/p%2520q/', 'https://x.example/?a=%E2%9C%93&b=%2541']));
$say('submenu icons', [$try(static fn () => block_core_navigation_link_render_submenu_icon()), $try(static fn () => block_core_navigation_submenu_render_submenu_icon()), $try(static fn () => block_core_shared_navigation_render_submenu_icon())]);
$say('block_core_navigation_submenu_get_submenu_visibility', array_map(static fn ($c) => $try(static fn () => block_core_navigation_submenu_get_submenu_visibility($c)), [[], ['openSubmenusOnClick' => true], ['showSubmenuIcon' => false], ['submenuVisibility' => 'always'], ['submenuVisibility' => 'click', 'openSubmenusOnClick' => false]]));
$draft = wp_insert_post(['post_type' => 'page', 'post_title' => 'Zz nav draft', 'post_status' => 'draft']);
$trashed = wp_insert_post(['post_type' => 'post', 'post_title' => 'Zz nav trashed', 'post_status' => 'trash']);
$private = wp_insert_post(['post_type' => 'page', 'post_title' => 'Zz nav private', 'post_status' => 'private']);
register_shutdown_function(static function () use ($draft, $trashed, $private): void {
    foreach ([$draft, $trashed, $private] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$say('block_core_shared_navigation_item_should_render', array_map(static fn ($c) => $try(static fn () => block_core_shared_navigation_item_should_render($c[0], new WP_Block($block('core/navigation-link', $c[0]), $c[1]))), [
    [['label' => 'A', 'kind' => 'post-type', 'id' => $draft, 'type' => 'page'], []],
    [['label' => 'A', 'kind' => 'post-type', 'id' => $trashed, 'type' => 'post'], []],
    [['label' => 'A', 'kind' => 'post-type', 'id' => $private, 'type' => 'page'], []],
    [['label' => 'A', 'kind' => 'post-type', 'type' => 'page'], []],
    [['label' => 'A', 'url' => 'https://x.example/'], []],
    [['label' => 'A', 'kind' => 'post-type', 'id' => 999999999, 'type' => 'page'], []],
    [['label' => 'A', 'kind' => 'post-type', 'id' => 1, 'type' => 'post'], []],
    [['label' => 'A', 'kind' => 'taxonomy', 'id' => 999999999, 'type' => 'category'], []],
    [['label' => 'A', 'kind' => 'taxonomy', 'id' => 1, 'type' => 'category'], []],
    [['kind' => 'custom', 'url' => ''], []],
]));
$say('block_core_navigation_link_build_variations', $try(static fn () => array_map(static fn (array $v) => [$v['name'] ?? null, $v['title'] ?? null, $v['attributes'] ?? null, $v['isActive'] ?? null, $v['isDefault'] ?? null], block_core_navigation_link_build_variations())));
$say('build_variation_for_navigation_link', [
    $try(static fn () => build_variation_for_navigation_link(get_post_type_object('page'), 'post-type')),
    $try(static fn () => build_variation_for_navigation_link(get_taxonomy('post_tag'), 'taxonomy')),
    $try(static fn () => build_variation_for_navigation_link(get_taxonomy('post_format'), 'taxonomy')),
]);
$say('block_core_navigation_link_filter_variations', [
    $try(static fn () => block_core_navigation_link_filter_variations([['name' => 'zz']], WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph'))),
    $try(static fn () => count((array) block_core_navigation_link_filter_variations([], WP_Block_Type_Registry::get_instance()->get_registered('core/navigation-link')))),
    $try(static fn () => array_column((array) block_core_navigation_link_filter_variations([['name' => 'zz']], WP_Block_Type_Registry::get_instance()->get_registered('core/navigation-link')), 'name')),
]);

// Page list and home link.
$say('block_core_page_list_build_css_colors', array_map(static fn ($c) => $try(static fn () => block_core_page_list_build_css_colors(...$c)), [
    [[], []],
    [['textColor' => 'accent'], ['backgroundColor' => 'base', 'overlayTextColor' => 'x']],
    [[], ['customTextColor' => '#111', 'customBackgroundColor' => '#eee', 'overlayTextColor' => 'base', 'overlayBackgroundColor' => 'contrast']],
    [['style' => ['color' => ['text' => '#123', 'background' => '#456']]], ['customOverlayTextColor' => '#333', 'customOverlayBackgroundColor' => '#ccc']],
    [['textColor' => 'accent', 'backgroundColor' => 'base', 'fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '20px']]], []],
]));
$say('block_core_page_list_get_submenu_visibility', array_map(static fn ($c) => $try(static fn () => block_core_page_list_get_submenu_visibility($c)), [[], ['openSubmenusOnClick' => true], ['showSubmenuIcon' => false], ['submenuVisibility' => 'always']]));
$pages = static fn () => [
    ['page_id' => 1, 'title' => 'One', 'link' => 'https://x.example/one/', 'is_active' => false],
    ['page_id' => 2, 'title' => 'Two', 'link' => 'https://x.example/two/', 'is_active' => true],
];
$say('block_core_page_list_nest_pages', [
    $try(static fn () => block_core_page_list_nest_pages($pages(), [1 => [['page_id' => 3, 'title' => 'Three', 'link' => 'https://x.example/three/', 'is_active' => false]], 3 => [['page_id' => 4, 'title' => 'Four', 'link' => 'https://x.example/four/', 'is_active' => false]]])),
    $try(static fn () => block_core_page_list_nest_pages($pages(), [])),
]);
$nested = [['page_id' => 1, 'title' => 'One', 'link' => 'https://x.example/one/', 'is_active' => false, 'children' => [['page_id' => 3, 'title' => 'Three', 'link' => 'https://x.example/three/', 'is_active' => true]]], ['page_id' => 2, 'title' => 'Two & co', 'link' => 'https://x.example/two/', 'is_active' => false]];
$say('block_core_page_list_render_nested_page_list', array_map(static fn ($c) => $try(static fn () => block_core_page_list_render_nested_page_list(...$c)), [
    ['hover', true, true, $nested, false, [1]],
    ['hover', false, true, $nested, false, [1]],
    ['click', true, true, $nested, false, [1]],
    ['click', false, true, $nested, false],
    ['always', true, true, $nested, false, [1]],
    ['always', false, true, $nested, false],
    ['click', true, false, $nested, false],
    ['hover', true, true, $nested, true, [], ['overlay_css_classes' => ['has-background'], 'overlay_inline_styles' => 'background-color: #000;'], 2],
    [false, false, false, $nested, false],
    [false, true, true, $nested, false, [1]],
    [true, true, true, $nested, false, [1], ['overlay_css_classes' => ['has-text-color', 'has-base-color'], 'overlay_inline_styles' => 'color: #333;']],
    [false, false, true, $nested, true, [], [], 1],
    [false, true, true, [], false],
]));
$say('home link', array_map(static fn ($c) => [$try(static fn () => block_core_home_link_build_css_colors($c)), $try(static fn () => block_core_home_link_build_li_wrapper_attributes($c))], [[], ['textColor' => 'accent', 'backgroundColor' => 'base'], ['customTextColor' => '#111', 'customBackgroundColor' => '#eee'], ['fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '20px'], 'color' => ['text' => '#123']]]]));

restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
