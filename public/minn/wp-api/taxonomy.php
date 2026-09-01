<?php

use Minn\Content\TermLinks;
/** Terms and taxonomies. Behaviour from contracts/fixtures/api/content.json. */

use Minn\Content\Terms;
use Minn\Runtime\Meta;
use Minn\Runtime\Refusal;
use Minn\Front\TermLists;
use Minn\Content\PostWriter;
use Minn\Runtime\Runtime;
use Minn\Runtime\TermQuery;
use Minn\Runtime\TermWriter;

/** @internal */
function _minn_term_query(): TermQuery
{
    return new TermQuery(Runtime::current()->db, static fn (string $s): string => sanitize_title($s));
}

/** @internal */
function _minn_term_writer(): TermWriter
{
    return new TermWriter(Runtime::current()->db, _minn_terms(), _minn_term_query(), _minn_post_writer(), static fn (string $s): string => sanitize_title($s));
}

/** @internal a term row joined with its taxonomy row, by id (and taxonomy when known) */
function _minn_term_row(int $termId, ?string $taxonomy): ?array
{
    return _minn_term_query()->row($termId, $taxonomy);
}

/** @internal the WP_Error a refusal reads as */
function _minn_refused(Refusal $refusal): WP_Error
{
    return new WP_Error($refusal->code, $refusal->message, $refusal->data);
}

/** @internal */
function _minn_terms(): Terms
{
    return new Terms(Runtime::current()->db);
}

function get_taxonomy($taxonomy)
{
    $row = is_scalar($taxonomy) ? Runtime::registry()->taxonomy((string) $taxonomy) : null;
    return $row === null ? false : new WP_Taxonomy((string) $taxonomy, [], $row);
}

function taxonomy_exists($taxonomy)
{
    return is_scalar($taxonomy) && Runtime::registry()->taxonomy((string) $taxonomy) !== null;
}

function is_taxonomy_hierarchical($taxonomy)
{
    $row = is_scalar($taxonomy) ? Runtime::registry()->taxonomy((string) $taxonomy) : null;
    return $row !== null && !empty($row['hierarchical']);
}

function is_taxonomy_viewable($taxonomy)
{
    if (is_scalar($taxonomy)) {
        $taxonomy = get_taxonomy($taxonomy);
    }
    return is_object($taxonomy) && (bool) $taxonomy->publicly_queryable;
}

function get_taxonomies($args = [], $output = 'names', $operator = 'and')
{
    $objects = [];
    foreach (Runtime::registry()->taxonomies() as $name => $row) {
        $objects[$name] = new WP_Taxonomy($name, [], $row);
    }
    return wp_filter_object_list($objects, $args, $operator, $output === 'names' ? 'name' : false);
}

function get_object_taxonomies($object_type, $output = 'names')
{
    if (is_object($object_type)) {
        $object_type = $object_type->post_type === 'attachment' ? get_attachment_taxonomies($object_type) : $object_type->post_type;
    }
    $types = array_map('strval', (array) $object_type);
    $out = [];
    foreach (Runtime::registry()->taxonomies() as $name => $row) {
        if (array_intersect($types, (array) $row['object_type']) !== []) {
            $out[$name] = $output === 'names' ? $name : new WP_Taxonomy($name, [], $row);
        }
    }
    return $output === 'names' ? array_values($out) : $out;
}

function get_attachment_taxonomies($attachment, $output = 'names')
{
    return [];
}

function register_taxonomy($taxonomy, $object_type, $args = [])
{
    $taxonomy = (string) $taxonomy;
    if ($taxonomy === '' || strlen($taxonomy) > 32) {
        _doing_it_wrong(__FUNCTION__, 'Taxonomy names must be between 1 and 32 characters in length.', '4.2.0');
        return new WP_Error('taxonomy_length_invalid', 'Taxonomy names must be between 1 and 32 characters in length.');
    }
    $args = apply_filters('register_taxonomy_args', (array) $args, $taxonomy, (array) $object_type);
    $row = Runtime::registry()->registerTaxonomy($taxonomy, array_values(array_map('strval', (array) $object_type)), $args);
    $object = new WP_Taxonomy($taxonomy, [], $row);
    do_action('registered_taxonomy', $taxonomy, $object_type, get_object_vars($object));
    do_action("registered_taxonomy_{$taxonomy}", $taxonomy, $object_type, get_object_vars($object));
    return $object;
}

function unregister_taxonomy($taxonomy)
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    if (!empty(Runtime::registry()->taxonomy((string) $taxonomy)['_builtin'])) {
        return new WP_Error('invalid_taxonomy', 'Unregistering a built-in taxonomy is not allowed.');
    }
    Runtime::registry()->unregisterTaxonomy((string) $taxonomy);
    do_action('unregistered_taxonomy', $taxonomy);
    return true;
}

function register_taxonomy_for_object_type($taxonomy, $object_type)
{
    if (!taxonomy_exists($taxonomy) || !post_type_exists($object_type)) {
        return false;
    }
    Runtime::registry()->addObjectType((string) $taxonomy, (string) $object_type);
    do_action('registered_taxonomy_for_object_type', $taxonomy, $object_type);
    return true;
}

function unregister_taxonomy_for_object_type($taxonomy, $object_type)
{
    if (!taxonomy_exists($taxonomy) || !post_type_exists($object_type)) {
        return false;
    }
    $removed = Runtime::registry()->removeObjectType((string) $taxonomy, (string) $object_type);
    if ($removed) {
        do_action('unregistered_taxonomy_for_object_type', $taxonomy, $object_type);
    }
    return $removed;
}

function get_taxonomy_labels($tax)
{
    return $tax->labels;
}

