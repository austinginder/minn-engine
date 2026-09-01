<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The stored password scheme. A modern "$wp$2y$..." value is bcrypt over
 * base64(HMAC-sha384(password, "wp-sha384")); a bare "$2y$" value is plain
 * bcrypt. Legacy phpass "$P$" hashes are not verified.
 */
final class Password
{
    /** Whether a password matches a stored hash of either scheme. */
    public static function verify(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$wp$')) {
            $pre = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
            return password_verify($pre, substr($hash, 3));
        }
        if (str_starts_with($hash, '$2y$')) {
            return password_verify($password, $hash);
        }
        return false;
    }

    /** A stored hash in the modern scheme: "$wp" plus bcrypt over the pre-hash. */
    public static function hash(string $password): string
    {
        $pre = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
        return '$wp' . password_hash($pre, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * The four characters of the hash the cookie key is derived from: the
     * LAST four for a "$wp$" hash, offset 8 for legacy phpass. Getting this
     * wrong yields a cookie that verifies nowhere while every other check
     * passes, so both branches are pinned by the auth suite.
     */
    public static function fragment(string $hash): string
    {
        return str_starts_with($hash, '$wp$') ? substr($hash, -4) : substr($hash, 8, 4);
    }
}
