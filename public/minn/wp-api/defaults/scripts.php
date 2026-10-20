<?php
// The script handles the reference registers itself and plugin code depends on.
// The engine ships the MIT-licensed libraries under minn/assets and serves them
// from that folder so hosts can read the files off disk.

// The registry is told wp_default_scripts as it is made (and again at init 0), as the reference's is: the handles
// register then, so plugins find them in $wp_scripts->registered from the start.
add_action('wp_default_scripts', 'wp_default_scripts');
add_action('wp_default_styles', 'wp_default_styles');

// The $wp_scripts and $wp_styles globals plugins read directly exist from the start. apiFetch is told
// where the REST API is, with a nonce, on each registry's first real use (_minn_tell_api_fetch), as the
// reference tells a registry as it is made: a request that never touches scripts makes no nonce.
if (Minn\Runtime\Runtime::booted()) {
    wp_scripts();
    wp_styles();
    Minn\Runtime\Runtime::current()->set('api_fetch_told', false);
    // The engine's own queueing and rendering use the registry as wp_enqueue_script and WP_Block::render do.
    Minn\Runtime\AssetQueue::onFirstUse(static function (): void {
        wp_scripts();
    });
}
