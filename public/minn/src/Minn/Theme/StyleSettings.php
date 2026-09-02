<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The settings and styles nodes as wp/v2/global-styles reports them:
 * preset lists keyed by the origin that declared them, appearanceTools
 * spelled out as the flags it stands for, and var:preset tokens resolved
 * to the custom properties they name. Pure: a node in, a node out.
 */
final class StyleSettings
{
    /** Where a settings node keeps preset lists, at its root and under each block. */
    private const PRESET_PATHS = [
        ['color', 'palette'], ['color', 'gradients'], ['color', 'duotone'],
        ['typography', 'fontSizes'], ['typography', 'fontFamilies'],
        ['spacing', 'spacingSizes'], ['shadow', 'presets'],
        ['dimensions', 'aspectRatios'], ['dimensions', 'dimensionSizes'],
    ];

    /**
     * What appearanceTools switches on, group by group in the order the
     * reference appends the groups it has to add; a group already present
     * keeps its place and gains the flags at its end.
     */
    private const APPEARANCE_TOOLS = [
        'background' => ['backgroundImage', 'backgroundSize', 'gradient'],
        'border' => ['color', 'radius', 'style', 'width'],
        'color' => ['link', 'heading', 'button', 'caption'],
        'dimensions' => ['aspectRatio', 'height', 'minHeight', 'minWidth', 'width'],
        'position' => ['sticky'],
        'spacing' => ['blockGap', 'margin', 'padding'],
        'typography' => ['lineHeight', 'textColumns'],
    ];

    /**
     * A settings node as written (theme.json, a variation, or the site
     * editor's post) with its presets keyed by origin and appearanceTools
     * expanded.
     *
     * @param string $origin theme for a theme's file, custom for the site editor's
     */
    public static function normalize(array $settings, string $origin): array
    {
        $settings = self::presetsUnder($settings, $origin);
        foreach ((array) ($settings['blocks'] ?? []) as $block => $node) {
            if (is_array($node)) {
                $settings['blocks'][$block] = self::presetsUnder($node, $origin);
            }
        }
        return self::withAppearanceTools($settings);
    }

    /** Every var:preset|kind|slug token replaced by the custom property it names. */
    public static function resolved(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::resolved($value);
            } elseif (is_string($value) && str_starts_with($value, 'var:')) {
                $node[$key] = 'var(--wp--' . str_replace('|', '--', substr($value, 4)) . ')';
            }
        }
        return $node;
    }

    private static function presetsUnder(array $node, string $origin): array
    {
        foreach (self::PRESET_PATHS as [$group, $list]) {
            $presets = $node[$group][$list] ?? null;
            if (is_array($presets) && array_is_list($presets)) {
                $node[$group][$list] = [$origin => $presets];
            }
        }
        return $node;
    }

    /** The flag itself goes; a flag the node already sets keeps its value. */
    private static function withAppearanceTools(array $settings): array
    {
        if (($settings['appearanceTools'] ?? false) !== true) {
            return $settings;
        }
        unset($settings['appearanceTools']);
        foreach (self::APPEARANCE_TOOLS as $group => $flags) {
            foreach ($flags as $flag) {
                $settings[$group][$flag] ??= true;
            }
        }
        return $settings;
    }
}
