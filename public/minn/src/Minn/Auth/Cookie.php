<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;

/**
 * The sign-in cookies: username|expiration|token|hmac, with
 *   key  = HMAC-md5(username|fragment|expiration|token, the scheme's salt)
 *   hmac = HMAC-sha256(username|expiration|token, key)
 * and the session live in the user's session_tokens store. The engine's own
 * sign-in reads the logged_in one; check() judges any scheme's the way
 * wp_validate_auth_cookie reports it (probe auth-cookies).
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
        $hmac = (string) self::signature($username, Password::fragment($user->passwordHash), $expiration, $token);
        return "{$username}|{$expiration}|{$token}|{$hmac}";
    }

    /** The session a logged_in cookie value proves, or null when any part fails. */
    public function validate(string $value): ?Authenticated
    {
        $parts = explode('|', $value);
        if (count($parts) !== 4) {
            return null;
        }
        [$verdict, $user] = $this->check($parts[0], $parts[1], $parts[2], $parts[3], 'logged_in');
        return $verdict === 'valid' && $user !== null ? new Authenticated($user, $parts[2]) : null;
    }

    /**
     * A cookie's parts judged under a scheme, in the reference's order:
     * expired (past its expiration plus $grace), bad_username, bad_hash (an
     * unknown scheme never matches), bad_session_token, else valid with its
     * user.
     *
     * @return array{string, ?UserRecord}
     */
    public function check(string $username, string $expiration, string $token, string $hmac, string $scheme, int $grace = 0): array
    {
        if ((int) $expiration + $grace < time()) {
            return ['expired', null];
        }
        $user = $this->users->findByLogin($username);
        if ($user === null) {
            return ['bad_username', null];
        }
        $expected = self::signature($username, Password::fragment($user->passwordHash), (int) $expiration, $token, $scheme);
        if ($expected === null || !hash_equals($expected, $hmac)) {
            return ['bad_hash', null];
        }
        if (!$this->sessions->isLive($user->id, $token)) {
            return ['bad_session_token', null];
        }
        return ['valid', $user];
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

    /** A cookie's hmac under a scheme's salt, or null for a scheme with none. */
    public static function signature(string $username, string $fragment, int $expiration, string $token, string $scheme = 'logged_in'): ?string
    {
        if (!Salts::knows($scheme)) {
            return null;
        }
        $key = Salts::hash("{$username}|{$fragment}|{$expiration}|{$token}", $scheme);
        return hash_hmac('sha256', "{$username}|{$expiration}|{$token}", $key);
    }
}
