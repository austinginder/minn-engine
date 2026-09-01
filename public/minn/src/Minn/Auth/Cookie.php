<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;

/**
 * The logged_in auth cookie: username|expiration|token|hmac, with
 *   key  = HMAC-md5(username|fragment|expiration|token, logged_in salt)
 *   hmac = HMAC-sha256(username|expiration|token, key)
 * and the session live in the user's session_tokens store.
 */
final readonly class Cookie
{
    public function __construct(
        private Db $db,
        private Users $users,
        private Sessions $sessions,
    ) {
    }

    /** The cookie name this site uses: a hash of the siteurl option. */
    public function name(): string
    {
        return 'wordpress_logged_in_' . md5($this->db->option('siteurl') ?? '');
    }

    /** A sign-in cookie value for a user, expiry, and session token. */
    public function mint(UserRecord $user, int $expiration, string $token): string
    {
        $username = $user->login;
        $hmac = self::signature($username, Password::fragment($user->passwordHash), $expiration, $token);
        return "{$username}|{$expiration}|{$token}|{$hmac}";
    }

    /** The session a cookie value proves, or null when any part fails. */
    public function validate(string $value): ?Authenticated
    {
        $parts = explode('|', $value);
        if (count($parts) !== 4) {
            return null;
        }
        [$username, $expiration, $token, $hmac] = $parts;
        if ((int) $expiration < time()) {
            return null;
        }
        $user = $this->users->findByLogin($username);
        if ($user === null) {
            return null;
        }
        $expected = self::signature($username, Password::fragment($user->passwordHash), (int) $expiration, $token);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }
        if (!$this->sessions->isLive($user->id, $token)) {
            return null;
        }
        return new Authenticated($user, $token);
    }

    /**
     * The logged_in cookie from a request's cookie jar: the canonical name
     * first, then any logged_in cookie, since tooling may send a name
     * derived from a different host.
     *
     * @param array<string, string> $cookies
     */
    public function fromJar(array $cookies): ?string
    {
        $value = $cookies[$this->name()] ?? null;
        if ($value !== null) {
            return $value;
        }
        foreach ($cookies as $name => $candidate) {
            if (str_starts_with((string) $name, 'wordpress_logged_in_')) {
                return $candidate;
            }
        }
        return null;
    }

    private static function signature(string $username, string $fragment, int $expiration, string $token): string
    {
        $key = Salts::hash("{$username}|{$fragment}|{$expiration}|{$token}", 'logged_in');
        return hash_hmac('sha256', "{$username}|{$expiration}|{$token}", $key);
    }
}
