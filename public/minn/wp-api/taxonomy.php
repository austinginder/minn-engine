<?php

use Minn\Runtime\Registry;
use Minn\Runtime\Deferrals;
use Minn\Content\TermLinks;
/** Terms and taxonomies. Behaviour from contracts/fixtures/api/content.json. */

use Minn\Content\Terms;
use Minn\Front\TermLists;
use Minn\Content\PostWriter;
use Minn\Runtime\Registered;
use Minn\Runtime\Runtime;
use Minn\Runtime\TermFields;
use Minn\Runtime\TermSave;
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
    return new TermWriter(Runtime::current()->db, _minn_terms(), _minn_term_query(), _minn_post_writer());
}

/** @internal a term row joined with its taxonomy row, by id (and taxonomy when known) */
function _minn_term_row(int $termId, ?string $taxonomy): ?array
{
    return _minn_term_query()->row($termId, $taxonomy);
}

/** @internal */
function _minn_terms(): Terms
{
    return new Terms(Runtime::current()->db);
}

function get_taxonomy($taxonomy)
{
    return Registered::taxonomy($taxonomy);
}

function taxonomy_exists($taxonomy)
{
    return Registered::taxonomyExists($taxonomy);
}

function is_taxonomy_hierarchical($taxonomy)
{
    return Registered::hierarchicalTaxonomy($taxonomy);
}

function is_taxonomy_viewable($taxonomy)
{
    return Registered::viewableTaxonomy($taxonomy);
}

function get_taxonomies($args = [], $output = 'names', $operator = 'and')
{
    return Registered::taxonomies($args, $output, $operator);
}

function get_object_taxonomies($object_type, $output = 'names')
{
    // A post is asked by its type; an attachment by its file's types too.
    if (is_object($object_type)) {
        $object_type = $object_type->post_type === 'attachment' ? get_attachment_taxonomies($object_type) : $object_type->post_type;
    }
    return Registered::objectTaxonomies($object_type, $output);
}

/**
 * The taxonomies an attachment carries: those registered for attachments,
 * for its file's extension, its MIME type, and each half of it
 * (attachment:image, attachment:image/png, attachment:png...).
 */
function get_attachment_taxonomies($attachment, $output = 'names')
{
    $attachment = is_int($attachment) ? get_post($attachment) : (is_array($attachment) ? (object) $attachment : $attachment);
    if (!is_object($attachment)) {
        return [];
    }
    $file = wp_basename((string) get_attached_file($attachment->ID ?? 0));
    $objects = ['attachment'];
    if (str_contains($file, '.')) {
        $objects[] = 'attachment:' . substr($file, strrpos($file, '.') + 1);
    }
    $mime = (string) ($attachment->post_mime_type ?? '');
    if ($mime !== '') {
        $objects[] = 'attachment:' . $mime;
        foreach (array_filter(explode('/', $mime)) as $half) {
            $objects[] = "attachment:{$half}";
        }
    }
    $taxonomies = [];
    foreach ($objects as $object) {
        $taxonomies = array_merge($taxonomies, (array) get_object_taxonomies($object, $output));
    }
    return $output === 'names' ? array_values(array_unique($taxonomies)) : $taxonomies;
}

function register_taxonomy($taxonomy, $object_type, $args = [])
{
    $taxonomy = (string) $taxonomy;
    if ($taxonomy === '' || strlen($taxonomy) > 32) {
        _doing_it_wrong(__FUNCTION__, 'Taxonomy names must be between 1 and 32 characters in length.', '4.2.0');
        return new WP_Error('taxonomy_length_invalid', 'Taxonomy names must be between 1 and 32 characters in length.');
    }
    // The object does each step itself, in the reference's order (probe type-object-methods).
    $object = new WP_Taxonomy($taxonomy, $object_type, $args);
    $object->add_rewrite_rules();
    Runtime::registry()->adoptTaxonomy($object);
    $object->add_hooks();
    if (!empty($object->default_term)) {
        _minn_default_term($taxonomy, $object->default_term);
    }
    do_action('registered_taxonomy', $taxonomy, $object_type, get_object_vars($object));
    do_action("registered_taxonomy_{$taxonomy}", $taxonomy, $object_type, get_object_vars($object));
    return $object;
}

/**
 * @internal A taxonomy's default term, settled as it registers (probe
 * taxonomy-default-term): nothing while default_term_{taxonomy} names a term
 * that is there; otherwise a term of that name is kept, or the term is made,
 * and its id stored.
 */
function _minn_default_term(string $taxonomy, array $term): void
{
    $stored = (int) get_option("default_term_{$taxonomy}");
    if ($stored > 0 && term_exists($stored, $taxonomy)) {
        return;
    }
    $found = term_exists((string) $term['name'], $taxonomy);
    if (!is_array($found)) {
        $found = wp_insert_term((string) $term['name'], $taxonomy, ['slug' => (string) $term['slug'], 'description' => (string) $term['description']]);
    }
    if (is_array($found)) {
        update_option("default_term_{$taxonomy}", $found['term_id']);
    }
}

function unregister_taxonomy($taxonomy)
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', __('Invalid taxonomy.'));
    }
    if (!empty(Runtime::registry()->taxonomy((string) $taxonomy)['_builtin'])) {
        return new WP_Error('invalid_taxonomy', 'Unregistering a built-in taxonomy is not allowed.');
    }
    $object = get_taxonomy($taxonomy);
    $object->remove_rewrite_rules();
    $object->remove_hooks();
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

/** A taxonomy's full labels from its own, then taxonomy_labels_{$name} (Runtime\Registry::taxonomyLabels; probe taxonomy-remaining). */
function get_taxonomy_labels($tax)
{
    $name = (string) ($tax->name ?? '');
    $labels = Runtime::registry()->taxonomyLabels((array) ($tax->labels ?? []), isset($tax->label) ? (string) $tax->label : null, empty($tax->hierarchical) ? 'post_tag' : 'category', $name);
    return apply_filters("taxonomy_labels_{$name}", (object) $labels);
}

