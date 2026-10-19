<?php
/**
 * The keys the reference runs through sanitize_key on the way to SQL (probe
 * query-keys): a meta function's column ({type}_id) once as it starts, and
 * again as it reads or adds on the way; update_meta_cache's, whether the
 * objects are cached or not; a meta query's in get_sql, even an empty one
 * (a term query always builds one, a post, comment or user query only for
 * meta it names); WP_Query's post types as parse_query keeps them (a list
 * sorted) and its short orderby names (name, author, date, title, modified,
 * parent, type); not a type pre_get_posts sets after, which reaches the SQL
 * as given; and transform_query's field, for terms it turns. Same protocol
 * as api-probe.php; the probe's post and its meta are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$post = wp_insert_post(['post_type' => 'post', 'post_title' => 'Zz query keys', 'post_status' => 'draft']);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "query-keys: could not make the post\n");
    exit(1);
}
register_shutdown_function(static function () use ($post): void {
    delete_user_meta(1, 'zz_query_keys');
    if (is_int($post) && $post > 1) {
        wp_delete_post($post, true);
    }
});
$keys = static function (callable $call): array {
    $seen = [];
    $record = static function ($key, $raw) use (&$seen) {
        $seen[] = is_scalar($raw) ? (string) $raw : json_encode($raw);
        return $key;
    };
    add_filter('sanitize_key', $record, 10, 2);
    $call();
    remove_filter('sanitize_key', $record, 10);
    return $seen;
};

// Meta functions.
$mid = 0;
$say('add_post_meta', $keys(static function () use ($post, &$mid) {
    $mid = add_post_meta($post, 'zz_k', 'v');
}));
$say('get_post_meta, not cached', $keys(static fn () => get_post_meta($post, 'zz_k', true)));
$say('get_post_meta, cached', $keys(static fn () => get_post_meta($post, 'zz_k', true)));
$say('update_post_meta, a new value', $keys(static fn () => update_post_meta($post, 'zz_k', 'w')));
$say('update_post_meta, the same value', $keys(static fn () => update_post_meta($post, 'zz_k', 'w')));
$say('update_post_meta, a new key', $keys(static fn () => update_post_meta($post, 'zz_k2', 'x')));
$say('metadata_exists', $keys(static fn () => metadata_exists('post', $post, 'zz_k')));
$say('get_metadata_by_mid', $keys(static fn () => get_metadata_by_mid('post', $mid)));
$say('update_metadata_by_mid', $keys(static fn () => update_metadata_by_mid('post', $mid, 'y')));
$say('delete_post_meta', $keys(static fn () => delete_post_meta($post, 'zz_k2')));
$say('delete_metadata_by_mid', $keys(static fn () => delete_metadata_by_mid('post', $mid)));
$say('delete_metadata for every object', $keys(static fn () => delete_metadata('post', 0, 'zz_query_keys_none', '', true)));
$say('update_meta_cache, not cached', $keys(static function () use ($post) {
    wp_cache_delete($post, 'post_meta');
    update_meta_cache('post', [$post]);
}));
$say('update_meta_cache, cached', $keys(static fn () => update_meta_cache('post', [$post])));
$say('add_user_meta', $keys(static fn () => add_user_meta(1, 'zz_query_keys', 'v')));
$say('delete_user_meta', $keys(static fn () => delete_user_meta(1, 'zz_query_keys')));

// Meta queries, and the queries that build them.
$say('meta query', $keys(static fn () => (new WP_Meta_Query([['key' => 'k', 'value' => 'v']]))->get_sql('post', 'wp_posts', 'ID')));
$say('meta query, empty', $keys(static fn () => (new WP_Meta_Query([]))->get_sql('post', 'wp_posts', 'ID')));
$say('meta query, a type there is not', $keys(static fn () => (new WP_Meta_Query([['key' => 'k']]))->get_sql('zz', 'wp_posts', 'ID')));
$say('term query', $keys(static fn () => get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'number' => 1])));
$say('term query, by term_taxonomy_id', $keys(static fn () => get_terms(['taxonomy' => 'category', 'term_taxonomy_id' => [1], 'hide_empty' => false])));
$say('comment query', $keys(static fn () => get_comments(['number' => 1])));
$say('comment query, by meta', $keys(static fn () => get_comments(['number' => 1, 'meta_key' => 'zz'])));
$say('user query, ids', $keys(static fn () => new WP_User_Query(['number' => 1, 'fields' => 'ID'])));
$say('user query, whole', $keys(static fn () => new WP_User_Query(['number' => 1])));

// WP_Query: types and orderby names.
$ids = ['posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids'];
foreach (['name', 'author', 'date', 'title', 'modified', 'parent', 'type', 'ID', 'menu_order', 'rand', 'none', 'post__in', 'comment_count', 'relevance', 'post_title', 'Title', 'zzunknown', 'menu_order title'] as $orderby) {
    $say("post query by {$orderby}", $keys(static fn () => new WP_Query(['post_type' => 'post', 'orderby' => $orderby] + $ids)));
}
$say('post query by two names', $keys(static fn () => new WP_Query(['post_type' => 'post', 'orderby' => ['title' => 'ASC', 'date' => 'DESC']] + $ids)));
foreach (['a type' => 'Post', 'types' => ['Post', 'PAGE'], 'a spaced type' => 'po st', 'any type' => 'any', 'no type' => ''] as $label => $type) {
    $query = null;
    $seen = $keys(static function () use (&$query, $type, $ids) {
        $query = new WP_Query(['post_type' => $type] + $ids);
    });
    preg_match("/\\.post_type (= '[^']*'|IN \\([^)]*\\))/", (string) $query->request, $where);
    $say("post query of {$label}", ['keys' => $seen, 'where' => $where[1] ?? '', 'kept' => $query->get('post_type')]);
}
foreach (['a type pre_get_posts sets' => 'PAGE', 'types pre_get_posts sets' => ['Post', 'PAGE']] as $label => $type) {
    $set = static function ($query) use ($type): void {
        $query->set('post_type', $type);
    };
    add_action('pre_get_posts', $set);
    $query = null;
    $seen = $keys(static function () use (&$query, $ids) {
        $query = new WP_Query(['post_type' => 'post'] + $ids);
    });
    remove_action('pre_get_posts', $set);
    preg_match("/\\.post_type (= '[^']*'|IN \\([^)]*\\))/", (string) $query->request, $where);
    $say("post query of {$label}", ['keys' => $seen, 'where' => $where[1] ?? '', 'kept' => $query->get('post_type')]);
}

// transform_query's field.
foreach ([
    'to term_id' => [['taxonomy' => 'category', 'field' => 'slug', 'terms' => ['uncategorized']], 'term_id'],
    'to Slug' => [['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [1]], 'Slug'],
    'to the field it has' => [['taxonomy' => 'category', 'field' => 'term_taxonomy_id', 'terms' => [1]], 'term_taxonomy_id'],
    'with no terms' => [['taxonomy' => 'category', 'field' => 'slug', 'terms' => []], 'term_id'],
] as $label => [$clause, $to]) {
    $clause += ['operator' => 'IN', 'include_children' => false];
    $seen = $keys(static function () use (&$clause, $to) {
        (new WP_Tax_Query([]))->transform_query($clause, $to);
    });
    $say("transform_query {$label}", ['keys' => $seen, 'terms' => $clause['terms'] ?? null, 'field' => $clause['field'] ?? null]);
}
$say('a tax query by id', $keys(static fn () => (new WP_Tax_Query([['taxonomy' => 'category', 'terms' => [1], 'include_children' => false]]))->get_sql('wp_posts', 'ID')));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
