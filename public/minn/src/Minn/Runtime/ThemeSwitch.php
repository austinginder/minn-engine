<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Files;

/**
 * Switching the theme as the reference does it, in two halves. The switch
 * (switch_theme): the leaving theme keeps its widgets and its menu
 * locations are remembered, the three theme options move, the switch is
 * recorded (theme_switched), the arriving theme's mods start with the
 * classic sidebars, only its mods stay autoloaded, then switch_theme. The
 * next load, on init (check_theme_switched): after_switch_theme with the
 * theme that left, whose defaults map the menu locations across
 * (_wp_menus_changed) and look the widgets over, then the rules flushed
 * and the record cleared. Deleting a theme fires delete_theme and
 * deleted_theme around its folder going, and forgets the update check.
 */
final class ThemeSwitch
{
    /** The menu location names, by role, a theme's locations are matched across by. */
    private const SLUG_GROUPS = [
        ['primary', 'menu-1', 'main', 'header', 'navigation', 'top'],
        ['secondary', 'menu-2', 'footer', 'subsidiary', 'bottom'],
        ['social'],
    ];

    /** switch_theme. */
    public static function switchTo(string $stylesheet): void
    {
        $old = \wp_get_theme();
        $new = \wp_get_theme($stylesheet);
        \set_theme_mod('sidebars_widgets', ['time' => time(), 'data' => \wp_get_sidebars_widgets()]);
        $locations = \get_theme_mod('nav_menu_locations');
        if ($locations !== false || Runtime::options()->filtered('theme_switch_menu_locations') !== false) {
            \update_option('theme_switch_menu_locations', $locations, true);
        }
        \update_option('template', $new->get_template());
        \update_option('stylesheet', $new->get_stylesheet());
        \delete_option('template_root');
        \delete_option('stylesheet_root');
        \update_option('current_theme', $new->get('Name'));
        \set_theme_mod('wp_classic_sidebars', $GLOBALS['wp_registered_sidebars'] ?? []);
        \update_option('theme_switched', $old->get_stylesheet());
        \update_option('theme_switched_via_customizer', false);
        \wp_set_option_autoload_values(['theme_mods_' . $new->get_stylesheet() => true, 'theme_mods_' . $old->get_stylesheet() => false]);
        \do_action('switch_theme', $new->get('Name'), $new, $old);
    }

    /** check_theme_switched: on the load after a switch, after_switch_theme with the theme that left. */
    public static function checkSwitched(): void
    {
        $stylesheet = Runtime::options()->filtered('theme_switched');
        if (!$stylesheet) {
            return;
        }
        $old = \wp_get_theme((string) $stylesheet);
        if (Runtime::options()->filtered('theme_switched_via_customizer')) {
            Runtime::hooks()->remove('after_switch_theme', '_wp_menus_changed');
            Runtime::hooks()->remove('after_switch_theme', '_wp_sidebars_changed');
            \update_option('theme_switched_via_customizer', false);
        }
        \do_action('after_switch_theme', $old->exists() ? $old->get('Name') : (string) $stylesheet, $old);
        \flush_rewrite_rules();
        \update_option('theme_switched', false);
    }

    /** _wp_menus_changed: the leaving theme's menu locations mapped onto the arriving theme's. */
    public static function menusChanged(): void
    {
        $mapped = self::mapLocations(\get_nav_menu_locations(), Runtime::options()->filtered('theme_switch_menu_locations'));
        \set_theme_mod('nav_menu_locations', $mapped);
        \delete_option('theme_switch_menu_locations');
    }

    /**
     * wp_map_nav_menu_locations: with one location on each side, it is
     * kept; otherwise a location of the same name keeps its menu, then by
     * role, each name of a role in turn hands the next unused location of
     * that role (with a menu) to the arriving locations that name it.
     *
     * @param array<string, int> $new
     */
    public static function mapLocations(array $new, mixed $old): array
    {
        $registered = array_keys((array) \get_registered_nav_menus());
        if (!is_array($old) || $old === []) {
            return $new;
        }
        if (count($registered) === 1 && count($old) === 1) {
            $new[(string) $registered[0]] = reset($old);
            return $new;
        }
        foreach ($registered as $location) {
            if (array_key_exists($location, $old)) {
                $new[$location] = $old[$location];
                unset($old[$location]);
            }
        }
        foreach (self::SLUG_GROUPS as $group) {
            foreach ($group as $slug) {
                foreach ($registered as $location) {
                    if (!self::names((string) $location, $slug)) {
                        continue;
                    }
                    $taken = self::nextOfGroup($old, $group);
                    if ($taken !== null) {
                        $new[$location] = $old[$taken];
                        unset($old[$taken]);
                    }
                }
            }
        }
        return $new;
    }

    /**
     * delete_theme: the folder removed between delete_theme and deleted_theme
     * (by $remove when the caller removes it its own way); false for no name.
     *
     * @param (callable(string): bool)|null $remove
     */
    public static function delete(string $stylesheet, ?callable $remove = null): bool
    {
        if ($stylesheet === '') {
            return false;
        }
        \do_action('delete_theme', $stylesheet);
        $deleted = $remove !== null ? (bool) $remove($stylesheet) : self::removeFolder($stylesheet);
        \do_action('deleted_theme', $stylesheet, $deleted);
        \delete_site_transient('update_themes');
        return $deleted;
    }

    /** A theme's folder, directly inside the themes folder (a symlink unlinked, never followed); one already gone counts. */
    private static function removeFolder(string $stylesheet): bool
    {
        if (\validate_file($stylesheet) !== 0 || str_contains($stylesheet, '/') || $stylesheet === '.' || $stylesheet === '..') {
            return false;
        }
        $path = rtrim((string) \get_theme_root($stylesheet), '/') . '/' . $stylesheet;
        return Files::deleteTree($path);
    }

    /** The first location left in $old that a name of the group names, with a menu in it. @param array<string, mixed> $old @param list<string> $group */
    private static function nextOfGroup(array $old, array $group): ?string
    {
        foreach ($old as $location => $menu) {
            foreach ($group as $slug) {
                if ($menu && self::names((string) $location, $slug)) {
                    return (string) $location;
                }
            }
        }
        return null;
    }

    /** Whether a location and a role's name contain one another, letter case aside. */
    private static function names(string $location, string $slug): bool
    {
        return $location !== '' && (stripos($location, $slug) !== false || stripos($slug, $location) !== false);
    }
}