function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw')
{
    if (empty($term)) {
        return new WP_Error('invalid_term', 'Empty Term.');
    }
    if ($taxonomy !== '' && !taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    if ($term instanceof WP_Term) {
        $object = $term;
    } elseif (is_object($term)) {
        $object = new WP_Term($term);
    } else {
        $row = _minn_term_row((int) $term, $taxonomy === '' ? null : (string) $taxonomy);
        if ($row === null) {
            return null;
        }
        $object = new WP_Term((object) $row);
    }
    $object = apply_filters('get_term', $object, $taxonomy);
    $object = apply_filters("get_{$object->taxonomy}", $object, $object->taxonomy);
    if ($output === ARRAY_A) {
        return $object->to_array();
    }
    if ($output === ARRAY_N) {
        return array_values($object->to_array());
    }
    return $object;
}

function get_term_by($field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw')
{
    if ($taxonomy !== '' && !taxonomy_exists($taxonomy) && $field !== 'term_taxonomy_id') {
        return false;
    }
    $found = _minn_term_query()->find((string) $field, $value, $taxonomy === '' ? null : (string) $taxonomy);
    return $found === null ? false : get_term($found['term_id'], $found['taxonomy'], $output, $filter);
}

function term_exists($term, $taxonomy = '', $parent_term = null)
{
    if ($term === null || $term === '' || $term === 0 || $term === '0') {
        return null;
    }
    $found = _minn_term_query()->exists(is_int($term) ? $term : (string) $term, $taxonomy === '' ? null : (string) $taxonomy, $parent_term === null ? null : (int) $parent_term);
    if ($found === null) {
        return null;
    }
    if ($taxonomy === '') {
        return (string) $found['term_id'];
    }
    return ['term_id' => (string) $found['term_id'], 'term_taxonomy_id' => (string) $found['term_taxonomy_id']];
}

function get_terms($args = [], $deprecated = '')
{
    // The legacy shape: the taxonomy (or a list of them) first, the arguments second.
    if (is_string($args) || (is_array($args) && !isset($args['taxonomy']) && !empty($deprecated) && wp_is_numeric_array($args))) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    } elseif (is_array($args) && wp_is_numeric_array($args) && $args !== [] && is_string($args[0])) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    }
    $args = wp_parse_args($args, TermQuery::DEFAULTS);
    $taxonomies = $args['taxonomy'] === null ? null : array_values(array_map('strval', (array) $args['taxonomy']));
    foreach ($taxonomies ?? [] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
        }
    }
    $query = _minn_term_query();
    $args = $query->normalise((array) apply_filters('get_terms_args', $args, $taxonomies ?? []));
    if ($args['fields'] === 'count' || $args['count']) {
        return (string) $query->count($args, $taxonomies);
    }
    $terms = array_map(static fn (array $r) => new WP_Term((object) $r), $query->rows($args, $taxonomies));
    $terms = apply_filters('get_terms', $terms, $taxonomies ?? [], $args, []);
    return TermQuery::shape($terms, (string) $args['fields']);
}

/** @internal the fields shapes get_terms and wp_get_object_terms share */
function _minn_term_fields(array $terms, string $fields): array
{
    return TermQuery::shape($terms, $fields);
}

function get_categories($args = '')
{
    $args = wp_parse_args($args, ['taxonomy' => 'category']);
    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return [];
    }
    foreach ($terms as $term) {
        if ($term instanceof WP_Term) {
            _make_cat_compat($term);
        }
    }
    return $terms;
}

function get_tags($args = '')
{
    $args = wp_parse_args($args, ['taxonomy' => 'post_tag']);
    $terms = get_terms($args);
    return is_wp_error($terms) ? [] : apply_filters('get_tags', $terms, $args);
}

function get_category($category, $output = OBJECT, $filter = 'raw')
{
    $term = get_term($category, 'category', $output, $filter);
    if ($term instanceof WP_Term) {
        _make_cat_compat($term);
    }
    return $term;
}

function get_tag($tag, $output = OBJECT, $filter = 'raw')
{
    return get_term($tag, 'post_tag', $output, $filter);
}

function get_category_by_slug($slug)
{
    $term = get_term_by('slug', $slug, 'category');
    if ($term instanceof WP_Term) {
        _make_cat_compat($term);
    }
    return $term;
}

function get_cat_ID($cat_name)
{
    $term = get_term_by('name', $cat_name, 'category');
    return $term ? $term->term_id : 0;
}

function get_cat_name($cat_id)
{
    $term = get_term((int) $cat_id, 'category');
    return $term instanceof WP_Term ? $term->name : '';
}

function _make_cat_compat(&$category)
{
    if ($category instanceof WP_Term) {
        $category->cat_ID = $category->term_id;
        $category->category_count = $category->count;
        $category->category_description = $category->description;
        $category->cat_name = $category->name;
        $category->category_nicename = $category->slug;
        $category->category_parent = $category->parent;
    }
}

function get_term_link($term, $taxonomy = '')
{
    if (!$term instanceof WP_Term) {
        if (is_int($term)) {
            $term = get_term($term, $taxonomy);
        } else {
            $term = get_term_by('slug', $term, $taxonomy);
        }
    }
    if (!$term instanceof WP_Term) {
        return new WP_Error('invalid_term', 'Empty Term.');
    }
    $permalinks = Runtime::current()->get('permalinks');
    $row = Minn\Content\TermRecord::fromRow(_minn_term_row($term->term_id, $term->taxonomy) ?? $term->to_array());
    $link = $permalinks === null ? home_url('/?' . $term->taxonomy . '=' . $term->slug) : $permalinks->forTerm($row);
    if ($term->taxonomy === 'post_tag') {
        $link = apply_filters('tag_link', $link, $term->term_id);
    } elseif ($term->taxonomy === 'category') {
        $link = apply_filters('category_link', $link, $term->term_id);
    }
    return apply_filters('term_link', $link, $term, $term->taxonomy);
}

