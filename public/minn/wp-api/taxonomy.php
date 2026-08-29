<?php
/** Terms and taxonomies. Behaviour from contracts/fixtures/api/content.json. */

use Minn\Content\Terms;
use Minn\Content\Slug;
use Minn\Runtime\Runtime;

/** @internal a term row joined with its taxonomy row, by id (and taxonomy when known) */
function _minn_term_row(int $termId, ?string $taxonomy): ?array
{
    $db = Runtime::current()->db;
    $sql = "SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count FROM {$db->table('terms')} t JOIN {$db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE t.term_id = ?";
    $params = [$termId];
    if ($taxonomy !== null) {
        $sql .= ' AND tt.taxonomy = ?';
        $params[] = $taxonomy;
    }
    return $db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
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
    $db = Runtime::current()->db;
    if ($taxonomy !== '' && !taxonomy_exists($taxonomy) && $field !== 'term_taxonomy_id') {
        return false;
    }
    $column = match ($field) {
        'slug' => 't.slug',
        'name' => 't.name',
        'term_taxonomy_id' => 'tt.term_taxonomy_id',
        'id', 'ID', 'term_id' => 't.term_id',
        default => null,
    };
    if ($column === null) {
        return false;
    }
    if ($field === 'slug') {
        $value = sanitize_title((string) $value);
    }
    if ($value === '' || $value === null) {
        return false;
    }
    $sql = "SELECT t.term_id, tt.taxonomy FROM {$db->table('terms')} t JOIN {$db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE {$column} = ?";
    $params = [$value];
    if ($taxonomy !== '') {
        $sql .= ' AND tt.taxonomy = ?';
        $params[] = (string) $taxonomy;
    }
    $row = $db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
    if ($row === null) {
        return false;
    }
    return get_term((int) $row['term_id'], (string) $row['taxonomy'], $output, $filter);
}

function term_exists($term, $taxonomy = '', $parent_term = null)
{
    $db = Runtime::current()->db;
    if ($term === null || $term === '' || $term === 0 || $term === '0') {
        return null;
    }
    $sql = "SELECT t.term_id, tt.term_taxonomy_id FROM {$db->table('terms')} t JOIN {$db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE ";
    $params = [];
    if (is_int($term) || (is_string($term) && ctype_digit($term))) {
        $sql .= 't.term_id = ?';
        $params[] = (int) $term;
    } else {
        $slug = sanitize_title((string) $term);
        $sql .= '(t.slug = ? OR t.name = ?)';
        $params[] = $slug;
        $params[] = (string) $term;
    }
    if ($taxonomy !== '') {
        $sql .= ' AND tt.taxonomy = ?';
        $params[] = (string) $taxonomy;
        if ($parent_term !== null && (int) $parent_term > 0) {
            $sql .= ' AND tt.parent = ?';
            $params[] = (int) $parent_term;
        }
    }
    $row = $db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
    if ($row === null) {
        return null;
    }
    if ($taxonomy === '') {
        return (string) $row['term_id'];
    }
    return ['term_id' => (string) $row['term_id'], 'term_taxonomy_id' => (string) $row['term_taxonomy_id']];
}

