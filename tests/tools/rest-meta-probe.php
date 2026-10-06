<?php
/**
 * Registered meta over REST, as the reference serves it (probe rest-meta):
 * each type's value read back (integer, boolean, number, string, a list,
 * an array, an object, a renamed key, an edit-only key, a prepare
 * callback, an enum, a protected key, a default), stored values that do
 * not fit their type, the meta on pages, categories, users and comments,
 * and writes: values cast, refused (wrong type, out of the enum, a
 * protected key), renamed, removed with null, unknown keys ignored, as an
 * administrator and as an author on their own post. Same protocol as
 * api-probe.php; dispatched in process; everything it makes goes at the
 * end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$string = ['show_in_rest' => true, 'single' => true, 'type' => 'string'];
register_meta('post', 'zz_m_general', $string + ['default' => 'zz-def']);
register_post_meta('post', 'zz_m_int', ['show_in_rest' => true, 'single' => true, 'type' => 'integer']);
register_post_meta('post', 'zz_m_int_def', ['show_in_rest' => true, 'single' => true, 'type' => 'integer', 'default' => 7]);
register_post_meta('post', 'zz_m_bool', ['show_in_rest' => true, 'single' => true, 'type' => 'boolean']);
register_post_meta('post', 'zz_m_num', ['show_in_rest' => true, 'single' => true, 'type' => 'number']);
register_post_meta('post', 'zz_m_list', ['show_in_rest' => true, 'single' => false, 'type' => 'string']);
register_post_meta('post', 'zz_m_ints', ['show_in_rest' => true, 'single' => false, 'type' => 'integer']);
register_post_meta('post', 'zz_m_arr', ['single' => true, 'type' => 'array', 'show_in_rest' => ['schema' => ['items' => ['type' => 'string']]]]);
register_post_meta('post', 'zz_m_obj', ['single' => true, 'type' => 'object', 'show_in_rest' => ['schema' => ['properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'string']]]]]);
register_post_meta('post', 'zz_m_named', ['single' => true, 'type' => 'string', 'show_in_rest' => ['name' => 'zz_m_alias']]);
register_post_meta('post', 'zz_m_edit', ['single' => true, 'type' => 'string', 'show_in_rest' => ['schema' => ['context' => ['edit']]]]);
register_post_meta('post', 'zz_m_prep', ['single' => true, 'type' => 'string', 'show_in_rest' => ['prepare_callback' => static fn ($value, $request, $args) => 'prep:' . $value . ':' . ($request instanceof WP_REST_Request ? 'request' : gettype($request)) . ':' . ($args['name'] ?? '')]]);
register_post_meta('post', 'zz_m_enum', ['single' => true, 'type' => 'string', 'show_in_rest' => ['schema' => ['enum' => ['a', 'b']]]]);
register_post_meta('post', '_zz_m_protected', $string);
register_post_meta('post', '_zz_m_prot_auth', $string + ['auth_callback' => '__return_true']);
register_post_meta('post', 'zz_m_hidden', ['show_in_rest' => false, 'single' => true, 'type' => 'string']);
register_post_meta('page', 'zz_m_page', $string);
register_term_meta('category', 'zz_m_term', $string);
register_meta('user', 'zz_m_user', $string);
register_meta('comment', 'zz_m_comment', ['show_in_rest' => true, 'single' => true, 'type' => 'integer']);
$keys = ['zz_m_general', 'zz_m_int', 'zz_m_int_def', 'zz_m_bool', 'zz_m_num', 'zz_m_list', 'zz_m_ints', 'zz_m_arr', 'zz_m_obj', 'zz_m_named', 'zz_m_edit', 'zz_m_prep', 'zz_m_enum', '_zz_m_protected', '_zz_m_prot_auth', 'zz_m_hidden'];

$send = static function (string $method, string $route, array $query = [], ?array $body = null): array {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    return ['status' => $response->get_status(), 'data' => $data];
};
// An answer's meta, or its error.
$meta = static function (array $answer): array {
    $data = (array) $answer['data'];
    return $answer['status'] >= 400 ? [$answer['status'], $data] : [$answer['status'], $data['meta'] ?? 'absent'];
};
// What the store holds for the probe's keys.
$stored = static function (int $id) use ($keys): array {
    $out = [];
    foreach ($keys as $key) {
        $values = get_post_meta($id, $key);
        if ($values !== []) {
            $out[$key] = $values;
        }
    }
    return $out;
};

$made = ['posts' => [], 'comments' => []];
wp_set_current_user(1);
$full = (int) wp_insert_post(['post_title' => 'Zz Meta Full', 'post_status' => 'publish']);
$empty = (int) wp_insert_post(['post_title' => 'Zz Meta Empty', 'post_status' => 'publish']);
$odd = (int) wp_insert_post(['post_title' => 'Zz Meta Odd', 'post_status' => 'publish']);
$page = (int) wp_insert_post(['post_title' => 'Zz Meta Page', 'post_type' => 'page', 'post_status' => 'publish']);
array_push($made['posts'], $full, $empty, $odd, $page);
foreach (['zz_m_int' => '5', 'zz_m_bool' => '1', 'zz_m_num' => '1.5', 'zz_m_arr' => ['x', 'y'], 'zz_m_obj' => ['a' => 1, 'b' => 'c'], 'zz_m_named' => 'n', 'zz_m_edit' => 'e', 'zz_m_prep' => 'p', 'zz_m_enum' => 'a', '_zz_m_protected' => 'secret', '_zz_m_prot_auth' => 'open', 'zz_m_hidden' => 'h', 'zz_m_general' => 'set'] as $key => $value) {
    add_post_meta($full, $key, $value);
}
add_post_meta($full, 'zz_m_list', 'b');
add_post_meta($full, 'zz_m_list', 'a');
add_post_meta($full, 'zz_m_ints', '3');
add_post_meta($full, 'zz_m_ints', 'x');
foreach (['zz_m_int' => 'abc', 'zz_m_bool' => 'no', 'zz_m_num' => 'x', 'zz_m_arr' => 'not a list', 'zz_m_obj' => 'not an object', 'zz_m_enum' => 'z', 'zz_m_int_def' => ''] as $key => $value) {
    add_post_meta($odd, $key, $value);
}
add_post_meta($page, 'zz_m_page', 'pg');

$say('full view', $meta($send('GET', '/wp/v2/posts/' . $full)));
$say('full edit', $meta($send('GET', '/wp/v2/posts/' . $full, ['context' => 'edit'])));
$say('empty view', $meta($send('GET', '/wp/v2/posts/' . $empty)));
$say('odd view', $meta($send('GET', '/wp/v2/posts/' . $odd)));
$picked = (array) $send('GET', '/wp/v2/posts/' . $full, ['_fields' => 'meta.zz_m_int,meta.zz_m_alias'])['data'];
$say('one key by _fields', [array_keys($picked), $picked['meta'] ?? null]);
$say('page view', $meta($send('GET', '/wp/v2/pages/' . $page)));

$comment = (int) wp_insert_comment(['comment_post_ID' => $full, 'comment_content' => 'Zz meta comment', 'comment_approved' => 1, 'user_id' => 1]);
$made['comments'][] = $comment;
add_comment_meta($comment, 'zz_m_comment', '12');
add_term_meta(1, 'zz_m_term', 'tm');
add_user_meta(1, 'zz_m_user', 'um');
$say('category view', $meta($send('GET', '/wp/v2/categories/1')));
$say('user view', $meta($send('GET', '/wp/v2/users/1')));
$say('user edit', $meta($send('GET', '/wp/v2/users/1', ['context' => 'edit'])));
$say('comment view', $meta($send('GET', '/wp/v2/comments/' . $comment)));

// Writes, one at a time, each read back with what the store then holds.
$writes = [
    'integer as text' => ['zz_m_int' => '9'],
    'integer refused' => ['zz_m_int' => 'abc'],
    'boolean false' => ['zz_m_bool' => false],
    'boolean as text' => ['zz_m_bool' => 'true'],
    'number' => ['zz_m_num' => 2.25],
    'list replaced' => ['zz_m_list' => ['c', 'a']],
    'list of one as a scalar' => ['zz_m_list' => 'solo'],
    'integers' => ['zz_m_ints' => ['4', 5]],
    'array' => ['zz_m_arr' => ['p', 'q']],
    'array refused' => ['zz_m_arr' => [1, ['x']]],
    'object' => ['zz_m_obj' => ['a' => 2, 'b' => 'z']],
    'object with an extra' => ['zz_m_obj' => ['a' => 3, 'zz' => 1]],
    'renamed key' => ['zz_m_alias' => 'renamed'],
    'stored name refused' => ['zz_m_named' => 'direct'],
    'enum refused' => ['zz_m_enum' => 'z'],
    'protected refused' => ['_zz_m_protected' => 'x'],
    'protected with auth' => ['_zz_m_prot_auth' => 'y'],
    'hidden ignored' => ['zz_m_hidden' => 'x'],
    'unknown ignored' => ['zz_m_nobody' => 'x'],
    'same value again' => ['zz_m_int' => 9],
    'removed with null' => ['zz_m_int' => null, 'zz_m_list' => null],
    'several at once, one refused' => ['zz_m_num' => 3, 'zz_m_enum' => 'nope', 'zz_m_edit' => 'changed'],
    'general key' => ['zz_m_general' => 'zz-def'],
];
foreach ($writes as $label => $body) {
    $answer = $send('POST', '/wp/v2/posts/' . $full, [], ['meta' => $body]);
    $say("write: {$label}", [$meta($answer), $stored($full)]);
}
$say('meta not an object', $meta($send('POST', '/wp/v2/posts/' . $full, [], ['meta' => 'zz'])));
$say('page write', $meta($send('POST', '/wp/v2/pages/' . $page, [], ['meta' => ['zz_m_page' => 'pg2', 'zz_m_int' => 1]])));
$say('category write', $meta($send('POST', '/wp/v2/categories/1', [], ['meta' => ['zz_m_term' => 'tm2']])));
$say('comment write', $meta($send('POST', '/wp/v2/comments/' . $comment, [], ['meta' => ['zz_m_comment' => '13']])));
$say('user write', $meta($send('POST', '/wp/v2/users/1', [], ['meta' => ['zz_m_user' => 'um2']])));
$created = $send('POST', '/wp/v2/posts', [], ['title' => 'Zz Meta Created', 'status' => 'draft', 'meta' => ['zz_m_int' => 4, 'zz_m_list' => ['n']]]);
$made['posts'][] = (int) ($created['data']['id'] ?? 0);
$say('create with meta', [$meta($created), $stored((int) ($created['data']['id'] ?? 0))]);

// An author, on their own post and on the administrator's.
$author = (int) (get_users(['role' => 'author', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
if ($author > 0) {
    $own = (int) wp_insert_post(['post_title' => 'Zz Meta Author', 'post_status' => 'draft', 'post_author' => $author]);
    $made['posts'][] = $own;
    wp_set_current_user($author);
    $say('author writes their own', $meta($send('POST', '/wp/v2/posts/' . $own, [], ['meta' => ['zz_m_int' => 2, '_zz_m_prot_auth' => 'a']])));
    $say('author writes a protected key', $meta($send('POST', '/wp/v2/posts/' . $own, [], ['meta' => ['_zz_m_protected' => 'a']])));
    wp_set_current_user(1);
}

wp_set_current_user(0);
foreach (array_reverse($made['comments']) as $id) {
    wp_delete_comment($id, true);
}
foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
delete_term_meta(1, 'zz_m_term');
delete_user_meta(1, 'zz_m_user');
foreach ($keys as $key) {
    unregister_meta_key('post', $key, 'post');
}
unregister_meta_key('post', 'zz_m_general');
unregister_meta_key('post', 'zz_m_page', 'page');
unregister_meta_key('term', 'zz_m_term', 'category');
unregister_meta_key('user', 'zz_m_user');
unregister_meta_key('comment', 'zz_m_comment');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
