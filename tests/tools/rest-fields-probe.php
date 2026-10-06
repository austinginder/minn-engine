<?php
/**
 * Fields plugin code adds with register_rest_field, as the reference
 * serves them (probe rest-fields): where they sit in an item, what the get
 * callback is handed (the item so far, the field's name, the request, the
 * object type), which contexts and _fields lists leave them out, a field
 * on several types at once, and the update callback a write runs (handed
 * the value, the saved object, the name, the request and the type; an
 * error it returns fails the write). Posts, pages, categories, users,
 * comments, media and a plugin's type and taxonomy. Same protocol as
 * api-probe.php; dispatched in process as an administrator; everything it
 * makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_taxonomy('zz_shelf', ['zz_tome'], ['show_in_rest' => true]);
register_post_type('zz_tome', ['public' => true, 'show_in_rest' => true, 'supports' => ['title', 'editor'], 'taxonomies' => ['zz_shelf']]);

$seen = [];
$describe = static function (string $field) use (&$seen): Closure {
    return static function ($item, $name, $request, $type) use ($field, &$seen) {
        $seen[] = [
            'field' => $field,
            'item keys' => is_array($item) ? array_keys($item) : gettype($item),
            'name' => $name,
            'request' => $request instanceof WP_REST_Request ? [$request->get_method(), $request->get_route(), $request['context'] ?? null] : gettype($request),
            'type' => $type,
        ];
        return $field . ':' . (is_array($item) ? ($item['id'] ?? $item['slug'] ?? '') : '');
    };
};
$updates = [];
$update = static function ($value, $object, $name, $request, $type) use (&$updates) {
    $updates[] = [
        'value' => $value,
        'object' => is_object($object) ? get_class($object) : gettype($object),
        'name' => $name,
        'request' => $request instanceof WP_REST_Request ? [$request->get_method(), $request->get_route()] : gettype($request),
        'type' => $type,
    ];
    if ($value === 'zz-refuse') {
        return new WP_Error('zz_refused', 'Zz refused', ['status' => 418]);
    }
    return true;
};

// Registered as plugins register them, as the server starts (after the site's own).
add_action('rest_api_init', static function () use ($describe, $update): void {
    register_rest_field('post', 'zz_plain', ['get_callback' => $describe('zz_plain')]);
    register_rest_field('post', 'zz_edit_only', ['get_callback' => $describe('zz_edit_only'), 'schema' => ['type' => 'string', 'context' => ['edit']]]);
    register_rest_field('post', 'zz_writable', ['get_callback' => $describe('zz_writable'), 'update_callback' => $update, 'schema' => ['type' => 'string', 'context' => ['view', 'edit']]]);
    register_rest_field('post', 'zz_no_getter', ['update_callback' => $update]);
    register_rest_field(['page', 'category', 'user', 'comment', 'attachment', 'zz_tome', 'zz_shelf'], 'zz_shared', ['get_callback' => $describe('zz_shared'), 'update_callback' => $update, 'schema' => ['type' => 'string', 'context' => ['view', 'edit', 'embed']]]);
}, 100);
if (did_action('rest_api_init')) {
    // A server already started gets the fields with the next one.
    $GLOBALS['wp_rest_server'] = null;
}

$home = home_url();
$ids = [];
$mask = static function ($value) use (&$ids, $home) {
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $json = str_replace([$home, addcslashes($home, '/')], '{home}', $json);
    $json = (string) preg_replace('/"(date|date_gmt|modified|modified_gmt)":"[0-9T:-]+"/', '"$1":"{date}"', $json);
    foreach ($ids as $name => $id) {
        if ($id > 0) {
            $json = (string) preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{' . $name . '}"', $json);
            $json = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $json);
        }
    }
    return json_decode($json, true);
};
$send = static function (string $method, string $route, array $query = [], ?array $body = null): array {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    return ['status' => $response->get_status(), 'data' => rest_get_server()->response_to_data($response, false)];
};
// What an item carries of the fields, the keys around them, and what the callbacks saw.
$fieldsOf = static function (array $item): array {
    $keys = array_keys($item);
    return [
        'tail keys' => array_slice($keys, -6),
        'values' => array_intersect_key($item, array_flip(['zz_plain', 'zz_edit_only', 'zz_writable', 'zz_no_getter', 'zz_shared'])),
    ];
};
$take = static function () use (&$seen, &$updates): array {
    $out = ['seen' => $seen, 'updates' => $updates];
    $seen = [];
    $updates = [];
    return $out;
};
wp_set_current_user(1);

$made = ['posts' => [], 'terms' => [], 'comments' => []];
$post = (int) wp_insert_post(['post_title' => 'Zz Fields Post', 'post_status' => 'publish', 'post_content' => 'zz']);
$page = (int) wp_insert_post(['post_title' => 'Zz Fields Page', 'post_type' => 'page', 'post_status' => 'publish']);
$tome = (int) wp_insert_post(['post_title' => 'Zz Fields Tome', 'post_type' => 'zz_tome', 'post_status' => 'publish']);
$shelf = (int) (wp_insert_term('Zz Fields Shelf', 'zz_shelf')['term_id'] ?? 0);
$comment = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'Zz fields comment', 'comment_approved' => 1, 'user_id' => 1]);
array_push($made['posts'], $post, $page, $tome);
$made['terms'][] = [$shelf, 'zz_shelf'];
$made['comments'][] = $comment;
$ids = ['post' => $post, 'page' => $page, 'tome' => $tome, 'shelf' => $shelf, 'comment' => $comment];

$single = $send('GET', '/wp/v2/posts/' . $post);
$say('post view', $mask([$fieldsOf($single['data']), $take()]));
$edit = $send('GET', '/wp/v2/posts/' . $post, ['context' => 'edit']);
$say('post edit', $mask([$fieldsOf($edit['data']), $take()]));
$embed = $send('GET', '/wp/v2/posts/' . $post, ['context' => 'embed']);
$say('post embed', $mask([$fieldsOf($embed['data']), $take()]));
$picked = $send('GET', '/wp/v2/posts/' . $post, ['_fields' => 'id,zz_writable']);
$say('post _fields', $mask([$picked['data'], $take()]));
$list = $send('GET', '/wp/v2/posts', ['include' => (string) $post]);
$say('post list', $mask([array_map($fieldsOf, $list['data']), $take()]));

foreach (['page' => '/wp/v2/pages/' . $page, 'category' => '/wp/v2/categories/1', 'user' => '/wp/v2/users/1', 'comment' => '/wp/v2/comments/' . $comment, 'tome' => '/wp/v2/zz_tome/' . $tome, 'shelf' => '/wp/v2/zz_shelf/' . $shelf] as $kind => $route) {
    $got = $send('GET', $route);
    $say("{$kind} view", $mask([$got['status'], $fieldsOf((array) $got['data']), $take()]));
}
$embedded = $send('GET', '/wp/v2/zz_tome/' . $tome, ['context' => 'embed']);
$say('tome embed', $mask([$fieldsOf((array) $embedded['data']), $take()]));

// Writes run the update callbacks for the fields the body names.
$updated = $send('POST', '/wp/v2/posts/' . $post, [], ['title' => 'Zz Fields Post Two', 'zz_writable' => 'zz-new', 'zz_no_getter' => 'zz-hidden']);
$say('post update', $mask([$updated['status'], $fieldsOf((array) $updated['data']), $take()]));
$refused = $send('POST', '/wp/v2/posts/' . $post, [], ['zz_writable' => 'zz-refuse']);
$say('post update refused', $mask([$refused, get_post($post)->post_title, $take()]));
$created = $send('POST', '/wp/v2/posts', [], ['title' => 'Zz Fields Created', 'status' => 'draft', 'zz_writable' => 'zz-born']);
$ids['created'] = (int) ($created['data']['id'] ?? 0);
$made['posts'][] = $ids['created'];
$say('post create', $mask([$created['status'], $fieldsOf((array) $created['data']), $take()]));
$untouched = $send('POST', '/wp/v2/posts/' . $post, [], ['excerpt' => 'zz']);
$say('post update without the field', $mask([$untouched['status'], $take()]));

// Terms and comments: the context their writes and deletes are answered in.
$shelfMade = $send('POST', '/wp/v2/zz_shelf', [], ['name' => 'Zz Fields Shelf Two', 'zz_shared' => 'zz-term']);
$ids['shelf2'] = (int) ($shelfMade['data']['id'] ?? 0);
$made['terms'][] = [$ids['shelf2'], 'zz_shelf'];
$say('term create', $mask([$shelfMade['status'], $fieldsOf((array) $shelfMade['data']), $take()]));
$shelfChanged = $send('POST', '/wp/v2/zz_shelf/' . $ids['shelf2'], [], ['description' => 'zz', 'zz_shared' => 'zz-term-two']);
$say('term update', $mask([$shelfChanged['status'], $fieldsOf((array) $shelfChanged['data']), $take()]));
$shelfGone = $send('DELETE', '/wp/v2/zz_shelf/' . $ids['shelf2'], ['force' => 'true']);
$say('term delete', $mask([$shelfGone['status'], $fieldsOf((array) ($shelfGone['data']['previous'] ?? [])), $take()]));
$commentChanged = $send('POST', '/wp/v2/comments/' . $comment, [], ['content' => 'Zz fields comment two', 'zz_shared' => 'zz-comment']);
$say('comment update', $mask([$commentChanged['status'], $fieldsOf((array) $commentChanged['data']), $take()]));
$commentGone = $send('DELETE', '/wp/v2/comments/' . $comment, ['force' => 'true']);
$say('comment delete', $mask([$commentGone['status'], $fieldsOf((array) ($commentGone['data']['previous'] ?? [])), $take()]));
$tomeGone = $send('DELETE', '/wp/v2/zz_tome/' . $tome);
$say('tome trash', $mask([$tomeGone['status'], $fieldsOf((array) $tomeGone['data']), $take()]));

wp_set_current_user(0);
foreach (array_reverse($made['comments']) as $id) {
    wp_delete_comment($id, true);
}
foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach ($made['terms'] as [$id, $taxonomy]) {
    wp_delete_term($id, $taxonomy);
}
foreach ($GLOBALS['wp_rest_additional_fields'] as $type => $fields) {
    foreach (['zz_plain', 'zz_edit_only', 'zz_writable', 'zz_no_getter', 'zz_shared'] as $field) {
        unset($GLOBALS['wp_rest_additional_fields'][$type][$field]);
    }
}
unregister_post_type('zz_tome');
unregister_taxonomy('zz_shelf');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