function get_category_link($category)
{
    $term = $category instanceof WP_Term ? $category : get_term((int) $category, 'category');
    return $term instanceof WP_Term ? get_term_link($term) : '';
}

function get_tag_link($tag)
{
    $term = $tag instanceof WP_Term ? $tag : get_term((int) $tag, 'post_tag');
    return $term instanceof WP_Term ? get_term_link($term) : '';
}

function wp_get_object_terms($object_ids, $taxonomies, $args = [])
{
    $taxonomies = array_values(array_map('strval', (array) $taxonomies));
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
        }
    }
    $ids = array_map('intval', (array) $object_ids);
    $args = wp_parse_args($args, ['fields' => 'all', 'orderby' => 'name', 'order' => 'ASC']);
    $args['taxonomy'] = $taxonomies;
    $args['object_ids'] = $ids;
    $args['hide_empty'] = false;
    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return $terms;
    }
    return apply_filters('wp_get_object_terms', $terms, $ids, $taxonomies, $args);
}

function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false)
{
    $object_id = (int) $object_id;
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $terms = array_values(array_filter(array_map(static fn ($t) => is_string($t) ? trim($t) : $t, (array) $terms), static fn ($t) => $t !== '' && $t !== null));
    $ttIds = [];
    foreach ($terms as $term) {
        $info = term_exists($term, $taxonomy);
        if (!$info) {
            if (is_int($term)) {
                continue;
            }
            $info = wp_insert_term((string) $term, $taxonomy);
            if (is_wp_error($info)) {
                return $info;
            }
        }
        $ttIds[] = $info['term_taxonomy_id'];
    }
    $old = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'tt_ids', 'orderby' => 'none']);
    $old = is_wp_error($old) ? [] : array_map('intval', $old);
    $keep = $append ? array_values(array_unique([...$old, ...array_map('intval', $ttIds)])) : array_map('intval', $ttIds);
    _minn_term_writer()->relate($object_id, $keep, $old, (string) $taxonomy);
    wp_cache_delete($object_id, 'post_meta');
    do_action('set_object_terms', $object_id, $terms, $ttIds, $taxonomy, $append, $old);
    return $ttIds;
}

function wp_remove_object_terms($object_id, $terms, $taxonomy)
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $ttIds = [];
    foreach ((array) $terms as $term) {
        $info = term_exists($term, $taxonomy);
        if ($info) {
            $ttIds[] = (int) $info['term_taxonomy_id'];
        }
    }
    return _minn_term_writer()->unrelate((int) $object_id, $ttIds, (string) $taxonomy);
}

function wp_add_object_terms($object_id, $terms, $taxonomy)
{
    return wp_set_object_terms($object_id, $terms, $taxonomy, true);
}

function get_the_terms($post, $taxonomy)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $terms = wp_get_object_terms($post->ID, $taxonomy);
    if (is_wp_error($terms)) {
        return $terms;
    }
    $terms = apply_filters('get_the_terms', $terms, $post->ID, $taxonomy);
    return $terms === [] ? false : $terms;
}

function get_the_category($post_id = false)
{
    $terms = get_the_terms($post_id, 'category');
    if (!is_array($terms)) {
        $terms = [];
    }
    foreach ($terms as $term) {
        _make_cat_compat($term);
    }
    return apply_filters('get_the_categories', $terms, $post_id);
}

function get_the_tags($post = 0)
{
    $terms = get_the_terms($post, 'post_tag');
    return apply_filters('get_the_tags', $terms);
}

function has_term($term = '', $taxonomy = '', $post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $terms = wp_get_object_terms($post->ID, $taxonomy);
    if (is_wp_error($terms)) {
        return false;
    }
    if ($term === '' || $term === null || $term === []) {
        return $terms !== [];
    }
    foreach ((array) $term as $one) {
        foreach ($terms as $t) {
            if ((is_int($one) && $t->term_id === $one) || (is_string($one) && ($t->slug === $one || $t->name === $one || (ctype_digit($one) && $t->term_id === (int) $one)))) {
                return true;
            }
        }
    }
    return false;
}

function has_category($category = '', $post = null)
{
    return has_term($category, 'category', $post);
}

function has_tag($tag = '', $post = null)
{
    return has_term($tag, 'post_tag', $post);
}

function in_category($category, $post = null)
{
    return has_category($category, $post);
}

function wp_insert_term($term, $taxonomy, $args = [])
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $term = apply_filters('pre_insert_term', wp_unslash((string) $term), $taxonomy, $args);
    if (is_wp_error($term)) {
        return $term;
    }
    if (is_int($term) && $term === 0) {
        return new WP_Error('invalid_term_id', 'Invalid term ID.');
    }
    $args = wp_parse_args(wp_unslash((array) $args), ['alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '']);
    $made = _minn_term_writer()->insert((string) $term, (string) $taxonomy, $args, is_taxonomy_hierarchical($taxonomy));
    if ($made instanceof Refusal) {
        return _minn_refused($made);
    }
    [$termId, $ttId] = [$made['term_id'], $made['term_taxonomy_id']];
    do_action('create_term', $termId, $ttId, $taxonomy, $args);
    do_action("create_{$taxonomy}", $termId, $ttId, $args);
    do_action('created_term', $termId, $ttId, $taxonomy, $args);
    do_action("created_{$taxonomy}", $termId, $ttId, $args);
    do_action('saved_term', $termId, $ttId, $taxonomy, false, $args);
    do_action("saved_{$taxonomy}", $termId, $ttId, false, $args);
    return ['term_id' => $termId, 'term_taxonomy_id' => $ttId];
}

function wp_update_term($term_id, $taxonomy, $args = [])
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $term = get_term((int) $term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return new WP_Error('invalid_term', 'Empty Term.');
    }
    $args = wp_unslash((array) $args);
    $change = _minn_term_writer()->update($term->to_array(), (string) $taxonomy, $args);
    if ($change instanceof Refusal) {
        return _minn_refused($change);
    }
    do_action('edit_terms', $term->term_id, $taxonomy, $args);
    _minn_term_writer()->apply($term->term_id, (string) $taxonomy, $change);
    do_action('edited_terms', $term->term_id, $taxonomy, $args);
    do_action('edit_term', $term->term_id, $term->term_taxonomy_id, $taxonomy, $args);
    do_action("edit_{$taxonomy}", $term->term_id, $term->term_taxonomy_id, $args);
    do_action('edited_term', $term->term_id, $term->term_taxonomy_id, $taxonomy, $args);
    do_action("edited_{$taxonomy}", $term->term_id, $term->term_taxonomy_id, $args);
    do_action('saved_term', $term->term_id, $term->term_taxonomy_id, $taxonomy, true, $args);
    do_action("saved_{$taxonomy}", $term->term_id, $term->term_taxonomy_id, true, $args);
    return ['term_id' => $term->term_id, 'term_taxonomy_id' => $term->term_taxonomy_id];
}

