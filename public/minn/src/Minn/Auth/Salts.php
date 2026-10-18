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
            $secret = self::secret('SECRET_KEY', 'secret_key');
            return $secret . hash_hmac('md5', $scheme, $secret);
        }
        [$key, $salt] = self::SCHEMES[$scheme];
        if (!defined($key) && !defined($salt)) {
            return '';
        }
        return self::secret($key, "{$scheme}_key") . self::secret($salt, "{$scheme}_salt");
    }

    /**
     * A key or salt: the wp-config.php constant, unless it is empty, the installer's placeholder, or a value
     * another key or salt there shares (the reference leaves all three out). Then the option of that name,
     * made the first time it is needed (64 characters, the extra special ones among them), as the reference
     * keeps it.
     */
    private static function secret(string $constant, string $option): string
    {
        $value = defined($constant) ? (string) constant($constant) : '';
        if ($value !== '' && !str_contains($value, 'put your unique phrase here') && !in_array($value, self::shared(), true)) {
            return $value;
        }
        $kept = (string) (Runtime::options()->get($option) ?? '');
        if ($kept === '') {
            $kept = (string) Handoff::password(64, true, true);
            Runtime::options()->update($option, $kept);
        }
        return $kept;
    }

    /** @return list<string> the values more than one of the site's keys and salts (SECRET_KEY among them) hold */
    private static function shared(): array
    {
        $values = [];
        foreach (['SECRET_KEY', ...array_merge(...array_values(self::SCHEMES))] as $constant) {
            if (defined($constant) && (string) constant($constant) !== '') {
                $values[] = (string) constant($constant);
            }
        }
        return array_keys(array_filter(array_count_values($values), static fn (int $count): bool => $count > 1));
    }

    /** HMAC-md5 under the scheme's salt: the primitive every cookie and nonce is built from. */
    public static function hash(string $data, string $scheme): string
    {
        return hash_hmac('md5', $data, self::for($scheme));
    }
}
