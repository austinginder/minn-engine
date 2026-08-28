<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Db;
use Minn\Http\Response;

/**
 * The three cookies a sign-in sets: the auth cookie on the admin and
 * plugins paths (the secure variant over HTTPS, keyed off that scheme's
 * salt) and the logged_in cookie on the site root, which is the one REST
 * reads. Every value shares the username|expiration|token|hmac shape.
 */
final readonly class AuthCookies
{
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

    public function attach(Response $response, array $user, int $expiration, string $token, bool $secure): Response
    {
        $hash = $this->hash();
        $authName = ($secure ? 'wordpress_sec_' : 'wordpress_') . $hash;
        $authValue = self::mint($user, $expiration, $token, $secure ? 'secure_auth' : 'auth');
        $loggedIn = $this->cookie->mint($user, $expiration, $token);
        $common = ['expires' => $expiration, 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax'];
        return $response
            ->withCookie($authName, $authValue, ['path' => '/wp-admin'] + $common)
            ->withCookie($authName, $authValue, ['path' => '/wp-content/plugins'] + $common)
            ->withCookie('wordpress_logged_in_' . $hash, $loggedIn, ['path' => '/'] + $common);
    }

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
    public static function mint(array $user, int $expiration, string $token, string $scheme): string
    {
        $username = (string) $user['user_login'];
        $fragment = Password::fragment((string) $user['user_pass']);
        $key = Salts::hash("{$username}|{$fragment}|{$expiration}|{$token}", $scheme);
        $hmac = hash_hmac('sha256', "{$username}|{$expiration}|{$token}", $key);
        return "{$username}|{$expiration}|{$token}|{$hmac}";
    }
}
