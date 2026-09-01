<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;

/**
 * The shortcode syntax in rendered content: [tag], [tag attr="v" flag],
 * [tag]inner[/tag], and [[tag]] as the escaped literal. Unregistered tags
 * stay as written, which is what the reference shows for a plugin that is
 * not installed.
 *
 * @phpstan-type Render Closure(array<string, string>, ?string, Seams): string
 */
final class Shortcodes
{
    /**
     * Content with the registered shortcodes run.
     *
     * @param array<string, Closure> $registry
     */
    public static function apply(string $html, array $registry, Seams $seams): string
    {
        if ($registry === [] || !str_contains($html, '[')) {
            return $html;
        }
        $tags = implode('|', array_map(static fn (string $t) => preg_quote($t, '/'), array_keys($registry)));
        $pattern = '/\[(\[?)(' . $tags . ')(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/s';
        return (string) preg_replace_callback($pattern, static function (array $m) use ($registry, $seams): string {
            if ($m[1] === '[' && ($m[6] ?? '') === ']') {
                return substr($m[0], 1, -1);
            }
            $attributes = self::attributes($m[3]);
            $content = isset($m[5]) && $m[5] !== '' ? $m[5] : null;
            return $m[1] . (string) $registry[$m[2]]($attributes, $content, $seams) . ($m[6] ?? '');
        }, $html);
    }

    /**
     * A shortcode's attributes parsed, curly quotes included.
     *
     * @return array<string, string> named attributes; bare words keyed by position
     */
    public static function attributes(string $text): array
    {
        // Content is texturized before shortcodes run (as on the reference), so
        // quotes around attribute values may have become their curly entities.
        $text = str_replace(['&#8220;', '&#8221;', '&#8243;'], '"', $text);
        $text = str_replace(['&#8216;', '&#8217;'], "'", $text);
        $out = [];
        $position = 0;
        preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*\'([^\']*)\'|([\w-]+)\s*=\s*([^\s\'"]+)|"([^"]*)"|(\S+)/', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if (($m[1] ?? '') !== '') {
                $out[strtolower($m[1])] = $m[2];
            } elseif (($m[3] ?? '') !== '') {
                $out[strtolower($m[3])] = $m[4];
            } elseif (($m[5] ?? '') !== '') {
                $out[strtolower($m[5])] = $m[6];
            } elseif (isset($m[7]) && $m[7] !== '') {
                $out[(string) $position++] = $m[7];
            } elseif (isset($m[8]) && $m[8] !== '') {
                $out[(string) $position++] = $m[8];
            }
        }
        return $out;
    }
}
