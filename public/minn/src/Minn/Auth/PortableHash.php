<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The portable phpass hash ("$P$"), from the published algorithm: an
 * iteration count character, an eight character salt, and MD5 iterated
 * over salt and password, encoded in phpass's own base64 alphabet. The
 * reference stores it in the post-password cookie (2^13 iterations, and it
 * accepts nothing weaker there), and still verifies it for application
 * passwords and reset keys made before its fast hash, which is why both
 * directions of each are accepted. Its pieces are public for phpass's own
 * class, PasswordHash (probe identity-internals): the base64 encoding,
 * both kinds of salt, and the hash with phpass's failure markers.
 */
final class PortableHash
{
    public const ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** bcrypt's own base64 alphabet. */
    private const BLOWFISH_ALPHABET = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    /** A portable hash of a password, 2^$countLog2 iterations (the reference's cookies carry 2^13). */
    public static function hash(string $password, int $countLog2 = 13): string
    {
        return self::crypt($password, self::salt(random_bytes(6), $countLog2));
    }

    /** A portable setting: "$P$", the count's character, and six bytes of salt encoded. */
    public static function salt(string $input, int $countLog2): string
    {
        return '$P$' . self::ALPHABET[$countLog2] . self::encode($input, 6);
    }

    /** A bcrypt setting: "$2a$", the two-digit cost, and the first sixteen bytes in bcrypt's base64. */
    public static function blowfishSalt(string $input, int $countLog2): string
    {
        $bits = '';
        foreach (str_split(substr($input, 0, 16)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $salt = '';
        foreach (str_split($bits, 6) as $chunk) {
            $salt .= self::BLOWFISH_ALPHABET[(int) bindec(str_pad($chunk, 6, '0'))];
        }
        return sprintf('$2a$%02d$', $countLog2) . $salt;
    }

    /** Whether a password matches a portable hash from the published algorithm. */
    public static function verify(string $password, string $hash): bool
    {
        if (strlen($hash) !== 34 || !str_starts_with($hash, '$P$')) {
            return false;
        }
        return hash_equals(self::crypt($password, substr($hash, 0, 12)), $hash);
    }

    /**
     * The hash for a setting; for a setting it cannot use ("$P$" or "$H$",
     * a count from 7 to 30, eight characters of salt) phpass's marker "*0",
     * or "*1" when the setting is itself "*0".
     */
    public static function crypt(string $password, string $setting): string
    {
        $failed = str_starts_with($setting, '*0') ? '*1' : '*0';
        $countLog2 = strlen($setting) > 3 ? strpos(self::ALPHABET, $setting[3]) : false;
        $salt = substr($setting, 4, 8);
        if (!in_array(substr($setting, 0, 3), ['$P$', '$H$'], true) || $countLog2 === false || $countLog2 < 7 || $countLog2 > 30 || strlen($salt) !== 8) {
            return $failed;
        }
        $count = 1 << $countLog2;
        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);
        return substr($setting, 0, 12) . self::encode($hash, 16);
    }

    /** phpass's base64: little-endian groups of three bytes, $count bytes of the input. */
    public static function encode(string $input, int $count): string
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
