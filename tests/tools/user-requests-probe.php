<?php
/**
 * Personal data requests as the reference keeps them (probe user-requests):
 * a request created (for a stranger, for a user, already confirmed, and the
 * refusals: a bad address, an unknown action, a duplicate), read back as a
 * WP_User_Request, given a key, the key checked (right, wrong, missing,
 * expired), the confirmation mail sent (caught by pre_wp_mail, the link's
 * key masked), the action described, the request confirmed with its
 * message and the notice to the site owner. The probe's requests and user,
 * removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        wp_delete_user($id);
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace([home_url(), site_url(), admin_url()], ['{home}', '{site}', '{admin}'], $value);
    $value = (string) preg_replace('/confirm_key=[A-Za-z0-9]+/', 'confirm_key={key}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$mail = [];
$catch = static function ($return, $atts) use (&$mail) {
    $mail[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $catch, 10, 2);
$errorOf = static fn ($result) => is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message()] : $result;
$read = static function ($id) use ($mask): mixed {
    $request = wp_get_user_request($id);
    if (!$request instanceof WP_User_Request) {
        return $request;
    }
    return $mask([
        'class' => get_class($request), 'ID' => $request->ID, 'user_id' => $request->user_id, 'email' => $request->email, 'action_name' => $request->action_name,
        'status' => $request->status, 'request_data' => $request->request_data, 'confirm_key' => $request->confirm_key === '' ? '' : 'set',
        'timestamps' => [$request->created_timestamp > 0, $request->modified_timestamp > 0, $request->confirmed_timestamp > 0, $request->completed_timestamp > 0],
    ]);
};

$stranger = wp_create_user_request('zz-stranger@example.com', 'export_personal_data', ['source' => 'zz']);
// Only a real id is kept: (int) of an error is 1, and the cleanup would delete that post.
$ids[is_int($stranger) ? $stranger : 0] = 'stranger';
$made['posts'][] = is_int($stranger) ? $stranger : 0;
$say('created for a stranger', $mask($stranger));
$post = get_post($stranger);
$say('the request post', $mask([$post->post_type, $post->post_status, $post->post_title, $post->post_name, $post->post_content, $post->post_author, $post->post_password]));
$say('read back', $read($stranger));

$user = (int) wp_insert_user(['user_login' => 'zz_requester', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-requester@example.com']);
$made['users'][] = $user;
$ids[$user] = 'user';
$forUser = wp_create_user_request('zz-requester@example.com', 'remove_personal_data', [], 'confirmed');
$ids[is_int($forUser) ? $forUser : 0] = 'for user';
$made['posts'][] = is_int($forUser) ? $forUser : 0;
$say('created for a user, confirmed', $read($forUser));
$say('a bad address', $errorOf(wp_create_user_request('not an email', 'export_personal_data')));
$say('an unknown action', $errorOf(wp_create_user_request('zz-x@example.com', '')));
$say('a duplicate', $errorOf(wp_create_user_request('zz-stranger@example.com', 'export_personal_data')));
$say('a bad status', $errorOf(wp_create_user_request('zz-y@example.com', 'export_personal_data', [], 'nope')));
$say('wp_get_user_request of a post', wp_get_user_request(1));
$say('wp_get_user_request of nothing', wp_get_user_request(0));

// The key.
$key = wp_generate_user_request_key($stranger);
$say('the key', [strlen($key), ctype_alnum($key), get_post($stranger)->post_status, get_post($stranger)->post_password !== '' && get_post($stranger)->post_password !== $key]);
$say('the right key', $errorOf(wp_validate_user_request_key($stranger, $key)));
$say('a wrong key', $errorOf(wp_validate_user_request_key($stranger, 'wrongwrongwrongwrong')));
$say('no key', $errorOf(wp_validate_user_request_key($stranger, '')));
// The reference reads the missing request's properties before it refuses (two warnings), then says the request is invalid.
set_error_handler(static fn () => true, E_WARNING);
$say('no request', $errorOf(wp_validate_user_request_key(999999999, $key)));
restore_error_handler();
$expire = static fn () => -1;
add_filter('user_request_key_expiration', $expire);
$say('an expired key', $errorOf(wp_validate_user_request_key($stranger, $key)));
remove_filter('user_request_key_expiration', $expire);

// The mail that asks the requester to confirm.
$heard = [];
foreach (['user_request_action_email_content', 'user_request_action_email_subject', 'user_request_action_email_headers'] as $hook) {
    add_filter($hook, static function ($value, ...$rest) use (&$heard, $hook) {
        $heard[] = [$hook, count($rest), array_keys((array) end($rest))];
        return $value;
    }, 10, 3);
}
$say('wp_send_user_request', $errorOf(wp_send_user_request($stranger)));
$say('the mail', $mask($mail));
$say('the mail filters heard', $heard);
$say('wp_send_user_request for a post', $errorOf(wp_send_user_request(1)));
$say('after sending', $read($stranger));
$mail = [];

foreach (['export_personal_data', 'remove_personal_data', 'zz_custom'] as $action) {
    $say("wp_user_request_action_description {$action}", wp_user_request_action_description($action));
}

// Confirming.
_wp_privacy_account_request_confirmed($stranger);
$say('confirmed', $read($stranger));
$say('the confirmed timestamp meta', (bool) get_post_meta($stranger, '_wp_user_request_confirmed_timestamp', true));
$say('the confirmed message', $mask(_wp_privacy_account_request_confirmed_message($stranger)));
$say('the confirmed message for an erasure', $mask(_wp_privacy_account_request_confirmed_message($forUser)));
$say('a custom action', $errorOf(wp_create_user_request('zz-custom@example.com', 'zz_custom_action')));
$say('the confirmed message for no request', $mask(_wp_privacy_account_request_confirmed_message(1)));
$filtered = static fn ($message, $id) => $message . ' [zz]';
add_filter('user_request_action_confirmed_message', $filtered, 10, 2);
$say('the confirmed message, filtered', $mask(_wp_privacy_account_request_confirmed_message($stranger)));
remove_filter('user_request_action_confirmed_message', $filtered, 10);
$say('the hooks', [has_action('user_request_action_confirmed', '_wp_privacy_account_request_confirmed'), has_action('user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification'), _wp_privacy_action_request_types()]);
_wp_privacy_account_request_confirmed($stranger);
$say('confirming twice keeps it confirmed', $read($stranger));
_wp_privacy_send_request_confirmation_notification($stranger);
$say('the notice to the owner', $mask($mail));
$say('the notice meta', (bool) get_post_meta($stranger, '_wp_admin_notified', true));
$mail = [];
_wp_privacy_send_request_confirmation_notification($stranger);
$say('a second notice', $mail);
remove_filter('pre_wp_mail', $catch, 10);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
