<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Parsed blocks back to markup. A core block is written by its short name;
 * attributes are JSON with every character that could end the comment or
 * open a tag spelled as an escape ("<" "<", "--" "--", a
 * backslash "\"), so a value can never break out of its delimiter.
 */
final class Serializer
{
    /**
     * A list of parsed blocks back to markup, freeform runs as written.
     *
     * @param list<Block> $blocks
     */
    public static function blocks(array $blocks): string
    {
        return implode('', array_map(self::block(...), $blocks));
    }

    /** One block with its inner blocks, as the delimiter comments the parser reads. */
    public static function block(Block $block): string
    {
        $content = '';
        $index = 0;
        foreach ($block->innerContent as $chunk) {
            $content .= is_string($chunk) ? $chunk : self::block($block->innerBlocks[$index++]);
        }
        return self::delimited($block->name, $block->attrs, $content);
    }

    /** Content wrapped in a block's delimiters; a block with no content is written self-closing. */
    public static function delimited(?string $name, array $attrs, string $content): string
    {
        if ($name === null) {
            return $content;
        }
        $short = str_starts_with($name, 'core/') ? substr($name, 5) : $name;
        $attributes = $attrs === [] ? '' : self::attributes($attrs) . ' ';
        if ($content === '') {
            return "<!-- wp:{$short} {$attributes}/-->";
        }
        return "<!-- wp:{$short} {$attributes}-->{$content}<!-- /wp:{$short} -->";
    }

    /** Block attributes as the delimiter carries them. */
    public static function attributes(array $attrs): string
    {
        $json = (string) json_encode($attrs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = str_replace(['\\\\', '--'], ['\\u005c', '\\u002d\\u002d'], $json);
        // The escapes are written in lower-case hex.
        return (string) preg_replace_callback('/\\\\u00([0-9A-F]{2})/', static fn (array $m): string => '\\u00' . strtolower($m[1]), $json);
    }
}
