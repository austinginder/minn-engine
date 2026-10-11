<?php
/**
 * WP_REST_Comments_Controller (probe rest-comments-controller): the
 * controller's base and meta; the routes it registers; its schema and
 * collection parameters; the permission checks for a visitor, a
 * contributor and an administrator; the query, status, author email,
 * content and target post helpers; comments read (view, edit, embed,
 * fields) and listed through it; the database fields a request makes;
 * and a comment created, updated, trashed and deleted through it.
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

// The post (its comments with it) and the user made here go on the way out.
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    wp_set_current_user(1);
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
$contributor = wp_insert_user(['user_login' => 'zz_rcc_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rcc@example.com', 'role' => 'contributor']);
$post = wp_insert_post(['post_title' => 'ZZ rcc post', 'post_content' => 'x', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'open']);
$closed = wp_insert_post(['post_title' => 'ZZ rcc closed', 'post_content' => 'x', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'closed']);
$draft = wp_insert_post(['post_title' => 'ZZ rcc draft', 'post_content' => 'x', 'post_status' => 'draft', 'post_author' => 1]);
foreach ([[$contributor, 'users'], [$post, 'posts'], [$closed, 'posts'], [$draft, 'posts']] as [$id, $kind]) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[$kind][] = $id;
}
$approved = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'An <b>approved</b> comment.', 'comment_author' => 'Zz Reader', 'comment_author_email' => 'reader@example.com', 'comment_author_url' => 'https://example.com/r', 'comment_approved' => 1, 'comment_date' => '2026-01-02 03:04:05', 'comment_date_gmt' => '2026-01-02 03:04:05']);
$held = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'A held comment.', 'comment_author' => 'Zz Held', 'comment_author_email' => 'held@example.com', 'comment_approved' => 0, 'comment_date' => '2026-01-03 03:04:05', 'comment_date_gmt' => '2026-01-03 03:04:05']);
$reply = wp_insert_comment(['comment_post_ID' => $post, 'comment_parent' => $approved, 'comment_content' => 'A reply.', 'comment_author' => 'admin', 'user_id' => 1, 'comment_approved' => 1, 'comment_date' => '2026-01-04 03:04:05', 'comment_date_gmt' => '2026-01-04 03:04:05']);
$own = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'The contributor\'s.', 'comment_author' => 'zz', 'user_id' => $contributor, 'comment_approved' => 0, 'comment_date' => '2026-01-05 03:04:05', 'comment_date_gmt' => '2026-01-05 03:04:05']);
$ids = [$contributor, $post, $closed, $draft, $approved, $held, $reply, $own];
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    $json = preg_replace(['/"(date|date_gmt)":"20[0-9T:-]+"/', '/(avatar\/)[0-9a-f]{64}/'], ['"$1":"{date}"', '$1{hash}'], $json);
    return json_decode($json, true);
};

$comments = new WP_REST_Comments_Controller();
$say('controller', ['namespace' => $prop($comments, 'namespace'), 'rest_base' => $prop($comments, 'rest_base'), 'meta' => is_object($prop($comments, 'meta')) ? get_class($prop($comments, 'meta')) : null]);
$say('schema', $named($comments->get_item_schema()));
$say('collection parameters', $named($comments->get_collection_params()));
$copy = new WP_REST_Comments_Controller();
Closure::bind(function (): void { $this->namespace = 'zz-rcc/v1'; }, $copy, WP_REST_Comments_Controller::class)();
$copy->register_routes();
$routes = rest_get_server()->get_routes();
$route = static fn (array $handlers) => array_map(static fn ($handler) => is_array($handler) ? $named(array_diff_key($handler, ['args' => true])) + ['args' => array_keys((array) ($handler['args'] ?? []))] : $handler, $handlers);
$say('routes registered', array_map(static fn ($pattern) => isset($routes[$pattern]) ? $route($routes[$pattern]) : null, ['comments' => '/zz-rcc/v1/comments', 'a comment' => '/zz-rcc/v1/comments/(?P<id>[\d]+)']));

$checks = static function () use ($comments, $call, $shown, $request, $post, $closed, $draft, $approved, $held, $own) {
    return [
        'list' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments'))),
        'list, a post' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['post' => [$post]]))),
        'list, a draft' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['post' => [$draft]]))),
        'list, edit' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['context' => 'edit']))),
        'list, held ones' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['status' => 'hold']))),
        'list, by email' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['author_email' => 'reader@example.com']))),
        'list, by type' => $shown($comments->get_items_permissions_check($request('GET', '/wp/v2/comments', ['type' => 'pingback']))),
        'one approved' => $shown($comments->get_item_permissions_check($request('GET', '/wp/v2/comments/' . $approved, ['id' => $approved]))),
        'one held' => $shown($comments->get_item_permissions_check($request('GET', '/wp/v2/comments/' . $held, ['id' => $held]))),
        'one\'s own held' => $shown($comments->get_item_permissions_check($request('GET', '/wp/v2/comments/' . $own, ['id' => $own]))),
        'one, edit' => $shown($comments->get_item_permissions_check($request('GET', '/wp/v2/comments/' . $approved, ['id' => $approved, 'context' => 'edit']))),
        'one, missing' => $shown($comments->get_item_permissions_check($request('GET', '/wp/v2/comments/999999', ['id' => 999999]))),
        'create' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $post, 'content' => 'x']))),
        'create on a closed post' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $closed, 'content' => 'x']))),
        'create on a draft' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $draft, 'content' => 'x']))),
        'create on a missing post' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => 999999, 'content' => 'x']))),
        'create, as someone else' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $post, 'content' => 'x', 'author' => 1]))),
        'create, a status' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $post, 'content' => 'x', 'status' => 'approved']))),
        'create, an IP' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['post' => $post, 'content' => 'x', 'author_ip' => '127.0.0.9']))),
        'create, with no post' => $shown($comments->create_item_permissions_check($request('POST', '/wp/v2/comments', ['content' => 'x']))),
        'update' => $shown($comments->update_item_permissions_check($request('PUT', '/wp/v2/comments/' . $approved, ['id' => $approved]))),
        'update one\'s own' => $shown($comments->update_item_permissions_check($request('PUT', '/wp/v2/comments/' . $own, ['id' => $own]))),
        'update a missing one' => $shown($comments->update_item_permissions_check($request('PUT', '/wp/v2/comments/999999', ['id' => 999999]))),
        'delete' => $shown($comments->delete_item_permissions_check($request('DELETE', '/wp/v2/comments/' . $approved, ['id' => $approved]))),
        'read the post' => [$call($comments, 'check_read_post_permission', get_post($post), $request('GET', '/wp/v2/comments')), $call($comments, 'check_read_post_permission', get_post($draft), $request('GET', '/wp/v2/comments'))],
        'read the comment' => [$call($comments, 'check_read_permission', get_comment($approved), $request('GET', '/wp/v2/comments')), $call($comments, 'check_read_permission', get_comment($held), $request('GET', '/wp/v2/comments')), $call($comments, 'check_read_permission', get_comment($own), $request('GET', '/wp/v2/comments'))],
        'edit the comment' => [$call($comments, 'check_edit_permission', get_comment($approved)), $call($comments, 'check_edit_permission', get_comment($own))],
        'target post' => [$shown($call($comments, 'check_target_post_permission', $post, $request('POST', '/wp/v2/comments'))), $shown($call($comments, 'check_target_post_permission', $draft, $request('POST', '/wp/v2/comments'))), $shown($call($comments, 'check_target_post_permission', 999999, $request('POST', '/wp/v2/comments')))],
    ];
};
$visitor = $checks();
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'a contributor' => $asContributor, 'an administrator' => $administrator]));

$say('helpers', $masked([
    'query parameters' => array_map(static fn ($param) => $call($comments, 'normalize_query_param', $param), ['author' => 'author', 'author_ip' => 'author_ip', 'parent' => 'parent', 'post' => 'post', 'search' => 'search', 'type' => 'type', 'id' => 'id', 'zz' => 'zz', 'author_email' => 'author_email', 'include' => 'include']),
    'statuses' => array_map(static fn ($value) => $call($comments, 'prepare_status_response', $value), ['1' => '1', '0' => '0', 'spam' => 'spam', 'trash' => 'trash', 'zz' => 'zz', 'approve' => 'approve']),
    'author emails' => [$shown($comments->check_comment_author_email('a@example.com', $request('POST', '/wp/v2/comments'), 'author_email')), $shown($comments->check_comment_author_email('', $request('POST', '/wp/v2/comments'), 'author_email')), $shown($comments->check_comment_author_email('not-an-email', $request('POST', '/wp/v2/comments'), 'author_email'))],
    'content allowed' => [$call($comments, 'check_is_comment_content_allowed', ['comment_content' => 'x']), $call($comments, 'check_is_comment_content_allowed', ['comment_content' => '']), $call($comments, 'check_is_comment_content_allowed', ['comment_content' => '  ']), $call($comments, 'check_is_comment_content_allowed', ['comment_content' => '', 'comment_type' => 'note']), $call($comments, 'check_is_comment_content_allowed', ['comment_content' => '<p></p>'])],
    'got' => [get_class($call($comments, 'get_comment', $approved)), $shown($call($comments, 'get_comment', 999999)), $shown($call($comments, 'get_comment', 0))],
]));

$one = static fn (array $params, int $id = 0) => $shown($comments->get_item($request('GET', '/wp/v2/comments/' . ($id ?: $approved), ['id' => $id ?: $approved] + $params)));
$say('reading', $masked([
    'a comment' => $one([]),
    'embedded' => $one(['context' => 'embed']),
    'edited' => $one(['context' => 'edit']),
    'fields' => $one(['_fields' => 'id,content,_links']),
    'fields without links' => $one(['_fields' => 'id,post']),
    'a reply' => $one([], $reply),
    'held' => $one(['context' => 'edit'], $held),
    'missing' => $one([], 999999),
    'prepared' => $shown($comments->prepare_item_for_response(get_comment($approved), $request('GET', '/wp/v2/comments'))),
    'links' => $call($comments, 'prepare_links', get_comment($reply)),
]));

$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $comments->get_collection_params()), static fn ($value) => $value !== null);
$listed = static fn (array $params) => $shown($comments->get_items($request('GET', '/wp/v2/comments', $params, $defaults)));
$say('listing', $masked([
    'the post\'s' => $listed(['post' => [$post]]),
    'every status' => $listed(['post' => [$post], 'status' => 'all', 'context' => 'edit']),
    'held ones' => $listed(['post' => [$post], 'status' => 'hold']),
    'a page' => $listed(['post' => [$post], 'per_page' => 1, 'page' => 2]),
    'replies' => $listed(['post' => [$post], 'parent' => [$approved]]),
    'top level' => $listed(['post' => [$post], 'parent' => [0]]),
    'by author' => $listed(['post' => [$post], 'author' => [1]]),
    'oldest first' => $listed(['post' => [$post], 'order' => 'asc']),
    'a search' => $listed(['post' => [$post], 'search' => 'reply']),
    'some fields' => $listed(['post' => [$post], '_fields' => 'id,author_name']),
]));

$prepared = static fn (array $params, string $method = 'POST') => $try(static function () use ($params, $method, $comments, $call, $request, $approved) {
    $result = $call($comments, 'prepare_item_for_database', $request($method, '/wp/v2/comments' . ($method === 'POST' ? '' : '/' . $approved), $params + ($method === 'POST' ? [] : ['id' => $approved])));
    return is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message()] : $result;
});
$say('prepared for the database', $masked([
    'nothing' => $prepared([]),
    'fields' => $prepared(['content' => 'C', 'post' => $post, 'parent' => $approved, 'author' => 1, 'author_name' => 'N', 'author_email' => 'e@example.com', 'author_url' => 'https://example.com', 'author_ip' => '127.0.0.5', 'author_user_agent' => 'UA', 'type' => 'comment', 'date' => '2024-01-02T03:04:05']),
    'raw content' => $prepared(['content' => ['raw' => 'R']]),
    'a GMT date' => $prepared(['date_gmt' => '2024-01-02T03:04:05']),
    'a missing author' => $prepared(['author' => 999999]),
    'an update' => $prepared(['content' => 'U'], 'PUT'),
]));

$life = [];
$created = $comments->create_item($request('POST', '/wp/v2/comments', ['post' => $post, 'parent' => 0, 'content' => 'ZZ rcc created comment', 'author_name' => 'Zz Created', 'author_email' => 'created@example.com']));
$createdId = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if ($createdId > 1) {
    $ids[] = $createdId;
}
$life['created'] = $shown($created);
$life['created again'] = $shown($comments->create_item($request('POST', '/wp/v2/comments', ['post' => $post, 'parent' => 0, 'content' => 'ZZ rcc created comment', 'author_name' => 'Zz Created', 'author_email' => 'created@example.com'])));
$life['created with an id'] = $shown($comments->create_item($request('POST', '/wp/v2/comments', ['id' => 5, 'post' => $post, 'content' => 'x'])));
$life['created with no content'] = $shown($comments->create_item($request('POST', '/wp/v2/comments', ['post' => $post, 'parent' => 0, 'content' => ''])));
$life['updated'] = $shown($comments->update_item($request('PUT', '/wp/v2/comments/' . $createdId, ['id' => $createdId, 'content' => 'ZZ rcc updated', 'status' => 'hold'])));
$life['updated, a missing one'] = $shown($comments->update_item($request('PUT', '/wp/v2/comments/999999', ['id' => 999999, 'content' => 'x'])));
$life['status moved'] = [$call($comments, 'handle_status_param', 'approved', $createdId), get_comment($createdId)->comment_approved, $call($comments, 'handle_status_param', 'zz', $createdId), $call($comments, 'handle_status_param', 'approved', $createdId)];
$life['trashed'] = $shown($comments->delete_item($request('DELETE', '/wp/v2/comments/' . $createdId, ['id' => $createdId])));
$life['trashed again'] = $shown($comments->delete_item($request('DELETE', '/wp/v2/comments/' . $createdId, ['id' => $createdId])));
$life['deleted'] = $shown($comments->delete_item($request('DELETE', '/wp/v2/comments/' . $createdId, ['id' => $createdId, 'force' => true])));
$life['gone'] = get_comment($createdId) === null;
wp_set_current_user(0);
$say('a comment through its life', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
