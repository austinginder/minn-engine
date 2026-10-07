<?php
/**
 * WP_Query's SQL as plugin code meets it, as the reference builds it
 * (probe wp-query-sql): for each set of query variables, the clauses
 * posts_clauses is handed, the request, the query filters that run in
 * their order, and what comes back. Then filters that change the clauses,
 * replace the request, answer before the query, or rewrite the results and
 * the count, and suppress_filters. The probe's own type, taxonomy, posts
 * and terms, removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_post_type('zz_q', ['public' => true, 'hierarchical' => true, 'supports' => ['title', 'editor', 'page-attributes']]);
register_taxonomy('zz_qtax', ['zz_q'], ['public' => true, 'hierarchical' => true]);

$made = ['posts' => [], 'terms' => []];
$ids = [];
foreach (['red', 'blue'] as $slug) {
    $term = wp_insert_term("Zz Q {$slug}", 'zz_qtax', ['slug' => "zz-q-{$slug}"]);
    $ids["term {$slug}"] = (int) ($term['term_id'] ?? 0);
    $made['terms'][] = $ids["term {$slug}"];
}
$specs = [
    'alpha' => ['date' => '2025-03-04 10:00:00', 'menu' => 2, 'content' => 'alpha beta gamma', 'n' => 3, 'c' => 'x', 'terms' => ['zz-q-red']],
    'beta' => ['date' => '2025-07-01 10:00:00', 'menu' => 1, 'content' => 'beta only', 'n' => 1, 'c' => 'y', 'terms' => ['zz-q-blue']],
    'gamma' => ['date' => '2026-02-01 10:00:00', 'menu' => 3, 'content' => 'gamma exact phrase here', 'n' => 5, 'c' => null, 'terms' => ['zz-q-red', 'zz-q-blue']],
    'delta' => ['date' => '2026-05-01 10:00:00', 'menu' => 0, 'content' => 'delta excluded word', 'n' => null, 'c' => 'x', 'terms' => []],
];
foreach ($specs as $slug => $spec) {
    $id = (int) wp_insert_post(['post_type' => 'zz_q', 'post_title' => "Zz Q " . ucfirst($slug), 'post_name' => "zz-q-{$slug}", 'post_status' => 'publish', 'post_date' => $spec['date'], 'post_date_gmt' => $spec['date'], 'menu_order' => $spec['menu'], 'post_content' => $spec['content'], 'post_author' => 1]);
    $ids[$slug] = $id;
    $made['posts'][] = $id;
    if ($spec['n'] !== null) {
        add_post_meta($id, 'zz_n', $spec['n']);
    }
    if ($spec['c'] !== null) {
        add_post_meta($id, 'zz_c', $spec['c']);
    }
    wp_set_object_terms($id, $spec['terms'], 'zz_qtax');
}
$child = (int) wp_insert_post(['post_type' => 'zz_q', 'post_title' => 'Zz Q Child', 'post_name' => 'zz-q-child', 'post_status' => 'draft', 'post_parent' => $ids['alpha'], 'post_date' => '2026-06-01 10:00:00', 'post_date_gmt' => '2026-06-01 10:00:00', 'post_author' => 1]);
$ids['child'] = $child;
$made['posts'][] = $child;

// Ids become their names (a number that is one, as a string); the search placeholder's random hash becomes %.
$mask = static function ($value) use (&$ids) {
    $byId = array_flip(array_filter($ids));
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
    if (preg_match('/^(pre_get_posts|posts_|post_limits|found_posts|the_posts|split_the_query|the_preview|wp_query_search)/', $hook)) {
        $fired[] = $hook;
    }
});
$query = static function (array $vars) use (&$fired, $mask): array {
    $clauses = null;
    // The query's own clauses: the first call (a plugin's own query inside may come later).
    $grab = static function ($pieces) use (&$clauses) {
        $clauses ??= $pieces;
        return $pieces;
    };
    add_filter('posts_clauses', $grab);
    $fired = [];
    $q = new WP_Query();
    // Uncached, so each case runs the whole chain (a cached request skips most of it).
    $posts = $q->query($vars + ['cache_results' => false]);
    remove_filter('posts_clauses', $grab);
    $titles = array_map(static fn ($p) => is_object($p) ? ($p->post_title ?? $p->ID) : $p, (array) $posts);
    return $mask([
        'clauses' => $clauses,
        'request' => preg_replace('/\s+/', ' ', trim((string) $q->request)),
        'filters' => array_values(array_unique($fired)),
        'posts' => $titles,
        'found' => [$q->found_posts, $q->max_num_pages, $q->post_count],
    ] + ($q->is_preview ? ['preview' => true] : []));
};
$base = ['post_type' => 'zz_q'];
$cases = [
    'default' => [],
    'paged' => ['posts_per_page' => 2, 'paged' => 2],
    'by title ascending' => ['orderby' => 'title', 'order' => 'ASC'],
    'two orders' => ['orderby' => ['menu_order' => 'ASC', 'date' => 'DESC']],
    'menu order and title' => ['orderby' => 'menu_order title', 'order' => 'ASC'],
    'search two words' => ['s' => 'alpha beta'],
    'search with an exclusion' => ['s' => 'gamma -phrase'],
    'search a phrase' => ['s' => '"exact phrase"'],
    'meta order' => ['meta_key' => 'zz_n', 'orderby' => 'meta_value_num', 'order' => 'ASC'],
    'meta compare' => ['meta_query' => [['key' => 'zz_n', 'value' => 2, 'compare' => '>=', 'type' => 'NUMERIC']]],
    'meta or exists' => ['meta_query' => ['relation' => 'OR', ['key' => 'zz_n', 'compare' => 'NOT EXISTS'], ['key' => 'zz_c', 'value' => 'x']]],
    'meta named clause order' => ['meta_query' => ['zz_order' => ['key' => 'zz_n', 'type' => 'NUMERIC']], 'orderby' => 'zz_order', 'order' => 'DESC'],
    'tax by slug' => ['tax_query' => [['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-red']]]],
    'tax not in' => ['tax_query' => [['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-red'], 'operator' => 'NOT IN']]],
    'tax and' => ['tax_query' => ['relation' => 'AND', ['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-red']], ['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-blue']]]],
    'tax by query var' => ['zz_qtax' => 'zz-q-blue'],
    'date after' => ['date_query' => [['after' => '2026-01-01']]],
    'year and month' => ['year' => 2025, 'monthnum' => 3],
    'by ids in order' => ['post__in' => [$ids['gamma'], $ids['alpha']], 'orderby' => 'post__in'],
    'not these ids' => ['post__not_in' => [$ids['alpha']]],
    'top level' => ['post_parent' => 0],
    'by author' => ['author' => 1],
    'any status' => ['post_status' => 'any'],
    'ids only' => ['fields' => 'ids'],
    'id and parent' => ['fields' => 'id=>parent', 'post_status' => 'any'],
    'no found rows' => ['no_found_rows' => true, 'posts_per_page' => 1],
    'everything' => ['posts_per_page' => -1],
    'offset' => ['offset' => 1, 'posts_per_page' => 2],
    'by name' => ['name' => 'zz-q-beta'],
    'by id' => ['p' => $ids['gamma']],
    'filters suppressed' => ['suppress_filters' => true],
    'everything at once' => ['s' => 'gamma', 'author' => 1, 'post__not_in' => [$ids['beta']], 'post_parent' => 0, 'year' => 2026, 'date_query' => [['after' => '2025-01-01']], 'tax_query' => [['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-red']]], 'meta_query' => [['key' => 'zz_n', 'compare' => 'EXISTS']], 'menu_order' => 3, 'post_mime_type' => '', 'orderby' => 'title', 'order' => 'ASC'],
    'mime types' => ['post_mime_type' => ['image', 'application/pdf']],
    'names in order' => ['post_name__in' => ['zz-q-gamma', 'zz-q-alpha'], 'orderby' => 'post_name__in'],
    'parents in' => ['post_parent__in' => [0], 'orderby' => 'parent'],
    'not by these authors' => ['author__not_in' => [2, 3]],
    'by author name' => ['author_name' => 'zz-nobody'],
    'by title' => ['title' => 'Zz Q Beta'],
    'no order' => ['orderby' => 'none'],
    'comment count and id' => ['orderby' => ['comment_count' => 'DESC', 'ID' => 'ASC']],
    'meta value, char' => ['meta_key' => 'zz_c', 'orderby' => 'meta_value', 'order' => 'ASC'],
    'meta value as a type' => ['meta_key' => 'zz_n', 'meta_type' => 'DECIMAL', 'orderby' => 'meta_value', 'order' => 'DESC'],
    'legacy meta vars' => ['meta_key' => 'zz_c', 'meta_value' => 'x'],
    'statuses listed' => ['post_status' => ['publish', 'draft']],
    'any type' => ['post_type' => 'any'],
    'two types' => ['post_type' => ['zz_q', 'page']],
    'category var' => ['cat' => 1],
    'menu order var' => ['menu_order' => 2],
    'page two with offset' => ['posts_per_page' => 1, 'paged' => 2, 'offset' => 1],
    'ids out of order' => ['post__not_in' => [$ids['gamma'], $ids['alpha']]],
    'ids in, repeated' => ['post__in' => [$ids['gamma'], $ids['alpha'], $ids['gamma']]],
    'parents in their order' => ['post_parent__in' => [$ids['alpha'], 0], 'orderby' => 'post_parent__in', 'post_status' => 'any'],
    'parents not in' => ['post_parent__not_in' => [$ids['alpha']], 'post_status' => 'any'],
    'authors in, out of order' => ['author__in' => [3, 1, 3]],
    'author with an exclusion' => ['author' => '1,-2'],
    'author name and id' => ['author_name' => 'admin'],
    'by its query var' => ['zz_q' => 'zz-q-beta'],
    'page id wipes the rest' => ['page_id' => $ids['beta'], 'menu_order' => 9],
    'a password' => ['post_password' => 'zz'],
    'has a password' => ['has_password' => true],
    'has no password' => ['has_password' => false],
    'comment count' => ['comment_count' => 0],
    'comment count compared' => ['comment_count' => ['value' => 1, 'compare' => '<']],
    'comment count, a bad compare' => ['comment_count' => ['value' => 1, 'compare' => 'LIKE']],
    'comment and ping status' => ['comment_status' => 'open', 'ping_status' => 'closed'],
    'legacy month' => ['m' => '202503'],
    'legacy day and hour' => ['m' => '2025030410'],
    'week and hour' => ['w' => 9, 'hour' => 10],
    'search with stopwords' => ['s' => 'the alpha a'],
    'search exact' => ['s' => 'alpha', 'exact' => true],
    'search as a sentence' => ['s' => 'alpha beta', 'sentence' => true],
    'search one column' => ['s' => 'alpha', 'search_columns' => ['post_title']],
    'search a bad column' => ['s' => 'alpha', 'search_columns' => ['post_name']],
    'search ten words' => ['s' => 'one two three four five six seven eight nine ten'],
    'search seven words' => ['s' => 'alpha beta gamma delta one two three'],
    'search only stopwords' => ['s' => 'the of'],
    'search by relevance' => ['s' => 'alpha beta', 'orderby' => 'relevance'],
    'search ordered by title' => ['s' => 'alpha', 'orderby' => 'title'],
    'search quoted and plain' => ['s' => '"exact phrase" gamma'],
    'seeded random' => ['orderby' => 'RAND(5)'],
    'an unknown order' => ['orderby' => 'zz_unknown'],
    'order by a field name' => ['orderby' => 'post_modified', 'order' => 'asc'],
    'a bad order' => ['orderby' => 'title', 'order' => 'sideways'],
    'meta clause order with another' => ['meta_query' => ['zz_order' => ['key' => 'zz_n', 'type' => 'NUMERIC']], 'orderby' => ['zz_order' => 'ASC', 'title' => 'DESC']],
    'meta key as the order' => ['meta_key' => 'zz_n', 'orderby' => 'zz_n'],
    'per page zero' => ['posts_per_page' => 0],
    'per page negative' => ['posts_per_page' => -5],
    'no paging' => ['nopaging' => true],
    'showposts' => ['showposts' => 2],
    'archive per page' => ['posts_per_archive_page' => 1, 'year' => 2026],
    'taxonomy without a type' => ['post_type' => '', 'zz_qtax' => 'zz-q-red'],
    'taxonomy and term vars' => ['taxonomy' => 'zz_qtax', 'term' => 'zz-q-blue'],
    'two terms by query var' => ['zz_qtax' => 'zz-q-red,zz-q-blue'],
    'terms joined by plus' => ['zz_qtax' => 'zz-q-red+zz-q-blue'],
    'a draft by id, signed out' => ['p' => $ids['child']],
    'posts with a sticky' => ['post_type' => 'post', 'posts_per_page' => 2],
    'posts, stickies ignored' => ['post_type' => 'post', 'posts_per_page' => 2, 'ignore_sticky_posts' => true],
    'posts with a sticky, page two' => ['post_type' => 'post', 'posts_per_page' => 2, 'paged' => 2],
    'posts with a sticky excluded' => ['post_type' => 'post', 'posts_per_page' => 2, 'post__not_in' => [5]],
    'two types, any status' => ['post_type' => ['zz_q', 'page'], 'post_status' => 'any'],
    'any type, any status' => ['post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 3],
    'one type in a list' => ['post_type' => ['zz_q']],
    'an unknown type' => ['post_type' => 'zz_nothing'],
    'any status but a draft named' => ['post_status' => ['any', 'draft']],
    'statuses as a string' => ['post_status' => 'publish,draft'],
    'an unknown status' => ['post_status' => 'zz_nothing'],
    'a meta value and a meta query' => ['meta_key' => 'zz_c', 'meta_value' => 'x', 'meta_query' => [['key' => 'zz_n', 'compare' => 'EXISTS']]],
    'a meta compare alone' => ['meta_compare' => '!='],
    'statuses with spaces' => ['post_status' => 'draft, publish , Private'],
    'a type with capitals' => ['post_type' => 'ZZ_Q'],
    'types with capitals' => ['post_type' => ['ZZ_Q', 'Page']],
];
foreach ($cases as $label => $vars) {
    $say($label, $query($vars + $base));
}

// A signed-in administrator: private posts readable, no password clause in a search.
wp_set_current_user(1);
foreach (['default' => [], 'search' => ['s' => 'alpha'], 'any status' => ['post_status' => 'any'], 'by id' => ['p' => $ids['gamma']], 'readable only' => ['perm' => 'readable'], 'a draft by id' => ['p' => $ids['child']], 'a draft by id, filters suppressed' => ['p' => $ids['child'], 'suppress_filters' => true], 'editable statuses' => ['post_status' => ['publish', 'private', 'draft'], 'perm' => 'editable'], 'readable statuses' => ['post_status' => ['publish', 'private'], 'perm' => 'readable'], 'taxonomy without a type, any status' => ['post_type' => '', 'zz_qtax' => 'zz-q-red', 'post_status' => 'any']] as $label => $vars) {
    $say("as an administrator: {$label}", $query($vars + $base));
}
wp_set_current_user(0);

// The request as written (whitespace and all), and what the query leaves in its variables and parts.
$raw = static function (array $vars) use ($mask): array {
    $given = null;
    $grab = static function ($sql) use (&$given) {
        $given ??= $sql;
        return $sql;
    };
    add_filter('posts_request', $grab);
    $q = new WP_Query($vars + ['cache_results' => false]);
    remove_filter('posts_request', $grab);
    return $mask(['given' => $given, 'request' => $q->request]);
};
$say('raw: split', $raw($base));
$say('raw: whole rows', $raw(['posts_per_page' => -1] + $base));
$say('raw: ids', $raw(['fields' => 'ids', 'no_found_rows' => true] + $base));
$say('raw: singular', $raw(['p' => $ids['gamma']] + $base));
$state = static function (array $vars) use ($mask): array {
    $q = new WP_Query($vars + ['cache_results' => false]);
    $vars = $q->query_vars;
    ksort($vars);
    return $mask([
        'vars' => $vars,
        'tax' => $q->tax_query ? ['queries' => $q->tax_query->queries, 'queried' => $q->tax_query->queried_terms, 'relation' => $q->tax_query->relation] : $q->tax_query,
        'meta' => $q->meta_query ? ['queries' => $q->meta_query->queries, 'clauses' => $q->meta_query->get_clauses()] : $q->meta_query,
        'date' => $q->date_query ? $q->date_query->queries : $q->date_query,
        'flags' => array_keys(array_filter(get_object_vars($q), static fn ($v, $k) => str_starts_with($k, 'is_') && $v === true, ARRAY_FILTER_USE_BOTH)),
    ]);
};
$say('state: default', $state($base));
$say('state: search', $state(['s' => 'the "exact phrase" -beta alpha'] + $base));
$say('state: authors and ids', $state(['author' => '3,1,-2', 'post__in' => [$ids['gamma'], $ids['alpha']], 'orderby' => 'post__in'] + $base));
$say('state: everything at once', $state(['s' => 'gamma', 'author' => 1, 'post__not_in' => [$ids['beta']], 'post_parent' => 0, 'year' => 2026, 'date_query' => [['after' => '2025-01-01']], 'tax_query' => [['taxonomy' => 'zz_qtax', 'field' => 'slug', 'terms' => ['zz-q-red']]], 'meta_query' => [['key' => 'zz_n', 'compare' => 'EXISTS']], 'menu_order' => 3] + $base));
$say('state: taxonomy query var', $state(['zz_qtax' => 'zz-q-red'] + $base));
$say('state: no type, taxonomy', $state(['post_type' => '', 'zz_qtax' => 'zz-q-red']));
$say('state: a category', $state(['cat' => '1', 'posts_per_page' => 1]));
$say('state: an author name', $state(['author_name' => 'admin', 'posts_per_page' => 1]));
$say('state: category and tag lists', $state(['category__in' => [9999, 1], 'category__not_in' => [9998, 2], 'tag__in' => [9999, 1], 'tag_slug__in' => ['zz-b', 'zz-a'], 'posts_per_page' => 1]));
$say('state: a tag', $state(['tag' => 'zz-b,zz-a', 'posts_per_page' => 1]));
$say('state: two types', $state(['post_type' => ['zz_q', 'page'], 'post_status' => 'any', 'posts_per_page' => 1]));
$say('state: tags joined', $state(['tag' => 'zz-b+zz-a', 'posts_per_page' => 1]));
$say('state: legacy meta and orders', $state(['meta_key' => 'zz_n', 'orderby' => 'meta_value_num title', 'order' => 'asc'] + $base));

// Filters that change what the query asks and answers.
$with = static function (string $label, string $hook, callable $callback, array $vars = [], int $args = 1) use ($say, $query, $base): void {
    add_filter($hook, $callback, 10, $args);
    $say($label, $query($vars + $base));
    remove_filter($hook, $callback, 10);
};
$with('a where clause added', 'posts_where', static fn ($where) => $where . " AND wp_posts.post_name != 'zz-q-beta'");
$with('a join and where added', 'posts_join', static fn ($join) => $join . " INNER JOIN wp_postmeta AS zzm ON (wp_posts.ID = zzm.post_id AND zzm.meta_key = 'zz_c')");
$with('orderby replaced', 'posts_orderby', static fn () => 'wp_posts.menu_order ASC');
$with('limits replaced', 'post_limits', static fn () => 'LIMIT 0, 1');
$with('fields replaced', 'posts_fields', static fn ($fields) => $fields);
$with('distinct asked', 'posts_distinct', static fn () => 'DISTINCT');
$with('groupby asked', 'posts_groupby', static fn () => 'wp_posts.ID');
$with('clauses changed together', 'posts_clauses', static fn ($c) => ['where' => $c['where'] . " AND wp_posts.menu_order > 1"] + $c);
$with('request replaced', 'posts_request', static fn ($sql) => str_replace('ORDER BY wp_posts.post_date DESC', 'ORDER BY wp_posts.post_title ASC', $sql));
$with('search emptied', 'posts_search', static fn () => '', ['s' => 'alpha']);
$with('answered before the query', 'posts_pre_query', static fn () => [get_post($ids['delta'])], []);
$with('results rewritten', 'posts_results', static fn ($posts) => array_reverse($posts));
$with('the posts trimmed', 'the_posts', static fn ($posts) => array_slice($posts, 0, 1));
$with('count rewritten', 'found_posts', static fn () => 42);
$with('count query replaced', 'found_posts_query', static fn () => 'SELECT 7', ['posts_per_page' => 1]);
$with('ids answered before the query', 'posts_pre_query', static fn () => [$ids['delta'], (string) $ids['alpha']], ['fields' => 'ids', 'no_found_rows' => true]);
$with('no split asked', 'split_the_query', static fn () => false);
$with('split forced on whole rows', 'split_the_query', static fn () => true, ['posts_per_page' => -1]);
$with('the id request replaced', 'posts_request_ids', static fn ($sql) => str_replace('LIMIT 0, 10', 'LIMIT 0, 2', $sql));
$with('search columns filtered', 'post_search_columns', static fn () => ['post_excerpt', 'post_name'], ['s' => 'alpha']);
$with('stopwords filtered', 'wp_search_stopwords', static fn () => ['alpha'], ['s' => 'alpha beta']);
$with('exclusion prefix changed', 'wp_query_search_exclusion_prefix', static fn () => '!', ['s' => 'gamma !phrase -beta']);
$with('search order emptied', 'posts_search_orderby', static fn () => '', ['s' => 'alpha beta']);
$with('the request filters', 'posts_where_request', static fn ($where) => $where . ' AND 1=1', []);
$with('request clauses changed', 'posts_clauses_request', static fn ($c) => ['limits' => 'LIMIT 0, 3'] + $c);
$with('clauses emptied', 'posts_clauses', static fn () => []);
wp_set_current_user(1);
$with('a preview filtered', 'the_preview', static fn ($post) => $post, ['p' => $ids['child']]);
wp_set_current_user(0);

foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach ($made['terms'] as $id) {
    wp_delete_term($id, 'zz_qtax');
}
unregister_taxonomy('zz_qtax');
unregister_post_type('zz_q');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
