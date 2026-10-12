<?php
/**
 * A taxonomy's default term (probe taxonomy-default-term): registering a
 * taxonomy with default_term creates the term and keeps its id in
 * default_term_{taxonomy}, heard through the term hooks; registered again,
 * with the option gone, with the term gone, and given as a bare name; and a
 * post saved without terms of the taxonomy, given the default term or not.
 * The posts are saved by user 1 (who may assign terms), and once by nobody.
 * The probe's posts, terms and options are removed and its taxonomies
 * unregistered at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$taxonomies = ['zz_dt_tax', 'zz_dt_plain', 'zz_dt_none', 'zz_dt_flat', 'zz_dt_name', 'zz_dt_slug'];
$posts = [];
$clear = static function () use ($taxonomies, $wpdb, &$posts): void {
    foreach ($posts as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($taxonomies as $taxonomy) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy));
        foreach ($ids as $id) {
            $id = is_numeric($id) ? (int) $id : 0;
            if ($id > 1) {
                $wpdb->delete($wpdb->term_relationships, ['term_taxonomy_id' => (int) $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", $id, $taxonomy))]);
                $wpdb->delete($wpdb->term_taxonomy, ['term_id' => $id, 'taxonomy' => $taxonomy]);
                $wpdb->delete($wpdb->termmeta, ['term_id' => $id]);
                $wpdb->delete($wpdb->terms, ['term_id' => $id]);
            }
        }
        delete_option("default_term_{$taxonomy}");
        delete_option("{$taxonomy}_children");
        if (taxonomy_exists($taxonomy)) {
            unregister_taxonomy($taxonomy);
        }
    }
    wp_cache_flush();
};
register_shutdown_function($clear);
$clear();

$heard = [];
foreach (['create_term' => 3, 'created_term' => 3, 'saved_term' => 4, 'edited_term' => 3, 'add_option' => 2, 'added_option' => 2, 'updated_option' => 3] as $hook => $count) {
    add_action($hook, static function (...$args) use ($hook, &$heard) {
        $name = (string) ($args[0] ?? '');
        if (str_contains($hook, 'option')) {
            if (str_starts_with($name, 'default_term_zz_dt')) {
                $heard[] = [$hook, $name];
            }
            return;
        }
        if (str_starts_with((string) ($args[2] ?? ''), 'zz_dt')) {
            $heard[] = [$hook, $args[2]];
        }
    }, 10, $count);
}
add_action('registered_taxonomy', static function ($taxonomy) use (&$heard) {
    if (str_starts_with((string) $taxonomy, 'zz_dt')) {
        $heard[] = ['registered_taxonomy', $taxonomy];
    }
});
// The term as a row, and whether the option names it.
$state = static function (string $taxonomy) use ($wpdb): array {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT t.term_id, t.name, t.slug, tt.description, tt.parent, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id", $taxonomy), ARRAY_A);
    $option = get_option("default_term_{$taxonomy}", 'unset');
    $named = null;
    foreach ($rows as $index => $row) {
        if ((string) $row['term_id'] === (string) $option) {
            $named = $index;
        }
        unset($rows[$index]['term_id']);
    }
    return ['terms' => $rows, 'option names term' => $option === 'unset' ? 'unset' : $named, 'option type' => gettype($option)];
};
$register = static function (string $taxonomy, array $args, array $types = ['post']) use (&$heard, $state): array {
    $heard = [];
    $returned = register_taxonomy($taxonomy, $types, $args);
    return ['returned' => is_wp_error($returned) ? $returned->get_error_code() : get_class($returned), 'default_term' => get_taxonomy($taxonomy)->default_term, 'heard' => $heard, 'state' => $state($taxonomy)];
};

$args = ['hierarchical' => true, 'default_term' => ['name' => 'Zz Default', 'slug' => 'zz-default', 'description' => 'The default.']];
$say('registered with a default term', $register('zz_dt_tax', $args));
$say('registered again', $register('zz_dt_tax', $args));
delete_option('default_term_zz_dt_tax');
$say('registered again, the option gone', $register('zz_dt_tax', $args));
$id = (int) get_option('default_term_zz_dt_tax');
$deleted = $id > 1 ? wp_delete_term($id, 'zz_dt_tax') : 'no id';
$say('the default term deleted', ['returned' => is_wp_error($deleted) ? $deleted->get_error_code() : $deleted, 'state' => $state('zz_dt_tax')]);
// Deleted once the option lets go, then named again: the option names a term that is gone.
delete_option('default_term_zz_dt_tax');
if ($id > 1) {
    wp_delete_term($id, 'zz_dt_tax');
}
update_option('default_term_zz_dt_tax', $id);
$say('registered again, the option naming a term that is gone', $register('zz_dt_tax', $args));
$say('a default term given as a name', $register('zz_dt_plain', ['default_term' => 'Zz Plain Default']));
$say('a flat taxonomy', $register('zz_dt_flat', ['hierarchical' => false, 'default_term' => ['name' => 'Zz Flat']]));
$say('no default term', $register('zz_dt_none', []));
// A term already there: one with the default's name but another slug, one with its slug but another name.
register_taxonomy('zz_dt_name', ['post'], []);
wp_insert_term('Zz Twin', 'zz_dt_name', ['slug' => 'zz-twin']);
$say('a term with the same name, another slug', $register('zz_dt_name', ['default_term' => ['name' => 'Zz Twin', 'slug' => 'zz-twin-default']]));
register_taxonomy('zz_dt_slug', ['post'], []);
wp_insert_term('Another Name', 'zz_dt_slug', ['slug' => 'zz-same-slug']);
$say('a term with the same slug, another name', $register('zz_dt_slug', ['default_term' => ['name' => 'Zz Slug Match', 'slug' => 'zz-same-slug']]));

// Posts saved without terms of the taxonomy, by someone who may assign terms, then by nobody.
$user = get_current_user_id();
wp_set_current_user(1);
$terms = static function (int $id, string $taxonomy) {
    $found = wp_get_object_terms($id, $taxonomy);
    return is_wp_error($found) ? $found->get_error_code() : array_map(static fn ($term) => $term->slug, $found);
};
$saved = [];
$posts[] = $first = wp_insert_post(['post_title' => 'zz dt one', 'post_status' => 'publish', 'post_type' => 'post']);
$saved['published, no terms given'] = is_int($first) && $first > 1 ? ['tax' => $terms($first, 'zz_dt_tax'), 'flat' => $terms($first, 'zz_dt_flat'), 'none' => $terms($first, 'zz_dt_none')] : 'not saved';
$other = wp_insert_term('Zz Other', 'zz_dt_tax');
$otherId = is_array($other) ? $other['term_id'] : 0;
$posts[] = $second = wp_insert_post(['post_title' => 'zz dt two', 'post_status' => 'publish', 'post_type' => 'post', 'tax_input' => ['zz_dt_tax' => [$otherId]]]);
$saved['a term of its own given'] = is_int($second) && $second > 1 ? $terms($second, 'zz_dt_tax') : 'not saved';
$posts[] = $third = wp_insert_post(['post_title' => 'zz dt three', 'post_status' => 'draft', 'post_type' => 'post']);
$saved['a draft'] = is_int($third) && $third > 1 ? $terms($third, 'zz_dt_tax') : 'not saved';
if (is_int($first) && $first > 1) {
    wp_set_object_terms($first, [], 'zz_dt_tax');
    $saved['its terms taken away'] = $terms($first, 'zz_dt_tax');
    wp_update_post(['ID' => $first, 'post_title' => 'zz dt one again']);
    $saved['then saved again'] = $terms($first, 'zz_dt_tax');
}
if (is_int($second) && $second > 1) {
    wp_update_post(['ID' => $second, 'post_title' => 'zz dt two again']);
    $saved['one with its own term, saved again'] = $terms($second, 'zz_dt_tax');
}
$posts[] = $page = wp_insert_post(['post_title' => 'zz dt page', 'post_status' => 'publish', 'post_type' => 'page']);
$saved['a page, not of the taxonomy'] = is_int($page) && $page > 1 ? $terms($page, 'zz_dt_tax') : 'not saved';
wp_set_current_user(0);
$posts[] = $anonymous = wp_insert_post(['post_title' => 'zz dt anonymous', 'post_status' => 'publish', 'post_type' => 'post']);
$saved['saved by nobody'] = is_int($anonymous) && $anonymous > 1 ? $terms($anonymous, 'zz_dt_tax') : 'not saved';
wp_set_current_user($user);
$say('posts saved without its terms', $saved);
$say('the default term counted', array_map(static fn ($row) => [$row['slug'], $row['count']], $state('zz_dt_tax')['terms']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
