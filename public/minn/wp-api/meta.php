<?php
/** The metadata API over the four *meta tables. */

use Minn\Runtime\Options;
use Minn\Runtime\Runtime;
use Minn\Runtime\Meta;
use Minn\Runtime\MetaKeys;

/** @internal table, id column */
function _minn_meta_table(string $type): ?array
{
    // A plugin's own meta type: the table it set on $wpdb as "{$type}meta".
    $table = Meta::knows($type) ? null : ($GLOBALS['wpdb']->{$type . 'meta'} ?? null);
    if (is_string($table) && $table !== '') {
        Minn\Runtime\MetaTypes::register($type, $table);
    }
    return Meta::knows($type) ? [$type] : null;
}

/** @internal */
function _minn_meta(): Meta
{
    return new Meta(Runtime::current()->db);
}

function get_metadata($meta_type, $object_id, $meta_key = '', $single = false)
{
    $value = get_metadata_raw($meta_type, $object_id, $meta_key, $single);
    if ($value !== null) {
        return $value;
    }
    return get_metadata_default($meta_type, $object_id, $meta_key, $single);
}

function get_metadata_raw($meta_type, $object_id, $meta_key = '', $single = false)
{
    $spec = _minn_meta_table((string) $meta_type);
    $object_id = (int) $object_id;
    if ($spec === null || $object_id <= 0) {
        return null;
    }
    $check = apply_filters("get_{$meta_type}_metadata", null, $object_id, $meta_key, $single, $meta_type);
    if ($check !== null) {
        return $single && is_array($check) ? $check[0] : $check;
    }
    $cache = wp_cache_get($object_id, $meta_type . '_meta', false, $found);
    if (!$found) {
        $cache = _minn_meta()->all((string) $meta_type, $object_id);
        wp_cache_set($object_id, $cache, $meta_type . '_meta');
    }
    if ($meta_key === '' || $meta_key === null) {
        return $cache;
    }
    if (!isset($cache[$meta_key])) {
        return null;
    }
    $values = array_map('maybe_unserialize', $cache[$meta_key]);
    return $single ? $values[0] : $values;
}

function get_metadata_default($meta_type, $object_id, $meta_key, $single = false)
{
    $value = apply_filters("default_{$meta_type}_metadata", $single ? '' : [], $object_id, $meta_key, $single, $meta_type);
    return !$single && !wp_is_numeric_array($value) ? [$value] : $value;
}

/** A registered key's default, for default_{type}_metadata (probe meta-api). */
function filter_default_metadata($value, $object_id, $meta_key, $single, $meta_type)
{
    return MetaKeys::defaultValue($value, (int) $object_id, (string) $meta_key, (bool) $single, (string) $meta_type);
}

/** An object's subtype: a post's type, a term's taxonomy, comment or user; '' for none. */
function get_object_subtype($object_type, $object_id)
{
    return MetaKeys::subtype((string) $object_type, (int) $object_id);
}

function metadata_exists($meta_type, $object_id, $meta_key)
{
    $check = apply_filters("get_{$meta_type}_metadata", null, (int) $object_id, $meta_key, true, $meta_type);
    if ($check !== null) {
        return (bool) $check;
    }
    $all = get_metadata_raw($meta_type, $object_id);
    return is_array($all) && isset($all[$meta_key]);
}

