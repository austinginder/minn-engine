<?php
// The script handles the reference registers itself and plugin code depends on.
// The engine ships the MIT-licensed libraries under minn/assets and serves them
// from that folder so hosts can read the files off disk.

add_action('init', static function (): void {
    wp_register_script('jquery-core', '/minn/assets/vendor/jquery/jquery.min.js', [], '3.7.1');
    wp_register_script('jquery-migrate', '/minn/assets/vendor/jquery/jquery-migrate.min.js', [], '3.4.1');
    // The reference's jquery alias depends on jquery-core alone; migrate stays registered for code that asks for it.
    wp_register_script('jquery', false, ['jquery-core'], '3.7.1');
    // The wp.* utility packages the engine reimplements (MIT, assets/wp), with the reference's dependency graph.
    $wp = static fn (string $file) => '/minn/assets/wp/' . $file . '.js';
    wp_register_script('wp-polyfill', $wp('polyfill'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-hooks', $wp('hooks'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-i18n', $wp('i18n'), ['wp-hooks'], MINN_ENGINE_VERSION);
    wp_register_script('wp-dom-ready', $wp('dom-ready'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-escape-html', $wp('escape-html'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-url', $wp('url'), ['wp-polyfill'], MINN_ENGINE_VERSION);
    wp_register_script('wp-html-entities', $wp('html-entities'), [], MINN_ENGINE_VERSION);
    wp_register_script('wp-a11y', $wp('a11y'), ['wp-dom-ready', 'wp-i18n', 'wp-polyfill'], MINN_ENGINE_VERSION);
    wp_register_script('wp-api-fetch', $wp('api-fetch'), ['wp-i18n', 'wp-url'], MINN_ENGINE_VERSION);
    // apiFetch reaches the REST API only once it is told where it is and
    // which nonce to send. Without the root middleware a caller resolves
    // its own path against the site root, so a namespace like
    // /wc/store/v1/cart is requested with no /wp-json/ in front of it.
    wp_add_inline_script(
        'wp-api-fetch',
        sprintf('wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( "%s" ) );', esc_url_raw(get_rest_url())) . "\n"
        . sprintf('wp.apiFetch.nonceMiddleware = wp.apiFetch.createNonceMiddleware( "%s" );', wp_create_nonce('wp_rest')) . "\n"
        . 'wp.apiFetch.use( wp.apiFetch.nonceMiddleware );' . "\n"
        . 'wp.apiFetch.use( wp.apiFetch.mediaUploadMiddleware );' . "\n"
        . sprintf('wp.apiFetch.nonceEndpoint = "%s";', admin_url('admin-ajax.php?action=rest-nonce')),
        'after'
    );

    // The site's own script pack (wp-content/minn-packages/wp-scripts): the
    // GPL packages the engine does not reimplement, installed by the site
    // owner, never shipped in minn/. The engine's MIT packages above are
    // already registered, so they win; the pack only fills the gaps.
    $contentDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    foreach (Minn\Runtime\ScriptPack::handles($contentDir) as $handle => $row) {
        if (wp_script_is($handle, 'registered')) {
            continue;
        }
        wp_register_script(
            $handle,
            content_url(Minn\Runtime\ScriptPack::RELATIVE_DIR . '/' . $row['file']),
            $row['deps'],
            $row['ver']
        );
    }
}, 0);

// The $wp_scripts and $wp_styles globals plugins read directly exist from the start.
if (Minn\Runtime\Runtime::booted()) {
    wp_scripts();
    wp_styles();
}
