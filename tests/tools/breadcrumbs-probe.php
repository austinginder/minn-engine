<?php
/**
 * The breadcrumbs block's helpers (probe breadcrumbs): items made, post
 * titles, a hierarchical post's ancestors, a term's ancestors and a post's
 * terms, the page number, and the crumbs an archive's main query gives
 * (dates, authors, terms, post type archives, paged). The probe's posts and
 * terms are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made['posts']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value);
    }
    return str_replace($host, '{host}', $value);
};
$page = static function (array $args) use (&$made): int {
    $made['posts'][] = $id = (int) wp_insert_post($args + ['post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'x']);
    return $id;
};
$term = static function (string $name, int $parent = 0) use (&$made): int {
    $made['terms'][] = $id = (int) (wp_insert_term($name, 'category', ['parent' => $parent])['term_id'] ?? 0);
    return $id;
};

$say('items made', $mask([block_core_breadcrumbs_create_item('Zz <b>T</b> & "q"'), block_core_breadcrumbs_create_item(''), block_core_breadcrumbs_create_item(2026)]));

$top = $page(['post_title' => 'Zz Top']);
$mid = $page(['post_title' => 'Zz Mid <i>m</i>', 'post_parent' => $top]);
$leaf = $page(['post_title' => '', 'post_parent' => $mid]);
$private = $page(['post_title' => 'Zz Private', 'post_status' => 'private', 'post_parent' => $top]);
$under = $page(['post_title' => 'Zz Under Private', 'post_parent' => $private]);
$ids += [$top => 'top', $mid => 'mid', $leaf => 'leaf', $private => 'private', $under => 'under'];
$say('post titles', $mask([block_core_breadcrumbs_get_post_title($top), block_core_breadcrumbs_get_post_title(get_post($mid)), block_core_breadcrumbs_get_post_title($leaf), block_core_breadcrumbs_get_post_title(999999999)]));
$say('a hierarchical post\'s ancestors', $mask([
    block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($leaf),
    block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($top),
    block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($private),
    block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($under),
]));

$parent_cat = $term('Zz Parent Cat');
$child_cat = $term('Zz Child Cat', $parent_cat);
$grand_cat = $term('Zz Grand Cat', $child_cat);
$ids += [$parent_cat => 'parentcat', $child_cat => 'childcat', $grand_cat => 'grandcat'];
$say('a term\'s ancestors', $mask([
    block_core_breadcrumbs_get_term_ancestors_items($grand_cat, 'category'),
    block_core_breadcrumbs_get_term_ancestors_items($child_cat, 'category'),
    block_core_breadcrumbs_get_term_ancestors_items($parent_cat, 'category'),
    block_core_breadcrumbs_get_term_ancestors_items(999999, 'category'),
]));
$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'Zz Crumb Post', 'post_status' => 'publish', 'post_content' => 'x', 'post_category' => [$child_cat]]);
$made['posts'][] = $two = (int) wp_insert_post(['post_title' => 'Zz Two Cats', 'post_status' => 'publish', 'post_content' => 'x', 'post_category' => [$grand_cat, $parent_cat]]);
$ids += [$post => 'post', $two => 'two'];
$say('a post\'s terms', $mask([
    block_core_breadcrumbs_get_terms_breadcrumbs($post, 'post'),
    block_core_breadcrumbs_get_terms_breadcrumbs($two, 'post'),
    block_core_breadcrumbs_get_terms_breadcrumbs($top, 'page'),
]));

$labels = static fn (array $items) => array_map(static fn ($item) => $item['label'], $items);
$shallow = $term('Zz Aa Shallow');
$made['posts'][] = $mixed = (int) wp_insert_post(['post_title' => 'Zz Mixed', 'post_status' => 'publish', 'post_content' => 'x', 'post_category' => [$shallow, $child_cat]]);
wp_set_object_terms($mixed, ['zz-tag-b', 'zz-tag-a'], 'post_tag');
$settings = static function ($value) use ($mixed, $labels) {
    $give = static fn () => $value;
    add_filter('block_core_breadcrumbs_post_type_settings', $give);
    $out = $labels(block_core_breadcrumbs_get_terms_breadcrumbs($mixed, 'post'));
    remove_filter('block_core_breadcrumbs_post_type_settings', $give);
    return $out;
};
$heard = [];
$listen = static function (...$args) use (&$heard) {
    $heard[] = [$args[0], $args[1], is_int($args[2])];
    return $args[0];
};
add_filter('block_core_breadcrumbs_post_type_settings', $listen, 10, 3);
$plain = $labels(block_core_breadcrumbs_get_terms_breadcrumbs($mixed, 'post'));
remove_filter('block_core_breadcrumbs_post_type_settings', $listen, 10);
register_post_type('zzbccpt', ['public' => true]);
foreach (['zzaflat' => ['hierarchical' => false, 'show_in_rest' => true], 'zzbhier' => ['hierarchical' => true, 'show_in_rest' => true], 'zznorest' => ['hierarchical' => true, 'show_in_rest' => false], 'zzhidden' => ['hierarchical' => true, 'public' => false, 'show_in_rest' => true]] as $taxonomy => $args) {
    register_taxonomy($taxonomy, ['zzbccpt', 'post'], $args + ['public' => true]);
}
$custom = static function (array $terms, $setting = null) use (&$made, $labels) {
    $made['posts'][] = $id = (int) wp_insert_post(['post_type' => 'zzbccpt', 'post_title' => 'Zz Custom', 'post_status' => 'publish']);
    foreach ($terms as $taxonomy => $slugs) {
        wp_set_object_terms($id, $slugs, $taxonomy);
    }
    $give = static fn () => $setting;
    if ($setting !== null) {
        add_filter('block_core_breadcrumbs_post_type_settings', $give);
    }
    $out = $labels(block_core_breadcrumbs_get_terms_breadcrumbs($id, 'zzbccpt'));
    remove_filter('block_core_breadcrumbs_post_type_settings', $give);
    return $out;
};
register_shutdown_function(static function (): void {
    foreach (['zzaflat', 'zzbhier', 'zznorest', 'zzhidden', 'post_tag'] as $taxonomy) {
        foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) as $found) {
            if ($taxonomy !== 'post_tag' || str_starts_with($found->slug, 'zz-tag-')) {
                wp_delete_term($found->term_id, $taxonomy);
            }
        }
    }
});
$say('the taxonomy a trail follows', [
    $plain, $heard,
    $settings(['taxonomy' => 'post_tag']),
    $settings(['taxonomy' => 'category', 'term' => 'zz-child-cat']),
    $settings(['term' => 'zz-child-cat']),
    $settings(['taxonomy' => 'zz-none']),
    $settings(['taxonomy' => 'category', 'term' => 'zz-nope']),
    $settings('notarray'),
    $settings(['taxonomy' => 'zznorest']),
    $custom(['zzaflat' => ['zz-flat-a'], 'zzbhier' => ['zz-hier-a']]),
    $custom(['zzbhier' => ['zz-hier-b']]),
    $custom(['zznorest' => ['zz-norest']]),
    $custom(['zzhidden' => ['zz-hidden']]),
    $custom([]),
    $custom(['zzaflat' => ['zz-flat-c'], 'zzbhier' => ['zz-hier-c']], ['taxonomy' => 'zzbhier']),
    $custom(['zzbhier' => ['zz-hier-d']], ['taxonomy' => 'zzaflat']),
]);

$archive = static function (array $vars) use ($mask) {
    $GLOBALS['wp_query'] = new WP_Query($vars);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    return $mask([block_core_breadcrumbs_get_archive_breadcrumbs(), block_core_breadcrumbs_is_paged(), block_core_breadcrumbs_create_page_number_item(), block_core_breadcrumbs_create_page_number_item('page')]);
};
register_post_type('zzbc', ['public' => true, 'has_archive' => true, 'label' => 'Zz Things']);
$say('an archive\'s crumbs', [
    $archive(['year' => 2026]),
    $archive(['year' => 2026, 'monthnum' => 8]),
    $archive(['year' => 2026, 'monthnum' => 8, 'day' => 28]),
    $archive(['m' => '202608']),
    $archive(['m' => '2026082812']),
    $archive(['author_name' => 'admin']),
    $archive(['s' => 'hello']),
    $archive(['category_name' => 'uncategorized']),
    $archive(['cat' => $grand_cat]),
    $archive(['tag' => 'zz-none']),
    $archive([]),
    $archive(['post_type' => 'wp_block']),
    $archive(['post_type' => 'zzbc']),
    $archive(['year' => 2026, 'paged' => 2]),
    $archive(['cat' => $child_cat, 'paged' => 3]),
    $archive(['page' => 2]),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
