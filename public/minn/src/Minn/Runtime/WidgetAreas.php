<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Widget;

/**
 * The widgets helper functions as the reference answers them (probe
 * widget-helpers): a widget id split into its base and number, a sidebar
 * by id (the inactive widgets are a sidebar too, with a name and nothing
 * else), finding and moving a widget between sidebars, one widget rendered
 * in its sidebar as dynamic_sidebar renders each (an unregistered widget
 * renders nothing), and the sidebars with every registered one present;
 * the stored sidebars read and written, and which are registered.
 */
final class WidgetAreas
{
    /** The widgets the engine provides. */
    private const DEFAULT_WIDGETS = ['WP_Widget_Pages', 'WP_Widget_Calendar', 'WP_Widget_Archives', 'WP_Widget_Media_Audio', 'WP_Widget_Media_Image', 'WP_Widget_Media_Gallery', 'WP_Widget_Media_Video', 'WP_Widget_Meta', 'WP_Widget_Search', 'WP_Widget_Text', 'WP_Widget_Categories', 'WP_Widget_Recent_Posts', 'WP_Widget_Recent_Comments', 'WP_Widget_RSS', 'WP_Widget_Tag_Cloud', 'WP_Nav_Menu_Widget', 'WP_Widget_Custom_HTML', 'WP_Widget_Block'];

    /**
     * The widgets the engine provides registered, then widgets_init for
     * plugin and theme code (wp_widgets_init); the Links widget only while
     * the link manager is on. The block widget is the one every modern
     * sidebar is made of, so it is registered before the action runs.
     */
    public static function registerDefaults(): void
    {
        $factory = Handoff::widgetFactory();
        foreach (self::DEFAULT_WIDGETS as $widget) {
            $factory->register($widget);
        }
        if (Runtime::options()->filtered('link_manager_enabled')) {
            $factory->register('WP_Widget_Links');
        }
        Runtime::hooks()->action('widgets_init', []);
    }

    /** A registered widget shown outside a sidebar (the_widget), in a div with its class unless the arguments say otherwise. */
    public static function show(mixed $widget, mixed $instance = [], mixed $args = []): void
    {
        $object = Handoff::widgetFactory()->widgets[$widget] ?? null;
        if (!$object instanceof Widget) {
            return;
        }
        $args = \Minn\Support\Lists::args($args, ['before_widget' => '<div class="widget %s">', 'after_widget' => '</div>', 'before_title' => '<h2 class="widgettitle">', 'after_title' => '</h2>']);
        $instance = \Minn\Support\Lists::args($instance);
        $args['before_widget'] = sprintf($args['before_widget'], $object->widget_options['classname']);
        $hooks = Runtime::hooks();
        $instance = $hooks->filter('widget_display_callback', [$instance, $object, $args]);
        if ($instance === false) {
            return;
        }
        $hooks->action('the_widget', [$widget, $instance, $args]);
        $object->_set(-1);
        $object->widget($args, $instance);
    }

    /**
     * The stored sidebars and their widgets (wp_get_sidebars_widgets), as
     * sidebars_widgets leaves them.
     *
     * @return array<string, mixed>
     */
    public static function stored(mixed $deprecated = true): mixed
    {
        $sidebars = Runtime::options()->filtered('sidebars_widgets', []);
        $sidebars = is_array($sidebars) ? $sidebars : [];
        unset($sidebars['array_version']);
        return Runtime::hooks()->filter('sidebars_widgets', [$sidebars]);
    }

    /** The sidebars stored (wp_set_sidebars_widgets), at array version 3. */
    public static function store(mixed $sidebars): void
    {
        $sidebars['array_version'] = 3;
        OptionWrites::update('sidebars_widgets', $sidebars);
    }

    /**
     * The sidebars with every registered one present and the widgets that
     * lost theirs inactive (retrieve_widgets), stored; before widgets_init,
     * the stored ones with the registered areas filled in empty.
     *
     * @return array<string, mixed>
     */
    public static function current(mixed $themeChanged = false): array
    {
        $stored = self::stored();
        $areas = array_keys($GLOBALS['wp_registered_sidebars'] ?? []);
        if (Runtime::hooks()->actionsDone('widgets_init') === 0) {
            return $stored + ['wp_inactive_widgets' => []] + array_fill_keys($areas, []);
        }
        $sidebars = self::retrieve($stored, $areas, array_keys($GLOBALS['wp_registered_widgets'] ?? []));
        self::store($sidebars);
        return $sidebars;
    }