function add_metadata($meta_type, $object_id, $meta_key, $meta_value, $unique = false)
{
    $spec = _minn_meta_table((string) $meta_type);
    $object_id = (int) $object_id;
    if ($spec === null || $object_id <= 0 || (string) $meta_key === '') {
        return false;
    }
    $meta_key = wp_unslash($meta_key);
    $meta_value = wp_unslash($meta_value);
    $meta_value = sanitize_meta($meta_key, $meta_value, $meta_type, get_object_subtype($meta_type, $object_id));
    $check = apply_filters("add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique);
    if ($check !== null) {
        return $check;
    }
    if ($unique && metadata_exists($meta_type, $object_id, $meta_key)) {
        return false;
    }
    do_action("add_{$meta_type}_meta", $object_id, $meta_key, $meta_value);
    $id = _minn_meta()->add((string) $meta_type, $object_id, (string) $meta_key, Options::toStorage($meta_value));
    wp_cache_delete($object_id, $meta_type . '_meta');
    do_action("added_{$meta_type}_meta", $id, $object_id, $meta_key, $meta_value);
    return $id;
}

function update_metadata($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '')
{
    $spec = _minn_meta_table((string) $meta_type);
    $object_id = (int) $object_id;
    if ($spec === null || $object_id <= 0 || (string) $meta_key === '') {
        return false;
    }
    // A key not stored yet is added from the value as it was handed in (add_metadata sanitizes it).
    [$raw_key, $passed] = [$meta_key, $meta_value];
    $meta_key = wp_unslash($meta_key);
    $meta_value = sanitize_meta($meta_key, wp_unslash($meta_value), $meta_type, get_object_subtype($meta_type, $object_id));
    $check = apply_filters("update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value);
    if ($check !== null) {
        return (bool) $check;
    }
    $rows = _minn_meta()->matching((string) $meta_type, $object_id, (string) $meta_key);
    if ($rows === []) {
        return add_metadata($meta_type, $object_id, $raw_key, $passed);
    }
    $stored = Options::toStorage($meta_value);
    $ids = Meta::idsToUpdate($rows, $stored, $prev_value === '' ? null : Options::toStorage($prev_value));
    if ($ids === []) {
        return false;
    }
    foreach ($ids as $id) {
        _minn_meta_action('update', (string) $meta_type, [$id, $object_id, $meta_key, $meta_value], [$id, $object_id, $meta_key, $meta_value]);
    }
    _minn_meta()->updateRows((string) $meta_type, $ids, $stored);
    wp_cache_delete($object_id, $meta_type . '_meta');
    foreach ($ids as $id) {
        _minn_meta_action('updated', (string) $meta_type, [$id, $object_id, $meta_key, $meta_value], [$id, $object_id, $meta_key, $meta_value]);
    }
    return true;
}

function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false)
{
    $spec = _minn_meta_table((string) $meta_type);
    $object_id = (int) $object_id;
    if ($spec === null || ($object_id <= 0 && !$delete_all) || (string) $meta_key === '') {
        return false;
    }
    $meta_key = wp_unslash($meta_key);
    $meta_value = wp_unslash($meta_value);
    $check = apply_filters("delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all);
    if ($check !== null) {
        return (bool) $check;
    }
    $rows = _minn_meta()->find((string) $meta_type, $delete_all ? null : $object_id, (string) $meta_key, $meta_value !== '' && $meta_value !== null && $meta_value !== false ? Options::toStorage($meta_value) : null);
    if ($rows === []) {
        return false;
    }
    $ids = array_map(static fn (array $r) => (int) $r['meta_id'], $rows);
    _minn_meta_action('delete', (string) $meta_type, [$ids, $object_id, $meta_key, $meta_value], [$ids]);
    _minn_meta()->deleteRows((string) $meta_type, $ids);
    foreach ($rows as $row) {
        wp_cache_delete((int) $row['object_id'], $meta_type . '_meta');
    }
    _minn_meta_action('deleted', (string) $meta_type, [$ids, $object_id, $meta_key, $meta_value], [$ids]);
    return true;
}

function sanitize_meta($meta_key, $meta_value, $object_type, $object_subtype = '')
{
    if ((string) $object_subtype !== '' && has_filter("sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}")) {
        return apply_filters("sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}", $meta_value, $meta_key, $object_type, $object_subtype);
    }
    return apply_filters("sanitize_{$object_type}_meta_{$meta_key}", $meta_value, $meta_key, $object_type);
}

function register_meta($object_type, $meta_key, $args, $deprecated = null)
{
    return MetaKeys::register((string) $object_type, (string) $meta_key, $args, $deprecated);
}

/** The registration arguments with only the known ones (the defaults' keys) kept; a default register_meta_args filter (probe deprecated). */
function _wp_register_meta_args_allowed_list($args, $default_args)
{
    return array_intersect_key($args, $default_args);
}

function registered_meta_key_exists($object_type, $meta_key, $object_subtype = '')
{
    return isset(MetaKeys::of((string) $object_type, (string) $object_subtype)[$meta_key]);
}

function unregister_meta_key($object_type, $meta_key, $object_subtype = '')
{
    return MetaKeys::unregister((string) $object_type, (string) $meta_key, (string) $object_subtype);
}

function get_registered_meta_keys($object_type, $object_subtype = '')
{
    return MetaKeys::of((string) $object_type, (string) $object_subtype);
}

/** A registered key's value for an object (false when the key is not registered), or every registered key's stored values. */
function get_registered_metadata($object_type, $object_id, $meta_key = '')
{
    $subtype = get_object_subtype($object_type, $object_id);
    if (!empty($meta_key)) {
        $subtype = $subtype !== '' && registered_meta_key_exists($object_type, $meta_key, $subtype) ? $subtype : '';
        $args = MetaKeys::of((string) $object_type, $subtype)[$meta_key] ?? null;
        return $args === null ? false : get_metadata($object_type, $object_id, $meta_key, !empty($args['single']));
    }
    $data = get_metadata($object_type, $object_id);
    return $data ? array_intersect_key((array) $data, MetaKeys::forObject((string) $object_type, $subtype)) : [];
}

function register_term_meta($taxonomy, $meta_key, array $args)
{
    $args['object_subtype'] = $taxonomy;
    return register_meta('term', $meta_key, $args);
}

function unregister_term_meta($taxonomy, $meta_key)
{
    return unregister_meta_key('term', $meta_key, $taxonomy);
}

/** The registered entry for a key, subtype first then the plain one. */
function _minn_registered_meta($object_type, $meta_key, $object_subtype = '')
{
    return MetaKeys::of((string) $object_type, (string) $object_subtype)[$meta_key] ?? MetaKeys::of((string) $object_type)[$meta_key] ?? null;
}

/** Objects' meta read into the cache at once (Runtime\Meta::prime), and answered by id; false for no type or no ids. */
function update_meta_cache($meta_type, $object_ids)
{
    if (!$meta_type || !$object_ids || !Meta::knows((string) $meta_type)) {
        return false;
    }
    $ids = array_map('intval', is_array($object_ids) ? $object_ids : explode(',', (string) preg_replace('|[^0-9,]|', '', (string) $object_ids)));
    $check = apply_filters("update_{$meta_type}_metadata_cache", null, $ids);
    if ($check !== null) {
        return (bool) $check;
    }
    return _minn_meta()->prime((string) $meta_type, $ids);
}

function get_post_meta($post_id, $key = '', $single = false)
{
    return get_metadata('post', $post_id, $key, $single);
}

function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    return update_metadata('post', $post_id, $meta_key, $meta_value, $prev_value);
}

function add_post_meta($post_id, $meta_key, $meta_value, $unique = false)
{
    return add_metadata('post', $post_id, $meta_key, $meta_value, $unique);
}

function delete_post_meta($post_id, $meta_key, $meta_value = '')
{
    return delete_metadata('post', $post_id, $meta_key, $meta_value);
}

function get_term_meta($term_id, $key = '', $single = false)
{
    return get_metadata('term', $term_id, $key, $single);
}

function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '')
{
    return update_metadata('term', $term_id, $meta_key, $meta_value, $prev_value);
}

