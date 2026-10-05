<?php

declare(strict_types=1);

namespace Minn\Http;

use InvalidArgumentException;

/**
 * Internationalized host names in ASCII: each label that is not ASCII is
 * written as "xn--" plus its Punycode (RFC 3492) and must stay under 64
 * bytes. Labels keep their case: there is no Nameprep step, as on the
 * reference ("Bücher" becomes "xn--Bcher-kva").
 */
final class Punycode
{
    public const PREFIX = 'xn--';
    public const MAX_LENGTH = 64;
    private const BASE = 36;
    private const TMIN = 1;
    private const TMAX = 26;
    private const SKEW = 38;
    private const DAMP = 700;
    private const INITIAL_BIAS = 72;
    private const INITIAL_N = 128;

    /** A whole host name, label by label. */
    public static function host(string $hostname): string
    {
        return implode('.', array_map(self::label(...), explode('.', $hostname)));
    }

    /**
     * One label in ASCII.
     *
     * @throws InvalidArgumentException with code 1 when an ASCII label is too long, 2 when the encoded one is
     */
    public static function label(string $text): string
    {
        if (preg_match('/^[\x00-\x7F]*$/', $text)) {
            if (strlen($text) >= self::MAX_LENGTH) {
                throw new InvalidArgumentException('Provided string is too long', 1);
            }
            return $text;
        }
        $encoded = self::PREFIX . self::encode($text);
        if (strlen($encoded) >= self::MAX_LENGTH) {
            throw new InvalidArgumentException('Encoded string is too long', 2);
        }
        return $encoded;
    }

    /** The Punycode of a UTF-8 string (without the prefix). */
    public static function encode(string $input): string
    {
        $points = mb_str_split($input, 1, 'UTF-8');
        $codes = array_map(static fn (string $c): int => (int) mb_ord($c, 'UTF-8'), $points);
        $output = implode('', array_filter($points, static fn (string $c): bool => strlen($c) === 1));
        $basic = strlen($output);
        $handled = $basic;
        if ($basic > 0) {
            $output .= '-';
        }
        [$n, $delta, $bias] = [self::INITIAL_N, 0, self::INITIAL_BIAS];
        while ($handled < count($codes)) {
            $m = min(array_filter($codes, static fn (int $c): bool => $c >= $n));
            $delta += ($m - $n) * ($handled + 1);
            $n = $m;
            foreach ($codes as $code) {
                if ($code < $n) {
                    $delta++;
                } elseif ($code === $n) {
                    $output .= self::digits($delta, $bias);
                    $bias = self::adapt($delta, $handled + 1, $handled === $basic ? self::DAMP : 2);
                    $delta = 0;
                    $handled++;
                }
            }
            $delta++;
            $n++;
        }
        return $output;
    }

    /** One delta as variable-length digits. */
    private static function digits(int $q, int $bias): string
    {
        $out = '';
        for ($k = self::BASE; ; $k += self::BASE) {
            $t = $k <= $bias ? self::TMIN : ($k >= $bias + self::TMAX ? self::TMAX : $k - $bias);
            if ($q < $t) {
                break;
            }
            $out .= self::digit($t + ($q - $t) % (self::BASE - $t));
            $q = intdiv($q - $t, self::BASE - $t);
        }
        return $out . self::digit($q);
    }

    private static function digit(int $d): string
    {
        return chr($d < 26 ? $d + 97 : $d + 22);
    }

    /** The next bias; the first delta is damped by DAMP, later ones by 2. */
    private static function adapt(int $delta, int $points, int $damp): int
    {
        $delta = intdiv($delta, $damp);
        $delta += intdiv($delta, $points);
        $k = 0;
        while ($delta > intdiv((self::BASE - self::TMIN) * self::TMAX, 2)) {
            $delta = intdiv($delta, self::BASE - self::TMIN);
            $k += self::BASE;
        }
        return $k + intdiv((self::BASE - self::TMIN + 1) * $delta, $delta + self::SKEW);
    }
}
