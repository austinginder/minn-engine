<?php
/**
 * The REST registry controllers as classes (probe rest-registry-controllers):
 * WP_REST_Post_Types_Controller, WP_REST_Post_Statuses_Controller,
 * WP_REST_Taxonomies_Controller and WP_REST_Search_Controller called
 * directly, as a plugin would. Their schemas and parameters, the permission
 * checks for nobody and for an administrator, lists and single items in
 * each context (a type, status and taxonomy the probe registers among
 * them), items prepared with chosen fields, their links, a search through
 * each handler, and the search controller's subtype and handler helpers.
 * Registrations live for the request only; the probe's posts and terms are
 * removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'users' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $mask($item);
        }
        return $out;
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($host, '{host}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $mask($response)];
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links()), $mask($headers)];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $route, array $params = []) {
    $request = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
// Called directly, a list needs the defaults dispatch would have given it from the collection parameters.
$listing = static function ($controller, string $route, array $params = []) use ($request) {
    $out = $request($route, $params);
    $out->set_default_params(array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null));
    return $out;
};
// A protected method, called from inside its class.
$reach = static fn (object $controller, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $controller, get_class($controller))(...$args);
// A list keeps the core entries and the probe's own, so other plugins' registrations do not move it.
$core = static function ($response, array $keep) use ($answer) {
    if ($response instanceof WP_REST_Response && is_array($response->get_data())) {
        $response->set_data(array_intersect_key($response->get_data(), array_flip($keep)));
    }
    return $answer($response);
};

register_taxonomy('zzregtax', [], ['label' => 'Zz Reg Taxes', 'show_in_rest' => true, 'hierarchical' => true, 'rest_base' => 'zz-reg-taxes']);
register_post_type('zzregtype', ['label' => 'Zz Reg Types', 'public' => true, 'show_in_rest' => true, 'rest_base' => 'zz-reg-types', 'supports' => ['title', 'editor', 'thumbnail'], 'taxonomies' => ['zzregtax', 'category'], 'menu_icon' => 'dashicons-carrot', 'has_archive' => 'zz-reg-archive']);
register_post_type('zzreghidden', ['label' => 'Zz Reg Hidden', 'public' => true, 'show_in_rest' => false]);
register_taxonomy('zzreghiddentax', [], ['label' => 'Zz Reg Hidden Taxes', 'show_in_rest' => false]);
register_post_status('zzregstatus', ['label' => 'Zz Reg Status', 'public' => true, 'show_in_admin_all_list' => false]);
register_post_status('zzregprivate', ['label' => 'Zz Reg Private', 'private' => true]);
register_post_status('zzreginternal', ['label' => 'Zz Reg Internal', 'internal' => true]);

// A contributor may edit posts and assign categories but manage no terms: it tells the capabilities apart.
$contributor = wp_insert_user(['user_login' => 'zzregcontributor', 'user_email' => 'zzregcontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-registry-controllers: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;

$types = new WP_REST_Post_Types_Controller();
$statuses = new WP_REST_Post_Statuses_Controller();
$taxonomies = new WP_REST_Taxonomies_Controller();
$say('the classes', [get_parent_class($types), get_parent_class($statuses), get_parent_class($taxonomies), get_parent_class('WP_REST_Search_Controller')]);
$say('schemas and parameters', [
    'types' => [$types->get_item_schema(), $types->get_collection_params()],
    'statuses' => [$statuses->get_item_schema(), $statuses->get_collection_params()],
    'taxonomies' => [$taxonomies->get_item_schema(), $taxonomies->get_collection_params()],
]);

$typeKeep = ['post', 'page', 'attachment', 'wp_block', 'zzregtype'];
$statusKeep = ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'zzregstatus', 'zzregprivate', 'zzreginternal'];
$taxKeep = ['category', 'post_tag', 'zzregtax'];
foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", [
        'types' => [$try(static fn () => $types->get_items_permissions_check($request('/wp/v2/types'))), $try(static fn () => $types->get_items_permissions_check($request('/wp/v2/types', ['context' => 'edit'])))],
        'statuses' => [
            $try(static fn () => $statuses->get_items_permissions_check($request('/wp/v2/statuses'))),
            $try(static fn () => $statuses->get_items_permissions_check($request('/wp/v2/statuses', ['context' => 'edit']))),
            $try(static fn () => $statuses->get_item_permissions_check($request('/wp/v2/statuses/draft', ['status' => 'draft']))),
            $try(static fn () => $statuses->get_item_permissions_check($request('/wp/v2/statuses/publish', ['status' => 'publish']))),
            $try(static fn () => $statuses->get_item_permissions_check($request('/wp/v2/statuses/zz-none', ['status' => 'zz-none']))),
            array_map(static fn ($name) => $reach($statuses, 'check_read_permission', get_post_status_object($name)), ['publish', 'draft', 'trash', 'zzregstatus', 'zzregprivate', 'zzreginternal']),
        ],
        'taxonomies' => [
            $try(static fn () => $taxonomies->get_items_permissions_check($request('/wp/v2/taxonomies'))),
            $try(static fn () => $taxonomies->get_items_permissions_check($request('/wp/v2/taxonomies', ['context' => 'edit']))),
            $try(static fn () => $taxonomies->get_items_permissions_check($request('/wp/v2/taxonomies', ['type' => 'zzregtype', 'context' => 'edit']))),
            $try(static fn () => $taxonomies->get_item_permissions_check($request('/wp/v2/taxonomies/category', ['taxonomy' => 'category']))),
            $try(static fn () => $taxonomies->get_item_permissions_check($request('/wp/v2/taxonomies/category', ['taxonomy' => 'category', 'context' => 'edit']))),
            $try(static fn () => $taxonomies->get_item_permissions_check($request('/wp/v2/taxonomies/zz-none', ['taxonomy' => 'zz-none']))),
            $try(static fn () => $taxonomies->get_item_permissions_check($request('/wp/v2/taxonomies/zzreghiddentax', ['taxonomy' => 'zzreghiddentax']))),
        ],
    ]);
    $say("types, {$who}", [
        $core($types->get_items($listing($types, '/wp/v2/types')), $typeKeep),
        $core($types->get_items($listing($types, '/wp/v2/types', ['context' => 'edit'])), $typeKeep),
        $try(static fn () => $types->get_item($request('/wp/v2/types/zzregtype', ['type' => 'zzregtype', 'context' => 'edit']))),
        $try(static fn () => $types->get_item($request('/wp/v2/types/page', ['type' => 'page', 'context' => 'embed']))),
        $try(static fn () => $types->get_item($request('/wp/v2/types/zzreghidden', ['type' => 'zzreghidden']))),
        $try(static fn () => $types->get_item($request('/wp/v2/types/zz-none', ['type' => 'zz-none']))),
    ]);
    $say("statuses, {$who}", [
        $core($statuses->get_items($listing($statuses, '/wp/v2/statuses')), $statusKeep),
        $core($statuses->get_items($listing($statuses, '/wp/v2/statuses', ['context' => 'edit'])), $statusKeep),
        $try(static fn () => $statuses->get_item($request('/wp/v2/statuses/publish', ['status' => 'publish', 'context' => 'edit']))),
        $try(static fn () => $statuses->get_item($request('/wp/v2/statuses/draft', ['status' => 'draft']))),
        $try(static fn () => $statuses->get_item($request('/wp/v2/statuses/zzregstatus', ['status' => 'zzregstatus', 'context' => 'embed']))),
        $try(static fn () => $statuses->get_item($request('/wp/v2/statuses/zz-none', ['status' => 'zz-none']))),
    ]);
    $say("taxonomies, {$who}", [
        $core($taxonomies->get_items($listing($taxonomies, '/wp/v2/taxonomies')), $taxKeep),
        $core($taxonomies->get_items($listing($taxonomies, '/wp/v2/taxonomies', ['context' => 'edit'])), $taxKeep),
        $core($taxonomies->get_items($listing($taxonomies, '/wp/v2/taxonomies', ['type' => 'zzregtype'])), $taxKeep),
        $core($taxonomies->get_items($listing($taxonomies, '/wp/v2/taxonomies', ['type' => 'page'])), $taxKeep),
        $try(static fn () => $taxonomies->get_item($request('/wp/v2/taxonomies/zzregtax', ['taxonomy' => 'zzregtax', 'context' => 'edit']))),
        $try(static fn () => $taxonomies->get_item($request('/wp/v2/taxonomies/post_tag', ['taxonomy' => 'post_tag', 'context' => 'embed']))),
        $try(static fn () => $taxonomies->get_item($request('/wp/v2/taxonomies/zz-none', ['taxonomy' => 'zz-none']))),
        $try(static fn () => $taxonomies->get_item($request('/wp/v2/taxonomies/zzreghiddentax', ['taxonomy' => 'zzreghiddentax']))),
    ]);
}

$fields = static fn (array $fields, string $context = 'view') => (static function () use ($fields, $context) {
    $request = new WP_REST_Request('GET', '/wp/v2/zz');
    $request->set_param('_fields', implode(',', $fields));
    $request->set_param('context', $context);
    return $request;
})();
$say('items prepared', [
    $try(static fn () => $types->prepare_item_for_response(get_post_type_object('zzregtype'), $fields(['slug', 'name', 'rest_base', 'taxonomies', 'supports'], 'edit'))),
    $try(static fn () => $types->prepare_item_for_response(get_post_type_object('post'), $fields(['slug', 'labels.singular_name', 'icon']))),
    $try(static fn () => $types->prepare_item_for_response(get_post_type_object('zzreghidden'), $fields(['slug', 'rest_base', 'rest_namespace', 'taxonomies', 'template', 'template_lock', '_links'], 'edit'))),
    $try(static fn () => $statuses->prepare_item_for_response(get_post_status_object('zzregprivate'), $fields(['slug', 'private', 'protected', 'show_in_list'], 'edit'))),
    $try(static fn () => $statuses->prepare_item_for_response(get_post_status_object('future'), $fields(['name', 'date_floating']))),
    $try(static fn () => $taxonomies->prepare_item_for_response(get_taxonomy('zzregtax'), $fields(['slug', 'types', 'hierarchical', 'rest_base', 'visibility'], 'edit'))),
    $try(static fn () => $taxonomies->prepare_item_for_response(get_taxonomy('category'), $fields(['name', 'labels']))),
]);
// What registration gives a type's features: those declared ahead first, autosave wherever there is an editor.
add_post_type_support('zzregahead', 'editor');
register_post_type('zzregahead', ['supports' => ['title']]);
register_post_type('zzregnone', ['supports' => false]);
register_post_type('zzregdefault', []);
register_post_type('zzregargs', ['supports' => ['editor' => ['notes' => true], 'title']]);
register_post_type('zzregnamed', ['show_in_rest' => false, 'rest_namespace' => 'zz/v1']);
register_taxonomy('zzregnamedtax', [], ['show_in_rest' => false, 'rest_namespace' => 'zz/v1']);
$say('what registration gives', [
    array_map(static fn ($name) => get_all_post_type_supports($name), ['zzregahead', 'zzregnone', 'zzregdefault', 'zzregargs', 'zzregtype']),
    array_map(static fn ($name) => [get_post_type_object($name)->rest_base, get_post_type_object($name)->rest_namespace], ['zzregtype', 'zzreghidden', 'zzregnamed']),
    array_map(static fn ($name) => [get_taxonomy($name)->rest_base, get_taxonomy($name)->rest_namespace], ['zzregtax', 'zzreghiddentax', 'zzregnamedtax']),
]);
$say('links', [
    $mask($reach($types, 'prepare_links', get_post_type_object('zzregtype'))),
    $mask($reach($types, 'prepare_links', get_post_type_object('attachment'))),
    $mask($reach($taxonomies, 'prepare_links', get_taxonomy('zzregtax'))),
    $mask($reach($taxonomies, 'prepare_links', get_taxonomy('post_format'))),
]);

$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x']);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "rest-registry-controllers: {$title} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$post('zzregsearch older', ['post_date' => '1977-01-01 10:00:00']);
$post('zzregsearch page', ['post_type' => 'page', 'post_date' => '1977-02-01 10:00:00']);
$term = wp_insert_term('zzregsearch tag', 'post_tag');
if (!is_array($term)) {
    fwrite(STDERR, "rest-registry-controllers: the tag could not be made\n");
    exit(1);
}
$made['terms'][] = [(int) $term['term_id'], 'post_tag'];
$ids[(int) $term['term_id']] = 'zzregsearch-tag';

$search = new WP_REST_Search_Controller([new WP_REST_Post_Search_Handler(), new WP_REST_Term_Search_Handler(), new WP_REST_Post_Format_Search_Handler()]);
$say('the search schema and parameters', [$search->get_item_schema(), $search->get_collection_params()]);
wp_set_current_user(0);
$searched = [];
foreach ([
    'posts by a word' => ['search' => 'zzregsearch'],
    'pages only' => ['search' => 'zzregsearch', 'subtype' => ['page']],
    'terms by a word' => ['search' => 'zzregsearch', 'type' => 'term'],
    'post formats' => ['search' => 'gal', 'type' => 'post-format'],
    'paged past the end' => ['search' => 'zzregsearch', 'page' => 3, 'per_page' => 1],
    'chosen fields' => ['search' => 'zzregsearch', '_fields' => 'id,title'],
    'embedded context' => ['search' => 'zzregsearch', 'context' => 'embed'],
] as $label => $params) {
    // Dispatch would hand the handler subtypes as a list; called directly, the default stays a string.
    $searched[$label] = $try(static fn () => $search->get_items($listing($search, '/wp/v2/search', $params + ['subtype' => ['any']])));
}
$say('searches', $searched);
$say('the search helpers', [
    'permission' => $try(static fn () => $search->get_items_permission_check($request('/wp/v2/search'))),
    'subtypes' => array_map(static fn ($given) => $mask($search->sanitize_subtypes($given, $listing($search, '/wp/v2/search'), 'subtype')), [['post', 'page'], 'post,page', ['any'], ['post', 'zz-none'], ['category'], []]),
    'subtypes for terms' => $mask($search->sanitize_subtypes(['post_tag', 'any'], $listing($search, '/wp/v2/search', ['type' => 'term']), 'subtype')),
    'handlers' => array_map(static function ($type) use ($search, $listing, $answer, $reach) {
        $handler = $reach($search, 'get_search_handler', $listing($search, '/wp/v2/search', ['type' => $type]));
        return is_object($handler) ? get_class($handler) : $answer($handler);
    }, ['post', 'term', 'post-format', 'zz-none']),
    'an item prepared' => $try(static fn () => $search->prepare_item_for_response($made['posts'][0], $listing($search, '/wp/v2/search', ['_fields' => 'id,title,url,type,subtype', 'subtype' => ['any']]))),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