function get_terms($args = [], $deprecated = '')
{
    if (is_string($args) || (is_array($args) && !isset($args['taxonomy']) && !empty($deprecated) && wp_is_numeric_array($args))) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    } elseif (is_array($args) && wp_is_numeric_array($args) && $args !== [] && is_string($args[0])) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    }
    $args = wp_parse_args($args, ['taxonomy' => null, 'object_ids' => null, 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => true, 'include' => [], 'exclude' => [], 'exclude_tree' => [], 'number' => '', 'offset' => '', 'fields' => 'all', 'count' => false, 'name' => '', 'slug' => '', 'term_taxonomy_id' => '', 'hierarchical' => true, 'search' => '', 'name__like' => '', 'description__like' => '', 'pad_counts' => false, 'get' => '', 'child_of' => 0, 'parent' => '', 'childless' => false, 'cache_domain' => 'core', 'update_term_meta_cache' => true, 'meta_query' => '', 'meta_key' => '', 'meta_value' => '']);
    $taxonomies = $args['taxonomy'] === null ? null : array_values(array_map('strval', (array) $args['taxonomy']));
    if ($taxonomies !== null) {
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
            }
        }
    }
    $args = apply_filters('get_terms_args', $args, $taxonomies ?? []);
    if ($args['get'] === 'all') {
        $args['hide_empty'] = false;
        $args['childless'] = false;
        $args['child_of'] = 0;
        $args['pad_counts'] = false;
    }
    $db = Runtime::current()->db;
    $where = [];
    $params = [];
    $join = '';
    if ($taxonomies !== null) {
        $where[] = 'tt.taxonomy IN (' . implode(',', array_fill(0, count($taxonomies), '?')) . ')';
        array_push($params, ...$taxonomies);
    }
    if ($args['object_ids'] !== null && $args['object_ids'] !== []) {
        $ids = array_map('intval', (array) $args['object_ids']);
        $join = "JOIN {$db->table('term_relationships')} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id";
        $where[] = 'tr.object_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if ($args['hide_empty'] && ($args['object_ids'] === null || $args['object_ids'] === [])) {
        $where[] = 'tt.count > 0';
    }
    if (!empty($args['include'])) {
        $ids = wp_parse_id_list($args['include']);
        $where[] = 't.term_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($args['exclude'])) {
        $ids = wp_parse_id_list($args['exclude']);
        $where[] = 't.term_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if ($args['name'] !== '') {
        $names = array_map('strval', (array) $args['name']);
        $where[] = 't.name IN (' . implode(',', array_fill(0, count($names), '?')) . ')';
        array_push($params, ...$names);
    }
    if ($args['slug'] !== '') {
        $slugs = array_map(static fn ($s) => sanitize_title((string) $s), (array) $args['slug']);
        $where[] = 't.slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
        array_push($params, ...$slugs);
    }
    if ($args['term_taxonomy_id'] !== '') {
        $ids = array_map('intval', (array) $args['term_taxonomy_id']);
        $where[] = 'tt.term_taxonomy_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if ($args['parent'] !== '' && $args['parent'] !== null) {
        $where[] = 'tt.parent = ?';
        $params[] = (int) $args['parent'];
    }
    if ($args['search'] !== '') {
        $needle = '%' . addcslashes((string) $args['search'], '%_\\') . '%';
        $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
        array_push($params, $needle, $needle);
    }
    if ($args['name__like'] !== '') {
        $where[] = 't.name LIKE ?';
        $params[] = '%' . addcslashes((string) $args['name__like'], '%_\\') . '%';
    }
    if ($args['description__like'] !== '') {
        $where[] = 'tt.description LIKE ?';
        $params[] = '%' . addcslashes((string) $args['description__like'], '%_\\') . '%';
    }
    if ($args['childless']) {
        $where[] = "NOT EXISTS (SELECT 1 FROM {$db->table('term_taxonomy')} c WHERE c.parent = t.term_id AND c.taxonomy = tt.taxonomy)";
    }
    if ($args['meta_key'] !== '' || (is_array($args['meta_query']) && $args['meta_query'] !== [])) {
        $clauses = is_array($args['meta_query']) ? $args['meta_query'] : [];
        if ($args['meta_key'] !== '') {
            $clauses[] = ['key' => $args['meta_key']] + ($args['meta_value'] !== '' ? ['value' => $args['meta_value']] : []);
        }
        foreach ($clauses as $clause) {
            if (!is_array($clause) || !isset($clause['key'])) {
                continue;
            }
            if (array_key_exists('value', $clause)) {
                $where[] = "EXISTS (SELECT 1 FROM {$db->table('termmeta')} m WHERE m.term_id = t.term_id AND m.meta_key = ? AND m.meta_value = ?)";
                $params[] = (string) $clause['key'];
                $params[] = is_array($clause['value']) ? (string) reset($clause['value']) : (string) $clause['value'];
            } else {
                $where[] = "EXISTS (SELECT 1 FROM {$db->table('termmeta')} m WHERE m.term_id = t.term_id AND m.meta_key = ?)";
                $params[] = (string) $clause['key'];
            }
        }
    }
    $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
    $orderby = match ((string) $args['orderby']) {
        'name' => 't.name',
        'slug' => 't.slug',
        'term_group' => 't.term_group',
        'term_id', 'id' => 't.term_id',
        'count' => 'tt.count',
        'description' => 'tt.description',
        'parent' => 'tt.parent',
        'term_taxonomy_id' => 'tt.term_taxonomy_id',
        'term_order' => $join !== '' ? 'tr.term_order' : 't.name',
        'include' => !empty($args['include']) ? 'FIELD(t.term_id,' . implode(',', wp_parse_id_list($args['include'])) . ')' : 't.name',
        'slug__in' => 't.slug',
        'none' => '',
        default => 't.name',
    };
    $orderClause = $orderby === '' ? '' : " ORDER BY {$orderby} {$order}";
    $limit = '';
    $limitParams = [];
    if ($args['number'] !== '' && (int) $args['number'] > 0) {
        $limit = ' LIMIT ? OFFSET ?';
        $limitParams = [(int) $args['number'], (int) $args['offset']];
    } elseif ($args['offset'] !== '' && (int) $args['offset'] > 0) {
        $limit = ' LIMIT 18446744073709551615 OFFSET ?';
        $limitParams = [(int) $args['offset']];
    }
    $clause = $where === [] ? '1=1' : implode(' AND ', $where);
    if ($args['fields'] === 'count' || $args['count']) {
        return (string) (int) $db->value("SELECT COUNT(DISTINCT tt.term_taxonomy_id) FROM {$db->table('terms')} t JOIN {$db->table('term_taxonomy')} tt ON tt.term_id = t.term_id {$join} WHERE {$clause}", $params);
    }
    $rows = $db->rows("SELECT DISTINCT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count FROM {$db->table('terms')} t JOIN {$db->table('term_taxonomy')} tt ON tt.term_id = t.term_id {$join} WHERE {$clause}{$orderClause}{$limit}", [...$params, ...$limitParams]);
    $terms = array_map(static fn (array $r) => new WP_Term((object) $r), $rows);
    if ((int) $args['child_of'] > 0) {
        $wanted = [(int) $args['child_of']];
        $kept = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($terms as $term) {
                if (in_array($term->parent, $wanted, true) && !in_array($term, $kept, true)) {
                    $kept[] = $term;
                    $wanted[] = $term->term_id;
                    $changed = true;
                }
            }
        }
        $terms = $kept;
    }
    foreach ((array) $args['exclude_tree'] as $tree) {
        $excluded = [(int) $tree];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($terms as $term) {
                if (in_array($term->parent, $excluded, true) && !in_array($term->term_id, $excluded, true)) {
                    $excluded[] = $term->term_id;
                    $changed = true;
                }
            }
        }
        $terms = array_values(array_filter($terms, static fn (WP_Term $t) => !in_array($t->term_id, $excluded, true)));
    }
    $terms = apply_filters('get_terms', $terms, $taxonomies ?? [], $args, []);
    return _minn_term_fields($terms, (string) $args['fields']);
}

