<?php
/**
 * The widgets helper functions as the reference answers them, the ones the
 * widget routes and plugins build on: parsing a widget id, a sidebar by id
 * (a registered one, the inactive widgets, none), finding and assigning a
 * widget's sidebar, a widget rendered in
 * its sidebar and its settings form, and retrieve_widgets. Same protocol
 * as api-probe.php; the widget options are put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$kept = ['sidebars_widgets' => get_option('sidebars_widgets', null), 'widget_search' => get_option('widget_search', null)];
register_sidebar(['id' => 'zz-probe-side', 'name' => 'Probe Side', 'before_widget' => '<aside id="%1$s" class="widget %2$s">', 'after_widget' => '</aside>', 'before_title' => '<h3>', 'after_title' => '</h3>']);
$say('parse ids', array_map('wp_parse_widget_id', ['search-2', 'block-12', 'foo', 'media_image-3', 'a-b-c-4', '-5']));
$say('sidebars', [wp_get_sidebar('zz-probe-side'), wp_get_sidebar('wp_inactive_widgets'), wp_get_sidebar('zz-nope')]);
update_option('widget_search', [5 => ['title' => 'Helper search'], '_multiwidget' => 1]);
wp_assign_widget_to_sidebar('search-5', 'zz-probe-side');
$say('assigned', [wp_find_widgets_sidebar('search-5'), wp_get_sidebars_widgets()['zz-probe-side'] ?? null, wp_find_widgets_sidebar('search-99')]);
$say('rendered', [wp_render_widget('search-5', 'zz-probe-side'), wp_render_widget('search-5', 'wp_inactive_widgets'), wp_render_widget('search-99', 'zz-probe-side')]);
$say('control', [wp_render_widget_control('search-5'), wp_render_widget_control('search-99')]);
wp_assign_widget_to_sidebar('search-5', 'wp_inactive_widgets');
$say('moved', [wp_find_widgets_sidebar('search-5'), wp_get_sidebars_widgets()['zz-probe-side'] ?? null, array_slice(wp_get_sidebars_widgets()['wp_inactive_widgets'] ?? [], -1)]);
wp_assign_widget_to_sidebar('search-5', '');
$say('unassigned', [wp_find_widgets_sidebar('search-5'), in_array('search-5', wp_get_sidebars_widgets()['wp_inactive_widgets'] ?? [], true)]);
$retrieved = retrieve_widgets();
$say('retrieve', [array_keys($retrieved), $retrieved['zz-probe-side'] ?? null]);
foreach ($kept as $option => $value) {
    $value === null ? delete_option($option) : update_option($option, $value);
}
unregister_sidebar('zz-probe-side');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
