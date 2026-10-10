<?php
/**
 * The view configurations (probe rest-view-config): WP_REST_View_Config_Controller
 * and WP_View_Config_Data called directly, as a plugin would. The schema
 * and its parts, the capability each entity needs and the permission check
 * for nobody, an administrator and a contributor, the configuration of
 * every core post type, custom types (their all-items label as the first
 * view's title), taxonomies, other kinds and entities that are not shown
 * in REST, empty objects cast by the schema, and the data class's patches:
 * set, merge, replace and remove (keys, list members by identity, nulls,
 * versions, undocumented keys, mismatched shapes), chained, and run
 * through the get_entity_view_config_{kind}_{name} filter. Registrations
 * live for the request only; the probe's user is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => []];
register_shutdown_function(static function () use (&$made): void {
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, html_entity_decode(strip_tags((string) $message))];
}, 10, 2);
$answer = static function ($response) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $response];
    }
    return [$response->get_status(), $response->get_data()];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $kind, string $name) {
    $request = new WP_REST_Request('GET', '/wp/v2/view-config');
    $request->set_param('kind', $kind);
    $request->set_param('name', $name);
    return $request;
};
// A protected or private method, called from inside its class.
$reach = static fn (object $object, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args);

// The engine's probe runner fires no init, where core and the theme register their pattern categories (the reference has).
if (!did_action('init')) {
    do_action('init');
}
register_post_type('zzvc', ['label' => 'Zz Vcs', 'labels' => ['all_items' => 'Every Zz'], 'public' => true, 'show_in_rest' => true]);
register_post_type('zzvcplain', ['label' => 'Zz Plains', 'public' => true, 'show_in_rest' => true]);
register_post_type('zzvchidden', ['label' => 'Zz Hidden', 'public' => true, 'show_in_rest' => false]);
register_taxonomy('zzvctax', ['post'], ['label' => 'Zz Vc Taxes', 'show_in_rest' => true]);
// Pattern categories the patterns' views are sorted among, by title and without regard to case.
register_block_pattern_category('zzvc-first', ['label' => 'Aardvark']);
register_block_pattern_category('zzvc-lower', ['label' => 'aardvark lower']);
register_block_pattern_category('zzvc-last', ['label' => 'Zebra', 'description' => 'Last of all.']);
$contributor = wp_insert_user(['user_login' => 'zzviewcontributor', 'user_email' => 'zzviewcontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-view-config: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;

$controller = new WP_REST_View_Config_Controller();
$say('the controller', [get_parent_class($controller), $controller->get_item_schema(), $controller->get_collection_params()]);
$say('the schema parts', array_map(static fn ($method) => $reach($controller, $method), ['get_view_base_schema', 'get_column_style_schema', 'get_table_layout_schema', 'get_list_layout_schema', 'get_combined_layout_schema', 'get_grid_layout_schema', 'get_form_layout_schema', 'get_form_field_schema', 'get_form_schema']));

$entities = [['postType', 'post'], ['postType', 'page'], ['postType', 'wp_template'], ['postType', 'zzvc'], ['postType', 'zzvchidden'], ['postType', 'zz-none'], ['taxonomy', 'category'], ['taxonomy', 'zzvctax'], ['taxonomy', 'post_format'], ['root', 'site'], ['zz', 'x'], ['', '']];
$say('the capability each entity needs', array_map(static fn ($entity) => [implode('/', $entity), $reach($controller, 'get_required_capability', ...$entity)], $entities));
foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", array_map(static fn ($entity) => [implode('/', $entity), $try(static fn () => $controller->get_items_permissions_check($request(...$entity)))], $entities));
}

wp_set_current_user(1);
$configs = [];
foreach (['post', 'page', 'attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'zzvc', 'zzvcplain', 'zzvchidden', 'revision', 'zz-none'] as $type) {
    $configs["postType/{$type}"] = $try(static fn () => $controller->get_items($request('postType', $type)));
}
// The patterns' views without core's own pattern categories, which the engine does not register yet (owed): the
// probe's, the theme's and the fixed views keep the order they are sorted into.
if (isset($configs['postType/wp_block'][1]['view_list'])) {
    $configs['postType/wp_block'][1]['view_list'] = array_values(array_filter($configs['postType/wp_block'][1]['view_list'], static fn ($view) => in_array($view['slug'], ['all-patterns', 'my-patterns', 'uncategorized'], true) || preg_match('/^(zzvc|twentytwentyfive_)/', (string) $view['slug'])));
}
foreach (['category', 'post_tag', 'zzvctax', 'post_format'] as $taxonomy) {
    $configs["taxonomy/{$taxonomy}"] = $try(static fn () => $controller->get_items($request('taxonomy', $taxonomy)));
}
foreach ([['root', 'site'], ['root', 'zz'], ['zz', 'x'], ['PostType', 'post'], ['', 'post'], ['postType', '']] as [$kind, $name]) {
    $configs["{$kind}/{$name}"] = $try(static fn () => $controller->get_items($request($kind, $name)));
}
$say('configurations', $configs);

$schema = $controller->get_item_schema();
$say('empty objects cast', array_map(static fn ($value) => json_encode($reach($controller, 'cast_empty_objects', $value, $schema)), [
    ['default_layouts' => ['table' => [], 'grid' => []], 'form' => []],
    ['default_view' => ['filters' => [], 'sort' => []], 'view_list' => []],
    ['view_list' => [['slug' => 'a', 'view' => []]]],
    [],
]));

$base = ['version' => 1, 'default_view' => ['type' => 'table', 'fields' => ['a', 'b'], 'sort' => ['field' => 'title', 'direction' => 'asc']], 'view_list' => [['slug' => 'all', 'title' => 'All'], ['slug' => 'x', 'title' => 'X', 'view' => ['filters' => []]]], 'form' => ['fields' => [['id' => 'f1'], 'f2']], 'default_layouts' => ['table' => ['a' => 1], 'grid' => []]];
$patched = static function (Closure $op) use ($base, $reach, &$wrong, $try) {
    $wrong = [];
    $data = new WP_View_Config_Data($base);
    $returned = $try(static fn () => $op($data));
    $shown = match (true) {
        $returned === ['not a response', null] => null,
        ($returned[1] ?? null) instanceof WP_View_Config_Data => 'itself',
        default => $returned,
    };
    return [$shown, $reach($data, 'get_data'), $wrong];
};
$say('patches', [
    'nothing' => $patched(static fn ($data) => null),
    'set a key' => $patched(static fn ($data) => $data->set(['default_view' => ['type' => 'grid']], 1)),
    'set a list' => $patched(static fn ($data) => $data->set(['view_list' => [['slug' => 'x', 'title' => 'Ex']]], 1)),
    'set a null' => $patched(static fn ($data) => $data->set(['default_view' => ['sort' => null]], 1)),
    'merge a list of words' => $patched(static fn ($data) => $data->merge(['default_view' => ['fields' => ['b', 'c']]], 1)),
    'merge a new list member' => $patched(static fn ($data) => $data->merge(['view_list' => [['slug' => 'y', 'title' => 'Y']]], 1)),
    'merge a member by identity' => $patched(static fn ($data) => $data->merge(['view_list' => [['slug' => 'x', 'view' => ['perPage' => 3]]]], 1)),
    'merge a member without identity' => $patched(static fn ($data) => $data->merge(['view_list' => [['title' => 'Anon']]], 1)),
    'merge form fields' => $patched(static fn ($data) => $data->merge(['form' => ['fields' => [['id' => 'f1', 'label' => 'F1'], 'f3']]], 1)),
    'merge a list over a word' => $patched(static fn ($data) => $data->merge(['default_view' => ['type' => ['x']]], 1)),
    'merge a map over a list' => $patched(static fn ($data) => $data->merge(['default_view' => ['fields' => ['k' => 'v']]], 1)),
    'merge a null' => $patched(static fn ($data) => $data->merge(['default_view' => ['sort' => null]], 1)),
    'replace a list' => $patched(static fn ($data) => $data->replace(['default_view' => ['fields' => ['c']]], 1)),
    'replace a nested map' => $patched(static fn ($data) => $data->replace(['default_view' => ['sort' => ['field' => 'date']]], 1)),
    'replace a member list' => $patched(static fn ($data) => $data->replace(['view_list' => [['slug' => 'x', 'title' => 'New']]], 1)),
    'replace a null' => $patched(static fn ($data) => $data->replace(['default_view' => ['fields' => null]], 1)),
    'remove a key' => $patched(static fn ($data) => $data->remove(['default_view' => ['sort']], 1)),
    'remove a word from a list' => $patched(static fn ($data) => $data->remove(['default_view' => ['fields' => ['a']]], 1)),
    'remove a member by identity' => $patched(static fn ($data) => $data->remove(['view_list' => ['x']], 1)),
    'remove a field by id' => $patched(static fn ($data) => $data->remove(['form' => ['fields' => ['f1']]], 1)),
    'remove a field by name' => $patched(static fn ($data) => $data->remove(['form' => ['fields' => ['f2']]], 1)),
    'remove a layout' => $patched(static fn ($data) => $data->remove(['default_layouts' => ['table']], 1)),
    'remove a top-level key' => $patched(static fn ($data) => $data->remove(['default_view'], 1)),
    'version 2' => $patched(static fn ($data) => $data->set(['default_view' => ['type' => 'grid']], 2)),
    'version 0' => $patched(static fn ($data) => $data->merge(['default_view' => ['type' => 'grid']], 0)),
    'an undocumented key' => $patched(static fn ($data) => $data->set(['zz' => 1, 'default_view' => ['type' => 'grid']], 1)),
    'the version key' => $patched(static fn ($data) => $data->replace(['version' => 2], 1)),
    'removing an undocumented key' => $patched(static fn ($data) => $data->remove(['zz' => ['a']], 1)),
    'chained' => $patched(static fn ($data) => $data->set(['default_view' => ['type' => 'grid']], 1)->merge(['default_view' => ['perPage' => 5]], 1)->remove(['view_list' => ['all']], 1)),
    'not an array' => $patched(static fn ($data) => $data->set('x', 1)),
]);

$heard = [];
$hook = static function ($data, $context) use (&$heard) {
    $heard[] = [get_class($data), $context];
    $data->merge(['view_list' => [['slug' => 'zz-mine', 'title' => 'Mine']]], 1)->set(['default_view' => ['type' => 'grid']], 1);
    return $data;
};
add_filter('get_entity_view_config_posttype_zzvc', $hook, 10, 2);
add_filter('get_entity_view_config_root_zz', $hook, 10, 2);
$say('the filter', [
    $try(static fn () => $controller->get_items($request('postType', 'zzvc'))),
    $try(static fn () => $controller->get_items($request('root', 'zz'))),
    (new WP_View_Config_Data($base))->apply_filters('postType', 'zzvc'),
    (new WP_View_Config_Data($base))->apply_filters('taxonomy', 'zzvc'),
    $heard,
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