function add_term_meta($term_id, $meta_key, $meta_value, $unique = false)
{
    return add_metadata('term', $term_id, $meta_key, $meta_value, $unique);
}

function delete_term_meta($term_id, $meta_key, $meta_value = '')
{
    return delete_metadata('term', $term_id, $meta_key, $meta_value);
}

function get_comment_meta($comment_id, $key = '', $single = false)
{
    return get_metadata('comment', $comment_id, $key, $single);
}

function update_comment_meta($comment_id, $meta_key, $meta_value, $prev_value = '')
{
    return update_metadata('comment', $comment_id, $meta_key, $meta_value, $prev_value);
}

function add_comment_meta($comment_id, $meta_key, $meta_value, $unique = false)
{
    return add_metadata('comment', $comment_id, $meta_key, $meta_value, $unique);
}

function delete_comment_meta($comment_id, $meta_key, $meta_value = '')
{
    return delete_metadata('comment', $comment_id, $meta_key, $meta_value);
}

/**
 * @internal A meta write's action, {verb}_{type}_meta, and for post meta the
 * older {verb}_postmeta the reference still fires beside it, each with
 * its own arguments.
 */
function _minn_meta_action(string $verb, string $meta_type, array $args, array $legacy): void
{
    do_action("{$verb}_{$meta_type}_meta", ...$args);
    if ($meta_type === 'post') {
        do_action("{$verb}_postmeta", ...$legacy);
    }
}

