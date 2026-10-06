<?php

declare(strict_types=1);

namespace Minn\Support;

use Normalizer;

/**
 * Accented and special characters to their plain spelling, from the
 * reference's own table (data/accents.json, captured with
 * tests/tools/accents-capture.php): text is put in composed form first, a
 * locale with spellings of its own (German, Danish, Catalan, Serbian,
 * Bosnian) has them, and a string that is not UTF-8 is read as Latin-1.
 * Anything the table does not name stays as it is.
 */
final class Accents
{
    /** @var array{table: array<string, string>, locales: array<string, array<string, string>>, latin1: array<string, string>}|null */
    private static ?array $data = null;

    /** The text with its accented characters spelled plainly, as the locale spells them. */
    public static function strip(string $text, string $locale = ''): string
    {
        if (!preg_match('/[\x80-\xff]/', $text)) {
            return $text;
        }
        $data = self::data();
        if (!mb_check_encoding($text, 'UTF-8')) {
            $bytes = [];
            foreach ($data['latin1'] as $hex => $plain) {
                $bytes[(string) hex2bin((string) $hex)] = $plain;
            }
            return strtr($text, $bytes);
        }
        if (class_exists(Normalizer::class) && !Normalizer::isNormalized($text)) {
            $text = (string) (Normalizer::normalize($text) ?: $text);
        }
        return strtr($text, ($data['locales'][$locale] ?? []) + $data['table']);
    }

    /** @return array{table: array<string, string>, locales: array<string, array<string, string>>, latin1: array<string, string>} */
    private static function data(): array
    {
        if (self::$data === null) {
            $decoded = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/data/accents.json'), true);
            self::$data = (is_array($decoded) ? $decoded : []) + ['table' => [], 'locales' => [], 'latin1' => []];
        }
        return self::$data;
    }
}
