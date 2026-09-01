<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * HTML special-character encoding with the reference's quote styles and its
 * "do not double encode" rule: an ampersand that already starts a known
 * named or numeric entity stays as it is.
 */
final class Entities
{
    /**
     * The reference's special-characters escaping, with its quote styles and double-encoding rule.
     *
     * @param int|string|false $quoteStyle ENT_* flags, 'single', 'double', or false for none
     * @param callable(string): bool $knownEntity whether a named entity is in the allowed table
     */
    public static function specialchars(string $text, int|string|false $quoteStyle, bool $doubleEncode, callable $knownEntity): string
    {
        if ($text === '' || !preg_match('/[&<>"\']/', $text)) {
            return $text;
        }
        [$flags, $single] = match ($quoteStyle) {
            'single' => [ENT_NOQUOTES, true],
            'double' => [ENT_COMPAT, false],
            false, 0 => [ENT_NOQUOTES, false],
            default => [(int) $quoteStyle, false],
        };
        if ($doubleEncode) {
            $text = htmlspecialchars($text, $flags | ENT_SUBSTITUTE, 'UTF-8', true);
        } else {
            $text = self::encodeStrayAmpersands($text, $knownEntity);
            $text = htmlspecialchars($text, $flags & ~ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
        }
        return $single || ($flags & ENT_QUOTES) ? str_replace("'", '&#039;', $text) : $text;
    }

    private static function encodeStrayAmpersands(string $text, callable $knownEntity): string
    {
        $text = (string) preg_replace_callback('/&(#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);/', static fn (array $m): string => $m[1][0] === '#' || $knownEntity($m[1]) ? '&' . $m[1] . ';' : '&amp;' . $m[1] . ';', $text);
        $text = (string) preg_replace('/&(?!(?:#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);)/', '&amp;', $text);
        return (string) preg_replace('/&amp;amp;(?=[A-Za-z0-9#]+;)/', '&amp;', $text);
    }

    private const DECODE = ['&amp;' => '&', '&#038;' => '&', '&#x26;' => '&', '&lt;' => '<', '&#060;' => '<', '&#x3C;' => '<', '&gt;' => '>', '&#062;' => '>', '&#x3E;' => '>'];
    private const DOUBLE_QUOTES = ['&quot;' => '"', '&#034;' => '"', '&#x22;' => '"'];
    private const SINGLE_QUOTES = ['&#039;' => "'", '&#x27;' => "'", '&#39;' => "'", '&apos;' => "'"];

    /** The reverse of specialchars: the five characters back, with the quote pairs the style asks for. */
    public static function decode(string $text, int|string $quoteStyle): string
    {
        if ($text === '' || !str_contains($text, '&')) {
            return $text;
        }
        $table = self::DECODE + match ($quoteStyle) {
            ENT_QUOTES => self::DOUBLE_QUOTES + self::SINGLE_QUOTES,
            ENT_COMPAT, 'double' => self::DOUBLE_QUOTES,
            'single' => self::SINGLE_QUOTES,
            default => [],
        };
        return strtr($text, $table);
    }
}