/** One meta row by its id: an object under the table's own column names, the value decoded; false when there is none. */
function get_metadata_by_mid($meta_type, $meta_id)
{
    if (!_minn_meta_table((string) $meta_type) || !is_numeric($meta_id) || (int) $meta_id <= 0) {
        return false;
    }
    $row = _minn_meta()->byId((string) $meta_type, (int) $meta_id);
    if ($row === null) {
        return false;
    }
    $row['meta_value'] = maybe_unserialize($row['meta_value']);
    return (object) $row;
}

/** Rewrites one meta row by its id, its key too when one is given, with the update actions for that row. */
function update_metadata_by_mid($meta_type, $meta_id, $meta_value, $meta_key = false)
{
    $meta = get_metadata_by_mid($meta_type, $meta_id);
    if ($meta === false) {
        return false;
    }
    $check = apply_filters("update_{$meta_type}_metadata_by_mid", null, $meta_id, $meta_value, $meta_key);
    if ($check !== null) {
        return (bool) $check;
    }
    [$column] = _minn_meta()->columns((string) $meta_type);
    $object_id = (int) $meta->{$column};
    $key = $meta_key === false ? $meta->meta_key : (string) $meta_key;
    $value = sanitize_meta($key, wp_unslash($meta_value), $meta_type, get_object_subtype($meta_type, $object_id));
    $args = [(int) $meta_id, $object_id, $key, $value];
    _minn_meta_action('update', (string) $meta_type, $args, $args);
    _minn_meta()->rewrite((string) $meta_type, (int) $meta_id, $key, Options::toStorage($value));
    wp_cache_delete($object_id, $meta_type . '_meta');
    _minn_meta_action('updated', (string) $meta_type, $args, $args);
    return true;
}

/** Removes one meta row by its id, with the delete actions for that row. */
function delete_metadata_by_mid($meta_type, $meta_id)
{
    $meta = get_metadata_by_mid($meta_type, $meta_id);
    if ($meta === false) {
        return false;
    }
    $check = apply_filters("delete_{$meta_type}_metadata_by_mid", null, $meta_id);
    if ($check !== null) {
        return (bool) $check;
    }
    [$column] = _minn_meta()->columns((string) $meta_type);
    $object_id = (int) $meta->{$column};
    $args = [[(int) $meta_id], $object_id, $meta->meta_key, $meta->meta_value];
    _minn_meta_action('delete', (string) $meta_type, $args, [(int) $meta_id]);
    _minn_meta()->deleteRows((string) $meta_type, [(int) $meta_id]);
    wp_cache_delete($object_id, $meta_type . '_meta');
    _minn_meta_action('deleted', (string) $meta_type, $args, [(int) $meta_id]);
    return true;
}

/** A key is protected when its first printable character is an underscore; is_protected_meta may say otherwise. */
function is_protected_meta($meta_key, $meta_type = '')
{
    $key = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $meta_key);
    return apply_filters('is_protected_meta', $key !== '' && $key[0] === '_', $meta_key, $meta_type);
}

/** The meta table of an object type the database has one for, or false. */
function _get_meta_table($type)
{
    global $wpdb;
    $table = (string) $type . 'meta';
    return $type !== '' && isset($wpdb->$table) && is_string($wpdb->$table) ? $wpdb->$table : false;
}

/** The JOIN and WHERE a meta query adds to a query of an object type (WP_Meta_Query); false for a type without meta. */
function get_meta_sql($meta_query, $type, $primary_table, $primary_id_column, $context = null)
{
    return (new WP_Meta_Query($meta_query))->get_sql($type, $primary_table, $primary_id_column, $context);
}
