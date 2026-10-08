<?php
/**
 * The navigation family's helpers (wp-includes/blocks/navigation*.php,
 * page-list.php, home-link.php; probe navigation-blocks): colours and font
 * sizes from attributes or block context, submenu visibility, the layout's
 * custom properties, the interactivity directives a submenu and the overlay
 * close button take, the block tree checks, the fallback menu, menu items as
 * blocks, the link variations, the page list's nesting, and the home link's
 * wrapper. The engine's own renderer (Minn\Blocks\Dynamic\Theme\Navigation)
 * renders the blocks; these answer plugins that call the helpers.
 */

use Minn\Blocks\Dynamic\Theme\Navigation;
use Minn\Content\Menus;

/** @internal colour classes and inline styles: has-text-color and the named colour's class, or the custom one inline; the same for the background */
function _minn_navigation_colors($text, $custom_text, $background, $custom_background): array
{
    $classes = [];
    $styles = '';
    foreach ([['text', $text, $custom_text, 'has-text-color', 'has-%s-color', 'color'], ['background', $background, $custom_background, 'has-background', 'has-%s-background-color', 'background-color']] as [, $named, $custom, $flag, $class, $property]) {
        if (!empty($named) || !empty($custom)) {
            $classes[] = $flag;
        }
        if (!empty($named)) {
            $classes[] = sprintf($class, $named);
        } elseif (!empty($custom)) {
            $styles .= "{$property}: {$custom};";
        }
    }
    return ['css_classes' => $classes, 'inline_styles' => $styles];
}

/** @internal the overlay colours alongside the main ones, from a source's overlay* keys */
function _minn_navigation_overlay_colors(array $source): array
{
    $overlay = _minn_navigation_colors($source['overlayTextColor'] ?? null, $source['customOverlayTextColor'] ?? null, $source['overlayBackgroundColor'] ?? null, $source['customOverlayBackgroundColor'] ?? null);
    return ['overlay_css_classes' => $overlay['css_classes'], 'overlay_inline_styles' => $overlay['inline_styles']];
}

/** A navigation's colour classes and styles, and its overlay's, from its attributes. */
function block_core_navigation_build_css_colors($attributes)
{
    $attributes = (array) $attributes;
    return _minn_navigation_colors($attributes['textColor'] ?? null, $attributes['customTextColor'] ?? null, $attributes['backgroundColor'] ?? null, $attributes['customBackgroundColor'] ?? null) + _minn_navigation_overlay_colors($attributes);
}

/** A navigation's font size class (a custom size rides on its style, not here). */
function block_core_navigation_build_css_font_sizes($attributes)
{
    return ['css_classes' => empty($attributes['fontSize']) ? [] : ['has-' . $attributes['fontSize'] . '-font-size'], 'inline_styles' => ''];
}

/** How a navigation opens its submenus: its submenuVisibility, hover becoming click when it opens submenus on click. */
function block_core_navigation_get_submenu_visibility($attributes)
{
    $visibility = (string) ($attributes['submenuVisibility'] ?? 'hover');
    return $visibility === 'hover' && !empty($attributes['openSubmenusOnClick']) ? 'click' : $visibility;
}

/** @internal how a submenu inside a navigation opens, from its context: always, else click or hover */
function _minn_context_submenu_visibility($context): string
{
    if (($context['submenuVisibility'] ?? null) === 'always') {
        return 'always';
    }
    return !empty($context['openSubmenusOnClick']) ? 'click' : 'hover';
}

/** The layout's custom properties: the justification, the direction, wrapping, and how a row or a column lines up its items. */
function block_core_navigation_get_layout_custom_property_declarations($layout)
{
    $justify = ['left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center', 'space-between' => 'space-between'][$layout['justifyContent'] ?? ''] ?? 'flex-start';
    $vertical = ($layout['orientation'] ?? '') === 'vertical';
    return [
        '--navigation-layout-justification-setting' => $justify,
        '--navigation-layout-direction' => $vertical ? 'column' : 'row',
        '--navigation-layout-wrap' => ($layout['flexWrap'] ?? '') === 'nowrap' ? 'nowrap' : 'wrap',
        '--navigation-layout-justify' => $vertical && !isset($layout['justifyContent']) ? 'initial' : $justify,
        '--navigation-layout-align' => $vertical ? $justify : 'center',
    ];
}

/** Whether overlay markup has its own close button (a button carrying the overlay close block's class). */
function block_core_navigation_overlay_html_has_close_block($html)
{
    $tags = new WP_HTML_Tag_Processor((string) $html);
    return $tags->next_tag(['tag_name' => 'button', 'class_name' => 'wp-block-navigation-overlay-close']);
}

