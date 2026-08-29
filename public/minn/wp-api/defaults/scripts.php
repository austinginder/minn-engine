<?php
// The script handles the reference registers itself and plugin code depends on.
// The engine ships the MIT-licensed libraries under assets/vendor and serves them
// at the reference's paths so plugin markup stays byte-identical.

add_action('init', static function (): void {
    wp_register_script('jquery-core', '/wp-includes/js/jquery/jquery.min.js', [], '3.7.1');
    wp_register_script('jquery-migrate', '/wp-includes/js/jquery/jquery-migrate.min.js', [], '3.4.1');
    wp_register_script('jquery', false, ['jquery-core', 'jquery-migrate'], '3.7.1');
    // The wp.* utility packages the engine reimplements (MIT, assets/wp), with the reference's dependency graph.
    $wp = static fn (string $file) => '/minn-engine/wp-' . $file . '.js';
    wp_register_script('wp-polyfill', $wp('polyfill'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-hooks', $wp('hooks'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-i18n', $wp('i18n'), ['wp-hooks'], MINN_ENGINE_VERSION);
    wp_register_script('wp-dom-ready', $wp('dom-ready'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-escape-html', $wp('escape-html'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-url', $wp('url'), ['wp-polyfill'], MINN_ENGINE_VERSION);
    wp_register_script('wp-html-entities', $wp('html-entities'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-a11y', $wp('a11y'), ['wp-dom-ready', 'wp-i18n', 'wp-polyfill'], MINN_ENGINE_VERSION);
    wp_register_script('wp-api-fetch', $wp('api-fetch'), ['wp-i18n', 'wp-url'], MINN_ENGINE_VERSION);
}, 0);

// The $wp_scripts and $wp_styles globals plugins read directly exist from the start.
if (Minn\Runtime\Runtime::booted()) {
    wp_scripts();
    wp_styles();
}
