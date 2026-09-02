<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Per-block element styles (style.elements in a block's attributes, the
 * link colour most often). The reference gives each such block a
 * numbered wp-elements-N class and a rule per element state, emitted with
 * the page's support styles in render order.
 */
final class Elements
{
    private const SELECTORS = [
        'link' => 'a:where(:not(.wp-element-button))',
        'heading' => 'h1, h2, h3, h4, h5, h6',
        'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'button' => '.wp-element-button, .wp-block-button__link',
    ];

    /**
     * Blocks whose colour support has no element slots: their stored
     * element styles are ignored and do not take a number.
     */
    private const WITHOUT_ELEMENTS = ['core/search', 'core/navigation', 'core/social-links', 'core/buttons', 'core/button'];

    /** The block's wp-elements-N class, recording its rules; null when the block styles no element. */
    public static function className(array $attrs, ?string $blockName = null): ?string
    {
        $elements = $attrs['style']['elements'] ?? null;
        if (!is_array($elements) || in_array($blockName, self::WITHOUT_ELEMENTS, true)) {
            return null;
        }
        $rules = '';
        $class = '%CLASS%';
        foreach ($elements as $element => $styles) {
            $selector = self::SELECTORS[$element] ?? null;
            if ($selector === null || !is_array($styles)) {
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
                if ($declarations === '') {
                    continue;
                }
                $rules .= implode(', ', array_map(
                    static fn (string $s) => ".{$class} {$s}{$state}",
                    explode(', ', $selector),
                )) . '{' . $declarations . '}';
            }
        }
        if ($rules === '') {
            return null;
        }
        $class = 'wp-elements-' . RenderState::current()->nextElements();
        RenderState::current()->recordElementRule(str_replace('%CLASS%', $class, $rules));
        return $class;
    }

    private static function declarations(array $styles): string
    {
        $out = '';
        if (isset($styles['color']['text'])) {
            $out .= 'color:' . Styles::value((string) $styles['color']['text']) . ';';
        }
        if (isset($styles['color']['background'])) {
            $out .= 'background-color:' . Styles::value((string) $styles['color']['background']) . ';';
        }
        foreach ([
            'fontSize' => 'font-size',
            'fontStyle' => 'font-style',
            'fontWeight' => 'font-weight',
            'letterSpacing' => 'letter-spacing',
            'lineHeight' => 'line-height',
            'textDecoration' => 'text-decoration',
            'textTransform' => 'text-transform',
        ] as $key => $property) {
            if (isset($styles['typography'][$key])) {
                $out .= $property . ':' . Styles::value((string) $styles['typography'][$key]) . ';';
            }
        }
        return $out;
    }
}