/** Every image in an overlay loads at low priority. */
function block_core_navigation_set_overlay_image_fetch_priority($overlay_blocks_html)
{
    $tags = new WP_HTML_Tag_Processor((string) $overlay_blocks_html);
    while ($tags->next_tag('img')) {
        $tags->set_attribute('fetchpriority', 'low');
    }
    return $tags->get_updated_html();
}

/** The overlay close buttons close the menu on click; the markup back. */
function block_core_navigation_add_directives_to_overlay_close($tags)
{
    while ($tags->next_tag(['tag_name' => 'button', 'class_name' => 'wp-block-navigation-overlay-close'])) {
        $tags->set_attribute('data-wp-on--click', 'actions.closeMenuOnClick');
    }
    return $tags->get_updated_html();
}

/**
 * A navigation's submenus wired for the Interactivity API: each item with
 * children gets its context and handlers (the hover ones when submenus open
 * on hover), each toggle its expanded state and click, each submenu list
 * its focus handler. The markup back.
 */
function block_core_navigation_add_directives_to_submenu($tags, $block_attributes)
{
    $visibility = block_core_navigation_get_submenu_visibility($block_attributes);
    while ($tags->next_tag()) {
        if ($tags->get_tag() === 'LI' && $tags->has_class('has-child')) {
            foreach (Navigation::submenuDirectives($visibility) as $name => $value) {
                $tags->set_attribute($name, $value);
            }
        } elseif ($tags->get_tag() === 'BUTTON' && $tags->has_class('wp-block-navigation-submenu__toggle')) {
            $tags->set_attribute('data-wp-bind--aria-expanded', 'state.isSubmenuOpen');
            $tags->set_attribute('data-wp-on--click', 'actions.toggleMenuOnClick');
        } elseif ($tags->get_tag() === 'UL' && $tags->has_class('wp-block-navigation__submenu-container')) {
            $tags->set_attribute('data-wp-on--focus', 'actions.openMenuOnFocus');
        }
    }
    return $tags->get_updated_html();
}

/** A rendered navigation as it is: the engine's renderer puts its supports' classes on the container already, and no case on the reference changes it here. */
function block_core_navigation_add_support_classes_to_container($block_content, $block)
{
    return $block_content;
}

/** A navigation's typography presets in the forms older content stored (var:preset|...) read as their values, the font size aside. */
function block_core_navigation_typographic_presets_backcompatibility($parsed_block)
{
    if (($parsed_block['blockName'] ?? '') !== 'core/navigation') {
        return $parsed_block;
    }
    foreach (['textDecoration', 'fontStyle', 'fontWeight', 'textTransform'] as $property) {
        $value = $parsed_block['attrs']['style']['typography'][$property] ?? null;
        if (is_string($value) && str_starts_with($value, 'var:preset|')) {
            $parsed_block['attrs']['style']['typography'][$property] = substr($value, (int) strrpos($value, '|') + 1);
        }
    }
    return $parsed_block;
}

/** Whether inner blocks hold a navigation block, at any depth. */
function block_core_navigation_block_contains_core_navigation($inner_blocks)
{
    return block_core_navigation_block_tree_has_block_type($inner_blocks, 'core/navigation');
}

/** Whether a tree of block instances holds a block of a type, not looking inside the types skipped. */
function block_core_navigation_block_tree_has_block_type($blocks, $block_type, $skip_block_types = [])
{
    foreach ($blocks ?? [] as $block) {
        if (!$block instanceof WP_Block) {
            continue;
        }
        if ($block->name === $block_type) {
            return true;
        }
        if (!in_array($block->name, (array) $skip_block_types, true) && $block->inner_blocks && block_core_navigation_block_tree_has_block_type($block->inner_blocks, $block_type, $skip_block_types)) {
            return true;
        }
    }
    return false;
}

/** The post ids the links in inner blocks point to, at any depth. */
function block_core_navigation_get_post_ids($inner_blocks)
{
    $ids = [];
    foreach ($inner_blocks ?? [] as $block) {
        $ids = [...$ids, ...block_core_navigation_from_block_get_post_ids($block)];
    }
    return $ids;
}

/** The post ids a block's inner links point to, then its own when it links to a post. */
function block_core_navigation_from_block_get_post_ids($block)
{
    $ids = $block->inner_blocks ? block_core_navigation_get_post_ids($block->inner_blocks) : [];
    if (($block->attributes['kind'] ?? null) === 'post-type' && isset($block->attributes['id'])) {
        $ids[] = $block->attributes['id'];
    }
    return $ids;
}

