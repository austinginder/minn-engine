<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Db;
use Minn\Http\Response;
use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;

/**
 * The three cookies a sign-in sets: the auth cookie on the admin and
 * plugins paths (the secure variant over HTTPS, keyed off that scheme's
 * salt) and the logged_in cookie on the site root, which is the one REST
 * reads. Every value shares the username|expiration|token|hmac shape.
 */
final readonly class AuthCookies
{
    private const DAY = 86400;

    private const HOUR = 3600;

    public function __construct(
        private Db $db,
        private Cookie $cookie,
    ) {
    }

    /** The hash suffixed onto every cookie name: md5 of the siteurl option. */
    public function hash(): string
    {
        return md5($this->db->option('siteurl') ?? '');
    }

    /**
     * The sign-in cookies as the reference settles them (suite login-hooks):
     * their life through auth_cookie_expiration (two days, or fourteen when
     * remembered, the cookies then kept twelve hours past it); the auth
     * cookie's security through secure_auth_cookie, and the logged-in
     * cookie's through secure_logged_in_cookie (secure only over HTTPS); a
     * session made for that life when none is given; then set_auth_cookie
     * and set_logged_in_cookie told the values, and send_auth_cookies asked
     * whether to send them. Signing in always runs under the booted
     * runtime (its sessions are WordPress's), so the hooks always run.
     *
     * @return array{expiration: int, expire: int, secure: bool, secureLoggedIn: bool, scheme: string, token: string, auth: string, loggedIn: string, send: bool}
     */
    public function settle(UserRecord $user, int $days, bool $secure, bool $https, string $token = ''): array
    {
        $remember = $days > 2;
        $expiration = time() + (int) Runtime::hooks()->filter('auth_cookie_expiration', [$days * self::DAY, $user->id, $remember]);
        $expire = $remember ? $expiration + 12 * self::HOUR : 0;
        $secure = (bool) Runtime::hooks()->filter('secure_auth_cookie', [$secure, $user->id]);
        $secureLoggedIn = (bool) Runtime::hooks()->filter('secure_logged_in_cookie', [$secure && $https, $user->id, $secure]);
        // The session is made as WordPress makes it, so session_token_manager and attach_session_information have their say.
        $token = $token !== '' ? $token : Handoff::sessionTokens($user->id)->create($expiration);
        $scheme = $secure ? 'secure_auth' : 'auth';
        $auth = self::mint($user, $expiration, $token, $scheme);
        $loggedIn = self::mint($user, $expiration, $token, 'logged_in');
        Runtime::hooks()->action('set_auth_cookie', [$auth, $expire, $expiration, $user->id, $scheme, $token]);
        Runtime::hooks()->action('set_logged_in_cookie', [$loggedIn, $expire, $expiration, $user->id, 'logged_in', $token]);
        $send = (bool) Runtime::hooks()->filter('send_auth_cookies', [true, $expire, $expiration, $user->id, $scheme, $token]);
        return ['expiration' => $expiration, 'expire' => $expire, 'secure' => $secure, 'secureLoggedIn' => $secureLoggedIn, 'scheme' => $scheme, 'token' => $token, 'auth' => $auth, 'loggedIn' => $loggedIn, 'send' => $send];
    }

    /**
     * The response with the settled cookies on it: the auth cookie on the
     * admin and plugins paths, the logged-in cookie on the site root; none
     * when send_auth_cookies said not to.
     *
     * @param array{expire: int, secure: bool, secureLoggedIn: bool, auth: string, loggedIn: string, send: bool} $plan from settle()
     */
    public function attach(Response $response, array $plan): Response
    {
        if (!$plan['send']) {
            return $response;
        }
        $hash = $this->hash();
        $authName = ($plan['secure'] ? 'wordpress_sec_' : 'wordpress_') . $hash;
        $auth = ['expires' => $plan['expire'], 'httponly' => true, 'secure' => $plan['secure'], 'samesite' => 'Lax'];
        $loggedIn = ['expires' => $plan['expire'], 'httponly' => true, 'secure' => $plan['secureLoggedIn'], 'samesite' => 'Lax'];
        return $response
            ->withCookie($authName, $plan['auth'], ['path' => '/wp-admin'] + $auth)
            ->withCookie($authName, $plan['auth'], ['path' => '/wp-content/plugins'] + $auth)
            ->withCookie('wordpress_logged_in_' . $hash, $plan['loggedIn'], ['path' => '/'] + $loggedIn);
    }

    /**
     * Whether signing out clears the cookies: clear_auth_cookie told, then
     * send_auth_cookies asked with no cookie in hand, as the reference asks
     * them (suite login-hooks).
     */
    public function clearing(): bool
    {
        Runtime::hooks()->action('clear_auth_cookie', []);
        return (bool) Runtime::hooks()->filter('send_auth_cookies', [true, 0, 0, 0, '', '']);
    }

    /** The response with every sign-in cookie expired. */
    public function clear(Response $response): Response
    {
        $hash = $this->hash();
        $past = ['expires' => time() - 3600, 'httponly' => true];
        foreach ([
            ['wordpress_' . $hash, '/wp-admin'],
            ['wordpress_' . $hash, '/wp-content/plugins'],
            ['wordpress_sec_' . $hash, '/wp-admin'],
            ['wordpress_sec_' . $hash, '/wp-content/plugins'],
            ['wordpress_logged_in_' . $hash, '/'],
        ] as [$name, $path]) {
            $response = $response->withCookie($name, ' ', ['path' => $path] + $past);
        }
        return $response;
    }

    /** A cookie value under any scheme's salt (auth, secure_auth, logged_in). */
    public static function mint(UserRecord $user, int $expiration, string $token, string $scheme): string
    {
        $username = $user->login;
        $hmac = (string) Cookie::signature($username, Password::fragment($user->passwordHash), $expiration, $token, $scheme);
        return "{$username}|{$expiration}|{$token}|{$hmac}";
    }
}
