<?php
/**
 * The application passwords REST controller as a class (probe
 * rest-app-passwords-class): WP_REST_Application_Passwords_Controller
 * called directly, as a plugin would. Its schema and parameters, the
 * permission checks for nobody, an administrator and a subscriber (on
 * themselves and on each other, by id and as "me"), and the passwords
 * listed, read, created (an app id, a taken name, no name), renamed and
 * deleted one by one and all at once, the current one (none, outside an
 * application password sign-in), and the helpers: the user and the
 * password a request names, an item prepared for the database and for the
 * response with chosen fields, its links. The probe's passwords and its
 * user are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'admin' => []];
// The administrator's own passwords, put back exactly (an empty list stays an empty list).
$adminKept = metadata_exists('user', 1, '_application_passwords') ? get_user_meta(1, '_application_passwords', true) : null;
register_shutdown_function(static function () use (&$made, $adminKept): void {
    $adminKept === null ? delete_user_meta(1, '_application_passwords') : update_user_meta(1, '_application_passwords', $adminKept);
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            WP_Application_Passwords::delete_all_application_passwords($id);
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
add_filter('wp_is_application_passwords_available', '__return_true');
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$subscriber = wp_insert_user(['user_login' => 'zzappsubscriber', 'user_email' => 'zzappsubscriber@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
if (!is_int($subscriber) || $subscriber < 2) {
    fwrite(STDERR, "rest-app-passwords-class: the subscriber could not be made\n");
    exit(1);
}
$made['users'][] = $subscriber;
$uuids = [];
$mask = static function ($value) use (&$mask, &$uuids, $host, $subscriber) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = match ($key) {
                'created', 'last_used' => $item === null ? null : '{time}',
                'password' => is_string($item) ? '{password: ' . strlen($item) . '}' : $item,
                default => $mask($item),
            };
        }
        return $out;
    }
    if (is_int($value) && $value === $subscriber) {
        return '{subscriber}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($host, '{host}', $value);
    foreach ($uuids as $n => $uuid) {
        $value = str_replace($uuid, '{uuid' . $n . '}', $value);
    }
    return (string) preg_replace('#/users/' . $subscriber . '(?![0-9])#', '/users/{subscriber}', $value);
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $mask($response->get_error_data())];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $mask($response)];
    }
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links())];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $method, $user, array $params = []) {
    $route = '/wp/v2/users/' . $user . '/application-passwords' . (isset($params['uuid']) ? '/' . $params['uuid'] : '');
    $request = new WP_REST_Request($method, $route);
    $request->set_url_params(array_filter(['user_id' => $user, 'uuid' => $params['uuid'] ?? null], static fn ($value) => $value !== null));
    // Called directly, a request has no context unless given one: the reference then shows no fields.
    foreach (array_diff_key($params + ['context' => 'edit'], ['uuid' => 1]) as $key => $value) {
        if ($value !== null) {
            $request->set_param($key, $value);
        }
    }
    return $request;
};
// A protected method, called from inside its class.
$reach = static fn (object $object, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args);

$controller = new WP_REST_Application_Passwords_Controller();
$say('the class', [get_parent_class($controller), $controller->get_item_schema(), $controller->get_collection_params()]);

// Passwords to read: one for the administrator, one for the subscriber, made outside the controller.
[$adminPassword, $adminItem] = WP_Application_Passwords::create_new_application_password(1, ['name' => 'Zz Admin App']);
[$subscriberPassword, $subscriberItem] = WP_Application_Passwords::create_new_application_password($subscriber, ['name' => 'Zz Subscriber App', 'app_id' => '0b7d1a6e-0000-4000-8000-00000000abcd']);
$made['admin'][] = $adminItem['uuid'];
$uuids[] = $adminItem['uuid'];
$uuids[] = $subscriberItem['uuid'];

foreach ([0 => 'nobody', 1 => 'an administrator', $subscriber => 'the subscriber'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", array_map(static fn ($check) => $try($check), [
        'list mine' => static fn () => $controller->get_items_permissions_check($request('GET', 'me')),
        'list the administrator\'s' => static fn () => $controller->get_items_permissions_check($request('GET', 1)),
        'list the subscriber\'s' => static fn () => $controller->get_items_permissions_check($request('GET', $subscriber)),
        'list a missing user\'s' => static fn () => $controller->get_items_permissions_check($request('GET', 999999)),
        'read the subscriber\'s' => static fn () => $controller->get_item_permissions_check($request('GET', $subscriber, ['uuid' => $subscriberItem['uuid']])),
        'create for the subscriber' => static fn () => $controller->create_item_permissions_check($request('POST', $subscriber, ['name' => 'x'])),
        'update the subscriber\'s' => static fn () => $controller->update_item_permissions_check($request('PUT', $subscriber, ['uuid' => $subscriberItem['uuid']])),
        'delete all of the subscriber\'s' => static fn () => $controller->delete_items_permissions_check($request('DELETE', $subscriber)),
        'delete the subscriber\'s' => static fn () => $controller->delete_item_permissions_check($request('DELETE', $subscriber, ['uuid' => $subscriberItem['uuid']])),
        'the current one' => static fn () => $controller->get_current_item_permissions_check($request('GET', 'me', ['uuid' => 'introspect'])),
        'the helper, for the administrator' => static fn () => $reach($controller, 'do_permissions_check', $request('GET', 1)),
    ]));
}

wp_set_current_user(1);
$say('passwords read', [
    'the administrator\'s, as "me"' => $try(static fn () => $controller->get_items($request('GET', 'me'))),
    'the subscriber\'s' => $try(static fn () => $controller->get_items($request('GET', $subscriber))),
    'one' => $try(static fn () => $controller->get_item($request('GET', $subscriber, ['uuid' => $subscriberItem['uuid']]))),
    'one, in the view context' => $try(static fn () => $controller->get_item($request('GET', $subscriber, ['uuid' => $subscriberItem['uuid'], 'context' => 'view']))),
    'one, with no context at all' => $try(static fn () => $controller->get_item($request('GET', $subscriber, ['uuid' => $subscriberItem['uuid'], 'context' => null]))),
    'a missing one' => $try(static fn () => $controller->get_item($request('GET', $subscriber, ['uuid' => 'zz-missing']))),
    'the current one, without an application password sign-in' => $try(static fn () => $controller->get_current_item($request('GET', 'me', ['uuid' => 'introspect']))),
]);

$created = [];
foreach ([
    'with an app id' => [$subscriber, ['name' => 'Zz Made Here', 'app_id' => '0b7d1a6e-0000-4000-8000-00000000beef']],
    'a taken name' => [$subscriber, ['name' => 'Zz Made Here']],
    'no name' => [$subscriber, ['name' => '']],
    'for the administrator' => [1, ['name' => 'Zz Admin Made']],
] as $label => [$user, $params]) {
    try {
        $response = $controller->create_item($request('POST', $user, $params));
    } catch (Throwable $e) {
        $created[$label] = ['threw' => get_class($e)];
        continue;
    }
    // The new password's uuid is masked from here on; it is the administrator's to remove when it is theirs.
    if ($response instanceof WP_REST_Response && is_string($response->get_data()['uuid'] ?? null)) {
        $uuids[] = $response->get_data()['uuid'];
        if ($user === 1) {
            $made['admin'][] = $response->get_data()['uuid'];
        }
    }
    $created[$label] = $answer($response);
}
$say('passwords created', $created);
$madeHere = $uuids[2] ?? 'zz-none';
$say('passwords renamed', [
    'renamed' => $try(static fn () => $controller->update_item($request('PUT', $subscriber, ['uuid' => $madeHere, 'name' => 'Zz Renamed']))),
    'to a taken name' => $try(static fn () => $controller->update_item($request('PUT', $subscriber, ['uuid' => $madeHere, 'name' => 'Zz Subscriber App']))),
    'a missing one' => $try(static fn () => $controller->update_item($request('PUT', $subscriber, ['uuid' => 'zz-missing', 'name' => 'Zz X']))),
]);

$say('the helpers', [
    'the user "me"' => $mask(($user = $reach($controller, 'get_user', $request('GET', 'me'))) instanceof WP_User ? $user->ID : $answer($user)),
    'the user by id' => $mask(($user = $reach($controller, 'get_user', $request('GET', $subscriber))) instanceof WP_User ? $user->ID : $answer($user)),
    'a missing user' => $answer($reach($controller, 'get_user', $request('GET', 999999))),
    'the password by uuid' => $mask($reach($controller, 'get_application_password', $request('GET', $subscriber, ['uuid' => $subscriberItem['uuid']]))),
    'a missing password' => $answer($reach($controller, 'get_application_password', $request('GET', $subscriber, ['uuid' => 'zz-missing']))),
    'an item for the database' => $mask($reach($controller, 'prepare_item_for_database', $request('POST', $subscriber, ['name' => 'Zz Prepared', 'app_id' => '0b7d1a6e-0000-4000-8000-00000000cafe', 'zz' => 'ignored']))),
    'an item with chosen fields' => $try(static fn () => $controller->prepare_item_for_response($subscriberItem, $request('GET', $subscriber, ['_fields' => 'uuid,name']))),
    'links' => $mask($reach($controller, 'prepare_links', get_userdata($subscriber), $subscriberItem)),
]);

$say('passwords deleted', [
    'one' => $try(static fn () => $controller->delete_item($request('DELETE', $subscriber, ['uuid' => $madeHere]))),
    'a missing one' => $try(static fn () => $controller->delete_item($request('DELETE', $subscriber, ['uuid' => 'zz-missing']))),
    'all of them' => $try(static fn () => $controller->delete_items($request('DELETE', $subscriber))),
    'what is left' => count(WP_Application_Passwords::get_user_application_passwords($subscriber)),
]);

$say('deprecations announced', $deprecated);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
