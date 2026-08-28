<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The portable phpass hash ("$P$"), the shape the reference stores in
 * the post-password cookie: an iteration count character, an eight
 * character salt, and MD5 iterated over salt and password, encoded in
 * phpass's own base64 alphabet. Implemented from the published algorithm
 * so cookies are accepted in both directions.
 */
final class PortableHash
{
    private const ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** The reference's cookies carry 2^13 iterations; it accepts nothing weaker. */
    public static function hash(string $password, int $countLog2 = 13): string
    {
        $salt = self::encode(random_bytes(6), 6);
        return self::crypt($password, '$P$' . self::ALPHABET[$countLog2] . $salt);
    }

    public static function verify(string $password, string $hash): bool
    {
        if (strlen($hash) !== 34 || !str_starts_with($hash, '$P$')) {
            return false;
        }
        return hash_equals(self::crypt($password, substr($hash, 0, 12)), $hash);
    }

    private static function crypt(string $password, string $setting): string
    {
        $countLog2 = strpos(self::ALPHABET, $setting[3]);
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return '*';
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);
        return substr($setting, 0, 12) . self::encode($hash, 16);
    }

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