/** Menu items as navigation blocks: a link each, a submenu for an item with children (its inner blocks serialized as its content). */
function block_core_navigation_parse_blocks_from_menu_items($menu_items, $menu_items_by_parent_id)
{
    $blocks = [];
    foreach ((array) $menu_items as $menu_item) {
        $block = ['blockName' => 'core/navigation-link', 'attrs' => Menus::linkAttributes((array) $menu_item), 'innerBlocks' => [], 'innerContent' => []];
        $children = $menu_items_by_parent_id[$menu_item->ID] ?? [];
        if ($children !== []) {
            $block['blockName'] = 'core/navigation-submenu';
            $block['innerBlocks'] = block_core_navigation_parse_blocks_from_menu_items($children, $menu_items_by_parent_id);
            $block['innerContent'] = array_map('serialize_block', $block['innerBlocks']);
        }
        $blocks[] = $block;
    }
    return $blocks;
}

/** The classic menu a navigation falls back to: the one in the primary location, else the newest (none when there are no menus). */
function block_core_navigation_get_classic_menu_fallback()
{
    $menus = wp_get_nav_menus();
    if (empty($menus)) {
        return null;
    }
    $locations = get_nav_menu_locations();
    foreach (['primary', 'main', 'menu-1'] as $location) {
        $menu = isset($locations[$location]) ? wp_get_nav_menu_object($locations[$location]) : null;
        if ($menu) {
            return $menu;
        }
    }
    usort($menus, static fn ($a, $b) => $b->term_id <=> $a->term_id);
    return $menus[0];
}

/** A classic menu's items as serialized navigation blocks. */
function block_core_navigation_get_classic_menu_fallback_blocks($classic_nav_menu)
{
    $items = (array) wp_get_nav_menu_items($classic_nav_menu->term_id, ['update_post_term_cache' => false]);
    _wp_menu_item_classes_by_context($items);
    $by_parent = [];
    foreach ($items as $item) {
        $by_parent[(int) $item->menu_item_parent][] = $item;
    }
    return serialize_blocks(block_core_navigation_parse_blocks_from_menu_items($by_parent[0] ?? [], $by_parent));
}

/** The newest published navigation menu, or null. */
function block_core_navigation_get_most_recently_published_navigation()
{
    $posts = get_posts(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'numberposts' => 1, 'no_found_rows' => true]);
    return $posts[0] ?? null;
}

/** A classic menu turned into a published navigation menu, once: the new navigation post, or null without a classic menu. */
function block_core_navigation_maybe_use_classic_menu_fallback()
{
    $menu = block_core_navigation_get_classic_menu_fallback();
    if (!$menu) {
        return null;
    }
    $id = wp_insert_post(['post_content' => block_core_navigation_get_classic_menu_fallback_blocks($menu), 'post_title' => $menu->name, 'post_name' => $menu->slug, 'post_status' => 'publish', 'post_type' => 'wp_navigation'], true);
    return is_wp_error($id) ? null : get_post($id);
}

/** The blocks a navigation with none of its own renders: the newest navigation menu's, else a page list; through block_core_navigation_render_fallback. */
function block_core_navigation_get_fallback_blocks()
{
    $navigation = block_core_navigation_get_most_recently_published_navigation();
    $blocks = $navigation ? block_core_navigation_filter_out_empty_blocks(parse_blocks($navigation->post_content)) : [];
    if ($blocks === []) {
        $blocks = [['blockName' => 'core/page-list', 'innerContent' => [], 'attrs' => []]];
    }
    return apply_filters('block_core_navigation_render_fallback', $blocks);
}

// Links and submenus.

/** A link's colour classes and styles from its navigation's context: the overlay colours inside a submenu. */
function block_core_navigation_link_build_css_colors($context, $attributes, $is_sub_menu = false)
{
    $context = (array) $context;
    $keys = $is_sub_menu ? ['overlayTextColor', 'customOverlayTextColor', 'overlayBackgroundColor', 'customOverlayBackgroundColor'] : ['textColor', 'customTextColor', 'backgroundColor', 'customBackgroundColor'];
    return _minn_navigation_colors(...array_map(static fn (string $key) => $context[$key] ?? null, $keys));
}

