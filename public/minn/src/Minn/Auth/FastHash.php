<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The reference's hash for high-entropy secrets ("$generic$", WordPress 6.8
 * and later): a 30-byte keyed BLAKE2b digest (sodium's generic hash, keyed
 * by the scheme's fixed label) in URL-safe base64 without padding. Reset
 * keys and application passwords are stored this way, so a secret minted
 * on either stack verifies on the other. Pinned against hashes the
 * reference made (tests/unit/auth-hashes.php). Without the sodium
 * extension nothing can be hashed or verified this way.
 */
final class FastHash
{
    public const PREFIX = '$generic$';
    private const KEY = 'wp_fast_hash_6.8+';

    /** Whether this PHP can make and check these hashes (the sodium extension). */
    public static function available(): bool
    {
        return function_exists('sodium_crypto_generichash');
    }

    /** The "$generic$" hash of a secret. */
    public static function hash(string $secret): string
    {
        return self::PREFIX . sodium_bin2base64(sodium_crypto_generichash($secret, self::KEY, 30), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /** Whether a secret matches a "$generic$" hash; false for any other kind of hash. */
    public static function verify(string $secret, string $hash): bool
    {
        return str_starts_with($hash, self::PREFIX) && self::available() && hash_equals($hash, self::hash($secret));
    }
}