    /** Whether a sidebar is registered (is_registered_sidebar). */
    public static function isRegistered(mixed $id): bool
    {
        return isset($GLOBALS['wp_registered_sidebars'][$id]);
    }

    public const INACTIVE = 'wp_inactive_widgets';

    /** wp_parse_widget_id: ['id_base' => ..., 'number' => n], or the id alone as its base. @return array{id_base: string, number?: int} */
    public static function parse(string $id): array
    {
        return preg_match('/^(.+)-(\d+)$/', $id, $m) ? ['id_base' => $m[1], 'number' => (int) $m[2]] : ['id_base' => $id];
    }

    /** wp_get_sidebar: a registered sidebar, the inactive widgets, or null. @return array<string, mixed>|null */
    public static function sidebar(string $id): ?array
    {
        if ($id === self::INACTIVE) {
            return ['id' => self::INACTIVE, 'name' => 'Inactive widgets'];
        }
        return $GLOBALS['wp_registered_sidebars'][$id] ?? null;
    }

    /** wp_find_widgets_sidebar: the sidebar holding a widget, or null. */
    public static function find(string $widgetId): ?string
    {
        foreach (self::stored() as $sidebar => $widgets) {
            if (in_array($widgetId, (array) $widgets, true)) {
                return (string) $sidebar;
            }
        }
        return null;
    }

    /** wp_assign_widget_to_sidebar: out of every sidebar, then onto the end of one ('' leaves it in none). */
    public static function assign(string $widgetId, string $sidebarId): void
    {
        $sidebars = self::stored();
        foreach ($sidebars as $sidebar => $widgets) {
            $sidebars[$sidebar] = array_values(array_filter((array) $widgets, static fn ($id) => $id !== $widgetId));
        }
        if ($sidebarId !== '') {
            $sidebars[$sidebarId][] = $widgetId;
        }
        self::store($sidebars);
    }

