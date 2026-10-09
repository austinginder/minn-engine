<?php
/**
 * The query clause classes' own steps (probe query-clause-internals):
 * WP_Date_Query (date values checked, values and time queries built,
 * relations, the SQL per query, subquery and clause), WP_Meta_Query and
 * WP_Tax_Query (the SQL per query and clause, the table alias a clause can
 * share, relations), each called directly through a subclass that opens
 * the protected ones. Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
global $wpdb;
$mask = static function ($value) use (&$mask, $wpdb) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($wpdb->prefix, '{prefix}', $wpdb->remove_placeholder_escape($value)) : $value;
};
$open = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
// get_sql_for_query takes its query by reference.
$by_ref = static function (object $object, string $method, array $query, ...$rest) {
    return (new ReflectionMethod($object, $method))->invokeArgs($object, [&$query, ...$rest]);
};

$say('date values checked', [
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['year' => 2024, 'month' => 2, 'day' => 29])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['year' => 2023, 'month' => 2, 'day' => 29])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['month' => 13, 'hour' => 25, 'minute' => 61, 'second' => -1])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['week' => 54, 'dayofweek' => 8, 'dayofweek_iso' => 0, 'dayofyear' => 367])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['before' => ['year' => 2024, 'month' => 14], 'after' => '2024-01-01'])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['day' => 31, 'month' => 4])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['year' => 2024, 'monthnum' => 2, 'day' => 30])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['month' => [1, 13]])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values()),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['second' => 60, 'minute' => -1, 'hour' => 24, 'dayofweek_iso' => 8, 'dayofweek' => 0, 'day' => 32, 'dayofyear' => 0, 'week' => 0, 'month' => 0, 'year' => 10000])),
    $heard(static fn () => (new WP_Date_Query([]))->validate_date_values(['year' => 2024, 'month' => 2, 'day' => 30, 'hour' => 5])),
]);

$date = new WP_Date_Query([['year' => 2024, 'month' => [1, 2], 'compare' => 'IN'], ['after' => '2024-01-01', 'inclusive' => true, 'column' => 'post_modified'], ['relation' => 'AND', ['hour' => 9, 'compare' => '>='], ['dayofweek' => [2, 6], 'compare' => 'BETWEEN']], 'relation' => 'or'], 'post_date');
$say('date sql', [
    $mask($open($date, 'get_sql_clauses')),
    $mask($open($date, 'get_sql_for_query', $date->queries)),
    $mask($open($date, 'get_sql_for_query', $date->queries, 1)),
    $mask($open($date, 'get_sql_for_subquery', $date->queries[0])),
    $mask($open($date, 'get_sql_for_clause', $date->queries[0], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', $date->queries[1], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['week' => 10, 'compare' => '!='], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['before' => ['year' => 2020], 'after' => ['year' => 2010, 'month' => 6], 'inclusive' => false], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', [], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['month' => 0, 'hour' => 0], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['year' => '2024', 'compare' => 'IN'], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['day' => [5], 'compare' => 'BETWEEN'], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['year' => 'x', 'month' => '3', 'compare' => 'IN'], $date->queries)),
    $mask($open($date, 'get_sql_for_clause', ['hour' => 10, 'second' => 5], $date->queries)),
    $mask($date->get_sql()),
]);
$say('date values and times built', [
    $date->build_value('IN', [1, 2, '3']), $date->build_value('NOT IN', '4,5'), $date->build_value('BETWEEN', [1, 5]), $date->build_value('NOT BETWEEN', '2,3'),
    $date->build_value('BETWEEN', [1]), $date->build_value('=', '5'), $date->build_value('=', 'abc'), $date->build_value('=', [7, 8]), $date->build_value('>', '1.5'), $date->build_value('=', null),
    $mask($date->build_time_query('post_date', '=', 10, 30, 0)),
    $mask($date->build_time_query('post_date', '=', 10)),
    $mask($date->build_time_query('post_date', '>', 10, 30)),
    $mask($date->build_time_query('post_date', '=', null, 30)),
    $mask($date->build_time_query('post_date', 'IN', [9, 10])),
    $mask($date->build_time_query('post_date', 'BETWEEN', '9,17')),
    $mask($date->build_time_query('post_date', '=', null, null, 15)),
    $mask($date->build_time_query('post_date', '=')),
    $mask($date->build_time_query('post_date', '<', 23, 59, 59)),
    $date->sanitize_relation('or'), $date->sanitize_relation('AND'), $date->sanitize_relation('foo'), $date->sanitize_relation(''),
]);

$meta_queries = [
    ['key' => 'zz_a', 'value' => 5, 'compare' => '>', 'type' => 'NUMERIC'],
    ['relation' => 'OR', ['key' => 'zz_b'], ['key' => 'zz_c', 'compare' => 'NOT EXISTS'], ['key' => 'zz_b', 'value' => 'x', 'compare' => 'LIKE']],
    'named' => ['key' => 'zz_d', 'value' => ['x', 'y'], 'compare' => 'IN'],
    ['value' => 'only value'],
    ['key' => ['zz_e', 'zz_f'], 'compare_key' => 'IN'],
];
$fresh_meta = static function () use ($meta_queries, $wpdb) {
    $meta = new WP_Meta_Query($meta_queries);
    $meta->meta_table = $wpdb->postmeta;
    $meta->meta_id_column = 'post_id';
    $meta->primary_table = $wpdb->posts;
    $meta->primary_id_column = 'ID';
    return $meta;
};
$say('meta sql', [
    $mask($open($fresh_meta(), 'get_sql_clauses')),
    $mask($by_ref($fresh_meta(), 'get_sql_for_query', $fresh_meta()->queries)),
    $mask($by_ref($fresh_meta(), 'get_sql_for_query', $fresh_meta()->queries, 2)),
    (static function () use ($fresh_meta, $mask) {
        $meta = $fresh_meta();
        $clause = $meta->queries['named'];
        $sql = $meta->get_sql_for_clause($clause, $meta->queries, 'named');
        return $mask([$sql, $clause]);
    })(),
    (static function () use ($fresh_meta, $mask, $open) {
        $meta = $fresh_meta();
        $queries = $meta->queries;
        $meta->get_sql_for_clause($queries[0], $queries);
        $out = [$open($meta, 'find_compatible_table_alias', $queries[1][0], $queries[1])];
        $meta->get_sql_for_clause($queries[1][0], $queries[1]);
        $out[] = $open($meta, 'find_compatible_table_alias', $queries[1][2], $queries[1]);
        $out[] = $open($meta, 'find_compatible_table_alias', $queries[1][1], $queries[1]);
        $out[] = $open($meta, 'find_compatible_table_alias', $queries['named'], $queries);
        $out[] = $mask($queries[1][0]);
        return $out;
    })(),
    $mask($fresh_meta()->get_sql('post', $wpdb->posts, 'ID')),
]);

$term = get_term_by('slug', 'uncategorized', 'category');
$term_id = $term ? (int) $term->term_id : 1;
$tax_queries = [
    ['taxonomy' => 'category', 'terms' => [$term_id], 'field' => 'term_id'],
    ['relation' => 'AND', ['taxonomy' => 'post_tag', 'operator' => 'EXISTS'], ['taxonomy' => 'category', 'terms' => [$term_id], 'operator' => 'NOT IN']],
    ['taxonomy' => 'category', 'terms' => [$term_id], 'operator' => 'AND', 'include_children' => false],
    ['taxonomy' => 'post_format', 'operator' => 'NOT EXISTS'],
];
$fresh_tax = static function () use ($tax_queries, $wpdb) {
    $tax = new WP_Tax_Query(array_merge($tax_queries, ['relation' => 'OR']));
    $tax->primary_table = $wpdb->posts;
    $tax->primary_id_column = 'ID';
    return $tax;
};
$say('tax sql', [
    $term_id === 1 || $term_id > 0,
    (static function () use ($fresh_tax, $mask, $open, $term_id) {
        return str_replace((string) $term_id, '{term}', json_encode($mask($open($fresh_tax(), 'get_sql_clauses'))));
    })(),
    (static function () use ($fresh_tax, $mask, $by_ref, $term_id) {
        $tax = $fresh_tax();
        return str_replace((string) $term_id, '{term}', json_encode($mask($by_ref($tax, 'get_sql_for_query', $tax->queries, 1))));
    })(),
    (static function () use ($fresh_tax, $mask, $open, $term_id) {
        $tax = $fresh_tax();
        $queries = $tax->queries;
        $sql = $tax->get_sql_for_clause($queries[0], $queries);
        $alias = [$open($tax, 'find_compatible_table_alias', $queries[1][0], $queries[1])];
        $tax->get_sql_for_clause($queries[1][0], $queries[1]);
        $alias[] = $open($tax, 'find_compatible_table_alias', $queries[1][1], $queries[1]);
        $alias[] = $open($tax, 'find_compatible_table_alias', $queries[2], $queries);
        $or = $tax->sanitize_query(['relation' => 'OR', ['taxonomy' => 'category', 'terms' => [$term_id], 'field' => 'term_taxonomy_id'], ['taxonomy' => 'post_tag', 'terms' => [$term_id], 'field' => 'term_taxonomy_id'], ['taxonomy' => 'post_tag', 'terms' => [$term_id], 'field' => 'term_taxonomy_id', 'operator' => 'NOT IN']]);
        $tax->get_sql_for_clause($or[0], $or);
        $alias[] = $open($tax, 'find_compatible_table_alias', $or[1], $or);
        $alias[] = $open($tax, 'find_compatible_table_alias', $or[2], $or);
        return str_replace((string) $term_id, '{term}', json_encode($mask([$sql, $alias, $queries[0], $or[0]])));
    })(),
    (static function () use ($mask, $term_id, $wpdb) {
        $tax = new WP_Tax_Query([['taxonomy' => 'category', 'terms' => ['zz-no-such-term'], 'field' => 'slug']]);
        return str_replace((string) $term_id, '{term}', json_encode($mask($tax->get_sql($wpdb->posts, 'ID'))));
    })(),
    $fresh_tax()->sanitize_relation('or'), $fresh_tax()->sanitize_relation('nope'),
    $open($fresh_tax(), 'is_first_order_clause', ['taxonomy' => 'category']), $open($fresh_tax(), 'is_first_order_clause', ['terms' => [1]]), $open($fresh_tax(), 'is_first_order_clause', ['relation' => 'OR', ['taxonomy' => 'x']]), $open($fresh_tax(), 'is_first_order_clause', []),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
