<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Emoji as the reference's mail and feeds carry them, from the list the
 * reference ships (data/emoji.json, read from it): encode() turns each emoji
 * character into its entity; staticize() turns each listed sequence in the
 * text (outside tags, and outside code and pre) into a CDN image, longest
 * sequence first. Text that already holds an entity is not encoded first;
 * once anything matched, the variation selectors left over are dropped.
 */
final class Emoji
{
    private const IGNORED = ['code', 'pre'];

    /** @var array{entities: list<string>, partials: list<string>}|null */
    private static ?array $list = null;

    /** Each emoji character in the text as its hexadecimal entity. */
    public static function encode(string $text): string
    {
        if (preg_match('/[^\x00-\x7F]/', $text) !== 1) {
            return $text;
        }
        $partials = array_flip(self::list()['partials']);
        return (string) preg_replace_callback('/./u', static function (array $m) use ($partials): string {
            $entity = sprintf('&#x%x;', mb_ord($m[0], 'UTF-8'));
            return isset($partials[$entity]) ? $entity : $m[0];
        }, $text);
    }

    /** The text with its emoji as images under $baseUrl (each file named for its code points, $extension after). */
    public static function staticize(string $text, string $baseUrl, string $extension): string
    {
        if (!str_contains($text, '&#x')) {
            $encoded = self::encode($text);
            if ($encoded === $text) {
                return $text;
            }
            $text = $encoded;
        }
        $found = array_values(array_filter(self::list()['entities'], static fn (string $entity): bool => str_contains($text, $entity)));
        if ($found === []) {
            return $text;
        }
        $images = [];
        foreach ($found as $entity) {
            $file = str_replace(['&#x', ';'], ['', '-'], $entity);
            $images[$entity] = sprintf('<img src="%s" alt="%s" class="wp-smiley" style="height: 1em; max-height: 1em;" />', $baseUrl . rtrim($file, '-') . $extension, html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return str_replace('&#xfe0f;', '', self::replaceInText($text, $images));
    }

    /** The replacements made in text runs only, never inside a tag or a code or pre element. @param array<string, string> $images */
    private static function replaceInText(string $text, array $images): string
    {
        $out = '';
        $ignoring = null;
        foreach (preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part !== '' && $part[0] === '<') {
                if ($ignoring === null && preg_match('#^<(' . implode('|', self::IGNORED) . ')[\s>]#', $part, $m) === 1) {
                    $ignoring = $m[1];
                } elseif ($ignoring !== null && preg_match('#^</' . $ignoring . '\s*>#', $part) === 1) {
                    $ignoring = null;
                }
                $out .= $part;
                continue;
            }
            $out .= $ignoring === null ? strtr($part, $images) : $part;
        }
        return $out;
    }

    /** @return array{entities: list<string>, partials: list<string>} */
    private static function list(): array
    {
        return self::$list ??= json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/emoji.json'), true) ?: ['entities' => [], 'partials' => []];
    }
}
