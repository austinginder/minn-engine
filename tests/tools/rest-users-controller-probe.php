<?php
/**
 * WP_REST_Users_Controller (probe rest-users-controller): the controller's
 * base and meta; the routes it registers; its schema and collection
 * parameters; the permission checks for a visitor, a contributor and an
 * administrator; the username, password, reassignment and role checks;
 * users read (view, edit, embed, fields, the current user) and listed
 * through it; the database fields a request makes; and a user created,
 * updated and deleted through it.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
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
$prop = static fn (object $object, string $name) => Closure::bind(fn () => $this->{$name} ?? null, $object, get_class($object))();
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

// Users made here go on the way out.
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
$make = static function (string $login, string $role, array $extra = []) use (&$made) {
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.com', 'role' => $role, 'display_name' => strtoupper($login)] + $extra);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture user\n");
        exit(1);
    }
    $made[] = $id;
    return $id;
};
$contributor = $make('zz_ruc_contributor', 'contributor', ['description' => 'A <b>bio</b>.', 'user_url' => 'https://example.com/c', 'first_name' => 'Zz', 'last_name' => 'Contributor']);
$subscriber = $make('zz_ruc_subscriber', 'subscriber');
$ids = [$contributor, $subscriber];
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    $json = preg_replace(['/"registered_date":"[0-9T:+-]+"/', '/(avatar\/)[0-9a-f]{64}/', '/"nonce":"[0-9a-f]+"/'], ['"registered_date":"{date}"', '$1{hash}', '"nonce":"{nonce}"'], $json);
    return json_decode($json, true);
};

$users = new WP_REST_Users_Controller();
$say('controller', ['namespace' => $prop($users, 'namespace'), 'rest_base' => $prop($users, 'rest_base'), 'meta' => is_object($prop($users, 'meta')) ? get_class($prop($users, 'meta')) : null, 'allow_batch' => $prop($users, 'allow_batch')]);
$say('schema', $named($users->get_item_schema()));
$say('collection parameters', $named($users->get_collection_params()));

// A copy under a namespace of its own, so only the class's routes are read.
$route = static fn (array $handlers) => array_map(static fn ($handler) => is_array($handler) ? $named(array_diff_key($handler, ['args' => true])) + ['args' => array_keys((array) ($handler['args'] ?? []))] : $handler, $handlers);
$copy = new WP_REST_Users_Controller();
Closure::bind(function (): void { $this->namespace = 'zz-ruc/v1'; }, $copy, WP_REST_Users_Controller::class)();
$copy->register_routes();
$routes = rest_get_server()->get_routes();
$say('routes registered', array_map(static fn ($pattern) => isset($routes[$pattern]) ? $route($routes[$pattern]) : null, ['users' => '/zz-ruc/v1/users', 'a user' => '/zz-ruc/v1/users/(?P<id>[\d]+)', 'the current user' => '/zz-ruc/v1/users/me']));

$checks = static function () use ($users, $call, $shown, $request, $contributor, $subscriber) {
    return [
        'list' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users'))),
        'list, edit' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['context' => 'edit']))),
        'list, by email' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['orderby' => 'email']))),
        'list, by registration' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['orderby' => 'registered_date']))),
        'list, authors' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['who' => 'authors']))),
        'list, with posts' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['has_published_posts' => true]))),
        'list, by role' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['roles' => ['subscriber']]))),
        'list, by capability' => $shown($users->get_items_permissions_check($request('GET', '/wp/v2/users', ['capabilities' => ['edit_posts']]))),
        'one with posts' => $shown($users->get_item_permissions_check($request('GET', '/wp/v2/users/1', ['id' => 1]))),
        'one without posts' => $shown($users->get_item_permissions_check($request('GET', '/wp/v2/users/' . $subscriber, ['id' => $subscriber]))),
        'oneself' => $shown($users->get_item_permissions_check($request('GET', '/wp/v2/users/' . $contributor, ['id' => $contributor]))),
        'one, edit' => $shown($users->get_item_permissions_check($request('GET', '/wp/v2/users/1', ['id' => 1, 'context' => 'edit']))),
        'one, missing' => $shown($users->get_item_permissions_check($request('GET', '/wp/v2/users/999999', ['id' => 999999]))),
        'create' => $shown($users->create_item_permissions_check($request('POST', '/wp/v2/users'))),
        'update another' => $shown($users->update_item_permissions_check($request('PUT', '/wp/v2/users/' . $subscriber, ['id' => $subscriber]))),
        'update oneself' => $shown($users->update_item_permissions_check($request('PUT', '/wp/v2/users/' . $contributor, ['id' => $contributor]))),
        'update oneself, a role' => $shown($users->update_item_permissions_check($request('PUT', '/wp/v2/users/' . $contributor, ['id' => $contributor, 'roles' => ['administrator']]))),
        'update oneself, the same role' => $shown($users->update_item_permissions_check($request('PUT', '/wp/v2/users/' . $contributor, ['id' => $contributor, 'roles' => ['contributor']]))),
        'update a missing user' => $shown($users->update_item_permissions_check($request('PUT', '/wp/v2/users/999999', ['id' => 999999]))),
        'update the current user' => $shown($users->update_current_item_permissions_check($request('PUT', '/wp/v2/users/me'))),
        'delete another' => $shown($users->delete_item_permissions_check($request('DELETE', '/wp/v2/users/' . $subscriber, ['id' => $subscriber]))),
        'delete a missing user' => $shown($users->delete_item_permissions_check($request('DELETE', '/wp/v2/users/999999', ['id' => 999999]))),
        'delete the current user' => $shown($users->delete_current_item_permissions_check($request('DELETE', '/wp/v2/users/me'))),
        'role updates' => [$shown($call($users, 'check_role_update', $subscriber, ['editor'])), $shown($call($users, 'check_role_update', $subscriber, ['zz-none'])), $shown($call($users, 'check_role_update', $contributor, ['contributor']))],
        'the current user' => $shown($users->get_current_item($request('GET', '/wp/v2/users/me', ['_fields' => 'id,name,slug']))),
    ];
};
$visitor = $checks();
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'a contributor' => $asContributor, 'an administrator' => $administrator]));

$validate = static fn (string $method, $value, array $params = []) => $shown($users->{$method}($value, $request('POST', '/wp/v2/users', $params), $method === 'check_reassign' ? 'reassign' : ($method === 'check_username' ? 'username' : 'password')));
$say('values checked', $masked([
    'usernames' => array_map(static fn ($value) => $validate('check_username', $value), ['zz_new' => 'zz_new', 'with space' => 'zz new', 'admin' => 'admin', 'html' => '<b>x</b>', 'empty' => '', 'accent' => 'zzé', 'long' => str_repeat('z', 61)]),
    'passwords' => array_map(static fn ($value) => $validate('check_user_password', $value), ['plain' => 'secret', 'empty' => '', 'backslash' => 'a\\b', 'spaces' => '  ']),
    'reassignments' => array_map(static fn ($value) => $validate('check_reassign', $value), ['false' => false, 'a user' => 1, 'a missing user' => 999999, 'zero' => 0, 'a string' => 'x']),
]));

$one = static fn (array $params, int $id = 1) => $shown($users->get_item($request('GET', '/wp/v2/users/' . $id, ['id' => $id] + $params)));
$reads = [
    'a user' => $one([], $contributor),
    'embedded' => $one(['context' => 'embed'], $contributor),
    'fields' => $one(['_fields' => 'id,name,_links'], $contributor),
    'fields without links' => $one(['_fields' => 'id,slug'], $contributor),
    'edited' => $one(['context' => 'edit'], $contributor),
    'missing' => $one([], 999999),
    'prepared' => $shown($users->prepare_item_for_response(get_userdata($contributor), $request('GET', '/wp/v2/users'))),
    'links' => $call($users, 'prepare_links', get_userdata($contributor)),
    'got' => [get_class($call($users, 'get_user', $contributor)), $shown($call($users, 'get_user', 999999)), $shown($call($users, 'get_user', 0))],
    'the current user, edit' => $shown($users->get_current_item($request('GET', '/wp/v2/users/me', ['context' => 'edit']))),
];
$say('reading', $masked($reads));

$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $users->get_collection_params()), static fn ($value) => $value !== null);
$listed = static fn (array $params) => $shown($users->get_items($request('GET', '/wp/v2/users', $params, $defaults)));
$say('listing', $masked([
    'ours' => $listed(['include' => [$contributor, $subscriber], 'context' => 'edit']),
    'a page' => $listed(['include' => [$contributor, $subscriber], 'per_page' => 1, 'page' => 2]),
    'by slug' => $listed(['slug' => ['zz_ruc_subscriber']]),
    'a search' => $listed(['search' => 'zz_ruc', 'orderby' => 'id']),
    'by role' => $listed(['roles' => ['contributor'], 'include' => [$contributor, $subscriber]]),
    'excluded' => $listed(['include' => [$contributor, $subscriber], 'exclude' => [$subscriber]]),
    'some fields' => $listed(['include' => [$contributor], '_fields' => 'id,name']),
]));

$prepared = static fn (array $params, string $method = 'POST') => $try(static function () use ($params, $method, $users, $call, $request, $subscriber) {
    $result = $call($users, 'prepare_item_for_database', $request($method, '/wp/v2/users' . ($method === 'POST' ? '' : '/' . $subscriber), $params + ($method === 'POST' ? [] : ['id' => $subscriber])));
    return is_wp_error($result) ? ['error' => $result->get_error_code()] : (array) $result;
});
$say('prepared for the database', $masked([
    'nothing' => $prepared([]),
    'fields' => $prepared(['username' => 'zz_u', 'email' => 'zz@example.com', 'name' => 'N', 'first_name' => 'F', 'last_name' => 'L', 'nickname' => 'nick', 'slug' => 'zz-slug', 'description' => 'D', 'url' => 'https://example.com', 'locale' => 'de_DE', 'password' => 'pw']),
    'fields, another order' => $prepared(['locale' => 'en_US', 'url' => 'u', 'description' => 'd', 'slug' => 's', 'nickname' => 'n', 'last_name' => 'l', 'first_name' => 'f', 'name' => 'nm', 'email' => 'e@example.com', 'username' => 'u_x']),
    'roles' => $prepared(['roles' => ['editor', 'author']]),
    'roles, a locale and a password' => $prepared(['locale' => 'de_DE', 'roles' => ['editor'], 'password' => 'p', 'url' => 'u', 'meta' => ['x' => 1]]),
    'an update' => $prepared(['name' => 'Renamed', 'username' => 'ignored'], 'PUT'),
    'the site locale' => $prepared(['locale' => 'site-default']),
]));

$life = [];
$created = $users->create_item($request('POST', '/wp/v2/users', ['username' => 'zz_ruc_created', 'email' => 'zz-ruc-created@example.com', 'password' => 'zz-secret-pass', 'name' => 'ZZ Created', 'roles' => ['author']]));
$createdId = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if ($createdId > 1) {
    $made[] = $createdId;
    $ids[] = $createdId;
}
$life['created'] = $shown($created);
$life['created again'] = $shown($users->create_item($request('POST', '/wp/v2/users', ['username' => 'zz_ruc_created', 'email' => 'zz-ruc-other@example.com', 'password' => 'x'])));
$life['created with an id'] = $shown($users->create_item($request('POST', '/wp/v2/users', ['id' => 5, 'username' => 'zz_ruc_with_id', 'email' => 'zz-ruc-with-id@example.com', 'password' => 'x'])));
$life['updated'] = $shown($users->update_item($request('PUT', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'name' => 'ZZ Renamed', 'description' => 'New bio', 'roles' => ['editor']])));
$life['updated, a taken email'] = $shown($users->update_item($request('PUT', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'email' => 'zz_ruc_contributor@example.com'])));
$life['updated, a new username'] = $shown($users->update_item($request('PUT', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'username' => 'zz_other'])));
$life['updated, a missing user'] = $shown($users->update_item($request('PUT', '/wp/v2/users/999999', ['id' => 999999, 'name' => 'x'])));
$life['deleted, not forced'] = $shown($users->delete_item($request('DELETE', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'reassign' => false])));
$life['deleted, reassigning to itself'] = $shown($users->delete_item($request('DELETE', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'force' => true, 'reassign' => $createdId])));
$life['deleted'] = $shown($users->delete_item($request('DELETE', '/wp/v2/users/' . $createdId, ['id' => $createdId, 'force' => true, 'reassign' => false])));
$life['gone'] = get_userdata($createdId) === false;
wp_set_current_user(0);
$say('a user through its life', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
