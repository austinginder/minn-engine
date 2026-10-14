<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Slashes;

/**
 * Metadata as WordPress's meta functions read and write it, for posts,
 * terms, comments, users and any type a plugin's table names: read through
 * get_{type}_metadata and the object cache (a value its default when unset),
 * written and removed with the filters that may answer first and the actions
 * around each change (post meta also told as postmeta), values sanitized and
 * stored serialized.
 */
final class MetaData
{
    /** A value, a key's values, or every key's (get_metadata), the default when the key is unset. */
    public static function get(mixed $type, mixed $objectId, mixed $key = '', mixed $single = false): mixed
    {
        $value = self::raw($type, $objectId, $key, $single);
        return $value !== null ? $value : self::fallback($type, $objectId, $key, $single);
    }

    /**
     * What is stored (get_metadata_raw): false for no type or object, null
     * for an unknown type or an unset key; get_{type}_metadata may answer
     * first; else the object's meta, read once into the cache.
     */
    public static function raw(mixed $type, mixed $objectId, mixed $key = '', mixed $single = false): mixed
    {
        if (!$type || !is_numeric($objectId)) {
            return false;
        }
        $objectId = abs((int) $objectId);
        if (!$objectId) {
            return false;
        }
        if (self::table((string) $type) === null) {
            return null;
        }
        $check = Runtime::hooks()->filter("get_{$type}_metadata", [null, $objectId, $key, $single, $type]);
        if ($check !== null) {
            return $single && is_array($check) ? $check[0] : $check;
        }
        $found = false;
        $cache = Handoff::cacheGet($objectId, $type . '_meta', false, $found);
        if (!$found) {
            $cache = self::store()->all((string) $type, $objectId);
            Handoff::cacheSet($objectId, $cache, $type . '_meta');
        }
        if ($key === '' || $key === null) {
            return $cache;
        }
        if (!isset($cache[$key])) {
            return null;
        }
        $values = array_map(static fn (mixed $value): mixed => StoredObjects::maybeDecode($value), $cache[$key]);
        return $single ? $values[0] : $values;
    }

    /** An unset key's value (get_metadata_default): empty, or an empty list, as default_{type}_metadata leaves it. */
    public static function fallback(mixed $type, mixed $objectId, mixed $key, mixed $single = false): mixed
    {
        $value = Runtime::hooks()->filter("default_{$type}_metadata", [$single ? '' : [], $objectId, $key, $single, $type]);
        if ($single) {
            return $value;
        }
        $isList = is_array($value) && array_filter(array_keys($value), static fn (int|string $k): bool => !is_numeric($k)) === [];
        return $isList ? $value : [$value];
    }

    /** Whether a key is set for an object (metadata_exists), get_{type}_metadata answering first. */
    public static function exists(mixed $type, mixed $objectId, mixed $key): bool
    {
        $check = Runtime::hooks()->filter("get_{$type}_metadata", [null, (int) $objectId, $key, true, $type]);
        if ($check !== null) {
            return (bool) $check;
        }
        $all = self::raw($type, $objectId);
        return is_array($all) && isset($all[$key]);
    }

    /** A value added (add_metadata): its id, or false; refused when unique and the key is already set. */
    public static function add(mixed $type, mixed $objectId, mixed $key, mixed $value, mixed $unique = false): mixed
    {
        $spec = self::table((string) $type);
        $objectId = (int) $objectId;
        if ($spec === null || $objectId <= 0 || (string) $key === '') {
            return false;
        }
        $key = Slashes::strip($key);
        $value = Slashes::strip($value);
        $value = self::sanitize($key, $value, $type, MetaKeys::subtype((string) $type, $objectId));
        $check = Runtime::hooks()->filter("add_{$type}_metadata", [null, $objectId, $key, $value, $unique]);
        if ($check !== null) {
            return $check;
        }
        if ($unique && self::exists($type, $objectId, $key)) {
            return false;
        }
        Runtime::hooks()->action("add_{$type}_meta", [$objectId, $key, $value]);
        $id = self::store()->add((string) $type, $objectId, (string) $key, Options::toStorage($value));
        Handoff::cacheDelete($objectId, $type . '_meta');
        Runtime::hooks()->action("added_{$type}_meta", [$id, $objectId, $key, $value]);
        return $id;
    }

    /**
     * A value changed (update_metadata): every row of the key, or only those
     * holding the previous value given; a key not stored yet is added from
     * the value as it was handed in (and the new row's id answers).
     */
    public static function update(mixed $type, mixed $objectId, mixed $key, mixed $value, mixed $previous = ''): mixed
    {
        $spec = self::table((string) $type);
        $objectId = (int) $objectId;
        if ($spec === null || $objectId <= 0 || (string) $key === '') {
            return false;
        }
        [$rawKey, $passed] = [$key, $value];
        $key = Slashes::strip($key);
        $value = self::sanitize($key, Slashes::strip($value), $type, MetaKeys::subtype((string) $type, $objectId));
        $check = Runtime::hooks()->filter("update_{$type}_metadata", [null, $objectId, $key, $value, $previous]);
        if ($check !== null) {
            return (bool) $check;
        }
        $rows = self::store()->matching((string) $type, $objectId, (string) $key);
        if ($rows === []) {
            return self::add($type, $objectId, $rawKey, $passed);
        }
        $stored = Options::toStorage($value);
        $ids = Meta::idsToUpdate($rows, $stored, $previous === '' ? null : Options::toStorage($previous));
        if ($ids === []) {
            return false;
        }
        foreach ($ids as $id) {
            self::announce('update', (string) $type, [$id, $objectId, $key, $value], [$id, $objectId, $key, $value]);
        }
        self::store()->updateRows((string) $type, $ids, $stored);
        Handoff::cacheDelete($objectId, $type . '_meta');
        foreach ($ids as $id) {
            self::announce('updated', (string) $type, [$id, $objectId, $key, $value], [$id, $objectId, $key, $value]);
        }
        return true;
    }

