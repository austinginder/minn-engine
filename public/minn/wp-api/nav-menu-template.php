<?php
/** Nav menu rendering: wp_nav_menu() over the reference's walker contract. Assembly lives in Minn\Runtime\NavMenu. */

use Minn\Runtime\NavMenu;

function wp_nav_menu($args = [])
{
    $defaults = ['menu' => '', 'container' => 'div', 'container_class' => '', 'container_id' => '', 'container_aria_label' => '', 'menu_class' => 'menu', 'menu_id' => '', 'echo' => true, 'fallback_cb' => 'wp_page_menu', 'before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>', 'item_spacing' => 'preserve', 'depth' => 0, 'walker' => '', 'theme_location' => ''];
    $args = (object) apply_filters('wp_nav_menu_args', wp_parse_args($args, $defaults));
    if (!in_array($args->item_spacing, ['preserve', 'discard'], true)) {
        $args->item_spacing = 'preserve';
    }
    $nav_menu = apply_filters('pre_wp_nav_menu', null, $args);
    if ($nav_menu === null) {
        $built = NavMenu::build($args);
        if ($built === null) {
            return isset($args->fallback_cb) && is_callable($args->fallback_cb) ? call_user_func($args->fallback_cb, (array) $args) : false;
        }
        if ($built === false) {
            return false;
        }
        $nav_menu = apply_filters('wp_nav_menu', $built, $args);
    }
    if ($args->echo) {
        echo $nav_menu;
        return null;
    }
    return $nav_menu;
}

function walk_nav_menu_tree($items, $depth, $args)
{
    $walker = empty($args->walker) ? new Walker_Nav_Menu() : $args->walker;
    return $walker->walk($items, $depth, $args);
}

/** The classic fallback when no menu is assigned: a page list. The engine renders the list shape without the reference's page-walker chrome. */
function wp_page_menu($args = [])
{
    $args = wp_parse_args($args, ['sort_column' => 'menu_order, post_title', 'menu_id' => '', 'menu_class' => 'menu', 'container' => 'div', 'echo' => true, 'link_before' => '', 'link_after' => '', 'before' => '<ul>', 'after' => '</ul>', 'item_spacing' => 'discard', 'walker' => '']);
    $args = apply_filters('wp_page_menu_args', $args);
    $list = '';
    foreach (get_pages(['sort_column' => $args['sort_column']]) ?: [] as $page) {
        $current = is_page($page->ID) ? ' class="page_item page-item-' . (int) $page->ID . ' current_page_item"' : ' class="page_item page-item-' . (int) $page->ID . '"';
        $list .= '<li' . $current . '><a href="' . esc_url((string) get_permalink($page->ID)) . '">' . $args['link_before'] . esc_html((string) $page->post_title) . $args['link_after'] . '</a></li>';
    }
    $menu = $list === '' ? '' : $args['before'] . $list . $args['after'];
    if ($menu !== '' && $args['container']) {
        $attrs = $args['menu_id'] ? ' id="' . esc_attr($args['menu_id']) . '"' : '';
        $menu = '<' . $args['container'] . $attrs . ' class="' . esc_attr($args['menu_class']) . '">' . $menu . '</' . $args['container'] . '>';
    }
    $menu = apply_filters('wp_page_menu', $menu, $args);
    if ($args['echo']) {
        echo $menu;
        return null;
    }
    return $menu;
}
