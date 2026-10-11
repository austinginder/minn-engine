<?php
/**
 * The themes controller class (probe rest-themes-controller): its base and
 * routes; schema and collection parameters; the stylesheet and status
 * parameters as the routes sanitize them; the permission checks for a
 * visitor, an author, an editor and an administrator; whether two themes
 * are the same; theme supports as they are shown; and the installed
 * themes read, listed and shaped.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// The status sanitizer is deprecated: the notice is silenced and its firing recorded.
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
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links(), 'headers' => $response->get_headers()] : $response);
$request = static function (string $method, string $route, array $params = [], array $defaults = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $request->set_default_params($defaults);
    return $request;
};
wp_set_current_user(0);
rest_get_server();

$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
$users = [];
foreach (['author', 'editor', 'subscriber'] as $role) {
    $users[$role] = wp_insert_user(['user_login' => "zz_rthm_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz-rthm-{$role}@example.com", 'role' => $role]);
    if (!is_int($users[$role]) || $users[$role] <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[] = $users[$role];
}
// A global styles row a theme gets when it is read: its id is masked like the users'.
$styles = static fn () => array_map('intval', get_posts(['post_type' => 'wp_global_styles', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]));
$ids = array_values($users);
$masked = static function ($value) use (&$ids, $styles) {
    $all = array_merge($ids, $styles());
    rsort($all);
    // Theme addresses take the scheme the command line gives them.
    $json = preg_replace('#https?://minn\.localhost/wp-content/#', '{content-url}/', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    foreach ($all as $index => $id) {
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    return json_decode($json, true);
};

$controller = new WP_REST_Themes_Controller();
$say('the controller', Closure::bind(fn () => [$this->namespace, $this->rest_base, self::PATTERN], $controller, WP_REST_Themes_Controller::class)());
$routes = (static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Themes_Controller())->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/themes')) {
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
$say('schema', $named($controller->get_item_schema()));
$say('collection parameters', $named($controller->get_collection_params()));
$say('parameters sanitized', [
    'stylesheets' => array_map(static fn ($value) => $controller->_sanitize_stylesheet_callback($value), ['plain' => 'twentytwentyfive', 'encoded' => 'twenty%20five', 'a plus' => 'a+b', 'a child' => 'parent/child', 'encoded slash' => 'parent%2Fchild', 'empty' => '']),
    'statuses' => array_map(static fn ($value) => $shown($controller->sanitize_theme_status($value, $request('GET', '/wp/v2/themes'), 'status')), ['active' => 'active', 'both' => ['active', 'inactive'], 'a list in text' => 'active,inactive', 'unknown' => 'zz', 'one unknown' => ['active', 'zz'], 'empty' => '', 'nothing' => []]),
    'deprecation' => $deprecated,
]);

$checks = static function () use ($controller, $shown, $request, $call) {
    $one = static fn (string $stylesheet) => $request('GET', '/wp/v2/themes/' . $stylesheet, ['stylesheet' => $stylesheet]);
    return [
        'all' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/themes'))),
        'active' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/themes', ['status' => ['active']]))),
        'inactive' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/themes', ['status' => ['inactive']]))),
        'the active one' => $shown($controller->get_item_permissions_check($one('twentytwentyfive'))),
        'another' => $shown($controller->get_item_permissions_check($one('twentytwentyfour'))),
        'a missing one' => $shown($controller->get_item_permissions_check($one('zz-none'))),
        'read the active one' => $shown($call($controller, 'check_read_active_theme_permission')),
    ];
};
$visitor = $checks();
$asUsers = [];
foreach ($users as $role => $id) {
    wp_set_current_user($id);
    $asUsers[$role] = $checks();
}
wp_set_current_user(1);
$say('permissions', ['a visitor' => $visitor] + $asUsers + ['an administrator' => $checks()]);

$say('the same theme', [
    $call($controller, 'is_same_theme', wp_get_theme('twentytwentyfive'), wp_get_theme()),
    $call($controller, 'is_same_theme', wp_get_theme('twentytwentyfour'), wp_get_theme()),
    $call($controller, 'is_same_theme', wp_get_theme('zz-none'), wp_get_theme('zz-none')),
]);

$features = get_registered_theme_features();
$support = static fn (string $feature, $value) => $call($controller, 'prepare_theme_support', $value, $features[$feature] ?? [], $feature, $request('GET', '/wp/v2/themes'));
$say('theme supports shown', [
    'align wide' => $support('align-wide', true),
    'post formats' => $support('post-formats', [['aside', 'gallery']]),
    'html5' => $support('html5', [['comment-list', 'search-form']]),
    'custom logo' => $support('custom-logo', [['width' => 200, 'height' => 100, 'flex-width' => true]]),
    'custom header' => $support('custom-header', [['default-image' => 'https://example.com/h.jpg', 'width' => 1200, 'height' => 300]]),
    'editor color palette' => $support('editor-color-palette', [[['name' => 'Red', 'slug' => 'red', 'color' => '#f00']]]),
    'responsive embeds' => $support('responsive-embeds', true),
    'title tag' => $support('title-tag', true),
    'automatic feed links' => $support('automatic-feed-links', true),
    'disable custom colors' => $support('disable-custom-colors', true),
]);

// Reading as the administrator.
wp_set_current_user(1);
// Tags are left out: the command line has the admin's feature list loaded, which names them (the HTTP probe rest-themes shows them as a request sees them).
$untagged = static function ($shownValue) {
    unset($shownValue['data']['tags']);
    return $shownValue;
};
$read = static fn (string $stylesheet, array $params = []) => $untagged($shown($controller->get_item($request('GET', '/wp/v2/themes/' . $stylesheet, ['stylesheet' => $stylesheet] + $params))));
$listed = static fn ($response) => is_wp_error($response) ? $shown($response) : ['names' => array_map(static fn ($item) => [$item['stylesheet'] ?? null, $item['status'] ?? null, isset($item['theme_supports'])], (array) $response->get_data()), 'headers' => $response->get_headers()];
$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null);
$say('reading', $masked([
    'the active one' => $read('twentytwentyfive'),
    'the active one, fields' => $read('twentytwentyfive', ['_fields' => 'stylesheet,name,status,theme_supports']),
    'the active one, embedded' => $read('twentytwentyfive', ['context' => 'embed']),
    'another' => $read('twentytwentyfour'),
    'a classic-looking one' => $read('minn-site', ['_fields' => 'stylesheet,template,is_block_theme,status']),
    'a missing one' => $read('zz-none'),
    'links' => $call($controller, 'prepare_links', wp_get_theme('twentytwentyfive')),
    'links of another' => $call($controller, 'prepare_links', wp_get_theme('twentytwentyfour')),
    'shaped' => $untagged($shown($controller->prepare_item_for_response(wp_get_theme('twentytwentythree'), $request('GET', '/wp/v2/themes')))),
    'all' => $listed($controller->get_items($request('GET', '/wp/v2/themes', [], $defaults))),
    'active' => $listed($controller->get_items($request('GET', '/wp/v2/themes', ['status' => ['active']], $defaults))),
    'inactive' => $listed($controller->get_items($request('GET', '/wp/v2/themes', ['status' => ['inactive']], $defaults))),
]));
wp_set_current_user(0);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
