<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;

/**
 * The small text filters the reference runs over content, titles and
 * comments, each from its observed rules (contracts/runtime.md "The content
 * filters", "The comment form"): smilies, the capital P, insecure home
 * addresses, the feed's embed clean-up, and a comment's links and spans.
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

    /**
     * Every link in a comment marked as user-generated, on slashed text as
     * the comment filters carry it: rel gains "nofollow ugc" after whatever
     * it held ("ugc" alone for a link to the site's own host), moves to the
     * end of the tag, and each attribute is written double-quoted.
     *
     * @param Closure(string $href): bool $internal whether an href points at the site itself
     */
    public static function relUgc(string $slashed, Closure $internal): string
    {
        return (string) preg_replace_callback('/<a\s([^>]*)>/i', static function (array $m) use ($internal): string {
            $attributes = self::attributes(stripslashes($m[1]));
            $rel = array_values(array_filter(preg_split('/\s+/', (string) ($attributes['rel'] ?? '')) ?: [], static fn (string $v): bool => $v !== ''));
            unset($attributes['rel']);
            $add = $internal((string) ($attributes['href'] ?? '')) ? ['ugc'] : ['nofollow', 'ugc'];
            $rel = array_values(array_unique([...$rel, ...$add]));
            if ($add === ['ugc']) {
                $rel = array_values(array_diff($rel, ['nofollow']));
            }
            $attributes['rel'] = implode(' ', $rel);
            $out = '';
            foreach ($attributes as $name => $value) {
                $out .= ' ' . $name . '="' . $value . '"';
            }
            return addslashes('<a' . $out . '>');
        }, $slashed);
    }

    /** A span keeps no class in a comment (where a note's mention would be faked); slashed text in, slashed out. */
    public static function noteMentionClasses(string $slashed): string
    {
        $plain = (string) preg_replace_callback('/<span\b([^>]*)>/i', static function (array $m): string {
            $rest = trim((string) preg_replace('/\s*\bclass\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[1]));
            return '<span ' . $rest . '>';
        }, stripslashes($slashed));
        return addslashes($plain);
    }

    /** A tag's attributes in order, name => value (quotes removed; a bare name has an empty value). @return array<string, string> */
    private static function attributes(string $raw): array
    {
        preg_match_all('/([\w:-]+)(?:\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', $raw, $found, PREG_SET_ORDER);
        $attributes = [];
        foreach ($found as $one) {
            $name = strtolower($one[1]);
            $attributes[$name] ??= ($one[3] ?? '') !== '' ? $one[3] : (($one[4] ?? '') !== '' ? $one[4] : ($one[5] ?? ''));
        }
        return $attributes;
    }
}
