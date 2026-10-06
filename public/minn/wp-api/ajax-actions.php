<?php
/**
 * The reference's own admin-ajax.php actions that work outside wp-admin:
 * the heartbeat a signed-out page may keep, and the REST nonce refresh.
 * Runtime\AjaxController registers them for each request.
 */

/** The signed-out heartbeat: what the page sent, the plugins' answers, and the server time, as JSON. */
function wp_ajax_nopriv_heartbeat()
{
    $data = isset($_POST['data']) ? (array) wp_unslash($_POST['data']) : [];
    $screen = empty($_POST['screen_id']) ? 'front' : sanitize_key($_POST['screen_id']);
    $response = [];
    if ($data !== []) {
        $response = apply_filters('heartbeat_nopriv_received', $response, $data, $screen);
    }
    $response = apply_filters('heartbeat_nopriv_send', $response, $screen);
    do_action('heartbeat_nopriv_tick', $response, $screen);
    $response['server_time'] = time();
    wp_send_json($response);
}

/** A fresh nonce for the REST API, for the signed-in visitor whose page asked. */
function wp_ajax_rest_nonce()
{
    exit(wp_create_nonce('wp_rest'));
}
