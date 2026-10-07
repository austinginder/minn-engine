<?php
/**
 * The SQL WP_Meta_Query, WP_Tax_Query and WP_Date_Query hand back, as the
 * reference builds it (probe query-clauses): get_sql's join and where for
 * each kind of clause, the clauses and aliases they keep, casts, relations
 * and nesting, other primary tables, and the notices bad values earn.
 * Plugins call these directly; WP_Query builds on them. Same protocol as
 * api-probe.php; the probe's terms are removed at the end.
 */

$log = [];
// The placeholder escape for % is random per request; it reads as % here.
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, json_decode((string) preg_replace('/\{[0-9a-f]{64}\}/', '%', (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true)];
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
global $wpdb;

// Meta queries.
$meta = static function (array $query, string $type = 'post', string $table = '', string $column = 'ID') use ($wpdb, $take): array {
    $mq = new WP_Meta_Query($query);
    $sql = $mq->get_sql($type, $table !== '' ? $table : $wpdb->posts, $column);
    return ['sql' => $sql, 'clauses' => $mq->get_clauses(), 'or' => $mq->has_or_relation(), 'notices' => $take()];
};
$metaCases = [
    'a key' => [['key' => 'k']],
    'key and value' => [['key' => 'k', 'value' => 'v']],
    'value with a quote' => [['key' => 'k', 'value' => "it's"]],
    'in a list' => [['key' => 'k', 'value' => ['a', 'b'], 'compare' => 'IN']],
    'not in a list' => [['key' => 'k', 'value' => ['a', 'b'], 'compare' => 'NOT IN']],
    'between numbers' => [['key' => 'n', 'value' => [1, 10], 'compare' => 'BETWEEN', 'type' => 'NUMERIC']],
    'like' => [['key' => 'k', 'value' => 'part', 'compare' => 'LIKE']],
    'not like' => [['key' => 'k', 'value' => 'part', 'compare' => 'NOT LIKE']],
    'exists' => [['key' => 'k', 'compare' => 'EXISTS']],
    'not exists' => [['key' => 'k', 'compare' => 'NOT EXISTS']],
    'regexp' => [['key' => 'k', 'value' => '^a', 'compare' => 'REGEXP']],
    'not equal' => [['key' => 'k', 'value' => 'v', 'compare' => '!=']],
    'greater, decimal' => [['key' => 'p', 'value' => '9.5', 'compare' => '>', 'type' => 'DECIMAL(10,2)']],
    'date type' => [['key' => 'd', 'value' => '2026-01-01', 'compare' => '<=', 'type' => 'DATE']],
    'char, binary, unsigned, signed' => ['relation' => 'AND', ['key' => 'a', 'value' => 'x', 'type' => 'CHAR'], ['key' => 'b', 'value' => 'y', 'type' => 'BINARY'], ['key' => 'c', 'value' => 1, 'type' => 'UNSIGNED'], ['key' => 'd', 'value' => 2, 'type' => 'SIGNED']],
    'or with nesting' => ['relation' => 'OR', ['key' => 'a', 'value' => 'x'], ['relation' => 'AND', ['key' => 'b', 'compare' => 'EXISTS'], ['key' => 'c', 'value' => 1, 'type' => 'NUMERIC', 'compare' => '<']]],
    'named clauses' => ['first' => ['key' => 'a'], 'second' => ['key' => 'b', 'value' => 'v']],
    'key compared like' => [['key' => 'pre', 'compare_key' => 'LIKE']],
    'keys in a list' => [['key' => ['a', 'b'], 'compare_key' => 'IN']],
    'value only' => [['value' => 'v']],
    'unknown compare' => [['key' => 'k', 'value' => 'v', 'compare' => 'ZZ']],
    'unknown type' => [['key' => 'k', 'value' => 'v', 'type' => 'ZZ']],
    'empty' => [],
];
foreach ($metaCases as $label => $query) {
    $say("meta: {$label}", $meta($query));
}
$say('meta: on users', $meta([['key' => 'k', 'value' => 'v']], 'user', $wpdb->users, 'ID'));
$say('meta: on terms', $meta([['key' => 'k', 'compare' => 'NOT EXISTS']], 'term', $wpdb->terms, 'term_id'));
$say('meta: on comments', $meta([['key' => 'k']], 'comment', $wpdb->comments, 'comment_ID'));
$vars = new WP_Meta_Query();
$vars->parse_query_vars(['meta_key' => 'k', 'meta_value' => 'v', 'meta_compare' => '!=', 'meta_type' => 'NUMERIC']);
$say('meta: from query vars', [$vars->queries, $vars->get_sql('post', $wpdb->posts, 'ID')]);
$say('meta: casts', array_map(static fn ($t) => (new WP_Meta_Query())->get_cast_for_type($t), ['', 'numeric', 'NUMERIC', 'DECIMAL', 'DECIMAL(10,2)', 'decimal(5)', 'CHAR', 'DATE', 'DATETIME', 'TIME', 'BINARY', 'SIGNED', 'UNSIGNED', 'ZZ']));

// Tax queries, over the probe's own terms.
register_taxonomy('zz_ct', 'post', ['hierarchical' => true]);
register_taxonomy('zz_cf', 'post');
$terms = [];
foreach (['parent' => 0, 'child' => 'parent', 'other' => 0] as $slug => $parent) {
    $made = wp_insert_term("Zz Ct {$slug}", 'zz_ct', ['slug' => "zz-ct-{$slug}", 'parent' => $parent === 0 ? 0 : $terms[$parent]['term_id']]);
    $terms[$slug] = $made;
}
$flat = wp_insert_term('Zz Cf one', 'zz_cf', ['slug' => 'zz-cf-one']);
$ttid = static fn (string $slug) => (int) $terms[$slug]['term_taxonomy_id'];
$names = [];
foreach ($terms as $slug => $made) {
    $names[(int) $made['term_id']] = "{term {$slug}}";
    $names[(int) $made['term_taxonomy_id']] = "{tt {$slug}}";
}
$names[(int) $flat['term_id']] = '{term flat}';
$names[(int) $flat['term_taxonomy_id']] = '{tt flat}';
// Term ids become their names wherever they appear.
$maskIds = static function ($value) use ($names) {
    $walk = static function ($v) use (&$walk, $names) {
        if (is_array($v)) {
            return array_map($walk, $v);
        }
        if (is_int($v) && isset($names[$v])) {
            return $names[$v];
        }
        if (!is_string($v)) {
            return $v;
        }
        foreach ($names as $id => $name) {
            $v = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', $name, $v);
        }
        return $v;
    };
    return $walk($value);
};
$tax = static function (array $query) use ($wpdb, $take, $maskIds): array {
    $tq = new WP_Tax_Query($query);
    $sql = $tq->get_sql($wpdb->posts, 'ID');
    return $maskIds(['sql' => $sql, 'queried_terms' => $tq->queried_terms, 'relation' => $tq->relation, 'notices' => $take()]);
};
$taxCases = [
    'by id' => [['taxonomy' => 'zz_ct', 'terms' => [(int) $terms['other']['term_id']]]],
    'by slug' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => 'zz-ct-other']],
    'by name' => [['taxonomy' => 'zz_ct', 'field' => 'name', 'terms' => ['Zz Ct other']]],
    'by term_taxonomy_id' => [['taxonomy' => 'zz_ct', 'field' => 'term_taxonomy_id', 'terms' => [$ttid('other')]]],
    'children included' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-parent']]],
    'children left out' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-parent'], 'include_children' => false]],
    'not in' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-other'], 'operator' => 'NOT IN']],
    'all of' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-other', 'zz-ct-child'], 'operator' => 'AND']],
    'exists' => [['taxonomy' => 'zz_cf', 'operator' => 'EXISTS']],
    'not exists' => [['taxonomy' => 'zz_cf', 'operator' => 'NOT EXISTS']],
    'or' => ['relation' => 'OR', ['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-other']], ['taxonomy' => 'zz_cf', 'field' => 'slug', 'terms' => ['zz-cf-one']]],
    'nested' => ['relation' => 'AND', ['taxonomy' => 'zz_cf', 'field' => 'slug', 'terms' => ['zz-cf-one']], ['relation' => 'OR', ['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-other']], ['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-child'], 'operator' => 'NOT IN']]],
    'no such term' => [['taxonomy' => 'zz_ct', 'field' => 'slug', 'terms' => ['zz-ct-none']]],
    'no such taxonomy' => [['taxonomy' => 'zz_none', 'field' => 'slug', 'terms' => ['x']]],
    'empty' => [],
];
foreach ($taxCases as $label => $query) {
    $say("tax: {$label}", $tax($query));
}

