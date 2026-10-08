<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Escape;

/**
 * wp_nav_menu()'s menu: the one the arguments name, its items marked for
 * the page in view (MenuItemMarks), walked, wrapped in the container.
 */
final class NavMenu
{
    /** @var list<string> the ul ids already used on this page, so a repeated menu counts up */
    private static array $usedIds = [];

    /**
     * The whole menu for wp_nav_menu(): resolve, fetch, decorate, walk,
     * wrap, filter. Null when there is no menu (the caller's fallback
     * runs); false when the items filtered away to nothing.
     */
    public static function build(object $args): string|false|null
    {
        $menu = self::menuForArgs($args);
        $items = $menu ? \wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]) : false;
        if (!$menu || !$items) {
            return null;
        }
        $items = MenuItemMarks::apply($items);
        if ((int) $args->depth !== 1) {
            $parents = array_map(static fn ($item) => (int) $item->menu_item_parent, $items);
            foreach ($items as $item) {
                if (in_array((int) $item->db_id, $parents, true)) {
                    $item->classes[] = 'menu-item-has-children';
                }
            }
        }
        $items = \apply_filters('wp_nav_menu_objects', $items, $args);
        $markup = (string) \walk_nav_menu_tree($items, (int) $args->depth, $args);
        $markup = (string) \apply_filters('wp_nav_menu_items', $markup, $args);
        $markup = (string) \apply_filters("wp_nav_menu_{$menu->slug}_items", $markup, $args);
        if ($markup === '') {
            return false;
        }
        $nav = sprintf((string) $args->items_wrap, Escape::attr(self::wrapId($menu, $args)), Escape::attr((string) $args->menu_class), $markup);
        return self::container($nav, $args, (string) $menu->slug);
    }

    /** The menu the args name: by menu, by assigned location, else the first menu that has items. */
    private static function menuForArgs(object $args): object|false
    {
        $menu = \wp_get_nav_menu_object($args->menu);
        if (!$menu && $args->theme_location) {
            $locations = \get_nav_menu_locations();
            return isset($locations[$args->theme_location]) ? \wp_get_nav_menu_object($locations[$args->theme_location]) : false;
        }
        if (!$menu && !$args->theme_location) {
            foreach (\wp_get_nav_menus() as $candidate) {
                if (\wp_get_nav_menu_items($candidate->term_id, ['update_post_term_cache' => false])) {
                    return $candidate;
                }
            }
        }
        return $menu;
    }

    /** The ul id: the caller's, else menu-{slug}, counted up when the same menu renders twice on a page. */
    private static function wrapId(object $menu, object $args): string
    {
        if (!empty($args->menu_id)) {
            return (string) $args->menu_id;
        }
        $id = 'menu-' . $menu->slug;
        while (in_array($id, self::$usedIds, true)) {
            $count = preg_match('/-(\d+)$/', $id, $m) ? (int) $m[1] + 1 : 1;
            $id = preg_replace('/-\d+$/', '', $id) . '-' . $count;
        }
        self::$usedIds[] = $id;
        return $id;
    }

    /** The container wrap, when the args ask for one. */
    private static function container(string $nav, object $args, string $slug): string
    {
        if (!$args->container) {
            return $nav;
        }
        $allowed = \apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);
        if (!in_array($args->container, (array) $allowed, true)) {
            return $nav;
        }
        $attributes = $args->container_id ? ' id="' . Escape::attr((string) $args->container_id) . '"' : '';
        $attributes .= ' class="' . Escape::attr((string) ($args->container_class ?: 'menu-' . $slug . '-container')) . '"';
        if ($args->container === 'nav' && $args->container_aria_label) {
            $attributes .= ' aria-label="' . Escape::attr((string) $args->container_aria_label) . '"';
        }
        return '<' . $args->container . $attributes . '>' . $nav . '</' . $args->container . '>';
    }
}