    /** Values removed (delete_metadata): the key's, or only those holding the value given; for every object when all are asked. */
    public static function delete(mixed $type, mixed $objectId, mixed $key, mixed $value = '', mixed $all = false): bool
    {
        $spec = self::table((string) $type);
        $objectId = (int) $objectId;
        if ($spec === null || ($objectId <= 0 && !$all) || (string) $key === '') {
            return false;
        }
        $key = Slashes::strip($key);
        $value = Slashes::strip($value);
        $check = Runtime::hooks()->filter("delete_{$type}_metadata", [null, $objectId, $key, $value, $all]);
        if ($check !== null) {
            return (bool) $check;
        }
        $rows = self::store()->find((string) $type, $all ? null : $objectId, (string) $key, $value !== '' && $value !== null && $value !== false ? Options::toStorage($value) : null);
        if ($rows === []) {
            return false;
        }
        $ids = array_map(static fn (array $r): int => (int) $r['meta_id'], $rows);
        self::announce('delete', (string) $type, [$ids, $objectId, $key, $value], [$ids]);
        self::store()->deleteRows((string) $type, $ids);
        foreach ($rows as $row) {
            Handoff::cacheDelete((int) $row['object_id'], $type . '_meta');
        }
        self::announce('deleted', (string) $type, [$ids, $objectId, $key, $value], [$ids]);
        return true;
    }

    /** Whether a key is private to code (is_protected_meta): one starting with an underscore, as the filter leaves it. */
    public static function isProtected(mixed $key, mixed $type = ''): mixed
    {
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $key);
        return Runtime::hooks()->filter('is_protected_meta', [$clean !== '' && $clean[0] === '_', $key, $type]);
    }

    /** A value as its key's sanitizer leaves it (sanitize_meta), the subtype's own sanitizer first when one is hooked. */
    public static function sanitize(mixed $key, mixed $value, mixed $objectType, mixed $subtype = ''): mixed
    {
        if ((string) $subtype !== '' && Runtime::hooks()->has("sanitize_{$objectType}_meta_{$key}_for_{$subtype}")) {
            return Runtime::hooks()->filter("sanitize_{$objectType}_meta_{$key}_for_{$subtype}", [$value, $key, $objectType, $subtype]);
        }
        return Runtime::hooks()->filter("sanitize_{$objectType}_meta_{$key}", [$value, $key, $objectType]);
    }

    /**
     * A meta type's table, or null when there is none: the built-in types,
     * and a plugin's type by the table it set on $wpdb as "{type}meta".
     *
     * @return list<string>|null
     */
    public static function table(string $type): ?array
    {
        $table = Meta::knows($type) ? null : ($GLOBALS['wpdb']->{$type . 'meta'} ?? null);
        if (is_string($table) && $table !== '') {
            MetaTypes::register($type, $table);
        }
        return Meta::knows($type) ? [$type] : null;
    }

    /**
     * A user's option (get_user_option): the site's prefixed key first, then
     * the bare one; false for nobody or neither; as get_user_option_{option}
     * leaves it.
     */
    public static function userOption(mixed $option, mixed $user = 0, mixed $deprecated = ''): mixed
    {
        $user = $user === 0 || $user === '' ? (int) CurrentUser::id() : (int) $user;
        if ($user === 0) {
            return false;
        }
        $prefix = Runtime::current()->db->prefix();
        if (self::exists('user', $user, $prefix . $option)) {
            $result = self::get('user', $user, $prefix . $option, true);
        } elseif (self::exists('user', $user, $option)) {
            $result = self::get('user', $user, $option, true);
        } else {
            $result = false;
        }
        return Runtime::hooks()->filter("get_user_option_{$option}", [$result, $option, Handoff::user($user)]);
    }

    /** A post's meta (get_post_meta). */
    public static function ofPost(mixed $postId, mixed $key = '', mixed $single = false): mixed
    {
        return self::get('post', $postId, $key, $single);
    }

    /** A post's meta changed (update_post_meta). */
    public static function updatePost(mixed $postId, mixed $key, mixed $value, mixed $previous = ''): mixed
    {
        return self::update('post', $postId, $key, $value, $previous);
    }

    /** A user's meta changed (update_user_meta). */
    public static function updateUser(mixed $userId, mixed $key, mixed $value, mixed $previous = ''): mixed
    {
        return self::update('user', $userId, $key, $value, $previous);
    }

    /** Every key of a post's meta (get_post_custom), the post being read's when none is named. */
    public static function postCustom(mixed $postId = 0): mixed
    {
        return self::get('post', (int) ($postId ?: PostTags::id()));
    }

    private static function store(): Meta
    {
        return new Meta(Runtime::current()->db);
    }

    /** An action told for a meta change, and its postmeta twin for posts. */
    private static function announce(string $verb, string $type, array $args, array $legacy): void
    {
        Runtime::hooks()->action("{$verb}_{$type}_meta", $args);
        if ($type === 'post') {
            Runtime::hooks()->action("{$verb}_postmeta", $legacy);
        }
    }
}