function wp_delete_term($term, $taxonomy, $args = [])
{
    if (!taxonomy_exists($taxonomy)) {
        return false;
    }
    $object = get_term((int) $term, $taxonomy);
    if (!$object instanceof WP_Term) {
        return false;
    }
    $default = (int) get_option('default_category');
    if ($taxonomy === 'category' && $object->term_id === $default) {
        return 0;
    }
    $args = wp_parse_args($args, ['default' => null, 'force_default' => false]);
    $row = _minn_term_row($object->term_id, (string) $taxonomy) ?? $object->to_array();
    do_action('pre_delete_term', $object->term_id, $taxonomy);
    $objects = array_map('strval', _minn_term_writer()->delete($row, (string) $taxonomy, is_taxonomy_hierarchical($taxonomy), $taxonomy === 'category' ? $default : (int) ($args['default'] ?? 0)));
    do_action('deleted_term_taxonomy', $object->term_taxonomy_id);
    do_action('delete_term', $object->term_id, $object->term_taxonomy_id, $taxonomy, $object, $objects);
    do_action("delete_{$taxonomy}", $object->term_id, $object->term_taxonomy_id, $object, $objects);
    return true;
}

function wp_delete_category($cat_id)
{
    return wp_delete_term($cat_id, 'category');
}

function get_term_children($term_id, $taxonomy)
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    return _minn_term_query()->children((int) $term_id, (string) $taxonomy);
}

function get_objects_in_term($term_ids, $taxonomies, $args = [])
{
    $taxonomies = array_values(array_map('strval', (array) $taxonomies));
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
        }
    }
    $order = (string) (wp_parse_args($args, ['order' => 'ASC'])['order']);
    return array_map('strval', _minn_term_query()->objectsIn(array_map('intval', (array) $term_ids), $taxonomies, $order));
}

function get_post_taxonomies($post = 0)
{
    $post = get_post($post);
    return $post === null ? [] : get_object_taxonomies($post);
}

function clean_term_cache($ids, $taxonomy = '', $clean_taxonomy = true)
{
    foreach ((array) $ids as $id) {
        wp_cache_delete((int) $id, 'terms');
        wp_cache_delete((int) $id, 'term_meta');
    }
    do_action('clean_term_cache', (array) $ids, $taxonomy, $clean_taxonomy);
}

function clean_object_term_cache($object_ids, $object_type)
{
    foreach ((array) $object_ids as $id) {
        wp_cache_delete((int) $id, 'post_meta');
    }
    do_action('clean_object_term_cache', (array) $object_ids, $object_type);
}

function wp_update_term_count($terms, $taxonomy, $do_deferred = false)
{
    _minn_post_writer()->recount((string) $taxonomy);
    return true;
}

function wp_update_term_count_now($terms, $taxonomy)
{
    return wp_update_term_count($terms, $taxonomy);
}

function wp_defer_term_counting($defer = null)
{
    return false;
}

function get_term_field($field, $term, $taxonomy = '', $context = 'display')
{
    $term = get_term($term, $taxonomy);
    if (!$term instanceof WP_Term) {
        return $term ?? '';
    }
    return $term->{$field} ?? '';
}

function sanitize_term($term, $taxonomy, $context = 'display')
{
    return $term;
}

function sanitize_term_field($field, $value, $term_id, $taxonomy, $context)
{
    return $value;
}

function get_term_to_edit($id, $taxonomy)
{
    return get_term($id, $taxonomy);
}

function term_description($term = 0, $deprecated = null)
{
    if (!$term) {
        $term = get_queried_object();
    }
    $term = $term instanceof WP_Term ? $term : get_term($term);
    return $term instanceof WP_Term ? apply_filters('term_description', $term->description, $term) : '';
}

function category_description($category = 0)
{
    return term_description($category ?: get_queried_object());
}


function wp_get_nav_menus($args = [])
{
    $terms = get_terms(wp_parse_args($args, ['taxonomy' => 'nav_menu', 'hide_empty' => false, 'orderby' => 'name']));
    return is_wp_error($terms) ? [] : apply_filters('wp_get_nav_menus', $terms, $args);
}

function wp_get_nav_menu_object($menu)
{
    if (!$menu) {
        return false;
    }
    if ($menu instanceof WP_Term) {
        return $menu;
    }
    $object = is_numeric($menu) ? get_term((int) $menu, 'nav_menu') : (get_term_by('slug', (string) $menu, 'nav_menu') ?: get_term_by('name', (string) $menu, 'nav_menu'));
    return $object instanceof WP_Term ? $object : false;
}

function is_nav_menu($menu)
{
    return wp_get_nav_menu_object($menu) !== false;
}

