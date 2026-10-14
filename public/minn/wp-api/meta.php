<?php
/** The metadata API over the four *meta tables. */

use Minn\Runtime\Options;
use Minn\Runtime\Runtime;
use Minn\Runtime\Meta;
use Minn\Runtime\MetaKeys;

/** @internal table, id column */
function _minn_meta_table(string $type): ?array
{
    return Minn\Runtime\MetaData::table($type);
}

/** @internal */
function _minn_meta(): Meta
{
    return new Meta(Runtime::current()->db);
}

function get_metadata($meta_type, $object_id, $meta_key = '', $single = false)
{
    return Minn\Runtime\MetaData::get($meta_type, $object_id, $meta_key, $single);
}

/** An object's meta as stored (probe user-more): false for no type or an id that is not a positive number once made absolute; null for a type without a table or a key not set. */
function get_metadata_raw($meta_type, $object_id, $meta_key = '', $single = false)
{
    return Minn\Runtime\MetaData::raw($meta_type, $object_id, $meta_key, $single);
}

function get_metadata_default($meta_type, $object_id, $meta_key, $single = false)
{
    return Minn\Runtime\MetaData::fallback($meta_type, $object_id, $meta_key, $single);
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
    return Minn\Runtime\MetaData::exists($meta_type, $object_id, $meta_key);
}

function add_metadata($meta_type, $object_id, $meta_key, $meta_value, $unique = false)
{
    return Minn\Runtime\MetaData::add($meta_type, $object_id, $meta_key, $meta_value, $unique);
}

function update_metadata($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '')
{
    return Minn\Runtime\MetaData::update($meta_type, $object_id, $meta_key, $meta_value, $prev_value);
}

function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false)
{
    return Minn\Runtime\MetaData::delete($meta_type, $object_id, $meta_key, $meta_value, $delete_all);
}

function sanitize_meta($meta_key, $meta_value, $object_type, $object_subtype = '')
{
    return Minn\Runtime\MetaData::sanitize($meta_key, $meta_value, $object_type, $object_subtype);
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

/** The one metadata lazyloader, made on first use. */
function wp_metadata_lazyloader()
{
    static $lazyloader = null;
    $lazyloader ??= new WP_Metadata_Lazyloader();
    return $lazyloader;
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
    return Minn\Runtime\MetaData::byMid($meta_type, $meta_id);
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
    return Minn\Runtime\MetaData::deleteByMid($meta_type, $meta_id);
}

/** A key is protected when its first printable character is an underscore; is_protected_meta may say otherwise. */
function is_protected_meta($meta_key, $meta_type = '')
{
    return Minn\Runtime\MetaData::isProtected($meta_key, $meta_type);
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
