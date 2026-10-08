<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Runtime\Runtime;

/**
 * Per-block element styles (style.elements in a block's attributes, the
 * link colour most often; probe block-supports). A block whose element
 * styles set a colour gets a numbered wp-elements-N class and a rule per
 * element and state, recorded with the page's block-support styles in
 * render order: links (their text colour, plain or on hover), headings,
 * each heading level and buttons (text, background or gradient). A block
 * type whose colour support skips serialization, whole or for links,
 * headings or buttons, writes none for those.
 */
final class Elements
{
    private const SELECTORS = [
        'link' => 'a:where(:not(.wp-element-button))',
        'heading' => 'h1, h2, h3, h4, h5, h6',
        'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'button' => '.wp-element-button, .wp-block-button__link',
    ];

    /** The kind each element's skip answers to: every heading level is a heading. */
    private const KINDS = ['link' => 'link', 'heading' => 'heading', 'h1' => 'heading', 'h2' => 'heading', 'h3' => 'heading', 'h4' => 'heading', 'h5' => 'heading', 'h6' => 'heading', 'button' => 'button'];

    /**
     * The block's wp-elements-N class, recording its rules; null when its
     * element styles earn none.
     *
     * @param array<string, mixed> $supports the block type's supports
     */
    public static function className(array $attrs, array $supports): ?string
    {
        $elements = $attrs['style']['elements'] ?? null;
        $skip = self::skips($supports);
        if (!is_array($elements) || !self::shouldAdd($elements, $skip)) {
            return null;
        }
        $class = 'wp-elements-' . RenderState::current()->nextElements();
        $rules = self::rules($class, $elements, $skip);
        if ($rules !== []) {
            Runtime::hooks()->action('minn_block_support_rules', [$rules]);
        }
        return $class;
    }

    /**
     * Whether element styles earn a class: a link's text colour (or its
     * hover's), a heading's, a heading level's or a button's text,
     * background or gradient, for a kind not skipped.
     *
     * @param array<string, mixed> $elements
     * @param array<string, bool> $skip by kind: link, heading, button
     */
    public static function shouldAdd(array $elements, array $skip): bool
    {
        foreach ($elements as $element => $styles) {
            $kind = self::KINDS[$element] ?? null;
            if ($kind === null || !is_array($styles) || !empty($skip[$kind])) {
                continue;
            }
            $color = (array) ($styles['color'] ?? []);
            $set = $kind === 'link'
                ? array_key_exists('text', $color) || isset($styles[':hover']['color']['text'])
                : array_intersect_key($color, ['text' => 1, 'background' => 1, 'gradient' => 1]) !== [];
            if ($set) {
                return true;
            }
        }
        return false;
    }

    /**
     * The kinds a block type's colour support skips serializing.
     *
     * @param array<string, mixed> $supports
     * @return array<string, bool>
     */
    public static function skips(array $supports): array
    {
        $skip = $supports['color']['__experimentalSkipSerialization'] ?? false;
        $out = [];
        foreach (['link', 'heading', 'button'] as $kind) {
            $out[$kind] = is_array($skip) ? in_array($kind, $skip, true) : (bool) $skip;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $elements
     * @param array<string, bool> $skip
     * @return list<array{selector: string, declarations: array<string, string>}>
     */
    private static function rules(string $class, array $elements, array $skip): array
    {
        $rules = [];
        foreach ($elements as $element => $styles) {
            $selector = self::SELECTORS[$element] ?? null;
            if ($selector === null || !is_array($styles) || !empty($skip[self::KINDS[$element]])) {
                continue;
            }
            $states = ['' => $styles];
            foreach ([':hover', ':focus', ':active', ':visited'] as $state) {
                if (isset($styles[$state]) && is_array($styles[$state])) {
                    $states[$state] = $styles[$state];
                }
            }
            foreach ($states as $state => $stateStyles) {
                $declarations = self::declarations($stateStyles);
                if ($declarations !== []) {
                    $rules[] = ['selector' => implode(', ', array_map(static fn (string $s) => ".{$class} {$s}{$state}", explode(', ', $selector))), 'declarations' => $declarations];
                }
            }
        }
        return $rules;
    }

    /** @return array<string, string> */
    private static function declarations(array $styles): array
    {
        $out = [];
        foreach (['text' => 'color', 'background' => 'background-color'] as $key => $property) {
            if (isset($styles['color'][$key])) {
                $out[$property] = Styles::value((string) $styles['color'][$key]);
            }
        }
        foreach (['fontSize' => 'font-size', 'fontStyle' => 'font-style', 'fontWeight' => 'font-weight', 'letterSpacing' => 'letter-spacing', 'lineHeight' => 'line-height', 'textDecoration' => 'text-decoration', 'textTransform' => 'text-transform'] as $key => $property) {
            if (isset($styles['typography'][$key])) {
                $out[$property] = Styles::value((string) $styles['typography'][$key]);
            }
        }
        return $out;
    }
}
