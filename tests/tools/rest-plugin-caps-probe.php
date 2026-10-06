<?php
/**
 * Who may write a plugin's types and taxonomies over REST when they name
 * their own capabilities (probe rest-plugin-caps): a hierarchical and a
 * flat taxonomy with custom term capabilities, and a type with its own
 * capability type; an author granted none, each, or all of them, creating,
 * reading in the edit context, updating and deleting. Same protocol as
 * api-probe.php; dispatched in process; capabilities granted through
 * user_has_cap; everything it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$termCaps = ['manage_terms' => 'zz_manage', 'edit_terms' => 'zz_edit', 'delete_terms' => 'zz_delete', 'assign_terms' => 'zz_assign'];
register_taxonomy('zz_kind', ['post'], ['hierarchical' => true, 'show_in_rest' => true, 'capabilities' => $termCaps]);
register_taxonomy('zz_label', ['post'], ['hierarchical' => false, 'show_in_rest' => true, 'capabilities' => $termCaps]);
register_post_type('zz_doc', ['public' => true, 'show_in_rest' => true, 'capability_type' => ['zz_doc', 'zz_docs'], 'map_meta_cap' => true, 'supports' => ['title', 'editor', 'author']]);
if (did_action('rest_api_init')) {
    $GLOBALS['wp_rest_server'] = null;
}
$granted = [];
add_filter('user_has_cap', static function (array $all) use (&$granted): array {
    foreach ($granted as $cap) {
        $all[$cap] = true;
    }
    return $all;
});
$ids = [];
$send = static function (string $method, string $route, array $query = [], ?array $body = null) use (&$ids): array {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    if ($method === 'POST' && $response->get_status() === 201) {
        $ids[] = [(int) ($data['id'] ?? 0), (string) ($data['taxonomy'] ?? $data['type'] ?? '')];
    }
    return [$response->get_status(), $response->get_status() >= 400 ? ($data['code'] ?? null) : null];
};

$author = (int) (get_users(['role' => 'author', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
$made = ['terms' => [], 'posts' => []];
$fixture = [];
foreach (['zz_kind', 'zz_label'] as $taxonomy) {
    $fixture[$taxonomy] = (int) (wp_insert_term("Zz Caps {$taxonomy}", $taxonomy)['term_id'] ?? 0);
    $made['terms'][] = [$fixture[$taxonomy], $taxonomy];
}
foreach (['none' => [], 'manage' => ['zz_manage'], 'edit' => ['zz_edit'], 'assign' => ['zz_assign'], 'delete' => ['zz_delete'], 'all' => array_values($termCaps)] as $grant => $caps) {
    $granted = $caps;
    wp_set_current_user($author);
    $rows = [];
    foreach (['zz_kind', 'zz_label'] as $taxonomy) {
        $created = $send('POST', "/wp/v2/{$taxonomy}", [], ['name' => "Zz Caps {$grant} {$taxonomy}"]);
        $rows[$taxonomy] = [
            'create' => $created,
            'read edit' => $send('GET', "/wp/v2/{$taxonomy}/{$fixture[$taxonomy]}", ['context' => 'edit']),
            'update' => $send('POST', "/wp/v2/{$taxonomy}/{$fixture[$taxonomy]}", [], ['description' => "zz {$grant}"]),
        ];
        // The term just made, or the fixture when none was: deleting is judged either way.
        $last = end($ids);
        $target = $created[0] === 201 && is_array($last) ? $last[0] : $fixture[$taxonomy];
        $rows[$taxonomy]['delete'] = $send('DELETE', "/wp/v2/{$taxonomy}/{$target}", ['force' => 'true']);
        if ($target === $fixture[$taxonomy] && $rows[$taxonomy]['delete'][0] === 200) {
            $fixture[$taxonomy] = (int) (wp_insert_term("Zz Caps {$taxonomy} again {$grant}", $taxonomy)['term_id'] ?? 0);
            $made['terms'][] = [$fixture[$taxonomy], $taxonomy];
        }
    }
    $say("terms with {$grant}", $rows);
}

// The type: create, edit their own, edit the administrator's, publish, delete.
$adminDoc = (int) wp_insert_post(['post_type' => 'zz_doc', 'post_title' => 'Zz Caps Admin Doc', 'post_status' => 'publish', 'post_author' => 1]);
$made['posts'][] = $adminDoc;
foreach (['none' => [], 'edit' => ['edit_zz_docs'], 'edit and publish' => ['edit_zz_docs', 'publish_zz_docs'], 'edit others' => ['edit_zz_docs', 'edit_others_zz_docs'], 'delete' => ['edit_zz_docs', 'delete_zz_docs'], 'edit posts only' => ['edit_posts']] as $grant => $caps) {
    $granted = $caps;
    wp_set_current_user($author);
    $draft = $send('POST', '/wp/v2/zz_doc', [], ['title' => "Zz Caps Doc {$grant}", 'status' => 'draft']);
    $mine = $draft[0] === 201 ? (int) end($ids)[0] : (int) wp_insert_post(['post_type' => 'zz_doc', 'post_title' => "Zz Caps Doc {$grant} theirs", 'post_status' => 'draft', 'post_author' => $author]);
    $say("type with {$grant}", [
        'create draft' => $draft,
        'create published' => $send('POST', '/wp/v2/zz_doc', [], ['title' => "Zz Caps Doc {$grant} published", 'status' => 'publish']),
        'edit their own' => $mine ? $send('POST', "/wp/v2/zz_doc/{$mine}", [], ['title' => "Zz Caps Doc {$grant} edited"]) : null,
        'read the administrator\'s in edit' => $send('GET', "/wp/v2/zz_doc/{$adminDoc}", ['context' => 'edit']),
        'edit the administrator\'s' => $send('POST', "/wp/v2/zz_doc/{$adminDoc}", [], ['title' => 'Zz Caps Admin Doc']),
        'delete their own' => $mine ? $send('DELETE', "/wp/v2/zz_doc/{$mine}", ['force' => 'true']) : null,
    ]);
    $made['posts'][] = $mine;
}

// A type naming its own capability type maps no meta capabilities unless it says so.
register_post_type('zz_raw', ['public' => true, 'show_in_rest' => true, 'capability_type' => 'zz_raw']);
register_post_type('zz_pair', ['public' => true, 'capability_type' => ['zz_one', 'zz_ones']]);
$raw = (int) wp_insert_post(['post_type' => 'zz_raw', 'post_title' => 'Zz Caps Raw', 'post_status' => 'publish', 'post_author' => 1]);
$made['posts'][] = $raw;
$say('a type with its own capability type', [
    'map_meta_cap' => [get_post_type_object('zz_raw')->map_meta_cap, get_post_type_object('zz_pair')->map_meta_cap],
    'caps' => [array_keys((array) get_post_type_object('zz_raw')->cap), (array) get_post_type_object('zz_pair')->cap],
    'mapped' => [map_meta_cap('edit_post', $author, $raw), map_meta_cap('delete_post', $author, $raw), map_meta_cap('read_post', $author, $raw), map_meta_cap('edit_post', 1, $raw)],
]);
unregister_post_type('zz_pair');

$granted = [];
wp_set_current_user(0);
foreach ($ids as [$id, $kind]) {
    $made[in_array($kind, ['zz_kind', 'zz_label'], true) ? 'terms' : 'posts'][] = $kind === 'zz_doc' ? $id : [$id, $kind];
}
foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
    wp_delete_term($id, $taxonomy);
}
unregister_post_type('zz_doc');
unregister_post_type('zz_raw');
unregister_taxonomy('zz_kind');
unregister_taxonomy('zz_label');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
