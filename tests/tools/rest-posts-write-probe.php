<?php
/**
 * WP_REST_Posts_Controller, write side (probe rest-posts-write): the create,
 * update and delete permission checks for a visitor, a contributor and an
 * administrator; the status, template, featured media and terms helpers; a
 * request prepared for the database; and a post created, updated, trashed
 * and deleted through the controller.
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
$shown = static fn ($value) => is_wp_error($value) ? ['error' => $value->get_error_code(), 'message' => $value->get_error_message(), 'data' => $value->get_error_data()] : $value;
$request = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
wp_set_current_user(0);

// Rows made here are removed by id on the way out; the sticky list is put back.
$made = ['posts' => [], 'users' => []];
$sticky = get_option('sticky_posts');
register_shutdown_function(static function () use (&$made, $sticky): void {
    wp_set_current_user(1);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
    update_option('sticky_posts', $sticky);
});
$contributor = wp_insert_user(['user_login' => 'zz_rpw_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rpw@example.com', 'role' => 'contributor']);
if (!is_int($contributor) || $contributor <= 1) {
    fwrite(STDERR, "no contributor\n");
    exit(1);
}
$made['users'][] = $contributor;
wp_set_current_user(1);
$draft = wp_insert_post(['post_title' => 'ZZ rpw draft', 'post_status' => 'draft', 'post_author' => 1]);
$own = wp_insert_post(['post_title' => 'ZZ rpw contributor draft', 'post_status' => 'draft', 'post_author' => $contributor]);
foreach ([$draft, $own] as $id) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture post\n");
        exit(1);
    }
    $made['posts'][] = $id;
}
wp_set_current_user(0);

$masked = static function ($value, array $ids) {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES);
    foreach ($ids as $id) {
        // An id standing as a number becomes a string; inside a string it is replaced in place.
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id}', $json);
    }
    $json = preg_replace('/"(date|date_gmt|modified|modified_gmt)":"[0-9T:-]+"/', '"$1":"{date}"', $json);
    return json_decode($json, true);
};
$controller = new WP_REST_Posts_Controller('post');
$pages = new WP_REST_Posts_Controller('page');
$checks = static function () use ($controller, $call, $shown, $request, $draft, $own) {
    return [
        'create' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts'))),
        'create, with an id' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts', ['id' => 5]))),
        'create, for someone else' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts', ['author' => 1]))),
        'create, sticky' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts', ['sticky' => true]))),
        'create, with a category' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts', ['categories' => [1]]))),
        'create, with a missing category' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/posts', ['categories' => [999999]]))),
        'update another\'s draft' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/posts/' . $draft, ['id' => $draft]))),
        'update one\'s own draft' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/posts/' . $own, ['id' => $own]))),
        'update one\'s own draft, for someone else' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/posts/' . $own, ['id' => $own, 'author' => 1]))),
        'update one\'s own draft, sticky' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/posts/' . $own, ['id' => $own, 'sticky' => true]))),
        'update a missing post' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/posts/999999', ['id' => 999999]))),
        'delete another\'s draft' => $shown($controller->delete_item_permissions_check($request('DELETE', '/wp/v2/posts/' . $draft, ['id' => $draft]))),
        'delete one\'s own draft' => $shown($controller->delete_item_permissions_check($request('DELETE', '/wp/v2/posts/' . $own, ['id' => $own]))),
        'delete a missing post' => $shown($controller->delete_item_permissions_check($request('DELETE', '/wp/v2/posts/999999', ['id' => 999999]))),
        'terms' => [$call($controller, 'check_assign_terms_permission', $request('POST', '/wp/v2/posts', ['categories' => [1]])), $call($controller, 'check_assign_terms_permission', $request('POST', '/wp/v2/posts', ['tags' => [999999]])), $call($controller, 'check_assign_terms_permission', $request('POST', '/wp/v2/posts'))],
        'statuses' => array_map(static fn ($status) => $shown($call($controller, 'handle_status_param', $status, get_post_type_object('post'))), ['draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'publish' => 'publish', 'future' => 'future', 'trash' => 'trash', 'zz' => 'zz']),
        // The status validator reads the route's arguments, as dispatching sets them.
        'status checks' => array_map(static function ($status) use ($controller, $shown, $request) {
            $check = $request('POST', '/wp/v2/posts', ['status' => $status]);
            $check->set_attributes(['args' => $controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)]);
            return $shown($controller->check_status($status, $check, 'status'));
        }, ['draft' => 'draft', 'private' => 'private', 'publish' => 'publish', 'zz' => 'zz']),
        'status check, private with a password' => $shown($controller->check_status('private', (static function () use ($controller, $request) {
            $check = $request('POST', '/wp/v2/posts', ['status' => 'private', 'password' => 'zz']);
            $check->set_attributes(['args' => $controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)]);
            return $check;
        })(), 'status')),
    ];
};
$visitor = $checks();
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', ['a visitor' => $visitor, 'a contributor' => $asContributor, 'an administrator' => $administrator]);

$say('templates', [
    'none' => $shown($controller->check_template('', $request('POST', '/wp/v2/posts'))),
    'unknown' => $shown($controller->check_template('zz-none', $request('POST', '/wp/v2/posts'))),
    'unknown for a page' => $shown($pages->check_template('zz-none', $request('POST', '/wp/v2/pages'))),
    'a theme template for a page' => $shown($pages->check_template('page-no-title', $request('POST', '/wp/v2/pages'))),
    'the current one' => $shown($controller->check_template('zz-current', $request('PUT', '/wp/v2/posts/' . $draft, ['id' => $draft]))),
]);
$controller->handle_template('zz-unchecked', $draft);
$unchecked = get_post_meta($draft, '_wp_page_template', true);
$controller->handle_template('zz-checked', $draft, true);
$checked = get_post_meta($draft, '_wp_page_template', true);
$say('templates kept', [$unchecked, $checked]);
$say('featured media', [
    'a missing image' => $shown($call($controller, 'handle_featured_media', 999999, $draft)),
    'none' => $shown($call($controller, 'handle_featured_media', 0, $draft)),
]);
$termed = $call($controller, 'handle_terms', $draft, $request('PUT', '/wp/v2/posts/' . $draft, ['categories' => [1], 'tags' => []]));
$say('terms kept', [$shown($termed), wp_get_object_terms($draft, 'category', ['fields' => 'ids']), wp_get_object_terms($draft, 'post_tag', ['fields' => 'ids']), $shown($call($controller, 'handle_terms', $draft, $request('PUT', '/wp/v2/posts/' . $draft, ['tags' => [999999]]))), wp_get_object_terms($draft, 'post_tag', ['fields' => 'ids'])]);

$prepared = static function (array $params, string $method = 'POST') use ($controller, $call, $request, $draft) {
    $result = $call($controller, 'prepare_item_for_database', $request($method, $method === 'POST' ? '/wp/v2/posts' : '/wp/v2/posts/' . $draft, $params + ($method === 'POST' ? [] : ['id' => $draft])));
    return is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : (is_object($result) ? (array) $result : $result);
};
$say('prepared for the database', $masked([
    'nothing' => $prepared([]),
    'fields' => $prepared(['title' => 'ZZ title', 'content' => 'ZZ content', 'excerpt' => 'ZZ excerpt', 'slug' => 'zz-slug', 'status' => 'pending', 'password' => 'zz', 'comment_status' => 'closed', 'ping_status' => 'closed', 'format' => 'aside', 'template' => '']),
    'fields, in another order' => $prepared(['template' => 'zz', 'ping_status' => 'open', 'comment_status' => 'open', 'password' => '', 'status' => 'publish', 'slug' => 'Zz Slug!', 'excerpt' => 'e', 'content' => 'c', 'title' => 't', 'author' => 1, 'date' => '2024-01-02T03:04:05']),
    'sticky with a password' => $prepared(['sticky' => true, 'password' => 'zz']),
    'sticky' => $prepared(['sticky' => true]),
    'a password on a sticky post' => $prepared(['password' => 'zz'], 'PUT'),
    'a date and a GMT date' => $prepared(['date' => '2024-01-02T03:04:05', 'date_gmt' => '2024-01-02T08:04:05']),
    'an empty date' => $prepared(['date' => '']),
    'a bad date' => $prepared(['date' => 'zz']),
    'raw objects' => $prepared(['title' => ['raw' => 'ZZ raw title'], 'content' => ['raw' => 'ZZ raw content'], 'excerpt' => ['raw' => 'ZZ raw excerpt']]),
    'dates' => $prepared(['date' => '2024-01-02T03:04:05']),
    'a GMT date' => $prepared(['date_gmt' => '2024-01-02T03:04:05']),
    'an author' => $prepared(['author' => 1]),
    'a missing author' => $prepared(['author' => 999999]),
    'a status not allowed' => $prepared(['status' => 'zz']),
    'an update' => $prepared(['title' => 'ZZ updated'], 'PUT'),
    'a parent for a post' => $prepared(['parent' => 1]),
    'menu order for a post' => $prepared(['menu_order' => 3]),
    'a page with a parent and an order' => $try(static function () use ($pages, $call, $request) {
        $result = $call($pages, 'prepare_item_for_database', $request('POST', '/wp/v2/pages', ['parent' => 2, 'menu_order' => 3, 'template' => 'page-no-title']));
        return is_wp_error($result) ? ['error' => $result->get_error_code()] : (array) $result;
    }),
    'a page with everything' => $try(static function () use ($pages, $call, $request) {
        $result = $call($pages, 'prepare_item_for_database', $request('POST', '/wp/v2/pages', ['comment_status' => 'open', 'menu_order' => 2, 'parent' => 0, 'password' => 'p', 'author' => 1, 'slug' => 's', 'status' => 'draft', 'excerpt' => 'e', 'title' => 't', 'date' => '2024-01-02T03:04:05', 'ping_status' => 'closed', 'content' => 'c']));
        return is_wp_error($result) ? ['error' => $result->get_error_code()] : (array) $result;
    }),
    'a page with a missing parent' => $try(static function () use ($pages, $call, $request) {
        $result = $call($pages, 'prepare_item_for_database', $request('POST', '/wp/v2/pages', ['parent' => 999999]));
        return is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : (array) $result;
    }),
], [$draft, $own]));

$answer = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'data' => $response->get_error_data()] : ['status' => $response->get_status(), 'headers' => $response->get_headers(), 'data' => $response->get_data(), 'links' => array_keys($response->get_links())];
$created = $controller->create_item($request('POST', '/wp/v2/posts', ['title' => 'ZZ rpw created', 'content' => '<!-- wp:paragraph --><p>ZZ body</p><!-- /wp:paragraph -->', 'status' => 'draft', 'categories' => [1], 'meta' => ['footnotes' => '']]));
$id = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if (is_int($id) && $id > 1) {
    $made['posts'][] = $id;
}
$lifecycle = ['created' => $answer($created)];
$lifecycle['created again with an id'] = $answer($controller->create_item($request('POST', '/wp/v2/posts', ['id' => $id, 'title' => 'ZZ again'])));
$lifecycle['updated'] = $answer($controller->update_item($request('PUT', '/wp/v2/posts/' . $id, ['id' => $id, 'title' => 'ZZ rpw updated', 'status' => 'publish', 'sticky' => true, 'slug' => 'zz-rpw-updated'])));
$lifecycle['updated, a missing post'] = $answer($controller->update_item($request('PUT', '/wp/v2/posts/999999', ['id' => 999999, 'title' => 'x'])));
$lifecycle['trashed'] = $answer($controller->delete_item($request('DELETE', '/wp/v2/posts/' . $id, ['id' => $id])));
$lifecycle['trashed again'] = $answer($controller->delete_item($request('DELETE', '/wp/v2/posts/' . $id, ['id' => $id])));
$lifecycle['deleted'] = $answer($controller->delete_item($request('DELETE', '/wp/v2/posts/' . $id, ['id' => $id, 'force' => true])));
$lifecycle['deleted, a missing post'] = $answer($controller->delete_item($request('DELETE', '/wp/v2/posts/999999', ['id' => 999999, 'force' => true])));
$lifecycle['gone'] = get_post($id) === null;
$say('a post through its life', $masked($lifecycle, $id > 1 ? [$id] : []));
wp_set_current_user(0);

echo preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), "\n";
