<?php
/**
 * The meta registry outside REST, as the reference answers it (probe
 * meta-api): what register_meta returns and keeps for each kind of
 * argument (a default, callbacks, show_in_rest as a list, a subtype, a
 * default that does not fit its type, revisions on a type without them,
 * the register_meta_args filter), the filters a registration adds and an
 * unregistration takes away, defaults read through get_metadata single and
 * not, sanitize and auth callbacks through sanitize_meta and
 * current_user_can, get_registered_metadata, get_object_subtype, and
 * register_term_meta / unregister_term_meta. Same protocol as
 * api-probe.php; what it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, preg_replace('/\s*\(This message was added in version [^)]+\.\)/', '', wp_strip_all_tags((string) $message))];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$take = static function () use (&$wrong): array {
    $out = $wrong;
    $wrong = [];
    return $out;
};
// A registration's kept arguments, callbacks named by kind.
$kept = static function (string $type, string $key, string $subtype = '') {
    $args = get_registered_meta_keys($type, $subtype)[$key] ?? null;
    if (!is_array($args)) {
        return $args;
    }
    foreach ($args as $name => $value) {
        if ($value instanceof Closure) {
            $args[$name] = 'closure';
        }
    }
    return $args;
};

$sanitizer = static fn ($value) => 'clean:' . $value;
$auth = static fn ($allowed, $key, $id, $user) => $key === 'zz_a_auth' && $user === 1;
$results = [
    'plain' => register_meta('post', 'zz_a_plain', []),
    'string with default' => register_meta('post', 'zz_a_default', ['type' => 'string', 'single' => true, 'default' => 'dflt']),
    'list with default' => register_meta('post', 'zz_a_list_default', ['type' => 'string', 'single' => false, 'default' => 'one']),
    'callbacks' => register_meta('post', 'zz_a_auth', ['single' => true, 'sanitize_callback' => $sanitizer, 'auth_callback' => $auth]),
    'subtype' => register_meta('post', 'zz_a_sub', ['object_subtype' => 'page', 'single' => true, 'default' => 'page-dflt']),
    'rest as a list' => register_meta('post', 'zz_a_rest', ['single' => true, 'type' => 'integer', 'show_in_rest' => ['name' => 'zz_alias', 'schema' => ['minimum' => 1]]]),
    'default of the wrong type' => register_meta('post', 'zz_a_badfault', ['type' => 'integer', 'single' => true, 'default' => 'text']),
    'unknown type' => register_meta('post', 'zz_a_badtype', ['type' => 'decimal']),
    'array without items in rest' => register_meta('post', 'zz_a_arr', ['type' => 'array', 'single' => true, 'show_in_rest' => true]),
    'revisions without show_in_rest' => register_meta('post', 'zz_a_rev', ['object_subtype' => 'post', 'single' => true, 'revisions_enabled' => true]),
    'revisions on a type without them' => register_meta('post', 'zz_a_rev2', ['object_subtype' => 'attachment', 'single' => true, 'show_in_rest' => true, 'revisions_enabled' => true]),
    'deprecated arguments' => register_meta('post', 'zz_a_old', 'sanitize_text_field', '__return_true'),
    'revisions on terms' => register_meta('term', 'zz_a_rev3', ['single' => true, 'revisions_enabled' => true]),
    'rest default checked against its schema' => register_meta('post', 'zz_a_enumdef', ['single' => true, 'type' => 'string', 'default' => 'c', 'show_in_rest' => ['schema' => ['enum' => ['a', 'b']]]]),
    'term meta' => register_term_meta('category', 'zz_a_term', ['single' => true, 'default' => 'tdef']),
    'term meta on every taxonomy' => register_term_meta('', 'zz_a_terms', ['single' => true]),
    'unknown object type' => register_meta('zz_thing', 'zz_a_thing', ['single' => true]),
];
$say('register_meta returns', [$results, $take()]);
add_filter('register_meta_args', static fn ($args, $defaults, $type, $key) => $key === 'zz_a_filtered' ? ['single' => true, 'zz_extra' => 1] + $args : $args, 10, 4);
$say('register_meta_args filter', [register_meta('post', 'zz_a_filtered', ['type' => 'boolean']), $kept('post', 'zz_a_filtered')]);
remove_all_filters('register_meta_args');

foreach (['zz_a_plain', 'zz_a_default', 'zz_a_list_default', 'zz_a_auth', 'zz_a_rest', 'zz_a_badfault', 'zz_a_arr', 'zz_a_old'] as $key) {
    $say("kept {$key}", $kept('post', $key));
}
$say('kept subtype', [$kept('post', 'zz_a_sub', 'page'), $kept('post', 'zz_a_sub'), $kept('post', 'zz_a_rev', 'post'), $kept('post', 'zz_a_rev2', 'attachment')]);
$say('kept term', [$kept('term', 'zz_a_term', 'category'), $kept('term', 'zz_a_terms')]);
$say('filters added', [
    'sanitize plain' => has_filter('sanitize_post_meta_zz_a_auth'),
    'sanitize subtype' => has_filter('sanitize_post_meta_zz_a_auth_for_post'),
    'auth plain' => has_filter('auth_post_meta_zz_a_auth'),
    'auth subtype' => has_filter('auth_post_meta_zz_a_auth_for_post'),
    'default' => has_filter('default_post_metadata', 'filter_default_metadata'),
    'deprecated sanitize' => has_filter('sanitize_post_meta_zz_a_old', 'sanitize_text_field'),
    'deprecated auth' => has_filter('auth_post_meta_zz_a_old', '__return_true'),
    'refused: bad default' => [has_filter('auth_post_meta_zz_a_badfault'), has_filter('sanitize_post_meta_zz_a_badfault')],
    'refused: array without items' => has_filter('auth_post_meta_zz_a_arr'),
    'refused: revisions' => [has_filter('auth_post_meta_zz_a_rev2_for_attachment'), has_filter('auth_term_meta_zz_a_rev3')],
    'refused: enum default' => has_filter('auth_post_meta_zz_a_enumdef'),
    'kept: unknown type' => has_filter('auth_post_meta_zz_a_badtype'),
]);

wp_set_current_user(1);
$post = (int) wp_insert_post(['post_title' => 'Zz Meta Api', 'post_status' => 'draft']);
$page = (int) wp_insert_post(['post_title' => 'Zz Meta Api Page', 'post_type' => 'page', 'post_status' => 'draft']);
$say('defaults read', [
    'single' => get_post_meta($post, 'zz_a_default', true),
    'all' => get_post_meta($post, 'zz_a_default'),
    'list single' => get_post_meta($post, 'zz_a_list_default', true),
    'list all' => get_post_meta($post, 'zz_a_list_default'),
    'subtype on its type' => get_post_meta($page, 'zz_a_sub', true),
    'subtype on another type' => get_post_meta($post, 'zz_a_sub', true),
    'exists' => metadata_exists('post', $post, 'zz_a_default'),
    'raw' => get_metadata_raw('post', $post, 'zz_a_default', true),
    'default function' => [get_metadata_default('post', $post, 'zz_a_default', true), get_metadata_default('post', $post, 'zz_a_list_default', false)],
    'wrong type default' => get_post_meta($post, 'zz_a_badfault', true),
    'term default' => get_term_meta(1, 'zz_a_term', true),
]);
update_post_meta($post, 'zz_a_auth', 'dirty');
$say('sanitize through the callback', [get_post_meta($post, 'zz_a_auth', true), sanitize_meta('zz_a_auth', 'x', 'post', 'post'), sanitize_meta('zz_a_auth', 'x', 'post', 'page'), sanitize_meta('zz_a_old', ' <b>y</b> ', 'post')]);
$author = (int) (get_users(['role' => 'author', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
register_meta('post', 'zz_a_refuse', ['single' => true, 'auth_callback' => '__return_false']);
register_meta('post', '_zz_a_prot', ['single' => true, 'auth_callback' => '__return_true']);
register_meta('post', 'zz_a_subauth', ['object_subtype' => 'page', 'single' => true, 'auth_callback' => '__return_false']);
$comment = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'Zz meta api comment', 'comment_approved' => 1]);
$say('meta capabilities', [
    'subtype auth filter' => [has_filter('auth_post_meta_zz_a_subauth_for_page'), has_filter('auth_post_meta_zz_a_subauth')],
    'admin, callback refuses' => current_user_can('edit_post_meta', $post, 'zz_a_refuse'),
    'admin, protected with auth' => current_user_can('edit_post_meta', $post, '_zz_a_prot'),
    'admin, no key' => current_user_can('edit_post_meta', $post),
    'admin, missing post' => current_user_can('edit_post_meta', 999999, 'zz_a_plain'),
    'admin, subtype refuses on its type' => current_user_can('edit_post_meta', $page, 'zz_a_subauth'),
    'admin, subtype key on another type' => current_user_can('edit_post_meta', $post, 'zz_a_subauth'),
    'admin, delete and add' => [current_user_can('delete_post_meta', $post, 'zz_a_plain'), current_user_can('add_post_meta', $post, '_zz_a_none')],
    'admin, term meta' => [current_user_can('edit_term_meta', 1, 'zz_a_term'), current_user_can('edit_term_meta', 1, '_zz_x'), current_user_can('edit_term_meta', 999999, 'zz_a_term')],
    'admin, comment meta' => [current_user_can('edit_comment_meta', $comment, 'zz_x'), current_user_can('edit_comment_meta', $comment, '_zz_x')],
    'admin, user meta' => [current_user_can('edit_user_meta', 1, 'zz_x'), current_user_can('edit_user_meta', 1, '_zz_x')],
    'author, own user meta' => [user_can($author, 'edit_user_meta', $author, 'zz_x'), user_can($author, 'edit_user_meta', 1, 'zz_x')],
    'author, term meta' => user_can($author, 'edit_term_meta', 1, 'zz_x'),
    'map' => [map_meta_cap('edit_post_meta', 1, $post, 'zz_a_refuse'), map_meta_cap('edit_term_meta', 1, 1, '_zz_x'), map_meta_cap('edit_user_meta', $author, $author), map_meta_cap('edit_comment_meta', 1, 999999)],
]);
wp_delete_comment($comment, true);
foreach (['zz_a_refuse', '_zz_a_prot'] as $key) {
    unregister_meta_key('post', $key);
}
unregister_meta_key('post', 'zz_a_subauth', 'page');
$say('auth through the callback', [
    'admin' => current_user_can('edit_post_meta', $post, 'zz_a_auth'),
    'admin, plain key' => current_user_can('edit_post_meta', $post, 'zz_a_plain'),
    'admin, protected unregistered' => current_user_can('edit_post_meta', $post, '_zz_a_none'),
    'author' => user_can($author, 'edit_post_meta', $post, 'zz_a_auth'),
    'author, deprecated auth' => user_can($author, 'edit_post_meta', $post, 'zz_a_old'),
]);
update_post_meta($post, 'zz_a_default', 'mine');
add_post_meta($post, 'zz_a_plain', 'p1');
add_post_meta($post, 'zz_a_plain', 'p2');
$say('get_registered_metadata', [
    function_exists('get_registered_metadata'),
    function_exists('get_registered_metadata') ? get_registered_metadata('post', $post, 'zz_a_default') : null,
    function_exists('get_registered_metadata') ? get_registered_metadata('post', $post, 'zz_a_plain') : null,
    function_exists('get_registered_metadata') ? get_registered_metadata('post', $post, 'zz_a_none') : null,
    function_exists('get_registered_metadata') ? array_keys((array) get_registered_metadata('post', $post)) : null,
]);
$say('get_object_subtype', [
    function_exists('get_object_subtype'),
    function_exists('get_object_subtype') ? [get_object_subtype('post', $page), get_object_subtype('term', 1), get_object_subtype('user', 1), get_object_subtype('comment', 1), get_object_subtype('post', 999999), get_object_subtype('zz', 1)] : null,
]);
$say('unregister', [
    unregister_meta_key('post', 'zz_a_auth'),
    has_filter('sanitize_post_meta_zz_a_auth'),
    has_filter('auth_post_meta_zz_a_auth'),
    unregister_meta_key('post', 'zz_a_auth'),
    function_exists('unregister_term_meta') ? unregister_term_meta('category', 'zz_a_term') : null,
    unregister_meta_key('post', 'zz_a_default'),
    get_post_meta($page, 'zz_a_default', true),
    unregister_post_meta('page', 'zz_a_sub'),
    $take(),
]);

wp_set_current_user(0);
wp_delete_post($post, true);
wp_delete_post($page, true);
foreach (['zz_a_plain', 'zz_a_list_default', 'zz_a_rest', 'zz_a_badfault', 'zz_a_arr', 'zz_a_old', 'zz_a_filtered'] as $key) {
    unregister_meta_key('post', $key);
}
unregister_meta_key('post', 'zz_a_rev', 'post');
unregister_meta_key('post', 'zz_a_rev2', 'attachment');
unregister_meta_key('term', 'zz_a_terms');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