// Date queries.
$date = static function (array $query, string $column = 'post_date') use ($take): array {
    $dq = new WP_Date_Query($query, $column);
    return ['sql' => $dq->get_sql(), 'notices' => $take()];
};
$dateCases = [
    'after, exclusive' => [['after' => '2026-01-01']],
    'after, inclusive' => [['after' => '2026-01-01', 'inclusive' => true]],
    'before a time' => [['before' => '2026-01-01 12:30:00']],
    'before as parts' => [['before' => ['year' => 2026, 'month' => 2]]],
    'after as parts, inclusive' => [['after' => ['year' => 2025], 'inclusive' => true]],
    'year, month, day' => [['year' => 2025, 'month' => 3, 'day' => 4]],
    'week of the year' => [['year' => 2025, 'week' => 10]],
    'day of the week' => [['dayofweek' => [2, 6], 'compare' => 'BETWEEN']],
    'hours in a list' => [['hour' => [9, 17], 'compare' => 'IN']],
    'minute and second' => [['minute' => 30, 'second' => 0]],
    'year not equal' => [['year' => 2024, 'compare' => '!=']],
    'modified, gmt column' => [['column' => 'post_modified_gmt', 'after' => '2026-01-01']],
    'or relation' => ['relation' => 'OR', ['year' => 2024], ['year' => 2026]],
    'hour only' => [['hour' => 9]],
    'minute only' => [['minute' => 30]],
    'second only' => [['second' => 15]],
    'hour and minute' => [['hour' => 9, 'minute' => 5]],
    'hour, minute, second' => [['hour' => 9, 'minute' => 5, 'second' => 7]],
    'hour and second' => [['hour' => 9, 'second' => 7]],
    'minute and second after' => [['minute' => 30, 'second' => 0, 'compare' => '>']],
    'hour and minute in a list' => [['hour' => [9, 10], 'minute' => 5, 'compare' => 'IN']],
    'day of year' => [['dayofyear' => 100]],
    'iso day of week' => [['dayofweek_iso' => 1]],
    'string year, month' => [['after' => '2025-06']],
    'string year only, before inclusive' => [['before' => '2025', 'inclusive' => true]],
    'string with minutes' => [['after' => '2026-01-01 10:30']],
    'bad week' => [['week' => 54]],
    'bad hour' => [['hour' => 24]],
    'bad minute and second' => [['minute' => 60, 'second' => 61]],
    'bad day of week' => [['dayofweek' => 8]],
    'bad iso day of week' => [['dayofweek_iso' => 0]],
    'bad day of year' => [['dayofyear' => 367]],
    'bad day' => [['day' => 32]],
    'bad month' => [['month' => 13]],
    'impossible date' => [['year' => 2025, 'month' => 2, 'day' => 30]],
];
foreach ($dateCases as $label => $query) {
    $say("date: {$label}", $date($query));
}
$say('date: other column', $date([['after' => '2026-01-01']], 'comment_date'));
$weeks = [];
foreach (range(0, 6) as $start) {
    $pin = static fn () => $start;
    add_filter('pre_option_start_of_week', $pin);
    $weeks[$start] = [_wp_mysql_week('c'), (new WP_Date_Query([['week' => 3]]))->get_sql()];
    remove_filter('pre_option_start_of_week', $pin);
}
$say('date: week by start of week', $weeks);
$say('date: unknown column', $date([['after' => '2026-01-01']], 'zz_col'));

foreach (array_reverse($terms) as $made) {
    wp_delete_term((int) $made['term_id'], 'zz_ct');
}
wp_delete_term((int) $flat['term_id'], 'zz_cf');
unregister_taxonomy('zz_ct');
unregister_taxonomy('zz_cf');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
