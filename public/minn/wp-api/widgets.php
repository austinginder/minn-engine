<?php
/** Widgets and sidebars: registration is recorded; a block theme renders none. */

use Minn\Widgets\WidgetForms;
use Minn\Runtime\Runtime;
use Minn\Runtime\WidgetAreas;

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
    // Several take the number into a %d in the name and onto the given id from the second on;
    // one keeps its name as given (probe widgets-remaining).
    for ($i = 1; $i <= $number; $i++) {
        $one = $args;
        $one['name'] = $number > 1 ? sprintf($args['name'] ?? __('Sidebar %d'), $i) : ($args['name'] ?? __('Sidebar'));
        if (isset($args['id'])) {
            $one['id'] = $i > 1 ? $args['id'] . '-' . $i : $args['id'];
        }
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
    if (empty($output_callback)) {
        // An empty callback takes the widget away (probe widgets-remaining).
        unset($GLOBALS['wp_registered_widgets'][$id]);
        return;
    }
    $widget = ['name' => $name, 'id' => $id, 'callback' => $output_callback, 'params' => $params];
    $widget = array_merge($widget, wp_parse_args($options, ['classname' => $output_callback]));
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

/**
 * A widget's settings form, and once per id base its update handler (the
 * instance number made -1); an empty callback takes both away. A second
 * form for an id before widgets_init leaves the first (probe deprecated).
 */
function wp_register_widget_control($id, $name, $control_callback, $options = [], ...$params)
{
    global $wp_registered_widget_controls, $wp_registered_widget_updates;
    $id = strtolower((string) $id);
    $id_base = _get_widget_id_base($id);
    if (empty($control_callback)) {
        unset($wp_registered_widget_controls[$id], $wp_registered_widget_updates[$id_base]);
        return;
    }
    if (isset($wp_registered_widget_controls[$id]) && !did_action('widgets_init')) {
        return;
    }
    $options = wp_parse_args($options, ['width' => 250, 'height' => 200]);
    $widget = array_merge(['name' => $name, 'id' => $id, 'callback' => $control_callback, 'params' => $params], $options, ['width' => (int) $options['width'], 'height' => (int) $options['height']]);
    $wp_registered_widget_controls[$id] = $widget;
    if (!isset($wp_registered_widget_updates[$id_base])) {
        if (isset($widget['params'][0]['number'])) {
            $widget['params'][0]['number'] = -1;
        }
        unset($widget['width'], $widget['height'], $widget['name'], $widget['id']);
        $wp_registered_widget_updates[$id_base] = $widget;
    }
}

/** A widget type's update callback, registered once per id base (an empty callback for one already there unregisters it). */
function _register_widget_update_callback($id_base, $update_callback, $options = [], ...$params)
{
    global $wp_registered_widget_updates;
    if (isset($wp_registered_widget_updates[$id_base])) {
        if (empty($update_callback)) {
            unset($wp_registered_widget_updates[$id_base]);
        }
        return;
    }
    $wp_registered_widget_updates[$id_base] = array_merge(['callback' => $update_callback, 'params' => $params], (array) $options);
}

/** One widget's form callback, as a control (250 by 200 unless the options say otherwise); an empty callback unregisters it. */
function _register_widget_form_callback($id, $name, $form_callback, $options = [], ...$params)
{
    global $wp_registered_widget_controls;
    $id = strtolower((string) $id);
    if (empty($form_callback)) {
        unset($wp_registered_widget_controls[$id]);
        return;
    }
    if (isset($wp_registered_widget_controls[$id]) && !did_action('widgets_init')) {
        return;
    }
    $options = wp_parse_args($options, ['width' => 250, 'height' => 200]);
    $wp_registered_widget_controls[$id] = array_merge(['name' => $name, 'id' => $id, 'callback' => $form_callback, 'params' => $params], $options, ['width' => (int) $options['width'], 'height' => (int) $options['height']]);
}

function wp_unregister_widget_control($id)
{
    wp_register_widget_control($id, '', '');
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
    }
    // Settings already numbered get the marker too (probe widgets-remaining).
    $settings['_multiwidget'] = 1;
    return $settings;
}