function wp_get_nav_menu_items($menu, $args = [])
{
    $menu = wp_get_nav_menu_object($menu);
    if (!$menu) {
        return false;
    }
    $items = get_posts(['post_type' => 'nav_menu_item', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC', 'tax_query' => [['taxonomy' => 'nav_menu', 'field' => 'term_id', 'terms' => [$menu->term_id]]]]);
    foreach ($items as $item) {
        $item->db_id = $item->ID;
        $item->menu_item_parent = (string) (int) get_post_meta($item->ID, '_menu_item_menu_item_parent', true);
        $item->object_id = (string) (int) get_post_meta($item->ID, '_menu_item_object_id', true);
        $item->object = (string) get_post_meta($item->ID, '_menu_item_object', true);
        $item->type = (string) get_post_meta($item->ID, '_menu_item_type', true);
        $item->type_label = ucfirst($item->type);
        $item->url = (string) get_post_meta($item->ID, '_menu_item_url', true);
        if ($item->type === 'post_type' && (int) $item->object_id > 0) {
            $item->url = (string) get_permalink((int) $item->object_id);
            $item->title = $item->post_title !== '' ? $item->post_title : get_the_title((int) $item->object_id);
        } elseif ($item->type === 'taxonomy' && (int) $item->object_id > 0) {
            $link = get_term_link((int) $item->object_id, $item->object);
            $item->url = is_wp_error($link) ? '' : $link;
            $term = get_term((int) $item->object_id, $item->object);
            $item->title = $item->post_title !== '' ? $item->post_title : ($term instanceof WP_Term ? $term->name : '');
        } else {
            $item->title = $item->post_title;
        }
        $item->target = (string) get_post_meta($item->ID, '_menu_item_target', true);
        $item->attr_title = $item->post_excerpt;
        $item->description = $item->post_content;
        $item->classes = (array) get_post_meta($item->ID, '_menu_item_classes', true);
        $item->xfn = (string) get_post_meta($item->ID, '_menu_item_xfn', true);
    }
    return apply_filters('wp_get_nav_menu_items', $items, $menu, $args);
}

function is_object_in_taxonomy($object_type, $taxonomy)
{
    $row = Runtime::registry()->taxonomy((string) $taxonomy);
    return $row !== null && in_array((string) $object_type, (array) $row['object_type'], true);
}

/** The slug, or the first "-N" form of it, that no other term of the taxonomy holds. */
function wp_unique_term_slug($slug, $term)
{
    $term = (object) $term;
    $unique = (new Terms(Runtime::current()->db))->uniqueSlug((string) $slug, (string) $term->taxonomy, (int) ($term->term_id ?? 0));
    return apply_filters('wp_unique_term_slug', $unique, $term, $slug);
}

/** Recounts the published objects behind each term_taxonomy id. */
function _update_post_term_count($terms, $taxonomy)
{
    $name = is_object($taxonomy) ? (string) $taxonomy->name : (string) $taxonomy;
    if ($name === '') {
        return null;
    }
    (new PostWriter(Runtime::current()->db, _minn_posts(), Runtime::current()->site))->recount($name);
    return null;
}

/** Term meta per id, as the cache primer hands it back; false for an empty list. */
function update_termmeta_cache($term_ids)
{
    $ids = array_values(array_unique(array_map('intval', (array) $term_ids)));
    if ($ids === []) {
        return false;
    }
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = (new Meta(Runtime::current()->db))->all('term', $id);
    }
    return $out;
}

/** The category's name, or "" for an id no category has. */
function get_the_category_by_ID($cat_id)
{
    $term = get_term((int) $cat_id, 'category');
    return $term instanceof WP_Term ? (string) $term->name : '';
}

/** The queried category's name, with a prefix, printed or returned; nothing off a category archive. */
function single_cat_title($prefix = '', $display = true)
{
    return single_term_title($prefix, $display);
}

function single_term_title($prefix = '', $display = true)
{
    $term = get_queried_object();
    if (!$term instanceof WP_Term || !(is_category() || is_tag() || is_tax())) {
        return '';
    }
    $filter = match ($term->taxonomy) { 'category' => 'single_cat_title', 'post_tag' => 'single_tag_title', default => 'single_term_title' };
    $name = apply_filters($filter, $term->name);
    if ($display) {
        echo $prefix . $name;
        return null;
    }
    return $name;
}

/** The nested category list; markup from Minn\Front\TermLists. */
function wp_list_categories($args = '')
{
    $args = wp_parse_args($args, ['show_option_all' => '', 'show_option_none' => 'No categories', 'orderby' => 'name', 'order' => 'ASC', 'style' => 'list', 'show_count' => 0, 'hide_empty' => 1, 'use_desc_for_title' => 0, 'child_of' => 0, 'feed' => '', 'feed_type' => '', 'feed_image' => '', 'exclude' => '', 'exclude_tree' => '', 'include' => '', 'hierarchical' => true, 'title_li' => 'Categories', 'show_option_none' => 'No categories', 'number' => null, 'echo' => 1, 'depth' => 0, 'current_category' => 0, 'pad_counts' => 0, 'taxonomy' => 'category', 'walker' => null, 'hide_title_if_empty' => false, 'separator' => '<br />']);
    $query = array_intersect_key($args, array_flip(['orderby', 'order', 'hide_empty', 'child_of', 'exclude', 'exclude_tree', 'include', 'number', 'pad_counts', 'taxonomy', 'hierarchical']));
    if ($args['depth'] === -1 || !$args['hierarchical']) {
        $query['hierarchical'] = false;
    }
    $terms = get_terms($query);
    $terms = is_array($terms) ? $terms : [];
    if (is_object($args['walker']) && method_exists($args['walker'], 'walk')) {
        $items = $args['walker']->walk($terms, (int) $args['depth'], $args);
    } else {
        $rows = array_map(static fn ($t) => (array) $t, $terms);
        $items = TermLists::categoryList($rows, $args, static fn (array $row) => (string) get_term_link((int) $row['term_id'], (string) $row['taxonomy']));
    }
    [$before, $after] = $terms === [] && $args['hide_title_if_empty'] ? ['', ''] : TermLists::categoryWrapper($args);
    $output = apply_filters('wp_list_categories', $before . $items . $after, $args);
    if ($args['echo']) {
        echo $output;
        return null;
    }
    return $output;
}

/** The tag cloud for a taxonomy's terms; nothing at all when there are none. */
function wp_tag_cloud($args = '')
{
    $args = wp_parse_args($args, ['smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45, 'format' => 'flat', 'separator' => "\n", 'orderby' => 'name', 'order' => 'ASC', 'exclude' => '', 'include' => '', 'link' => 'view', 'taxonomy' => 'post_tag', 'post_type' => '', 'echo' => true, 'show_count' => 0]);
    $tags = get_terms(array_merge(['taxonomy' => $args['taxonomy'], 'orderby' => 'count', 'order' => 'DESC'], array_intersect_key($args, array_flip(['number', 'exclude', 'include', 'hide_empty']))));
    if (!is_array($tags) || $tags === []) {
        return null;
    }
    foreach ($tags as $tag) {
        $tag->link = (string) get_term_link($tag);
        $tag->id = (int) $tag->term_id;
    }
    $return = wp_generate_tag_cloud($tags, $args);
    $return = apply_filters('wp_tag_cloud', $return, $args);
    if ($args['echo']) {
        echo $return;
        return null;
    }
    return $return;
}

function wp_generate_tag_cloud($tags, $args = '')
{
    $args = wp_parse_args($args, ['smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 0, 'format' => 'flat', 'separator' => "\n", 'orderby' => 'name', 'order' => 'ASC', 'topic_count_text' => null, 'topic_count_text_callback' => null, 'topic_count_scale_callback' => 'default_topic_count_scale', 'filter' => 1, 'show_count' => 0]);
    $rows = array_map(static fn ($t) => (array) $t, (array) $tags);
    $out = TermLists::tagCloud($rows, $args);
    if ($out === null) {
        return '';
    }
    if ($args['format'] === 'array') {
        $out = explode("\n", $out);
    }
    return $args['filter'] ? apply_filters('wp_generate_tag_cloud', $out, $tags, $args) : $out;
}

/** @internal the term_taxonomy ids a tax clause names, children of hierarchical terms included when asked */
function _minn_term_taxonomy_ids(string $taxonomy, string $field, array $terms, bool $children): array
{
    if ($terms === []) {
        return [];
    }
    $ids = [];
    $parents = [];
    foreach ($terms as $value) {
        $term = match ($field) {
            'slug', 'name' => get_term_by($field, (string) $value, $taxonomy),
            'term_taxonomy_id' => get_term_by('term_taxonomy_id', (int) $value, $taxonomy),
            default => get_term((int) $value, $taxonomy),
        };
        if ($term instanceof WP_Term) {
            $ids[] = (int) $term->term_taxonomy_id;
            $parents[] = (int) $term->term_id;
        }
    }
    if ($children && $parents !== [] && is_taxonomy_hierarchical($taxonomy)) {
        foreach ($parents as $parent) {
            foreach (get_terms(['taxonomy' => $taxonomy, 'child_of' => $parent, 'hide_empty' => false]) ?: [] as $child) {
                $ids[] = (int) $child->term_taxonomy_id;
            }
        }
    }
    return array_values(array_unique($ids));
}

/** The category select: one option per term, nested by depth when hierarchical, in the reference's markup. */
function wp_dropdown_categories($args = '')
{
    $args = wp_parse_args($args, ['show_option_all' => '', 'show_option_none' => '', 'orderby' => 'id', 'order' => 'ASC', 'show_count' => 0, 'hide_empty' => 1, 'child_of' => 0, 'exclude' => '', 'include' => '', 'echo' => 1, 'selected' => 0, 'hierarchical' => 0, 'name' => 'cat', 'id' => '', 'class' => 'postform', 'depth' => 0, 'tab_index' => 0, 'taxonomy' => 'category', 'hide_if_empty' => false, 'option_none_value' => -1, 'value_field' => 'term_id', 'required' => false, 'aria_describedby' => '']);
    $query = array_intersect_key($args, array_flip(['orderby', 'order', 'hide_empty', 'child_of', 'exclude', 'include', 'taxonomy']));
    $query['hierarchical'] = (bool) $args['hierarchical'];
    $terms = get_terms($query);
    $terms = is_array($terms) ? array_map(static fn ($t) => (array) $t, $terms) : [];
    if ($terms === [] && $args['hide_if_empty']) {
        return apply_filters('wp_dropdown_cats', '', $args);
    }
    $id = $args['id'] !== '' ? $args['id'] : $args['name'];
    $output = '<select ' . ($args['required'] ? 'required' : '') . " name='" . esc_attr($args['name']) . "' id='" . esc_attr($id) . "' class='" . esc_attr($args['class']) . "'"
        . ((int) $args['tab_index'] > 0 ? ' tabindex="' . (int) $args['tab_index'] . '"' : '') . ($args['aria_describedby'] !== '' ? ' aria-describedby="' . esc_attr($args['aria_describedby']) . '"' : '') . ">\n";
    if ($terms !== []) {
        if ($args['show_option_all'] !== '') {
            $output .= "\t<option value='0'" . ((string) $args['selected'] === '0' ? " selected='selected'" : '') . '>' . esc_html($args['show_option_all']) . "</option>\n";
        }
        if ($args['show_option_none'] !== '') {
            $output .= "\t<option value='" . esc_attr((string) $args['option_none_value']) . "'" . ((string) $args['selected'] === (string) $args['option_none_value'] ? " selected='selected'" : '') . '>' . esc_html($args['show_option_none']) . "</option>\n";
        }
        $output .= TermLists::dropdownOptions($terms, $args);
    }
    $output .= "</select>\n";
    $output = apply_filters('wp_dropdown_cats', $output, $args);
    if ($args['echo']) {
        echo $output;
    }
    return $output;
}

/** Every descendant of one term within the caller's own list (ids or term objects), preorder; ancestors guard against loops. */
function _get_term_children($term_id, $terms, $taxonomy, &$ancestors = [])
{
    if ((int) $term_id === 0 && !taxonomy_exists((string) $taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    return \Minn\Support\Lists::descendants(
        array_values((array) $terms),
        (int) $term_id,
        static fn ($t) => (int) (is_object($t) ? $t->term_id : (is_array($t) ? ($t['term_id'] ?? 0) : $t)),
        static function ($t) use ($taxonomy) {
            if (is_object($t)) {
                return (int) $t->parent;
            }
            if (is_array($t)) {
                return (int) ($t['parent'] ?? 0);
            }
            $term = get_term((int) $t, (string) $taxonomy);
            return $term instanceof WP_Term ? (int) $term->parent : -1;
        },
        array_map('intval', (array) $ancestors),
    );
}

/** Whether the object has one of the terms (ids, slugs, or names), or any term of the taxonomy at all. */
function is_object_in_term($object_id, $taxonomy, $terms = null)
{
    if (!taxonomy_exists((string) $taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $object_terms = wp_get_object_terms([(int) $object_id], [(string) $taxonomy]);
    if (is_wp_error($object_terms)) {
        return $object_terms;
    }
    if (empty($terms)) {
        return $object_terms !== [];
    }
    foreach ((array) $terms as $wanted) {
        foreach ($object_terms as $term) {
            $held = is_object($term) ? [(int) $term->term_id, (string) $term->slug, (string) $term->name] : [(int) $term];
            if (in_array(is_numeric($wanted) ? (int) $wanted : (string) $wanted, $held, true)) {
                return true;
            }
        }
    }
    return false;
}

/** The term's ancestor chain as links (or names), each followed by the separator, the probed shape. */
function get_term_parents_list($term_id, $taxonomy, $args = [])
{
    $args = wp_parse_args($args, ['format' => 'name', 'separator' => '/', 'link' => true, 'inclusive' => true]);
    $term = get_term((int) $term_id, (string) $taxonomy);
    if (!$term instanceof WP_Term) {
        return is_wp_error($term) ? $term : '';
    }
    $chain = array_reverse(get_ancestors($term->term_id, (string) $taxonomy, 'taxonomy'));
    if ($args['inclusive']) {
        $chain[] = $term->term_id;
    }
    $out = '';
    foreach ($chain as $id) {
        $ancestor = get_term((int) $id, (string) $taxonomy);
        if (!$ancestor instanceof WP_Term) {
            continue;
        }
        $name = $args['format'] === 'slug' ? $ancestor->slug : $ancestor->name;
        $out .= ($args['link'] ? '<a href="' . esc_url((string) get_term_link($ancestor)) . '">' . esc_html($name) . '</a>' : esc_html($name)) . $args['separator'];
    }
    return $out;
}

/** The post's category links, rel="category tag", joined by the separator (a ul.post-categories without one). */
function the_category($separator = '', $parents = '', $post_id = false)
{
    $categories = get_the_category($post_id);
    if (!is_array($categories) || $categories === []) {
        return;
    }
    $links = array_map(static fn ($c) => '<a href="' . esc_url((string) get_term_link($c)) . '" rel="category tag">' . esc_html($c->name) . '</a>', $categories);
    if ((string) $separator === '') {
        echo '<ul class="post-categories"><li>' . implode('</li><li>', $links) . '</li></ul>';
        return;
    }
    echo implode((string) $separator, $links);
}

/** The post's tag links, rel="tag"; nothing prints for a post without tags (probed). */
function the_tags($before = null, $sep = ', ', $after = '')
{
    $post = get_post();
    $tags = $post === null ? [] : (get_the_terms($post->ID, 'post_tag') ?: []);
    if (!is_array($tags) || $tags === []) {
        return;
    }
    $links = array_map(static fn ($t) => '<a href="' . esc_url((string) get_term_link($t)) . '" rel="tag">' . esc_html($t->name) . '</a>', $tags);
    echo ($before ?? 'Tags: ') . implode((string) $sep, $links) . $after;
}

/**
 * A post's terms in one taxonomy as links, or false when it has none.
 * Shapes captured from the reference: each link carries rel="tag", the
 * list is joined with $sep between $before and $after.
 */
function get_the_term_list($post_id, $taxonomy, $before = '', $sep = '', $after = '')
{
    $terms = get_the_terms($post_id, $taxonomy);
    if (is_wp_error($terms)) {
        return $terms;
    }
    if (empty($terms)) {
        return false;
    }
    $rows = [];
    foreach ($terms as $term) {
        $link = get_term_link($term, $taxonomy);
        if (is_wp_error($link)) {
            return $link;
        }
        $rows[] = ['name' => $term->name, 'url' => (string) $link];
    }
    $links = apply_filters("term_links-{$taxonomy}", array_map(static fn (array $row): string => TermLinks::joined([$row], 'tag', ''), $rows)); // phpcs:ignore
    return $before . implode($sep, $links) . $after;
}

function get_the_tag_list($before = '', $sep = '', $after = '', $post_id = 0)
{
    return apply_filters('the_tags', get_the_term_list($post_id, 'post_tag', $before, $sep, $after), $before, $sep, $after, $post_id);
}

/**
 * A post's categories as links. With no separator the reference emits a
 * `post-categories` list; with one it joins bare links. Both carry
 * rel="category tag".
 */
function get_the_category_list($separator = '', $parents = '', $post_id = false)
{
    $categories = get_the_category($post_id);
    if (empty($categories)) {
        return apply_filters('the_category', '', $separator, $parents);
    }
    $rows = [];
    foreach ($categories as $category) {
        $rows[] = ['name' => $category->name, 'url' => (string) get_category_link($category->term_id)];
    }
    return apply_filters('the_category', TermLinks::categories($rows, $separator), $separator, $parents);
}

/** @internal */
function _minn_menus(): Minn\Content\Menus
{
    $runtime = Minn\Runtime\Runtime::current();
    return new Minn\Content\Menus(
        $runtime->db,
        _minn_posts(),
        _minn_terms(),
        Minn\Front\Permalinks::fromDb($runtime->db),
        _minn_post_writer(),
        $runtime->site,
    );
}

function wp_create_nav_menu($menu_name)
{
    return wp_update_nav_menu_object(0, ['menu-name' => $menu_name]);
}

function wp_update_nav_menu_object($menu_id = 0, $menu_data = [])
{
    $menu_id = (int) $menu_id;
    $menus = _minn_menus();
    $named = array_key_exists('menu-name', $menu_data);
    $name = trim((string) ($menu_data['menu-name'] ?? ''));
    if ($named || $menu_id === 0) {
        $refusal = $menus->refuseName($name, $menu_id);
        if ($refusal !== null) {
            return new WP_Error($refusal->code, $refusal->message, $refusal->data);
        }
    }
    $description = array_key_exists('description', $menu_data) ? (string) $menu_data['description'] : null;
    if ($menu_id === 0) {
        $menu_id = $menus->createMenu($name, (string) $description);
        do_action('wp_create_nav_menu', $menu_id, $menu_data);
    } else {
        $menus->updateMenu($menu_id, $named ? $name : null, $description);
    }
    do_action('wp_update_nav_menu', $menu_id, $menu_data);
    return $menu_id;
}

function wp_delete_nav_menu($menu)
{
    $object = wp_get_nav_menu_object($menu);
    if (!$object) {
        return false;
    }
    $id = (int) $object->term_id;
    _minn_menus()->deleteMenu($id);
    // A deleted menu leaves its theme locations empty rather than pointing at
    // a term that is gone.
    $locations = get_nav_menu_locations();
    $kept = array_filter($locations, static fn ($assigned) => (int) $assigned !== $id);
    if (count($kept) !== count($locations)) {
        set_theme_mod('nav_menu_locations', $kept);
    }
    do_action('wp_delete_nav_menu', $id);
    return true;
}

function _get_term_hierarchy($taxonomy)
{
    return _minn_term_query()->hierarchy((string) $taxonomy);
}

function term_is_ancestor_of($term1, $term2, $taxonomy)
{
    $ancestor = is_object($term1) ? (int) $term1->term_id : (int) $term1;
    $child = is_object($term2) ? (int) $term2->term_id : (int) $term2;
    return in_array($ancestor, array_map('intval', get_ancestors($child, (string) $taxonomy, 'taxonomy')), true);
}

function get_category_parents($category_id, $link = false, $separator = '/', $nicename = false, $deprecated = [])
{
    $term = get_term((int) $category_id, 'category');
    if (!$term || is_wp_error($term)) {
        return $term instanceof WP_Error ? $term : '';
    }
    // Outermost first, the term itself last.
    $ids = array_reverse(array_map('intval', get_ancestors((int) $category_id, 'category', 'taxonomy')));
    $ids[] = (int) $category_id;
    $line = [];
    foreach ($ids as $id) {
        $row = get_term($id, 'category');
        if (!$row || is_wp_error($row)) {
            continue;
        }
        $line[] = ['name' => $row->name, 'slug' => $row->slug, 'link' => get_category_link($id)];
    }
    return Minn\Front\TermLists::parentChain($line, (bool) $link, (string) $separator, (bool) $nicename);
}

function tag_description($tag = 0)
{
    return term_description($tag);
}

function _prime_term_caches($term_ids, $update_meta_cache = true)
{
    // Terms are read straight from the database; there is nothing to prime.
}

function get_object_term_cache($id, $taxonomy)
{
    $cached = wp_cache_get((int) $id, 'minn_object_terms');
    if (!is_array($cached) || !array_key_exists((string) $taxonomy, $cached)) {
        return false;
    }
    return $cached[(string) $taxonomy];
}

function update_object_term_cache($object_ids, $object_type)
{
    $types = get_object_taxonomies((string) $object_type);
    foreach ((array) $object_ids as $id) {
        $terms = [];
        foreach ($types as $taxonomy) {
            $found = wp_get_object_terms((int) $id, $taxonomy);
            $terms[$taxonomy] = is_wp_error($found) ? [] : $found;
        }
        wp_cache_set((int) $id, $terms, 'minn_object_terms');
    }
}

function get_the_taxonomies($post = 0, $args = [])
{
    $post = get_post($post);
    if (!$post) {
        return [];
    }
    $args = wp_parse_args($args, ['template' => '%s: %l.', 'term_template' => '<a href="%1$s">%2$s</a>']);
    $out = [];
    foreach (get_object_taxonomies((string) $post->post_type) as $taxonomy) {
        $terms = get_object_term_cache((int) $post->ID, $taxonomy);
        $terms = $terms === false ? wp_get_object_terms((int) $post->ID, $taxonomy) : $terms;
        if (is_wp_error($terms) || $terms === []) {
            continue;
        }
        $object = get_taxonomy($taxonomy);
        $links = [];
        foreach ($terms as $term) {
            $links[] = sprintf($args['term_template'], esc_attr((string) get_term_link($term)), $term->name);
        }
        $out[$taxonomy] = str_replace(['%s', '%l'], [$object->labels->name ?? $taxonomy, _minn_join_list($links)], $args['template']);
    }
    return $out;
}

/** @internal a list read the way a sentence reads it */
function _minn_join_list(array $items): string
{
    if (count($items) < 2) {
        return (string) ($items[0] ?? '');
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
}
