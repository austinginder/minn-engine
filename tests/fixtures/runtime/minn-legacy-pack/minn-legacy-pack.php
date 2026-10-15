<?php
/**
 * Plugin Name: Minn Legacy Pack (test stand-in)
 * Description: Stands in for the Minn Legacy Pack in suite ajax: active, it tells the engine that WordPress's browser files are there (it carries none of them) and answers the media modal's upload with its nonce checked.
 * Version: 0.0.0
 */

if (!defined('MINN_ENGINE_VERSION')) {
    return;
}

add_action('wp_ajax_upload-attachment', static function (): void {
    check_ajax_referer('media-form');
    echo 'stand-in upload';
    wp_die();
}, 1);
