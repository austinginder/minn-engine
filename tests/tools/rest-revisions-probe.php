<?php
/**
 * The revisions, autosaves and patterns controller classes (probe
 * rest-revisions): each controller's parent, base and meta; the routes it
 * registers; its schema and collection parameters; the permission checks;
 * a post's revisions listed (pages, offsets, order, include, exclude,
 * search), read (view, edit, embed, fields) and deleted; the excerpt
 * shaped; a post's autosaves listed, created and read; and the patterns
 * controller's read permission, context filter and schema.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
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

// The posts made here go on the way out (their revisions and autosaves with them).
$made = [];
register_shutdown_function(static function () use (&$made): void {
    wp_set_current_user(1);
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ rev one', 'post_content' => 'v1', 'post_status' => 'publish']);
$page = wp_insert_post(['post_title' => 'ZZ rev page', 'post_content' => 'p1', 'post_status' => 'publish', 'post_type' => 'page']);
$locked = wp_insert_post(['post_title' => 'ZZ rev locked', 'post_content' => 'secret', 'post_excerpt' => 'hidden excerpt', 'post_status' => 'publish', 'post_password' => 'pw']);
foreach ([$post, $page, $locked] as $id) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture post\n");
        exit(1);
    }
    $made[] = $id;
}
wp_update_post(['ID' => $post, 'post_title' => 'ZZ rev two', 'post_content' => 'v2']);
wp_update_post(['ID' => $post, 'post_title' => 'ZZ rev three', 'post_content' => 'v3', 'post_excerpt' => 'ex']);
wp_update_post(['ID' => $page, 'post_title' => 'ZZ rev page two', 'post_content' => 'p2']);
$revisions = array_values(array_map(static fn ($revision) => $revision->ID, wp_get_post_revisions($post)));
$pageRevisions = array_values(array_map(static fn ($revision) => $revision->ID, wp_get_post_revisions($page)));
wp_set_current_user(0);

$ids = array_merge([$post, $page, $locked], $revisions, $pageRevisions);
rsort($ids);
$masked = static function ($value) use (&$ids) {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    // WP-CLI and the engine's runner may give the site URL either scheme (memory: the CLI forces it).
    $json = preg_replace(['/"(date|date_gmt|modified|modified_gmt)":"[0-9T:-]+"/', '/(preview_nonce=)[0-9a-f]+/', '/"preview_link":"https?:/'], ['"$1":"{date}"', '$1{nonce}', '"preview_link":"{scheme}:'], $json);
    return json_decode($json, true);
};

$controllers = [];
foreach (['post', 'page', 'wp_block'] as $type) {
    $controllers['revisions of ' . $type] = new WP_REST_Revisions_Controller($type);
    $controllers['autosaves of ' . $type] = new WP_REST_Autosaves_Controller($type);
}
$say('controllers', array_map(static fn ($controller) => ['namespace' => $prop($controller, 'namespace'), 'rest_base' => $prop($controller, 'rest_base'), 'parent_base' => $prop($controller, 'parent_base'), 'parent_post_type' => $prop($controller, 'parent_post_type'), 'meta' => is_object($prop($controller, 'meta')) ? get_class($prop($controller, 'meta')) : null, 'parent_controller' => is_object($prop($controller, 'parent_controller')) ? get_class($prop($controller, 'parent_controller')) : null, 'revisions_controller' => is_object($prop($controller, 'revisions_controller')) ? get_class($prop($controller, 'revisions_controller')) : null], $controllers));
$say('schemas', array_map(static fn ($controller) => $named($controller->get_item_schema()), $controllers));
$say('collection parameters', array_map(static fn ($controller) => $named($controller->get_collection_params()), $controllers));

register_post_type('zzrevs', ['public' => true, 'show_in_rest' => true, 'rest_base' => 'zz-revs', 'supports' => ['title', 'editor', 'revisions', 'autosave']]);
(new WP_REST_Revisions_Controller('zzrevs'))->register_routes();
(new WP_REST_Autosaves_Controller('zzrevs'))->register_routes();
$routes = rest_get_server()->get_routes();
$route = static fn (array $handlers) => array_map(static fn ($handler) => is_array($handler) ? $named(array_diff_key($handler, ['args' => true])) + ['args' => array_keys((array) ($handler['args'] ?? []))] : $handler, $handlers);
$say('routes registered', array_map(static fn ($pattern) => isset($routes[$pattern]) ? $route($routes[$pattern]) : null, [
    'revisions' => '/wp/v2/zz-revs/(?P<parent>[\d]+)/revisions',
    'a revision' => '/wp/v2/zz-revs/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)',
    'autosaves' => '/wp/v2/zz-revs/(?P<id>[\d]+)/autosaves',
    'an autosave' => '/wp/v2/zz-revs/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)',
]));

$revisionsOf = $controllers['revisions of post'];
$autosavesOf = $controllers['autosaves of post'];
$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $revisionsOf->get_collection_params()), static fn ($value) => $value !== null);
$base = '/wp/v2/posts/' . $post . '/revisions';
$checks = static function () use ($revisionsOf, $autosavesOf, $controllers, $shown, $request, $post, $page, $revisions, $base) {
    return [
        'list' => $shown($revisionsOf->get_items_permissions_check($request('GET', $base, ['parent' => $post]))),
        'list, a missing parent' => $shown($revisionsOf->get_items_permissions_check($request('GET', '/wp/v2/posts/999999/revisions', ['parent' => 999999]))),
        'list, a page as a post' => $shown($revisionsOf->get_items_permissions_check($request('GET', '/wp/v2/posts/' . $page . '/revisions', ['parent' => $page]))),
        'one' => $shown($revisionsOf->get_item_permissions_check($request('GET', $base . '/' . $revisions[0], ['parent' => $post, 'id' => $revisions[0]]))),
        'delete' => $shown($revisionsOf->delete_item_permissions_check($request('DELETE', $base . '/' . $revisions[0], ['parent' => $post, 'id' => $revisions[0]]))),
        'delete, a missing revision' => $shown($revisionsOf->delete_item_permissions_check($request('DELETE', $base . '/999999', ['parent' => $post, 'id' => 999999]))),
        'autosaves' => $shown($autosavesOf->get_items_permissions_check($request('GET', '/wp/v2/posts/' . $post . '/autosaves', ['id' => $post, 'parent' => $post]))),
        'autosave' => $shown($autosavesOf->create_item_permissions_check($request('POST', '/wp/v2/posts/' . $post . '/autosaves', ['id' => $post]))),
        'autosave, a missing post' => $shown($autosavesOf->create_item_permissions_check($request('POST', '/wp/v2/posts/999999/autosaves', ['id' => 999999]))),
    ];
};
$visitor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'an administrator' => $administrator]));

$listed = static fn (array $params) => $shown($revisionsOf->get_items($request('GET', $base, ['parent' => $post] + $params, $defaults)));
$say('revisions listed', $masked([
    'all' => $listed([]),
    'one a page' => $listed(['per_page' => 1]),
    'the second page' => $listed(['per_page' => 1, 'page' => 2]),
    'a page too far' => $listed(['per_page' => 1, 'page' => 9]),
    'an offset' => $listed(['per_page' => 1, 'offset' => 1]),
    'oldest first' => $listed(['order' => 'asc']),
    'by id' => $listed(['orderby' => 'id', 'order' => 'asc']),
    'included' => $listed(['include' => [$revisions[1]], 'orderby' => 'include']),
    'excluded' => $listed(['exclude' => [$revisions[0]]]),
    'a search' => $listed(['search' => 'two']),
    'by relevance with no search' => $listed(['orderby' => 'relevance']),
    'by include with none' => $listed(['orderby' => 'include']),
    'the page\'s, as a post' => $shown($revisionsOf->get_items($request('GET', '/wp/v2/posts/' . $page . '/revisions', ['parent' => $page], $defaults))),
    'the page\'s' => $shown($controllers['revisions of page']->get_items($request('GET', '/wp/v2/pages/' . $page . '/revisions', ['parent' => $page], $defaults))),
]));

$one = static fn (array $params, int $parent = 0, int $id = 0) => $shown($revisionsOf->get_item($request('GET', $base . '/' . ($id ?: $revisions[0]), ['parent' => $parent ?: $post, 'id' => $id ?: $revisions[0]] + $params)));
$say('a revision read', $masked([
    'view' => $one([]),
    'edit' => $one(['context' => 'edit']),
    'embed' => $one(['context' => 'embed']),
    'fields' => $one(['_fields' => 'id,title,_links']),
    'another parent' => $one([], 1),
    'missing' => $one([], 0, 999999),
    'a missing parent' => $one([], 999999),
    'prepared' => $shown($revisionsOf->prepare_item_for_response(get_post($revisions[0]), $request('GET', $base))),
    'got' => [get_class($call($revisionsOf, 'get_revision', $revisions[0])), $shown($call($revisionsOf, 'get_revision', $post)), $shown($call($revisionsOf, 'get_revision', 999999)), get_class($call($revisionsOf, 'get_parent', $post)), $shown($call($revisionsOf, 'get_parent', 999999))],
    'excerpts' => [$call($revisionsOf, 'prepare_excerpt_response', 'some excerpt', get_post($revisions[0])), $call($revisionsOf, 'prepare_excerpt_response', '', get_post($revisions[0])), $call($revisionsOf, 'prepare_excerpt_response', 'hidden', get_post($locked))],
    'dates' => [$call($revisionsOf, 'prepare_date_response', '2024-01-02 03:04:05'), $call($revisionsOf, 'prepare_date_response', '0000-00-00 00:00:00'), $call($revisionsOf, 'prepare_date_response', '2024-01-02 03:04:05', '2024-01-05 06:07:08')],
    'query' => [$call($revisionsOf, 'prepare_items_query', ['posts_per_page' => 5], $request('GET', $base)), $call($revisionsOf, 'prepare_items_query', ['orderby' => 'id'], $request('GET', $base, ['orderby' => 'id']))],
]));

$autosaves = static fn () => $shown($autosavesOf->get_items($request('GET', '/wp/v2/posts/' . $post . '/autosaves', ['id' => $post, 'parent' => $post])));
$life = ['none yet' => $autosaves()];
$created = $autosavesOf->create_item($request('POST', '/wp/v2/posts/' . $post . '/autosaves', ['id' => $post, 'title' => 'ZZ autosaved', 'content' => 'auto', 'excerpt' => 'ae']));
$autosave = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if ($autosave > 1) {
    $ids[] = $autosave;
    rsort($ids);
}
$life['created'] = $shown($created);
$life['listed'] = $autosaves();
$life['read'] = $shown($autosavesOf->get_item($request('GET', '/wp/v2/posts/' . $post . '/autosaves/' . $autosave, ['parent' => $post, 'id' => $autosave])));
$life['read, edit'] = $shown($autosavesOf->get_item($request('GET', '/wp/v2/posts/' . $post . '/autosaves/' . $autosave, ['parent' => $post, 'id' => $autosave, 'context' => 'edit'])));
$life['read, a missing one'] = $shown($autosavesOf->get_item($request('GET', '/wp/v2/posts/' . $post . '/autosaves/999999', ['parent' => $post, 'id' => 999999])));
$life['created again'] = $shown($autosavesOf->create_item($request('POST', '/wp/v2/posts/' . $post . '/autosaves', ['id' => $post, 'title' => 'ZZ autosaved again', 'content' => 'auto 2'])));
$life['no changes'] = $shown($autosavesOf->create_post_autosave(['ID' => $post, 'post_title' => 'ZZ autosaved again', 'post_content' => 'auto 2', 'post_excerpt' => 'ae']));
$life['prepared'] = $shown($autosavesOf->prepare_item_for_response(get_post($autosave), $request('GET', '/wp/v2/posts/' . $post . '/autosaves')));
$current = get_post($post);
$life['the same as the post'] = $shown($autosavesOf->create_post_autosave(['ID' => $post, 'post_title' => $current->post_title, 'post_content' => $current->post_content, 'post_excerpt' => $current->post_excerpt]));
$life['after the same'] = $autosaves();
$life['read, none'] = $shown($autosavesOf->get_item($request('GET', '/wp/v2/posts/' . $post . '/autosaves/1', ['parent' => $post, 'id' => 1])));
$life['read, a page with none'] = $shown($controllers['autosaves of page']->get_item($request('GET', '/wp/v2/pages/' . $page . '/autosaves/1', ['parent' => $page, 'id' => 1])));
$say('autosaves', $masked($life));

$doomed = $revisions[count($revisions) - 1];
$say('a revision deleted', $masked([
    'not forced' => $shown($revisionsOf->delete_item($request('DELETE', $base . '/' . $doomed, ['parent' => $post, 'id' => $doomed]))),
    'forced' => $shown($revisionsOf->delete_item($request('DELETE', $base . '/' . $doomed, ['parent' => $post, 'id' => $doomed, 'force' => true]))),
    'gone' => get_post($doomed) === null,
    'forced, a missing one' => $shown($revisionsOf->delete_item($request('DELETE', $base . '/999999', ['parent' => $post, 'id' => 999999, 'force' => true]))),
]));

$blocks = new WP_REST_Blocks_Controller('wp_block');
$pattern = wp_insert_post(['post_title' => 'ZZ rev pattern', 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'post_status' => 'publish', 'post_type' => 'wp_block']);
if (is_int($pattern) && $pattern > 1) {
    $made[] = $pattern;
}
$blockChecks = static fn () => [$blocks->check_read_permission(get_post($pattern)), $blocks->check_read_permission(get_post($post))];
$asAdministrator = $blockChecks();
wp_set_current_user(0);
$say('patterns', [
    'read, an administrator' => $asAdministrator,
    'read, a visitor' => $blockChecks(),
    'context' => [$blocks->filter_response_by_context(['title' => ['raw' => 'r', 'rendered' => 'x'], 'content' => ['raw' => 'c', 'rendered' => 'y', 'protected' => false, 'block_version' => 1], 'id' => 5], 'view'), $blocks->filter_response_by_context(['title' => ['raw' => 'r', 'rendered' => 'x'], 'content' => ['raw' => 'c', 'rendered' => 'y'], 'id' => 5], 'edit'), $blocks->filter_response_by_context(['title' => ['raw' => 'r', 'rendered' => 'x'], 'content' => ['raw' => 'c'], 'id' => 5], 'embed')],
    'schema' => $named($blocks->get_item_schema()),
]);

echo preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), "\n";
