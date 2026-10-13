<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\Html;
use Minn\Runtime\Runtime;

/**
 * Plain text shaped the way WordPress's formatting helpers shape it: cut to
 * a number of words or characters, named references written as numeric
 * ones, stray ampersands encoded, whitespace normalized, a path's last part,
 * and a callback run over every value of a nested structure.
 */
final class Text
{
    /** The text's first words, tags gone, with $more after them when some were cut (wp_trim_words), as the filter leaves it. */
    public static function trimWords(mixed $text, mixed $count = 55, mixed $more = null): string
    {
        $more ??= '&hellip;';
        $plain = str_replace("\xC2\xA0", ' ', Html::stripAllTags((string) $text));
        $words = (array) preg_split("/[\n\r\t ]+/", $plain, (int) $count + 1, PREG_SPLIT_NO_EMPTY);
        $cut = count($words) > (int) $count;
        $trimmed = implode(' ', $cut ? array_slice($words, 0, -1) : $words) . ($cut ? $more : '');
        return (string) Runtime::hooks()->filter('wp_trim_words', [$trimmed, $count, $more, $text]);
    }

    /** The text's first characters, tags and line breaks gone, with $more after them when some were cut (wp_html_excerpt). */
    public static function htmlExcerpt(mixed $text, mixed $count, mixed $more = null): string
    {
        $plain = Html::stripAllTags((string) $text, true);
        $excerpt = mb_substr($plain, 0, (int) $count, 'UTF-8');
        return $excerpt === $plain ? $excerpt : trim($excerpt) . ($more ?? '');
    }

    /** Named character references written as numeric ones (ent2ncr), unless pre_ent2ncr answers first. */
    public static function numericReferences(mixed $text): mixed
    {
        $answered = Runtime::hooks()->filter('pre_ent2ncr', [null, $text]);
        if ($answered !== null) {
            return $answered;
        }
        return preg_replace_callback('/&([a-zA-Z0-9]+);/', static function (array $m): string {
            $character = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $character === $m[0] ? $m[0] : '&#' . mb_ord($character, 'UTF-8') . ';';
        }, (string) $text);
    }

    /** Ampersands that begin no reference written as &#038; (convert_chars). */
    public static function encodeAmpersands(mixed $content): string
    {
        $content = (string) $content;
        return str_contains($content, '&') ? (string) preg_replace('/&(?!(?:#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);)/', '&#038;', $content) : $content;
    }

    /** Line breaks as single newlines and runs of spaces and tabs as one space, ends trimmed (normalize_whitespace). */
    public static function normalizeWhitespace(mixed $text): string
    {
        return (string) preg_replace(['/\n+/', '/[ \t]+/'], ["\n", ' '], str_replace("\r", "\n", trim((string) $text)));
    }

    /** A path's last part, multibyte names intact, without the suffix given (wp_basename). */
    public static function basename(mixed $path, string $suffix = ''): string
    {
        return urldecode(basename(str_replace(['%2F', '%5C'], '/', urlencode((string) $path)), $suffix));
    }

    /** A value with the callback run over every scalar inside it, arrays and objects followed (map_deep). */
    public static function mapDeep(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::mapDeep($item, $callback), $value);
        }
        if (is_object($value)) {
            foreach (get_object_vars($value) as $name => $item) {
                $value->{$name} = self::mapDeep($item, $callback);
            }
            return $value;
        }
        // The callback runs as WordPress code calls it, coercively (urlencode(1) is "1"): array_map invokes it, not this strict file.
        return array_map($callback, [$value])[0];
    }
}
