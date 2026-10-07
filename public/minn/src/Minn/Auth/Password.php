<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The stored password scheme. A modern "$wp$2y$..." value is bcrypt over
 * base64(HMAC-sha384(password, "wp-sha384")) at PHP's default cost (10 up
 * to PHP 8.3, 12 from 8.4) unless the site's options say otherwise; under
 * another algorithm (argon2id) the hash is that algorithm's own. Legacy
 * phpass "$P$" hashes and any other password_hash() value still verify, and
 * a sign-in replaces whatever needsRehash() says is outdated (probe
 * password-rehash).
 */
final class Password
{
    /** Whether a password matches a stored hash: the "$wp$" scheme, legacy phpass, or any hash password_hash() makes. */
    public static function verify(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$wp$')) {
            return password_verify(self::prehash($password), substr($hash, 3));
        }
        if (str_starts_with($hash, '$P$')) {
            return PortableHash::verify($password, $hash);
        }
        return $hash !== '' && password_verify($password, $hash);
    }

    /**
     * A stored hash: bcrypt in the "$wp$" scheme over the pre-hash, or, under
     * any other algorithm, that algorithm's own hash of the password.
     *
     * @param array<string, mixed> $options password_hash() options (cost, ...); PHP's defaults when empty
     */
    public static function hash(string $password, string $algorithm = PASSWORD_BCRYPT, array $options = []): string
    {
        if ($algorithm === PASSWORD_BCRYPT) {
            return '$wp' . password_hash(self::prehash($password), PASSWORD_BCRYPT, $options);
        }
        return password_hash($password, $algorithm, $options);
    }

    /**
     * Whether a stored hash is not the one these settings would make: under
     * bcrypt, anything outside the "$wp$" scheme or at another cost; under
     * another algorithm, whatever PHP says is outdated for it.
     *
     * @param array<string, mixed> $options
     */
    public static function needsRehash(string $hash, string $algorithm = PASSWORD_BCRYPT, array $options = []): bool
    {
        if ($algorithm === PASSWORD_BCRYPT) {
            return !str_starts_with($hash, '$wp$') || password_needs_rehash(substr($hash, 3), PASSWORD_BCRYPT, $options);
        }
        return password_needs_rehash($hash, $algorithm, $options);
    }

    private static function prehash(string $password): string
    {
        return base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
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