/** A link's font size from its navigation's context: the preset's class, else the custom size (made fluid) inline. */
function block_core_navigation_link_build_css_font_sizes($context)
{
    if (!empty($context['fontSize'])) {
        return ['css_classes' => ['has-' . $context['fontSize'] . '-font-size'], 'inline_styles' => ''];
    }
    $size = $context['style']['typography']['fontSize'] ?? null;
    return ['css_classes' => [], 'inline_styles' => $size === null ? '' : sprintf('font-size: %s;', wp_get_typography_font_size_value(['size' => $size]))];
}

/** A link's URL decoded once when a query value is still encoded after parsing (a double-encoded link). */
function block_core_navigation_link_maybe_urldecode($url)
{
    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
    foreach ($query as $value) {
        if (is_string($value) && $value !== '' && rawurldecode($value) !== $value) {
            return rawurldecode((string) $url);
        }
    }
    return $url;
}

/** The submenu toggle's chevron. */
function block_core_navigation_link_render_submenu_icon()
{
    return Navigation::CHEVRON;
}

/** The submenu toggle's chevron. */
function block_core_navigation_submenu_render_submenu_icon()
{
    return Navigation::CHEVRON;
}

/** The submenu toggle's chevron. */
function block_core_shared_navigation_render_submenu_icon()
{
    return Navigation::CHEVRON;
}

/** How a submenu opens, from its navigation's context. */
function block_core_navigation_submenu_get_submenu_visibility($context)
{
    return _minn_context_submenu_visibility($context);
}

/** Whether a navigation item renders: a link to a post only while that post is published. */
function block_core_shared_navigation_item_should_render($attributes, $block)
{
    if (($attributes['kind'] ?? null) !== 'post-type' || empty($attributes['id'])) {
        return true;
    }
    $post = get_post((int) $attributes['id']);
    return $post instanceof WP_Post && $post->post_status === 'publish';
}

/**
 * A link variation for a post type or taxonomy: its name (tag for
 * post_tag), and its own item-link label and description where it set
 * them (one left at core's default is "{singular} link" and a blank); post
 * formats have their own.
 */
function build_variation_for_navigation_link($entity, $kind)
{
    $name = $entity->name === 'post_tag' ? 'tag' : $entity->name;
    if ($entity->name === 'post_format') {
        [$title, $description] = [__('Post Format Link'), __('A link to a post format')];
    } else {
        // Core's defaults are the labels a type starts from: a post's or a page's, a tag's or a category's.
        $base = $kind === 'taxonomy' ? get_taxonomy(empty($entity->hierarchical) ? 'post_tag' : 'category') : get_post_type_object(empty($entity->hierarchical) ? 'post' : 'page');
        $default = $base ? $base->labels : (object) [];
        $title = ($entity->labels->item_link ?? '') !== ($default->item_link ?? '') ? $entity->labels->item_link : sprintf(__('%s link'), $entity->labels->singular_name ?? '');
        $description = ($entity->labels->item_link_description ?? '') !== ($default->item_link_description ?? '') ? $entity->labels->item_link_description : ' ';
    }
    return ['name' => $name, 'title' => $title, 'description' => $description, 'attributes' => ['type' => $name, 'kind' => $kind]];
}

/** The link variations: one for each post type and taxonomy shown in navigation menus. */
function block_core_navigation_link_build_variations()
{
    $variations = [];
    foreach (get_post_types(['show_in_nav_menus' => true], 'objects') as $post_type) {
        $variations[] = build_variation_for_navigation_link($post_type, 'post-type');
    }
    foreach (get_taxonomies(['show_in_nav_menus' => true], 'objects') as $taxonomy) {
        $variations[] = build_variation_for_navigation_link($taxonomy, 'taxonomy');
    }
    return $variations;
}

/** The navigation link's variations: the built ones first, then those given; any other block's as given. */
function block_core_navigation_link_filter_variations($variations, $block_type)
{
    return ($block_type->name ?? null) === 'core/navigation-link' ? array_merge(block_core_navigation_link_build_variations(), (array) $variations) : $variations;
}

// Page list and home link.

/** A page list's colours and its overlay's, from its navigation's context. */
function block_core_page_list_build_css_colors($attributes, $context)
{
    $context = (array) $context;
    return _minn_navigation_colors($context['textColor'] ?? null, $context['customTextColor'] ?? null, $context['backgroundColor'] ?? null, $context['customBackgroundColor'] ?? null) + _minn_navigation_overlay_colors($context);
}

/** How a page list's submenus open, from its navigation's context. */
function block_core_page_list_get_submenu_visibility($context)
{
    return _minn_context_submenu_visibility($context);
}

