<?php
/** Widgets and sidebars: registration is recorded; a block theme renders none. */

use Minn\Runtime\Runtime;

function _minn_widget_factory(): WP_Widget_Factory
{
    if (!isset($GLOBALS['wp_widget_factory']) || !$GLOBALS['wp_widget_factory'] instanceof WP_Widget_Factory) {
        $GLOBALS['wp_widget_factory'] = new WP_Widget_Factory();
    }
    return $GLOBALS['wp_widget_factory'];
}

function register_widget($widget)
{
    _minn_widget_factory()->register($widget);
}

function unregister_widget($widget)
{
    _minn_widget_factory()->unregister($widget);
}

function register_sidebars($number = 1, $args = [])
{
    $number = (int) $number;
    if (is_string($args)) {
        parse_str($args, $args);
    }
    for ($i = 1; $i <= $number; $i++) {
        $one = $args;
        $one['name'] = $number > 1 ? sprintf('%s %d', $args['name'] ?? 'Sidebar', $i) : ($args['name'] ?? 'Sidebar');
        $one['id'] = $args['id'] ?? 'sidebar-' . $i;
        register_sidebar($one);
    }
}

function register_sidebar($args = [])
{
    $sidebars = $GLOBALS['wp_registered_sidebars'] ?? [];
    $i = count($sidebars) + 1;
    $id_is_empty = empty($args['id']);
    $defaults = ['name' => sprintf('Sidebar %d', $i), 'id' => "sidebar-{$i}", 'description' => '', 'class' => '', 'before_widget' => '<li id="%1$s" class="widget %2$s">', 'after_widget' => "</li>\n", 'before_title' => '<h2 class="widgettitle">', 'after_title' => "</h2>\n", 'before_sidebar' => '', 'after_sidebar' => '', 'show_in_rest' => false];
    $sidebar = wp_parse_args($args, apply_filters('register_sidebar_defaults', $defaults));
    if ($id_is_empty) {
        _doing_it_wrong(__FUNCTION__, 'No id was provided for a sidebar.', '4.2.0');
    }
    $sidebar = apply_filters('register_sidebar', $sidebar);
    $GLOBALS['wp_registered_sidebars'][$sidebar['id']] = $sidebar;
    add_theme_support('widgets');
    do_action('register_sidebar', $sidebar);
    return $sidebar['id'];
}

function unregister_sidebar($sidebar_id)
{
    unset($GLOBALS['wp_registered_sidebars'][$sidebar_id]);
}

function is_registered_sidebar($sidebar_id)
{
    return isset($GLOBALS['wp_registered_sidebars'][$sidebar_id]);
}

function wp_register_sidebar_widget($id, $name, $output_callback, $options = [], ...$params)
{
    $id = (string) $id;
    $id_base = is_numeric(substr($id, (int) strrpos($id, '-') + 1)) ? substr($id, 0, (int) strrpos($id, '-')) : $id;
    $widget = ['name' => $name, 'id' => $id, 'callback' => $output_callback, 'params' => $params];
    $widget = array_merge($widget, wp_parse_args($options, ['classname' => $output_callback, 'description' => '']));
    if (is_callable($output_callback) && (!isset($GLOBALS['wp_registered_widgets'][$id]) || did_action('widgets_init'))) {
        do_action('wp_register_sidebar_widget', $widget);
        $GLOBALS['wp_registered_widgets'][$id] = $widget;
    }
}

function wp_unregister_sidebar_widget($id)
{
    do_action('wp_unregister_sidebar_widget', $id);
    unset($GLOBALS['wp_registered_widgets'][$id], $GLOBALS['wp_registered_widget_controls'][$id], $GLOBALS['wp_registered_widget_updates'][$id]);
}

function wp_register_widget_control($id, $name, $control_callback, $options = [], ...$params)
{
    $GLOBALS['wp_registered_widget_controls'][$id] = ['name' => $name, 'id' => $id, 'callback' => $control_callback, 'params' => $params] + wp_parse_args($options, ['width' => 250, 'height' => 200]);
}

function wp_get_sidebars_widgets($deprecated = true)
{
    $sidebars = get_option('sidebars_widgets', []);
    $sidebars = is_array($sidebars) ? $sidebars : [];
    unset($sidebars['array_version']);
    return apply_filters('sidebars_widgets', $sidebars);
}

function wp_set_sidebars_widgets($sidebars_widgets)
{
    $sidebars_widgets['array_version'] = 3;
    update_option('sidebars_widgets', $sidebars_widgets);
}

