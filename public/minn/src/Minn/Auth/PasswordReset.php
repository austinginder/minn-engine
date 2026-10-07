<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Content\Users;

/**
 * Password reset keys in the reference's storage shape: user_activation_key
 * holds "time:hash", the key itself travels in the email link and is good
 * for a day. The hash is the reference's own ($generic$, FastHash), so a
 * link either stack sent works on the other. Older shapes still verify: a
 * phpass hash (WordPress before 6.8) and the engine's former $minn$ HMAC.
 * A matching key stored without its time is expired, as the reference
 * treats it.
 */
final readonly class PasswordReset
{
    public const LIFETIME = 86400;

    public function __construct(private Users $users)
    {
    }

    /** Mints a key, stores its hash, returns the key for the link. */
    public function issue(UserRecord $user): string
    {
        [$key, $stored] = self::mint();
        $this->users->update($user->id, ['user_activation_key' => $stored]);
        return $key;
    }

    /**
     * A new key and the value that stores it (the time it was issued and its
     * hash), for a caller that saves it itself.
     *
     * @return array{0: string, 1: string}
     */
    public static function mint(): array
    {
        $key = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(24))), 0, 20);
        return [$key, time() . ':' . self::hash($key)];
    }

    /** True when the key matches the stored hash and has not expired. */
    public function verify(UserRecord $user, string $key): bool
    {
        return $this->status($user, $key) === 'valid';
    }

    /** "valid", "expired" (a matching key past its lifetime, a day unless given, or stored without a time), or "invalid". */
    public function status(UserRecord $user, string $key, int $lifetime = self::LIFETIME): string
    {
        $stored = $user->activationKey;
        if ($key === '' || $stored === '') {
            return 'invalid';
        }
        [$time, $hash] = preg_match('/^(\d+):(.+)$/', $stored, $m) ? [(int) $m[1], $m[2]] : [null, $stored];
        if (!self::matches($key, $hash)) {
            return 'invalid';
        }
        return $time === null || $time + $lifetime < time() ? 'expired' : 'valid';
    }

    /** Forgets a user's reset key. */
    public function clear(UserRecord $user): void
    {
        $this->users->update($user->id, ['user_activation_key' => '']);
    }

    /** The stored hash for a new key: the reference's own, or phpass (which it also reads) without sodium. */
    private static function hash(string $key): string
    {
        return FastHash::available() ? FastHash::hash($key) : Phpass::hash($key);
    }

    private static function matches(string $key, string $hash): bool
    {
        if (str_starts_with($hash, '$minn$')) {
            // Issued by the engine before it wrote the reference's hash.
            return hash_equals($hash, '$minn$' . hash_hmac('sha256', $key, Salts::for('nonce')));
        }
        return FastHash::verify($key, $hash) || Phpass::verify($key, $hash);
    }
}
