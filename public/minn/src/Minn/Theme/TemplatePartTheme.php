<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\Attributes;

/**
 * A template-part block inside a template says which theme's part it means.
 * The theme's own files usually leave that out, and the reference fills the
 * active theme in on the way out, for saved rows as much as for files. It
 * appends the attribute and leaves every other byte of the markup alone.
 */
final readonly class TemplatePartTheme
{
    private const TAG = '<!-- wp:template-part';

    public static function apply(string $markup, string $theme): string
    {
        $out = '';
        $offset = 0;
        while (($start = strpos($markup, self::TAG, $offset)) !== false) {
            $cursor = $start + strlen(self::TAG);
            $out .= substr($markup, $offset, $cursor - $offset);
            $spaces = strspn($markup, " \t\n\r", $cursor);
            $attributes = Attributes::objectAt($markup, $cursor + $spaces);
            if ($attributes === null) {
                $out .= ' ' . self::encode($theme);
                $offset = $cursor;
                continue;
            }
            $out .= substr($markup, $cursor, $spaces) . self::withTheme($attributes, $theme);
            $offset = $cursor + $spaces + strlen($attributes);
        }
        return $out . substr($markup, $offset);
    }

    private static function withTheme(string $attributes, string $theme): string
    {
        $decoded = json_decode($attributes, true);
        if (is_array($decoded) && array_key_exists('theme', $decoded)) {
            return $attributes;
        }
        $inner = trim(substr($attributes, 1, -1));
        return '{' . ($inner === '' ? '' : $inner . ',') . self::pair($theme) . '}';
    }

    private static function encode(string $theme): string
    {
        return '{' . self::pair($theme) . '}';
    }

    private static function pair(string $theme): string
    {
        return '"theme":' . json_encode($theme, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