/** @internal the fields shapes get_terms and wp_get_object_terms share */
function _minn_term_fields(array $terms, string $fields): array
{
    switch ($fields) {
        case 'ids':
            return array_map(static fn (WP_Term $t) => $t->term_id, $terms);
        case 'tt_ids':
            return array_map(static fn (WP_Term $t) => $t->term_taxonomy_id, $terms);
        case 'names':
            return array_map(static fn (WP_Term $t) => $t->name, $terms);
        case 'slugs':
            return array_map(static fn (WP_Term $t) => $t->slug, $terms);
        case 'id=>name':
            $out = [];
            foreach ($terms as $t) {
                $out[$t->term_id] = $t->name;
            }
            return $out;
        case 'id=>slug':
            $out = [];
            foreach ($terms as $t) {
                $out[$t->term_id] = $t->slug;
            }
            return $out;
        case 'id=>parent':
            $out = [];
            foreach ($terms as $t) {
                $out[$t->term_id] = $t->parent;
            }
            return $out;
        case 'all_with_object_id':
        default:
            return array_values($terms);
    }
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
    $row = _minn_term_row($term->term_id, $term->taxonomy) ?? $term->to_array();
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
    $termIds = [];
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
        $termIds[] = (int) $info['term_id'];
        $ttIds[] = $info['term_taxonomy_id'];
    }
    $db = Runtime::current()->db;
    $old = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'tt_ids', 'orderby' => 'none']);
    $old = is_wp_error($old) ? [] : $old;
    $keep = $append ? array_values(array_unique([...$old, ...array_map('intval', $ttIds)])) : array_map('intval', $ttIds);
    foreach (array_diff($old, $keep) as $ttid) {
        do_action('delete_term_relationships', $object_id, [$ttid], $taxonomy);
        $db->execute("DELETE FROM {$db->table('term_relationships')} WHERE object_id = ? AND term_taxonomy_id = ?", [$object_id, (int) $ttid]);
        do_action('deleted_term_relationships', $object_id, [$ttid], $taxonomy);
    }
    foreach ($keep as $ttid) {
        if (in_array($ttid, $old, true)) {
            continue;
        }
        do_action('add_term_relationship', $object_id, $ttid, $taxonomy);
        $db->execute("INSERT IGNORE INTO {$db->table('term_relationships')} (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)", [$object_id, (int) $ttid]);
        do_action('added_term_relationship', $object_id, $ttid, $taxonomy);
    }
    _minn_post_writer()->recount((string) $taxonomy);
    wp_cache_delete($object_id, 'post_meta');
    do_action('set_object_terms', $object_id, $terms, $ttIds, $taxonomy, $append, $old);
    return $ttIds;
}

