<?php
/**
 * The term query filters a REST term read runs, as the reference runs them
 * (probe rest-term-filters): get_term and get_{taxonomy} on each term,
 * get_terms on a list (with the taxonomies and the query's arguments);
 * and what a filter that changes a term's count or name does to the
 * answer, listed and single. (The query's own filters, get_terms_args and
 * terms_clauses, wait for the term query's SQL to be the reference's.) Same
 * protocol as api-probe.php; dispatched in process; filters removed at the
 * end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$seen = [];
// Whether each filter ran (how often is the reference's own business).
$record = static function (string $filter) use (&$seen): void {
    $seen[$filter] = true;
};
add_filter('get_term', static function ($term, $taxonomy) use ($record) {
    $record('get_term');
    if ($term instanceof WP_Term && $taxonomy === 'category') {
        $term->name .= ' [one]';
    }
    return $term;
}, 10, 2);
add_filter('get_category', static function ($term) use ($record) {
    $record('get_category');
    return $term;
});
$listArgs = null;
add_filter('get_terms', static function ($terms, $taxonomies, $args) use ($record, &$listArgs) {
    $record('get_terms');
    $listArgs = [$taxonomies, array_intersect_key((array) $args, array_flip(['taxonomy', 'orderby', 'order', 'hide_empty', 'number', 'offset', 'fields', 'include', 'exclude', 'parent', 'slug', 'search', 'hierarchical', 'childless']))];
    foreach ($terms as $term) {
        if ($term instanceof WP_Term) {
            $term->count += 100;
        }
    }
    return $terms;
}, 10, 3);
$take = static function () use (&$seen): array {
    $out = $seen;
    ksort($out);
    $seen = [];
    return $out;
};
$shape = static fn (array $data): array => array_map(static fn ($term) => [$term['name'] ?? null, $term['count'] ?? null], array_is_list($data) ? $data : [$data]);
$send = static function (string $route, array $query = []): array {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    return (array) rest_get_server()->response_to_data($response, false);
};

$list = $send('/wp/v2/categories', ['include' => '1']);
$say('category list', [$shape($list), $take(), $listArgs]);
$listArgs = null;
$say('category single', [$shape($send('/wp/v2/categories/1')), $take(), $listArgs]);
$say('tag list', [count($send('/wp/v2/tags', ['per_page' => 1])) <= 1, $take()]);

foreach (['get_term', 'get_category', 'get_terms'] as $filter) {
    remove_all_filters($filter);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
