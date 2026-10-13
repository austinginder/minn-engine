<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Runtime\Handoff;
use Minn\Runtime\MetaKeys;
use Minn\Runtime\Runtime;

/**
 * Whether a user may do something, as WordPress decides it: the capability
 * mapped to the primitive ones it needs (map_meta_cap, a meta key's auth
 * filters included), the user's capabilities as user_has_cap leaves them,
 * and every primitive one granted. "exist" is always granted and
 * "do_not_allow" never.
 */
final class Can
{
    /** Whether the request's user may (current_user_can). */
    public static function current(mixed $capability, mixed ...$args): mixed
    {
        return self::user(Handoff::currentUser(), $capability, ...$args);
    }

    /** Whether a user, by record or id, may (user_can), as the record's has_cap answers; anyone not a user is asked as nobody. */
    public static function user(mixed $user, mixed $capability, mixed ...$args): mixed
    {
        if (!is_object($user)) {
            $user = Handoff::user($user);
        }
        return Handoff::userRecord($user)->has_cap($capability, ...$args);
    }

    /**
     * Whether the user record has a capability (WP_User::has_cap): a number
     * is the old user level; the primitive ones it maps to must each be set
     * in the user's capabilities as user_has_cap leaves them.
     */
    public static function check(object $user, mixed $capability, mixed ...$args): bool
    {
        if (is_numeric($capability)) {
            $capability = 'level_' . (int) $capability;
        }
        $required = self::map($capability, $user->ID, ...$args);
        $granted = Runtime::hooks()->filter('user_has_cap', [$user->allcaps, $required, array_merge([$capability, $user->ID], $args), $user]);
        $granted['exist'] = true;
        unset($granted['do_not_allow']);
        foreach ((array) $required as $one) {
            if (empty($granted[$one])) {
                return false;
            }
        }
        return true;
    }

    /**
     * The primitive capabilities a capability needs for a user (map_meta_cap):
     * a meta key's own when it is a meta capability, else the engine's map,
     * the first argument read as the object's id; then map_meta_cap.
     */
    public static function map(mixed $capability, mixed $userId, mixed ...$args): mixed
    {
        $caps = MetaKeys::capabilities((string) $capability, (int) $userId, $args)
            ?? Runtime::current()->capabilities->map((string) $capability, (int) $userId, isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : null);
        return Runtime::hooks()->filter('map_meta_cap', [$caps, $capability, $userId, $args]);
    }
}
