<?php
/**
 * The settings controller class (probe rest-settings-controller): its base
 * and routes; the registered options shown in REST and the schema built
 * from them (objects closed to properties they do not name); values
 * prepared against their schemas; the request's values sanitized; who may
 * read and change settings; and the settings read and updated through the
 * class, a plugin's among them. Every option it changes is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// set_additional_properties_to_false is deprecated: the notice is silenced and its firing recorded.
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/user.php';
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$named = static function ($value) use (&$named) {
    if ($value instanceof Closure) {
        return '@closure';
    }
    if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
        return '@method:' . get_class($value[0]) . '::' . $value[1];
    }
    return is_array($value) ? array_map($named, $value) : $value;
};
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data()] : $response);
$request = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
wp_set_current_user(0);
rest_get_server();

// The options written here, and the editor, are put back on the way out.
$options = [];
foreach (['blogname', 'blogdescription', 'posts_per_page', 'default_comment_status', 'zz_rsc_object', 'zz_rsc_named', 'zz_rsc_plain'] as $name) {
    $options[$name] = get_option($name, null);
}
$made = [];
register_shutdown_function(static function () use ($options, &$made): void {
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
$editor = wp_insert_user(['user_login' => 'zz_rsc_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rsc@example.com', 'role' => 'editor']);
if (!is_int($editor) || $editor <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made[] = $editor;
register_setting('zz_rsc', 'zz_rsc_object', ['type' => 'object', 'description' => 'ZZ object', 'default' => ['a' => 'x'], 'show_in_rest' => ['schema' => ['properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'object', 'properties' => ['c' => ['type' => 'integer']]]]]]]);
register_setting('zz_rsc', 'zz_rsc_named', ['type' => 'integer', 'label' => 'ZZ named', 'default' => 3, 'show_in_rest' => ['name' => 'zz_rsc_rest_name']]);
register_setting('zz_rsc', 'zz_rsc_plain', ['type' => 'string', 'show_in_rest' => true]);
register_setting('zz_rsc', 'zz_rsc_hidden', ['type' => 'string']);

$controller = new WP_REST_Settings_Controller();
$say('the controller', Closure::bind(fn () => [$this->namespace, $this->rest_base], $controller, WP_REST_Settings_Controller::class)());
$routes = (static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Settings_Controller())->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/settings')) {
            continue;
        }
        foreach ($endpoints as $endpoint) {
            $found[$route][] = ['methods' => array_keys($endpoint['methods']), 'callback' => $named($endpoint['callback']), 'permission' => $named($endpoint['permission_callback'] ?? null), 'args' => $named($endpoint['args'])];
        }
    }
    $GLOBALS['wp_rest_server'] = $server;
    return $found;
})();
$say('routes', $routes);
$say('registered options', $named($call($controller, 'get_registered_options')));
$say('schema', $named($controller->get_item_schema()));
$say('closed objects', [
    'a flat one' => $call($controller, 'set_additional_properties_to_false', ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]]),
    'nested' => $call($controller, 'set_additional_properties_to_false', ['type' => 'object', 'properties' => ['a' => ['type' => 'object', 'properties' => ['b' => ['type' => 'string']]]]]),
    'an array of objects' => $call($controller, 'set_additional_properties_to_false', ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]]]),
    'one that names its own' => $call($controller, 'set_additional_properties_to_false', ['type' => 'object', 'properties' => [], 'additionalProperties' => true]),
    'a list of types' => $call($controller, 'set_additional_properties_to_false', ['type' => ['object', 'null'], 'properties' => ['a' => ['type' => 'string']]]),
    'a string' => $call($controller, 'set_additional_properties_to_false', ['type' => 'string']),
    'deprecation' => $deprecated,
]);
$say('values prepared', array_map(static fn ($case) => $call($controller, 'prepare_value', $case[0], $case[1]), [
    'a string' => ['x', ['type' => 'string']],
    'a number as text' => ['5', ['type' => 'integer']],
    'text for a number' => ['abc', ['type' => 'integer']],
    'a boolean' => ['1', ['type' => 'boolean']],
    'an object' => [['a' => 'x', 'z' => 'y'], ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'additionalProperties' => false]],
    'an array' => [['1', 2], ['type' => 'array', 'items' => ['type' => 'integer']]],
    'null' => [null, ['type' => 'string']],
    'an enum miss' => ['zz', ['type' => 'string', 'enum' => ['open', 'closed']]],
    'a string for an array' => ['a,b', ['type' => 'array', 'items' => ['type' => 'string']]],
]));
$say('values sanitized', array_map(static fn ($case) => $shown($controller->sanitize_callback($case[0], $request('POST', '/wp/v2/settings'), $case[1])), [
    'null' => [null, 'title'],
    'a string' => ['x', 'title'],
    'not a setting' => ['x', 'zz_none'],
]));

$checks = static fn () => [$shown($controller->get_item_permissions_check($request('GET', '/wp/v2/settings')))];
$visitor = $checks();
wp_set_current_user($editor);
$asEditor = $checks();
wp_set_current_user(1);
$say('permissions', ['a visitor' => $visitor, 'an editor' => $asEditor, 'an administrator' => $checks()]);

// Reading and writing as the administrator.
wp_set_current_user(1);
$heard = [];
$listen = static function ($result, $name, $value, $args) use (&$heard) {
    $heard[] = [$name, $value, $args['option_name'] ?? null];
    return $name === 'zz_rsc_plain' && $value === 'intercepted' ? true : $result;
};
add_filter('rest_pre_update_setting', $listen, 10, 4);
$update = static fn (array $params) => $shown($controller->update_item($request('PUT', '/wp/v2/settings', $params)));
$life = [];
$life['read'] = $shown($controller->get_item($request('GET', '/wp/v2/settings')));
$life['updated'] = [$update(['title' => 'ZZ rsc title', 'description' => 'ZZ rsc tagline', 'posts_per_page' => 7, 'zz_rsc_rest_name' => 9, 'zz_rsc_object' => ['a' => 'y', 'b' => ['c' => 2]]]), get_option('blogname'), get_option('zz_rsc_named')];
$life['a value its schema refuses'] = $update(['posts_per_page' => 'many']);
$life['an object with more'] = $update(['zz_rsc_object' => ['a' => 'y', 'z' => 1]]);
$life['nothing'] = $update([]);
$life['not a setting'] = $update(['zz_none' => 'x']);
$life['a plugin intercepts'] = [$update(['zz_rsc_plain' => 'intercepted']), get_option('zz_rsc_plain', 'unset')];
$life['set, then null'] = [$update(['zz_rsc_plain' => 'kept']), get_option('zz_rsc_plain', 'unset'), $update(['zz_rsc_plain' => null]), get_option('zz_rsc_plain', 'unset')];
$life['a stored value its schema refuses, then null'] = (static function () use ($update) {
    update_option('zz_rsc_named', 'not a number');
    return [$update(['zz_rsc_rest_name' => null]), get_option('zz_rsc_named', 'unset')];
})();
$life['heard'] = $heard;
remove_filter('rest_pre_update_setting', $listen, 10);
wp_set_current_user(0);
$say('settings through their life', $life);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