/** @internal a fetched term's numbers as integers: its id, group, taxonomy id, parent and count */
function _minn_term_numbers(WP_Term $term): WP_Term
{
    foreach (['term_id', 'term_group', 'term_taxonomy_id', 'parent', 'count'] as $field) {
        $term->{$field} = (int) $term->{$field};
    }
    return $term;
}

function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw')
{
    return Minn\Runtime\Records::term($term, $taxonomy, $output, $filter);
}

function get_term_by($field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw')
{
    return Minn\Runtime\TermLookup::by($field, $value, $taxonomy, $output, $filter);
}

/**
 * A term by id, or by slug and then by name as saving would store it
 * (probe term-lookup): a digit string is a name, a parent narrows both
 * lookups (0 to the top level) and a parent with no children finds
 * nothing. The ids come back as strings, the term_id alone without a
 * taxonomy.
 */
function term_exists($term, $taxonomy = '', $parent_term = null)
{
    return Minn\Runtime\TermLookup::exists($term, $taxonomy, $parent_term);
}

/** @internal term_exists() for a string: the slug it makes, then the name as saved */
function _minn_term_lookup(string $term, ?string $taxonomy, ?int $parent): ?array
{
    $query = _minn_term_query();
    if ($term === '' || ($parent !== null && $parent > 0 && !$query->hasChildren($parent, [$taxonomy]))) {
        return null;
    }
    $slug = sanitize_title($term);
    $found = $slug === '' ? null : $query->lookup('slug', $slug, $taxonomy, $parent);
    return $found ?? $query->lookup('name', wp_unslash((string) sanitize_term_field('name', $term, 0, $taxonomy ?? false, 'db')), $taxonomy, $parent);
}

function get_terms($args = [], $deprecated = '')
{
    return Minn\Runtime\TermLookup::terms($args, $deprecated);
}

/** Categories (or another taxonomy's terms, through get_categories_taxonomy) as get_terms finds them, each given the old category fields. */
function get_categories($args = '')
{
    $args = wp_parse_args($args, ['taxonomy' => 'category']);
    $args['taxonomy'] = apply_filters('get_categories_taxonomy', $args['taxonomy'], $args);
    if (($args['type'] ?? '') === 'link') {
        _deprecated_argument(__FUNCTION__, '3.0.0', sprintf(__('%1$s is deprecated. Use %2$s instead.'), '<code>type => link</code>', '<code>taxonomy => link_category</code>'));
        $args['taxonomy'] = 'link_category';
    }
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

/** A category's old field names beside its term fields, on a term object or a term array alike (probe admin-terms). */
function _make_cat_compat(&$category)
{
    Minn\Runtime\TermTags::categoryFields($category);
}

function get_term_link($term, $taxonomy = '')
{
    return Minn\Runtime\TermLookup::link($term, $taxonomy);
}

/**
 * @internal a term's link by query: a category by its id, any other term by
 * its taxonomy's query var, or by taxonomy and term where it has none
 */
function _minn_term_query_link(WP_Term $term): string
{
    return Minn\Runtime\TermLookup::queryLink($term);
}

function get_category_link($category)
{
    return Minn\Runtime\TermTags::categoryLink($category);
}

function get_tag_link($tag)
{
    $term = $tag instanceof WP_Term ? $tag : get_term((int) $tag, 'post_tag');
    return $term instanceof WP_Term ? get_term_link($term) : '';
}

function wp_get_object_terms($object_ids, $taxonomies, $args = [])
{
    return Minn\Runtime\TermLookup::objectTerms($object_ids, $taxonomies, $args);
}

function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false)
{
    return Minn\Runtime\TermWrites::setObjectTerms($object_id, $terms, $taxonomy, $append);
}

function wp_remove_object_terms($object_id, $terms, $taxonomy)
{
    return Minn\Runtime\TermWrites::removeObjectTerms($object_id, $terms, $taxonomy);
}

function wp_add_object_terms($object_id, $terms, $taxonomy)
{
    return wp_set_object_terms($object_id, $terms, $taxonomy, true);
}

function get_the_terms($post, $taxonomy)
{
    return Minn\Runtime\TermTags::ofPost($post, $taxonomy);
}

function get_the_category($post_id = false)
{
    return Minn\Runtime\TermTags::categories($post_id);
}

function get_the_tags($post = 0)
{
    return Minn\Runtime\TermTags::tags($post);
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

/** A new term, its fields through their filters in the reference's order (Runtime\TermSave). */
function wp_insert_term($term, $taxonomy, $args = [])
{
    return Minn\Runtime\TermWrites::insert($term, $taxonomy, $args);
}

/** A term changed, its fields through their filters in the reference's order (Runtime\TermSave). */
function wp_update_term($term_id, $taxonomy, $args = [])
{
    return Minn\Runtime\TermWrites::update($term_id, $taxonomy, $args);
}

function wp_delete_term($term, $taxonomy, $args = [])
{
    return Minn\Runtime\TermWrites::delete($term, $taxonomy, $args);
}

function wp_delete_category($cat_id)
{
    return wp_delete_term($cat_id, 'category');
}

// The term helpers importers call (probe admin-terms).

/** A category's id by name or slug (under a parent when one is given), as a string; null when there is none. */
function category_exists($cat_name, $category_parent = null)
{
    $id = term_exists($cat_name, 'category', $category_parent);
    return is_array($id) ? $id['term_id'] : $id;
}

/** A tag's term_id and term_taxonomy_id, or null. */
function tag_exists($tag_name)
{
    return term_exists($tag_name, 'post_tag');
}

/** A category by name, made when it is not there; its id. */
function wp_create_category($category_name, $category_parent = 0)
{
    $id = category_exists($category_name, $category_parent);
    return $id ? (int) $id : wp_insert_category(['cat_name' => $category_name, 'category_parent' => $category_parent]);
}

/** Categories by name, each found or made; given a post, they become its categories. */
function wp_create_categories($categories, $post_id = 0)
{
    $ids = [];
    foreach ((array) $categories as $category) {
        $id = category_exists($category) ?: wp_create_category($category);
        if ($id) {
            $ids[] = $id;
        }
    }
    if ($post_id) {
        wp_set_post_categories($post_id, $ids);
    }
    return $ids;
}

/**
 * A category (or another taxonomy's term) made, or updated when cat_ID
 * names one: its id, 0 on failure, or the error when asked for. A parent
 * that is not there, or that is the term itself or below it, becomes none.
 */
function wp_insert_category($catarr, $wp_error = false)
{
    $catarr = wp_parse_args($catarr, ['cat_ID' => 0, 'taxonomy' => 'category', 'cat_name' => '', 'category_description' => '', 'category_nicename' => '', 'category_parent' => '']);
    if (trim((string) $catarr['cat_name']) === '') {
        return $wp_error ? new WP_Error('cat_name', __('You did not enter a category name.')) : 0;
    }
    $id = (int) $catarr['cat_ID'];
    $taxonomy = (string) $catarr['taxonomy'];
    $parent = max(0, (int) $catarr['category_parent']);
    if ($parent && (!term_exists($parent, $taxonomy) || ($id && term_is_ancestor_of($id, $parent, $taxonomy)))) {
        $parent = 0;
    }
    $args = ['name' => $catarr['cat_name'], 'slug' => $catarr['category_nicename'], 'parent' => $parent, 'description' => $catarr['category_description']];
    $saved = $id ? wp_update_term($id, $taxonomy, $args) : wp_insert_term($catarr['cat_name'], $taxonomy, $args);
    if (is_wp_error($saved)) {
        return $wp_error ? $saved : 0;
    }
    return (int) $saved['term_id'];
}

/** A category's fields changed, the rest kept; false when it would be its own parent. */
function wp_update_category($catarr)
{
    $id = (int) ($catarr['cat_ID'] ?? 0);
    if (isset($catarr['category_parent']) && $id === (int) $catarr['category_parent']) {
        return false;
    }
    $category = get_term($id, 'category', ARRAY_A);
    _make_cat_compat($category);
    return wp_insert_category(array_merge(wp_slash((array) $category), $catarr));
}

/** A term by name in a taxonomy, made when it is not there: its term_id and term_taxonomy_id. */
function wp_create_term($tag_name, $taxonomy = 'post_tag')
{
    return term_exists($tag_name, $taxonomy) ?: wp_insert_term($tag_name, $taxonomy);
}

function wp_create_tag($tag_name)
{
    return wp_create_term($tag_name, 'post_tag');
}

/** A post's terms in one taxonomy as the comma-separated names an edit field shows (through terms_to_edit); false when it has none. */
function get_terms_to_edit($post_id, $taxonomy = 'post_tag')
{
    $terms = get_object_term_cache((int) $post_id, $taxonomy);
    if ($terms === false) {
        $terms = wp_get_object_terms((int) $post_id, $taxonomy);
    }
    if (!$terms) {
        return false;
    }
    if (is_wp_error($terms)) {
        return $terms;
    }
    $names = esc_attr(implode(',', array_map(static fn ($term) => $term->name, $terms)));
    return apply_filters('terms_to_edit', $names, $taxonomy);
}

function get_tags_to_edit($post_id, $taxonomy = 'post_tag')
{
    return get_terms_to_edit($post_id, $taxonomy);
}

function get_term_children($term_id, $taxonomy)
{
    return Minn\Runtime\TermLookup::children($term_id, $taxonomy);
}

function get_objects_in_term($term_ids, $taxonomies, $args = [])
{
    $taxonomies = array_values(array_map('strval', (array) $taxonomies));
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', __('Invalid taxonomy.'));
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
    Minn\Runtime\TermLookup::cleanCache($ids, $taxonomy, $clean_taxonomy);
}

function clean_object_term_cache($object_ids, $object_type)
{
    Minn\Content\RowCache::forget('post_terms');
    foreach ((array) $object_ids as $id) {
        wp_cache_delete((int) $id, 'post_meta');
    }
    do_action('clean_object_term_cache', (array) $object_ids, $object_type);
}

function wp_update_term_count($terms, $taxonomy, $do_deferred = false)
{
    return Minn\Runtime\TermWrites::updateCount($terms, $taxonomy, $do_deferred);
}

/** Recounts terms by term_taxonomy id through the taxonomy's count callback, then lets their caches go. */
function wp_update_term_count_now($terms, $taxonomy)
{
    return Minn\Runtime\TermWrites::updateCountNow($terms, $taxonomy);
}

/** Whether term counts are put off (Runtime\Deferrals); turning it off counts what was. */
function wp_defer_term_counting($defer = null)
{
    return Deferrals::terms($defer);
}

/** A field of a term in a context (display by default); '' for a field it does not have. */
function get_term_field($field, $term, $taxonomy = '', $context = 'display')
{
    return Minn\Runtime\TermLookup::field($field, $term, $taxonomy, $context);
}

/** Each field of a term (object or array) in a context, through the field filters; the term says which context it is in. */
function sanitize_term($term, $taxonomy, $context = 'display')
{
    return TermFields::term($term, (string) $taxonomy, (string) $context);
}

/** One field of a term in a context: raw, edit, db (saving), display, attribute, js or rss. */
function sanitize_term_field($field, $value, $term_id, $taxonomy, $context)
{
    return TermFields::field((string) $field, $value, (int) $term_id, $taxonomy === false ? false : (string) $taxonomy, (string) $context);
}

function wp_cache_set_terms_last_changed()
{
    wp_cache_set('last_changed', microtime(), 'terms');
}

/**
 * A term two taxonomies share, split (probe hook-inputs): the taxonomy row
 * named gets a term of its own, its children follow, split_shared_term
 * says so, and the split is recorded for wp_get_split_term(); a term held
 * by one taxonomy keeps its id.
 */
function _split_shared_term($term_id, $term_taxonomy_id, $record = true)
{
    $term_id = (int) (is_object($term_id) ? $term_id->term_id : $term_id);
    $term_taxonomy_id = (int) (is_object($term_taxonomy_id) ? $term_taxonomy_id->term_taxonomy_id : $term_taxonomy_id);
    $split = _minn_term_writer()->split($term_id, $term_taxonomy_id);
    if ($split === null) {
        return $term_id;
    }
    [$new_term_id, $taxonomy, $children] = $split;
    clean_term_cache([$term_id, $new_term_id, ...$children], $taxonomy);
    do_action('split_shared_term', $term_id, $new_term_id, $term_taxonomy_id, $taxonomy);
    if ($record) {
        $splits = get_option('_split_terms', []);
        $splits = is_array($splits) ? $splits : [];
        $splits[$term_id][$taxonomy] = $new_term_id;
        update_option('_split_terms', $splits);
    }
    return $new_term_id;
}

/** A term made ready for an edit form, '' for none (probe taxonomy-remaining). */
function get_term_to_edit($id, $taxonomy)
{
    $term = get_term($id, $taxonomy);
    if (is_wp_error($term)) {
        return $term;
    }
    return is_object($term) ? sanitize_term($term, $taxonomy, 'edit') : '';
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
    return Minn\Runtime\NavMenu::menus($args);
}

function wp_get_nav_menu_object($menu)
{
    return Minn\Runtime\NavMenu::menu($menu);
}

function is_nav_menu($menu)
{
    return wp_get_nav_menu_object($menu) !== false;
}

/** A menu's items, set up as walkers read them (Runtime\NavMenuItems); false for no menu. */
function wp_get_nav_menu_items($menu, $args = [])
{
    return Minn\Runtime\NavMenu::items($menu, $args);
}

/** Whether a post is a menu item; an error is not. */
function is_nav_menu_item($menu_item_id = 0)
{
    return !is_wp_error($menu_item_id) && get_post_type($menu_item_id) === 'nav_menu_item';
}

/** A menu item, post or term dressed with the fields walkers read (Runtime\NavMenuItems). */
function wp_setup_nav_menu_item($menu_item)
{
    return Minn\Runtime\NavMenuItems::prepare($menu_item);
}

function is_object_in_taxonomy($object_type, $taxonomy)
{
    return Minn\Runtime\Registered::objectInTaxonomy($object_type, $taxonomy);
}

/**
 * A slug no other term of the taxonomy holds (probe term-insert-filters):
 * one taken in the taxonomy gets its parents' slugs added, nearest first,
 * until it is free (wp_unique_term_slug_is_bad_slug decides whether it was
 * taken), then "-2", "-3" past any term holding it in any taxonomy.
 */
function wp_unique_term_slug($slug, $term)
{
    return Minn\Runtime\TermWrites::uniqueSlug($slug, $term);
}

/** The default on wp_update_term_parent: no term may sit under itself, however far down; such a parent becomes 0. */
function wp_check_term_hierarchy_for_loops($parent_term, $term_id, $taxonomy)
{
    $parent = (int) $parent_term;
    $term_id = (int) $term_id;
    $seen = [];
    for ($at = $parent; $at > 0 && !isset($seen[$at]); $at = (int) (_minn_term_row($at, (string) $taxonomy)['parent'] ?? 0)) {
        if ($at === $term_id) {
            return 0;
        }
        $seen[$at] = true;
    }
    return $parent;
}

/** Recounts the published objects behind each term_taxonomy id. */
function _update_post_term_count($terms, $taxonomy)
{
    Minn\Runtime\TermWrites::countPublished($terms, $taxonomy);
}

/** Recounts every object behind each term_taxonomy id, posts or not. */
function _update_generic_term_count($terms, $taxonomy)
{
    Minn\Runtime\TermWrites::countAll($terms, $taxonomy);
}

/** The default on transition_post_status: a post that changes status changes its terms' published counts. */
/**
 * The default on transition_post_status: each of the type's taxonomies is
 * asked which statuses it counts (update_post_term_count_statuses), and a
 * move into or out of one recounts the post's terms there (probe hook-inputs).
 */
function _update_term_count_on_transition_post_status($new_status, $old_status, $post)
{
    if ($new_status === $old_status) {
        return;
    }
    foreach ((array) get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
        $counted = (array) apply_filters('update_post_term_count_statuses', ['publish'], $taxonomy);
        if (!in_array($new_status, $counted, true) && !in_array($old_status, $counted, true)) {
            continue;
        }
        $tt_ids = wp_get_object_terms($post->ID, $taxonomy->name, ['fields' => 'tt_ids']);
        if (!is_wp_error($tt_ids)) {
            wp_update_term_count($tt_ids, $taxonomy->name);
        }
    }
}

/** The default on transition_post_status: whether the site has more than one author is asked again. */
function __clear_multi_author_cache()
{
    delete_transient('is_multi_author');
}

/** Term meta per id, as update_meta_cache primes it; false for an empty list. */
function update_termmeta_cache($term_ids)
{
    return update_meta_cache('term', $term_ids);
}

/** The category's name, or "" for an id no category has. */
function get_the_category_by_ID($cat_id)
{
    $term = get_term((int) $cat_id, 'category');
    return $term instanceof WP_Term ? (string) $term->name : '';
}

/** The queried category's name, with a prefix, printed or returned; null off a category archive. */
function single_cat_title($prefix = '', $display = true)
{
    return single_term_title($prefix, $display);
}

/** The queried term's name after a prefix, printed or returned; null off a term archive. */
function single_term_title($prefix = '', $display = true)
{
    return Minn\Runtime\DocumentTitle::term($prefix, $display);
}

/**
 * The category list (probe plugin-queue4): a titled list item around the
 * categories walked by walk_category_tree, nested when hierarchical; the
 * "all" link and the none message when asked; the category being viewed
 * marked current. False for a taxonomy that is not there.
 */
function wp_list_categories($args = '')
{
    $given = wp_parse_args($args);
    $r = wp_parse_args($args, ['child_of' => 0, 'current_category' => 0, 'depth' => 0, 'echo' => 1, 'exclude' => '', 'exclude_tree' => '', 'feed' => '', 'feed_image' => '', 'feed_type' => '', 'hide_empty' => 1, 'hide_title_if_empty' => false, 'hierarchical' => true, 'order' => 'ASC', 'orderby' => 'name', 'separator' => '<br />', 'show_count' => 0, 'show_option_all' => '', 'show_option_none' => __('No categories'), 'style' => 'list', 'taxonomy' => 'category', 'title_li' => __('Categories'), 'use_desc_for_title' => 0]);
    if (!isset($given['pad_counts']) && $r['show_count'] && $r['hierarchical']) {
        $r['pad_counts'] = true;
    }
    if ($r['hierarchical']) {
        $r['exclude_tree'] = array_merge(wp_parse_id_list($r['exclude_tree']), wp_parse_id_list($r['exclude']));
        $r['exclude'] = '';
    }
    $r['class'] ??= $r['taxonomy'] === 'category' ? 'categories' : $r['taxonomy'];
    if (!taxonomy_exists($r['taxonomy'])) {
        return false;
    }
    $categories = get_categories($r);
    $titled = $r['title_li'] && $r['style'] === 'list' && ($categories !== [] || !$r['hide_title_if_empty']);
    $output = ($titled ? '<li class="' . esc_attr($r['class']) . '">' . $r['title_li'] . '<ul>' : '') . _minn_category_list_body($categories, $r) . ($titled ? '</ul></li>' : '');
    $html = apply_filters('wp_list_categories', $output, $args);
    if (!$r['echo']) {
        return $html;
    }
    echo $html;
}

/** @internal the list's items: the none message, or the "all" link and the categories walked, the viewed one current */
function _minn_category_list_body(array $categories, array $r): string
{
    $list = $r['style'] === 'list';
    if ($categories === []) {
        return empty($r['show_option_none']) ? '' : ($list ? '<li class="cat-item-none">' . $r['show_option_none'] . '</li>' : $r['show_option_none']);
    }
    $output = '';
    if (!empty($r['show_option_all'])) {
        $all = esc_url(get_option('show_on_front') === 'page' && get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : home_url('/'));
        $output = $list ? "<li class='cat-item-all'><a href='{$all}'>{$r['show_option_all']}</a></li>" : "<a href='{$all}'>{$r['show_option_all']}</a>" . $r['separator'];
    }
    if (empty($r['current_category']) && (is_category() || is_tax() || is_tag())) {
        $viewed = get_queried_object();
        $r['current_category'] = $viewed && ($viewed->taxonomy ?? null) === $r['taxonomy'] ? get_queried_object_id() : 0;
    }
    return $output . walk_category_tree($categories, $r['hierarchical'] ? $r['depth'] : -1, $r);
}

/** The categories walked as a list, by the caller's walker or Walker_Category. */
function walk_category_tree(...$args)
{
    $walker = ($args[2]['walker'] ?? null) instanceof Walker ? $args[2]['walker'] : new Walker_Category();
    return $walker->walk(...$args);
}

/** The categories walked as options, by the caller's walker or Walker_CategoryDropdown. */
function walk_category_dropdown_tree(...$args)
{
    $walker = ($args[2]['walker'] ?? null) instanceof Walker ? $args[2]['walker'] : new Walker_CategoryDropdown();
    return $walker->walk(...$args);
}

/** Whether one category is above another. */
function cat_is_ancestor_of($cat1, $cat2)
{
    return term_is_ancestor_of($cat1, $cat2, 'category');
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
    // As the reference makes it (probe filter-inputs): the tags through tag_cloud_sort first, a plugin's order kept as
    // it comes back (else the cloud's own sort), then each tag's entry through wp_generate_tag_cloud_data.
    $sorted = apply_filters('tag_cloud_sort', $tags, $args);
    $rows = [];
    foreach ((array) $sorted as $key => $tag) {
        $rows[$key] = _minn_tag_cloud_row($tag, $args);
    }
    $rows = $sorted !== $tags ? ((int) $args['number'] > 0 ? array_slice($rows, 0, (int) $args['number']) : $rows) : TermLists::cloudTags($rows, $args);
    $out = $rows === [] ? null : TermLists::cloudMarkup((array) apply_filters('wp_generate_tag_cloud_data', TermLists::cloudData($rows, $args)), $args);
    if ($args['format'] === 'array') {
        $out = $out === null ? [] : explode("\n", $out);
    }
    $out ??= '';
    return $args['filter'] ? apply_filters('wp_generate_tag_cloud', $out, $tags, $args) : $out;
}

/**
 * @internal One tag as the cloud reads it (probe template-tags-remaining):
 * its own id if it has one (else the cloud uses its key), its count
 * through the scale callback, the count as words for the label (the text
 * callback, else the nooped plural, "%s item" by default), and its link
 * escaped as a URL.
 */
function _minn_tag_cloud_row($tag, array $args): array
{
    $count = (int) ($tag->count ?? 0);
    $text = is_callable($args['topic_count_text_callback'])
        ? call_user_func($args['topic_count_text_callback'], $count, $tag, $args)
        : sprintf(translate_nooped_plural(is_array($args['topic_count_text']) ? $args['topic_count_text'] : _n_noop('%s item', '%s items'), $count), number_format_i18n($count));
    return ['id' => $tag->id ?? null, 'scaled' => call_user_func($args['topic_count_scale_callback'], $count), 'text' => (string) $text, 'link' => esc_url((string) ($tag->link ?? ''))] + (array) $tag;
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

/**
 * The category select (probe plugin-queue4): the "all" and none choices
 * (through list_cats, unescaped), then the categories walked by
 * walk_category_dropdown_tree, flat unless hierarchical; through
 * wp_dropdown_cats. Nothing at all when empty and hide_if_empty is set.
 */
function wp_dropdown_categories($args = '')
{
    $r = wp_parse_args($args, ['show_option_all' => '', 'show_option_none' => '', 'orderby' => 'id', 'order' => 'ASC', 'show_count' => 0, 'hide_empty' => 1, 'child_of' => 0, 'exclude' => '', 'echo' => 1, 'selected' => 0, 'hierarchical' => 0, 'name' => 'cat', 'id' => '', 'class' => 'postform', 'depth' => 0, 'tab_index' => 0, 'taxonomy' => 'category', 'hide_if_empty' => false, 'option_none_value' => -1, 'value_field' => 'term_id', 'required' => false, 'aria_describedby' => '']);
    $query = $r;
    unset($query['name']);
    $categories = get_terms($query);
    $categories = is_array($categories) ? $categories : [];
    $shown = !$r['hide_if_empty'] || $categories !== [];
    $name = esc_attr($r['name']);
    $output = $shown ? '<select ' . ($r['required'] ? 'required' : '') . " name='{$name}' id='" . ($r['id'] ? esc_attr($r['id']) : $name) . "' class='" . esc_attr($r['class']) . "'"
        . ((int) $r['tab_index'] > 0 ? " tabindex=\"{$r['tab_index']}\"" : '') . ($r['aria_describedby'] ? ' aria-describedby="' . esc_attr($r['aria_describedby']) . '"' : '') . ">\n" : '';
    if ($categories === [] && $shown && !empty($r['show_option_none'])) {
        $output .= "\t<option value='" . esc_attr($r['option_none_value']) . "' selected='selected'>" . apply_filters('list_cats', $r['show_option_none'], null) . "</option>\n";
    }
    if ($categories !== []) {
        if ($r['show_option_all']) {
            $output .= "\t<option value='0'" . ((string) $r['selected'] === '0' ? " selected='selected'" : '') . '>' . apply_filters('list_cats', $r['show_option_all'], null) . "</option>\n";
        }
        if ($r['show_option_none']) {
            $output .= "\t<option value='" . esc_attr($r['option_none_value']) . "'" . selected($r['option_none_value'], $r['selected'], false) . '>' . apply_filters('list_cats', $r['show_option_none'], null) . "</option>\n";
        }
        $output .= walk_category_dropdown_tree($categories, $r['hierarchical'] ? $r['depth'] : -1, $r);
    }
    $output = apply_filters('wp_dropdown_cats', $output . ($shown ? "</select>\n" : ''), $r);
    if ($r['echo']) {
        echo $output;
    }
    return $output;
}

/** Every descendant of one term within the caller's own list (ids or term objects), preorder; ancestors guard against loops. */
function _get_term_children($term_id, $terms, $taxonomy, &$ancestors = [])
{
    return Minn\Runtime\TermLookup::descendants($term_id, $terms, $taxonomy, $ancestors);
}

/** Whether the object has one of the terms (ids, slugs, or names), or any term of the taxonomy at all. */
function is_object_in_term($object_id, $taxonomy, $terms = null)
{
    if (!taxonomy_exists((string) $taxonomy)) {
        return new WP_Error('invalid_taxonomy', __('Invalid taxonomy.'));
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

/** The post's category list as get_the_category_list builds it, printed. */
function the_category($separator = '', $parents = '', $post_id = false)
{
    echo get_the_category_list($separator, $parents, $post_id);
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
    $categories = apply_filters('the_category_list', get_the_category($post_id), $post_id);
    if (empty($categories)) {
        return apply_filters('the_category', '', $separator, $parents);
    }
    $rows = [];
    foreach ($categories as $category) {
        $rows[] = _minn_category_list_row($category, (string) $separator, (string) $parents);
    }
    return apply_filters('the_category', TermLinks::categories($rows, (string) $separator), $separator, $parents);
}

/**
 * @internal One category in the list (probe template-tags-remaining): its
 * parents linked ahead of it ("multiple") or named inside its own link
 * ("single", where the reference's list item has two spaces before rel).
 */
function _minn_category_list_row(WP_Term $category, string $separator, string $parents): array
{
    $row = ['name' => $category->name, 'url' => (string) get_category_link($category->term_id)];
    $chain = static function (bool $link) use ($category, $separator): string {
        $line = $category->parent ? get_category_parents($category->parent, $link, $separator) : '';
        return is_string($line) ? $line : '';
    };
    return match ($parents) {
        'multiple' => $row + ['before' => $chain(true)],
        'single' => ['name' => $chain(false) . $category->name, 'space' => $separator === '' ? '  ' : ' '] + $row,
        default => $row,
    };
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

/** A menu made (id 0) or changed, as the reference saves one (Runtime\MenuEvents): its id or the refusal. */
function wp_update_nav_menu_object($menu_id = 0, $menu_data = [])
{
    return _minn_menu_events()->saveMenu((int) $menu_id, (array) $menu_data);
}

/** A menu deleted with its items, as the reference deletes one (Runtime\MenuEvents). */
function wp_delete_nav_menu($menu)
{
    $object = wp_get_nav_menu_object($menu);
    if (!$object) {
        return false;
    }
    _minn_menu_events()->deleteMenu((int) $object->term_id);
    return true;
}

/** A menu item made (id 0) or changed, as the reference saves one (Runtime\MenuEvents): its id or the refusal. */
/** A menu item that names itself as its parent (ID and parent equal, loosely, and set) gets no parent. Any other parent is kept. */
function _wp_reset_invalid_menu_item_parent($menu_item_data)
{
    if (!empty($menu_item_data['ID']) && !empty($menu_item_data['menu_item_parent']) && (int) $menu_item_data['ID'] === (int) $menu_item_data['menu_item_parent']) {
        $menu_item_data['menu_item_parent'] = 0;
    }
    return $menu_item_data;
}

function wp_update_nav_menu_item($menu_id = 0, $menu_item_db_id = 0, $menu_item_data = [], $fire_after_hooks = true)
{
    return _minn_menu_events()->saveItem((int) $menu_id, (int) $menu_item_db_id, (array) $menu_item_data, $fire_after_hooks ? 'now' : 'later');
}

/** @internal the menu writes that tell plugins */
function _minn_menu_events(): Minn\Runtime\MenuEvents
{
    return new Minn\Runtime\MenuEvents(_minn_menus(), _minn_posts());
}

function _get_term_hierarchy($taxonomy)
{
    return Minn\Runtime\TermLookup::hierarchy($taxonomy);
}

/** Lets go of what a taxonomy keeps about all its terms: the children map is dropped and built again. */
function clean_taxonomy_cache($taxonomy)
{
    Minn\Runtime\TermLookup::cleanTaxonomy($taxonomy);
}

function term_is_ancestor_of($term1, $term2, $taxonomy)
{
    $ancestor = is_object($term1) ? (int) $term1->term_id : (int) $term1;
    $child = is_object($term2) ? (int) $term2->term_id : (int) $term2;
    return in_array($ancestor, array_map('intval', get_ancestors($child, (string) $taxonomy, 'taxonomy')), true);
}

function get_category_parents($category_id, $link = false, $separator = '/', $nicename = false, $deprecated = [])
{
    return Minn\Runtime\TermTags::categoryParents($category_id, $link, $separator, $nicename, $deprecated);
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
    return Minn\Runtime\TermTags::cached($id, $taxonomy);
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

/** The default on delete_post: the menu items that point at a deleted post go with it. */
function _wp_delete_post_menu_item($object_id)
{
    foreach (_minn_menus()->itemsPointingAt((int) $object_id, 'post_type') as $item_id) {
        wp_delete_post($item_id, true);
    }
}

/** The default on transition_post_status: a top-level page that is published joins every menu set to add new pages. */
function _wp_auto_add_pages_to_menu($new_status, $old_status, $post)
{
    if ($new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'page' || (int) $post->post_parent !== 0) {
        return;
    }
    $options = get_option('nav_menu_options');
    foreach ((array) ($options['auto_add'] ?? []) as $menu_id) {
        if (is_nav_menu($menu_id)) {
            _minn_menus()->appendPage((int) $menu_id, (int) $post->ID, get_current_user_id());
        }
    }
}

/** The term a shared term was split into, from the _split_terms record a pre-4.2 site may carry; false when none. */
function wp_get_split_term($old_term_id, $taxonomy)
{
    $splits = get_option('_split_terms', []);
    return is_array($splits) ? ($splits[(int) $old_term_id][(string) $taxonomy] ?? false) : false;
}

/** Every taxonomy's new id for a term split from a shared one, from the _split_terms record; [] when it was never split. */
function wp_get_split_terms($old_term_id)
{
    $splits = get_option('_split_terms', []);
    return is_array($splits) ? ($splits[(int) $old_term_id] ?? []) : [];
}

/** Whether a term's taxonomy can be viewed publicly; false for no term. */
function is_term_publicly_viewable($term)
{
    $term = get_term($term);
    return $term instanceof WP_Term && is_taxonomy_viewable($term->taxonomy);
}

/** Prints the post's taxonomies (get_the_taxonomies) between before and after, joined by sep. */
function the_taxonomies($args = [])
{
    $args = wp_parse_args($args, ['post' => 0, 'before' => '', 'sep' => ' ', 'after' => '']);
    echo $args['before'] . implode($args['sep'], get_the_taxonomies($args['post'], $args)) . $args['after'];
}

/** A term's meta rows (meta_key, meta_value, meta_id, term_id as strings) by key then id; false on a database older than term meta. */
function has_term_meta($term_id)
{
    $check = wp_check_term_meta_support_prefilter(null);
    return $check !== null ? $check : _minn_meta()->listing('term', (int) $term_id);
}

/** The term meta short-circuit: false on a database older than term meta (db_version 34370), the check as given otherwise. */
function wp_check_term_meta_support_prefilter($check)
{
    return (int) get_option('db_version') < 34370 ? false : $check;
}

function single_tag_title($prefix = '', $display = true)
{
    return single_term_title($prefix, $display);
}

function _pad_term_counts(&$terms, $taxonomy)
{
    Minn\Runtime\TermQueryTree::pad($terms, (string) $taxonomy);
}

/** Nothing to queue: term meta loads when it is first read. */
/** Queues terms so the first read of one's meta loads them all. */
function wp_lazyload_term_meta(array $term_ids)
{
    wp_metadata_lazyloader()->queue_objects('term', $term_ids);
}

/** Prints a post's terms as links between before and after, through the_terms; false for a taxonomy that does not exist. */
function the_terms($post_id, $taxonomy, $before = '', $sep = ', ', $after = '')
{
    $list = get_the_term_list($post_id, $taxonomy, $before, $sep, $after);
    if (is_wp_error($list)) {
        return false;
    }
    echo apply_filters('the_terms', $list, $taxonomy, $before, $sep, $after);
}

/** A term's parent in a taxonomy (0 at the top), or false when the term is not in it. */
function wp_get_term_taxonomy_parent_id($term_id, $taxonomy)
{
    $term = get_term((int) $term_id, $taxonomy);
    return $term instanceof WP_Term ? (int) $term->parent : false;
}

/** Whether a term id is shared between taxonomies: never once the site has split its shared terms. */
function wp_term_is_shared($term_id)
{
    if (get_option('finished_splitting_shared_terms')) {
        return false;
    }
    return count(get_terms(['include' => [(int) $term_id], 'hide_empty' => false, 'fields' => 'tt_ids', 'taxonomy' => array_keys(get_taxonomies())])) > 1;
}

/** Terms keep no cache here to fill. */
function update_term_cache($terms, $taxonomy = '')
{
}

/** Deprecated since 4.0: every category's id. */
function get_all_category_ids()
{
    _deprecated_function(__FUNCTION__, '4.0.0', 'get_terms()');
    return get_terms(['taxonomy' => 'category', 'fields' => 'ids', 'get' => 'all']);
}

/** The JOIN and WHERE a tax query adds (WP_Tax_Query). */
function get_tax_sql($tax_query, $primary_table, $primary_id_column)
{
    return (new WP_Tax_Query($tax_query))->get_sql($primary_table, $primary_id_column);
}

/**
 * A category by its path of slugs (probe category-menu-helpers): each part
 * read as a slug, the categories carrying the last one tried in turn, the
 * one whose own path of ancestors matches returned; without a full match,
 * the first of them when full_match is off.
 */
function get_category_by_path($category_path, $full_match = true, $output = OBJECT)
{
    $parts = array_values(array_filter(array_map('sanitize_title', explode('/', trim(urldecode((string) $category_path), '/'))), static fn ($part) => $part !== ''));
    if ($parts === []) {
        return null;
    }
    $candidates = get_terms(['taxonomy' => 'category', 'get' => 'all', 'slug' => end($parts), 'hide_empty' => false]);
    $candidates = is_array($candidates) ? $candidates : [];
    foreach ($candidates as $candidate) {
        $path = array_reverse(array_map(static fn ($id) => get_term((int) $id, 'category')->slug ?? '', get_ancestors((int) $candidate->term_id, 'category', 'taxonomy')));
        if ([...$path, $candidate->slug] === $parts) {
            return get_category((int) $candidate->term_id, $output);
        }
    }
    return !$full_match && $candidates !== [] ? get_category((int) $candidates[0]->term_id, $output) : null;
}

function sanitize_category($category, $context = 'display')
{
    return sanitize_term($category, 'category', $context);
}

function sanitize_category_field($field, $value, $cat_id, $context)
{
    return sanitize_term_field($field, $value, $cat_id, 'category', $context);
}

function clean_category_cache($id)
{
    clean_term_cache($id, 'category');
}

/** The tag cloud's order by count, fewest first. */
function _wp_object_count_sort_cb($a, $b)
{
    return $a->count - $b->count;
}

/** The tag cloud's order by name, naturally and ignoring case. */
function _wp_object_name_sort_cb($a, $b)
{
    return strnatcasecmp($a->name, $b->name);
}

/** The tag cloud's default scale: a hundred per tenfold count. */
function default_topic_count_scale($count)
{
    return (int) round(log10($count + 1) * 100);
}

function _is_valid_nav_menu_item($item)
{
    return empty($item->_invalid);
}

/**
 * The menu items pointing at an object: posts by their id, terms by their
 * id and taxonomy; any status, oldest first.
 */
function wp_get_associated_nav_menu_items($object_id = 0, $object_type = 'post_type', $taxonomy = '')
{
    $items = get_posts(['post_type' => 'nav_menu_item', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'meta_key' => '_menu_item_object_id', 'meta_value' => (int) $object_id, 'suppress_filters' => true]);
    return array_values(array_filter(array_map('intval', $items), static fn ($id) => get_post_meta($id, '_menu_item_type', true) === $object_type && ($object_type !== 'taxonomy' || get_post_meta($id, '_menu_item_object', true) === $taxonomy)));
}

/** A deleted term takes the menu items pointing at it. */
function _wp_delete_tax_menu_item($object_id, $tt_id, $taxonomy)
{
    foreach (wp_get_associated_nav_menu_items((int) $object_id, 'taxonomy', (string) $taxonomy) as $item_id) {
        wp_delete_post($item_id, true);
    }
}

/** The posts and terms the menu items point at, loaded into the cache together. */
function update_menu_item_cache($menu_items)
{
    $posts = [];
    $terms = [];
    foreach ((array) $menu_items as $item) {
        $type = $item->type ?? get_post_meta((int) ($item->ID ?? 0), '_menu_item_type', true);
        $object_id = (int) ($item->object_id ?? get_post_meta((int) ($item->ID ?? 0), '_menu_item_object_id', true));
        if ($type === 'post_type') {
            $posts[] = $object_id;
        } elseif ($type === 'taxonomy') {
            $terms[] = $object_id;
        }
    }
    _prime_post_caches(array_values(array_unique(array_filter($posts))), false, false);
    _prime_term_caches(array_values(array_unique(array_filter($terms))), false);
}

/** A menu item's id attribute only the first time the item prints in a request. */
function _nav_menu_item_id_use_once($id, $item)
{
    $runtime = Minn\Runtime\Runtime::current();
    $used = (array) $runtime->get('nav_menu_used_item_ids', []);
    if (in_array($item->ID, $used, true)) {
        return '';
    }
    $runtime->set('nav_menu_used_item_ids', [...$used, $item->ID]);
    return $id;
}

/** The has-children class is dropped at the depth where a menu stops. */
function wp_nav_menu_remove_menu_item_has_children_class($classes, $menu_item, $args = false, $depth = false)
{
    $limit = is_object($args) ? (int) ($args->depth ?? 0) : 0;
    return $limit > 0 && (int) $depth + 1 >= $limit ? array_diff((array) $classes, ['menu-item-has-children']) : $classes;
}

/** The core taxonomies registered again from the engine's registry, in the reference's order (probe tail-helpers). */
function create_initial_taxonomies()
{
    // The first five name their one object type as text, the rest as a list, as the reference registers them.
    $named = ['category', 'post_tag', 'nav_menu', 'link_category', 'post_format'];
    foreach (Minn\Runtime\Runtime::registry()->taxonomies() as $name => $row) {
        if (!empty($row['_builtin'])) {
            // Once init has begun, a post format's address base is asked of plugins (probe hook-inputs-2).
            if ($name === 'post_format' && did_action('init') && is_array($row['rewrite'] ?? null)) {
                $row['rewrite']['slug'] = (string) apply_filters('post_format_rewrite_base', 'type');
            }
            $types = (array) ($row['object_type'] ?? []);
            $object = register_taxonomy($name, in_array($name, $named, true) ? (string) ($types[0] ?? '') : $types, ['capabilities' => (array) ($row['cap'] ?? [])] + $row);
            // The captured labels are the merged ones; core's category and post tag name no archives label of their own, so none is derived for them.
            $labels = $object instanceof WP_Taxonomy && is_object($object->labels) ? $object->labels : null;
            if ($labels !== null && !isset($row['labels']['archives']) && isset($labels->archives) && $labels->archives === ($labels->all_items ?? null)) {
                unset($labels->archives);
                Minn\Runtime\Runtime::registry()->adoptTaxonomy($object);
            }
        }
    }
}
