<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The site's own secret material, read from the constants wp-config.php
 * defines, so the engine keys off exactly what the install used.
 */
final class Salts
{
    private const SCHEMES = [
        'logged_in' => ['LOGGED_IN_KEY', 'LOGGED_IN_SALT'],
        'nonce' => ['NONCE_KEY', 'NONCE_SALT'],
        'auth' => ['AUTH_KEY', 'AUTH_SALT'],
        'secure_auth' => ['SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'],
    ];

    public static function for(string $scheme): string
    {
        [$key, $salt] = self::SCHEMES[$scheme];
        return (defined($key) ? (string) constant($key) : '') . (defined($salt) ? (string) constant($salt) : '');
    }

    /** HMAC-md5 under the scheme's salt: the primitive every cookie and nonce is built from. */
    public static function hash(string $data, string $scheme): string
    {
        return hash_hmac('md5', $data, self::for($scheme));
    }
}
