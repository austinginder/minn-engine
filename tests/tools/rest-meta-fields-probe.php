<?php
/**
 * The REST meta field classes (probe rest-meta-fields): WP_REST_Meta_Fields
 * and its post, term, user and comment kinds. Meta of every shape is
 * registered (single and multiple, string, integer, boolean, object, array,
 * a default, a REST name, a context, hidden, protected); each class's meta
 * type, subtype and REST field type, its registered fields and field schema,
 * the value read for an object (in view and edit), values written (changed,
 * unchanged, deleted, refused by the schema, not allowed), the static value
 * preparer and the array check, and the protected helpers reached through a
 * subclass. The probe's objects and meta are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'users' => [], 'comments' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, 'category');
        }
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id, 1);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$shape = static function ($value) use (&$shape) {
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code(), 'status' => $value->get_error_data()['status'] ?? null, 'more' => array_values(array_diff($value->get_error_codes(), [$value->get_error_code()]))];
    }
    return is_array($value) ? array_map($shape, $value) : $value;
};
// default_additional_properties_to_false announces its deprecation; heard, not printed.
$heard = [];
add_filter('deprecated_function_trigger_error', '__return_false');
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$heard) {
    $heard[] = [$function, $replacement, $version];
}, 10, 3);
$fail = static function (string $what) {
    fwrite(STDERR, "rest-meta-fields: {$what} could not be made\n");
    exit(1);
};

register_post_meta('post', 'zz_rmf_string', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'default' => 'the default', 'description' => 'A string.']);
register_post_meta('post', 'zz_rmf_ints', ['type' => 'integer', 'single' => false, 'show_in_rest' => true]);
register_post_meta('post', 'zz_rmf_flag', ['type' => 'boolean', 'single' => true, 'show_in_rest' => ['name' => 'zz_rmf_renamed']]);
register_post_meta('post', 'zz_rmf_object', ['type' => 'object', 'single' => true, 'show_in_rest' => ['schema' => ['type' => 'object', 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'integer']]]]]);
register_post_meta('post', 'zz_rmf_list', ['type' => 'array', 'single' => true, 'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]]]);
register_post_meta('post', 'zz_rmf_edit_only', ['type' => 'string', 'single' => true, 'show_in_rest' => ['schema' => ['context' => ['edit']]]]);
register_post_meta('post', 'zz_rmf_hidden', ['type' => 'string', 'single' => true, 'show_in_rest' => false]);
register_post_meta('post', '_zz_rmf_protected', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_false']);
register_post_meta('page', 'zz_rmf_page_only', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
register_term_meta('category', 'zz_rmf_term', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
register_meta('user', 'zz_rmf_user', ['type' => 'integer', 'single' => true, 'show_in_rest' => true, 'default' => 3]);
register_meta('comment', 'zz_rmf_comment', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);

wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'zz rmf post', 'post_status' => 'publish']);
is_int($post) && $post > 1 ? $made['posts'][] = $post : $fail('the post');
$term = wp_insert_term('zz rmf term', 'category');
is_array($term) ? $made['terms'][] = (int) $term['term_id'] : $fail('the term');
$user = wp_insert_user(['user_login' => 'zz_rmf_user', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rmf@example.com', 'role' => 'editor']);
is_int($user) && $user > 1 ? $made['users'][] = $user : $fail('the user');
$comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz rmf comment', 'comment_approved' => 1]);
is_int($comment) && $comment > 1 ? $made['comments'][] = $comment : $fail('the comment');
add_post_meta($post, 'zz_rmf_ints', 4);
add_post_meta($post, 'zz_rmf_ints', 5);
update_post_meta($post, 'zz_rmf_flag', '1');
update_post_meta($post, 'zz_rmf_object', ['a' => 'x', 'b' => 2]);
update_post_meta($post, 'zz_rmf_edit_only', 'secret-ish');
update_post_meta($post, '_zz_rmf_protected', 'locked');
update_term_meta((int) $term['term_id'], 'zz_rmf_term', 'term value');
update_comment_meta($comment, 'zz_rmf_comment', 'comment value');

// The protected helpers, reached through a subclass.
class ZZ_RMF_Post extends WP_REST_Post_Meta_Fields
{
    public function reach(string $method, ...$args)
    {
        return $this->$method(...$args);
    }

    public static function reachStatic(string $method, ...$args)
    {
        return static::$method(...$args);
    }
}

$kinds = [
    'post' => new WP_REST_Post_Meta_Fields('post'), 'page' => new WP_REST_Post_Meta_Fields('page'), 'term' => new WP_REST_Term_Meta_Fields('category'),
    'user' => new WP_REST_User_Meta_Fields(), 'comment' => new WP_REST_Comment_Meta_Fields(), 'tag' => new WP_REST_Term_Meta_Fields('post_tag'), 'genre' => new WP_REST_Term_Meta_Fields('zz_rmf_genre'),
];
$identity = [];
foreach ($kinds as $label => $fields) {
    $reach = Closure::bind(fn () => [$this->get_meta_type(), $this->get_meta_subtype(), $this->get_rest_field_type()], $fields, get_class($fields));
    $identity[$label] = $reach();
}
$say('meta type, subtype and REST field type', $identity);

$view = new WP_REST_Request('GET', '/wp/v2/posts/' . $post);
$view->set_param('context', 'view');
$edit = new WP_REST_Request('GET', '/wp/v2/posts/' . $post);
$edit->set_param('context', 'edit');
$probe = new ZZ_RMF_Post('post');
$fieldKeys = static fn ($fields) => array_map(static fn ($field) => array_intersect_key((array) $field, array_flip(['type', 'single', 'default', 'show_in_rest'])), (array) $fields);
$say('registered fields and the schema', [
    array_keys((array) $probe->reach('get_registered_fields')), $fieldKeys(array_intersect_key((array) $probe->reach('get_registered_fields'), array_flip(['zz_rmf_string', 'zz_rmf_flag']))),
    $kinds['post']->get_field_schema(), array_keys((array) $kinds['page']->get_field_schema()['properties']),
]);
$say('values read', $shape([
    $kinds['post']->get_value($post, $view), $kinds['post']->get_value($post, $edit), $kinds['page']->get_value($post, $view),
    $kinds['term']->get_value((int) $term['term_id'], $view), $kinds['user']->get_value($user, $view), $kinds['comment']->get_value($comment, $view), $kinds['post']->get_value(999999, $view),
]));

$writes = [];
foreach ([
    'a changed string and ints' => ['zz_rmf_string' => 'written', 'zz_rmf_ints' => [7, 8, 8]],
    'the same again' => ['zz_rmf_string' => 'written'],
    'a renamed flag' => ['zz_rmf_renamed' => false],
    'a deletion' => ['zz_rmf_string' => null],
    'an object and a list' => ['zz_rmf_object' => ['a' => 'y', 'b' => 3], 'zz_rmf_list' => ['p', 'q']],
    'a refused type' => ['zz_rmf_object' => 'not an object'],
    'a refused list item' => ['zz_rmf_ints' => ['nope']],
    'a protected key' => ['_zz_rmf_protected' => 'changed'],
    'a hidden key' => ['zz_rmf_hidden' => 'ignored'],
    'an unknown key' => ['zz_rmf_unknown' => 'ignored'],
    'two failures' => ['zz_rmf_object' => 'bad', '_zz_rmf_protected' => 'changed'],
] as $label => $meta) {
    $writes[$label] = [$shape($kinds['post']->update_value($meta, $post)), $shape($kinds['post']->get_value($post, $edit))];
}
wp_set_current_user(0);
$writes['signed out'] = $shape($kinds['post']->update_value(['zz_rmf_string' => 'nobody'], $post));
wp_set_current_user(1);
$say('values written', $writes);

$args = ['schema' => ['type' => 'integer'], 'single' => true];
$say('the static preparer and the array check', $shape([
    WP_REST_Meta_Fields::prepare_value('5', $view, $args), WP_REST_Meta_Fields::prepare_value('x', $view, $args), WP_REST_Meta_Fields::prepare_value(['a'], $view, ['schema' => ['type' => 'array', 'items' => ['type' => 'string']], 'single' => true]),
    $kinds['post']->check_meta_is_array(['a' => 1], $view, 'meta'), $kinds['post']->check_meta_is_array('x', $view, 'meta'), $kinds['post']->check_meta_is_array(null, $view, 'meta'),
]));

$say('the protected helpers', $shape([
    $probe->reach('prepare_value_for_response', '5', $view, ['schema' => ['type' => 'integer'], 'prepare_callback' => [WP_REST_Meta_Fields::class, 'prepare_value'], 'single' => true]),
    $probe->reach('is_meta_value_same_as_stored_value', 'zz_rmf_string', 'post', 'a', 'a'), $probe->reach('is_meta_value_same_as_stored_value', 'zz_rmf_string', 'post', 'a', 'b'), $probe->reach('is_meta_value_same_as_stored_value', 'zz_rmf_ints', 'post', '1', 1),
    $probe->reach('default_additional_properties_to_false', ['type' => 'object', 'properties' => ['a' => ['type' => 'object', 'properties' => []]]]), $probe->reach('default_additional_properties_to_false', ['type' => 'array', 'items' => ['type' => 'object']]),
    ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'string'), ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'integer'), ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'boolean'), ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'array'), ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'object'), ZZ_RMF_Post::reachStatic('get_empty_value_for_type', 'number'),
    $probe->reach('update_meta_value', $post, 'zz_rmf_string', 'zz_rmf_string', 'via helper'), get_post_meta($post, 'zz_rmf_string', true),
    $probe->reach('update_multi_meta_value', $post, 'zz_rmf_ints', 'zz_rmf_ints', [1, 2]), get_post_meta($post, 'zz_rmf_ints'),
    $probe->reach('delete_meta_value', $post, 'zz_rmf_string', 'zz_rmf_string'), get_post_meta($post, 'zz_rmf_string'),
    $probe->reach('update_meta_value', $post, '_zz_rmf_protected', '_zz_rmf_protected', 'x'), $probe->reach('delete_meta_value', $post, '_zz_rmf_protected', '_zz_rmf_protected'),
    $heard,
]));

// The deprecated registration of the meta field on a type's REST responses.
global $wp_rest_additional_fields;
$heard = [];
$before = array_keys((array) ($wp_rest_additional_fields['zz_rmf_type'] ?? []));
(new class ('zz_rmf_type') extends WP_REST_Post_Meta_Fields {
})->register_field();
$added = $wp_rest_additional_fields['zz_rmf_type']['meta'] ?? null;
$say('the deprecated field registration', [$before, $heard, is_array($added) ? array_keys($added) : $added, is_array($added) ? [is_array($added['get_callback']) ? $added['get_callback'][1] : gettype($added['get_callback']), is_array($added['update_callback']) ? $added['update_callback'][1] : gettype($added['update_callback']), $added['schema']['description'] ?? null] : null]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
