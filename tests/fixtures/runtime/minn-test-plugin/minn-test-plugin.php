<?php
/**
 * Plugin Name: Minn runtime probe
 * Description: A WordPress plugin, unchanged, that marks the page from every lifecycle seam the engine fires. The runtime suite switches it on and reads the marks.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MINN_TEST_PLUGIN_FILE', __FILE__);
$GLOBALS['minn_test_plugin_order'] = [];

function minn_test_plugin_mark($hook)
{
    $GLOBALS['minn_test_plugin_order'][] = $hook;
}

foreach (['plugins_loaded', 'init', 'wp_loaded', 'template_redirect'] as $hook) {
    add_action($hook, static function () use ($hook) {
        minn_test_plugin_mark($hook);
    });
}

add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('minn-test-plugin', plugins_url('probe.css', MINN_TEST_PLUGIN_FILE), [], '1.0.0');
    wp_enqueue_script('minn-test-plugin', plugins_url('probe.js', MINN_TEST_PLUGIN_FILE), [], '1.0.0', true);
    wp_localize_script('minn-test-plugin', 'minnTestPlugin', ['home' => home_url('/'), 'admin' => is_admin()]);
});

add_action('wp_head', static function () {
    minn_test_plugin_mark('wp_head');
    echo '<meta name="minn-test-plugin" content="' . esc_attr(implode(',', $GLOBALS['minn_test_plugin_order'])) . '">' . "\n";
}, 5);

add_filter('body_class', static function ($classes) {
    $classes[] = 'minn-test-plugin-body';
    return $classes;
});

add_filter('the_content', static function ($content) {
    return $content . '<p class="minn-test-plugin-content">' . esc_html(get_option('blogname')) . '</p>';
}, 20);

add_shortcode('minn_test', static function ($atts) {
    $atts = shortcode_atts(['word' => 'none'], $atts, 'minn_test');
    return '<span class="minn-test-shortcode">' . esc_html($atts['word']) . '</span>';
});

add_action('wp_footer', static function () {
    echo '<!-- minn-test-plugin footer: ' . esc_html(wp_get_theme()->get('Name')) . ' -->' . "\n";
});
