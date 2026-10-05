<?php
/** The metadata API over the four *meta tables. */

use Minn\Runtime\Options;
use Minn\Runtime\Runtime;
use Minn\Runtime\Meta;

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
    $value = $single ? '' : [];
    return apply_filters("default_{$meta_type}_metadata", $value, $object_id, $meta_key, $single, $meta_type);
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
    $meta_value = sanitize_meta($meta_key, $meta_value, $meta_type);
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
    $meta_key = wp_unslash($meta_key);
    $meta_value = sanitize_meta($meta_key, wp_unslash($meta_value), $meta_type);
    $check = apply_filters("update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value);
    if ($check !== null) {
        return (bool) $check;
    }
    $rows = _minn_meta()->matching((string) $meta_type, $object_id, (string) $meta_key);
    if ($rows === []) {
        return add_metadata($meta_type, $object_id, $meta_key, $meta_value);
    }
    $stored = Options::toStorage($meta_value);
    $ids = Meta::idsToUpdate($rows, $stored, $prev_value === '' ? null : Options::toStorage($prev_value));
    if ($ids === []) {
        return false;
    }
    foreach ($ids as $id) {
        do_action("update_{$meta_type}_meta", $id, $object_id, $meta_key, $meta_value);
    }
    _minn_meta()->updateRows((string) $meta_type, $ids, $stored);
    wp_cache_delete($object_id, $meta_type . '_meta');
    foreach ($ids as $id) {
        do_action("updated_{$meta_type}_meta", $id, $object_id, $meta_key, $meta_value);
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
    do_action("delete_{$meta_type}_meta", $ids, $object_id, $meta_key, $meta_value);
    _minn_meta()->deleteRows((string) $meta_type, $ids);
    foreach ($rows as $row) {
        wp_cache_delete((int) $row['object_id'], $meta_type . '_meta');
    }
    do_action("deleted_{$meta_type}_meta", $ids, $object_id, $meta_key, $meta_value);
    return true;
}

function sanitize_meta($meta_key, $meta_value, $object_type, $object_subtype = '')
{
    return apply_filters("sanitize_{$object_type}_meta_{$meta_key}", $meta_value, $meta_key, $object_type);
}

function register_meta($object_type, $meta_key, $args, $deprecated = null)
{
    $defaults = ['object_subtype' => '', 'type' => 'string', 'label' => '', 'description' => '', 'single' => false, 'sanitize_callback' => null, 'auth_callback' => null, 'show_in_rest' => false, 'revisions_enabled' => false];
    $args = wp_parse_args((array) $args, $defaults);
    $subtype = (string) $args['object_subtype'];
    unset($args['object_subtype']);
    if (!in_array($args['type'], ['string', 'boolean', 'integer', 'number', 'array', 'object'], true)) {
        return false;
    }
    if ($args['show_in_rest'] && !$subtype && ($object_type === 'post' || $object_type === 'term' || $object_type === 'comment' || $object_type === 'user')) {
        // A REST-visible key with no subtype is still fine on the reference.
    }
    $args['object_subtype'] = $subtype;
    $registered = Runtime::current()->get('registered_meta', []);
    $registered[$object_type][$subtype][$meta_key] = $args;
    Runtime::current()->set('registered_meta', $registered);
    return true;
}

function registered_meta_key_exists($object_type, $meta_key, $object_subtype = '')
{
    return isset(Runtime::current()->get('registered_meta', [])[$object_type][(string) $object_subtype][$meta_key]);
}

function unregister_meta_key($object_type, $meta_key, $object_subtype = '')
{
    $registered = Runtime::current()->get('registered_meta', []);
    if (!isset($registered[$object_type][(string) $object_subtype][$meta_key])) {
        return false;
    }
    unset($registered[$object_type][(string) $object_subtype][$meta_key]);
    Runtime::current()->set('registered_meta', $registered);
    return true;
}

function get_registered_meta_keys($object_type, $object_subtype = '')
{
    $keys = Runtime::current()->get('registered_meta', [])[$object_type][(string) $object_subtype] ?? [];
    foreach ($keys as $key => $args) {
        unset($keys[$key]['object_subtype']);
    }
    return $keys;
}

/** The registered entry for a key, subtype first then the plain one. */
function _minn_registered_meta($object_type, $meta_key, $object_subtype = '')
{
    $all = Runtime::current()->get('registered_meta', []);
    return $all[$object_type][(string) $object_subtype][$meta_key] ?? $all[$object_type][''][$meta_key] ?? null;
}

function update_meta_cache($meta_type, $object_ids)
{
    return [];
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