function wp_remove_object_terms($object_id, $terms, $taxonomy)
{
    $object_id = (int) $object_id;
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }
    $db = Runtime::current()->db;
    $removed = 0;
    foreach ((array) $terms as $term) {
        $info = term_exists($term, $taxonomy);
        if (!$info) {
            continue;
        }
        $removed += $db->execute("DELETE FROM {$db->table('term_relationships')} WHERE object_id = ? AND term_taxonomy_id = ?", [$object_id, (int) $info['term_taxonomy_id']]);
    }
    _minn_post_writer()->recount((string) $taxonomy);
    return $removed > 0;
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
    $term = wp_unslash((string) $term);
    $term = apply_filters('pre_insert_term', $term, $taxonomy, $args);
    if (is_wp_error($term)) {
        return $term;
    }
    if (is_int($term) && $term === 0) {
        return new WP_Error('invalid_term_id', 'Invalid term ID.');
    }
    if (trim($term) === '') {
        return new WP_Error('empty_term_name', 'A name is required for this term.');
    }
    $args = wp_parse_args(wp_unslash((array) $args), ['alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '']);
    $parent = (int) $args['parent'];
    if ($parent > 0 && !term_exists($parent, $taxonomy)) {
        return new WP_Error('missing_parent', 'Parent term does not exist.');
    }
    $name = $term;
    $hierarchical = is_taxonomy_hierarchical($taxonomy);
    $existing = get_term_by('name', $name, $taxonomy);
    if ($existing instanceof WP_Term) {
        if (!$hierarchical || $existing->parent === $parent) {
            $slugMatch = $args['slug'] === '' || sanitize_title($args['slug']) === $existing->slug;
            if ($slugMatch || !$hierarchical) {
                return new WP_Error('term_exists', 'A term with the name provided already exists in this taxonomy.', $existing->term_id);
            }
        }
    }
    $base = $args['slug'] !== '' ? sanitize_title($args['slug']) : sanitize_title($name);
    $slug = _minn_terms()->uniqueSlug($base, (string) $taxonomy);
    if ($args['slug'] !== '' && $slug !== $base && get_term_by('slug', $base, $taxonomy)) {
        return new WP_Error('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $base));
    }
    $termId = _minn_terms()->create($name, $slug, (string) $taxonomy, (string) $args['description'], $parent);
    $row = _minn_term_row($termId, (string) $taxonomy);
    $ttId = (int) $row['term_taxonomy_id'];
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
    $name = isset($args['name']) ? trim((string) $args['name']) : $term->name;
    if ($name === '') {
        return new WP_Error('empty_term_name', 'A name is required for this term.');
    }
    $description = isset($args['description']) ? (string) $args['description'] : $term->description;
    $parent = isset($args['parent']) ? (int) $args['parent'] : $term->parent;
    if ($parent > 0 && !term_exists($parent, $taxonomy)) {
        return new WP_Error('missing_parent', 'Parent term does not exist.');
    }
    $slug = isset($args['slug']) && $args['slug'] !== '' ? sanitize_title((string) $args['slug']) : $term->slug;
    if ($slug !== $term->slug || (isset($args['name']) && !isset($args['slug']) && $slug === '')) {
        $slug = _minn_terms()->uniqueSlug($slug === '' ? $name : $slug, (string) $taxonomy, $term->term_id);
    }
    $duplicate = get_term_by('slug', $slug, $taxonomy);
    if ($duplicate instanceof WP_Term && $duplicate->term_id !== $term->term_id) {
        return new WP_Error('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $slug));
    }
    do_action('edit_terms', $term->term_id, $taxonomy, $args);
    _minn_terms()->rename($term->term_id, $name, $slug);
    _minn_terms()->describe($term->term_id, (string) $taxonomy, $description, $parent);
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
    if ($taxonomy === 'category' && $object->term_id === (int) get_option('default_category')) {
        return 0;
    }
    $args = wp_parse_args($args, ['default' => null, 'force_default' => false]);
    $row = _minn_term_row($object->term_id, (string) $taxonomy);
    do_action('pre_delete_term', $object->term_id, $taxonomy);
    $objects = get_objects_in_term($object->term_id, $taxonomy);
    $default = $taxonomy === 'category' ? (int) get_option('default_category') : (int) ($args['default'] ?? 0);
    do_action('delete_term_taxonomy', $object->term_taxonomy_id);
    _minn_terms()->delete($row, is_taxonomy_hierarchical($taxonomy));
    if ($default > 0 && $taxonomy === 'category') {
        foreach ($objects as $objectId) {
            if (wp_get_object_terms((int) $objectId, 'category', ['fields' => 'ids']) === []) {
                wp_set_object_terms((int) $objectId, [$default], 'category', true);
            }
        }
    }
    _minn_post_writer()->recount((string) $taxonomy);
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
    $db = Runtime::current()->db;
    $out = [];
    $queue = [(int) $term_id];
    while ($queue !== []) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        $rows = $db->rows("SELECT term_id FROM {$db->table('term_taxonomy')} WHERE taxonomy = ? AND parent IN ({$placeholders})", [(string) $taxonomy, ...$queue]);
        $queue = [];
        foreach ($rows as $row) {
            if (!in_array((int) $row['term_id'], $out, true)) {
                $out[] = (int) $row['term_id'];
                $queue[] = (int) $row['term_id'];
            }
        }
    }
    return $out;
}

function get_objects_in_term($term_ids, $taxonomies, $args = [])
{
    $ids = array_map('intval', (array) $term_ids);
    $taxonomies = array_values(array_map('strval', (array) $taxonomies));
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
        }
    }
    if ($ids === []) {
        return [];
    }
    $db = Runtime::current()->db;
    $order = strtoupper((string) (wp_parse_args($args, ['order' => 'ASC'])['order'])) === 'DESC' ? 'DESC' : 'ASC';
    $rows = $db->rows("SELECT DISTINCT tr.object_id FROM {$db->table('term_relationships')} tr JOIN {$db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy IN (" . implode(',', array_fill(0, count($taxonomies), '?')) . ') AND tt.term_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY tr.object_id {$order}", [...$taxonomies, ...$ids]);
    return array_map(static fn (array $r) => (string) $r['object_id'], $rows);
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
