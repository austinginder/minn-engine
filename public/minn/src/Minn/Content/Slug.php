<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;
use Minn\Support\Accents;

final class Slug
{
    /**
     * A title as the reference's sanitize_title makes a slug of it when
     * saving, without the filters (the runtime's sanitize_title adds those):
     * accents spelled plainly, then the dash form, non-ASCII percent-encoded
     * up to 200 bytes.
     */
    public static function sanitize(string $text, string $locale = ''): string
    {
        return self::dashes(Accents::strip($text, $locale), true, static fn (string $title, int $length): string => self::uriEncode($title, $length, static fn (string $ascii): string => $ascii));
    }

    /**
     * Non-ASCII characters as lower-case percent escapes, a whole character
     * at a time, stopping before one would take the text past $length bytes
     * (0 for no limit); each ASCII character goes through $ascii (as it is,
     * or rawurlencode).
     *
     * @param Closure(string): string $ascii
     */
    public static function uriEncode(string $text, int $length, Closure $ascii): string
    {
        $out = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text) as $char) {
            $encoded = ord($char[0]) < 0x80 ? $ascii($char) : implode('', array_map(static fn (string $byte): string => '%' . bin2hex($byte), str_split($char)));
            if ($length > 0 && strlen($out) + strlen($encoded) > $length) {
                break;
            }
            $out .= $encoded;
        }
        return $out;
    }

    private const SAVE_DASHES = ['%c2%a0', '%e2%80%93', '%e2%80%94', '&nbsp;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;', '/'];
    private const SAVE_DROPPED = ['%c2%ad', '%c2%a1', '%c2%bf', '%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba', '%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d', '%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f', '%e2%80%a2', '%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2', '%c2%b4', '%cb%8a', '%cc%81', '%cd%81', '%cc%80', '%cc%84', '%cc%8c', '%e2%82%ac', '%c2%a3', '%e2%80%80', '%e2%80%81', '%e2%80%82', '%e2%80%83', '%e2%80%84', '%e2%80%85', '%e2%80%86', '%e2%80%87', '%e2%80%88', '%e2%80%89', '%e2%80%8a', '%e2%80%8b', '%e2%80%8c', '%e2%80%8d', '%e2%80%8e', '%e2%80%8f', '%e2%80%aa', '%e2%80%ab', '%e2%80%ac', '%e2%80%ad', '%e2%80%ae', '%e2%80%af', '%e2%81%9f', '%e3%80%80', '%ef%bb%bf'];

    /**
     * The reference's dashed title: percent escapes preserved, lower-cased,
     * non-ASCII percent-encoded, entities dropped, dots and whitespace to
     * hyphens; the save context also folds dash-like and invisible characters.
     *
     * @param Closure(string, int): string $utf8Encode percent-encodes non-ASCII bytes up to a length
     */
    /**
     * A slug cut to a byte length without splitting a multibyte character or
     * leaving a dash hanging off the end.
     */
    public static function truncate(string $slug, int $length): string
    {
        if ($length < 1 || strlen($slug) <= $length) {
            return $slug;
        }
        $cut = mb_strcut($slug, 0, $length);
        return rtrim($cut, '-');
    }

    /** The reference's dash form of a title: tags and marks stripped, spaces to dashes, and the save-time rules when saving. */
    public static function dashes(string $title, bool $forSave, Closure $utf8Encode): string
    {
        $title = strip_tags($title);
        $title = (string) preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
        $title = str_replace('%', '', $title);
        $title = (string) preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
        if (mb_check_encoding($title, 'UTF-8')) {
            $title = $utf8Encode(mb_strtolower($title, 'UTF-8'), 200);
        }
        $title = strtolower($title);
        if ($forSave) {
            $title = str_replace(self::SAVE_DASHES, '-', $title);
            // The times sign reads as an x ("1920×1080").
            $title = str_replace(['%c3%97', '×'], 'x', $title);
            $title = str_replace(self::SAVE_DROPPED, '', $title);
        }
        $title = (string) preg_replace('/&.+?;/', '', $title);
        $title = str_replace('.', '-', $title);
        $title = (string) preg_replace('/[^%a-z0-9 _-]/', '', $title);
        $title = (string) preg_replace('/\s+/', '-', $title);
        return trim((string) preg_replace('|-+|', '-', $title), '-');
    }
}
