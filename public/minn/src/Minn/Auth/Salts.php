<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;

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

    /** True when every key and salt is defined and none is the installer's placeholder. */
    public static function configured(): bool
    {
        foreach (self::SCHEMES as [$key, $salt]) {
            foreach ([$key, $salt] as $constant) {
                if (!defined($constant) || (string) constant($constant) === '' || str_contains((string) constant($constant), 'put your unique phrase here')) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Whether a scheme has a key and salt of its own (auth, secure_auth, logged_in, nonce). */
    public static function knows(string $scheme): bool
    {
        return isset(self::SCHEMES[$scheme]);
    }

    /**
     * The key and salt of a scheme joined, as the reference feeds them to its HMACs. A scheme of a plugin's own
     * (or none) is salted from the site's secret key: the key, then the scheme's HMAC under it.
     */
    public static function for(string $scheme): string
    {
        if (!isset(self::SCHEMES[$scheme])) {
            $secret = self::secretKey();
            return $secret . hash_hmac('md5', $scheme, $secret);
        }
        [$key, $salt] = self::SCHEMES[$scheme];
        return (defined($key) ? (string) constant($key) : '') . (defined($salt) ? (string) constant($salt) : '');
    }

    /**
     * The key other schemes are salted from: SECRET_KEY when wp-config.php defines it, else the secret_key
     * option, made the first time it is needed (64 characters, the extra special ones among them), as the
     * reference keeps it.
     */
    private static function secretKey(): string
    {
        if (defined('SECRET_KEY') && (string) constant('SECRET_KEY') !== '') {
            return (string) constant('SECRET_KEY');
        }
        $key = (string) (Runtime::options()->get('secret_key') ?? '');
        if ($key === '') {
            $key = (string) Handoff::password(64, true, true);
            Runtime::options()->update('secret_key', $key);
        }
        return $key;
    }

    /** HMAC-md5 under the scheme's salt: the primitive every cookie and nonce is built from. */
    public static function hash(string $data, string $scheme): string
    {
        return hash_hmac('md5', $data, self::for($scheme));
    }
}
