<?php
/**
 * How a taxonomy query finds its terms, as the reference finds them (probe
 * tax-terms): the term queries each clause issues (their arguments whole, as
 * get_terms_args sees them), the children a hierarchical clause adds and in
 * what order, the SQL that comes of it, the clause as it is left, AND's
 * count of what it asked for, transform_query called directly,
 * get_term_by's lookups and refusals, and get_term_children's reads of the
 * stored hierarchy. Same protocol as
 * api-probe.php; the probe's terms and its taxonomy's stored hierarchy are
 * removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;

register_taxonomy('zz_tt', 'post', ['hierarchical' => true]);
register_taxonomy('zz_tf', 'post');
$made = [];
$names = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made, true) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    delete_option('zz_tt_children');
    delete_option('zz_tf_children');
});
// P has C1, C2 and C3; C1 has G1, which has H1; C2 has G2. T1 and T2 are flat.
$term = static function (string $key, string $taxonomy, string $parent = '') use (&$made, &$names): void {
    $result = wp_insert_term('Zz ' . $key, $taxonomy, ['slug' => 'zz-' . strtolower($key), 'parent' => $parent === '' ? 0 : $made[$parent][0]]);
    if (!is_array($result)) {
        fwrite(STDERR, "tax-terms: could not make {$key}\n");
        exit(1);
    }
    $made[$key] = [(int) $result['term_id'], $taxonomy];
    $names[(int) $result['term_id']] = "{{$key}}";
    $names[(int) $result['term_taxonomy_id']] = "{tt {$key}}";
};
foreach ([['P', ''], ['C1', 'P'], ['C2', 'P'], ['G1', 'C1'], ['G2', 'C2'], ['H1', 'G1'], ['C3', 'P']] as [$key, $parent]) {
    $term($key, 'zz_tt', $parent);
}
$term('T1', 'zz_tf');
$term('T2', 'zz_tf');
$id = static fn (string $key): int => $made[$key][0];
$ttid = static fn (string $key): int => (int) get_term($made[$key][0])->term_taxonomy_id;
// Ids become their names wherever they appear; a term id and a term_taxonomy_id may share a number, so a
// value is read as a term id in arguments and as a term_taxonomy_id in SQL and the clause it leaves.
$termNames = array_combine(array_map(static fn ($pair) => $pair[0], $made), array_map(static fn ($key) => "{{$key}}", array_keys($made)));
$ttNames = array_combine(array_map($ttid, array_keys($made)), array_map(static fn ($key) => "{tt {$key}}", array_keys($made)));
$mask = static function ($value, array $map) use (&$mask) {
    if (is_array($value)) {
        return array_map(static fn ($v) => $mask($v, $map), $value);
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return $map[(int) $value] ?? $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($map as $number => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $number . '(?![0-9])/', $name, $value);
    }
    return $value;
};
$keys = ['taxonomy', 'orderby', 'order', 'hide_empty', 'include', 'number', 'fields', 'name', 'slug', 'term_taxonomy_id', 'hierarchical', 'get', 'child_of', 'parent', 'childless', 'update_term_meta_cache'];
$watch = static function (callable $run) use ($keys, $mask, $termNames): array {
    $args = [];
    $grab = static function ($a) use (&$args, $keys, $mask, $termNames) {
        $args[] = $mask(array_intersect_key($a, array_flip($keys)), $termNames);
        return $a;
    };
    $reads = 0;
    $read = static function ($v) use (&$reads) {
        $reads++;
        return $v;
    };
    $messages = [];
    $message = static function ($translation, $text) use (&$messages) {
        $messages[] = $text;
        return $translation;
    };
    add_filter('gettext', $message, 10, 2);
    add_filter('get_terms_args', $grab);
    add_filter('option_zz_tt_children', $read);
    add_filter('option_zz_tf_children', $read);
    $result = $run();
    remove_filter('option_zz_tf_children', $read);
    remove_filter('option_zz_tt_children', $read);
    remove_filter('get_terms_args', $grab);
    remove_filter('gettext', $message, 10);
    return [$args, $reads, $result, $messages];
};
$clauseOf = static function (array $clause) use ($mask, $ttNames): array {
    return $mask(array_intersect_key($clause, array_flip(['taxonomy', 'terms', 'field', 'operator', 'include_children'])), $ttNames);
};
$where = static fn (string $sql): string => trim((string) preg_replace('/\s+/', ' ', $sql));

// Clauses, each through get_sql (a WP_Query builds the same way).
$cases = [
    'flat slugs' => ['zz_tf', 'slug', ['zz-t1', 'zz-t2'], true, 'IN'],
    'flat slugs repeated' => ['zz_tf', 'slug', ['zz-t1', 'zz-t1', 'zz-t2'], true, 'IN'],
    'flat slug and a missing one' => ['zz_tf', 'slug', ['zz-t1', 'zz-none'], true, 'IN'],
    'flat names' => ['zz_tf', 'name', ['Zz T2'], true, 'IN'],
    'flat ids' => ['zz_tf', 'term_id', ['T2', 'T1'], true, 'IN'],
    'flat term_taxonomy_ids' => ['zz_tf', 'term_taxonomy_id', ['tt T1'], true, 'IN'],
    'flat ids, all of' => ['zz_tf', 'term_id', ['T1', 'T2'], true, 'AND'],
    'flat slugs repeated, all of' => ['zz_tf', 'slug', ['zz-t1', 'zz-t1', 'zz-t2'], true, 'AND'],
    'flat slugs, all of, one missing' => ['zz_tf', 'slug', ['zz-t1', 'zz-none'], true, 'AND'],
    'flat ids, none of' => ['zz_tf', 'term_id', ['T1'], true, 'NOT IN'],
    'tree slug' => ['zz_tt', 'slug', ['zz-p'], true, 'IN'],
    'tree slug, children left out' => ['zz_tt', 'slug', ['zz-p'], false, 'IN'],
    'tree slugs overlapping' => ['zz_tt', 'slug', ['zz-c2', 'zz-p'], true, 'IN'],
    'tree name' => ['zz_tt', 'name', ['Zz C1'], true, 'IN'],
    'tree id deep' => ['zz_tt', 'term_id', ['C1'], true, 'IN'],
    'tree ids' => ['zz_tt', 'term_id', ['C3', 'C2'], true, 'IN'],
    'tree term_taxonomy_id' => ['zz_tt', 'term_taxonomy_id', ['tt C2'], true, 'IN'],
    'tree term_taxonomy_id, children left out' => ['zz_tt', 'term_taxonomy_id', ['tt C2'], false, 'IN'],
    'tree missing slug' => ['zz_tt', 'slug', ['zz-none'], true, 'IN'],
    'tree slug and a missing one' => ['zz_tt', 'slug', ['zz-c3', 'zz-none'], true, 'IN'],
    'tree id, all of' => ['zz_tt', 'term_id', ['C2'], true, 'AND'],
    'tree siblings, all of' => ['zz_tt', 'term_id', ['C3', 'C2'], true, 'AND'],
    'tree overlapping, all of' => ['zz_tt', 'term_id', ['C2', 'P'], true, 'AND'],
    'tree overlapping, all of, children left out' => ['zz_tt', 'term_id', ['C2', 'P'], false, 'AND'],
    'tree slug and a missing one, all of' => ['zz_tt', 'slug', ['zz-c3', 'zz-none'], true, 'AND'],
    'tree id and a missing one, all of' => ['zz_tt', 'term_id', ['C3', 'none'], false, 'AND'],
    'tree slug, none of' => ['zz_tt', 'slug', ['zz-c1'], true, 'NOT IN'],
];
foreach ($cases as $label => [$taxonomy, $field, $terms, $children, $operator]) {
    $terms = array_map(static fn ($t) => match (true) {
        $t === 'none' => 999999999,
        str_starts_with($t, 'tt ') => $ttid(substr($t, 3)),
        $field === 'term_id' => $id($t),
        default => $t,
    }, $terms);
    [$args, $reads, $result, $messages] = $watch(static function () use ($wpdb, $taxonomy, $field, $terms, $children, $operator) {
        $tq = new WP_Tax_Query([['taxonomy' => $taxonomy, 'field' => $field, 'terms' => $terms, 'include_children' => $children, 'operator' => $operator]]);
        $sql = $tq->get_sql($wpdb->posts, 'ID');
        return [$sql, $tq->queries[0], $tq->queried_terms];
    });
    $say("clause: {$label}", ['term queries' => $args, 'hierarchy reads' => $reads, 'where' => $mask($where($result[0]['where']), $ttNames), 'join' => $where($result[0]['join']), 'clause' => $clauseOf($result[1]), 'queried' => array_map(static fn ($q) => ['terms' => $mask($q['terms'] ?? null, ($q['field'] ?? '') === 'term_taxonomy_id' ? $ttNames : $termNames)] + $q, $result[2]), 'messages' => $messages]);
}

// transform_query, called as plugins call it.
foreach ([
    'slug to term_taxonomy_id, children' => [['taxonomy' => 'zz_tt', 'field' => 'slug', 'terms' => ['zz-c2'], 'include_children' => true], 'term_taxonomy_id'],
    'slug to term_id' => [['taxonomy' => 'zz_tt', 'field' => 'slug', 'terms' => ['zz-c2', 'zz-c3'], 'include_children' => false], 'term_id'],
    'flat names to slug' => [['taxonomy' => 'zz_tf', 'field' => 'name', 'terms' => ['Zz T1', 'Zz T2']], 'slug'],
    'flat ids to term_taxonomy_id' => [['taxonomy' => 'zz_tf', 'field' => 'term_id', 'terms' => [$id('T1')]], 'term_taxonomy_id'],
    'already there' => [['taxonomy' => 'zz_tf', 'field' => 'slug', 'terms' => ['zz-t1']], 'slug'],
    'missing' => [['taxonomy' => 'zz_tf', 'field' => 'slug', 'terms' => ['zz-none']], 'term_taxonomy_id'],
    'all of, one missing' => [['taxonomy' => 'zz_tf', 'field' => 'slug', 'terms' => ['zz-t1', 'zz-none'], 'operator' => 'AND'], 'term_taxonomy_id'],
    'nothing to turn' => [['taxonomy' => 'zz_tf', 'field' => 'slug', 'terms' => []], 'term_taxonomy_id'],
] as $label => [$clause, $to]) {
    $clause += ['operator' => 'IN', 'include_children' => true];
    [$args, $reads, $result, $messages] = $watch(static function () use ($clause, $to) {
        $tq = new WP_Tax_Query([]);
        $tq->transform_query($clause, $to);
        return $clause;
    });
    if (is_wp_error($result)) {
        $say("transform: {$label}", ['term queries' => $args, 'error' => [$result->get_error_code(), $result->get_error_message()], 'messages' => $messages]);
        continue;
    }
    $say("transform: {$label}", ['term queries' => $args, 'hierarchy reads' => $reads, 'clause' => $mask($result['terms'], $to === 'term_taxonomy_id' ? $ttNames : $termNames), 'field' => $result['field'], 'messages' => $messages]);
}

// get_term_by: a name or slug needs a taxonomy that exists and a value; a term_taxonomy_id needs no taxonomy.
foreach ([
    'by name' => ['name', 'Zz T1', 'zz_tf'],
    'by slug' => ['slug', 'zz-t1', 'zz_tf'],
    'by name, no taxonomy' => ['name', 'Zz T1', ''],
    'by slug, a taxonomy there is not' => ['slug', 'zz-t1', 'zz_none'],
    'by name, empty' => ['name', '', 'zz_tf'],
    'by a field there is not' => ['zz_field', 'zz-t1', 'zz_tf'],
    'by term_taxonomy_id, no taxonomy' => ['term_taxonomy_id', 'tt T1', ''],
    'by id' => ['id', 'T2', 'zz_tf'],
    'by ID, another taxonomy' => ['ID', 'T2', 'zz_tt'],
] as $label => [$field, $value, $taxonomy]) {
    $value = str_starts_with($value, 'tt ') ? $ttid(substr($value, 3)) : ($field === 'id' || $field === 'ID' ? $id($value) : $value);
    [$args, $reads, $result] = $watch(static fn () => get_term_by($field, $value, $taxonomy));
    $say("get_term_by: {$label}", ['term queries' => $args, 'term' => $result instanceof WP_Term ? $mask($result->term_id, $termNames) : $result]);
}

// get_term_children, from the stored hierarchy.
foreach (['P' => 'zz_tt', 'C1' => 'zz_tt', 'C3' => 'zz_tt', 'T1' => 'zz_tf'] as $key => $taxonomy) {
    [$args, $reads, $result] = $watch(static fn () => get_term_children($id($key), $taxonomy));
    $say("children: {$key}", ['term queries' => $args, 'hierarchy reads' => $reads, 'children' => $mask($result, $termNames)]);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