    /** wp_render_widget: a registered widget as its sidebar wraps it, through dynamic_sidebar_params; '' otherwise. */
    public static function render(string $widgetId, string $sidebarId): string
    {
        $widget = $GLOBALS['wp_registered_widgets'][$widgetId] ?? null;
        $sidebar = $sidebarId === self::INACTIVE ? [] : ($GLOBALS['wp_registered_sidebars'][$sidebarId] ?? null);
        if ($widget === null || $sidebar === null || !is_callable($widget['callback'] ?? null)) {
            return '';
        }
        $sidebar += ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''];
        $params = array_merge([array_merge($sidebar, ['widget_id' => $widgetId, 'widget_name' => $widget['name']])], (array) $widget['params']);
        $classname = is_string($widget['classname'] ?? null) ? (string) $widget['classname'] : '';
        $params[0]['before_widget'] = sprintf((string) $params[0]['before_widget'], $widgetId, $classname);
        $params = Runtime::hooks()->filter('dynamic_sidebar_params', [$params]);
        ob_start();
        call_user_func_array($widget['callback'], $params);
        return (string) ob_get_clean();
    }

    /** wp_render_widget_control: a registered widget's settings form, or null. */
    public static function control(string $widgetId): ?string
    {
        $control = $GLOBALS['wp_registered_widget_controls'][$widgetId] ?? null;
        if ($control === null || !isset($GLOBALS['wp_registered_widgets'][$widgetId]) || !is_callable($control['callback'] ?? null)) {
            return null;
        }
        ob_start();
        call_user_func_array($control['callback'], (array) $control['params']);
        return (string) ob_get_clean();
    }

    /**
     * retrieve_widgets (probe widgets-remaining): the inactive list, then every
     * registered sidebar, holding only widgets that are registered. The
     * inactive list takes, in order, the registered widgets no list mentions
     * (the -1 placeholders aside, in the order they were registered), the
     * widgets already inactive, and the widgets of sidebars no longer
     * registered, which go.
     *
     * @param array<string, mixed> $stored the stored lists, sidebar => widget ids
     * @param list<string> $areas the registered sidebars
     * @param list<string> $registered the registered widgets
     * @return array<string, list<string>>
     */
    public static function retrieve(array $stored, array $areas, array $registered): array
    {
        $known = array_flip($registered);
        $keep = static fn ($ids): array => array_values(array_filter((array) $ids, static fn ($id) => is_string($id) && isset($known[$id])));
        $sidebars = [self::INACTIVE => []];
        foreach ($areas as $id) {
            $sidebars[$id] = $keep($stored[$id] ?? []);
        }
        $gone = array_diff_key(array_filter($stored, 'is_array'), $sidebars);
        $inactive = array_merge(self::unmentioned($stored, $registered), $keep($stored[self::INACTIVE] ?? []), ...array_map($keep, array_values($gone)));
        $sidebars[self::INACTIVE] = array_values(array_unique($inactive));
        return $sidebars;
    }

    /** The id fragments a theme switch matches sidebars by, group by group (probe sidebars-mapping). */
    private const MAPPING_GROUPS = [['sidebar', 'primary', 'main', 'right'], ['second', 'left'], ['sidebar-2', 'footer', 'bottom'], ['header', 'top']];

    /**
     * The old theme's widget lists fitted to the new theme's sidebars
     * (probe sidebars-mapping). One old sidebar and one new map straight
     * across. Otherwise a sidebar of the same id keeps its widgets; then,
     * group by group, the new sidebars whose ids hold one of the group's
     * fragments take, in order, the old ones that do (a new sidebar may take
     * from several groups); what is left goes after the inactive widgets.
     * When nothing is inactive then, the stored inactive widgets that are
     * registered and placed nowhere come back, keys kept.
     *
     * @param array<string, mixed> $existing the old theme's lists
     * @param list<string> $areas the new theme's sidebars
     * @param array<array-key, mixed> $stored the theme's stored inactive widgets
     * @param list<string> $registered the registered widgets
     * @return array<string, array<array-key, mixed>>
     */
    public static function map(array $existing, array $areas, array $stored, array $registered): array
    {
        if ($existing === []) {
            return [self::INACTIVE => []];
        }
        $mapped = [self::INACTIVE => (array) ($existing[self::INACTIVE] ?? [])];
        unset($existing[self::INACTIVE]);
        $orphans = array_filter($existing, static fn ($key) => str_starts_with((string) $key, 'orphaned_widgets_'), ARRAY_FILTER_USE_KEY);
        if (count($areas) === 1 && count($existing) - count($orphans) === 1) {
            $mapped[self::INACTIVE] = array_merge($mapped[self::INACTIVE], ...array_values(array_filter($orphans, 'is_array')));
            $mapped[$areas[0]] = array_values((array) current(array_diff_key($existing, $orphans)));
            return $mapped;
        }
        $taken = [];
        foreach ($areas as $id) {
            $mapped[$id] = is_array($existing[$id] ?? null) ? array_values($existing[$id]) : [];
            $taken[$id] = array_key_exists($id, $existing);
        }
        foreach (self::MAPPING_GROUPS as $fragments) {
            $old = array_keys(array_filter($existing, static fn ($widgets, $id) => empty($taken[$id]) && is_array($widgets) && $widgets !== [] && self::inGroup((string) $id, $fragments), ARRAY_FILTER_USE_BOTH));
            foreach (array_values(array_filter($areas, static fn ($id) => self::inGroup($id, $fragments))) as $i => $id) {
                if (!isset($old[$i])) {
                    break;
                }
                $mapped[$id] = array_merge($mapped[$id], $existing[$old[$i]]);
                $taken[$old[$i]] = true;
            }
        }
        foreach ($existing as $id => $widgets) {
            if (empty($taken[$id]) && is_array($widgets)) {
                $mapped[self::INACTIVE] = array_merge($mapped[self::INACTIVE], $widgets);
            }
        }
        if ($mapped[self::INACTIVE] === []) {
            $placed = array_flip(array_filter(array_merge(...array_values($mapped)), 'is_string'));
            $known = array_flip($registered);
            $mapped[self::INACTIVE] = array_filter($stored, static fn ($id) => is_string($id) && isset($known[$id]) && !isset($placed[$id]));
        }
        return $mapped;
    }

    /** @param list<string> $fragments */
    private static function inGroup(string $id, array $fragments): bool
    {
        foreach ($fragments as $fragment) {
            if (str_contains(strtolower($id), $fragment)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Registered widgets no stored list mentions, the -1 placeholders aside.
     *
     * @param array<string, mixed> $stored
     * @param list<string> $registered
     * @return list<string>
     */
    private static function unmentioned(array $stored, array $registered): array
    {
        $mentioned = array_flip(array_merge(...array_values(array_map(static fn ($ids) => array_filter((array) $ids, 'is_string'), $stored))));
        return array_values(array_filter($registered, static fn ($id) => !isset($mentioned[$id]) && !str_ends_with($id, '-1')));
    }
}
