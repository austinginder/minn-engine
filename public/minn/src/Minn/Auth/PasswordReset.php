<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Content\Users;

/**
 * Password reset keys in the reference's storage shape: user_activation_key
 * holds "time:hash", the key itself travels in the email link and is good
 * for a day. The hash is the engine's own ($minn$ over the nonce salt); a
 * key the reference issued ($generic$) is not readable here, so it is
 * refused and the reader asks for a fresh link.
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
        $key = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(24))), 0, 20);
        $this->users->update((int) $user['ID'], ['user_activation_key' => time() . ':' . self::hash($key)]);
        return $key;
    }

    /** True when the key matches the stored hash and has not expired. */
    public function verify(UserRecord $user, string $key): bool
    {
        return $this->status($user, $key) === 'valid';
    }

    /** "valid", "expired" (a matching key past its day), or "invalid". */
    public function status(UserRecord $user, string $key): string
    {
        $stored = (string) ($user['user_activation_key'] ?? '');
        if ($key === '' || !preg_match('/^(\d+):(.+)$/', $stored, $m)) {
            return 'invalid';
        }
        if (!hash_equals($m[2], self::hash($key))) {
            return 'invalid';
        }
        return (int) $m[1] + self::LIFETIME < time() ? 'expired' : 'valid';
    }

    public function clear(UserRecord $user): void
    {
        $this->users->update((int) $user['ID'], ['user_activation_key' => '']);
    }

    private static function hash(string $key): string
    {
        return '$minn$' . hash_hmac('sha256', $key, Salts::for('nonce'));
    }
}
