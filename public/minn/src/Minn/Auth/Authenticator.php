<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Request;

/**
 * Resolves the current user two ways. A page load carries the cookie alone;
 * a REST call must also carry a nonce bound to the same session.
 */
final readonly class Authenticator
{
    public function __construct(
        private Cookie $cookie,
        private Users $users,
    ) {
    }

    public static function fromDb(Db $db): self
    {
        $users = new Users($db);
        return new self(new Cookie($db, $users, new Sessions($users)), $users);
    }

    /** @param array<string, string> $cookies */
    public function session(array $cookies): Authenticated|AuthFailure
    {
        $value = $this->cookie->fromJar($cookies);
        if ($value === null) {
            return AuthFailure::notLoggedIn();
        }
        return $this->cookie->validate($value) ?? AuthFailure::notLoggedIn();
    }

    /** @param array<string, string> $cookies */
    public function rest(array $cookies, ?string $nonce): Authenticated|AuthFailure
    {
        $session = $this->session($cookies);
        if ($session instanceof AuthFailure) {
            return $session;
        }
        if ($nonce === null || !Nonce::verify($nonce, $session->id(), $session->token)) {
            return AuthFailure::invalidNonce();
        }
        return $session;
    }

    public function restFromRequest(Request $request): Authenticated|AuthFailure
    {
        return $this->rest($request->cookies, $request->header('x-wp-nonce') ?? $request->query('_wpnonce'));
    }

    /** Username and password to a user row; no session is created here. */
    public function login(string $username, string $password): ?array
    {
        $user = $this->users->findByLogin($username) ?? (str_contains($username, '@') ? $this->users->findByEmail($username) : null);
        if ($user === null || !Password::verify($password, (string) $user['user_pass'])) {
            return null;
        }
        return $user;
    }
}
