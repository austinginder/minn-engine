<?php
/**
 * The query classes' compatibility doors and smaller steps (probe
 * query-compat): WP_Query's, WP_User_Query's and WP_Comment_Query's magic
 * reads, writes and calls of their old fields and methods,
 * is_comments_popup, generate_postdata, the search SQL and cache keys
 * the user, comment and term queries build, the comment IDs and
 * descendants a comment query fills, and the term query's meta orderby.
 * Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$notices = [];
foreach (['doing_it_wrong_run' => 3, 'deprecated_function_run' => 3] as $hook => $args) {
    add_action($hook, static function (...$heard) use (&$notices, $hook): void {
        $notices[] = [$hook, ...array_map(static fn ($v) => is_string($v) ? strip_tags($v) : $v, $heard)];
    }, 10, $args);
}
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$notices) {
    $notices = [];
    try {
        $out = $call();
    } catch (Throwable $e) {
        $out = [get_class($e), $e->getMessage()];
    }
    return [$out, $notices];
};
global $wpdb;
$mask = static function ($value) use (&$mask, $wpdb) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($wpdb->prefix, '{prefix}', $wpdb->remove_placeholder_escape($value));
    return (string) preg_replace(['/[0-9a-f]{32}/', '/\b\d\.\d{8} \d{10}\b/'], ['{md5}', '{microtime}'], $value);
};
$open = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
$changed = static fn ($value) => is_array($value) ? array_map(static fn ($v) => is_string($v) ? (string) preg_replace('/\d\.\d{8} \d{10}/', '{microtime}', $v) : $v, $value) : $value;
$made = ['comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made['comments']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
});

$query = new WP_Query(['post_type' => 'post', 'posts_per_page' => 1, 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC']);
$say('WP_Query doors', [
    $heard(static fn () => is_string($query->query_vars_hash) ? strlen($query->query_vars_hash) : $query->query_vars_hash),
    $heard(static fn () => $query->query_vars_changed),
    $heard(static fn () => [isset($query->query_vars_hash), isset($query->query_vars_changed), isset($query->zz_nothing)]),
    $heard(static fn () => $query->zz_nothing),
    $heard(static fn () => $query->init_query_flags()),
    $heard(static fn () => [$query->is_home, $query->is_single, $query->is_archive]),
    $heard(static fn () => $query->parse_tax_query(['category_name' => 'uncategorized', 'post_type' => 'post'])),
    $heard(static fn () => $query->zz_undefined_method(1, 2)),
    $heard(static fn () => $query->is_comments_popup()),
]);

$post = get_post((int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1"));
$say('generate_postdata', [
    $post instanceof WP_Post,
    (static function () use ($post, $mask) {
        $data = (new WP_Query())->generate_postdata($post);
        if (!is_array($data)) {
            return $data;
        }
        $data['authordata'] = is_object($data['authordata'] ?? null) ? get_class($data['authordata']) : ($data['authordata'] ?? null);
        $data['id'] = is_int($data['id'] ?? null) && $data['id'] > 0;
        $data['pages'] = is_array($data['pages'] ?? null) ? count($data['pages']) : null;
        $data['currentday'] = is_string($data['currentday'] ?? null) ? strlen($data['currentday']) : null;
        $data['currentmonth'] = is_string($data['currentmonth'] ?? null) ? strlen($data['currentmonth']) : null;
        return $mask($data);
    })(),
    (new WP_Query())->generate_postdata(0),
    (static function () {
        $paged = new WP_Post((object) ['ID' => 999999, 'post_author' => 1, 'post_date' => '2024-03-05 10:00:00', 'post_content' => "one<!--nextpage-->two<!--nextpage-->three", 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'zz paged']);
        $data = (new WP_Query(['page' => 2]))->generate_postdata($paged);
        return [$data['pages'] ?? null, $data['multipage'] ?? null, $data['numpages'] ?? null, $data['page'] ?? null, $data['more'] ?? null, $data['currentday'] ?? null, $data['currentmonth'] ?? null];
    })(),
]);

$users = new WP_User_Query(['number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID']);
$say('WP_User_Query doors', [
    $heard(static fn () => is_array($users->results) ? count($users->results) : $users->results),
    $heard(static fn () => $users->total_users >= 1),
    $heard(static fn () => [isset($users->results), isset($users->total_users), isset($users->zz_nothing)]),
    $heard(static fn () => $users->zz_nothing),
    $heard(static function () use ($users) {
        $users->results = ['zz'];
        return $users->get_results();
    }),
    $heard(static function () use ($users) {
        $users->zz_dynamic = 5;
        return [isset($users->zz_dynamic), $users->zz_dynamic ?? null];
    }),
    $heard(static function () use ($users) {
        unset($users->results);
        return isset($users->results);
    }),
    $heard(static fn () => $mask($users->get_search_sql('zz', ['user_login', 'user_email'], 'both'))),
    $heard(static fn () => $users->get_total()),
    $heard(static fn () => $users->zz_undefined_method()),
]);
$say('user search and cache keys', [
    $mask($open($users, 'get_search_sql', 'zz', ['user_login', 'user_email'], 'both')),
    $mask($open($users, 'get_search_sql', 'zz', ['user_login'], 'leading')),
    $mask($open($users, 'get_search_sql', 'zz', ['user_nicename'], 'trailing')),
    $mask($open($users, 'get_search_sql', '42', ['ID'], false)),
    $mask($open($users, 'get_search_sql', 'a_b%c', ['user_url', 'display_name'], false)),
    $open($users, 'generate_cache_key', $users->query_vars, 'SELECT 1'),
    $open($users, 'generate_cache_key', ['fields' => 'all'] + $users->query_vars, 'SELECT 1'),
    $open($users, 'generate_cache_key', ['has_published_posts' => true] + $users->query_vars, 'SELECT 2'),
    $changed($open($users, 'get_cache_last_changed', $users->query_vars)),
    $changed($open($users, 'get_cache_last_changed', ['has_published_posts' => true] + $users->query_vars)),
    $changed($open($users, 'get_cache_last_changed', ['has_published_posts' => ['post'], 'meta_key' => 'x'] + $users->query_vars)),
    $changed($open($users, 'get_cache_last_changed', ['orderby' => 'post_count'] + $users->query_vars)),
    $changed($open($users, 'get_cache_last_changed', ['orderby' => ['post_count' => 'ASC']] + $users->query_vars)),
    $open($users, 'get_cache_last_changed', $users->query_vars) === [wp_cache_get_last_changed('users')],
]);

$comments = new WP_Comment_Query();
$comments->query(['number' => 2, 'orderby' => 'comment_ID', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true]);
$say('WP_Comment_Query doors', [
    $heard(static fn () => $mask($comments->get_search_sql('zz', ['comment_author', 'comment_content']))),
    $heard(static fn () => $comments->zz_undefined_method()),
    $mask($open($comments, 'get_search_sql', 'zz', ['comment_author', 'comment_author_email'])),
    $mask($open($comments, 'get_search_sql', 'a%b', ['comment_content'])),
    (static function () use ($open, $mask) {
        $query = new WP_Comment_Query();
        $query->query(['number' => 3, 'orderby' => 'comment_ID', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true]);
        $ids = $open($query, 'get_comment_ids');
        return [is_array($ids), $ids === array_map('intval', $ids) || $ids === array_map('strval', $ids), count($ids) <= 3, $mask($query->request)];
    })(),
]);
$made['comments'][] = $parent = (int) wp_insert_comment(['comment_post_ID' => $post->ID, 'comment_content' => 'zz parent', 'comment_approved' => 1, 'comment_author' => 'zz']);
$made['comments'][] = $child = (int) wp_insert_comment(['comment_post_ID' => $post->ID, 'comment_content' => 'zz child', 'comment_approved' => 1, 'comment_parent' => $parent, 'comment_author' => 'zz']);
$made['comments'][] = (int) wp_insert_comment(['comment_post_ID' => $post->ID, 'comment_content' => 'zz grandchild', 'comment_approved' => 1, 'comment_parent' => $child, 'comment_author' => 'zz']);
$say('comment descendants', (static function () use ($open, $parent) {
    if ($parent < 1) {
        return 'no threads';
    }
    $query = new WP_Comment_Query();
    $top = $query->query(['comment__in' => [$parent], 'hierarchical' => 'threaded', 'status' => 'approve', 'no_found_rows' => true]);
    $children = array_map(static fn ($comment) => array_keys($comment->get_children()), $top);
    $content = static fn ($comment) => is_object($comment) ? $comment->comment_content : $comment;
    $flat = new WP_Comment_Query();
    $flat->query_vars = ['hierarchical' => 'flat', 'status' => 'approve', 'fields' => '', 'orderby' => '', 'order' => 'ASC', 'update_comment_meta_cache' => false, 'cache_results' => true] + (array) $flat->query_var_defaults;
    $flat_filled = $open($flat, 'fill_descendants', [get_comment($parent)]);
    $threaded = new WP_Comment_Query();
    $threaded->query_vars = ['hierarchical' => 'threaded', 'status' => 'approve', 'fields' => '', 'orderby' => '', 'order' => 'ASC', 'update_comment_meta_cache' => false, 'cache_results' => true] + (array) $threaded->query_var_defaults;
    $threaded_filled = $open($threaded, 'fill_descendants', [get_comment($parent)]);
    return [
        count($top), array_values(array_map(static fn ($comment) => array_values(array_map(static fn ($child) => $child->comment_content, $comment->get_children())), $top)),
        array_values(array_map($content, $flat_filled)),
        array_values(array_map(static fn ($comment) => [$comment->comment_content, array_values(array_map(static fn ($child) => [$child->comment_content, array_values(array_map(static fn ($grand) => $grand->comment_content, $child->get_children()))], $comment->get_children()))], $threaded_filled)),
    ];
})());

$terms = new WP_Term_Query();
$terms->query(['taxonomy' => 'category', 'hide_empty' => false, 'meta_query' => [['key' => 'zz_order', 'type' => 'NUMERIC'], 'named' => ['key' => 'zz_named']], 'number' => 1]);
$say('term orderby meta and cache keys', [
    $mask($open($terms, 'parse_orderby_meta', 'meta_value')),
    $mask($open($terms, 'parse_orderby_meta', 'meta_value_num')),
    $mask($open($terms, 'parse_orderby_meta', 'named')),
    $mask($open($terms, 'parse_orderby_meta', 'zz_order')),
    $mask($open($terms, 'parse_orderby_meta', 'name')),
    $mask($open($terms, 'parse_orderby_meta', '')),
    $open($terms, 'generate_cache_key', $terms->query_vars, 'SELECT 1'),
    $open($terms, 'generate_cache_key', ['fields' => 'ids'] + $terms->query_vars, 'SELECT 1'),
    $open($terms, 'generate_cache_key', ['fields' => 'count'] + $terms->query_vars, 'SELECT 1'),
    $open($terms, 'generate_cache_key', ['fields' => 'all_with_object_id'] + $terms->query_vars, 'SELECT 1'),
    $open($terms, 'generate_cache_key', ['update_term_meta_cache' => false, 'cache_results' => false] + $terms->query_vars, 'SELECT 1'),
    $open($terms, 'generate_cache_key', ['fields' => 'count'] + $terms->query_vars, 'SELECT 2'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
