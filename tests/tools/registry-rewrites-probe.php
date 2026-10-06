<?php
/**
 * What a post type's and a taxonomy's rewrite and query var settle into,
 * and the address pattern (permastruct) each registers (probe
 * registry-rewrites): public or not, queryable or not, with a rewrite
 * array, without rewrites, without a query var or with its own, under
 * pretty and plain permalinks; feeds only with an archive; a taxonomy's
 * rewrite merged over its defaults; the patterns' arguments; built-in and
 * earlier patterns left as they were by a structure set later, a new one
 * under the new front, and a pattern gone with its type. Same protocol as
 * api-probe.php; the structure is put back.
 */

global $wp_rewrite;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$structure = (string) get_option('permalink_structure');
try {
    foreach (['pretty' => '/%postname%/', 'plain' => ''] as $mode => $candidate) {
        $wp_rewrite->set_permalink_structure($candidate);
        $kinds = ['pub' => ['public' => true], 'hid' => ['public' => false], 'pq' => ['public' => false, 'publicly_queryable' => true], 'arr' => ['public' => true, 'rewrite' => ['slug' => 'things', 'with_front' => false]], 'off' => ['public' => true, 'rewrite' => false], 'qvoff' => ['public' => true, 'query_var' => false], 'qvname' => ['public' => false, 'query_var' => 'zzq']];
        $out = [];
        foreach ($kinds as $kind => $args) {
            register_post_type("zz_t{$kind}", $args);
            $object = get_post_type_object("zz_t{$kind}");
            $out["type {$kind}"] = [$object->rewrite, $object->query_var, $wp_rewrite->get_extra_permastruct("zz_t{$kind}")];
            unregister_post_type("zz_t{$kind}");
            register_taxonomy("zz_x{$kind}", 'post', $args);
            $object = get_taxonomy("zz_x{$kind}");
            $out["taxonomy {$kind}"] = [$object->rewrite, $object->query_var, $wp_rewrite->get_extra_permastruct("zz_x{$kind}")];
            unregister_taxonomy("zz_x{$kind}");
        }
        $say("registered under {$mode} permalinks", $out);
    }
    $wp_rewrite->set_permalink_structure('/%postname%/');
    register_post_type('zz_ta', ['public' => true, 'rewrite' => ['with_front' => false, 'slug' => 'x', 'extra' => 1]]);
    register_post_type('zz_tb', ['public' => true, 'has_archive' => true, 'hierarchical' => true]);
    register_post_type('zz_tc', ['public' => true, 'rewrite' => ['feeds' => true, 'pages' => false, 'ep_mask' => 4]]);
    register_taxonomy('zz_xa', 'post', ['rewrite' => ['hierarchical' => true, 'slug' => 'y/z', 'extra' => 1]]);
    register_taxonomy('zz_xb', 'post', ['hierarchical' => true]);
    foreach (['zz_ta', 'zz_tb', 'zz_tc'] as $type) {
        $say("type {$type}", [get_post_type_object($type)->rewrite, $wp_rewrite->extra_permastructs[$type] ?? null]);
    }
    foreach (['zz_xa', 'zz_xb'] as $taxonomy) {
        $say("taxonomy {$taxonomy}", [get_taxonomy($taxonomy)->rewrite, $wp_rewrite->extra_permastructs[$taxonomy] ?? null]);
    }
    $say('built-in patterns', [$wp_rewrite->extra_permastructs['category'] ?? null, $wp_rewrite->extra_permastructs['post_format'] ?? null, array_values(array_filter(array_keys($wp_rewrite->extra_permastructs), static fn (string $name): bool => in_array($name, ['category', 'post_tag', 'post_format'], true) || str_starts_with($name, 'zz_')))]);
    $wp_rewrite->set_permalink_structure('/blog/%postname%/');
    $say('after a structure change', [$wp_rewrite->get_extra_permastruct('zz_ta'), $wp_rewrite->get_extra_permastruct('zz_tb'), $wp_rewrite->get_extra_permastruct('category'), get_post_type_object('zz_tb')->rewrite]);
    register_post_type('zz_td', ['public' => true]);
    $say('registered after the change', [$wp_rewrite->get_extra_permastruct('zz_td'), $wp_rewrite->front]);
    unregister_post_type('zz_td');
    $say('unregistered', $wp_rewrite->get_extra_permastruct('zz_td'));
} finally {
    $wp_rewrite->set_permalink_structure($structure);
    foreach (['zz_ta', 'zz_tb', 'zz_tc'] as $type) {
        unregister_post_type($type);
    }
    foreach (['zz_xa', 'zz_xb'] as $taxonomy) {
        unregister_taxonomy($taxonomy);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
