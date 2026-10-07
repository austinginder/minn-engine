<?php
// The script handles the reference registers itself and plugin code depends on.
// The engine ships the MIT-licensed libraries under minn/assets and serves them
// from that folder so hosts can read the files off disk.

// The registry is told wp_default_scripts as it is made (and again at init 0), as the reference's is: the handles
// register then, so plugins find them in $wp_scripts->registered from the start.
add_action('wp_default_scripts', 'wp_default_scripts');

add_action('init', static function (): void {
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

}, 0);

// The $wp_scripts and $wp_styles globals plugins read directly exist from the start.
if (Minn\Runtime\Runtime::booted()) {
    wp_scripts();
    wp_styles();
}
