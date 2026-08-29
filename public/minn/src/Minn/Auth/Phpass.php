<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The portable phpass hash ($P$), from Openwall's public description of the
 * scheme: a log2 iteration count, an eight-character salt, and an iterated
 * MD5 in phpass's own base-64 alphabet. The reference still verifies these
 * for application passwords, which makes them the portable choice.
 */
final class Phpass
{
    private const ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function hash(string $password, int $log2Rounds = 11): string
    {
        $salt = self::encode(random_bytes(6), 6);
        return self::crypt($password, '$P$' . self::ALPHABET[$log2Rounds] . $salt);
    }

    public static function verify(string $password, string $hash): bool
    {
        if (strlen($hash) !== 34 || !str_starts_with($hash, '$P$')) {
            return false;
        }
        return hash_equals($hash, self::crypt($password, $hash));
    }

    private static function crypt(string $password, string $setting): string
    {
        $rounds = strpos(self::ALPHABET, $setting[3]);
        if ($rounds === false || $rounds < 7 || $rounds > 30) {
            return '*';
        }
        $count = 1 << $rounds;
        $salt = substr($setting, 4, 8);
        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);
        return substr($setting, 0, 12) . self::encode($hash, 16);
    }

    /** phpass's base-64: 6 bits at a time, low bits first, no padding. */
    private static function encode(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ALPHABET[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ALPHABET[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ALPHABET[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ALPHABET[($value >> 18) & 0x3f];
        } while ($i < $count);
        return $output;
    }
}
