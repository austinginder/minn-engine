<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;

/**
 * The small text filters the reference runs over content, titles and
 * comments, each from its observed rules (contracts/runtime.md "The content
 * filters"): smilies, the capital P, insecure home addresses, and the
 * feed's embed clean-up.
 */
final class TextFilters
{
    private const IMAGE = '/\.(png|gif|jpe?g|svg|webp)$/i';
    private const IGNORED = ['code', 'pre', 'script', 'style', 'textarea'];

    /**
     * Smilies become their emoji, or an image for the few that have one: a
     * code only counts as a whole word between spaces, and nothing inside a
     * code, pre, script, style or textarea element, or inside a tag, changes.
     *
     * @param array<string, string> $table code => emoji, or an image file name
     * @param Closure(string $file, string $code): string $image the <img> for an image smiley
     */
    public static function smilies(string $text, array $table, Closure $image): string
    {
        $out = '';
        $ignoring = null;
        foreach (preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part !== '' && $part[0] === '<') {
                $ignoring = self::ignoring($part, $ignoring);
                $out .= $part;
                continue;
            }
            $out .= $ignoring === null ? self::words($part, $table, $image) : $part;
        }
        return $out;
    }

    /** The element whose text is left alone after a tag: one of the ignored elements opening, or the one still open. */
    private static function ignoring(string $tag, ?string $current): ?string
    {
        if ($current !== null) {
            return preg_match('#^</' . $current . '\s*>#', $tag) === 1 ? null : $current;
        }
        return preg_match('#^<(' . implode('|', self::IGNORED) . ')[\s>]#', $tag, $m) === 1 && !str_ends_with($tag, '/>') ? $m[1] : null;
    }

    /** One text run, word by word. @param array<string, string> $table */
    private static function words(string $text, array $table, Closure $image): string
    {
        $out = '';
        foreach (preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $word) {
            if (!isset($table[$word])) {
                $out .= $word;
                continue;
            }
            $out .= preg_match(self::IMAGE, $table[$word]) === 1 ? $image($table[$word], $word) : $table[$word];
        }
        return $out;
    }

    /** "Wordpress" spelled right after a space, a parenthesis, a tag, or an opening curly quote; elsewhere it stays. */
    public static function capitalP(string $text): string
    {
        return (string) preg_replace('/(?<=[ (>]|&#8216;|&#8220;)Wordpress/', 'WordPress', $text);
    }

    /** "Wordpress" spelled right everywhere, as a title is. */
    public static function capitalPEverywhere(string $text): string
    {
        return str_replace('Wordpress', 'WordPress', $text);
    }

    /** The site's own http address made https, escaped forms included. */
    public static function secureHome(string $content, string $insecure, string $secure): string
    {
        $escaped = static fn (string $url): string => str_replace('/', '\/', $url);
        return str_replace([$insecure, $escaped($insecure)], [$secure, $escaped($secure)], $content);
    }

    /** A feed carries an embedded post's iframe without the style that hides it until its script runs. */
    public static function feedEmbeds(string $content): string
    {
        return (string) preg_replace_callback(
            '/<iframe[^>]*class="[^"]*wp-embedded-content[^"]*"[^>]*>/',
            static fn (array $m): string => str_replace('style="position: absolute; visibility: hidden;"', '', $m[0]),
            $content,
        );
    }
}
