<?php
/**
 * post.php's remaining helpers (probe post-more): labels built from defaults,
 * the last post time by field and timezone, page paths walked from a
 * children map, meta capabilities recorded, post parent caches primed and
 * updated, the mime types a post type's posts carry, a post's enclosures,
 * the private-posts SQL, whether a post can be embedded, the __trashed slug
 * suffixes, old auto-drafts removed, and term meta queued for lazy loading.
 * The probe's posts, terms and type are removed. Same protocol as
 * api-probe.php.
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
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
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
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (string $label, array $fields) use (&$made, &$ids): int {
    $id = (int) wp_insert_post($fields + ['post_title' => 'zz pm ' . $label, 'post_content' => 'x', 'post_status' => 'publish']);
    $made['posts'][] = $id;
    $ids[$id] = $label;
    return $id;
};

$defaults = ['name' => ['Things', 'Pages of things'], 'singular_name' => ['Thing', 'Page of thing'], 'add_new_item' => ['Add Thing', 'Add Page'], 'menu_name' => [null, null], 'all_items' => [null, null], 'archives' => [null, null]];
$object = static fn (array $fields) => (object) ($fields + ['labels' => [], 'hierarchical' => false, 'label' => 'Zz', 'name' => 'zz_labels']);
$say('labels from defaults', [
    (array) _get_custom_object_labels($object([]), $defaults),
    (array) _get_custom_object_labels($object(['hierarchical' => true]), $defaults),
    (array) _get_custom_object_labels($object(['labels' => ['singular_name' => 'One Zz', 'name' => 'Many Zz'], 'label' => 'Label Zz']), $defaults),
    (array) _get_custom_object_labels($object(['label' => 'Only Label']), $defaults),
    (array) _get_custom_object_labels($object(['labels' => ['menu_name' => 'Menu Zz', 'all_items' => 'Every Zz']]), $defaults),
]);

register_post_type('zz_pm_type', ['public' => true, 'label' => 'Zz pm']);
register_post_type('zz_pm_hidden', ['public' => false, 'label' => 'Zz hidden']);
$older = $post('older', ['post_type' => 'zz_pm_type', 'post_date' => '2030-01-01 10:00:00', 'post_date_gmt' => '2030-01-01 15:00:00']);
$newer = $post('newer', ['post_type' => 'zz_pm_type', 'post_date' => '2030-02-01 10:00:00', 'post_date_gmt' => '2030-02-01 15:00:00']);
global $wpdb;
$wpdb->update($wpdb->posts, ['post_modified' => '2030-03-01 09:00:00', 'post_modified_gmt' => '2030-03-01 14:00:00', 'post_status' => 'publish'], ['ID' => $older]);
$wpdb->update($wpdb->posts, ['post_modified' => '2030-02-02 09:00:00', 'post_modified_gmt' => '2030-02-02 14:00:00', 'post_status' => 'publish'], ['ID' => $newer]);
clean_post_cache($older);
clean_post_cache($newer);
$say('last post time', [
    _get_last_post_time('gmt', 'date', 'zz_pm_type'),
    _get_last_post_time('blog', 'date', 'zz_pm_type'),
    _get_last_post_time('server', 'date', 'zz_pm_type'),
    _get_last_post_time('gmt', 'modified', 'zz_pm_type'),
    _get_last_post_time('blog', 'modified', 'zz_pm_type'),
    _get_last_post_time('gmt', 'other', 'zz_pm_type'),
    _get_last_post_time('gmt', 'date', 'zz_pm_hidden'),
    _get_last_post_time('gmt', 'date', ['zz_pm_type', 'zz_pm_hidden']),
]);

$page = static fn (int $id, string $name, int $parent) => (object) ['ID' => $id, 'post_name' => $name, 'post_parent' => $parent];
$children = [0 => [$page(11, 'top', 0), $page(12, 'other', 0)], 11 => [$page(21, 'child', 11)], 21 => [$page(31, 'grandchild', 21)]];
$result = [];
_page_traverse_name(0, $children, $result);
$fromChild = [];
_page_traverse_name(11, $children, $fromChild);
$say('page paths walked', [$result, $fromChild, $children]);

global $post_type_meta_caps;
$capsBefore = (array) $post_type_meta_caps;
_post_type_meta_capabilities(['read_post' => 'read_zz_pm', 'edit_post' => 'edit_zz_pm', 'delete_post' => 'delete_zz_pm', 'edit_posts' => 'edit_zz_pms', 'publish_posts' => 'x']);
$added = array_diff_key((array) $post_type_meta_caps, $capsBefore);
$say('meta capabilities recorded', [$added, count((array) $post_type_meta_caps) === count($capsBefore) + count($added)]);
foreach (array_keys($added) as $cap) {
    unset($post_type_meta_caps[$cap]);
}

$parent = $post('parent', ['post_type' => 'page']);
$child = $post('child', ['post_type' => 'page', 'post_parent' => $parent]);
$orphan = $post('orphan', ['post_type' => 'page']);
// A post's parent id is cached as post_parent:{id} in the posts group; update_post_parent_caches
// caches the parent posts themselves.
$parentKey = static fn (int $id) => wp_cache_get('post_parent:' . $id, 'posts');
foreach ([$parent, $child, $orphan] as $id) {
    wp_cache_delete('post_parent:' . $id, 'posts');
}
$cached = [array_map($parentKey, [$child, $orphan, $parent])];
_prime_post_parent_id_caches([$child, $orphan, 999999999]);
$cached[] = array_map($parentKey, [$child, $orphan, 999999999]);
$childPost = get_post($child);
wp_cache_delete($parent, 'posts');
update_post_parent_caches([$childPost, get_post($orphan)]);
$cachedParent = wp_cache_get($parent, 'posts');
$cached[] = [is_object($cachedParent), is_object($cachedParent) ? (int) $cachedParent->ID : null];
update_post_parent_caches([]);
$cached[] = wp_get_post_parent_id($child);
$say('post parent caches', $mask($cached));

register_post_type('zz_pm_mime', ['public' => false]);
$post('mime a', ['post_type' => 'zz_pm_mime', 'post_mime_type' => 'image/webp']);
$post('mime b', ['post_type' => 'zz_pm_mime', 'post_mime_type' => 'application/pdf']);
$post('mime c', ['post_type' => 'zz_pm_mime', 'post_mime_type' => 'image/webp']);
$post('mime none', ['post_type' => 'zz_pm_mime']);
$mimes = get_available_post_mime_types('zz_pm_mime');
sort($mimes);
$say('mime types in use', [$mimes, get_available_post_mime_types('zz_pm_nothing')]);

$enclosed = $post('enclosed', []);
add_post_meta($enclosed, 'enclosure', "https://zz.example/a.mp3\n12345\naudio/mpeg");
add_post_meta($enclosed, 'enclosure', "https://zz.example/b.mp4\n99\nvideo/mp4\n");
add_post_meta($enclosed, 'enclosure', 'not a line list');
add_post_meta($enclosed, 'enclosure', '');
$encFilter = static fn ($list, $id) => array_merge((array) $list, ['https://zz.example/extra']);
$say('enclosures', [get_enclosed($enclosed), get_enclosed($older), (static function () use ($encFilter, $enclosed) {
    add_filter('get_enclosed', $encFilter, 10, 2);
    $out = get_enclosed($enclosed);
    remove_filter('get_enclosed', $encFilter, 10);
    return $out;
})()]);

$say('private posts SQL', [
    get_private_posts_cap_sql('post'),
    get_private_posts_cap_sql('page'),
    (static function () {
        wp_set_current_user(1);
        $out = [get_private_posts_cap_sql('post'), get_private_posts_cap_sql(['post', 'page'])];
        wp_set_current_user(0);
        return $out;
    })(),
]);
$hidden = $post('hidden', ['post_type' => 'zz_pm_hidden']);
$embed = static fn ($is, $p) => $p->post_type === 'page' ? false : $is;
$say('embeddable', [
    is_post_embeddable($older), is_post_embeddable($parent), is_post_embeddable($hidden), is_post_embeddable(999999999),
    (static function () use ($embed, $older, $parent) {
        add_filter('is_post_embeddable', $embed, 10, 2);
        $out = [is_post_embeddable($older), is_post_embeddable($parent)];
        remove_filter('is_post_embeddable', $embed, 10);
        return $out;
    })(),
]);

$trashed = $post('trash me', ['post_name' => 'zz-pm-slug']);
$say('trashed suffix for a post', $mask([wp_add_trashed_suffix_to_post_name_for_post(get_post($trashed)), get_post_field('post_name', $trashed, 'raw'), wp_add_trashed_suffix_to_post_name_for_post(get_post($trashed)), get_post_meta($trashed, '_wp_desired_post_slug')]));
$wpdb->update($wpdb->posts, ['post_status' => 'trash', 'post_name' => 'zz-pm-reused'], ['ID' => $trashed]);
clean_post_cache($trashed);
$fresh = $post('fresh', ['post_name' => 'zz-pm-other']);
$say('trashed suffix for trashed posts', $mask([
    wp_add_trashed_suffix_to_post_name_for_trashed_posts('zz-pm-reused', $fresh),
    get_post_field('post_name', $trashed, 'raw'),
    wp_add_trashed_suffix_to_post_name_for_trashed_posts('zz-pm-nothing'),
    wp_add_trashed_suffix_to_post_name_for_trashed_posts('zz-pm-reused__trashed', $trashed),
]));

$oldDraft = $post('old auto draft', ['post_status' => 'auto-draft']);
$newDraft = $post('new auto draft', ['post_status' => 'auto-draft']);
$wpdb->update($wpdb->posts, ['post_date' => gmdate('Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS)], ['ID' => $oldDraft]);
clean_post_cache($oldDraft);
wp_delete_auto_drafts();
$say('old auto-drafts removed', [get_post($oldDraft) === null, get_post($newDraft) instanceof WP_Post]);

$term = wp_insert_term('zz pm term', 'category');
$made['terms'][] = $termId = (int) $term['term_id'];
add_term_meta($termId, 'zz_pm_meta', 'meta value');
$termed = $post('termed', ['post_category' => [$termId]]);
$say('term meta queued for lazy loading', [wp_queue_posts_for_term_meta_lazyload([get_post($termed)]), get_term_meta($termId, 'zz_pm_meta', true), wp_queue_posts_for_term_meta_lazyload([])]);

unregister_post_type('zz_pm_type');
unregister_post_type('zz_pm_hidden');
unregister_post_type('zz_pm_mime');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
