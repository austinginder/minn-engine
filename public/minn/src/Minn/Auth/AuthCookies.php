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
     * cookie's through secure_logged_in_cookie (secure only when $https,
     * which wp_set_auth_cookie answers by the home address's scheme); a
     * session made for that life when none is given; each cookie through
     * auth_cookie; then set_auth_cookie
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
        // Each cookie through auth_cookie, as the reference makes them (probe functions-formatting-doubts).
        $auth = (string) Runtime::hooks()->filter('auth_cookie', [self::mint($user, $expiration, $token, $scheme), $user->id, $expiration, $scheme, $token]);
        $loggedIn = (string) Runtime::hooks()->filter('auth_cookie', [self::mint($user, $expiration, $token, 'logged_in'), $user->id, $expiration, 'logged_in', $token]);
        Runtime::hooks()->action('set_auth_cookie', [$auth, $expire, $expiration, $user->id, $scheme, $token]);
        Runtime::hooks()->action('set_logged_in_cookie', [$loggedIn, $expire, $expiration, $user->id, 'logged_in', $token]);
        $send = (bool) Runtime::hooks()->filter('send_auth_cookies', [true, $expire, $expiration, $user->id, $scheme, $token]);
        return ['expiration' => $expiration, 'expire' => $expire, 'secure' => $secure, 'secureLoggedIn' => $secureLoggedIn, 'scheme' => $scheme, 'token' => $token, 'auth' => $auth, 'loggedIn' => $loggedIn, 'send' => $send];
    }

    /**
     * The response with the settled cookies on it (sent()); none when
     * send_auth_cookies said not to.
     *
     * @param array{expire: int, secure: bool, secureLoggedIn: bool, auth: string, loggedIn: string, send: bool} $plan from settle()
     */
    public function attach(Response $response, array $plan): Response
    {
        foreach ($plan['send'] ? $this->sent($plan) : [] as [$name, $value, $options]) {
            $response = $response->withCookie($name, $value, $options);
        }
        return $response;
    }

    /**
     * The settled cookies as the reference sends them (suite login-hooks):
     * the auth cookie, or the secure auth cookie when the sign-in is secure,
     * on the plugins path and then the admin path, then the logged-in cookie
     * on the cookie path; names, paths and domain from the cookie constants,
     * HTTP only, secure as settled, no same-site flag.
     *
     * @param array{expire: int, secure: bool, secureLoggedIn: bool, auth: string, loggedIn: string} $plan from settle()
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public function sent(array $plan): array
    {
        $hash = $this->hash();
        $constant = static fn (string $name, string $default): string => defined($name) ? (string) constant($name) : $default;
        $authName = $plan['secure'] ? $constant('SECURE_AUTH_COOKIE', 'wordpress_sec_' . $hash) : $constant('AUTH_COOKIE', 'wordpress_' . $hash);
        $options = ['expires' => $plan['expire'], 'domain' => $constant('COOKIE_DOMAIN', ''), 'httponly' => true];
        $auth = ['secure' => $plan['secure']] + $options;
        return [
            [$authName, $plan['auth'], ['path' => $constant('PLUGINS_COOKIE_PATH', '/wp-content/plugins')] + $auth],
            [$authName, $plan['auth'], ['path' => $constant('ADMIN_COOKIE_PATH', '/wp-admin')] + $auth],
            [$constant('LOGGED_IN_COOKIE', 'wordpress_logged_in_' . $hash), $plan['loggedIn'], ['path' => $constant('COOKIEPATH', '/'), 'secure' => $plan['secureLoggedIn']] + $options],
        ];
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

    /**
     * The response with every sign-in cookie expired a year back, as
     * wp_clear_auth_cookie sends them (measured on the reference's log
     * out): the auth and secure auth cookies on the admin and plugins
     * paths, the logged-in cookie on the cookie and site paths, the user's
     * settings cookies, then the auth, secure auth, user and pass cookies
     * on both paths and the post password cookie; names, paths and domain
     * from the cookie constants, no flag but the path.
     */
    public function clear(Response $response, int $userId = 0): Response
    {
        $hash = $this->hash();
        $name = static fn (string $constant, string $prefix): string => defined($constant) ? (string) constant($constant) : $prefix . $hash;
        $path = static fn (string $constant, string $default): string => defined($constant) ? (string) constant($constant) : $default;
        [$auth, $secure, $loggedIn] = [$name('AUTH_COOKIE', 'wordpress_'), $name('SECURE_AUTH_COOKIE', 'wordpress_sec_'), $name('LOGGED_IN_COOKIE', 'wordpress_logged_in_')];
        [$user, $pass] = [$name('USER_COOKIE', 'wordpressuser_'), $name('PASS_COOKIE', 'wordpresspass_')];
        [$admin, $plugins] = [$path('ADMIN_COOKIE_PATH', '/wp-admin'), $path('PLUGINS_COOKIE_PATH', '/wp-content/plugins')];
        [$cookiePath, $sitePath] = [$path('COOKIEPATH', '/'), $path('SITECOOKIEPATH', '/')];
        $options = ['expires' => time() - 365 * self::DAY, 'domain' => $path('COOKIE_DOMAIN', '')];
        foreach ([
            [$auth, $admin], [$secure, $admin], [$auth, $plugins], [$secure, $plugins],
            [$loggedIn, $cookiePath], [$loggedIn, $sitePath],
            ['wp-settings-' . $userId, $sitePath], ['wp-settings-time-' . $userId, $sitePath],
            [$auth, $cookiePath], [$auth, $sitePath], [$secure, $cookiePath], [$secure, $sitePath],
            [$user, $cookiePath], [$pass, $cookiePath], [$user, $sitePath], [$pass, $sitePath],
            ['wp-postpass_' . $hash, $cookiePath],
        ] as [$cookie, $at]) {
            $response = $response->withCookie($cookie, ' ', ['path' => $at] + $options);
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