/**
 * Registers the widgets the engine provides, then fires widgets_init for
 * plugin and theme code. The block widget is the one every modern sidebar
 * is made of, so it must be registered before the action runs or nothing
 * a site saved in the block editor renders at all.
 */
function wp_widgets_init()
{
    foreach (['WP_Widget_Pages', 'WP_Widget_Calendar', 'WP_Widget_Archives', 'WP_Widget_Media_Audio', 'WP_Widget_Media_Image', 'WP_Widget_Media_Gallery', 'WP_Widget_Media_Video', 'WP_Widget_Meta', 'WP_Widget_Search', 'WP_Widget_Text', 'WP_Widget_Categories', 'WP_Widget_Recent_Posts', 'WP_Widget_Recent_Comments', 'WP_Widget_RSS', 'WP_Widget_Tag_Cloud', 'WP_Nav_Menu_Widget', 'WP_Widget_Custom_HTML', 'WP_Widget_Block'] as $widget) {
        register_widget($widget);
    }
    // The Links widget only while the link manager is on (probe widget-methods).
    if (get_option('link_manager_enabled')) {
        register_widget('WP_Widget_Links');
    }
    do_action('widgets_init');
}

function wp_use_widgets_block_editor()
{
    return (bool) apply_filters('use_widgets_block_editor', true);
}

function wp_get_widget_defaults()
{
    // One empty list per registered sidebar (probe widgets-remaining).
    return array_fill_keys(array_keys($GLOBALS['wp_registered_sidebars'] ?? []), []);
}

function wp_widget_rss_output($rss, $args = [])
{
    if (is_string($rss)) {
        $rss = fetch_feed($rss);
    } elseif (is_array($rss) && isset($rss['url'])) {
        $args = $rss;
        $rss = fetch_feed($rss['url']);
    } elseif (!is_object($rss)) {
        return;
    }
    if (is_wp_error($rss)) {
        if (is_admin() || current_user_can('manage_options')) {
            echo '<p><strong>' . __('RSS Error:') . '</strong> ' . esc_html($rss->get_error_message()) . '</p>';
        }
        return;
    }
    $args = wp_parse_args($args, ['show_author' => 0, 'show_date' => 0, 'show_summary' => 0, 'items' => 0]);
    $items = (int) $args['items'];
    if ($items < 1 || $items > 20) {
        $items = 10;
    }
    if (!$rss->get_item_quantity()) {
        echo '<ul><li>' . __('An error has occurred, which probably means the feed is down. Try again later.') . '</li></ul>';
        return;
    }
    echo '<ul>';
    foreach ($rss->get_items(0, $items) as $item) {
        echo _minn_rss_widget_item($item, $args);
    }
    echo '</ul>';
}

/** @internal one entry of the RSS widget: the title (linked when the item has a link), then date, summary and author as asked */
function _minn_rss_widget_item($item, array $args): string
{
    $link = esc_url(strip_tags((string) $item->get_link()));
    $title = esc_html(trim(strip_tags((string) $item->get_title())));
    $title = $title === '' ? __('Untitled') : $title;
    $summary = '';
    if (!empty($args['show_summary'])) {
        $text = html_entity_decode((string) $item->get_description(), ENT_QUOTES, get_option('blog_charset'));
        $summary = '<div class="rssSummary">' . esc_attr(wp_trim_words($text, 55, ' [&hellip;]')) . '</div>';
    }
    $stamp = $item->get_date('U');
    $date = !empty($args['show_date']) && $stamp ? ' <span class="rss-date">' . date_i18n(get_option('date_format'), $stamp) . '</span>' : '';
    $author = '';
    if (!empty($args['show_author']) && is_object($item->get_author())) {
        $author = ' <cite>' . esc_html(strip_tags((string) $item->get_author()->get_name())) . '</cite>';
    }
    $heading = $link === '' ? $title : "<a class='rsswidget' href='{$link}'>{$title}</a>";
    return "<li>{$heading}{$date}{$summary}{$author}</li>";
}

