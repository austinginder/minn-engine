<?php
/**
 * The facade review's doubts about taxonomy.php, blocks.php and user.php, each measured on both
 * stacks and matched (~/Documents/minn-facade-review/doubts-2026-10-20/e-result.md).
 * Same protocol as api-probe.php; the probe's rows (zzdoubte) are removed after.
 */

global $wpdb;
$log = [];
$home = untrailingslashit(home_url());
$hosts = [preg_replace('#^https?:#', 'https:', $home), preg_replace('#^https?:#', 'http:', $home)];
$mask = static function ($value) use (&$mask, $hosts) {
    if (is_string($value)) {
        return str_replace($hosts, 'SITE', $value);
    }
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['WP_Error', $value->get_error_code()];
    }
    if (is_object($value)) {
        return ['object', get_class($value)];
    }
    return $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
$made = ['terms' => [], 'posts' => [], 'users' => [], 'taxonomies' => [], 'types' => [], 'dirs' => []];
$term = static function (string $name, string $taxonomy, array $args = []) use (&$made): int {
    $saved = wp_insert_term($name, $taxonomy, $args);
    if (is_wp_error($saved)) {
        return 0;
    }
    $made['terms'][] = [$saved['term_id'], $taxonomy];
    return (int) $saved['term_id'];
};
$post = static function (array $args) use (&$made): int {
    $id = wp_insert_post($args + ['post_title' => 'zzdoubte post', 'post_status' => 'publish']);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    $made['posts'][] = $id;
    return (int) $id;
};
$taxonomy = static function (string $name, $types, array $args = []) use (&$made): void {
    register_taxonomy($name, $types, $args);
    $made['taxonomies'][$name] = true;
};
// A template part's area is a term the part brings into being; the ones made here go too.
$areaTerms = static fn (): array => array_map('intval', (array) get_terms(['taxonomy' => 'wp_template_part_area', 'hide_empty' => false, 'fields' => 'ids', 'cache_results' => false]));
$areasBefore = $areaTerms();
// The split bookkeeping is put back as it was: the splits record each split, the flag their end.
$kept = [];
foreach (['finished_splitting_shared_terms', '_split_terms'] as $option) {
    $kept[$option] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option), ARRAY_A);
}
$labels = static fn ($html): array => preg_match_all('/aria-label="([^"]*)"/', (string) $html, $m) ? $m[1] : [];
try {
    // ---- taxonomy.php ----

    // wp_list_categories: the "all" link of a taxonomy only on a type with an archive is that archive.
    register_post_type('zzdoubtet_cpt', ['public' => true, 'has_archive' => 'zzdoubtet-cpts', 'rewrite' => ['slug' => 'zzdoubtet-cpt']]);
    $made['types']['zzdoubtet_cpt'] = true;
    $taxonomy('zzdoubtet_ctax', 'zzdoubtet_cpt', ['public' => true]);
    $ctaxTerm = $term('zzdoubtet ctax one', 'zzdoubtet_ctax');
    $cpt = $post(['post_type' => 'zzdoubtet_cpt']);
    wp_set_object_terms($cpt, [$ctaxTerm], 'zzdoubtet_ctax');
    $all = static fn (string $tax): string => preg_match("/cat-item-all'><a href='([^']*)'/", (string) wp_list_categories(['taxonomy' => $tax, 'show_option_all' => 'All', 'echo' => 0, 'hide_empty' => 0]), $m) ? $m[1] : 'none';
    $say('wp_list_categories: the "all" link of a type-only taxonomy, and of category', [$all('zzdoubtet_ctax'), $all('category')]);

    // A taxonomy's default term: the named term is found or made and stored, even over another stored term.
    $taxonomy('zzdoubtet_dt', 'post');
    $other = $term('zzdoubtet Other', 'zzdoubtet_dt');
    update_option('default_term_zzdoubtet_dt', $other);
    unregister_taxonomy('zzdoubtet_dt');
    $taxonomy('zzdoubtet_dt', 'post', ['default_term' => ['name' => 'zzdoubtet Default', 'slug' => 'zzdoubtet-default']]);
    $default = term_exists('zzdoubtet Default', 'zzdoubtet_dt');
    if (is_array($default)) {
        $made['terms'][] = [(int) $default['term_id'], 'zzdoubtet_dt'];
    }
    $first = [(int) get_option('default_term_zzdoubtet_dt') === (int) ($default['term_id'] ?? -1), (int) wp_count_terms(['taxonomy' => 'zzdoubtet_dt', 'hide_empty' => false])];
    update_option('default_term_zzdoubtet_dt', $other);
    unregister_taxonomy('zzdoubtet_dt');
    $taxonomy('zzdoubtet_dt', 'post', ['default_term' => ['name' => 'zzdoubtet Default', 'slug' => 'zzdoubtet-default']]);
    $say('default_term over another stored term: stored is the default, term count; again', [$first, (int) get_option('default_term_zzdoubtet_dt') === (int) ($default['term_id'] ?? -1), (int) wp_count_terms(['taxonomy' => 'zzdoubtet_dt', 'hide_empty' => false])]);

    // The tag cloud's label: topic_count_text, then single_text/multiple_text, then the callback.
    $tags = [(object) ['term_id' => 901, 'id' => 901, 'name' => 'zzdoubtet a', 'slug' => 'a', 'count' => 1, 'link' => '#a'], (object) ['term_id' => 902, 'id' => 902, 'name' => 'zzdoubtet b', 'slug' => 'b', 'count' => 3, 'link' => '#b']];
    $cloud = static fn (array $args) => $labels(wp_generate_tag_cloud($tags, $args + ['filter' => 0, 'largest' => 22, 'smallest' => 8]));
    $say('tag cloud labels: callback with topic_count_text', $cloud(['topic_count_text_callback' => static fn ($n) => "CB {$n}", 'topic_count_text' => _n_noop('%s TCT', '%s TCTs')]));
    $say('tag cloud labels: single_text and multiple_text', $cloud(['single_text' => 'single', 'multiple_text' => 'multiple']));
    $say('tag cloud labels: single_text with a placeholder', $cloud(['single_text' => '%s one', 'multiple_text' => '%s many']));
    $say('tag cloud labels: single_text with a callback', $cloud(['single_text' => 'single', 'multiple_text' => 'multiple', 'topic_count_text_callback' => static fn ($n) => "CB {$n}"]));
    $say('tag cloud labels: default_topic_count_text as the callback', $cloud(['topic_count_text_callback' => 'default_topic_count_text']));

    // _split_shared_term: a row of another term gives that term; a split of the last shared term finishes splitting.
    $taxonomy('zzdoubtet_s1', 'post');
    $z = $term('zzdoubtet split z', 'zzdoubtet_s1');
    $z2 = $term('zzdoubtet split z2', 'zzdoubtet_s1');
    $z2tt = (int) $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $z2));
    $say('_split_shared_term with another term\'s row: ids, objects', [_split_shared_term($z, $z2tt) === $z2, _split_shared_term(get_term($z), get_term($z2)) === $z2, _split_shared_term($z, (int) $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $z))) === $z]);
    $wpdb->delete($wpdb->options, ['option_name' => 'finished_splitting_shared_terms']);
    wp_cache_delete('finished_splitting_shared_terms', 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    $shared = $term('zzdoubtet shared', 'zzdoubtet_s1');
    $wpdb->insert($wpdb->term_taxonomy, ['term_id' => $shared, 'taxonomy' => 'zzdoubtet_s2', 'description' => '', 'parent' => 0, 'count' => 0]);
    $sharedTt = (int) $wpdb->insert_id;
    // wp_term_is_shared: a row in a taxonomy nobody registered shares the term too.
    $say('wp_term_is_shared with a row in an unregistered taxonomy, and of a plain term', [wp_term_is_shared($shared), wp_term_is_shared($z)]);
    $taxonomy('zzdoubtet_s2', 'post');
    $split = _split_shared_term($shared, $sharedTt);
    if (is_int($split) && $split > 0 && $split !== $shared) {
        $made['terms'][] = [$split, 'zzdoubtet_s2'];
    }
    $say('_split_shared_term of the last shared term: split, finished_splitting_shared_terms', [is_int($split) && $split !== $shared, get_option('finished_splitting_shared_terms')]);

    // _update_term_count_on_transition_post_status: a move between two counted statuses recounts nothing.
    $recounts = 0;
    $counter = static function () use (&$recounts) {
        $recounts++;
    };
    add_action('edited_term_taxonomy', $counter);
    $counted = static fn () => ['publish', 'private'];
    add_filter('update_post_term_count_statuses', $counted);
    $countPost = $post(['post_status' => 'private']);
    wp_set_object_terms($countPost, [$z], 'zzdoubtet_s1');
    $rows = [];
    foreach ([['private', 'publish'], ['publish', 'private'], ['draft', 'private'], ['private', 'draft'], ['draft', 'pending']] as [$new, $old]) {
        $recounts = 0;
        _update_term_count_on_transition_post_status($new, $old, get_post($countPost));
        $rows[] = "{$new}<-{$old}:{$recounts}";
    }
    remove_filter('update_post_term_count_statuses', $counted);
    remove_action('edited_term_taxonomy', $counter);
    $say('_update_term_count_on_transition_post_status, publish and private counted', $rows);

    // _wp_reset_invalid_menu_item_parent: only an array is looked at.
    $reset = [];
    foreach ([(object) ['ID' => 3, 'menu_item_parent' => 3], 'abc', 5, null, ['ID' => 3, 'menu_item_parent' => '3']] as $given) {
        try {
            $reset[] = $mask(_wp_reset_invalid_menu_item_parent($given));
        } catch (Throwable $e) {
            $reset[] = 'threw';
        }
    }
    $say('_wp_reset_invalid_menu_item_parent of an object, a string, a number, null, an array', $reset);

    // clean_object_term_cache: each taxonomy's relationships go, post meta stays, terms' last_changed moves.
    $cachePost = $post([]);
    $probeCache = static function () use ($cachePost): array {
        return [wp_cache_get($cachePost, 'zzdoubtet_s1_relationships'), wp_cache_get($cachePost, 'category_relationships'), wp_cache_get($cachePost, 'post_meta')];
    };
    $prime = static function () use ($cachePost): void {
        wp_cache_set($cachePost, [7], 'zzdoubtet_s1_relationships');
        wp_cache_set($cachePost, [8], 'category_relationships');
        wp_cache_set($cachePost, ['zz' => ['1']], 'post_meta');
        wp_cache_set('last_changed', 'zzdoubtet-lc', 'terms');
    };
    $fired = 0;
    $onClean = static function () use (&$fired) {
        $fired++;
    };
    add_action('clean_object_term_cache', $onClean);
    $prime();
    clean_object_term_cache([$cachePost], 'post');
    $after = [$probeCache(), wp_cache_get('last_changed', 'terms') !== 'zzdoubtet-lc', $fired];
    $prime();
    $fired = 0;
    wp_suspend_cache_invalidation(true);
    clean_object_term_cache([$cachePost], 'post');
    wp_suspend_cache_invalidation(false);
    remove_action('clean_object_term_cache', $onClean);
    $say('clean_object_term_cache: caches, last_changed moved, fired; and with invalidation suspended', [$after, [$probeCache(), wp_cache_get('last_changed', 'terms') !== 'zzdoubtet-lc', $fired]]);
    wp_cache_delete($cachePost, 'zzdoubtet_s1_relationships');
    wp_cache_delete($cachePost, 'category_relationships');
    wp_cache_delete($cachePost, 'post_meta');
    wp_cache_delete('last_changed', 'terms');

    // get_category as an array carries the old category keys.
    $say('get_category as ARRAY_A: keys', array_keys((array) get_category((int) get_option('default_category'), ARRAY_A)));

    // get_object_taxonomies of an attachment: its file's types too.
    $taxonomy('zzdoubtet_cnt', 'attachment');
    $taxonomy('zzdoubtet_img', 'attachment:image');
    $attachment = $post(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_title' => 'zzdoubtet attachment']);
    $say('get_object_taxonomies of an attachment: names, objects', [get_object_taxonomies(get_post($attachment)), array_keys(get_object_taxonomies(get_post($attachment), 'objects'))]);

    // get_tag_link of a term in another taxonomy is still its link.
    $say('get_tag_link of the default category and a custom term', [get_tag_link((int) get_option('default_category')), get_tag_link($z) !== '', get_tag_link(0), get_tag_link(999999999)]);

    // get_tags: an invalid taxonomy is an error through the filter; nothing found skips it.
    $heard = [];
    $onTags = static function ($tags) use (&$heard) {
        $heard[] = is_wp_error($tags) ? 'error' : count($tags);
        return $tags;
    };
    add_filter('get_tags', $onTags);
    $say('get_tags: an invalid taxonomy, nothing found, what the filter heard', [get_tags(['taxonomy' => 'zzdoubtet_nope']), get_tags(['taxonomy' => 'zzdoubtet_s1', 'name__like' => 'zzdoubtet-none']), $heard]);
    remove_filter('get_tags', $onTags);

    // get_taxonomy_labels: the old helps and no_tagcloud, and a filter's partial labels merged over the defaults.
    $say('get_taxonomy_labels with helps and no_tagcloud: flat, hierarchical', array_map(static function (bool $tree) {
        $labels = get_taxonomy_labels((object) ['name' => 'zzdoubtet_l1', 'hierarchical' => $tree, 'labels' => [], 'helps' => 'HELPS', 'no_tagcloud' => 'NOTC']);
        return [$labels->separate_items_with_commas ?? null, $labels->not_found ?? null, $labels->no_terms ?? null];
    }, [false, true]));
    $taxonomy('zzdoubtet_l2', 'post', ['helps' => 'HELPS2', 'no_tagcloud' => 'NOTC2']);
    $say('register_taxonomy with helps and no_tagcloud', [get_taxonomy('zzdoubtet_l2')->labels->separate_items_with_commas, get_taxonomy('zzdoubtet_l2')->labels->not_found]);
    $partial = static fn () => (object) ['name' => 'ZZ Labs3'];
    add_filter('taxonomy_labels_zzdoubtet_l3', $partial);
    $l3 = (array) get_taxonomy_labels((object) ['name' => 'zzdoubtet_l3', 'hierarchical' => false, 'labels' => []]);
    remove_filter('taxonomy_labels_zzdoubtet_l3', $partial);
    $say('get_taxonomy_labels: a filter giving the name only', [count($l3), $l3['name'] ?? null, $l3['singular_name'] ?? null, $l3['add_new_item'] ?? null, $l3['menu_name'] ?? null, $l3['all_items'] ?? null]);

    // get_term_parents_list: "false" as a string turns links and the term itself off.
    $taxonomy('zzdoubtet_h', 'post', ['hierarchical' => true]);
    $hp = $term('zzdoubtet Parent', 'zzdoubtet_h');
    $hc = $term('zzdoubtet Child', 'zzdoubtet_h', ['parent' => $hp]);
    $hg = $term('zzdoubtet Grand', 'zzdoubtet_h', ['parent' => $hc]);
    $say('get_term_parents_list with link and inclusive as "false", false, "0", "no"', array_map(static fn ($flag) => get_term_parents_list($hg, 'zzdoubtet_h', ['link' => $flag, 'inclusive' => $flag]), ['false', false, '0', 'no']));

    // get_the_category_by_ID: any term's name; 0 and a filtered error are errors.
    $tag = $term('zzdoubtet tag', 'post_tag');
    $byId = [get_the_category_by_ID($tag), get_the_category_by_ID($hp), get_the_category_by_ID(0), get_the_category_by_ID(999999999)];
    $failing = static fn () => new WP_Error('zzdoubtet_err', 'x');
    add_filter('get_term', $failing);
    $byId[] = get_the_category_by_ID($tag);
    remove_filter('get_term', $failing);
    $say('get_the_category_by_ID of a tag, a custom term, 0, a missing id, a filtered error', $byId);

    // get_the_category_list: a post without categories, and plain permalinks.
    $bare = $post([]);
    wp_delete_object_term_relationships($bare, 'category');
    clean_object_term_cache([$bare], 'post');
    $heard = [];
    $onCategory = static function ($list, $sep, $parents) use (&$heard) {
        $heard[] = [$list, $sep, $parents];
        return $list;
    };
    add_filter('the_category', $onCategory, 10, 3);
    $none = get_the_category_list(', ', '', $bare);
    remove_filter('the_category', $onCategory, 10);
    $say('get_the_category_list with no categories, and what the_category heard', [$none, $heard]);
    $plain = static fn () => '';
    add_filter('pre_option_permalink_structure', $plain);
    $plainList = get_the_category_list(', ', '', $countPost);
    $plainLink = get_category_link((int) get_option('default_category'));
    remove_filter('pre_option_permalink_structure', $plain);
    $say('get_the_category_list and get_category_link under plain permalinks', [preg_replace('/cat=\d+/', 'cat=ID', $plainList), preg_replace('/cat=\d+/', 'cat=ID', $plainLink)]);
    add_filter('pre_option_permalink_structure', $plain);
    $GLOBALS['wp_rewrite']->init();
    $plainList = get_the_category_list(', ', '', $countPost);
    $plainLink = get_category_link((int) get_option('default_category'));
    $plainRewrite = $GLOBALS['wp_rewrite']->using_permalinks();
    remove_filter('pre_option_permalink_structure', $plain);
    $GLOBALS['wp_rewrite']->init();
    $say('get_the_category_list and get_category_link with the rewrite set up again under plain permalinks', [$plainRewrite, preg_replace('/cat=\d+/', 'cat=ID', $plainList), preg_replace('/cat=\d+/', 'cat=ID', $plainLink), $GLOBALS['wp_rewrite']->using_permalinks()]);

    // get_the_taxonomies: the taxonomy's own templates, an attachment's file types, three terms listed.
    $taxonomy('zzdoubtet_tt', 'post', ['labels' => ['name' => 'ZZT Tags'], 'template' => '%s => %l', 'term_template' => '[%2$s|%1$s]']);
    $taxPost = $post([]);
    $ta = $term('zzdoubtet a', 'zzdoubtet_tt');
    $tb = $term('zzdoubtet b', 'zzdoubtet_tt');
    $tc = $term('zzdoubtet c', 'zzdoubtet_tt');
    wp_set_object_terms($taxPost, [$ta, $tb, $tc], 'zzdoubtet_tt');
    $three = [$term('zzdoubtet x1', 'post_tag'), $term('zzdoubtet x2', 'post_tag'), $term('zzdoubtet x3', 'post_tag')];
    wp_set_object_terms($taxPost, $three, 'post_tag');
    $say('get_the_taxonomies: own template, passed template, three tags', [get_the_taxonomies($taxPost)['zzdoubtet_tt'] ?? null, get_the_taxonomies($taxPost, ['template' => 'T %s: %l', 'term_template' => '<%2$s>'])['zzdoubtet_tt'] ?? null, get_the_taxonomies($taxPost)['post_tag'] ?? null]);
    wp_set_object_terms($taxPost, array_slice($three, 0, 2), 'post_tag');
    $say('get_the_taxonomies: two tags', get_the_taxonomies($taxPost)['post_tag'] ?? null);
    $imgTerm = $term('zzdoubtet img term', 'zzdoubtet_img');
    wp_set_object_terms($attachment, [$imgTerm], 'zzdoubtet_img');
    $say('get_the_taxonomies of an attachment', array_keys(get_the_taxonomies($attachment)));

    // is_object_in_term: a digit string is an id, a name or a slug; no object is an error.
    $year = $term('2024', 'zzdoubtet_s1');
    $slugged = $term('zzdoubtet year', 'zzdoubtet_s1', ['slug' => '2025']);
    $yearPost = $post([]);
    wp_set_object_terms($yearPost, [$year, $slugged], 'zzdoubtet_s1');
    $say('is_object_in_term: "2024" by name, "2025" by slug, int 2024, its id as a string, object 0, "abc"', [is_object_in_term($yearPost, 'zzdoubtet_s1', '2024'), is_object_in_term($yearPost, 'zzdoubtet_s1', '2025'), is_object_in_term($yearPost, 'zzdoubtet_s1', 2024), is_object_in_term($yearPost, 'zzdoubtet_s1', (string) $year), is_object_in_term(0, 'zzdoubtet_s1'), is_object_in_term('abc', 'zzdoubtet_s1')]);

    // term_description: the field's display filters.
    $described = $term('zzdoubtet described', 'zzdoubtet_s1', ['description' => 'zz desc']);
    $heard = [];
    $onDesc = static function ($value, ...$rest) use (&$heard) {
        $heard[] = ['term_description', $value, count($rest), $rest[1] ?? null, $rest[2] ?? null];
        return $value;
    };
    $onTaxDesc = static function ($value, ...$rest) use (&$heard) {
        $heard[] = ['zzdoubtet_s1_description', $value, count($rest), $rest[1] ?? null];
        return $value . ' [tax]';
    };
    add_filter('term_description', $onDesc, 10, 4);
    add_filter('zzdoubtet_s1_description', $onTaxDesc, 10, 3);
    $out = [term_description($described), term_description(get_term($described))];
    remove_filter('term_description', $onDesc, 10);
    remove_filter('zzdoubtet_s1_description', $onTaxDesc, 10);
    $say('term_description: by id and object, and the filters heard', [$out, $heard]);

    // the_tags: through get_the_tag_list, so the_tags and term_links-post_tag apply.
    $GLOBALS['post'] = get_post($taxPost);
    $onTheTags = static fn ($list) => "[the_tags:{$list}]";
    $onLinks = static fn ($links) => [...$links, 'EXTRA'];
    add_filter('the_tags', $onTheTags);
    add_filter('term_links-post_tag', $onLinks);
    ob_start();
    the_tags();
    the_tags('B:', '|', ':A');
    $printed = ob_get_clean();
    remove_filter('the_tags', $onTheTags);
    remove_filter('term_links-post_tag', $onLinks);
    unset($GLOBALS['post']);
    $say('the_tags with the_tags and term_links-post_tag filters', preg_replace('#href="[^"]*"#', 'href', $printed));

    // update_object_term_cache: each taxonomy's relationships group, ids; a cached object keeps its entry.
    $taxonomy('zzdoubtet_tpl', 'post');
    $tplTerms = [$term('zzdoubtet tpl 1', 'zzdoubtet_tpl'), $term('zzdoubtet tpl 2', 'zzdoubtet_tpl'), $term('zzdoubtet tpl 3', 'zzdoubtet_tpl')];
    $postA = $post([]);
    $postB = $post([]);
    wp_set_object_terms($postA, [$z], 'zzdoubtet_s1');
    wp_set_object_terms($postA, $tplTerms, 'zzdoubtet_tpl');
    foreach (['zzdoubtet_s1', 'zzdoubtet_tpl'] as $group) {
        wp_cache_delete($postA, "{$group}_relationships");
        wp_cache_delete($postB, "{$group}_relationships");
    }
    wp_cache_set($postB, ['zzdoubtet-sentinel'], 'zzdoubtet_s1_relationships');
    update_object_term_cache([$postA, $postB], 'post');
    $ids = static fn ($cached) => is_array($cached) ? array_map(static fn ($v) => is_numeric($v) ? 'id' : $v, $cached) : $cached;
    $say('update_object_term_cache: A s1, A tpl, B s1, B tpl', [$ids(wp_cache_get($postA, 'zzdoubtet_s1_relationships')), $ids(wp_cache_get($postA, 'zzdoubtet_tpl_relationships')), wp_cache_get($postB, 'zzdoubtet_s1_relationships'), wp_cache_get($postB, 'zzdoubtet_tpl_relationships')]);
    $readB = get_object_term_cache($postB, 'zzdoubtet_s1');
    $readA = get_object_term_cache($postA, 'zzdoubtet_tpl');
    $say('get_object_term_cache after: B s1, A tpl names', [$mask($readB), is_array($readA) ? array_map(static fn ($t) => $t->name ?? null, $readA) : $readA]);
    wp_set_object_terms($postA, [$tplTerms[0]], 'zzdoubtet_tpl');
    $readA = get_object_term_cache($postA, 'zzdoubtet_tpl');
    $say('get_object_term_cache after the terms change', is_array($readA) ? array_map(static fn ($t) => $t->name ?? null, $readA) : $readA);
    foreach ([$postA, $postB] as $id) {
        foreach (['zzdoubtet_s1', 'zzdoubtet_tpl'] as $group) {
            wp_cache_delete($id, "{$group}_relationships");
        }
    }

    // wp_check_term_hierarchy_for_loops: a loop above the new parent is broken.
    $l1 = $term('zzdoubtet L1', 'zzdoubtet_h');
    $l2 = $term('zzdoubtet L2', 'zzdoubtet_h');
    $l3 = $term('zzdoubtet L3', 'zzdoubtet_h');
    $fresh = $term('zzdoubtet New', 'zzdoubtet_h');
    foreach ([[$l1, $l2], [$l2, $l3], [$l3, $l2]] as [$child, $parent]) {
        $wpdb->update($wpdb->term_taxonomy, ['parent' => $parent], ['term_id' => $child, 'taxonomy' => 'zzdoubtet_h']);
    }
    clean_term_cache([$l1, $l2, $l3], 'zzdoubtet_h');
    $checked = wp_check_term_hierarchy_for_loops($l1, $fresh, 'zzdoubtet_h');
    $parentOf = static fn (int $id) => (int) $wpdb->get_var($wpdb->prepare("SELECT parent FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'zzdoubtet_h'", $id));
    $say('wp_check_term_hierarchy_for_loops over a loop above: result, parents of L1, L2, L3 (as names)', [$checked === $l1, array_map(static fn ($id) => [0 => 0, $l1 => 'L1', $l2 => 'L2', $l3 => 'L3'][$parentOf($id)] ?? 'other', [$l1, $l2, $l3]), wp_check_term_hierarchy_for_loops($fresh, $fresh, 'zzdoubtet_h')]);

    // wp_delete_nav_menu: a menu object whose term is gone is not deleted, and says nothing.
    $deleted = 0;
    $onDelete = static function () use (&$deleted) {
        $deleted++;
    };
    add_action('wp_delete_nav_menu', $onDelete);
    $ghost = static fn () => (object) ['term_id' => 999999999, 'name' => 'zz ghost', 'slug' => 'zz-ghost', 'taxonomy' => 'nav_menu', 'term_taxonomy_id' => 999999999];
    add_filter('wp_get_nav_menu_object', $ghost);
    $ghostDeleted = wp_delete_nav_menu('zz-ghost');
    remove_filter('wp_get_nav_menu_object', $ghost);
    $menu = wp_create_nav_menu('zzdoubtet menu');
    $realDeleted = is_int($menu) ? wp_delete_nav_menu($menu) : 'not made';
    remove_action('wp_delete_nav_menu', $onDelete);
    $say('wp_delete_nav_menu of a ghost menu, and of a real one, and the action count', [$ghostDeleted, $realDeleted, $deleted, wp_delete_nav_menu('zzdoubtet-none')]);

    // wp_dropdown_categories: the viewed category selected; counts padded when hierarchical.
    $dp = $term('zzdoubtet dp', 'category');
    $dc = $term('zzdoubtet dc', 'category', ['parent' => $dp]);
    $dg = $term('zzdoubtet dg', 'category', ['parent' => $dc]);
    $dropPost = $post([]);
    wp_set_post_categories($dropPost, [$dg]);
    $savedQuery = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query(['cat' => $dc]);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $html = wp_dropdown_categories(['echo' => 0, 'hide_empty' => 0, 'include' => [$dp, $dc, $dg]]);
    $GLOBALS['wp_query'] = $savedQuery;
    $GLOBALS['wp_the_query'] = $savedQuery;
    $say('wp_dropdown_categories on a category archive: the selected option', preg_match("/<option class=\"level-0\" value=\"(\d+)\" selected=\"selected\"/", $html, $m) ? ((int) $m[1] === $dc ? 'viewed' : 'other') : 'none');
    $html = wp_dropdown_categories(['echo' => 0, 'hide_empty' => 0, 'hierarchical' => 1, 'show_count' => 1, 'include' => [$dp, $dc, $dg]]);
    $say('wp_dropdown_categories hierarchical with counts', preg_match_all('/zzdoubtet (d\w)&nbsp;&nbsp;\((\d+)\)/', $html, $m) ? array_map(null, $m[1], $m[2]) : $html);

    // wp_generate_tag_cloud: order RAND shuffles, orderby random sorts by name, no tags skip the filter.
    $five = [];
    foreach (['e', 'c', 'a', 'd', 'b'] as $i => $letter) {
        $five[] = (object) ['term_id' => 910 + $i, 'id' => 910 + $i, 'name' => "zzdoubtet {$letter}", 'slug' => $letter, 'count' => $i + 1, 'link' => "#{$letter}"];
    }
    $orders = static function (array $args) use ($five) {
        $seen = [];
        for ($i = 0; $i < 12; $i++) {
            preg_match_all('/>zzdoubtet (\w)</', wp_generate_tag_cloud($five, $args + ['filter' => 0]), $m);
            $seen[implode('', $m[1])] = true;
        }
        return count($seen) > 1 ? 'shuffled' : array_keys($seen);
    };
    $say('wp_generate_tag_cloud order RAND, orderby random, orderby count, orderby zz', [$orders(['order' => 'RAND']), $orders(['orderby' => 'random']), $orders(['orderby' => 'count']), $orders(['orderby' => 'zz'])]);
    $filtered = 0;
    $onCloud = static function ($out) use (&$filtered) {
        $filtered++;
        return $out;
    };
    add_filter('wp_generate_tag_cloud', $onCloud);
    $say('wp_generate_tag_cloud of no tags: flat, array, filter runs', [wp_generate_tag_cloud([]), wp_generate_tag_cloud([], ['format' => 'array']), $filtered]);
    remove_filter('wp_generate_tag_cloud', $onCloud);

    // wp_nav_menu_remove_menu_item_has_children_class: a negative depth flattens every level.
    $classes = ['menu-item', 'menu-item-has-children'];
    $say('has-children class kept at depths 0, 1, 2, 5 for depth -1; depth 0; depth 2', [array_map(static fn ($d) => in_array('menu-item-has-children', wp_nav_menu_remove_menu_item_has_children_class($classes, null, (object) ['depth' => -1], $d), true), [0, 1, 2, 5]), in_array('menu-item-has-children', wp_nav_menu_remove_menu_item_has_children_class($classes, null, (object) ['depth' => 0], 3), true), array_map(static fn ($d) => in_array('menu-item-has-children', wp_nav_menu_remove_menu_item_has_children_class($classes, null, (object) ['depth' => 2], $d), true), [0, 1])]);

    // wp_tag_cloud: edit links, get_terms arguments, an array echoed.
    $taxonomy('zzdoubtet_cloud', 'post', ['public' => true]);
    $cloudPost = $post([]);
    $cloudTerms = [$term('zzdoubtet alpha', 'zzdoubtet_cloud'), $term('zzdoubtet beta', 'zzdoubtet_cloud'), $term('zzdoubtet gamma', 'zzdoubtet_cloud')];
    wp_set_object_terms($cloudPost, $cloudTerms, 'zzdoubtet_cloud');
    wp_set_current_user(1);
    $edit = wp_tag_cloud(['taxonomy' => 'zzdoubtet_cloud', 'link' => 'edit', 'echo' => false]);
    wp_set_current_user(0);
    $say('wp_tag_cloud edit links', preg_match_all('/href="([^"]*)"/', (string) $edit, $m) ? array_map(static fn ($u) => preg_replace('/tag_ID=\d+/', 'tag_ID=ID', $u), $m[1]) : $edit);
    $names = static fn ($html) => preg_match_all('/>zzdoubtet (\w+)</', (string) $html, $m) ? $m[1] : [];
    $say('wp_tag_cloud with search, slug, name__like', [$names(wp_tag_cloud(['taxonomy' => 'zzdoubtet_cloud', 'echo' => false, 'search' => 'beta'])), $names(wp_tag_cloud(['taxonomy' => 'zzdoubtet_cloud', 'echo' => false, 'slug' => 'zzdoubtet-gamma'])), $names(wp_tag_cloud(['taxonomy' => 'zzdoubtet_cloud', 'echo' => false, 'name__like' => 'alp']))]);
    ob_start();
    $asArray = @wp_tag_cloud(['taxonomy' => 'zzdoubtet_cloud', 'format' => 'array', 'echo' => true]);
    $printed = ob_get_clean();
    $say('wp_tag_cloud format array echoed: printed, returned', [$printed, is_array($asArray) ? count($asArray) : $asArray]);

    // ---- user.php ----
    $user = static function (string $login, string $role, string $pass = '') use (&$made): int {
        $id = wp_insert_user(['user_login' => $login, 'user_pass' => $pass !== '' ? $pass : wp_generate_password(), 'role' => $role, 'user_email' => "{$login}@example.com"]);
        if (is_wp_error($id) || !$id) {
            return 0;
        }
        $made['users'][] = $id;
        return (int) $id;
    };
    $u1 = $user('zzdoubteu_one', 'author', 'zzdoubteu-Pass-1');
    $u2 = $user('zzdoubteu_two', 'author');

    // _wp_get_current_user: the global first; with none, who the request is, the global set again.
    wp_set_current_user($u1);
    $GLOBALS['current_user'] = new WP_User($u2);
    $swapped = wp_get_current_user()->ID === $u2;
    unset($GLOBALS['current_user']);
    $again = wp_get_current_user();
    $say('wp_get_current_user: a swapped global, then none (id, global set)', [$swapped, $again->ID, isset($GLOBALS['current_user']) && $GLOBALS['current_user'] === $again]);
    wp_set_current_user(0);

    // count_many_users_posts: private posts count for their author and readers of private posts.
    $post(['post_author' => $u1, 'post_title' => 'zzdoubteu public']);
    $post(['post_author' => $u1, 'post_status' => 'private', 'post_title' => 'zzdoubteu private 1']);
    $post(['post_author' => $u2, 'post_status' => 'private', 'post_title' => 'zzdoubteu private 2']);
    $counts = [];
    foreach (['nobody' => 0, 'u1' => $u1, 'u2' => $u2, 'admin' => 1] as $who => $id) {
        wp_set_current_user($id);
        $many = count_many_users_posts([$u1, $u2]);
        $public = count_many_users_posts([$u1, $u2], 'post', true);
        $counts[$who] = [(int) $many[$u1], (int) $many[$u2], (int) $public[$u1], (int) $public[$u2]];
    }
    wp_set_current_user(0);
    $say('count_many_users_posts by viewer: all u1, u2, public u1, u2', $counts);

    // count_users: who has no capabilities row, an empty one, or only a role nobody registered.
    $capKey = $wpdb->get_blog_prefix() . 'capabilities';
    $before = ['time' => count_users('time'), 'memory' => count_users('memory')];
    $noRow = $user('zzdoubteu_norow', 'subscriber');
    $emptyCaps = $user('zzdoubteu_empty', 'subscriber');
    $unknownRole = $user('zzdoubteu_unknown', 'subscriber');
    $wpdb->delete($wpdb->usermeta, ['user_id' => $noRow, 'meta_key' => $capKey]);
    $wpdb->update($wpdb->usermeta, ['meta_value' => serialize([])], ['user_id' => $emptyCaps, 'meta_key' => $capKey]);
    $wpdb->update($wpdb->usermeta, ['meta_value' => serialize(['zzdoubteu_role' => true])], ['user_id' => $unknownRole, 'meta_key' => $capKey]);
    foreach ([$noRow, $emptyCaps, $unknownRole] as $id) {
        clean_user_cache($id);
    }
    $delta = [];
    foreach (['time', 'memory'] as $strategy) {
        $now = count_users($strategy);
        $delta[$strategy] = [$now['total_users'] - $before[$strategy]['total_users'], ($now['avail_roles']['none'] ?? 0) - ($before[$strategy]['avail_roles']['none'] ?? 0), ($now['avail_roles']['subscriber'] ?? 0) - ($before[$strategy]['avail_roles']['subscriber'] ?? 0), $now['avail_roles']['zzdoubteu_role'] ?? null];
    }
    $say('count_users deltas (total, none, subscriber, the unknown role): time, memory', $delta);

    // get_role: the roles object's own role, the same object each time.
    $editor = get_role('editor');
    $editor->capabilities['zzdoubteu_cap'] = true;
    $say('get_role is the roles object\'s role', [get_role('editor') === $editor, wp_roles()->get_role('editor') === $editor, isset(get_role('editor')->capabilities['zzdoubteu_cap']), get_role('zzdoubteu_none')]);
    unset($editor->capabilities['zzdoubteu_cap']);

    // retrieve_password: the posted user_login when none is given.
    $mails = [];
    $holdMail = static function ($short, $atts) use (&$mails) {
        $mails[] = is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'];
        return true;
    };
    add_filter('pre_wp_mail', $holdMail, 10, 2);
    $_POST['user_login'] = 'zzdoubteu_nobody';
    $unknown = retrieve_password();
    $_POST['user_login'] = 'zzdoubteu_one';
    $known = retrieve_password();
    unset($_POST['user_login']);
    $say('retrieve_password from the posted user_login: unknown, known, mail to', [$unknown, $known, $mails]);

    // sanitize_user_field: a URL in the edit context.
    $say('sanitize_user_field user_url, edit and display', array_map(static fn ($url) => [sanitize_user_field('user_url', $url, $u1, 'edit'), sanitize_user_field('user_url', $url, $u1, 'display')], ['http://ex.com/a b"<x>', 'x.com/a"b', 'javascript:alert(1)', 'https://ok.example/p?q=1&r=2', "http://ex.com/a'b"]));

    // The mail asking to confirm a new address, and the password hint: through gettext.
    $asked = [];
    $onGettext = static function ($translation, $text) use (&$asked) {
        if (str_starts_with((string) $text, 'Howdy ###USERNAME###') || str_starts_with((string) $text, 'Hint: The password')) {
            $asked[] = substr((string) $text, 0, 20);
        }
        return $translation;
    };
    add_filter('gettext', $onGettext, 10, 2);
    wp_set_current_user($u1);
    $_POST['email'] = 'zzdoubteu_new@example.com';
    $_POST['user_id'] = $u1;
    $mails = [];
    send_confirmation_on_profile_email();
    wp_get_password_hint();
    unset($_POST['email'], $_POST['user_id']);
    wp_set_current_user(0);
    remove_filter('gettext', $onGettext, 10);
    $say('the email change mail and the password hint through gettext; mail to', [$asked, $mails]);
    remove_filter('pre_wp_mail', $holdMail, 10);

    // wp_authenticate_cookie: the secure cookie's name when the sign-in said secure.
    $GLOBALS['auth_secure_cookie'] = true;
    $_COOKIE[SECURE_AUTH_COOKIE] = 'zzdoubteu-garbage';
    $secure = wp_authenticate_cookie(null, '', '');
    unset($_COOKIE[SECURE_AUTH_COOKIE]);
    $_COOKIE[AUTH_COOKIE] = 'zzdoubteu-garbage';
    $plain = wp_authenticate_cookie(null, '', '');
    unset($_COOKIE[AUTH_COOKIE], $GLOBALS['auth_secure_cookie']);
    $say('wp_authenticate_cookie with auth_secure_cookie: a bad secure cookie, a bad plain one', [$mask($secure), $mask($plain)]);

    // wp_authenticate_username_password: an earlier error with empty fields is handed on untouched.
    $say('wp_authenticate_username_password after an earlier error, empty fields', wp_authenticate_username_password(new WP_Error('zz_prev', 'x'), '', '')->get_error_codes());

    // wp_schedule_update_user_counts: nothing while installing.
    $scheduled = [];
    $noEvent = static fn () => false;
    $onSchedule = static function ($pre, $event) use (&$scheduled) {
        $scheduled[] = [$event->hook, $event->schedule];
        return true;
    };
    add_filter('pre_get_scheduled_event', $noEvent);
    add_filter('pre_schedule_event', $onSchedule, 10, 2);
    wp_schedule_update_user_counts();
    wp_installing(true);
    wp_schedule_update_user_counts();
    wp_installing(false);
    remove_filter('pre_get_scheduled_event', $noEvent);
    remove_filter('pre_schedule_event', $onSchedule, 10);
    $say('wp_schedule_update_user_counts, then while installing', $scheduled);

    // wp_signon: the cookie step is hooked on authenticate, and the sign-in ends a pending reset.
    $noCookies = static fn () => false;
    add_filter('send_auth_cookies', $noCookies);
    $hookedBefore = has_filter('authenticate', 'wp_authenticate_cookie');
    $during = null;
    $peek = static function ($u) use (&$during) {
        $during = has_filter('authenticate', 'wp_authenticate_cookie');
        return $u;
    };
    add_filter('authenticate', $peek, 99);
    unset($GLOBALS['auth_secure_cookie']);
    $signed = wp_signon(['user_login' => 'zzdoubteu_one', 'user_password' => 'zzdoubteu-Pass-1'], true);
    remove_filter('authenticate', $peek, 99);
    $say('wp_signon: cookie step before, during, after; signed in; auth_secure_cookie after', [$hookedBefore, $during, has_filter('authenticate', 'wp_authenticate_cookie'), $signed instanceof WP_User, $GLOBALS['auth_secure_cookie'] ?? 'unset']);
    unset($GLOBALS['auth_secure_cookie']);
    remove_filter('authenticate', 'wp_authenticate_cookie', 30);
    remove_filter('send_auth_cookies', $noCookies);
    wp_set_current_user(0);

    // ---- blocks.php ----
    $blockDir = sys_get_temp_dir() . '/zzdoubteb-' . getmypid();
    $made['dirs'][] = $blockDir;
    $write = static function (string $file, string $text) use ($blockDir): void {
        if (!is_dir(dirname("{$blockDir}/{$file}"))) {
            mkdir(dirname("{$blockDir}/{$file}"), 0777, true);
        }
        file_put_contents("{$blockDir}/{$file}", $text);
    };

    // filter_block_core_template_part_attributes: a named kses context is its allowlist; null passes.
    $say('filter_block_core_template_part_attributes by context', [
        filter_block_core_template_part_attributes('header', 'tagName', 'post'),
        filter_block_core_template_part_attributes('div', 'tagName', 'post'),
        filter_block_core_template_part_attributes('script', 'tagName', 'post'),
        filter_block_core_template_part_attributes('header', 'tagName', 'strip'),
        filter_block_core_template_part_attributes('header', 'tagName', 'data'),
        filter_block_core_template_part_attributes('header', 'tagName', ['header' => []]),
        filter_block_core_template_part_attributes(null, 'tagName', 'post'),
        filter_block_core_template_part_attributes('', 'tagName', 'post'),
        filter_block_core_template_part_attributes('script', 'slug', 'post'),
    ]);
    $say('filter_block_kses_value of a template part, and filter_block_content', [filter_block_kses_value(['tagName' => 'script', 'x' => ['tagName' => 'script'], 'y' => 'aside'], 'post', [], ['blockName' => 'core/template-part']), filter_block_kses_value(['tagName' => 'script'], 'post', []), filter_block_content('<!-- wp:template-part {"tagName":"script","slug":"zz"} /-->')]);

    // wp_has_border_feature_support: a feature counts when true or a list; a default only when true.
    $borderType = static fn ($supports) => new WP_Block_Type('zzdoubteb/border', ['supports' => $supports]);
    $say('wp_has_border_feature_support', [
        array_map(static fn ($v) => wp_has_border_feature_support($borderType(['__experimentalBorder' => ['color' => $v]]), 'color'), [1, 'yes', [], true, ['x'], false, 0]),
        array_map(static fn ($v) => wp_has_border_feature_support($borderType(['__experimentalBorder' => $v]), 'color'), [true, 1, 'yes', false]),
        array_map(static fn ($v) => wp_has_border_feature_support($borderType([]), 'color', $v), [false, true, 1, [], 'yes']),
        wp_has_border_feature_support($borderType(['__experimentalBorder' => 1]), 'color', true),
    ]);

    // get_block_wrapper_attributes: from the supports only style, class, id and aria-label; the caller's extras after.
    register_block_type('zzdoubteb/wrap', ['supports' => ['zzdoubteb' => true]]);
    $supportsReg = WP_Block_Supports::get_instance();
    $supportsReg->register('zzdoubteb-support', ['apply' => static fn () => ['style' => 'color:red', 'class' => 'zz-sup', 'data-zz' => 'S', 'title' => 'SupT', 'role' => 'note', 'id' => 'sid', 'aria-label' => 'sal']]);
    WP_Block_Supports::$block_to_render = ['blockName' => 'zzdoubteb/wrap', 'attrs' => []];
    $wrapped = get_block_wrapper_attributes(['data-x' => '1', 'title' => 'ET', 'id' => 'eid']);
    WP_Block_Supports::$block_to_render = null;
    $say('get_block_wrapper_attributes with a support giving other attributes', $wrapped);
    unregister_block_type('zzdoubteb/wrap');

    // wp_global_styles_render_svg_filters: the block editor's screen prints them too.
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    add_filter('deprecated_function_trigger_error', '__return_false');
    $svgLength = static function () {
        ob_start();
        wp_global_styles_render_svg_filters();
        return strlen((string) ob_get_clean()) > 0;
    };
    $svg = [$svgLength()];
    $savedScreen = $GLOBALS['current_screen'] ?? null;
    $GLOBALS['current_screen'] = WP_Screen::get('zzdoubteb-screen');
    $svg[] = $svgLength();
    $GLOBALS['current_screen']->is_block_editor(true);
    $svg[] = $svgLength();
    $GLOBALS['current_screen'] = $savedScreen;
    $say('wp_global_styles_render_svg_filters on the front, an admin screen, the block editor (printed)', $svg);

    // register_block_script_module_id: the block's version without an asset file.
    $write('mod/block.json', json_encode(['apiVersion' => 3, 'name' => 'zzdoubteb/mod', 'version' => '1.2.3', 'viewScriptModule' => 'file:./view.js']));
    $write('mod/view.js', '//');
    $moduleId = register_block_script_module_id(['name' => 'zzdoubteb/mod', 'version' => '1.2.3', 'viewScriptModule' => 'file:./view.js', 'file' => "{$blockDir}/mod/block.json"], 'viewScriptModule');
    $moduleNoVersion = register_block_script_module_id(['name' => 'zzdoubteb/mod2', 'viewScriptModule' => 'file:./view.js', 'file' => "{$blockDir}/mod/block.json"], 'viewScriptModule');
    $versions = array_map(static function ($id) {
        wp_enqueue_script_module($id);
        ob_start();
        wp_script_modules()->print_enqueued_script_modules();
        $tag = (string) ob_get_clean();
        wp_dequeue_script_module($id);
        return preg_match('/view\.js\?ver=([^"\']*)/', $tag, $m) ? $m[1] : (str_contains($tag, 'view.js') ? 'no ver' : 'none');
    }, [$moduleId, $moduleNoVersion]);
    $say('register_block_script_module_id without an asset file: ids, versions', [$moduleId, $moduleNoVersion, $versions]);
    wp_deregister_script_module($moduleId);
    wp_deregister_script_module($moduleNoVersion);

    // register_block_script_handle: the asset file's handle, read before the registered check.
    $write('scr/block.json', '{}');
    $write('scr/index.js', '//');
    $write('scr/index.asset.php', "<?php return ['handle' => 'zzdoubteb-custom-handle', 'dependencies' => [], 'version' => '3'];");
    $scriptMeta = static fn (string $name) => ['name' => $name, 'editorScript' => 'file:./index.js', 'file' => "{$blockDir}/scr/block.json"];
    $first = register_block_script_handle($scriptMeta('zzdoubteb/assets'), 'editorScript');
    wp_register_script('zzdoubteb-pre-editor-script', false, [], 'pre');
    $write('scr/index.asset.php', "<?php return ['handle' => 'zzdoubteb-pre-custom', 'dependencies' => [], 'version' => '3'];");
    $second = register_block_script_handle($scriptMeta('zzdoubteb/pre'), 'editorScript');
    $script = static fn ($handle) => isset(wp_scripts()->registered[$handle]) ? [wp_scripts()->registered[$handle]->ver, basename((string) wp_scripts()->registered[$handle]->src)] : 'none';
    $say('register_block_script_handle naming its own handle; with the generated one taken', [$first, $script($first), $second, $script($second), $script('zzdoubteb-assets-editor-script')]);
    foreach (['zzdoubteb-custom-handle', 'zzdoubteb-pre-custom', 'zzdoubteb-pre-editor-script', 'zzdoubteb-assets-editor-script'] as $handle) {
        wp_deregister_script($handle);
    }

    // register_block_style_handle: its path, and the right-to-left file under an rtl locale.
    $write('sty/block.json', '{}');
    $write('sty/style.css', 'a{}');
    $write('sty/style-rtl.css', 'b{}');
    $styleMeta = static fn (string $name) => ['name' => $name, 'style' => 'file:./style.css', 'file' => "{$blockDir}/sty/block.json"];
    $styleData = static fn ($handle) => [basename((string) wp_styles()->get_data($handle, 'path')), wp_styles()->get_data($handle, 'rtl'), wp_styles()->get_data($handle, 'suffix')];
    $ltr = register_block_style_handle($styleMeta('zzdoubteb/ltr'), 'style');
    $direction = $GLOBALS['wp_locale']->text_direction;
    $GLOBALS['wp_locale']->text_direction = 'rtl';
    $rtl = register_block_style_handle($styleMeta('zzdoubteb/rtl'), 'style');
    $GLOBALS['wp_locale']->text_direction = $direction;
    $say('register_block_style_handle: path, rtl, suffix; ltr then rtl', [$styleData($ltr), $styleData($rtl)]);
    wp_deregister_style($ltr);
    wp_deregister_style($rtl);

    // register_block_type_from_metadata: a name from the arguments, the text domain's strings, the old typography shape.
    $write('noname/block.json', json_encode(['apiVersion' => 3, 'title' => 'ZZ No Name']));
    $write('i18n/block.json', json_encode(['apiVersion' => 3, 'name' => 'zzdoubteb/i18n', 'title' => 'ZZ Title', 'description' => 'ZZ Desc', 'keywords' => ['zzkey'], 'textdomain' => 'zzdoubteb', 'supports' => ['fontSize' => true, 'lineHeight' => true]]));
    $contexts = [];
    $onContext = static function ($translation, $text, $context, $domain) use (&$contexts) {
        if ($domain === 'zzdoubteb') {
            $contexts[] = [$text, $context];
        }
        return $translation;
    };
    add_filter('gettext_with_context', $onContext, 10, 4);
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    $noName = register_block_type_from_metadata("{$blockDir}/noname", ['name' => 'zzdoubteb/noname']);
    $i18n = register_block_type_from_metadata("{$blockDir}/i18n");
    remove_filter('gettext_with_context', $onContext, 10);
    $say('register_block_type_from_metadata: a name from args, the strings asked in context, the typography supports, the migration filter', [$noName instanceof WP_Block_Type ? $noName->name : $noName, $contexts, $i18n instanceof WP_Block_Type ? $i18n->supports : $i18n, has_filter('block_type_metadata', 'wp_migrate_old_typography_shape')]);
    foreach (['zzdoubteb/noname', 'zzdoubteb/i18n'] as $name) {
        if (WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            unregister_block_type($name);
        }
    }

    // _block_bindings_term_data_get_value: a navigation link's own id and type; count and parent as text.
    $bindCat = $term('zzdoubteb Cat', 'category');
    $bindTag = $term('zzdoubteb Tag', 'post_tag');
    $bindPub = $term('zzdoubteb Pub', 'category');
    $bound = static function (string $name, array $attrs, array $context, string $field) {
        $block = new WP_Block(['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], $context);
        return _block_bindings_term_data_get_value(['field' => $field], $block);
    };
    $say('term data binding: navigation link by attributes, submenu by tag, both given, a paragraph, count and parent', [
        $bound('core/navigation-link', ['id' => $bindCat, 'type' => 'category'], [], 'name'),
        $bound('core/navigation-submenu', ['id' => $bindTag, 'type' => 'tag'], [], 'name'),
        $bound('core/navigation-link', ['id' => $bindCat, 'type' => 'category'], ['termId' => $bindPub, 'taxonomy' => 'category'], 'name'),
        $bound('core/paragraph', ['id' => $bindCat, 'type' => 'category'], ['termId' => $bindPub, 'taxonomy' => 'category'], 'name'),
        $bound('core/navigation-link', ['id' => $bindTag, 'type' => 'post_tag'], [], 'slug'),
        $bound('core/paragraph', [], ['termId' => $bindPub, 'taxonomy' => 'category'], 'count'),
        $bound('core/paragraph', [], ['termId' => $bindPub, 'taxonomy' => 'category'], 'parent'),
    ]);

    // _minn_resolve_pattern_blocks: a pattern of two blocks inside a group takes two places in its inner content.
    register_block_pattern('zzdoubteb/two', ['title' => 'ZZ Two', 'content' => '<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>b</p><!-- /wp:paragraph -->']);
    $groupMarkup = '<!-- wp:group --><div class="wp-block-group"><!-- wp:pattern {"slug":"zzdoubteb/two"} /--><!-- wp:paragraph --><p>c</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
    $resolvedGroup = resolve_pattern_blocks(parse_blocks($groupMarkup));
    $say('resolve_pattern_blocks inside a group: inner content, serialized', [$resolvedGroup[0]['innerContent'] ?? null, preg_replace('/\s+/', ' ', serialize_blocks($resolvedGroup))]);
    unregister_block_pattern('zzdoubteb/two');

    // _resolve_template_for_new_post: the main query for an auto-draft looks among auto-drafts.
    $draft = $post(['post_type' => 'page', 'post_status' => 'auto-draft', 'post_title' => 'zzdoubteb draft']);
    $_GET['_wp-find-template'] = 'true';
    $resolveFor = static function (int $user, string $var) use ($draft) {
        wp_set_current_user($user);
        $query = new WP_Query();
        $saved = $GLOBALS['wp_the_query'];
        $GLOBALS['wp_the_query'] = $query;
        $query->parse_query([$var => $draft]);
        _resolve_template_for_new_post($query);
        $GLOBALS['wp_the_query'] = $saved;
        return $query->get('post_status');
    };
    $say('_resolve_template_for_new_post: admin by page_id, admin by p, visitor', [$resolveFor(1, 'page_id'), $resolveFor(1, 'p'), $resolveFor(0, 'page_id')]);
    unset($_GET['_wp-find-template']);
    wp_set_current_user(0);

    // Block hooks: a single-instance hooked block goes in once, and not where one already is; a null context is the global post.
    register_block_type('zzdoubteb/single', ['supports' => ['multiple' => false], 'block_hooks' => ['core/paragraph' => 'after']]);
    $two = '<!-- wp:paragraph --><p>1</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>2</p><!-- /wp:paragraph -->';
    $count = static fn (string $html, string $needle) => substr_count($html, $needle);
    $contexts = [];
    $onTypes = static function ($types, $position, $anchor, $context) use (&$contexts) {
        $contexts[] = is_object($context) ? get_class($context) : gettype($context);
        return $types;
    };
    add_filter('hooked_block_types', $onTypes, 10, 4);
    $GLOBALS['post'] = get_post($draft);
    apply_block_hooks_to_content('<!-- wp:paragraph --><p>1</p><!-- /wp:paragraph -->', null);
    unset($GLOBALS['post']);
    apply_block_hooks_to_content('<!-- wp:paragraph --><p>1</p><!-- /wp:paragraph -->', null);
    remove_filter('hooked_block_types', $onTypes, 10);
    $say('apply_block_hooks_to_content with no context: with a global post, without', array_values(array_unique($contexts)));
    $say('a single-instance hooked block: two anchors, one there already, one nested, marked', [
        $count(apply_block_hooks_to_content($two), 'wp:zzdoubteb/single'),
        $count(apply_block_hooks_to_content($two . '<!-- wp:zzdoubteb/single /-->'), 'wp:zzdoubteb/single'),
        $count(apply_block_hooks_to_content('<!-- wp:group --><div><!-- wp:zzdoubteb/single /--></div><!-- /wp:group -->' . $two), 'wp:zzdoubteb/single'),
        $count(apply_block_hooks_to_content($two, null, 'set_ignored_hooked_blocks_metadata'), 'ignoredHookedBlocks'),
    ]);

    // insert_hooked_blocks_into_rest_response: an empty or missing rendered content stays so.
    $restPost = get_post($post(['post_content' => '<!-- wp:paragraph --><p>r</p><!-- /wp:paragraph -->']));
    $rest = static function (array $content) use ($restPost) {
        $response = insert_hooked_blocks_into_rest_response(new WP_REST_Response(['content' => $content]), $restPost);
        return [array_key_exists('rendered', $response->data['content']) ? ($response->data['content']['rendered'] === 'zz-old' ? 'kept' : ($response->data['content']['rendered'] === '' ? '' : 'rebuilt')) : 'missing', substr_count($response->data['content']['raw'], 'wp:zzdoubteb/single')];
    };
    $say('insert_hooked_blocks_into_rest_response: rendered empty, missing, given', [$rest(['raw' => $restPost->post_content, 'rendered' => '']), $rest(['raw' => $restPost->post_content]), $rest(['raw' => $restPost->post_content, 'rendered' => 'zz-old'])]);

    // update_ignored_hooked_blocks_postmeta: a post not saved yet comes back as it is.
    $unsaved = static fn (string $type) => update_ignored_hooked_blocks_postmeta((object) ['post_type' => $type, 'post_content' => '<!-- wp:paragraph --><p>u</p><!-- /wp:paragraph -->'])->post_content;
    $say('update_ignored_hooked_blocks_postmeta without an id: navigation, post', [$unsaved('wp_navigation'), $unsaved('post')]);

    // inject_ignored_hooked_blocks_metadata_attributes: the deprecated argument, and an existing template with its changes.
    $template = $post(['post_type' => 'wp_template', 'post_name' => 'zzdoubteb-inject', 'post_title' => 'Zzdoubteb Inject', 'post_content' => '<!-- wp:paragraph --><p>t</p><!-- /wp:paragraph -->', 'tax_input' => ['wp_theme' => [get_stylesheet()]]]);
    wp_set_object_terms($template, get_stylesheet(), 'wp_theme');
    $deprecated = [];
    $onDeprecated = static function ($function, $message, $version) use (&$deprecated) {
        $deprecated[] = [$function, $version];
    };
    add_action('deprecated_argument_run', $onDeprecated, 10, 3);
    add_filter('deprecated_argument_trigger_error', '__return_false');
    $seen = [];
    $onSeen = static function ($types, $position, $anchor, $context) use (&$seen) {
        $seen[] = is_object($context) ? [$context->slug ?? null, $context->title ?? null, $context->id ?? null] : null;
        return $types;
    };
    add_filter('hooked_block_types', $onSeen, 10, 4);
    $changes = inject_ignored_hooked_blocks_metadata_attributes((object) ['ID' => $template, 'post_name' => 'zzdoubteb-pending', 'post_title' => 'Pending Title', 'post_content' => '<!-- wp:paragraph --><p>t</p><!-- /wp:paragraph -->'], 'zz');
    remove_filter('hooked_block_types', $onSeen, 10);
    remove_action('deprecated_argument_run', $onDeprecated, 10);
    $say('inject_ignored_hooked_blocks_metadata_attributes: deprecated, the template the hooks heard, the content', [$deprecated, array_values(array_unique($seen, SORT_REGULAR)), $changes->post_content]);
    unregister_block_type('zzdoubteb/single');

    // Block templates: saved rows, a plugin's registration, the theme's files.
    $theme = get_stylesheet();
    $saveTemplate = static function (string $type, string $slug, string $title, string $themeName, array $meta = []) use ($post, &$made) {
        $id = $post(['post_type' => $type, 'post_name' => $slug, 'post_title' => $title, 'post_content' => '<!-- wp:paragraph --><p>' . $slug . '</p><!-- /wp:paragraph -->', 'meta_input' => $meta]);
        wp_set_object_terms($id, $themeName, 'wp_theme');
        if ($type === 'wp_template_part') {
            wp_set_object_terms($id, 'uncategorized', 'wp_template_part_area');
        }
        return $id;
    };
    register_block_template('zzdoubteb//zzdoubteb-plug', ['title' => 'Plug Title', 'description' => 'Plug Desc', 'content' => '<!-- wp:paragraph --><p>plug</p><!-- /wp:paragraph -->', 'post_types' => ['post']]);
    $custom = $saveTemplate('wp_template', 'zzdoubteb-custom', 'Zzdoubteb Custom', $theme);
    $suggestion = $saveTemplate('wp_template', 'page-zzdoubteb', 'Zzdoubteb Suggestion', $theme, ['is_wp_suggestion' => true]);
    // Dated as the injected template is, whatever second the clock reached, so the date order ties and the ids decide (as when the fixture was captured).
    $injected = get_post($template);
    foreach ([$custom, $suggestion] as $pinned) {
        if (is_int($pinned) && $pinned > 0 && $injected instanceof WP_Post) {
            $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_date' => $injected->post_date, 'post_date_gmt' => $injected->post_date_gmt], ['ID' => $pinned]);
            clean_post_cache($pinned);
        }
    }
    $saveTemplate('wp_template_part', 'zzdoubteb-part', 'Zzdoubteb Part', $theme);
    $saveTemplate('wp_template', 'zzdoubteb-other', 'Zzdoubteb Other', 'zzdoubteb-other-theme');
    $made['wp_theme'] = 'zzdoubteb-other-theme';
    $newer = $saveTemplate('wp_template', 'zzdoubteb-dated-new', 'Zzdoubteb Dated New', $theme);
    $older = $saveTemplate('wp_template', 'zzdoubteb-dated-old', 'Zzdoubteb Dated Old', $theme);
    wp_update_post(['ID' => $newer, 'post_date' => '2021-01-01 00:00:00', 'post_date_gmt' => '2021-01-01 00:00:00']);
    wp_update_post(['ID' => $older, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00']);
    $say('get_block_templates: saved rows of two dates', array_values(array_filter(array_map(static fn ($t) => $t->slug, (array) get_block_templates()), static fn ($slug) => str_contains($slug, 'zzdoubteb-dated'))));
    wp_delete_post($newer, true);
    wp_delete_post($older, true);
    $slugs = static fn ($templates) => array_values(array_filter(array_map(static fn ($t) => $t->slug, (array) $templates), static fn ($slug) => str_contains($slug, 'zzdoubteb') || $slug === 'page-no-title'));
    $say('get_block_templates for pages, for posts', [$slugs(get_block_templates(['post_type' => 'page'])), $slugs(get_block_templates(['post_type' => 'post']))]);
    $listed = array_values(array_filter((array) get_block_templates(), static fn ($t) => str_contains($t->slug, 'zzdoubteb')));
    $say('get_block_templates: the site\'s own, in order, custom or not', array_map(static fn ($t) => [$t->slug, $t->is_custom, $t->source], $listed));
    $say('get_block_template of another theme\'s saved template', ($other = get_block_template('zzdoubteb-other-theme//zzdoubteb-other')) ? [$other->slug, $other->theme, $other->source] : $other);
    $fileHooks = [];
    foreach (['pre_get_block_file_template', 'get_block_file_template'] as $hook) {
        add_filter($hook, static function ($value, $id) use ($hook, &$fileHooks) {
            $fileHooks[] = [$hook, explode('//', (string) $id)[1] ?? $id];
            return $value;
        }, 10, 2);
    }
    get_block_template("{$theme}//page-no-title");
    get_block_template("{$theme}//header", 'wp_template_part');
    get_block_template("{$theme}//zzdoubteb-plug");
    $say('get_block_template: the file template filters heard', $fileHooks);
    foreach (['pre_get_block_file_template', 'get_block_file_template'] as $hook) {
        remove_all_filters($hook);
    }

    // A saved template a plugin registered: its plugin, title and description from the registration.
    $plugSaved = $saveTemplate('wp_template', 'zzdoubteb-plug', '', $theme, ['origin' => 'plugin']);
    wp_update_post(['ID' => $plugSaved, 'post_title' => '', 'post_excerpt' => '']);
    $fill = static fn ($t) => $t instanceof WP_Block_Template ? [$t->plugin, $t->title, $t->description, $t->origin] : $t;
    $listedPlug = array_values(array_filter((array) get_block_templates(['slug__in' => ['zzdoubteb-plug']]), static fn ($t) => $t->wp_id !== null));
    $say('a saved plugin template: from the post, fetched, listed', [$fill(_build_block_template_result_from_post(get_post($plugSaved))), $fill(get_block_template("{$theme}//zzdoubteb-plug")), array_map($fill, $listedPlug)]);

    // Hooked blocks in saved templates and parts, listed and fetched.
    register_block_type('zzdoubteb/hk', ['block_hooks' => ['core/paragraph' => 'after']]);
    $marks = static fn ($t) => $t instanceof WP_Block_Template ? substr_count((string) $t->content, 'zzdoubteb/hk') : null;
    $listedHooks = [];
    foreach (['wp_template' => 'zzdoubteb-custom', 'wp_template_part' => 'zzdoubteb-part'] as $type => $slug) {
        $found = array_values(array_filter((array) get_block_templates(['slug__in' => [$slug]], $type), static fn ($t) => $t->wp_id !== null));
        $listedHooks[$slug] = [$marks($found[0] ?? null), $marks(get_block_template("{$theme}//{$slug}", $type))];
    }
    $say('hooked block markers in saved templates: listed, fetched', $listedHooks);
    unregister_block_type('zzdoubteb/hk');
    unregister_block_template('zzdoubteb//zzdoubteb-plug');
} catch (Throwable $e) {
    $say('THREW', get_class($e) . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    wp_set_current_user(0);
    unset($_POST['user_login'], $_POST['email'], $_POST['user_id'], $GLOBALS['auth_secure_cookie'], $_GET['_wp-find-template']);
    if (WP_Block_Templates_Registry::get_instance()->is_registered('zzdoubteb//zzdoubteb-plug')) {
        unregister_block_template('zzdoubteb//zzdoubteb-plug');
    }
    foreach (['zzdoubteb/single', 'zzdoubteb/wrap', 'zzdoubteb/hk'] as $name) {
        if (WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            unregister_block_type($name);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as [$id, $tax]) {
        if (is_int($id) && $id > 0 && taxonomy_exists($tax)) {
            wp_delete_term($id, $tax);
        }
    }
    // Whatever is left of the probe's terms (a split, a row in a taxonomy gone) goes too, both tables.
    $left = $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} WHERE name LIKE 'zzdoubte%'");
    $leftTt = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE 'zzdoubte%'" . ($left ? ' OR term_id IN (' . implode(',', array_map('absint', $left)) . ')' : ''));
    if ($leftTt) {
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode(',', array_map('absint', $leftTt)) . ')');
        $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN (" . implode(',', array_map('absint', $leftTt)) . ')');
    }
    if ($left) {
        $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE term_id IN (" . implode(',', array_map('absint', $left)) . ')');
        $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN (" . implode(',', array_map('absint', $left)) . ')');
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    foreach (array_keys($made['taxonomies']) as $name) {
        if (taxonomy_exists($name)) {
            unregister_taxonomy($name);
        }
        delete_option("{$name}_children");
    }
    foreach (array_keys($made['types']) as $name) {
        unregister_post_type($name);
    }
    foreach ($made['dirs'] as $dir) {
        if (is_dir($dir) && str_contains($dir, 'zzdoubteb-')) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($dir);
        }
    }
    if (!empty($made['wp_theme']) && ($otherTheme = get_term_by('slug', $made['wp_theme'], 'wp_theme'))) {
        wp_delete_term($otherTheme->term_id, 'wp_theme');
    }
    delete_option('default_term_zzdoubtet_dt');
    foreach (array_diff($areaTerms(), $areasBefore) as $area) {
        if (is_int($area) && $area > 1) {
            wp_delete_term($area, 'wp_template_part_area');
        }
    }
    foreach ($kept as $option => $row) {
        $wpdb->delete($wpdb->options, ['option_name' => $option]);
        if (is_array($row)) {
            $wpdb->insert($wpdb->options, ['option_name' => $option, 'option_value' => $row['option_value'], 'autoload' => $row['autoload']]);
        }
        wp_cache_delete($option, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
}

echo json_encode($log);
