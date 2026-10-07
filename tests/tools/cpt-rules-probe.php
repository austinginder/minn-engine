<?php
/**
 * What registering a post type or a taxonomy adds for the request parse
 * (probe cpt-rules): the public query vars a viewable type and a queryable
 * taxonomy add (a hidden one adds none), a type's archive rules among the
 * top rules (true or its own slug, under the front or the root, feeds and
 * pages or not), and both gone again with the type. Same protocol as
 * api-probe.php; the structure is put back.
 */

global $wp, $wp_rewrite;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$structure = (string) get_option('permalink_structure');
$types = [
    'zz_pa' => ['public' => true, 'has_archive' => true],
    'zz_pb' => ['public' => true, 'has_archive' => 'zz-shelf', 'query_var' => 'zzbook'],
    'zz_pc' => ['public' => true, 'has_archive' => true, 'rewrite' => ['with_front' => false, 'feeds' => false, 'pages' => false]],
    'zz_pd' => ['public' => false, 'query_var' => 'zzhidden'],
    'zz_pe' => ['public' => false, 'publicly_queryable' => true],
];
$taxonomies = [
    'zz_xa' => ['public' => true],
    'zz_xb' => ['public' => false, 'query_var' => 'zzprivate'],
];
$archives = static function () use ($wp_rewrite): array {
    return array_filter((array) $wp_rewrite->extra_rules_top, static fn ($query) => str_contains((string) $query, 'post_type=zz_'));
};
try {
    foreach (['under a front' => '/blog/%postname%/', 'at the root' => '/%postname%/'] as $where => $candidate) {
        $wp_rewrite->set_permalink_structure($candidate);
        $before = $wp->public_query_vars;
        foreach ($types as $name => $args) {
            register_post_type($name, $args);
        }
        foreach ($taxonomies as $name => $args) {
            register_taxonomy($name, 'zz_pa', $args);
        }
        $say("query vars added {$where}", array_values(array_diff($wp->public_query_vars, $before)));
        $say("archive rules {$where}", $archives());
        foreach (array_keys($types) as $name) {
            unregister_post_type($name);
        }
        foreach (array_keys($taxonomies) as $name) {
            unregister_taxonomy($name);
        }
        $say("query vars left {$where}", array_values(array_diff($wp->public_query_vars, $before)));
        $say("archive rules left {$where}", $archives());
    }
} finally {
    $wp_rewrite->set_permalink_structure($structure);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