function wp_widget_rss_process($widget_rss, $check_feed = true)
{
    $items = (int) ($widget_rss['items'] ?? 0);
    if ($items < 1 || $items > 20) {
        $items = 10;
    }
    $url = sanitize_url(strip_tags((string) ($widget_rss['url'] ?? '')));
    $title = isset($widget_rss['title']) ? trim(strip_tags((string) $widget_rss['title'])) : '';
    $show_summary = (int) ($widget_rss['show_summary'] ?? 0);
    $show_author = (int) ($widget_rss['show_author'] ?? 0);
    $show_date = (int) ($widget_rss['show_date'] ?? 0);
    $error = false;
    $link = '';
    if ($check_feed) {
        $rss = fetch_feed($url);
        if (is_wp_error($rss)) {
            $error = $rss->get_error_message();
        } else {
            $link = esc_url(strip_tags((string) $rss->get_permalink()));
        }
    }
    return compact('title', 'url', 'link', 'items', 'error', 'show_summary', 'show_author', 'show_date');
}

/** The RSS widget's settings form (Widgets\WidgetForms::rss), each input shown unless $inputs hides it. */
function wp_widget_rss_form($args, $inputs = null)
{
    echo WidgetForms::rss((array) $args, (array) $inputs);
}

/** @internal the script a dropdown widget prints: navigate or submit on change, unless Escape closed the list */
function _minn_dropdown_script(string $dropdownId, string $onChange): string
{
    return "( ( dropdownId ) => {\n\tconst dropdown = document.getElementById( dropdownId );\n\tfunction onSelectChange() {\n\t\tsetTimeout( () => {\n\t\t\tif ( 'escape' === dropdown.dataset.lastkey ) {\n\t\t\t\treturn;\n\t\t\t}\n\t\t\t" . $onChange . "\n\t\t}, 250 );\n\t}\n\tfunction onKeyUp( event ) {\n\t\tif ( 'Escape' === event.key ) {\n\t\t\tdropdown.dataset.lastkey = 'escape';\n\t\t} else {\n\t\t\tdelete dropdown.dataset.lastkey;\n\t\t}\n\t}\n\tfunction onClick() {\n\t\tdelete dropdown.dataset.lastkey;\n\t}\n\tdropdown.addEventListener( 'keyup', onKeyUp );\n\tdropdown.addEventListener( 'click', onClick );\n\tdropdown.addEventListener( 'change', onSelectChange );\n})( " . wp_json_encode($dropdownId) . " );\n";
}

/** A widget id as its base and number (Runtime\WidgetAreas). */
function wp_parse_widget_id($id)
{
    return WidgetAreas::parse((string) $id);
}

/** A registered sidebar, the inactive widgets, or null. */
function wp_get_sidebar($id)
{
    return WidgetAreas::sidebar((string) $id);
}

/** The sidebar holding a widget, or null. */
function wp_find_widgets_sidebar($widget_id)
{
    return WidgetAreas::find((string) $widget_id);
}

/** Moves a widget onto the end of a sidebar ('' takes it out of all). */
function wp_assign_widget_to_sidebar($widget_id, $sidebar_id)
{
    WidgetAreas::assign((string) $widget_id, (string) $sidebar_id);
}

/** One registered widget rendered as its sidebar wraps it; '' when it cannot be. */
function wp_render_widget($widget_id, $sidebar_id)
{
    return WidgetAreas::render((string) $widget_id, (string) $sidebar_id);
}

/** A registered widget's settings form, or null. */
function wp_render_widget_control($id)
{
    return WidgetAreas::control((string) $id);
}

/** A registered sidebar's description through kses (its own sidebar_description context); null when it has none. */
function wp_sidebar_description($id)
{
    $sidebar = is_scalar($id) ? ($GLOBALS['wp_registered_sidebars'][$id] ?? null) : null;
    return isset($sidebar['description']) ? wp_kses($sidebar['description'], 'sidebar_description') : null;
}

