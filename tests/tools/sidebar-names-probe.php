<?php
/**
 * A sidebar asked for by its name, as the reference finds it (probe
 * sidebar-names): Enfold and others call dynamic_sidebar('Sidebar Blog')
 * for a sidebar registered with the id av_blog. dynamic_sidebar() and
 * is_active_sidebar() by id, by name, by a name in another case or spacing,
 * by a number, and by a name nothing has. The probe's sidebars and widget
 * assignments are put back on the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$saved = get_option('sidebars_widgets');
register_shutdown_function(static function () use ($saved): void {
    update_option('sidebars_widgets', $saved);
});
wp_register_sidebar_widget('zz-probe-widget', 'Zz Probe Widget', static function ($args): void {
    echo $args['before_widget'] . 'zz' . $args['after_widget'];
});
register_sidebar(['name' => 'Zz Probe Blog', 'id' => 'zz_probe_blog', 'before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>', 'before_title' => '<h3>', 'after_title' => '</h3>']);
register_sidebar(['name' => 'Zz Probe Empty', 'id' => 'zz_probe_empty']);
register_sidebar(['name' => 'Zz Probe Numbered', 'id' => 'sidebar-77', 'before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>']);
$sidebars = (array) $saved;
$sidebars['zz_probe_blog'] = ['zz-probe-widget'];
$sidebars['sidebar-77'] = ['zz-probe-widget'];
$sidebars['zz_probe_empty'] = [];
wp_set_sidebars_widgets($sidebars);
// Read once, the assignments are kept for the request: start from the ones just set.
unset($GLOBALS['_wp_sidebars_widgets']);
$print = static function ($index): array {
    ob_start();
    $had = dynamic_sidebar($index);
    $html = (string) ob_get_clean();
    preg_match_all('/<section id="([^"]+)"/', $html, $m);
    return [$had, $m[1], strlen($html) > 0];
};
foreach (['zz_probe_blog', 'Zz Probe Blog', 'zz probe blog', 'ZZ PROBE BLOG', 'Zz-Probe-Blog', 'Zz Probe Empty', 'zz_probe_empty', 77, '77', 'Zz Probe Numbered', 'No Such Sidebar'] as $index) {
    $say('dynamic_sidebar(' . var_export($index, true) . ')', $print($index));
    $say('is_active_sidebar(' . var_export($index, true) . ')', is_active_sidebar($index));
}
unregister_sidebar('zz_probe_blog');
unregister_sidebar('zz_probe_empty');
unregister_sidebar('sidebar-77');
wp_unregister_sidebar_widget('zz-probe-widget');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
