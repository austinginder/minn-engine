<?php
/**
 * More widget helpers (probe widgets-more): a sidebar's description through
 * kses, widget ids taken out of sidebars when they are not registered (or
 * not in the list given), the classic sidebars a block theme keeps
 * registered, and the notice when the old editor script is queued beside
 * the widgets editor. Same protocol as api-probe.php; it writes nothing.
 */

// A loaded site is past init, as the reference is under WP-CLI; the engine's probe runner skips it, so the asset functions would warn as if called too early.
$GLOBALS['wp_actions']['init'] = max(1, (int) ($GLOBALS['wp_actions']['init'] ?? 0));
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_registered_sidebars, $wp_registered_widgets;
wp_scripts();
wp_styles();
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');

register_sidebar(['id' => 'zz-main', 'name' => 'Zz Main Sidebar', 'description' => 'The <b>main</b> & "only" one']);
register_sidebar(['id' => 'zz-footer-1', 'name' => 'Zz Footer One']);
register_sidebar(['id' => 'zz-d', 'name' => 'Zz D', 'description' => '<script>x()</script><a href="https://zz.example/" onclick="y()">link</a> <p>para</p> <em>em</em> &amp; & "q" \'s\'']);
$wp_registered_sidebars['zz-nodesc'] = ['id' => 'zz-nodesc', 'name' => 'No desc'];
$say('a sidebar\'s description', [wp_sidebar_description('zz-main'), wp_sidebar_description('zz-footer-1'), wp_sidebar_description('zz-d'), wp_sidebar_description('zz-nodesc'), wp_sidebar_description('zz-never'), wp_sidebar_description(0)]);

// Only widget ids the probe registers itself count, so the site's own widgets do not matter.
$wp_registered_widgets['zz-known-2'] = ['id' => 'zz-known-2', 'name' => 'Zz Known', 'callback' => '__return_empty_string'];
$wp_registered_widgets['zz-known-3'] = ['id' => 'zz-known-3', 'name' => 'Zz Known', 'callback' => '__return_empty_string'];
$say('widgets not registered taken out', [
    _wp_remove_unregistered_widgets(['zz-main' => ['zz-known-2', 'zz-never-5', 'zz-known-3'], 'wp_inactive_widgets' => ['zz-gone-1', 'zz-known-2'], 'array_version' => 3]),
    _wp_remove_unregistered_widgets(['zz-main' => ['zz-known-2', 'zz-never-5']], ['zz-never-5']),
    _wp_remove_unregistered_widgets(['zz-main' => ['zz-known-2', 'zz-x-1']], ['zz-known-2', 'zz-x-1']),
    _wp_remove_unregistered_widgets(['s' => ['5', 5, 'zz-known-2']], ['5']),
    _wp_remove_unregistered_widgets(['zz-main' => 'notarray', 'x' => [1, 'zz-known-2']]),
    _wp_remove_unregistered_widgets([]),
]);

$say('classic sidebars a block theme keeps', [
    has_action('widgets_init', '_wp_block_theme_register_classic_sidebars'),
    wp_is_block_theme(),
    (static function () {
        global $wp_registered_sidebars;
        $classic = static fn () => [
            'zz-classic' => ['id' => 'zz-classic', 'name' => 'Zz Classic', 'description' => 'old', 'class' => '', 'before_widget' => '<li>', 'after_widget' => '</li>', 'before_title' => '<h2>', 'after_title' => '</h2>', 'before_sidebar' => '', 'after_sidebar' => '', 'show_in_rest' => false],
            'zz-main' => ['id' => 'zz-main', 'name' => 'Overridden'],
            'zz-key' => ['id' => 'zz-other', 'name' => 'Keyed apart'],
        ];
        add_filter('theme_mod_wp_classic_sidebars', $classic);
        $out = [_wp_block_theme_register_classic_sidebars(), array_keys($wp_registered_sidebars), $wp_registered_sidebars['zz-classic'] ?? null, $wp_registered_sidebars['zz-main'] ?? null, $wp_registered_sidebars['zz-key'] ?? null, $wp_registered_sidebars['zz-other'] ?? null];
        remove_filter('theme_mod_wp_classic_sidebars', $classic);
        return $out;
    })(),
    (static function () {
        global $wp_registered_sidebars;
        $before = array_keys($wp_registered_sidebars);
        $none = static fn () => 'notarray';
        add_filter('theme_mod_wp_classic_sidebars', $none);
        _wp_block_theme_register_classic_sidebars();
        remove_filter('theme_mod_wp_classic_sidebars', $none);
        return array_keys($wp_registered_sidebars) === $before;
    })(),
]);

// The handles are the reference's own; where the engine has not registered them, stand-ins do.
foreach (['wp-edit-widgets', 'wp-customize-widgets', 'wp-editor', 'wp-edit-post'] as $handle) {
    if (!wp_script_is($handle, 'registered')) {
        wp_register_script($handle, false);
    }
    if (!wp_style_is($handle, 'registered')) {
        wp_register_style($handle, false);
    }
}
$deps = static function (array $scripts, array $styles) use (&$wrong): array {
    $wrong = [];
    array_map('wp_enqueue_script', $scripts);
    array_map('wp_enqueue_style', $styles);
    $out = [wp_check_widget_editor_deps(), $wrong];
    array_map('wp_dequeue_script', $scripts);
    array_map('wp_dequeue_style', $styles);
    return $out;
};
$say('the old editor beside the widgets editor', [
    has_action('admin_head', 'wp_check_widget_editor_deps'),
    $deps([], []),
    $deps(['wp-edit-widgets', 'wp-editor'], []),
    $deps(['wp-customize-widgets', 'wp-editor'], []),
    $deps(['wp-editor'], []),
    $deps(['wp-edit-widgets'], []),
    $deps([], ['wp-edit-widgets', 'wp-edit-post']),
    $deps([], ['wp-customize-widgets', 'wp-edit-post']),
    $deps(['wp-edit-widgets'], ['wp-edit-post']),
    $deps(['wp-edit-widgets', 'wp-editor'], ['wp-edit-widgets', 'wp-edit-post']),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
