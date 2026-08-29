<?php
/**
 * Behaviour probe for the content layer: posts, queries, the writers,
 * terms, post types, taxonomies, authors, query vars. Same protocol as
 * api-probe.php. Everything it creates is deleted on shutdown.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$ids = static fn ($posts) => array_map(static fn ($p) => is_object($p) ? $p->ID : (int) $p, (array) $posts);
$vars = static fn ($o) => is_object($o) ? array_keys(get_object_vars($o)) : gettype($o);
$kind = static fn ($r) => $r instanceof WP_Error ? 'error:' . $r->get_error_code() : (is_object($r) ? get_class($r) : var_export($r, true));
$created = ['posts' => [], 'terms' => []];
// Everything the probe makes is named so a crashed run can be swept on the next start and on shutdown.
$cleanup = static function () use (&$created): void {
    foreach ($created['posts'] as $id) {
        wp_delete_post($id, true);
    }
    foreach (get_posts(['post_type' => ['post', 'page', 'minn_probe'], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 's' => 'Probe:']) as $id) {
        wp_delete_post($id, true);
    }
    foreach (get_posts(['post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'title' => 'hooks']) as $id) {
        wp_delete_post($id, true);
    }
    foreach (get_posts(['post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'title' => 'hooks2']) as $id) {
        wp_delete_post($id, true);
    }
    foreach (get_posts(['post_type' => 'nope', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
        wp_delete_post($id, true);
    }
    foreach ([['post_tag', 'probetag'], ['post_tag', 'custom-slug'], ['post_tag', 'brand-new'], ['post_tag', 'probe-slug'], ['category', 'probe-cat'], ['category', 'probe-cat-2'], ['category', 'probe-renamed']] as [$tax, $slug]) {
        $t = get_term_by('slug', $slug, $tax);
        if ($t) {
            wp_delete_term($t->term_id, $tax);
        }
    }
};
$cleanup();
register_shutdown_function($cleanup);
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($url) => is_string($url) ? str_replace($home, '{home}', $url) : $url;

// Reads.
$p = get_post(1);
$say('get_post class', get_class($p));
$say('get_post props', $vars($p));
$say('get_post ARRAY_A keys', array_keys(get_post(1, ARRAY_A)));
$say('get_post missing', get_post(999999));
$say('get_post types', [gettype($p->ID), gettype($p->post_author), gettype($p->post_parent), gettype($p->menu_order), gettype($p->comment_count)]);
$say('get_post_field', [get_post_field('post_name', 6), get_post_field('post_parent', 6), get_post_field('nope', 6), get_post_field('post_title', 999999)]);
$say('get_the_title', [get_the_title(7), get_the_title(999999), get_the_title(3)]);
$say('get_post_status', [get_post_status(1), get_post_status(3), get_post_status(999999)]);
$say('get_post_type', [get_post_type(1), get_post_type(2), get_post_type(999999)]);
$say('get_post_mime_type', get_post_mime_type(1));
$say('get_post_ancestors', [get_post_ancestors(6), get_post_ancestors(2)]);
$say('get_page_by_path', [get_page_by_path('sample-page/docs')->ID, get_page_by_path('docs'), get_page_by_path('sample-page')->ID, get_page_by_path('hello-world', OBJECT, 'post')->ID, get_page_by_path('nope')]);
$say('get_page_uri', [get_page_uri(6), get_page_uri(2), get_page_uri(1)]);
$say('url_to_postid', [url_to_postid($home . '/sample-page/docs/'), url_to_postid('/hello-world/'), url_to_postid($home . '/?p=5'), url_to_postid($home . '/nope/'), url_to_postid($home . '/category/uncategorized/')]);
$say('get_post_custom_keys', get_post_custom_keys(5));
$say('wp_is_post_revision', [wp_is_post_revision(1), wp_is_post_autosave(1)]);
$say('get_the_date', [get_the_date('Y-m-d', 1), get_the_date('', 1) === date_i18n(get_option('date_format'), strtotime(get_post(1)->post_date)), get_the_time('H:i', 1), get_the_modified_date('Y', 1), get_post_time('U', true, 1) === strtotime(get_post(1)->post_date_gmt . ' UTC'), get_post_time('Y-m-d', false, 5)]);
$say('get_the_excerpt handwritten', get_the_excerpt(5));
$say('get_the_excerpt generated', get_the_excerpt(1));
$say('get_the_content', get_the_content(null, false, 1));
$say('post_password_required', post_password_required(1));
$say('get_edit_post_link anon', get_edit_post_link(1));
$say('get_permalink', [$rel(get_permalink(1)), $rel(get_permalink(6)), $rel(get_permalink(3)), get_permalink(999999)]);
$say('get_the_permalink', $rel(get_the_permalink(5)));
$say('get_adjacent_post', (static function () use ($rel) { $GLOBALS['post'] = get_post(8); $prev = get_previous_post(); $next = get_next_post(); return [$prev ? $prev->ID : $prev, $next ? $next->ID : $next]; })());

// get_posts / get_pages / get_children / wp_count_posts.
$say('get_posts default', $ids(get_posts()));
$say('get_posts args', $ids(get_posts(['numberposts' => 3, 'order' => 'ASC', 'orderby' => 'title'])));
$say('get_posts pages', $ids(get_posts(['post_type' => 'page'])));
$say('get_posts any status', $ids(get_posts(['post_type' => 'page', 'post_status' => 'any', 'orderby' => 'ID', 'order' => 'ASC'])));
$say('get_posts draft', $ids(get_posts(['post_status' => 'draft'])));
$say('get_posts include', $ids(get_posts(['include' => [7, 1, 5]])));
$say('get_posts exclude', $ids(get_posts(['exclude' => [608, 609, 610, 611], 'numberposts' => -1])));
$say('get_posts fields ids', get_posts(['fields' => 'ids', 'numberposts' => 2]));
$say('get_posts category', $ids(get_posts(['category' => 1, 'numberposts' => -1])));
$say('get_posts tag', $ids(get_posts(['tag' => 'engine'])));
$say('get_posts search', $ids(get_posts(['s' => 'Building'])));
$say('get_posts author', $ids(get_posts(['author' => 2])));
$say('get_posts post__in order', $ids(get_posts(['post__in' => [7, 1, 5], 'orderby' => 'post__in'])));
$say('get_posts meta', $ids(get_posts(['meta_key' => '_thumbnail_id', 'numberposts' => -1])));
$say('get_posts multi type', $ids(get_posts(['post_type' => ['post', 'page'], 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC'])));
$say('get_posts name', $ids(get_posts(['name' => 'hello-world'])));
$say('get_posts offset', $ids(get_posts(['offset' => 2, 'numberposts' => 2])));
$say('get_posts rand count', count(get_posts(['orderby' => 'rand', 'numberposts' => 3])));
$say('get_pages default', $ids(get_pages()));
$say('get_pages child_of', $ids(get_pages(['child_of' => 2])));
$say('get_pages parent', $ids(get_pages(['parent' => 2])));
$say('get_pages sort', $ids(get_pages(['sort_column' => 'post_title', 'sort_order' => 'desc'])));
$say('get_children', array_keys(get_children(['post_parent' => 2, 'post_type' => 'page'])));
$say('get_children by id', array_keys(get_children(2)));
$say('wp_count_posts', get_object_vars(wp_count_posts()));
$say('wp_count_posts page', get_object_vars(wp_count_posts('page')));
$say('wp_get_recent_posts', array_column(wp_get_recent_posts(['numberposts' => 2]), 'ID'));

// WP_Query.
$q = new WP_Query(['post_type' => 'post']);
$say('WP_Query counts', [$q->found_posts, $q->post_count, $q->max_num_pages, $ids($q->posts)]);
$say('WP_Query flags', array_intersect_key(get_object_vars($q), array_flip(['is_single', 'is_page', 'is_archive', 'is_home', 'is_search', 'is_404', 'is_singular', 'is_paged', 'is_main_query' => 0, 'is_post_type_archive'])));
$loop = [];
while ($q->have_posts()) {
    $q->the_post();
    $loop[] = [get_the_ID(), in_the_loop(), $q->current_post];
}
wp_reset_postdata();
$say('WP_Query loop', $loop);
$say('WP_Query after loop', [$q->have_posts(), in_the_loop(), get_the_ID()]);
$q2 = new WP_Query(['posts_per_page' => 3, 'paged' => 2]);
$say('WP_Query paged', [$q2->found_posts, $q2->post_count, $q2->max_num_pages, $ids($q2->posts), $q2->query_vars['paged'], $q2->query_vars['posts_per_page']]);
$say('WP_Query query_vars keys', array_keys((new WP_Query())->query_vars));
$say('WP_Query default posts_per_page', (new WP_Query([]))->query_vars['posts_per_page']);
$say('WP_Query get', [$q2->get('paged'), $q2->get('nope'), $q2->get('nope', 'd')]);
$say('WP_Query tax_query', $ids((new WP_Query(['tax_query' => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine']]]]))->posts));
$say('WP_Query meta_query', $ids((new WP_Query(['meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']], 'posts_per_page' => -1]))->posts));
$say('WP_Query search', [$ids((new WP_Query(['s' => 'building open']))->posts), (new WP_Query(['s' => 'zzzznotfound']))->found_posts]);
$say('WP_Query p', $ids((new WP_Query(['p' => 5]))->posts));
$say('WP_Query page_id', [$ids((new WP_Query(['page_id' => 2]))->posts), (new WP_Query(['page_id' => 2]))->is_page]);
$say('WP_Query pagename', $ids((new WP_Query(['pagename' => 'sample-page/docs']))->posts));
$say('WP_Query name', $ids((new WP_Query(['name' => 'hello-world']))->posts));
$say('WP_Query nopaging', (new WP_Query(['nopaging' => true]))->post_count);
$say('WP_Query no_found_rows', (new WP_Query(['no_found_rows' => true, 'posts_per_page' => 2]))->found_posts);
$say('WP_Query fields ids', (new WP_Query(['fields' => 'ids', 'posts_per_page' => 2]))->posts);
$say('WP_Query orderby array', $ids((new WP_Query(['orderby' => ['title' => 'ASC'], 'posts_per_page' => 3]))->posts));
$say('WP_Query orderby modified', $ids((new WP_Query(['orderby' => 'modified', 'order' => 'ASC', 'posts_per_page' => 2]))->posts));
$say('WP_Query post_status array', $ids((new WP_Query(['post_type' => 'page', 'post_status' => ['draft', 'publish'], 'orderby' => 'ID', 'order' => 'ASC']))->posts));
$say('WP_Query author_name', $ids((new WP_Query(['author_name' => 'editor']))->posts));
$say('WP_Query category_name', count((new WP_Query(['category_name' => 'uncategorized', 'posts_per_page' => -1]))->posts));
$say('WP_Query cat exclude', count((new WP_Query(['cat' => -1, 'posts_per_page' => -1]))->posts));
$say('WP_Query date_query', $ids((new WP_Query(['date_query' => [['year' => (int) date('Y', strtotime(get_post(1)->post_date))]], 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC']))->posts));
$say('WP_Query post_parent', $ids((new WP_Query(['post_type' => 'page', 'post_parent' => 2]))->posts));
$say('WP_Query post__not_in', $ids((new WP_Query(['post__not_in' => [608, 609, 610, 611, 11, 9], 'posts_per_page' => -1]))->posts));
$say('WP_Query queried object', [$kind((new WP_Query(['page_id' => 2]))->get_queried_object()), $kind((new WP_Query(['tag' => 'engine']))->get_queried_object()), $kind((new WP_Query(['post_type' => 'post']))->get_queried_object())]);
$say('WP_Query is flags single', array_intersect_key(get_object_vars(new WP_Query(['p' => 1])), array_flip(['is_single', 'is_singular', 'is_home', 'is_archive'])));
$say('WP_Query is flags tag', array_intersect_key(get_object_vars(new WP_Query(['tag' => 'engine'])), array_flip(['is_tag', 'is_archive', 'is_home', 'is_singular'])));
$say('WP_Query query prop', (new WP_Query(['p' => 1]))->query);
$say('query_posts / wp_reset_query', (static function () use ($ids) { $r = $ids(query_posts(['p' => 5])); $id = get_the_ID(); wp_reset_query(); return [$r, $id, get_the_ID()]; })());

// Writers.
$new = wp_insert_post(['post_title' => 'Probe: insert', 'post_content' => 'Body', 'post_status' => 'draft', 'tags_input' => ['engine', 'probetag'], 'post_category' => [1]]);
$created['posts'][] = $new;
$row = get_post($new);
$say('wp_insert_post draft', [is_int($new) && $new > 0, $row->post_name, $row->post_status, $row->post_type, $row->post_date !== '0000-00-00 00:00:00', $row->post_date_gmt, $row->guid === $home . '/?p=' . $new, $row->post_author, $row->comment_status, $row->ping_status]);
$say('insert tags', wp_get_post_terms($new, 'post_tag', ['fields' => 'names']));
$say('insert categories', wp_get_post_categories($new));
$created['terms'][] = [get_term_by('slug', 'probetag', 'post_tag')->term_id, 'post_tag'];
$pub = wp_insert_post(['post_title' => 'Probe: published', 'post_content' => 'Body']);
$created['posts'][] = $pub;
$say('wp_insert_post default status', [get_post($pub)->post_status, wp_get_post_categories($pub), get_post($pub)->post_name]);
$pub2 = wp_insert_post(['post_title' => 'Probe: published', 'post_content' => 'Body', 'post_status' => 'publish']);
$created['posts'][] = $pub2;
$say('wp_insert_post publish', [get_post($pub2)->post_status, get_post($pub2)->post_name, wp_get_post_categories($pub2), get_post($pub2)->post_date_gmt !== '0000-00-00 00:00:00']);
$say('wp_insert_post empty', (static function () use (&$created) { $a = wp_insert_post(['post_title' => '']); $b = wp_insert_post(['post_title' => ''], true); foreach ([$a, $b] as $r) { if (is_int($r) && $r > 0) { $created['posts'][] = $r; } } return [is_int($a) ? ($a > 0 ? 'id' : $a) : get_class($a), is_int($b) ? ($b > 0 ? 'id' : $b) : $b->get_error_code(), is_int($b) && $b > 0 ? [get_post($b)->post_title, get_post($b)->post_status, get_post($b)->post_name] : null]; })());
$say('wp_insert_post bad type', (static function () use (&$created) { $r = wp_insert_post(['post_title' => 'x', 'post_type' => 'nope'], true); if (is_int($r) && $r > 0) { $created['posts'][] = $r; } return is_int($r) ? ($r > 0 ? 'id' : $r) : $r->get_error_code(); })());
$upd = wp_update_post(['ID' => $new, 'post_title' => 'Probe: updated', 'post_content' => 'Body 2']);
$say('wp_update_post', [$upd === $new, get_post($new)->post_title, get_post($new)->post_name, get_post($new)->post_status]);
$say('wp_update_post missing', [wp_update_post(['ID' => 999999, 'post_title' => 'x']), $kind(wp_update_post(['ID' => 999999], true))]);
$say('wp_update_post slash', (static function () use ($new) { wp_update_post(['ID' => $new, 'post_title' => "It's \\\"q\\\""]); return get_post($new)->post_title; })());
$say('wp_publish_post', (static function () use ($new) { wp_publish_post($new); return [get_post($new)->post_status, get_post($new)->post_name]; })());
$say('wp_insert_post revisions', count(wp_get_post_revisions($new)));
$say('wp_trash_post', (static function () use ($new, $kind) { $r = wp_trash_post($new); return [$kind($r), $r->post_status, get_post_meta($new, '_wp_trash_meta_status', true), get_post_meta($new, '_wp_trash_meta_time', true) !== '']; })());
$say('wp_trash_post again', wp_trash_post($new));
$say('wp_untrash_post', (static function () use ($new, $kind) { $r = wp_untrash_post($new); return [$kind($r), get_post($new)->post_status, get_post_meta($new, '_wp_trash_meta_status', true)]; })());
$say('wp_untrash_post not trashed', wp_untrash_post($new));
$say('wp_delete_post', (static function () use ($new, $kind) { $r = wp_delete_post($new, true); return [$kind($r), is_object($r) ? $r->ID === $new : null, get_post($new)]; })());
$say('wp_delete_post missing', [wp_delete_post(999999), wp_delete_post(999999, true)]);
$say('wp_delete_post trash first', (static function () use ($pub, $kind) { $r = wp_delete_post($pub); return [$kind($r), get_post($pub)->post_status]; })());
$say('delete hooks', (static function () use ($pub2) { $seen = []; foreach (['before_delete_post', 'delete_post', 'deleted_post', 'wp_trash_post', 'trashed_post', 'save_post', 'wp_insert_post', 'transition_post_status'] as $h) { add_action($h, static function () use (&$seen, $h) { $seen[] = $h; }); } wp_update_post(['ID' => $pub2, 'post_title' => 'again']); wp_delete_post($pub2, true); return $seen; })());
$say('save_post args', (static function () use (&$created, $kind) { $args = null; add_action('save_post', static function (...$a) use (&$args, $kind) { $args = [$a[0] > 0, $kind($a[1]), $a[2]]; }, 10, 3); $id = wp_insert_post(['post_title' => 'hooks', 'post_content' => 'x', 'post_status' => 'draft']); $created['posts'][] = $id; $first = $args; wp_update_post(['ID' => $id, 'post_title' => 'hooks2']); return [$first, $args]; })());

// Terms.
$say('get_terms category', array_map(static fn ($t) => [$t->term_id, $t->name, $t->slug, $t->count, $t->parent, $t->taxonomy], get_terms(['taxonomy' => 'category', 'hide_empty' => false])));
$say('get_terms tags', array_map(static fn ($t) => [$t->term_id, $t->name, $t->count], get_terms(['taxonomy' => 'post_tag'])));
$say('get_terms fields ids', get_terms(['taxonomy' => 'post_tag', 'fields' => 'ids']));
$say('get_terms names', get_terms(['taxonomy' => 'post_tag', 'fields' => 'names']));
$say('get_terms id=>name', get_terms(['taxonomy' => 'post_tag', 'fields' => 'id=>name']));
$say('get_terms legacy', get_terms('post_tag', ['fields' => 'ids']));
$say('get_terms order', get_terms(['taxonomy' => 'post_tag', 'fields' => 'ids', 'orderby' => 'name', 'order' => 'DESC']));
$say('get_terms number', get_terms(['taxonomy' => 'post_tag', 'fields' => 'ids', 'number' => 1]));
$say('get_terms include', get_terms(['taxonomy' => 'post_tag', 'fields' => 'ids', 'include' => [3]]));
$say('get_terms slug', get_terms(['taxonomy' => 'post_tag', 'fields' => 'ids', 'slug' => 'engine']));
$say('get_terms search', get_terms(['taxonomy' => 'post_tag', 'fields' => 'names', 'search' => 'par']));
$say('get_terms bad taxonomy', $kind(get_terms(['taxonomy' => 'nope'])));
$say('get_terms hide_empty', count(get_terms(['taxonomy' => 'category'])) >= 1);
$t = get_term(1, 'category');
$say('get_term', [get_class($t), get_object_vars($t)]);
$say('get_term types', [gettype($t->term_id), gettype($t->count), gettype($t->parent), gettype($t->term_taxonomy_id)]);
$say('get_term missing', [get_term(999999, 'category'), get_term(1, 'post_tag'), $kind(get_term(2, 'nope')), get_term(0)]);
$say('get_term no taxonomy', get_term(2)->taxonomy);
$say('get_term_by', [get_term_by('slug', 'engine', 'post_tag')->term_id, get_term_by('name', 'Uncategorized', 'category')->term_id, get_term_by('id', 3, 'post_tag')->name, get_term_by('term_taxonomy_id', 1)->name, get_term_by('slug', 'nope', 'post_tag')]);
$say('term_exists', [term_exists('engine', 'post_tag'), term_exists('Engine', 'post_tag'), term_exists(2, 'post_tag'), term_exists('engine'), term_exists('nope'), term_exists('uncategorized', 'category', 0), term_exists('')]);
$say('get_term_link', [$rel(get_term_link(1)), $rel(get_term_link(2, 'post_tag')), $rel(get_term_link('engine', 'post_tag')), $kind(get_term_link(999999)), $rel(get_category_link(1)), $rel(get_tag_link(2))]);
$say('wp_get_object_terms', array_map(static fn ($t) => [$t->term_id, $t->name, $t->taxonomy], wp_get_object_terms(5, 'post_tag')));
$say('wp_get_object_terms multi', array_map(static fn ($t) => $t->taxonomy . ':' . $t->slug, wp_get_object_terms([5, 1], ['category', 'post_tag'])));
$say('wp_get_object_terms fields', [wp_get_object_terms(5, 'post_tag', ['fields' => 'ids']), wp_get_object_terms(5, 'post_tag', ['fields' => 'names']), wp_get_object_terms(5, 'post_tag', ['fields' => 'slugs']), wp_get_object_terms(5, 'post_tag', ['fields' => 'tt_ids'])]);
$say('wp_get_object_terms none', wp_get_object_terms(1, 'post_tag'));
$say('wp_get_post_terms', wp_get_post_terms(5, 'post_tag', ['fields' => 'names']));
$say('get_the_terms', [array_map(static fn ($t) => $t->slug, get_the_terms(5, 'post_tag')), get_the_terms(1, 'post_tag'), $kind(get_the_terms(1, 'nope'))]);
$say('get_the_category', array_map(static fn ($t) => [$t->term_id, $t->cat_ID, $t->cat_name, $t->category_nicename, $t->category_count, $t->category_parent], get_the_category(5)));
$say('get_the_tags', [array_map(static fn ($t) => $t->name, get_the_tags(5)), get_the_tags(1)]);
$say('has_term', [has_term('engine', 'post_tag', 5), has_term('nope', 'post_tag', 5), has_term('', 'post_tag', 5), has_term('', 'post_tag', 1), has_term(2, 'post_tag', 5), has_term(['engine', 'x'], 'post_tag', 5)]);
$say('has_category / has_tag', [has_category('uncategorized', 5), has_category(1, 5), has_tag('engine', 5), has_tag('', 1), has_category('', 1)]);
$ins = wp_insert_term('Probe Cat', 'category', ['parent' => 1, 'description' => 'desc']);
$created['terms'][] = [$ins['term_id'], 'category'];
$say('wp_insert_term', [array_map('gettype', $ins), array_keys($ins), get_term($ins['term_id'])->slug, get_term($ins['term_id'])->parent, get_term($ins['term_id'])->description, get_term($ins['term_id'])->count]);
$say('wp_insert_term dup', [$kind($d = wp_insert_term('Probe Cat', 'category', ['parent' => 1])), $d instanceof WP_Error ? $d->get_error_data() === $ins['term_id'] : null]);
$say('wp_insert_term dup other parent', is_array(wp_insert_term('Probe Cat', 'category')) ? 'created' : wp_insert_term('Probe Cat', 'category')->get_error_code());
$dup = get_term_by('name', 'Probe Cat', 'category');
$say('wp_insert_term slug', [wp_insert_term('Probe Slug', 'post_tag', ['slug' => 'Custom Slug!'])['term_id'] > 0, get_term_by('name', 'Probe Slug', 'post_tag')->slug]);
$created['terms'][] = [get_term_by('name', 'Probe Slug', 'post_tag')->term_id, 'post_tag'];
$say('wp_insert_term empty', $kind(wp_insert_term('', 'post_tag')));
$say('wp_insert_term bad tax', $kind(wp_insert_term('x', 'nope')));
$say('wp_update_term', (static function () use ($ins) { $r = wp_update_term($ins['term_id'], 'category', ['name' => 'Probe Cat Renamed', 'slug' => 'probe-renamed']); $t = get_term($ins['term_id']); return [array_keys($r), $t->name, $t->slug]; })());
$say('wp_update_term missing', $kind(wp_update_term(999999, 'category', ['name' => 'x'])));
$tp = wp_insert_post(['post_title' => 'Probe: terms', 'post_content' => 'x', 'post_status' => 'publish']);
$created['posts'][] = $tp;
$say('wp_set_object_terms', (static function () use ($tp) { $r = wp_set_object_terms($tp, ['engine', 'brand-new'], 'post_tag'); return [array_map('gettype', $r), wp_get_object_terms($tp, 'post_tag', ['fields' => 'slugs']), get_term_by('slug', 'engine', 'post_tag')->count]; })());
$created['terms'][] = [get_term_by('slug', 'brand-new', 'post_tag')->term_id, 'post_tag'];
$say('wp_set_object_terms append', (static function () use ($tp) { wp_set_object_terms($tp, [3], 'post_tag', true); return wp_get_object_terms($tp, 'post_tag', ['fields' => 'slugs']); })());
$say('wp_set_object_terms replace ids', (static function () use ($tp) { $r = wp_set_object_terms($tp, [2], 'post_tag'); return [$r, wp_get_object_terms($tp, 'post_tag', ['fields' => 'ids']), get_term(3, 'post_tag')->count]; })());
$say('wp_set_object_terms clear', [wp_set_object_terms($tp, [], 'post_tag'), wp_get_object_terms($tp, 'post_tag'), get_term(2, 'post_tag')->count]);
$say('wp_set_object_terms bad tax', $kind(wp_set_object_terms($tp, ['x'], 'nope')));
$say('wp_set_post_terms', [wp_set_post_terms($tp, 'engine, parity', 'post_tag'), wp_get_post_terms($tp, 'post_tag', ['fields' => 'slugs'])]);
$say('wp_set_post_terms categories by id', [count(wp_set_post_terms($tp, [1], 'category')), wp_get_post_categories($tp)]);
$say('wp_delete_term', [wp_delete_term($dup->term_id, 'category'), wp_delete_term(999999, 'category'), wp_delete_term(1, 'category'), ($x = wp_delete_term(2, 'nope')) instanceof WP_Error ? $x->get_error_code() : $x]);
$say('wp_delete_term default category', get_option('default_category'));
$say('get_term_children', [get_term_children(1, 'category'), get_term_children(999999, 'category')]);
$say('get_ancestors', [get_ancestors($ins['term_id'], 'category'), get_ancestors(6, 'page'), get_ancestors(999999, 'category')]);
$say('get_categories', array_map(static fn ($c) => $c->slug, get_categories(['hide_empty' => false])));
$say('get_tags', array_map(static fn ($c) => $c->slug, get_tags()));
$say('get_objects_in_term', array_values(array_filter(get_objects_in_term(1, 'category'), static fn ($id) => (int) $id < 1000)));
$say('get_post_taxonomies', [get_post_taxonomies(5), get_post_taxonomies(2)]);
$say('clean_term_cache', clean_term_cache(1, 'category'));

// Post types and taxonomies.
$pt = get_post_type_object('post');
$say('get_post_type_object class', get_class($pt));
$say('post type props', $vars($pt));
$say('post type values', [$pt->name, $pt->label, $pt->labels->name, $pt->labels->singular_name, $pt->public, $pt->hierarchical, $pt->has_archive, $pt->rewrite, $pt->query_var, $pt->capability_type, $pt->cap->edit_posts, $pt->cap->publish_posts, $pt->cap->edit_others_posts, $pt->show_in_rest, $pt->rest_base, $pt->menu_icon, $pt->taxonomies, $pt->_builtin, $pt->description, $pt->map_meta_cap]);
$page = get_post_type_object('page');
$say('page type values', [$page->hierarchical, $page->capability_type, $page->cap->edit_posts, $page->rest_base, $page->has_archive, $page->rewrite, $page->menu_icon]);
$att = get_post_type_object('attachment');
$say('attachment type values', [$att->public, $att->show_in_rest, $att->rest_base, $att->cap->create_posts, $att->capability_type]);
$say('get_post_type_object missing', get_post_type_object('nope'));
$say('post type labels keys', array_keys(get_object_vars($pt->labels)));
$reg = register_post_type('minn_probe', ['public' => true, 'label' => 'Probes', 'supports' => ['title', 'editor', 'custom-fields'], 'show_in_rest' => true, 'taxonomies' => ['post_tag'], 'has_archive' => true, 'rewrite' => ['slug' => 'probes']]);
$say('register_post_type', [get_class($reg), $reg->name, $reg->label, $reg->labels->name, $reg->labels->singular_name, $reg->labels->add_new, $reg->labels->add_new_item, $reg->labels->menu_name, $reg->rest_base, $reg->rest_namespace, $reg->has_archive, $reg->rewrite, $reg->cap->edit_posts, $reg->capability_type, $reg->hierarchical, $reg->publicly_queryable, $reg->show_ui, $reg->show_in_menu, $reg->exclude_from_search, $reg->query_var, $reg->menu_position, $reg->taxonomies, $reg->_builtin, $reg->map_meta_cap]);
$say('register_post_type supports', [post_type_supports('minn_probe', 'title'), post_type_supports('minn_probe', 'thumbnail'), post_type_supports('minn_probe', 'custom-fields')]);
$say('register_post_type bad', [$kind(register_post_type('', [])), $kind(register_post_type('this_name_is_way_too_long_for_it', [])), $kind(register_post_type('Has Space', [])), post_type_exists('Has Space'), post_type_exists('this_name_is_way_too_long_for_it')]);
unregister_post_type('Has Space');
unregister_post_type('this_name_is_way_too_long_for_it');
$say('register_post_type twice', [$kind(register_post_type('minn_probe', ['label' => 'Again'])), get_post_type_object('minn_probe')->label]);
$say('get_post_types', array_keys(get_post_types()));
$say('get_post_types public', array_keys(get_post_types(['public' => true])));
$say('get_post_types custom', array_keys(get_post_types(['_builtin' => false])));
$say('get_post_types objects', $kind(array_values(get_post_types([], 'objects'))[0]));
$say('get_post_types not', array_keys(get_post_types(['public' => true, 'hierarchical' => true], 'names', 'not')));
$say('post_type_supports', [post_type_supports('post', 'thumbnail'), post_type_supports('post', 'nope'), post_type_supports('page', 'page-attributes'), post_type_supports('nope', 'title')]);
$say('post type supports list', array_keys(get_all_post_type_supports('post')));
$say('add/remove support', (static function () { add_post_type_support('page', 'minn-feature', ['a' => 1]); $a = get_all_post_type_supports('page')['minn-feature']; remove_post_type_support('page', 'minn-feature'); return [$a, post_type_supports('page', 'minn-feature')]; })());
$say('get_post_types_by_support', [get_post_types_by_support('excerpt'), get_post_types_by_support(['title', 'thumbnail'])]);
$say('is_post_type_viewable', [is_post_type_viewable('post'), is_post_type_viewable('revision'), is_post_type_viewable('attachment'), is_post_type_viewable('nope'), is_post_type_viewable(get_post_type_object('page'))]);
$say('is_post_type_hierarchical', [is_post_type_hierarchical('page'), is_post_type_hierarchical('post'), is_post_type_hierarchical('nope')]);
$say('post_type_exists', [post_type_exists('post'), post_type_exists('minn_probe'), post_type_exists('nope')]);
$say('unregister_post_type', [unregister_post_type('minn_probe'), post_type_exists('minn_probe'), $kind(unregister_post_type('post')), $kind(unregister_post_type('nope'))]);
$say('get_post_status_object', [get_object_vars(get_post_status_object('publish')), get_post_status_object('nope')]);
$say('get_post_stati', array_keys(get_post_stati()));
$say('get_post_stati public', array_keys(get_post_stati(['public' => true])));
$say('register_post_status', (static function () use ($kind) { $o = register_post_status('minn_status', ['label' => 'Probe', 'public' => true]); return [$kind($o), $o->name, $o->label, $o->public, $o->show_in_admin_all_list, $o->_builtin, in_array('minn_status', array_keys(get_post_stati()), true)]; })());
$say('get_post_statuses', get_post_statuses());
$tax = get_taxonomy('category');
$say('get_taxonomy class', get_class($tax));
$say('taxonomy props', $vars($tax));
$say('taxonomy values', [$tax->name, $tax->label, $tax->labels->name, $tax->hierarchical, $tax->object_type, $tax->public, $tax->rewrite, $tax->query_var, $tax->cap->manage_terms, $tax->cap->assign_terms, $tax->show_in_rest, $tax->rest_base, $tax->_builtin, $tax->default_term, $tax->show_admin_column]);
$tag = get_taxonomy('post_tag');
$say('tag taxonomy values', [$tag->hierarchical, $tag->rewrite, $tag->query_var, $tag->rest_base, $tag->cap->assign_terms]);
$say('get_taxonomy missing', get_taxonomy('nope'));
$rt = register_taxonomy('minn_tax', ['post', 'minn_probe'], ['label' => 'Probe Tax', 'hierarchical' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'ptax']]);
$say('register_taxonomy', [get_class($rt), $rt->name, $rt->label, $rt->labels->name, $rt->labels->singular_name, $rt->labels->add_new_item, $rt->hierarchical, $rt->object_type, $rt->rewrite, $rt->query_var, $rt->rest_base, $rt->cap->manage_terms, $rt->public, $rt->show_ui, $rt->_builtin, $rt->default_term]);
$say('register_taxonomy bad', [$kind(register_taxonomy('', 'post')), $kind(register_taxonomy('this_taxonomy_name_is_far_too_long', 'post')), taxonomy_exists('this_taxonomy_name_is_far_too_long')]);
unregister_taxonomy('this_taxonomy_name_is_far_too_long');
$say('taxonomy_exists', [taxonomy_exists('category'), taxonomy_exists('minn_tax'), taxonomy_exists('nope')]);
$say('is_taxonomy_hierarchical', [is_taxonomy_hierarchical('category'), is_taxonomy_hierarchical('post_tag'), is_taxonomy_hierarchical('nope')]);
$say('get_object_taxonomies', [get_object_taxonomies('post'), get_object_taxonomies('page'), get_object_taxonomies('attachment'), get_object_taxonomies(get_post(5)), get_object_taxonomies('nope'), array_keys(get_object_taxonomies('post', 'objects'))]);
$say('get_taxonomies', [array_keys(get_taxonomies()), array_keys(get_taxonomies(['public' => true])), array_keys(get_taxonomies(['_builtin' => false]))]);
$say('register_taxonomy_for_object_type', [register_taxonomy_for_object_type('minn_tax', 'page'), get_object_taxonomies('page'), register_taxonomy_for_object_type('nope', 'page'), unregister_taxonomy_for_object_type('minn_tax', 'page'), get_object_taxonomies('page')]);
$say('unregister_taxonomy', [unregister_taxonomy('minn_tax'), taxonomy_exists('minn_tax'), $kind(unregister_taxonomy('category')), $kind(unregister_taxonomy('nope'))]);

// Authors.
$say('get_the_author_meta', [get_the_author_meta('display_name', 1), get_the_author_meta('user_email', 1), get_the_author_meta('nope', 1), get_the_author_meta('ID', 2), get_the_author_meta('user_nicename', 2), get_the_author_meta('description', 1), get_the_author_meta('display_name', 999999)]);
$say('get_the_author no post', get_the_author());
$say('get_the_author with post', (static function () { $GLOBALS['post'] = get_post(9); setup_postdata($GLOBALS['post']); $r = [get_the_author(), get_the_author_meta('ID'), get_the_author_meta('display_name')]; wp_reset_postdata(); return $r; })());
$say('get_author_posts_url', [$rel(get_author_posts_url(1)), $rel(get_author_posts_url(2, 'editor')), $rel(get_author_posts_url(999999))]);
$say('count_user_posts', [count_user_posts(1), count_user_posts(2, 'post', true), count_user_posts(2, ['post', 'page'])]);

// Query vars and conditionals from the command line (no main query).
$say('get_query_var', [get_query_var('paged'), get_query_var('paged', 1), get_query_var('nope', 'd')]);
$say('set_query_var', (static function () { set_query_var('minn_probe', 'v'); return get_query_var('minn_probe'); })());
$say('get_search_query', get_search_query());
$say('queried object', [get_queried_object(), get_queried_object_id()]);
$say('conditionals', [is_singular(), is_single(), is_page(), is_home(), is_front_page(), is_archive(), is_search(), is_404(), is_author(), is_category(), is_tag(), is_tax(), is_date(), is_feed(), is_attachment(), is_post_type_archive(), is_paged(), is_preview(), is_embed(), in_the_loop(), have_posts(), is_main_query()]);
$say('wp_get_nav_menus', array_map(static fn ($m) => [$m->term_id, $m->name, $m->slug, $m->taxonomy], wp_get_nav_menus()));
$say('wp_get_nav_menu_items missing', wp_get_nav_menu_items('nope'));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
