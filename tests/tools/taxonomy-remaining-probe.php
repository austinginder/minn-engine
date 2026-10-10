<?php
/**
 * The rest of taxonomy.php (probe taxonomy-remaining): taxonomy labels built
 * from a taxonomy's own, viewable taxonomies, a term to edit, terms added to
 * and removed from objects, whether an object is in a term, term children and
 * padded counts, hierarchy loops, term counts (now, deferred, generic), the
 * taxonomy cache, and a category deleted. The probe's posts, terms and
 * taxonomies are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
register_taxonomy('zz_tr_shelf', ['post', 'page'], ['public' => true, 'hierarchical' => true, 'rewrite' => false, 'labels' => ['name' => 'Shelves']]);
register_taxonomy('zz_tr_tag', 'post', ['public' => true, 'rewrite' => false]);
register_taxonomy('zz_tr_hidden', 'post', ['public' => false, 'publicly_queryable' => false, 'rewrite' => false]);
register_taxonomy('zz_tr_generic', 'zz_tr_none', ['public' => true, 'rewrite' => false, 'update_count_callback' => '_update_generic_term_count']);
$categoryChildren = get_option('category_children', null);
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $categoryChildren, $pings): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    // Terms made along the way (a numeric string given as a term is a new term's name) go too.
    foreach (['zz_tr_shelf', 'zz_tr_tag', 'zz_tr_hidden', 'zz_tr_generic'] as $taxonomy) {
        foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids']) as $id) {
            if ((int) $id > 0) {
                wp_delete_term((int) $id, $taxonomy);
            }
        }
        delete_option("{$taxonomy}_children");
    }
    $categoryChildren === null ? delete_option('category_children') : update_option('category_children', $categoryChildren);
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Term) {
        return $mask(['term' => $value->term_id, 'name' => $value->name, 'slug' => $value->slug, 'parent' => $value->parent, 'count' => $value->count, 'taxonomy' => $value->taxonomy, 'filter' => $value->filter]);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code(), 'message' => $value->get_error_message()];
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (is_string($value) && preg_match('/^\d+$/', $value) && isset($ids[(int) $value])) {
        return '{' . $ids[(int) $value] . '}';
    }
    return $value;
};
$term = static function (string $name, string $taxonomy, int $parent = 0) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy, ['parent' => $parent])['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x']);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};

$labels = static fn ($taxonomy) => (array) get_taxonomy_labels($taxonomy);
$say('taxonomy labels', [
    $labels(get_taxonomy('category')), $labels(get_taxonomy('post_tag')), $labels(get_taxonomy('zz_tr_shelf')), $labels(get_taxonomy('zz_tr_tag')),
    $labels((object) ['name' => 'zz_tr_bare', 'labels' => [], 'hierarchical' => false]),
    $labels((object) ['name' => 'zz_tr_given', 'labels' => ['name' => 'Givens', 'singular_name' => 'Given', 'zz_custom' => 'C'], 'hierarchical' => true, 'label' => 'Ignored']),
    $labels((object) ['name' => 'zz_tr_labelled', 'labels' => ['menu_name' => 'Menu'], 'hierarchical' => false, 'label' => 'Labelled']),
]);
$heard = [];
add_filter('taxonomy_labels_zz_tr_filtered', $listener = static function ($labels) use (&$heard) {
    $heard[] = [get_class($labels), $labels->name ?? null];
    $labels->zz_added = 'A';
    return $labels;
});
$filtered = get_taxonomy_labels((object) ['name' => 'zz_tr_filtered', 'labels' => ['name' => 'Filtered'], 'hierarchical' => false]);
remove_filter('taxonomy_labels_zz_tr_filtered', $listener);
$say('taxonomy labels filter', [$heard, $filtered->zz_added ?? null]);

$say('viewable taxonomies', array_map(static fn ($taxonomy) => [$taxonomy, is_taxonomy_viewable($taxonomy)], ['category', 'post_tag', 'post_format', 'nav_menu', 'wp_theme', 'zz_tr_shelf', 'zz_tr_hidden', 'zz_tr_missing', '']) + [99 => is_taxonomy_viewable(get_taxonomy('zz_tr_shelf'))]);

$root = $term('zz tr root', 'zz_tr_shelf');
$child = $term('zz tr child', 'zz_tr_shelf', $root);
$grand = $term('zz tr grand', 'zz_tr_shelf', $child);
$other = $term('zz tr other "q" & <b>co</b>', 'zz_tr_shelf');
$tagA = $term('zz tr tag a', 'zz_tr_tag');
$tagB = $term('zz tr tag b', 'zz_tr_tag');
$say('a term to edit', $mask([get_term_to_edit($other, 'zz_tr_shelf'), get_term_to_edit($root, 'zz_tr_tag'), get_term_to_edit(999999, 'zz_tr_shelf'), get_term_to_edit($root, 'zz_tr_missing'), get_term_to_edit((string) $child, 'zz_tr_shelf')]));

$first = $post('zz tr first');
$second = $post('zz tr second');
$objects = [
    wp_add_object_terms($first, [$child, 'zz tr new by add'], 'zz_tr_shelf'), wp_add_object_terms($first, $tagA, 'zz_tr_tag'), wp_add_object_terms($first, (string) $tagB, 'zz_tr_tag'),
    wp_add_object_terms($first, $child, 'zz_tr_shelf'), wp_add_object_terms($first, [], 'zz_tr_tag'), wp_add_object_terms($first, $root, 'zz_tr_missing'),
];
foreach (get_terms(['taxonomy' => 'zz_tr_shelf', 'name' => 'zz tr new by add', 'hide_empty' => false]) as $added) {
    $made['terms'][] = [(int) $added->term_id, 'zz_tr_shelf'];
    $ids[(int) $added->term_id] = 'new-by-add';
}
// A numeric string given as a term is a new term's name.
foreach (get_terms(['taxonomy' => 'zz_tr_tag', 'name' => (string) $tagB, 'hide_empty' => false]) as $added) {
    $ids[(int) $added->term_id] = 'named-by-number';
    $ids[(int) $added->term_taxonomy_id] = 'named-by-number';
}
$objects[] = wp_get_object_terms($first, ['zz_tr_shelf', 'zz_tr_tag'], ['fields' => 'ids', 'orderby' => 'term_id']);
$objects[] = [wp_remove_object_terms($first, $tagA, 'zz_tr_tag'), wp_remove_object_terms($first, [$tagA], 'zz_tr_tag'), wp_remove_object_terms($first, 'zz-tr-tag-b', 'zz_tr_tag'), wp_remove_object_terms($first, [999999], 'zz_tr_tag'), wp_remove_object_terms($first, $root, 'zz_tr_missing')];
$objects[] = wp_get_object_terms($first, ['zz_tr_shelf', 'zz_tr_tag'], ['fields' => 'ids', 'orderby' => 'term_id']);
$heard = [];
foreach (['add_term_relationship', 'added_term_relationship', 'delete_term_relationships', 'deleted_term_relationships', 'set_object_terms'] as $hook) {
    add_action($hook, $listeners[$hook] = static function (...$args) use ($hook, &$heard, $mask) {
        $heard[] = [$hook, $mask(array_map(static fn ($arg) => is_array($arg) ? array_map('intval', $arg) : (is_numeric($arg) ? (int) $arg : $arg), $args))];
    }, 10, 6);
}
wp_add_object_terms($second, [$root, $grand], 'zz_tr_shelf');
wp_remove_object_terms($second, [$grand], 'zz_tr_shelf');
foreach ($listeners as $hook => $listener) {
    remove_action($hook, $listener, 10);
}
$objects[] = $heard;
$say('terms added to and removed from objects', $mask($objects));

$say('whether an object is in a term', $mask([
    is_object_in_term($first, 'zz_tr_shelf'), is_object_in_term($first, 'zz_tr_shelf', $child), is_object_in_term($first, 'zz_tr_shelf', (string) $child), is_object_in_term($first, 'zz_tr_shelf', 'zz-tr-child'), is_object_in_term($first, 'zz_tr_shelf', 'zz tr child'),
    is_object_in_term($first, 'zz_tr_shelf', [$root, $child]), is_object_in_term($first, 'zz_tr_shelf', $root), is_object_in_term($first, 'zz_tr_tag'), is_object_in_term($first, 'zz_tr_missing'), is_object_in_term($second, 'zz_tr_shelf', [999999, 'zz-tr-root']), is_object_in_term(999999, 'zz_tr_shelf'),
]));

$all = get_terms(['taxonomy' => 'zz_tr_shelf', 'hide_empty' => false, 'orderby' => 'term_id']);
$childIds = static fn ($found) => is_array($found) ? array_map(static fn ($t) => is_object($t) ? $t->term_id : $t, $found) : $found;
$say('term children', $mask([
    $childIds(_get_term_children($root, $all, 'zz_tr_shelf')), $childIds(_get_term_children($child, $all, 'zz_tr_shelf')), $childIds(_get_term_children($grand, $all, 'zz_tr_shelf')),
    $childIds(_get_term_children($root, array_map(static fn ($t) => $t->term_id, $all), 'zz_tr_shelf')), $childIds(_get_term_children(0, $all, 'zz_tr_missing')), $childIds(_get_term_children($root, [], 'zz_tr_shelf')),
]));

$third = $post('zz tr third');
wp_set_object_terms($third, [$grand], 'zz_tr_shelf');
$padded = get_terms(['taxonomy' => 'zz_tr_shelf', 'hide_empty' => false, 'orderby' => 'term_id']);
_pad_term_counts($padded, 'zz_tr_shelf');
$plain = get_terms(['taxonomy' => 'zz_tr_shelf', 'hide_empty' => false, 'orderby' => 'term_id']);
$say('padded counts', $mask([array_map(static fn ($t) => [$t->term_id, $t->count], $plain), array_map(static fn ($t) => [$t->term_id, $t->count], $padded)]));

$loops = [wp_check_term_hierarchy_for_loops(0, $root, 'zz_tr_shelf'), wp_check_term_hierarchy_for_loops($root, 0, 'zz_tr_shelf'), wp_check_term_hierarchy_for_loops($root, $root, 'zz_tr_shelf'), wp_check_term_hierarchy_for_loops($grand, $root, 'zz_tr_shelf'), wp_check_term_hierarchy_for_loops($other, $root, 'zz_tr_shelf'), wp_check_term_hierarchy_for_loops(999999, $root, 'zz_tr_shelf')];
$parents = static fn () => array_map(static fn ($id) => get_term($id, 'zz_tr_shelf')->parent, [$root, $child, $grand]);
$loops[] = $parents();
$say('hierarchy loops', $mask($loops));

$count = static fn ($id, $taxonomy) => (int) get_term($id, $taxonomy)->count;
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['count' => 9], ['term_id' => $root, 'taxonomy' => 'zz_tr_shelf']);
clean_term_cache($root, 'zz_tr_shelf');
$counts = [$count($root, 'zz_tr_shelf'), wp_update_term_count_now([$root], 'zz_tr_shelf'), $count($root, 'zz_tr_shelf')];
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['count' => 9], ['term_id' => $root, 'taxonomy' => 'zz_tr_shelf']);
clean_term_cache($root, 'zz_tr_shelf');
wp_defer_term_counting(true);
$counts[] = wp_update_term_count([$root], 'zz_tr_shelf');
$counts[] = $count($root, 'zz_tr_shelf');
$counts[] = wp_update_term_count([], '', true);
$counts[] = $count($root, 'zz_tr_shelf');
wp_defer_term_counting(false);
$counts[] = [wp_update_term_count([], 'zz_tr_shelf'), wp_update_term_count_now([], 'zz_tr_shelf')];
$generic = $term('zz tr generic', 'zz_tr_generic');
$GLOBALS['wpdb']->insert($GLOBALS['wpdb']->term_relationships, ['object_id' => $first, 'term_taxonomy_id' => get_term($generic, 'zz_tr_generic')->term_taxonomy_id]);
$GLOBALS['wpdb']->insert($GLOBALS['wpdb']->term_relationships, ['object_id' => $second, 'term_taxonomy_id' => get_term($generic, 'zz_tr_generic')->term_taxonomy_id]);
_update_generic_term_count([get_term($generic, 'zz_tr_generic')->term_taxonomy_id], get_taxonomy('zz_tr_generic'));
clean_term_cache($generic, 'zz_tr_generic');
$counts[] = $count($generic, 'zz_tr_generic');
$heard = [];
add_action('edit_term_taxonomy', $editHeard = static function ($ttId, $taxonomy) use (&$heard) {
    $heard[] = ['edit_term_taxonomy', is_object($taxonomy) ? $taxonomy->name : $taxonomy];
}, 10, 2);
add_action('edited_term_taxonomy', $editedHeard = static function ($ttId, $taxonomy) use (&$heard) {
    $heard[] = ['edited_term_taxonomy', is_object($taxonomy) ? $taxonomy->name : $taxonomy];
}, 10, 2);
wp_update_term_count_now([get_term($root, 'zz_tr_shelf')->term_taxonomy_id], 'zz_tr_shelf');
_update_generic_term_count([get_term($generic, 'zz_tr_generic')->term_taxonomy_id], get_taxonomy('zz_tr_generic'));
remove_action('edit_term_taxonomy', $editHeard, 10);
remove_action('edited_term_taxonomy', $editedHeard, 10);
$counts[] = $heard;
$GLOBALS['wpdb']->delete($GLOBALS['wpdb']->term_relationships, ['term_taxonomy_id' => get_term($generic, 'zz_tr_generic')->term_taxonomy_id]);
$say('term counts', $counts);

wp_cache_set('all_ids', [1, 2], 'zz_tr_shelf');
wp_cache_set('get', ['x'], 'zz_tr_shelf');
update_option('zz_tr_shelf_children', ['stale' => [1]]);
$heard = [];
add_action('clean_taxonomy_cache', $cleanHeard = static function ($taxonomy) use (&$heard) {
    $heard[] = $taxonomy;
});
clean_taxonomy_cache('zz_tr_shelf');
remove_action('clean_taxonomy_cache', $cleanHeard);
$children = get_option('zz_tr_shelf_children');
$say('the taxonomy cache', $mask([wp_cache_get('all_ids', 'zz_tr_shelf'), wp_cache_get('get', 'zz_tr_shelf'), is_array($children) ? array_map(static fn ($parent, $list) => [$parent, $list], array_keys($children), $children) : $children, $heard, wp_cache_get('last_changed', 'terms') !== false]));

$doomed = $term('zz tr doomed', 'category');
$doomedChild = $term('zz tr doomed child', 'category', $doomed);
$made['posts'][] = $inDoomed = (int) wp_insert_post(['post_title' => 'zz tr in doomed', 'post_status' => 'publish', 'post_category' => [$doomed]]);
$ids[$inDoomed] = 'in-doomed';
$say('a category deleted', $mask([
    wp_delete_category($doomed), get_term($doomed, 'category'), get_term($doomedChild, 'category')->parent, wp_get_post_categories($inDoomed) === [(int) get_option('default_category')], wp_delete_category((int) get_option('default_category')), wp_delete_category(999999),
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
