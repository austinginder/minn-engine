<?php
/**
 * WP_Term_Query's SQL as plugin code meets it, as the reference builds it
 * (probe wp-term-query-sql): for each set of arguments, the clauses
 * terms_clauses is handed, the request, the term query filters that run in
 * their order, and what comes back (names, ids, counts, or the shape the
 * fields ask for). Then filters that change the clauses, answer before the
 * query, or rewrite the results. The probe's own taxonomies, terms and
 * posts, removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_post_type('zz_tq', ['public' => true]);
register_taxonomy('zz_tqh', ['zz_tq'], ['hierarchical' => true]);
register_taxonomy('zz_tqf', ['zz_tq']);

$ids = [];
$made = ['terms' => [], 'posts' => []];
$terms = [
    'alpha' => ['zz_tqh', 0, 'First of the tree'],
    'beta' => ['zz_tqh', 'alpha', 'Child of alpha'],
    'gamma' => ['zz_tqh', 'beta', 'Grandchild'],
    'delta' => ['zz_tqh', 0, 'Empty root'],
    'echo' => ['zz_tqf', 0, 'A flat term'],
    'foxtrot' => ['zz_tqf', 0, 'Another flat term'],
];
foreach ($terms as $slug => [$taxonomy, $parent, $description]) {
    $term = wp_insert_term('Zz Tq ' . ucfirst($slug), $taxonomy, ['slug' => "zz-tq-{$slug}", 'parent' => $parent === 0 ? 0 : $ids[$parent], 'description' => $description]);
    $ids[$slug] = (int) ($term['term_id'] ?? 0);
    $ids["tt {$slug}"] = (int) ($term['term_taxonomy_id'] ?? 0);
    $made['terms'][] = [$ids[$slug], $taxonomy];
}
add_term_meta($ids['alpha'], 'zz_rank', 2);
add_term_meta($ids['beta'], 'zz_rank', 1);
add_term_meta($ids['echo'], 'zz_rank', 3);
foreach (['one' => ['zz-tq-beta', 'zz-tq-echo'], 'two' => ['zz-tq-gamma', 'zz-tq-echo'], 'three' => ['zz-tq-alpha']] as $slug => $slugs) {
    $id = (int) wp_insert_post(['post_type' => 'zz_tq', 'post_title' => 'Zz Tq ' . $slug, 'post_status' => 'publish']);
    $ids["post {$slug}"] = $id;
    $made['posts'][] = $id;
    wp_set_object_terms($id, array_values(array_filter($slugs, static fn ($s) => str_ends_with($s, 'echo') === false)), 'zz_tqh');
    wp_set_object_terms($id, array_values(array_filter($slugs, static fn ($s) => str_ends_with($s, 'echo'))), 'zz_tqf');
}

// Ids become their names (in keys and in SQL too).
$mask = static function ($value) use (&$ids) {
    $byId = [];
    foreach (array_filter($ids) as $name => $id) {
        $byId[$id] ??= $name;
    }
    $walk = static function ($v) use (&$walk, $byId) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $item) {
                $out[is_int($k) && isset($byId[$k]) ? '{' . $byId[$k] . '}' : $k] = $walk($item);
            }
            return $out;
        }
        if (is_int($v) && isset($byId[$v])) {
            return '{' . $byId[$v] . '}';
        }
        if (!is_string($v)) {
            return $v;
        }
        $v = (string) preg_replace('/\{[0-9a-f]{64}\}/', '%', $v);
        foreach ($byId as $id => $name) {
            $v = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $v);
        }
        return $v;
    };
    return $walk($value);
};
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(parse_term_query|pre_get_terms|get_terms|terms_|list_terms_exclusions|get_meta_sql|get_term$|get_zz_tq)/', $hook)) {
        $fired[] = $hook;
    }
});
$shape = static function ($result) {
    if (is_wp_error($result)) {
        return ['error' => $result->get_error_code()];
    }
    if (!is_array($result)) {
        return $result;
    }
    return array_map(static fn ($t) => $t instanceof WP_Term ? $t->name . ' (' . $t->count . ')' : $t, $result);
};
$query = static function (array $args) use (&$fired, $mask, $shape): array {
    $clauses = null;
    $grab = static function ($pieces) use (&$clauses) {
        $clauses ??= $pieces;
        return $pieces;
    };
    add_filter('terms_clauses', $grab);
    $fired = [];
    $q = new WP_Term_Query();
    // Uncached, so each case runs its query.
    $result = $q->query($args + ['cache_results' => false]);
    remove_filter('terms_clauses', $grab);
    return $mask([
        'clauses' => $clauses,
        'request' => preg_replace('/\s+/', ' ', trim((string) $q->request)),
        'filters' => array_values(array_unique($fired)),
        'terms' => $shape($result),
    ]);
};
$tree = ['taxonomy' => 'zz_tqh'];
$cases = [
    'default' => [],
    'everything' => ['hide_empty' => false],
    'by name descending' => ['hide_empty' => false, 'orderby' => 'name', 'order' => 'DESC'],
    'by slug' => ['hide_empty' => false, 'orderby' => 'slug'],
    'by count' => ['hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC'],
    'by id' => ['hide_empty' => false, 'orderby' => 'term_id'],
    'by description' => ['hide_empty' => false, 'orderby' => 'description'],
    'by parent' => ['hide_empty' => false, 'orderby' => 'parent'],
    'by term order' => ['hide_empty' => false, 'orderby' => 'term_order'],
    'by include order' => ['hide_empty' => false, 'include' => [$ids['gamma'], $ids['alpha']], 'orderby' => 'include'],
    'by slug order' => ['hide_empty' => false, 'slug' => ['zz-tq-gamma', 'zz-tq-alpha'], 'orderby' => 'slug__in'],
    'no order' => ['hide_empty' => false, 'orderby' => 'none'],
    'by a meta value' => ['hide_empty' => false, 'meta_key' => 'zz_rank', 'orderby' => 'meta_value_num'],
    'a meta query' => ['hide_empty' => false, 'meta_query' => [['key' => 'zz_rank', 'value' => 1, 'compare' => '>', 'type' => 'NUMERIC']]],
    'included' => ['hide_empty' => false, 'include' => [$ids['beta'], $ids['alpha']]],
    'excluded' => ['hide_empty' => false, 'exclude' => [$ids['beta']]],
    'a tree excluded' => ['hide_empty' => false, 'exclude_tree' => [$ids['alpha']]],
    'two at a time' => ['hide_empty' => false, 'number' => 2],
    'two, from the second' => ['hide_empty' => false, 'number' => 2, 'offset' => 1],
    'by slug list' => ['hide_empty' => false, 'slug' => ['zz-tq-beta', 'zz-tq-alpha']],
    'by name' => ['hide_empty' => false, 'name' => 'Zz Tq Beta'],
    'by term taxonomy id' => ['hide_empty' => false, 'term_taxonomy_id' => [$ids['tt beta']]],
    'names like' => ['hide_empty' => false, 'name__like' => 'ta'],
    'descriptions like' => ['hide_empty' => false, 'description__like' => 'root'],
    'a search' => ['hide_empty' => false, 'search' => 'alp'],
    'children of alpha' => ['hide_empty' => false, 'parent' => $ids['alpha']],
    'top level' => ['hide_empty' => false, 'parent' => 0],
    'descendants of alpha' => ['hide_empty' => false, 'child_of' => $ids['alpha']],
    'childless' => ['hide_empty' => false, 'childless' => true],
    'not hierarchical, empty hidden' => ['hierarchical' => false],
    'padded counts' => ['pad_counts' => true],
    'all of them' => ['get' => 'all'],
    'ids' => ['hide_empty' => false, 'fields' => 'ids'],
    'names' => ['hide_empty' => false, 'fields' => 'names'],
    'slugs' => ['hide_empty' => false, 'fields' => 'slugs'],
    'counted' => ['hide_empty' => false, 'fields' => 'count'],
    'id and parent' => ['hide_empty' => false, 'fields' => 'id=>parent'],
    'id and name' => ['hide_empty' => false, 'fields' => 'id=>name'],
    'id and slug' => ['hide_empty' => false, 'fields' => 'id=>slug'],
    'term taxonomy ids' => ['hide_empty' => false, 'fields' => 'tt_ids'],
    'all with object id' => ['fields' => 'all_with_object_id', 'object_ids' => [$ids['post one'], $ids['post two']]],
    'for posts' => ['object_ids' => [$ids['post one'], $ids['post three']]],
    'for posts, in term order' => ['object_ids' => [$ids['post one']], 'orderby' => 'term_order'],
];
foreach ($cases as $label => $args) {
    $say($label, $query($args + $tree));
}
$raw = new WP_Term_Query(['taxonomy' => 'zz_tqh', 'hide_empty' => false, 'number' => 2, 'hierarchical' => false, 'cache_results' => false]);
$say('the request as written', $mask($raw->request));
$lists = new WP_Term_Query(['taxonomy' => 'zz_tqh', 'hide_empty' => false, 'slug' => 'zz-tq-beta', 'name' => 'Zz Tq Beta,Other', 'term_taxonomy_id' => (string) $ids['tt beta'], 'object_ids' => $ids['post one'], 'cache_results' => false]);
$say('the lists a query keeps', $mask([array_intersect_key($lists->query_vars, array_flip(['slug', 'name', 'term_taxonomy_id', 'object_ids', 'include', 'exclude'])), preg_replace('/\s+/', ' ', trim((string) $lists->request))]));
$say('both taxonomies', $query(['taxonomy' => ['zz_tqf', 'zz_tqh'], 'hide_empty' => false]));
$say('no taxonomy', $query(['hide_empty' => false, 'include' => [$ids['alpha'], $ids['echo']]]));
$say('an unknown taxonomy', $query(['taxonomy' => 'zz_none']));
$say('get_terms, the legacy shape', $mask($shape(get_terms('zz_tqf', ['hide_empty' => false]))));
$say('wp_get_object_terms', $mask($shape(wp_get_object_terms([$ids['post one']], ['zz_tqh', 'zz_tqf']))));
$say('wp_get_object_terms, ids', $mask(wp_get_object_terms([$ids['post one']], 'zz_tqh', ['fields' => 'ids'])));
$say('get_the_terms', $mask($shape(get_the_terms($ids['post two'], 'zz_tqh'))));

// Filters that change what the query asks and answers.
$with = static function (string $label, string $hook, callable $callback, array $args = [], int $accepted = 1) use ($say, $query, $tree): void {
    add_filter($hook, $callback, 10, $accepted);
    $say($label, $query($args + $tree + ['hide_empty' => false]));
    remove_filter($hook, $callback, 10);
};
$with('clauses changed', 'terms_clauses', static fn ($c) => ['where' => $c['where'] . " AND t.slug != 'zz-tq-beta'"] + $c);
$with('an exclusion added', 'list_terms_exclusions', static fn ($exclusions) => $exclusions . " AND t.slug != 'zz-tq-gamma'");
$with('orderby replaced', 'get_terms_orderby', static fn () => 't.slug');
$with('fields replaced', 'get_terms_fields', static fn ($fields) => $fields);
$with('answered before the query', 'terms_pre_query', static fn () => [get_term($ids['delta'])]);
$with('args changed', 'get_terms_args', static fn ($args) => ['number' => 1] + $args);
$with('defaults changed', 'get_terms_defaults', static fn ($defaults) => ['orderby' => 'slug'] + $defaults);
$with('results rewritten', 'get_terms', static fn ($terms) => array_reverse($terms));
$with('a term filtered', 'get_term', static function ($term) {
    $term->name .= ' (seen)';
    return $term;
});
add_action('pre_get_terms', $pre = static function ($q) {
    $q->query_vars['orderby'] = 'slug';
    $q->query_vars['order'] = 'DESC';
});
$say('pre_get_terms changes the order', $query($tree + ['hide_empty' => false]));
remove_action('pre_get_terms', $pre);

foreach ($made['posts'] as $id) {
    wp_delete_post($id, true);
}
foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
    wp_delete_term($id, $taxonomy);
}
unregister_taxonomy('zz_tqh');
unregister_taxonomy('zz_tqf');
unregister_post_type('zz_tq');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
