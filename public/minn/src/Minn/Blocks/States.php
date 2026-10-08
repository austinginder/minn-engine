<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Block state styles (a block's style[":hover"] and the like, probe
 * block-supports): the selector arithmetic and the fallbacks the reference
 * applies when it writes a state's rules. Pure: styles and selectors in,
 * styles, groups and declarations out.
 */
final class States
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    /**
     * A selector list split at its top-level commas (none inside parentheses
     * counts), each part as written.
     *
     * @return list<string>
     */
    public static function split(string $selector): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        foreach (str_split($selector) as $char) {
            $depth += $char === '(' ? 1 : ($char === ')' && $depth > 0 ? -1 : 0);
            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;
        return $parts;
    }

    /**
     * A state on each selector of a block's list, its leading compound
     * selector swapped for the base; the base alone for an empty list.
     */
    public static function selector(string $base, string $selectors, string $state): string
    {
        $out = [];
        foreach (self::split($selectors) as $part) {
            $part = trim($part);
            $out[] = ($part === '' ? $base : (string) preg_replace('/^[^\s>+~]+/', $base, $part)) . $state;
        }
        return implode(', ', $out);
    }

    /** Presets (var:preset|{kind}|{slug}) as their custom properties, through arrays; anything else as it is. */
    public static function presetVars(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'presetVars'], $value);
        }
        if (is_string($value) && str_starts_with($value, 'var:preset|')) {
            return 'var(--wp--' . implode('--', explode('|', substr($value, 4))) . ')';
        }
        return $value;
    }

    /**
     * Declarations with a background image unset under a background colour
     * that sets neither a background nor an image of its own.
     *
     * @param array<string, string> $declarations
     * @return array<string, string>
     */
    public static function backgroundResets(array $declarations): array
    {
        if (isset($declarations['background-color']) && !isset($declarations['background']) && !isset($declarations['background-image'])) {
            $declarations['background-image'] = 'unset';
        }
        return $declarations;
    }

    /**
     * Declarations with a solid border style for a border (or a side of one)
     * given a width or a colour and no style.
     *
     * @param array<string, string> $declarations
     * @return array<string, string>
     */
    public static function borderFallbacks(array $declarations): array
    {
        foreach (['border', ...array_map(static fn (string $side) => "border-{$side}", self::SIDES)] as $prefix) {
            if ((isset($declarations["{$prefix}-width"]) || isset($declarations["{$prefix}-color"])) && !isset($declarations["{$prefix}-style"])) {
                $declarations["{$prefix}-style"] = 'solid';
            }
        }
        return $declarations;
    }

    /**
     * A state's style with an explicit aspect ratio unsetting the height and
     * minimum height, or a height or minimum height unsetting the aspect ratio.
     *
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    public static function dimensionFallbacks(array $style): array
    {
        $dimensions = $style['dimensions'] ?? null;
        if (!is_array($dimensions)) {
            return $style;
        }
        $ratio = $dimensions['aspectRatio'] ?? null;
        if (is_string($ratio) && $ratio !== '' && $ratio !== 'auto') {
            $style['dimensions'] = array_merge($dimensions, ['minHeight' => 'unset', 'height' => 'unset']);
        } elseif (isset($dimensions['minHeight']) || isset($dimensions['height'])) {
            $style['dimensions']['aspectRatio'] = 'unset';
        }
        return $style;
    }

    /**
     * A state's style split by the selectors a block gives its features: a
     * feature (or one of its properties) with a selector of its own goes
     * there, a feature's other properties to its root selector, the rest to
     * the block's root. Groups keep the order they are first met in.
     *
     * @param array<string, mixed> $style
     * @param array<string, mixed> $selectors the block's selectors, root among them
     * @return list<array{selector: ?string, style: array<string, mixed>}>
     */
    public static function groups(array $style, array $selectors): array
    {
        $groups = [];
        $root = is_string($selectors['root'] ?? null) ? $selectors['root'] : null;
        foreach ($style as $feature => $value) {
            $own = $selectors[$feature] ?? null;
            if (is_string($own)) {
                self::addGroup($groups, $own, [$feature => $value]);
                continue;
            }
            $rest = $value;
            if (is_array($own) && is_array($value)) {
                $rest = [];
                foreach ($value as $property => $part) {
                    if (is_string($own[$property] ?? null)) {
                        self::addGroup($groups, $own[$property], [$feature => [$property => $part]]);
                    } else {
                        $rest[$property] = $part;
                    }
                }
                if ($rest === []) {
                    continue;
                }
            }
            self::addGroup($groups, is_array($own) && is_string($own['root'] ?? null) ? $own['root'] : $root, [$feature => $rest]);
        }
        return array_values($groups);
    }

    /**
     * Adds a style to the group of a selector, merged into what it holds.
     *
     * @param array<string, array{selector: ?string, style: array<string, mixed>}> $groups
     * @param array<string, mixed> $style
     */
    public static function addGroup(array &$groups, ?string $selector, array $style): void
    {
        $key = (string) $selector;
        $groups[$key] ??= ['selector' => $selector, 'style' => []];
        $groups[$key]['style'] = array_replace_recursive($groups[$key]['style'], $style);
    }
}
