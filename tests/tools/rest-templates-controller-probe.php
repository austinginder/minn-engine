<?php
/**
 * The templates controller class (probe rest-templates-controller), for
 * templates and template parts: base and routes; schema and collection
 * parameters; template ids as the routes sanitize them; the permission
 * checks for a visitor, an author, an editor and an administrator;
 * templates read, listed, looked up and shaped; what a request saves; and
 * a theme template saved over, a custom template and a part created,
 * trashed and deleted through the class.
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
$named = static function ($value) use (&$named) {
    if ($value instanceof Closure) {
        return '@closure';
    }
    if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
        return '@method:' . get_class($value[0]) . '::' . $value[1];
    }
    return is_array($value) ? array_map($named, $value) : $value;
};
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links()] : $response);
// A template as the probe shows it: its fields, with the long markup cut to its start.
$brief = static function ($response) use ($shown) {
    $out = $shown($response);
    if (isset($out['data']['content']['raw']) && is_string($out['data']['content']['raw'])) {
        $out['data']['content']['raw'] = substr($out['data']['content']['raw'], 0, 120);
    }
    return $out;
};
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

// The template rows and users made here go on the way out.
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
$users = [];
foreach (['author', 'editor'] as $role) {
    $users[$role] = wp_insert_user(['user_login' => "zz_rtpl_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz-rtpl-{$role}@example.com", 'role' => $role]);
    if (!is_int($users[$role]) || $users[$role] <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['users'][] = $users[$role];
}
$theme = get_stylesheet();
$ids = array_values($users);
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    $json = preg_replace('/\b20\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d\b/', '{date}', $json);
    return json_decode($json, true);
};

$templates = new WP_REST_Templates_Controller('wp_template');
$parts = new WP_REST_Templates_Controller('wp_template_part');
$base = static fn (WP_REST_Templates_Controller $controller) => Closure::bind(fn () => [$this->namespace, $this->rest_base, $this->post_type], $controller, WP_REST_Templates_Controller::class)();
$say('the controllers', [$base($templates), $base($parts)]);
$routes = (static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Templates_Controller('wp_template'))->register_routes();
    (new WP_REST_Templates_Controller('wp_template_part'))->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/template')) {
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
$say('schemas', ['templates' => $named($templates->get_item_schema()), 'parts' => $named($parts->get_item_schema())]);
$say('collection parameters', ['templates' => $named($templates->get_collection_params()), 'parts' => $named($parts->get_collection_params())]);
$say('ids sanitized', array_map(static fn ($id) => $templates->_sanitize_template_id($id), [
    'double slashed' => $theme . '//index',
    'single slashed' => $theme . '/index',
    'encoded' => rawurlencode($theme . '//index'),
    'encoded, single' => rawurlencode($theme . '/index'),
    'no slash' => 'index',
    'a nested slug' => $theme . '/parts/header',
    'a nested slug, doubled' => $theme . '//parts/header',
    'a trailing slash' => $theme . '/',
    'empty' => '',
    'a plus' => $theme . '//a+b',
]));

$checks = static function () use ($templates, $parts, $shown, $request, $theme) {
    $one = static fn (string $base, string $id, string $method = 'GET') => $request($method, "/wp/v2/{$base}/{$id}", ['id' => $id]);
    return [
        'templates' => $shown($templates->get_items_permissions_check($request('GET', '/wp/v2/templates'))),
        'templates, edit' => $shown($templates->get_items_permissions_check($request('GET', '/wp/v2/templates', ['context' => 'edit']))),
        'parts' => $shown($parts->get_items_permissions_check($request('GET', '/wp/v2/template-parts'))),
        'one' => $shown($templates->get_item_permissions_check($one('templates', $theme . '//index'))),
        'one, edit' => $shown($templates->get_item_permissions_check($request('GET', "/wp/v2/templates/{$theme}//index", ['id' => $theme . '//index', 'context' => 'edit']))),
        'create' => $shown($templates->create_item_permissions_check($request('POST', '/wp/v2/templates', ['slug' => 'zz-x']))),
        'update' => $shown($templates->update_item_permissions_check($one('templates', $theme . '//index', 'PUT'))),
        'delete' => $shown($templates->delete_item_permissions_check($one('templates', $theme . '//index', 'DELETE'))),
        'the check' => $shown(Closure::bind(fn ($r) => $this->permissions_check($r), $templates, WP_REST_Templates_Controller::class)($request('GET', '/wp/v2/templates'))),
        'actions' => Closure::bind(fn () => $this->get_available_actions(), $templates, WP_REST_Templates_Controller::class)(),
    ];
};
$visitor = $checks();
wp_set_current_user($users['author']);
$asAuthor = $checks();
wp_set_current_user($users['editor']);
$asEditor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'an author' => $asAuthor, 'an editor' => $asEditor, 'an administrator' => $administrator]));

// Reading and writing as the administrator (the reference's methods do not check, the engine's routes do).
wp_set_current_user(1);
$read = static fn (WP_REST_Templates_Controller $controller, string $base, string $id, array $params = []) => $brief($controller->get_item($request('GET', "/wp/v2/{$base}/{$id}", ['id' => $id] + $params)));
$listed = static fn ($response) => is_wp_error($response) ? $shown($response) : array_map(static fn ($item) => [$item['id'] ?? null, $item['source'] ?? null, $item['type'] ?? null, $item['area'] ?? null, $item['is_custom'] ?? null], (array) $response->get_data());
$tdefaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $templates->get_collection_params()), static fn ($value) => $value !== null);
$say('reading', $masked([
    'a template' => $read($templates, 'templates', $theme . '//index'),
    'a template, edit' => $read($templates, 'templates', $theme . '//index', ['context' => 'edit']),
    'a template, embedded' => $read($templates, 'templates', $theme . '//index', ['context' => 'embed']),
    'a template, fields' => $read($templates, 'templates', $theme . '//index', ['_fields' => 'id,slug,title']),
    'a part' => $read($parts, 'template-parts', $theme . '//header', ['context' => 'edit']),
    'a part from templates' => $read($templates, 'templates', $theme . '//header'),
    'a missing one' => $read($templates, 'templates', $theme . '//zz-none'),
    'another theme' => $read($templates, 'templates', 'zz-theme//index'),
    'links' => $call($templates, 'prepare_links', $theme . '//index'),
    'shaped' => $brief($templates->prepare_item_for_response(get_block_template($theme . '//single'), $request('GET', '/wp/v2/templates'))),
    'shaped, edit' => $brief($parts->prepare_item_for_response(get_block_template($theme . '//footer', 'wp_template_part'), $request('GET', '/wp/v2/template-parts', ['context' => 'edit']))),
    'templates' => $listed($templates->get_items($request('GET', '/wp/v2/templates', [], $tdefaults))),
    'templates for pages' => $listed($templates->get_items($request('GET', '/wp/v2/templates', ['post_type' => 'page'], $tdefaults))),
    'parts' => $listed($parts->get_items($request('GET', '/wp/v2/template-parts', [], $tdefaults))),
    'header parts' => $listed($parts->get_items($request('GET', '/wp/v2/template-parts', ['area' => 'header'], $tdefaults))),
    'a list with an id' => $listed($templates->get_items($request('GET', '/wp/v2/templates', ['wp_id' => 999999], $tdefaults))),
    'a fallback' => $brief($templates->get_template_fallback($request('GET', '/wp/v2/templates/lookup', ['slug' => 'single-post-hello']))),
    'a custom fallback' => $brief($templates->get_template_fallback($request('GET', '/wp/v2/templates/lookup', ['slug' => 'zz-custom', 'is_custom' => true]))),
    'a prefixed fallback' => $brief($templates->get_template_fallback($request('GET', '/wp/v2/templates/lookup', ['slug' => 'category-news', 'template_prefix' => 'category']))),
    'a fallback from parts' => $brief($parts->get_template_fallback($request('GET', '/wp/v2/template-parts/lookup', ['slug' => 'page-about']))),
]));

$prepared = static fn (WP_REST_Templates_Controller $controller, string $method, string $route, array $params) => $try(static function () use ($controller, $method, $route, $params, $call, $shown) {
    $result = $call($controller, 'prepare_item_for_database', (static function () use ($method, $route, $params) {
        $request = new WP_REST_Request($method, $route);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    })());
    return is_wp_error($result) ? $shown($result) : (array) $result;
});
$say('prepared for the database', $masked([
    'a new template' => $prepared($templates, 'POST', '/wp/v2/templates', ['slug' => 'zz-new', 'title' => 'ZZ new', 'content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'description' => 'D', 'status' => 'publish', 'author' => 1]),
    'a new part' => $prepared($parts, 'POST', '/wp/v2/template-parts', ['slug' => 'zz-part', 'title' => ['raw' => 'ZZ part'], 'content' => ['raw' => 'C'], 'area' => 'footer']),
    'nothing' => $prepared($templates, 'POST', '/wp/v2/templates', []),
    'a theme template' => $prepared($templates, 'PUT', "/wp/v2/templates/{$theme}//index", ['id' => $theme . '//index', 'content' => 'changed']),
    'a missing one' => $prepared($templates, 'PUT', "/wp/v2/templates/{$theme}//zz-none", ['id' => $theme . '//zz-none', 'content' => 'changed']),
]));

$steps = [];
foreach (['rest_pre_insert_wp_template', 'rest_insert_wp_template', 'rest_after_insert_wp_template', 'rest_pre_insert_wp_template_part', 'rest_after_insert_wp_template_part', 'rest_delete_wp_template', 'wp_insert_post', 'wp_after_insert_post', 'wp_trash_post', 'delete_post'] as $hook) {
    add_filter($hook, static function ($value = null) use (&$steps) {
        $steps[] = current_filter();
        return $value;
    }, 10, 1);
}
$heard = static function () use (&$steps) {
    $heard = $steps;
    $steps = [];
    return $heard;
};
$keep = static function ($response) use (&$made, &$ids) {
    $id = $response instanceof WP_REST_Response ? (int) (($response->get_data()['wp_id'] ?? 0) ?: ($response->get_data()['previous']['wp_id'] ?? 0)) : 0;
    if ($id > 1 && !in_array($id, $made['posts'], true)) {
        $made['posts'][] = $id;
        $ids[] = $id;
    }
    // Its revisions are masked like it (they go with it).
    foreach ($id > 1 ? wp_get_post_revisions($id) : [] as $revision) {
        if (!in_array($revision->ID, $ids, true)) {
            $ids[] = $revision->ID;
        }
    }
    return $response;
};
$life = [];
$life['a theme template saved over'] = [$brief($keep($templates->update_item($request('PUT', "/wp/v2/templates/{$theme}//search", ['id' => $theme . '//search', 'content' => '<!-- wp:paragraph --><p>ZZ search</p><!-- /wp:paragraph -->', 'title' => 'ZZ search'])))), $heard()];
$life['saved again'] = [$brief($keep($templates->update_item($request('PUT', "/wp/v2/templates/{$theme}//search", ['id' => $theme . '//search', 'description' => 'ZZ described'])))), $heard()];
$life['a missing one updated'] = [$brief($templates->update_item($request('PUT', "/wp/v2/templates/{$theme}//zz-none", ['id' => $theme . '//zz-none', 'content' => 'x']))), $heard()];
$life['a custom template'] = [$brief($keep($templates->create_item($request('POST', '/wp/v2/templates', ['slug' => 'zz-rtpl-custom', 'title' => 'ZZ custom', 'content' => '<!-- wp:paragraph --><p>custom</p><!-- /wp:paragraph -->', 'description' => 'Custom'])))), $heard()];
$life['the same slug again'] = [$brief($keep($templates->create_item($request('POST', '/wp/v2/templates', ['slug' => 'zz-rtpl-custom', 'title' => 'ZZ custom 2'])))), $heard()];
$life['a part'] = [$brief($keep($parts->create_item($request('POST', '/wp/v2/template-parts', ['slug' => 'zz-rtpl-part', 'title' => 'ZZ part', 'content' => 'part', 'area' => 'footer'])))), $heard()];
$life['a theme file deleted'] = [$brief($templates->delete_item($request('DELETE', "/wp/v2/templates/{$theme}//index", ['id' => $theme . '//index', 'force' => true]))), $heard()];
$life['the saved one trashed'] = [$brief($templates->delete_item($request('DELETE', "/wp/v2/templates/{$theme}//search", ['id' => $theme . '//search']))), $heard()];
$life['the custom one trashed'] = [$brief($templates->delete_item($request('DELETE', "/wp/v2/templates/{$theme}//zz-rtpl-custom", ['id' => $theme . '//zz-rtpl-custom']))), $heard()];
$life['the part deleted'] = [$brief($parts->delete_item($request('DELETE', "/wp/v2/template-parts/{$theme}//zz-rtpl-part", ['id' => $theme . '//zz-rtpl-part', 'force' => true]))), $heard()];
$life['a missing one deleted'] = [$brief($templates->delete_item($request('DELETE', "/wp/v2/templates/{$theme}//zz-none", ['id' => $theme . '//zz-none', 'force' => true]))), $heard()];
wp_set_current_user(0);
$say('templates through their lives', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
