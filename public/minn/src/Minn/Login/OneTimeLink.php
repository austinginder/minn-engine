<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Content\UserRecord;
use Minn\Content\Users;

/**
 * The one-time sign-in link hosting tools mint (the captaincore helper's
 * contract, which Cove and CaptainCore share): a token in the user's meta,
 * good once, for fifteen minutes. `wp user login` and `minn login` mint it;
 * the sign-in page spends it. Five wrong guesses at one user's token burn
 * it, as the helper's own validator does, because a helper-minted token is
 * only seven hex characters and reaches the engine too.
 */
final readonly class OneTimeLink
{
    public const TOKEN = 'cove_login_token';
    public const MINTED = 'cove_login_token_time';
    public const MISSES = 'cove_login_token_misses';
    /** Seconds a token stays good after it is minted. */
    public const LIFETIME = 15 * 60;
    /** Wrong guesses at one token before it is burnt. */
    public const MISS_LIMIT = 5;

    public function __construct(private Users $users)
    {
    }

    /**
     * Mints a fresh token for the user and hands back the link's query
     * string. The token is 128 bits, stored raw (the helper's format, which
     * it compares verbatim when a hide-login plugin keeps the engine's page
     * from answering); a database read yields the link, which the window
     * and the single use bound.
     */
    public function mint(UserRecord $user): string
    {
        $token = bin2hex(random_bytes(16));
        $this->users->setMeta($user->id, self::TOKEN, $token);
        $this->users->setMeta($user->id, self::MINTED, (string) time());
        $this->users->deleteMeta($user->id, self::MISSES);
        return 'user_id=' . $user->id . '&' . self::TOKEN . '=' . $token;
    }

    /** Whether the token given is the user's live one; a match is spent, a miss counted, a stale token cleared. */
    public function redeem(UserRecord $user, string $provided): bool
    {
        $token = (string) ($this->users->meta($user->id, self::TOKEN) ?? '');
        $minted = (int) ($this->users->meta($user->id, self::MINTED) ?? 0);
        if ($token === '' || time() - $minted > self::LIFETIME) {
            $this->clear($user->id);
            return false;
        }
        // A hash is still accepted for a link minted before the token was stored raw.
        if (hash_equals($token, $provided) || hash_equals($token, hash('sha256', $provided))) {
            $this->clear($user->id);
            return true;
        }
        $misses = (int) ($this->users->meta($user->id, self::MISSES) ?? 0) + 1;
        if ($misses >= self::MISS_LIMIT) {
            $this->clear($user->id);
        } else {
            $this->users->setMeta($user->id, self::MISSES, (string) $misses);
        }
        return false;
    }

    /** Forgets the user's token, its mint time and its miss count. */
    private function clear(int $userId): void
    {
        foreach ([self::TOKEN, self::MINTED, self::MISSES] as $key) {
            $this->users->deleteMeta($userId, $key);
        }
    }
}
