<?php
/**
 * Plugin Name: Minn Test WP API
 * Description: Says where it ran, for tests/wp-api-modes.test.php (MINN_WP_API). Linked in while that suite runs.
 */

add_action('wp_footer', static function (): void {
    echo "<!-- zz-wp-api footer -->\n";
});
add_action('wp_ajax_nopriv_zz_wp_api', static function (): void {
    echo 'zz-wp-api ajax';
    exit;
});
add_action('rest_api_init', static function (): void {
    register_rest_route('zz-wp-api/v1', '/ping', ['methods' => 'GET', 'callback' => static fn (): string => 'pong', 'permission_callback' => '__return_true']);
});
add_action('zz_wp_api_event', static function (): void {
    update_option('zz_wp_api_event_ran', (string) time(), false);
});
