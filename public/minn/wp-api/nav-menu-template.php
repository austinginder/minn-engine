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
/** The menu items given the classes and current flags for the page being viewed, in place (Runtime\MenuItemMarks). */
function _wp_menu_item_classes_by_context(&$menu_items)
{
    $menu_items = Minn\Runtime\MenuItemMarks::apply(array_values((array) $menu_items));
}

function wp_page_menu($args = [])
{
    $args = wp_parse_args($args, ['sort_column' => 'menu_order, post_title', 'menu_id' => '', 'menu_class' => 'menu', 'container' => 'div', 'echo' => true, 'link_before' => '', 'link_after' => '', 'before' => '<ul>', 'after' => '</ul>', 'item_spacing' => 'discard', 'walker' => '']);
    $args['item_spacing'] = in_array($args['item_spacing'], ['preserve', 'discard'], true) ? $args['item_spacing'] : 'discard';
    $n = $args['item_spacing'] === 'preserve' ? "\n" : '';
    $args = apply_filters('wp_page_menu_args', $args);
    $list = array_merge($args, ['echo' => false, 'title_li' => '']);
    $menu = '';
    if (!empty($args['show_home'])) {
        $text = in_array($args['show_home'], [true, '1', 1], true) ? __('Home') : $args['show_home'];
        $menu = '<li ' . (is_front_page() && !is_paged() ? 'class="current_page_item"' : '') . '><a href="' . esc_url(home_url('/')) . '">' . $args['link_before'] . $text . $args['link_after'] . '</a></li>';
        if (get_option('show_on_front') === 'page') {
            $list['exclude'] = (empty($list['exclude']) ? '' : $list['exclude'] . ',') . get_option('page_on_front');
        }
    }
    $menu .= wp_list_pages($list);
    $container = sanitize_text_field($args['container']) ?: 'div';
    if ($menu !== '') {
        // As wp_nav_menu's fallback, the list is wrapped in a plain ul whatever the caller's before and after.
        $fallback = ($args['fallback_cb'] ?? null) === 'wp_page_menu' && $container !== 'ul';
        $menu = ($fallback ? "<ul>{$n}" : $args['before']) . $menu . ($fallback ? '</ul>' : $args['after']);
    }
    $attrs = ($args['menu_id'] ? ' id="' . esc_attr($args['menu_id']) . '"' : '') . ($args['menu_class'] ? ' class="' . esc_attr($args['menu_class']) . '"' : '');
    $menu = apply_filters('wp_page_menu', "<{$container}{$attrs}>{$menu}</{$container}>{$n}", $args);
    if ($args['echo']) {
        echo $menu;
        return null;
    }
    return $menu;
}