/** Pages nested under their parents: a level is keyed by page id, so each entry takes the children listed under its key. */
function block_core_page_list_nest_pages($current_level, $children)
{
    foreach ((array) $current_level as $key => $page) {
        if (isset($children[$key])) {
            $current_level[$key]['children'] = block_core_page_list_nest_pages($children[$key], $children);
        }
    }
    return $current_level;
}

/**
 * A nested page list as list items: the current page and its ancestors
 * marked; inside a navigation, the navigation's item classes, how submenus
 * open, and a toggle (a button in place of the link for a click submenu);
 * a submenu's items in the overlay colours.
 */
function block_core_page_list_render_nested_page_list($submenu_visibility, $show_submenu_icons, $is_navigation_child, $nested_pages, $is_nested, $active_page_ancestor_ids = [], $colors = [], $depth = 0)
{
    if (empty($nested_pages)) {
        return null;
    }
    $markup = '';
    foreach ($nested_pages as $page) {
        $markup .= _minn_page_list_item((array) $page, (string) $submenu_visibility, (bool) $show_submenu_icons, (bool) $is_navigation_child, (bool) $is_nested, (array) $active_page_ancestor_ids, (array) $colors)
            . (empty($page['children']) ? '' : '<ul class="wp-block-navigation__submenu-container">' . block_core_page_list_render_nested_page_list($submenu_visibility, $show_submenu_icons, $is_navigation_child, $page['children'], true, $active_page_ancestor_ids, $colors, $depth + 1) . '</ul>')
            . '</li>';
    }
    return $markup;
}

/**
 * @internal a page's opening list item and its link (a toggle button for a
 * click submenu inside a navigation; the icon button after the link when
 * icons show), its classes marking the current page, its ancestors and how
 * its submenu opens; the overlay colours on a nested item
 */
function _minn_page_list_item(array $page, string $visibility, bool $icons, bool $navigation_child, bool $nested, array $ancestors, array $colors): string
{
    $parent = !empty($page['children']);
    $open = ['hover' => $icons ? 'open-on-hover-click' : '', 'click' => 'open-on-click', 'always' => 'open-always'][$visibility] ?? '';
    $classes = array_filter(['wp-block-pages-list__item', !empty($page['is_active']) ? 'current-menu-item' : '', in_array($page['page_id'], $ancestors, true) ? 'current-menu-ancestor' : '', $parent ? 'has-child' : '', $navigation_child ? 'wp-block-navigation-item' : '', $navigation_child ? $open : '', ...($nested ? (array) ($colors['overlay_css_classes'] ?? []) : [])]);
    $style = $nested && !empty($colors['overlay_inline_styles']) ? ' style="' . esc_attr($colors['overlay_inline_styles']) . '"' : '';
    $title = esc_html($page['title'] ?? '');
    $label = esc_attr(($page['title'] ?? '') . ' submenu');
    if ($navigation_child && $parent && $visibility === 'click') {
        $head = '<button aria-label="' . $label . '" class=" wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false">' . $title . '</button><span class="wp-block-page-list__submenu-icon wp-block-navigation__submenu-icon">' . Navigation::CHEVRON . '</span>';
    } else {
        $head = '<a class="wp-block-pages-list__item__link' . ($navigation_child ? ' wp-block-navigation-item__content' : '') . '" href="' . esc_url($page['link'] ?? '') . '"' . (!empty($page['is_active']) ? ' aria-current="page"' : '') . '>' . $title . '</a>'
            . ($navigation_child && $parent && $icons ? '<button aria-label="' . $label . '" class="wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle" aria-expanded="false">' . Navigation::CHEVRON . '</button>' : '');
    }
    return '<li class="' . esc_attr(implode(' ', $classes)) . '"' . $style . '>' . $head;
}

/** A home link's colours from its navigation's context: the named ones as classes, the style's own inline. */
function block_core_home_link_build_css_colors($context)
{
    return _minn_navigation_colors($context['textColor'] ?? null, $context['style']['color']['text'] ?? null, $context['backgroundColor'] ?? null, $context['style']['color']['background'] ?? null);
}

/** A home link's list item attributes: its colours and the navigation item class, through the block's wrapper. */
function block_core_home_link_build_li_wrapper_attributes($context)
{
    $colors = block_core_home_link_build_css_colors($context);
    return get_block_wrapper_attributes(['class' => trim(implode(' ', $colors['css_classes']) . ' wp-block-navigation-item'), 'style' => $colors['inline_styles']]);
}
