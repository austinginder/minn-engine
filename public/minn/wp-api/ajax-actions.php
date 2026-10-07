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

/** The heartbeat of a signed-in page: refused without its nonce, otherwise answered as Minn\Runtime\Heartbeat describes. */
function wp_ajax_heartbeat()
{
    if (empty($_POST['_nonce'])) {
        wp_send_json_error();
    }
    wp_send_json(Minn\Runtime\Heartbeat::answer((array) wp_unslash($_POST)));
}

/** Fresh REST and heartbeat nonces, for a page whose heartbeat nonce has gone stale. */
function wp_refresh_heartbeat_nonces($response)
{
    $response['rest_nonce'] = wp_create_nonce('wp_rest');
    $response['heartbeat_nonce'] = wp_create_nonce('heartbeat-nonce');
    return $response;
}

/** A fresh nonce for the REST API, for the signed-in visitor whose page asked. */
function wp_ajax_rest_nonce()
{
    exit(wp_create_nonce('wp_rest'));
}