function is_active_sidebar($index)
{
    $index = is_int($index) ? "sidebar-{$index}" : sanitize_title((string) $index);
    $sidebars = wp_get_sidebars_widgets();
    $active = !empty($sidebars[$index]);
    return (bool) apply_filters('is_active_sidebar', $active, $index);
}

function is_dynamic_sidebar()
{
    foreach (wp_get_sidebars_widgets() as $index => $widgets) {
        if (!empty($widgets) && is_registered_sidebar($index)) {
            return true;
        }
    }
    return false;
}

function dynamic_sidebar($index = 1)
{
    $index = is_int($index) ? "sidebar-{$index}" : sanitize_title((string) $index);
    $sidebars = wp_get_sidebars_widgets();
    if (!isset($GLOBALS['wp_registered_sidebars'][$index]) || empty($sidebars[$index])) {
        do_action('dynamic_sidebar_before', $index, false);
        do_action('dynamic_sidebar_after', $index, false);
        return (bool) apply_filters('dynamic_sidebar_has_widgets', false, $index);
    }
    $sidebar = $GLOBALS['wp_registered_sidebars'][$index];
    do_action('dynamic_sidebar_before', $index, true);
    $did = false;
    foreach ((array) $sidebars[$index] as $id) {
        if (!isset($GLOBALS['wp_registered_widgets'][$id])) {
            continue;
        }
        $widget = $GLOBALS['wp_registered_widgets'][$id];
        $params = array_merge([array_merge($sidebar, ['widget_id' => $id, 'widget_name' => $widget['name']])], (array) $widget['params']);
        $classname = is_string($widget['classname'] ?? '') ? (string) $widget['classname'] : '';
        $params[0]['before_widget'] = sprintf($params[0]['before_widget'], $id, $classname);
        $params = apply_filters('dynamic_sidebar_params', $params);
        do_action('dynamic_sidebar', $widget);
        if (is_callable($widget['callback'])) {
            call_user_func_array($widget['callback'], $params);
            $did = true;
        }
    }
    do_action('dynamic_sidebar_after', $index, true);
    return (bool) apply_filters('dynamic_sidebar_has_widgets', $did, $index);
}

function is_active_widget($callback = false, $widget_id = false, $id_base = false, $skip_inactive = true)
{
    foreach (wp_get_sidebars_widgets() as $sidebar => $widgets) {
        if ($skip_inactive && ($sidebar === 'wp_inactive_widgets' || str_starts_with((string) $sidebar, 'orphaned_widgets'))) {
            continue;
        }
        foreach ((array) $widgets as $widget) {
            if (($callback && isset($GLOBALS['wp_registered_widgets'][$widget]['callback']) && $GLOBALS['wp_registered_widgets'][$widget]['callback'] === $callback) || ($id_base && _get_widget_id_base($widget) === $id_base)) {
                if (!$widget_id || $widget_id === $GLOBALS['wp_registered_widgets'][$widget]['id']) {
                    return $sidebar;
                }
            }
        }
    }
    return false;
}

function _get_widget_id_base($id)
{
    return preg_replace('/-[0-9]+$/', '', (string) $id);
}

function the_widget($widget, $instance = [], $args = [])
{
    $object = _minn_widget_factory()->widgets[$widget] ?? null;
    if (!$object instanceof WP_Widget) {
        return;
    }
    $before = ['before_widget' => '<div class="widget %s">', 'after_widget' => '</div>', 'before_title' => '<h2 class="widgettitle">', 'after_title' => '</h2>'];
    $args = wp_parse_args($args, $before);
    $instance = wp_parse_args($instance);
    $args['before_widget'] = sprintf($args['before_widget'], $object->widget_options['classname']);
    $instance = apply_filters('widget_display_callback', $instance, $object, $args);
    if ($instance === false) {
        return;
    }
    do_action('the_widget', $widget, $instance, $args);
    $object->_set(-1);
    $object->widget($args, $instance);
}

function wp_convert_widget_settings($base_name, $option_name, $settings)
{
    $single = false;
    $changed = false;
    if (empty($settings)) {
        $single = true;
    } else {
        foreach (array_keys($settings) as $number) {
            if (!is_numeric($number)) {
                $single = true;
                break;
            }
        }
    }
    if ($single) {
        $settings = [2 => $settings];
        $settings['_multiwidget'] = 1;
    }
    return $settings;
}

function wp_widgets_init()
{
    do_action('widgets_init');
}

function wp_use_widgets_block_editor()
{
    return (bool) apply_filters('use_widgets_block_editor', true);
}

function wp_get_widget_defaults()
{
    $defaults = [];
    foreach ((array) ($GLOBALS['wp_registered_widget_controls'] ?? []) as $id => $control) {
        $defaults[$id] = [];
    }
    return $defaults;
}
