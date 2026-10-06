<?php
/**
 * Settings as a plugin registers them and as wp/v2/settings serves them:
 * register_setting's stored arguments and the hooks it fires, a registered
 * default answering get_option, unregister_setting undoing it all, and the
 * settings route reading and writing a plugin's show_in_rest setting through
 * rest_pre_get_setting and rest_pre_update_setting (a short circuit, a
 * value of the wrong type, null deleting). Same protocol as api-probe.php;
 * dispatched in process, as an administrator; its options go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$before = array_keys(get_registered_settings());
$mine = ['zz_probe_color', 'zz_probe_count', 'zz_probe_flags', 'zz_probe_hidden', 'zz_probe_plain', 'zz_probe_list', 'zz_probe_args'];
foreach ($mine as $option) {
    delete_option($option);
}
$seen = [];
add_filter('register_setting_args', static function ($args, $defaults, $group, $name) use (&$seen) {
    if (str_starts_with($name, 'zz_probe_')) {
        $seen[] = ['register_setting_args', $name, $group, $args, $defaults];
    }
    return $args;
}, 10, 4);
add_action('register_setting', static function ($group, $name, $args) use (&$seen) {
    if (str_starts_with($name, 'zz_probe_')) {
        $seen[] = ['register_setting', $group, $name, $args];
    }
}, 10, 3);
add_action('unregister_setting', static function ($group, $name) use (&$seen) {
    $seen[] = ['unregister_setting', $group, $name];
}, 10, 2);
$clean = static function ($args) {
    if (isset($args['sanitize_callback']) && $args['sanitize_callback'] instanceof Closure) {
        $args['sanitize_callback'] = '{closure}';
    }
    return $args;
};

// Registered as a plugin does, on rest_api_init, so the settings route knows them.
add_action('rest_api_init', static function () {
    register_setting('zz_probe', 'zz_probe_color', ['type' => 'string', 'default' => 'teal', 'show_in_rest' => true, 'description' => 'A colour.', 'sanitize_callback' => 'sanitize_text_field']);
    register_setting('zz_probe', 'zz_probe_count', ['type' => 'integer', 'default' => 3, 'show_in_rest' => ['name' => 'zz_probe_number', 'schema' => ['minimum' => 1]], 'label' => 'Count']);
    register_setting('zz_probe', 'zz_probe_flags', ['type' => 'array', 'show_in_rest' => ['schema' => ['items' => ['type' => 'string']]], 'default' => []]);
    register_setting('zz_probe', 'zz_probe_hidden', static fn ($v) => strtoupper((string) $v));
    register_setting('zz_probe', 'zz_probe_plain', ['show_in_rest' => true]);
    register_setting('zz_probe', 'zz_probe_list', ['type' => 'array', 'show_in_rest' => true]);
    register_setting('zz_probe', 'zz_probe_args', ['sanitize_callback' => static fn (...$args) => count($args)]);
});
rest_get_server();
$say('register hooks', array_map(static fn ($row) => array_map($clean, $row), $seen));
$registered = get_registered_settings();
$say('registered', array_map($clean, array_intersect_key($registered, array_flip($mine))));
// What core registers as the settings route starts (a plugin's earlier registrations aside).
$say('core registered', array_diff_key($registered, array_flip([...$before, ...$mine])));
$say('defaults', [get_option('zz_probe_color'), get_option('zz_probe_color', 'given'), get_option('zz_probe_count'), get_option('zz_probe_flags'), get_option('zz_probe_hidden'), get_option('zz_probe_plain')]);
$say('sanitized', [sanitize_option('zz_probe_color', " <b>red</b>\n"), sanitize_option('zz_probe_hidden', 'quiet'), sanitize_option('zz_probe_args', 'x')]);

$send = static function (string $label, string $method, array $body = []) use (&$seen, $say): void {
    $seen = [];
    $request = new WP_REST_Request($method, '/wp/v2/settings');
    if ($body !== []) {
        $request->set_body_params($body);
    }
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    $answer = $response->is_error()
        ? ['code' => $data['code'] ?? null, 'message' => $data['message'] ?? null, 'params' => $data['data']['params'] ?? null]
        : array_intersect_key((array) $data, array_flip(['zz_probe_color', 'zz_probe_number', 'zz_probe_count', 'zz_probe_flags', 'zz_probe_hidden', 'zz_probe_plain', 'zz_probe_list', 'title', 'posts_per_page', 'use_smilies', 'default_ping_status', 'site_logo', 'language', 'start_of_week']));
    $say($label, ['status' => $response->get_status(), 'answer' => $answer, 'hooks' => $seen]);
};
$watch = static function ($result, $name, $value = null) use (&$seen) {
    if (str_starts_with($name, 'zz_probe_')) {
        $seen[] = [current_filter(), $name, $value];
    }
    return $result;
};
$handed = [];
add_filter('rest_pre_get_setting', static function ($result, $name, $args) use (&$handed) {
    if (in_array($name, ['zz_probe_number', 'zz_probe_color', 'title', 'zz_probe_plain'], true) && !isset($handed['get ' . $name])) {
        $handed['get ' . $name] = $args;
    }
    return $result;
}, 5, 3);
add_filter('rest_pre_update_setting', static function ($result, $name, $value, $args) use (&$handed) {
    if (!isset($handed['update ' . $name])) {
        $handed['update ' . $name] = $args;
    }
    return $result;
}, 5, 4);
add_filter('rest_pre_get_setting', static fn ($result, $name, $args) => $watch($result, $name), 10, 3);
add_filter('rest_pre_update_setting', static fn ($result, $name, $value, $args) => $watch($result, $name, $value), 10, 4);
foreach (['update_option_zz_probe_color', 'update_option_zz_probe_count', 'add_option_zz_probe_count', 'add_option_zz_probe_color', 'delete_option'] as $hook) {
    add_action($hook, static function (...$args) use (&$seen, $hook) {
        if ($hook !== 'delete_option' || str_starts_with((string) $args[0], 'zz_probe_')) {
            $seen[] = [$hook, ...$args];
        }
    }, 10, 3);
}
$send('get', 'GET');
$send('post', 'POST', ['zz_probe_color' => '<i>blue</i>', 'zz_probe_number' => '7', 'zz_probe_flags' => ['a', 'b']]);
$send('post wrong type', 'POST', ['zz_probe_number' => 'many']);
$send('post below minimum', 'POST', ['zz_probe_number' => 0]);
$send('post null deletes', 'POST', ['zz_probe_color' => null]);
$short = static fn ($result, $name) => $name === 'zz_probe_color' ? 'short-circuited' : $result;
add_filter('rest_pre_get_setting', $short, 20, 2);
$skip = static fn ($result, $name) => $name === 'zz_probe_number' ? true : $result;
add_filter('rest_pre_update_setting', $skip, 20, 2);
$junk = static fn ($result, $name) => $name === 'zz_probe_number' ? 'abc' : $result;
add_filter('rest_pre_get_setting', $junk, 20, 2);
$send('get short circuit', 'GET');
remove_filter('rest_pre_get_setting', $junk, 20);
$send('post short circuit', 'POST', ['zz_probe_number' => 9, 'zz_probe_color' => 'green']);
remove_filter('rest_pre_get_setting', $short, 20);
remove_filter('rest_pre_update_setting', $skip, 20);
$say('stored', [get_option('zz_probe_color'), get_option('zz_probe_count'), get_option('zz_probe_flags')]);
$send('post plain and list', 'POST', ['zz_probe_plain' => 'words', 'zz_probe_list' => ['x']]);
update_option('zz_probe_count', 'junk');
$send('null over a stored value of the wrong type', 'POST', ['zz_probe_number' => null]);
$say('stored after', [get_option('zz_probe_count'), get_option('zz_probe_plain'), get_option('zz_probe_list')]);
$send('null over a stored value', 'POST', ['zz_probe_plain' => null]);
$send('null over no value', 'POST', ['zz_probe_plain' => null]);
$say('filters handed', $handed);

$seen = [];
delete_option('zz_probe_color');
foreach ($mine as $option) {
    unregister_setting('zz_probe', $option);
}
$say('unregister', [$seen, array_values(array_intersect(array_keys(get_registered_settings()), $mine)), get_option('zz_probe_count'), get_option('zz_probe_color'), sanitize_option('zz_probe_hidden', 'quiet')]);
foreach ($mine as $option) {
    delete_option($option);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