/** Sidebars with only the widget ids registered (or given) left in each list, keys kept; anything not a list is left as it is. */
function _wp_remove_unregistered_widgets($sidebars_widgets, $allowed_widget_ids = [])
{
    $allowed = empty($allowed_widget_ids) ? array_keys((array) ($GLOBALS['wp_registered_widgets'] ?? [])) : (array) $allowed_widget_ids;
    foreach ((array) $sidebars_widgets as $sidebar => $widgets) {
        if (is_array($widgets)) {
            $sidebars_widgets[$sidebar] = array_filter($widgets, static fn ($widget) => in_array($widget, $allowed));
        }
    }
    return $sidebars_widgets;
}

/** On a block theme, the sidebars the previous classic theme registered (theme mod wp_classic_sidebars) registered again as they were, by their ids (widgets_init 1). */
function _wp_block_theme_register_classic_sidebars()
{
    $classic = wp_is_block_theme() ? get_theme_mod('wp_classic_sidebars') : null;
    foreach (is_array($classic) ? $classic : [] as $sidebar) {
        $GLOBALS['wp_registered_sidebars'][$sidebar['id']] = $sidebar;
    }
}

/** With the widgets editor's script queued, a notice for the old editor's script or the post editor's style queued beside it (admin_head). */
function wp_check_widget_editor_deps()
{
    if (!wp_script_is('wp-edit-widgets', 'enqueued') && !wp_script_is('wp-customize-widgets', 'enqueued')) {
        return;
    }
    if (wp_script_is('wp-editor', 'enqueued')) {
        _doing_it_wrong('wp_enqueue_script()', sprintf(__('"%1$s" script should not be enqueued together with the new widgets editor (%2$s or %3$s).'), 'wp-editor', 'wp-edit-widgets', 'wp-customize-widgets'), '5.8.0');
    }
    if (wp_style_is('wp-edit-post', 'enqueued')) {
        _doing_it_wrong('wp_enqueue_style()', sprintf(__('"%1$s" style should not be enqueued together with the new widgets editor (%2$s or %3$s).'), 'wp-edit-post', 'wp-edit-widgets', 'wp-customize-widgets'), '5.8.0');
    }
}

/**
 * The stored widget lists made to fit the registered sidebars and widgets,
 * and stored (probe widgets-remaining). Before widgets_init every stored
 * widget would look unknown, so the lists are only given the missing
 * sidebars then, and nothing is stored.
 */
function retrieve_widgets($theme_changed = false)
{
    $stored = wp_get_sidebars_widgets();
    $areas = array_keys($GLOBALS['wp_registered_sidebars'] ?? []);
    if (!did_action('widgets_init')) {
        return $stored + ['wp_inactive_widgets' => []] + array_fill_keys($areas, []);
    }
    $sidebars = WidgetAreas::retrieve($stored, $areas, array_keys($GLOBALS['wp_registered_widgets'] ?? []));
    wp_set_sidebars_widgets($sidebars);
    return $sidebars;
}

/** The number after the highest of a widget base's registered instances (1 when there are none), plus one. */
function next_widget_id_number($id_base)
{
    $number = 1;
    foreach (array_keys((array) ($GLOBALS['wp_registered_widgets'] ?? [])) as $widget_id) {
        if (preg_match('/' . preg_quote((string) $id_base, '/') . '-([0-9]+)$/', (string) $widget_id, $m)) {
            $number = max($number, (int) $m[1]);
        }
    }
    return $number + 1;
}

/** Deprecated since 2.8: wp_register_sidebar_widget, the name made into the id. */
function register_sidebar_widget($name, $output_callback, $classname = '', ...$params)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'wp_register_sidebar_widget()');
    $name = is_array($name) ? (count($name) === 3 ? sprintf($name[0], $name[2]) : $name[0]) : $name;
    wp_register_sidebar_widget(sanitize_title($name), $name, $output_callback, !empty($classname) && is_string($classname) ? ['classname' => $classname] : [], ...$params);
}

/** Deprecated since 2.8: wp_register_widget_control, the name made into the id. */
function register_widget_control($name, $control_callback, $width = '', $height = '', ...$params)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'wp_register_widget_control()');
    $name = is_array($name) ? (count($name) === 3 ? sprintf($name[0], $name[2]) : $name[0]) : $name;
    wp_register_widget_control(sanitize_title($name), $name, $control_callback, array_filter(['width' => $width, 'height' => $height]), ...$params);
}
