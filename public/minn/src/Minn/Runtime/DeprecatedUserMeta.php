<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Serialized;

/**
 * update_usermeta() and delete_usermeta(), deprecated since 3.0.0 and kept
 * for the plugins that still call them (probes hook-inputs,
 * nav-pomo-rest-doubts): they work on every row of a key, and their actions
 * name the key's first row and carry the value as stored.
 */
final class DeprecatedUserMeta
{
    /**
     * A user's meta key set to one value on every row it has (a new row when
     * it has none; an empty value deletes the key). update_usermeta fires
     * before a change, updated_usermeta or added_usermeta after the caches
     * are cleaned; false when the first row already holds the value.
     */
    public static function update(mixed $userId, mixed $metaKey, mixed $metaValue): bool
    {
        Notices::deprecated('function', 'update_usermeta', '3.0.0', 'update_user_meta()');
        $userId = (int) $userId;
        if (!$userId) {
            return false;
        }
        $key = self::key($metaKey);
        $stored = (string) Serialized::maybeEncode(is_string($metaValue) ? stripslashes($metaValue) : $metaValue);
        if (empty($stored)) {
            return self::delete($userId, $key);
        }
        $meta = new Meta(Runtime::current()->db);
        $rows = $meta->matching('user', $userId, $key);
        if ($rows === []) {
            $id = $meta->add('user', $userId, $key, $stored);
            self::clean($userId);
            Runtime::hooks()->action('added_usermeta', [$id, $userId, $key, $stored]);
            return true;
        }
        $first = (string) $rows[0]['meta_id'];
        Runtime::hooks()->action('update_usermeta', [$first, $userId, $key, $stored]);
        if ($rows[0]['meta_value'] === $stored) {
            return false;
        }
        $meta->updateRows('user', array_column($rows, 'meta_id'), $stored);
        self::clean($userId);
        Runtime::hooks()->action('updated_usermeta', [$first, $userId, $key, $stored]);
        return true;
    }

    /**
     * A user's meta key deleted: every row, or those holding the value when
     * one is given (an array serialized, a string trimmed). While the key has
     * a row, delete_usermeta and deleted_usermeta fire with its first row and
     * that value, whether or not a row matched; true unless there is no user.
     */
    public static function delete(mixed $userId, mixed $metaKey, mixed $metaValue = ''): bool
    {
        Notices::deprecated('function', 'delete_usermeta', '3.0.0', 'delete_user_meta()');
        $userId = (int) $userId;
        if (!$userId) {
            return false;
        }
        $key = self::key($metaKey);
        $value = is_array($metaValue) || is_object($metaValue) ? serialize($metaValue) : trim((string) $metaValue);
        $meta = new Meta(Runtime::current()->db);
        $first = $meta->matching('user', $userId, $key)[0]['meta_id'] ?? null;
        if ($first === null) {
            return true;
        }
        Runtime::hooks()->action('delete_usermeta', [(string) $first, $userId, $key, $value]);
        $meta->deleteRows('user', array_column($meta->find('user', $userId, $key, empty($value) ? null : $value), 'meta_id'));
        self::clean($userId);
        Runtime::hooks()->action('deleted_usermeta', [(string) $first, $userId, $key, $value]);
        return true;
    }

    /** A meta key as the old functions read it: letters, digits and underscores. */
    private static function key(mixed $metaKey): string
    {
        return (string) preg_replace('|[^a-z0-9_]|i', '', (string) $metaKey);
    }

    /** The user's caches cleaned, its meta among them. */
    private static function clean(int $userId): void
    {
        UserWrites::cleanCache($userId);
        Handoff::cacheDelete($userId, 'user_meta');
    }
}
