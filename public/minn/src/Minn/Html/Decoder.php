<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * Character reference decoding for text and attribute values: numeric and
 * named references, the legacy names that work without a semicolon (not in
 * an attribute when an "=" or alphanumeric follows), unknown ones left as is.
 */
final class Decoder
{
    /** @var array<string, string>|null names that decode without a trailing semicolon, mapped to their text */
    private static ?array $legacy = null;

    public static function text(string $raw): string
    {
        return self::decode($raw, false);
    }

    public static function attribute(string $raw): string
    {
        return self::decode($raw, true);
    }

    private static function decode(string $raw, bool $inAttribute): string
    {
        if (!str_contains($raw, '&')) {
            return $raw;
        }
        return (string) preg_replace_callback('/&(#[xX][0-9a-fA-F]+|#[0-9]+|[a-zA-Z][a-zA-Z0-9]*)(;?)/', static function (array $m) use ($inAttribute, $raw): string {
            $body = $m[1];
            $semicolon = $m[2] === ';';
            if ($body[0] === '#') {
                if (!$semicolon) {
                    // A numeric reference still decodes without its semicolon.
                }
                $code = ($body[1] === 'x' || $body[1] === 'X') ? (int) hexdec(substr($body, 2)) : (int) substr($body, 1);
                return self::codePoint($code);
            }
            $entity = html_entity_decode('&' . $body . ';', ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
            if ($semicolon && $entity !== '&' . $body . ';') {
                return $entity;
            }
            // Without a semicolon only the legacy names decode, and never in an attribute when "=" or an alphanumeric follows.
            $legacy = self::legacy();
            foreach ($legacy as $name => $text) {
                if (str_starts_with($body, $name)) {
                    $rest = substr($body, strlen($name));
                    if ($rest !== '' && $inAttribute) {
                        return $m[0];
                    }
                    if ($rest === '' && $inAttribute && $semicolon === false) {
                        $after = substr($raw, strpos($raw, $m[0]) + strlen($m[0]), 1);
                        if ($after === '=' || ctype_alnum($after)) {
                            return $m[0];
                        }
                    }
                    return $text . $rest . ($semicolon ? ';' : '');
                }
            }
            return $m[0];
        }, $raw);
    }

    private static function codePoint(int $code): string
    {
        if ($code === 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
            return "\u{FFFD}";
        }
        if ($code >= 0x80 && $code <= 0x9F) {
            $windows = [0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152, 0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A, 0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178];
            $code = $windows[$code] ?? $code;
        }
        return mb_chr($code, 'UTF-8') ?: "\u{FFFD}";
    }

    /** @return array<string, string> longest names first */
    private static function legacy(): array
    {
        if (self::$legacy !== null) {
            return self::$legacy;
        }
        $names = [];
        foreach (get_html_translation_table(HTML_ENTITIES, ENT_QUOTES | ENT_HTML401, 'UTF-8') as $char => $entity) {
            $name = substr($entity, 1, -1);
            if (ctype_alpha($name[0])) {
                $names[$name] = $char;
            }
        }
        uksort($names, static fn (string $a, string $b) => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
        return self::$legacy = $names;
    }
}
