<?php
/**
 * The post type and taxonomy objects (probe type-objects): WP_Post_Type and
 * WP_Taxonomy made directly (their properties filled from the arguments,
 * without registering) and the steps registration takes on them: supports,
 * rewrite rules and query vars, meta box callbacks, hooks, taxonomies for
 * a type, each undone again, and the default labels. Registrations live
 * for the request only and are removed; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_rewrite, $wp;
$scalars = static function (object $object): array {
    $vars = get_object_vars($object);
    ksort($vars);
    return array_map(static fn ($value) => is_object($value) ? ['object' => get_object_vars($value)] : ($value instanceof Closure ? 'closure' : $value), $vars);
};
$rewrites = static function (string $name, string $var) use ($wp_rewrite, $wp) {
    $tag = array_search("%{$name}%", $wp_rewrite->rewritecode, true);
    return [
        'permastruct' => $wp_rewrite->extra_permastructs[$name] ?? null,
        'tag' => $tag === false ? null : [$wp_rewrite->rewritereplace[$tag], $wp_rewrite->queryreplace[$tag]],
        'query var' => in_array($var, $wp->public_query_vars, true),
        'top rules' => array_values(array_filter(array_keys($wp_rewrite->extra_rules_top), static fn ($regex) => str_contains($regex, 'zz'))),
    ];
};
$zz_box = static function (): void {
};

$args = [
    'public' => true, 'label' => 'Zz Books', 'labels' => ['singular_name' => 'Zz Book', 'add_new_item' => 'Add Zz Book'],
    'supports' => ['title', 'thumbnail', 'zz-feature' => ['size' => 2]], 'taxonomies' => ['category', 'zz_genre'], 'has_archive' => 'zz-books',
    'rewrite' => ['slug' => 'zz-books-items'], 'register_meta_box_cb' => 'zz_probe_meta_box', 'capability_type' => 'zz_book', 'map_meta_cap' => true,
    'show_in_rest' => true, 'rest_base' => 'zz-books',
];
$made = new WP_Post_Type('zz_book', $args);
$say('a post type made directly', [$scalars($made), post_type_exists('zz_book'), get_all_post_type_supports('zz_book')]);

$say('a post type with few arguments', [
    $scalars(new WP_Post_Type('zz_plain')),
    $scalars(new WP_Post_Type('zz_tree', ['hierarchical' => true, 'public' => false, 'query_var' => 'zz_tree_q', 'rewrite' => false, 'supports' => false])),
]);

$say('supports added and removed', [
    (static function () use ($made) {
        $made->add_supports();
        return get_all_post_type_supports('zz_book');
    })(),
    (static function () use ($made) {
        $made->remove_supports();
        return get_all_post_type_supports('zz_book');
    })(),
]);

$say('rewrite rules added and removed', [
    (static function () use ($made, $rewrites) {
        $made->add_rewrite_rules();
        return $rewrites('zz_book', 'zz_book');
    })(),
    (static function () use ($made, $rewrites) {
        $made->remove_rewrite_rules();
        return $rewrites('zz_book', 'zz_book');
    })(),
    (static function () use ($rewrites) {
        $hidden = new WP_Post_Type('zz_hidden', ['public' => false, 'publicly_queryable' => false, 'rewrite' => ['slug' => 'zz-hidden']]);
        $hidden->add_rewrite_rules();
        $out = $rewrites('zz_hidden', 'zz_hidden');
        $hidden->remove_rewrite_rules();
        return [$out, $rewrites('zz_hidden', 'zz_hidden')];
    })(),
]);

$say('meta boxes and hooks', [
    (static function () use ($made) {
        $made->register_meta_boxes();
        return [has_action('add_meta_boxes_zz_book', 'zz_probe_meta_box'), has_action('add_meta_boxes', 'zz_probe_meta_box')];
    })(),
    (static function () use ($made) {
        $made->unregister_meta_boxes();
        return has_action('add_meta_boxes_zz_book', 'zz_probe_meta_box');
    })(),
    (static function () use ($made) {
        $made->add_hooks();
        return [has_action('future_zz_book', '_future_post_hook'), has_action('future_post', '_future_post_hook'), has_action('future_page', '_future_post_hook')];
    })(),
    (static function () use ($made) {
        $made->remove_hooks();
        return has_action('future_zz_book', '_future_post_hook');
    })(),
]);

register_taxonomy('zz_genre', [], ['public' => true, 'rewrite' => false]);
register_post_type('zz_book', $args);
$registered = get_post_type_object('zz_book');
$say('a registered post type', [
    get_class($registered), $registered->supports ?? 'none', get_object_taxonomies('zz_book'), get_taxonomy('zz_genre')->object_type, get_taxonomy('category')->object_type,
    has_action('future_zz_book', '_future_post_hook'), has_action('add_meta_boxes_zz_book', 'zz_probe_meta_box'),
    (static function () use ($registered) {
        $registered->unregister_taxonomies();
        return [get_object_taxonomies('zz_book'), get_taxonomy('zz_genre')->object_type, get_taxonomy('category')->object_type];
    })(),
    (static function () use ($registered) {
        $registered->register_taxonomies();
        return [get_object_taxonomies('zz_book'), get_taxonomy('zz_genre')->object_type];
    })(),
]);
unregister_post_type('zz_book');
$say('unregistered', [
    post_type_exists('zz_book'), get_object_taxonomies('zz_book'), get_taxonomy('zz_genre')->object_type, has_action('future_zz_book', '_future_post_hook'), has_action('add_meta_boxes_zz_book', 'zz_probe_meta_box'),
    get_all_post_type_supports('zz_book'), $rewrites('zz_book', 'zz_book'),
]);

$say('default post type labels', [
    count((array) WP_Post_Type::get_default_labels()) > 10, WP_Post_Type::get_default_labels()['name'] ?? null,
    (static function () {
        WP_Post_Type::reset_default_labels();
        return array_slice(WP_Post_Type::get_default_labels(), 0, 3, true);
    })(),
]);

$taxonomy = new WP_Taxonomy('zz_topic', ['post', 'zz_none'], ['public' => true, 'hierarchical' => true, 'labels' => ['singular_name' => 'Zz Topic'], 'rewrite' => ['slug' => 'zz-topics'], 'query_var' => 'zz_topic_q', 'update_count_callback' => '_update_post_term_count', 'default_term' => 'Zz Default']);
$say('a taxonomy made directly', [
    $scalars($taxonomy), taxonomy_exists('zz_topic'),
    $scalars(new WP_Taxonomy('zz_flat', 'post')),
    $scalars(new WP_Taxonomy('zz_private', [], ['public' => false, 'rewrite' => true, 'query_var' => true])),
]);
$say('taxonomy rewrite rules and hooks', [
    (static function () use ($taxonomy, $rewrites) {
        $taxonomy->add_rewrite_rules();
        return $rewrites('zz_topic', 'zz_topic_q');
    })(),
    (static function () use ($taxonomy, $rewrites) {
        $taxonomy->remove_rewrite_rules();
        return $rewrites('zz_topic', 'zz_topic_q');
    })(),
    (static function () use ($taxonomy) {
        $taxonomy->add_hooks();
        return [has_filter('wp_ajax_add-zz_topic', '_wp_ajax_add_hierarchical_term'), has_action('wp_ajax_add-zz_topic')];
    })(),
    (static function () use ($taxonomy) {
        $taxonomy->remove_hooks();
        return [has_action('wp_ajax_add-zz_topic')];
    })(),
]);
$say('default taxonomy labels', [
    count((array) WP_Taxonomy::get_default_labels()) > 10, WP_Taxonomy::get_default_labels()['name'] ?? null,
    (static function () {
        WP_Taxonomy::reset_default_labels();
        return array_slice(WP_Taxonomy::get_default_labels(), 0, 3, true);
    })(),
]);
unregister_taxonomy('zz_genre');

foreach (['zz_a', 'zz_b', 'zz_c'] as $type) {
    register_post_type($type, ['show_in_rest' => true]);
}
register_taxonomy('zz_shared', ['zz_a', 'zz_b', 'zz_c'], ['show_in_rest' => true]);
$say('a type taken off a taxonomy from the middle, then put back', [
    unregister_taxonomy_for_object_type('zz_shared', 'zz_b'), get_taxonomy('zz_shared')->object_type,
    rest_do_request(new WP_REST_Request('GET', '/wp/v2/taxonomies/zz_shared'))->get_data()['types'] ?? null,
    register_taxonomy_for_object_type('zz_shared', 'zz_b'), get_taxonomy('zz_shared')->object_type,
    unregister_taxonomy_for_object_type('zz_shared', 'zz_c'), get_taxonomy('zz_shared')->object_type,
    unregister_taxonomy_for_object_type('zz_shared', 'zz_c'), get_object_taxonomies('zz_a'),
]);
unregister_taxonomy('zz_shared');
foreach (['zz_a', 'zz_b', 'zz_c'] as $type) {
    unregister_post_type($type);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
