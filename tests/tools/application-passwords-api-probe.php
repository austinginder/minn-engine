<?php
/**
 * The application password API plugin code calls, as the reference answers
 * it (probe application-passwords-api): WP_Application_Passwords creating
 * (and refusing a nameless or repeated one), listing, finding, naming,
 * recording a use, chunking, deleting one and all, whether the feature is
 * in use; the availability checks; and wp_authenticate_application_password
 * outside and inside an API request (a right password, a wrong one, an
 * unknown user). Each answer as its shape: a password's characters masked,
 * times and ids as kinds; the hooks each call fires. The probe's own
 * passwords on user 1, removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$say('supported', wp_is_application_passwords_supported());
// Both stacks run the probe without HTTPS; the feature is switched on as the reference's test site switches it on.
add_filter('wp_is_application_passwords_available', '__return_true');
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(wp_create_application_password|wp_update_application_password|wp_delete_application_password|application_password_|wp_authenticate_application_password_errors|wp_is_application_passwords_)/', $hook)) {
        $fired[] = $hook;
    }
});
$made = [];
// An answer as its shape: strings of a password's length masked, uuids and times by kind.
$shape = static function ($value) use (&$shape, &$made) {
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code(), 'message' => $value->get_error_message(), 'data' => $value->get_error_data()];
    }
    if ($value instanceof WP_User) {
        return ['user' => $value->ID];
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = match (true) {
                in_array($key, ['uuid', 'app_id'], true) && is_string($item) && preg_match('/^[0-9a-f-]{36}$/', $item) === 1 => array_search($item, $made, true) !== false ? '{uuid ' . array_search($item, $made, true) . '}' : 'uuid',
                in_array($key, ['created', 'last_used'], true) && is_int($item) => 'time',
                $key === 'password' && is_string($item) => 'hash',
                default => $shape($item),
            };
        }
        return $out;
    }
    if (is_string($value) && preg_match('/^[A-Za-z0-9]{24}$/', $value) === 1) {
        return 'a password';
    }
    return $value;
};
$call = static function (string $label, callable $fn) use ($say, $shape, &$fired): mixed {
    $fired = [];
    $result = $fn();
    $say($label, ['result' => $shape($result), 'hooks' => array_values(array_unique($fired))]);
    return $result;
};

$fired = [];
$say('available', [wp_is_application_passwords_available(), array_values(array_unique($fired))]);
$say('available for user 1', wp_is_application_passwords_available_for_user(get_userdata(1)));
$appId = wp_generate_uuid4();
$first = $call('create', static fn () => WP_Application_Passwords::create_new_application_password(1, ['name' => 'Zz Probe App', 'app_id' => $appId]));
if (is_array($first)) {
    $made['first'] = $first[1]['uuid'];
}
$call('create, nameless', static fn () => WP_Application_Passwords::create_new_application_password(1, ['name' => '']));
$repeat = $call('create, a repeated name', static fn () => WP_Application_Passwords::create_new_application_password(1, ['name' => 'Zz Probe App']));
if (is_array($repeat)) {
    $made['repeat'] = $repeat[1]['uuid'];
}
$second = $call('create, a second', static fn () => WP_Application_Passwords::create_new_application_password(1, ['name' => 'Zz Probe Two']));
if (is_array($second)) {
    $made['second'] = $second[1]['uuid'];
}
$say('the chunked password', is_array($first) ? preg_replace('/[A-Za-z0-9]/', 'x', WP_Application_Passwords::chunk_password($first[0])) : null);
$say('list', $shape(array_values(array_filter(WP_Application_Passwords::get_user_application_passwords(1), static fn ($item) => in_array($item['uuid'], $made, true)))));
$call('find one', static fn () => WP_Application_Passwords::get_user_application_password(1, $made['first'] ?? ''));
$call('find none', static fn () => WP_Application_Passwords::get_user_application_password(1, wp_generate_uuid4()));
$say('a name in use', [WP_Application_Passwords::application_name_exists_for_user(1, 'Zz Probe App'), WP_Application_Passwords::application_name_exists_for_user(1, 'Zz Nobody')]);
$call('rename', static fn () => WP_Application_Passwords::update_application_password(1, $made['first'] ?? '', ['name' => 'Zz Probe Renamed']));
$call('rename none', static fn () => WP_Application_Passwords::update_application_password(1, wp_generate_uuid4(), ['name' => 'Zz Nobody']));
$call('record a use', static fn () => WP_Application_Passwords::record_application_password_usage(1, $made['first'] ?? ''));
$call('record a use of none', static fn () => WP_Application_Passwords::record_application_password_usage(1, wp_generate_uuid4()));
$say('in use', WP_Application_Passwords::is_in_use());

$login = get_userdata(1)->user_login;
$call('authenticate outside an API request', static fn () => wp_authenticate_application_password(null, $login, is_array($first) ? $first[0] : ''));
add_filter('application_password_is_api_request', '__return_true');
$call('authenticate, the right password', static fn () => wp_authenticate_application_password(null, $login, is_array($first) ? WP_Application_Passwords::chunk_password($first[0]) : ''));
$call('authenticate, a wrong password', static fn () => wp_authenticate_application_password(null, $login, 'abcd abcd abcd abcd abcd abcd'));
$call('authenticate, no such user', static fn () => wp_authenticate_application_password(null, 'zz-no-such-user', 'abcd abcd abcd abcd abcd abcd'));
$call('authenticate, a user already found', static fn () => wp_authenticate_application_password(get_userdata(1), $login, 'whatever'));
$call('authenticate, an email unknown', static fn () => wp_authenticate_application_password(null, 'zz-nobody@example.com', 'abcd abcd abcd abcd abcd abcd'));
add_filter('wp_is_application_passwords_available_for_user', '__return_false');
$call('authenticate, not for this user', static fn () => wp_authenticate_application_password(null, $login, 'abcd abcd abcd abcd abcd abcd'));
remove_filter('wp_is_application_passwords_available_for_user', '__return_false');
add_filter('wp_is_application_passwords_available', '__return_false');
$call('authenticate, not available', static fn () => wp_authenticate_application_password(null, $login, 'abcd abcd abcd abcd abcd abcd'));
$say('available for user 1, when none are', wp_is_application_passwords_available_for_user(get_userdata(1)));
remove_filter('wp_is_application_passwords_available', '__return_false');
remove_filter('application_password_is_api_request', '__return_true');

$call('delete one', static fn () => WP_Application_Passwords::delete_application_password(1, $made['second'] ?? ''));
$call('delete none', static fn () => WP_Application_Passwords::delete_application_password(1, wp_generate_uuid4()));
foreach ($made as $uuid) {
    WP_Application_Passwords::delete_application_password(1, $uuid);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
