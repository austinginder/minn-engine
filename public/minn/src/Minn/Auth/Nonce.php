<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The wp_rest nonce: ten characters of HMAC-md5(tick|wp_rest|uid|token)
 * under the nonce salt, accepted for the current tick and the one before.
 */
final class Nonce
{
    private const LIFETIME = 86400;

    /** The current half-life tick a nonce is bound to. */
    public static function tick(): float
    {
        return ceil(time() / (self::LIFETIME / 2));
    }

    /** A wp_rest nonce for a user and session token at the current tick. */
    public static function create(int $userId, string $token, string $action = 'wp_rest'): string
    {
        return self::at(self::tick(), $userId, $token, $action);
    }

    /** Whether a nonce is valid at this tick or the one before. */
    public static function verify(string $nonce, int $userId, string $token, string $action = 'wp_rest'): bool
    {
        $tick = self::tick();
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
