<?php
/**
 * Fixture mu-plugin for tests/security-sweep.test.php, staged in both
 * stacks' mu-plugin folders while the suite runs: on POST
 * ?minn_test_upload_overrides=1, media_handle_upload with overrides that
 * allow JPEG only, answered as JSON (the error, or that it attached, and
 * which upload prefilters were heard; the attachment removed again).
 */
if (!isset($_GET['minn_test_upload_overrides']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
add_action('init', static function (): void {
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    $heard = [];
    foreach (['wp_handle_upload_prefilter', 'wp_handle_sideload_prefilter'] as $hook) {
        add_filter($hook, static function ($file) use (&$heard, $hook) {
            $heard[] = $hook;
            return $file;
        });
    }
    $id = media_handle_upload('file', 0, [], ['test_form' => false, 'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg']]);
    $out = is_wp_error($id) ? ['error' => $id->get_error_message()] : ['attached' => true];
    if (is_int($id) && $id > 1) {
        wp_delete_attachment($id, true);
    }
    wp_send_json($out + ['heard' => $heard]);
}, 1);
