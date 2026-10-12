<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Runtime\Runtime;

/**
 * The wp_rest nonce: ten characters of HMAC-md5(tick|wp_rest|uid|token)
 * under the nonce salt, accepted for the current tick and the one before.
 */
final class Nonce
{
    private const LIFETIME = 86400;

    /**
     * How long a nonce for an action lives, in seconds (nonce_life, handed
     * the action as the caller gave it; probe filter-inputs-3). A plugin's
     * filter reaches every nonce the engine makes and checks once plugins
     * have loaded; a lifetime under a second keeps the default.
     */
    public static function lifetime(int|string $action): int
    {
        $lifetime = (int) Runtime::hooks()->filter('nonce_life', [self::LIFETIME, $action]);
        return $lifetime > 0 ? $lifetime : self::LIFETIME;
    }

    /** The current half-life tick a nonce for an action is bound to. */
    public static function tick(int|string $action = -1): float
    {
        return ceil(time() / (self::lifetime($action) / 2));
    }

    /** A wp_rest nonce for a user and session token at the current tick. */
    public static function create(int $userId, string $token, int|string $action = 'wp_rest'): string
    {
        return self::at(self::tick($action), $userId, $token, (string) $action);
    }

    /** Whether a nonce is valid at this tick or the one before. */
    public static function verify(string $nonce, int $userId, string $token, string $action = 'wp_rest'): bool
    {
        $tick = self::tick($action);
        foreach ([$tick, $tick - 1] as $candidate) {
            if (hash_equals(self::at($candidate, $userId, $token, $action), $nonce)) {
                return true;
            }
        }
        return false;
    }

    /** The nonce for a given tick. */
    public static function at(float $tick, int $userId, string $token, string $action): string
    {
        return substr(Salts::hash("{$tick}|{$action}|{$userId}|{$token}", 'nonce'), -12, 10);
    }
}
