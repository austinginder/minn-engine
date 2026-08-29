<?php
/** The metadata API over the four *meta tables. */

use Minn\Runtime\Options;
use Minn\Runtime\Runtime;

/** @internal table, id column */
function _minn_meta_table(string $type): ?array
{
    return match ($type) {
        'post' => ['postmeta', 'post_id', 'meta_id'],
        'user' => ['usermeta', 'user_id', 'umeta_id'],
        'term' => ['termmeta', 'term_id', 'meta_id'],
        'comment' => ['commentmeta', 'comment_id', 'meta_id'],
        default => null,
    };
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
        $db = Runtime::current()->db;
        $rows = $db->rows("SELECT meta_key, meta_value FROM {$db->table($spec[0])} WHERE {$spec[1]} = ? ORDER BY {$spec[2]} ASC", [$object_id]);
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['meta_key']][] = (string) $row['meta_value'];
        }
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
    $db = Runtime::current()->db;
    $db->execute("INSERT INTO {$db->table($spec[0])} ({$spec[1]}, meta_key, meta_value) VALUES (?, ?, ?)", [$object_id, $meta_key, Options::toStorage($meta_value)]);
    $id = $db->insertId();
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
    $meta_value = wp_unslash($meta_value);
    $meta_value = sanitize_meta($meta_key, $meta_value, $meta_type);
    $check = apply_filters("update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value);
    if ($check !== null) {
        return (bool) $check;
    }
    $db = Runtime::current()->db;
    $rows = $db->rows("SELECT {$spec[2]} AS meta_id, meta_value FROM {$db->table($spec[0])} WHERE {$spec[1]} = ? AND meta_key = ? ORDER BY {$spec[2]} ASC", [$object_id, $meta_key]);
    if ($rows === []) {
        return add_metadata($meta_type, $object_id, $meta_key, $meta_value);
    }
    $stored = Options::toStorage($meta_value);
    if ($prev_value === '' && $stored === (string) $rows[0]['meta_value']) {
        return false;
    }
    $ids = [];
    foreach ($rows as $row) {
        if ($prev_value === '' || Options::toStorage($prev_value) === (string) $row['meta_value']) {
            $ids[] = (int) $row['meta_id'];
        }
    }
    if ($ids === []) {
        return false;
    }
    foreach ($ids as $id) {
        do_action("update_{$meta_type}_meta", $id, $object_id, $meta_key, $meta_value);
    }
    $db->execute("UPDATE {$db->table($spec[0])} SET meta_value = ? WHERE {$spec[2]} IN (" . implode(',', $ids) . ')', [$stored]);
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
    $db = Runtime::current()->db;
    $sql = "SELECT {$spec[2]} AS meta_id, {$spec[1]} AS object_id FROM {$db->table($spec[0])} WHERE meta_key = ?";
    $params = [$meta_key];
    if (!$delete_all) {
        $sql .= " AND {$spec[1]} = ?";
        $params[] = $object_id;
    }
    if ($meta_value !== '' && $meta_value !== null && $meta_value !== false) {
        $sql .= ' AND meta_value = ?';
        $params[] = Options::toStorage($meta_value);
    }
    $rows = $db->rows($sql, $params);
    if ($rows === []) {
        return false;
    }
    $ids = array_map(static fn (array $r) => (int) $r['meta_id'], $rows);
    do_action("delete_{$meta_type}_meta", $ids, $object_id, $meta_key, $meta_value);
    $db->execute("DELETE FROM {$db->table($spec[0])} WHERE {$spec[2]} IN (" . implode(',', $ids) . ')');
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
