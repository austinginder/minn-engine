<?php
/**
 * What plugins are told when application passwords change over REST, as
 * the reference tells them (probe rest-application-password-hooks): for a
 * create, a rename, a delete and a delete of all, as an administrator, the
 * application password lifecycle hooks that fire (the REST ones and
 * WP_Application_Passwords' own), in order, each with its arguments'
 * shapes, and the response's status. The probe's own passwords
 * on user 1, removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
add_filter('wp_is_application_passwords_available', '__return_true');
$describe = static function ($value) use (&$describe) {
    return match (true) {
        $value instanceof WP_REST_Request => 'request:' . $value->get_method(),
        $value instanceof WP_REST_Response => 'response:' . $value->get_status() . ':' . implode(',', array_keys((array) $value->get_data())),
        $value instanceof WP_User => 'user',
        $value instanceof WP_Error => 'error:' . $value->get_error_code(),
        is_array($value) => array_map($describe, array_intersect_key($value, array_flip(['uuid', 'app_id', 'name', 'password', 'created', 'last_used', 'last_ip']))) ?: (array_is_list($value) ? 'list' : array_keys($value)),
        is_string($value) && preg_match('/^[A-Za-z0-9]{24}$/', $value) === 1 => 'a password',
        is_string($value) && preg_match('/^[0-9a-f-]{36}$/', $value) === 1 => 'uuid',
        is_string($value) && str_starts_with($value, '$') => 'hash',
        is_int($value) && $value > 1000000 => 'time',
        is_int($value) && $value > 1 => 'a user',
        default => $value,
    };
};
$heard = [];
add_action('all', static function (string $hook, ...$args) use (&$heard, $describe): void {
    if (preg_match('/^(rest_pre_insert|wp_create|wp_update|wp_delete|rest_after_insert|rest_prepare)_application_password$/', $hook) === 1) {
        $heard[] = [$hook, array_map($describe, $args)];
    }
}, 10, 6);
// A plugin that reshapes the answer; without one the reference still fires the filter, the engine skips it.
add_filter('rest_prepare_application_password', static fn ($response) => $response);
wp_set_current_user(1);
$send = static function (string $method, string $route, array $body = []) use (&$heard): array {
    $heard = [];
    $request = new WP_REST_Request($method, $route);
    if ($body !== []) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($body));
    }
    $response = rest_do_request($request);
    return [$response->get_status(), $heard, rest_get_server()->response_to_data($response, false)];
};

[$status, $hooks, $data] = $send('POST', '/wp/v2/users/1/application-passwords', ['name' => 'Zz Probe App']);
$uuid = (string) ($data['uuid'] ?? '');
$say('create', [$status, $hooks]);
[$status, $hooks] = $send('POST', "/wp/v2/users/1/application-passwords/{$uuid}", ['name' => 'Zz Probe Renamed']);
$say('rename', [$status, $hooks]);
[$status, $hooks] = $send('DELETE', "/wp/v2/users/1/application-passwords/{$uuid}");
$say('delete', [$status, $hooks]);
// Delete-all on a user of the probe's own, so user 1's real passwords stay.
$userId = (int) wp_insert_user(['user_login' => 'zz_app_hooks', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-app-hooks@example.com', 'role' => 'editor']);
foreach (['Zz One', 'Zz Two'] as $name) {
    WP_Application_Passwords::create_new_application_password($userId, ['name' => $name]);
}
[$status, $hooks] = $send('DELETE', "/wp/v2/users/{$userId}/application-passwords");
$say('delete all', [$status, $hooks]);

if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
wp_delete_user($userId, 1);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
