<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The shortcode registry plugin code fills with add_shortcode, and the
 * expansion do_shortcode performs: [tag attrs], [tag attrs/], [tag]…[/tag]
 * (the first closing tag wins; content is not expanded again), [[tag]] as
 * the literal, unregistered tags left as written.
 */
final class Shortcodes
{
    /** @var array<string, callable> */
    private array $tags = [];

    public function add(string $tag, callable $callback): void
    {
        $this->tags[$tag] = $callback;
    }

    public function remove(string $tag): void
    {
        unset($this->tags[$tag]);
    }

    public function removeAll(): void
    {
        $this->tags = [];
    }

    public function has(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }

    /** @return array<string, callable> every registered tag and its handler, to restore after a narrowed run */
    public function all(): array
    {
        return $this->tags;
    }

    /** @param array<string, callable> $tags */
    public function restore(array $tags): void
    {
        $this->tags = $tags;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tags);
    }

    public function pattern(?array $tags = null): ?string
    {
        $names = $tags ?? array_keys($this->tags);
        if ($names === []) {
            return null;
        }
        $alternatives = implode('|', array_map(static fn (string $t) => preg_quote($t, '/'), $names));
        return '/\[(\[?)(' . $alternatives . ')(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/s';
    }

    public function apply(string $content): string
    {
        $pattern = $this->pattern();
        if ($pattern === null || !str_contains($content, '[')) {
            return $content;
        }
        return (string) preg_replace_callback($pattern, function (array $m): string {
            if ($m[1] === '[' && ($m[6] ?? '') === ']') {
                return substr($m[0], 1, -1);
            }
            $attributes = self::parse($m[3]);
            $content = isset($m[5]) && $m[5] !== '' ? $m[5] : null;
            return $m[1] . (string) ($this->tags[$m[2]])($attributes, $content, $m[2]) . ($m[6] ?? '');
        }, $content);
    }

    public function strip(string $content): string
    {
        $pattern = $this->pattern();
        if ($pattern === null || !str_contains($content, '[')) {
            return $content;
        }
        return (string) preg_replace_callback($pattern, static function (array $m): string {
            if ($m[1] === '[' && ($m[6] ?? '') === ']') {
                return substr($m[0], 1, -1);
            }
            return $m[1] . ($m[6] ?? '');
        }, $content);
    }

    /** @return array<int|string, string> named attributes; bare words and quoted values keyed by position */
    public static function parse(string $text): array
    {
        $text = str_replace(['&#8220;', '&#8221;', '&#8243;'], '"', $text);
        $text = str_replace(['&#8216;', '&#8217;'], "'", $text);
        $out = [];
        $position = 0;
        preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*\'([^\']*)\'|([\w-]+)\s*=\s*([^\s\'"]+)|"([^"]*)"|\'([^\']*)\'|(\S+)/', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if (($m[1] ?? '') !== '') {
                $out[strtolower($m[1])] = $m[2];
            } elseif (($m[3] ?? '') !== '') {
                $out[strtolower($m[3])] = $m[4];
            } elseif (($m[5] ?? '') !== '') {
                $out[strtolower($m[5])] = $m[6];
            } elseif (isset($m[7]) && $m[7] !== '') {
                $out[$position++] = $m[7];
            } elseif (isset($m[8]) && $m[8] !== '') {
                $out[$position++] = $m[8];
            } elseif (isset($m[9]) && $m[9] !== '') {
                $out[$position++] = $m[9];
            }
        }
        return $out;
    }
}
