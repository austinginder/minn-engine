<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Request;
use Minn\Runtime\Runtime;

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

    /** An authenticator over the shared database door. */
    public static function fromDb(Db $db): self
    {
        $users = new Users($db);
        return new self(new Cookie($db, $users, new Sessions($users)), $users);
    }

    /**
     * The session the cookie jar carries, or why there is none.
     *
     * @param array<string, string> $cookies
     */
    public function session(array $cookies): Authenticated|AuthFailure
    {
        $value = $this->cookie->fromJar($cookies);
        if ($value === null) {
            return AuthFailure::notLoggedIn();
        }
        return $this->cookie->validate($value) ?? AuthFailure::notLoggedIn();
    }

    /**
     * The session for a REST call: the cookie, then the nonce.
     *
     * @param array<string, string> $cookies
     */
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

    /**
     * A REST caller: the cookie session with its nonce first; failing that,
     * HTTP Basic credentials carrying an application password, which need no
     * nonce. A wrong Basic pair is reported as not logged in.
     */
    public function restFromRequest(Request $request): Authenticated|AuthFailure
    {
        $session = $this->rest($request->cookies, $request->header('x-wp-nonce') ?? $request->query('_wpnonce'));
        if ($session instanceof Authenticated || $session->code === 'rest_cookie_invalid_nonce') {
            return $session;
        }
        return $this->applicationPassword($request) ?? $session;
    }

    /** The user an Authorization: Basic header's application password unlocks, with the use recorded. */
    public function applicationPassword(Request $request): ?Authenticated
    {
        if (!self::applicationPasswordsAvailable($request)) {
            return null;
        }
        $credentials = self::basicCredentials($request);
        if ($credentials === null) {
            return null;
        }
        [$login, $password] = $credentials;
        $user = $this->users->findByLogin($login) ?? (str_contains($login, '@') ? $this->users->findByEmail($login) : null);
        if ($user === null || !self::applicationPasswordsAvailableFor($user)) {
            return null;
        }
        $passwords = new ApplicationPasswords($this->users);
        $record = $passwords->verify($user->id, $password);
        if ($record === null) {
            return null;
        }
        $record = $passwords->touch($user->id, (string) $record['uuid'], $request->remoteAddress) ?? $record;
        return new Authenticated($user, '', $record);
    }

    /**
     * The login and password an Authorization: Basic header carries, or null.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function basicCredentials(Request $request): ?array
    {
        $header = $request->header('authorization') ?? '';
        if (!preg_match('/^Basic\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        $decoded = base64_decode($m[1], true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }
        [$login, $password] = explode(':', $decoded, 2);
        return [$login, $password];
    }

    /** Application passwords need HTTPS, unless plugin code says otherwise through the reference's filter. */
    public static function applicationPasswordsAvailable(Request $request): bool
    {
        $available = $request->secure;
        if (Runtime::booted()) {
            $available = (bool) Runtime::hooks()->filter('wp_is_application_passwords_available', [$available]);
        }
        return $available;
    }

    /**
     * Whether Basic auth may sign this user in; the runtime's filter has the last word.
     *
     * @param array<string, mixed> $user
     */
    public static function applicationPasswordsAvailableFor(UserRecord $user): bool
    {
        if (!Runtime::booted()) {
            return true;
        }
        return (bool) Runtime::hooks()->filter('wp_is_application_passwords_available_for_user', [true, (object) $user]);
    }

    /** A real hash of a password nobody knows, checked against when the user does not exist so the answer takes as long either way. */
    private const NOBODY = '$wp$2y$10$q/tQZiHVAKXuCoJIeZTH0.rb6Otms4vDKv0NrPeGKdvracPiLPy4W';

    /** Username and password to a user row, an outdated hash replaced as the sign-in succeeds; no session is created here. */
    public function login(string $username, string $password): ?UserRecord
    {
        $user = $this->users->findByLogin($username) ?? (str_contains($username, '@') ? $this->users->findByEmail($username) : null);
        if (!Password::verify($password, $user?->passwordHash ?? self::NOBODY) || $user === null) {
            return null;
        }
        if (Password::needsRehash($user->passwordHash)) {
            $this->users->setPassword($user->id, Password::hash($password));
            return $this->users->find($user->id);
        }
        return $user;
    }
}
