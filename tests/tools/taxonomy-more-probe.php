<?php
/**
 * taxonomy.php's remaining functions (probe taxonomy-more): whether term meta
 * is supported (the database version gate) and a term's meta rows, whether a
 * term can be viewed publicly, the post's taxonomies printed, the record of
 * terms split from shared ones, and a deleted term's meta removed with it. The probe's post, terms and taxonomies are
 * removed. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach (['zz_tm_hidden', 'zz_tm_queryable', 'zz_tm_shown'] as $taxonomy) {
        unregister_taxonomy($taxonomy);
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$term = static function (string $label, string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = $label;
    return $id;
};

register_taxonomy('zz_tm_hidden', 'post', ['public' => false, 'label' => 'Zz hidden']);
register_taxonomy('zz_tm_queryable', 'post', ['public' => false, 'publicly_queryable' => true, 'label' => 'Zz queryable']);
register_taxonomy('zz_tm_shown', 'post', ['public' => true, 'label' => 'Zz Shown', 'labels' => ['singular_name' => 'Shown one']]);
$category = $term('category', 'zz tm category', 'category');
$second = $term('second', 'zz tm second', 'category');
$tag = $term('tag', 'zz tm tag', 'post_tag');
$hidden = $term('hidden', 'zz tm hidden', 'zz_tm_hidden');
$queryable = $term('queryable', 'zz tm queryable', 'zz_tm_queryable');
$shown = $term('shown', 'zz tm shown', 'zz_tm_shown');
$bare = $term('bare', 'zz tm bare', 'category');

add_term_meta($category, 'zz_b', 'second key');
add_term_meta($category, 'zz_a', 'first');
add_term_meta($category, 'zz_a', ['an' => 'array']);
$metaIds = static function (array $rows) use (&$ids) {
    $seen = [];
    foreach ($rows as $i => $row) {
        $seen[] = array_keys($row);
        $ids[(int) $row['meta_id']] = 'meta ' . $i;
    }
    return $seen;
};
$rows = has_term_meta($category);
$say('term meta rows', [$metaIds($rows), $mask($rows), has_term_meta($bare), has_term_meta(999999999), has_term_meta((string) $category) === $rows]);
$oldDb = static fn () => '34369';
$newDb = static fn () => '34370';
$say('term meta support', [
    wp_check_term_meta_support_prefilter(null),
    wp_check_term_meta_support_prefilter('kept'),
    wp_check_term_meta_support_prefilter(false),
    (static function () use ($oldDb, $category, $rows) {
        $mid = (int) $rows[0]['meta_id'];
        add_filter('pre_option_db_version', $oldDb);
        $out = [wp_check_term_meta_support_prefilter(null), wp_check_term_meta_support_prefilter('kept'), has_term_meta($category), get_term_meta($category, 'zz_a'), add_term_meta($category, 'zz_c', 'x'), update_term_meta($category, 'zz_a', 'y', 'first'), delete_term_meta($category, 'zz_b'), get_metadata_by_mid('term', $mid), update_metadata_by_mid('term', $mid, 'z'), delete_metadata_by_mid('term', $mid), update_termmeta_cache([$category])];
        remove_filter('pre_option_db_version', $oldDb);
        return $out;
    })(),
    (static function () use ($newDb) {
        add_filter('pre_option_db_version', $newDb);
        $out = wp_check_term_meta_support_prefilter(null);
        remove_filter('pre_option_db_version', $newDb);
        return $out;
    })(),
    get_term_meta($category, 'zz_c'),
    get_term_meta($category, 'zz_a'),
    get_term_meta($category, 'zz_b'),
]);
$say('term meta support, where it is hooked', array_filter(array_map(
    static fn ($hook) => [$hook, has_filter($hook, 'wp_check_term_meta_support_prefilter')],
    ['get_term_metadata', 'add_term_metadata', 'update_term_metadata', 'delete_term_metadata', 'get_term_metadata_by_mid', 'update_term_metadata_by_mid', 'delete_term_metadata_by_mid', 'update_term_metadata_cache', 'get_post_metadata'],
), static fn ($row) => $row[1] !== false));

$say('publicly viewable', [
    is_term_publicly_viewable($category),
    is_term_publicly_viewable(get_term($tag)),
    is_term_publicly_viewable((string) $category),
    is_term_publicly_viewable($hidden),
    is_term_publicly_viewable($queryable),
    is_term_publicly_viewable($shown),
]);

$post = (int) wp_insert_post(['post_title' => 'zz tm post', 'post_status' => 'publish', 'post_content' => 'x', 'post_category' => [$category, $second]]);
$made['posts'][] = $post;
$ids[$post] = 'post';
wp_set_object_terms($post, [$tag], 'post_tag');
wp_set_object_terms($post, [$hidden], 'zz_tm_hidden');
wp_set_object_terms($post, [$shown], 'zz_tm_shown');
$plain = (int) wp_insert_post(['post_title' => 'zz tm plain', 'post_status' => 'publish', 'post_content' => 'x', 'post_type' => 'page']);
$made['posts'][] = $plain;
$ids[$plain] = 'plain';
$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);
$say('taxonomies printed', $mask([
    $echoed(static fn () => the_taxonomies()),
    $echoed(static fn () => the_taxonomies(['before' => '<p>', 'sep' => ' | ', 'after' => '</p>'])),
    $echoed(static fn () => the_taxonomies(['template' => '%s are %l.', 'term_template' => '<em>%2$s</em>'])),
    $echoed(static fn () => the_taxonomies(['post' => $plain, 'before' => '[', 'after' => ']'])),
    $echoed(static fn () => the_taxonomies('before=(&after=)&sep=;')),
]));
wp_reset_postdata();

$splits = static fn () => [5 => ['category' => 9, 'post_tag' => 11], 6 => []];
$say('split terms', [
    wp_get_split_terms(5),
    (static function () use ($splits) {
        add_filter('pre_option__split_terms', $splits);
        $out = [wp_get_split_terms(5), wp_get_split_terms('5'), wp_get_split_terms(6), wp_get_split_terms(7), wp_get_split_term(5, 'post_tag'), wp_get_split_term(5, 'nav_menu')];
        remove_filter('pre_option__split_terms', $splits);
        return $out;
    })(),
]);

$doomed = $term('doomed', 'zz tm doomed', 'category');
$made['terms'][] = [$child = (int) wp_insert_term('zz tm doomed child', 'category', ['parent' => $doomed])['term_id'], 'category'];
$ids[$child] = 'child';
add_term_meta($doomed, 'zz_d2', 'two');
add_term_meta($doomed, 'zz_d1', 'one');
$heard = [];
$hooks = ['pre_delete_term', 'edit_term_taxonomies', 'edited_term_taxonomies', 'delete_term_meta', 'deleted_term_meta', 'delete_term_taxonomy', 'deleted_term_taxonomy', 'delete_term', 'delete_category'];
$listeners = [];
foreach ($hooks as $hook) {
    $listeners[$hook] = static function (...$args) use ($hook, &$heard): void {
        $heard[] = [$hook, array_map(static fn ($arg) => is_object($arg) ? get_class($arg) : $arg, array_slice($args, 0, 3))];
    };
    add_action($hook, $listeners[$hook], 10, 5);
}
$metaRows = has_term_meta($doomed);
foreach ($metaRows as $i => $row) {
    $ids[(int) $row['meta_id']] = 'doomed meta ' . $i;
}
// The tables' counters usually agree, so a term's term_taxonomy_id is its term_id; labelled only when not.
foreach (['doomed' => $doomed, 'child' => $child] as $label => $id) {
    $tt = (int) get_term($id)->term_taxonomy_id;
    if ($tt !== $id) {
        $ids[$tt] = $label . ' tt';
    }
}
wp_delete_term($doomed, 'category');
foreach ($listeners as $hook => $listener) {
    remove_action($hook, $listener, 10);
}
$say('a deleted term takes its meta', $mask([$heard, has_term_meta($doomed), get_term_meta($doomed, 'zz_d1'), get_term($child)->parent]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
