<?php
/**
 * The rest of widgets.php (probe widgets-remaining): sidebars registered in
 * numbers (names with and without a %d, ids given or not), sidebar checks,
 * an old-style widget registered by callback, widget id bases, the stored
 * widget lists set and read back (active widgets by callback, id and base,
 * dynamic sidebars), single widget settings converted to the multi-widget
 * shape, the widget defaults, the RSS widget's form, and the widgets
 * retrieved (unknown ones dropped, lost ones made inactive) with the theme
 * the same and when the sidebars change. The stored widget lists, the
 * theme's mods and the probe's widget option are put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$mods = 'theme_mods_' . get_stylesheet();
$saved = [];
foreach (['sidebars_widgets', 'widget_zz_wc', 'widget_zz_multi', $mods] as $name) {
    $saved[$name] = get_option($name, null);
}
register_shutdown_function(static function () use ($saved): void {
    foreach ($saved as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
});
$sidebar = static function (string $id): ?array {
    $row = $GLOBALS['wp_registered_sidebars'][$id] ?? null;
    if ($row === null) {
        return null;
    }
    ksort($row);
    return $row;
};

register_sidebars(2, ['name' => 'ZZ Bar %d', 'id' => 'zz-bar']);
register_sidebars(3, ['name' => 'ZZ Many', 'id' => 'zz-many', 'class' => 'zz-class']);
register_sidebars(1, ['name' => 'ZZ Solo %d', 'id' => 'zz-solo']);
register_sidebars(2, 'name=ZZ String&id=zz-string&before_widget=<li>');
$say('sidebars registered in numbers', array_map($sidebar, ['zz-bar', 'zz-bar-2', 'zz-bar-3', 'zz-many', 'zz-many-2', 'zz-many-3', 'zz-solo', 'zz-string', 'zz-string-2']));
$say('sidebar checks', [is_registered_sidebar('zz-bar'), is_registered_sidebar('zz-bar-2'), is_registered_sidebar('zz-nope'), is_registered_sidebar(''), is_registered_sidebar('wp_inactive_widgets')]);

function zz_wr_widget_output($args)
{
    echo 'zz output';
}
wp_register_sidebar_widget('zz-wdg-4', 'ZZ Widget', 'zz_wr_widget_output', ['classname' => 'zz-classname', 'description' => 'ZZ description'], 'extra one', 'extra two');
wp_register_sidebar_widget('zz-plain', 'ZZ Plain', 'zz_wr_widget_output');
wp_register_sidebar_widget('zz-gone', 'ZZ Gone', 'zz_wr_widget_output');
wp_register_sidebar_widget('zz-gone', 'ZZ Gone', '');
$widget = static function (string $id): ?array {
    $row = $GLOBALS['wp_registered_widgets'][$id] ?? null;
    if ($row === null) {
        return null;
    }
    $row['callback'] = is_string($row['callback'] ?? null) ? $row['callback'] : gettype($row['callback'] ?? null);
    ksort($row);
    return $row;
};
$say('an old-style widget', [$widget('zz-wdg-4'), $widget('zz-plain'), $widget('zz-gone')]);
$say('widget id bases', array_map(static fn ($id) => _get_widget_id_base($id), ['text-3', 'zz-wdg-4', 'nodash', 'a-b-12', 'block-0', 'trailing-', '-7', 'x-y-z']));

$before = is_dynamic_sidebar();
wp_set_sidebars_widgets(['wp_inactive_widgets' => ['zz-plain'], 'zz-bar' => ['zz-wdg-4'], 'zz-many' => [], 'array_version' => 3]);
$say('widget lists set and read back', [
    $before, is_dynamic_sidebar(), wp_get_sidebars_widgets(), get_option('sidebars_widgets'),
    is_active_widget('zz_wr_widget_output'), is_active_widget(false, 'zz-wdg-4'), is_active_widget(false, false, 'zz-wdg'), is_active_widget(false, 'zz-plain'), is_active_widget(false, 'zz-plain', false, false),
    is_active_widget(false, 'text-999'), is_active_widget(false, false, 'zz-nope'), is_active_widget(false, false, 'zz-plain', false),
]);

$say('settings converted', [
    wp_convert_widget_settings('zz_wc', 'widget_zz_wc', ['title' => 'Old single']), get_option('widget_zz_wc'),
    wp_convert_widget_settings('zz_multi', 'widget_zz_multi', [3 => ['title' => 'Three'], '_multiwidget' => 1]), get_option('widget_zz_multi', 'unset'),
    wp_convert_widget_settings('zz_multi', 'widget_zz_multi', [2 => ['title' => 'Two'], 5 => ['title' => 'Five']]), get_option('widget_zz_multi', 'unset'),
    wp_convert_widget_settings('zz_multi', 'widget_zz_multi', []), get_option('widget_zz_multi', 'unset'),
]);

$defaults = wp_get_widget_defaults();
ksort($defaults);
$say('widget defaults', [array_keys($defaults), $defaults['zz-bar'] ?? 'absent', $defaults['text'] ?? 'absent', $defaults['zz-wdg-4'] ?? 'absent']);

ob_start();
wp_widget_rss_form(['number' => 2, 'title' => 'ZZ "feed" & co', 'url' => 'https://zz.example/feed?a=1&b=2', 'items' => 5, 'error' => false, 'show_summary' => 1, 'show_author' => 0, 'show_date' => 1]);
$form = (string) ob_get_clean();
ob_start();
wp_widget_rss_form(['number' => 7, 'title' => '', 'url' => '', 'items' => 25, 'error' => 'zz failed'], ['url' => true, 'title' => false, 'items' => true, 'show_summary' => false, 'show_author' => true, 'show_date' => false]);
$say('the RSS form', [$form, (string) ob_get_clean()]);

// The site's own block widgets sit in a registered sidebar, so only the probe's widgets can be lost, and they
// are left out of what is printed. The lists are read back after each write: retrieve_widgets works from the
// lists as last read.
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$site = array_values(array_filter(array_keys($GLOBALS['wp_registered_widgets']), static fn ($id) => (bool) preg_match('/^block-\d+$/', (string) $id)));
$strip = static fn ($lists) => array_map(static fn ($ids) => is_array($ids) ? array_values(array_diff($ids, $site)) : $ids, (array) $lists);
wp_register_sidebar_widget('zz-lost-8', 'ZZ Lost 8', 'zz_wr_widget_output');
wp_register_sidebar_widget('zz-lost-7', 'ZZ Lost 7', 'zz_wr_widget_output');
wp_register_sidebar_widget('zz-first-1', 'ZZ First 1', 'zz_wr_widget_output');
wp_set_sidebars_widgets(['wp_inactive_widgets' => ['zz-unknown-3', 'zz-plain'], 'zz-bar' => array_merge(['zz-unknown-9', 'zz-wdg-4'], $site), 'zz-gone-area' => ['zz-lost-7', 'zz-unknown-5'], 'array_version' => 3]);
wp_get_sidebars_widgets();
$retrieved = retrieve_widgets();
$say('widgets retrieved, the theme the same', [$strip($retrieved), $strip(get_option('sidebars_widgets'))]);
wp_set_sidebars_widgets(['wp_inactive_widgets' => [], 'zz-many' => array_merge(['zz-plain'], $site), 'zz-gone-area' => ['zz-wdg-4'], 'array_version' => 3]);
wp_get_sidebars_widgets();
_wp_sidebars_changed();
$say('the sidebars changed', [$strip(wp_get_sidebars_widgets()), $strip(get_option('sidebars_widgets'))]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
