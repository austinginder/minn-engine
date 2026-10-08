<?php

declare(strict_types=1);

namespace Minn\Support;

use Closure;

/**
 * The style attribute as kses keeps it (safecss_filter_attr): each
 * declaration whose property is on the list stays, a value's url() only
 * on the properties that may hold one and only with a safe scheme, the
 * gradients and CSS functions the reference allows, and nothing that could
 * end the declaration. Plugins widen the list and the verdict through the
 * hooks the facade wires in (safe_style_css, safecss_filter_attr_allow_css).
 */
final class KsesCss
{
    /** @var array{0: Closure(list<string>): list<string>, 1: Closure(bool, string): bool}|null */
    private static ?array $styleHooks = null;

    /** The properties style attributes keep, as the reference lists them before safe_style_css (probe safety-filters); --* stands for custom properties. */
    private const CSS_PROPERTIES = [
        'background', 'background-color', 'background-image', 'background-position', 'background-repeat',
        'background-size', 'background-attachment', 'background-blend-mode', 'border', 'border-radius',
        'border-width', 'border-color', 'border-style', 'border-right', 'border-right-color', 'border-right-style',
        'border-right-width', 'border-bottom', 'border-bottom-color', 'border-bottom-left-radius',
        'border-bottom-right-radius', 'border-bottom-style', 'border-bottom-width', 'border-bottom-right-radius',
        'border-bottom-left-radius', 'border-left', 'border-left-color', 'border-left-style', 'border-left-width',
        'border-top', 'border-top-color', 'border-top-left-radius', 'border-top-right-radius', 'border-top-style',
        'border-top-width', 'border-top-left-radius', 'border-top-right-radius', 'border-spacing', 'border-collapse',
        'caption-side', 'columns', 'column-count', 'column-fill', 'column-gap', 'column-rule', 'column-span',
        'column-width', 'display', 'color', 'filter', 'font', 'font-family', 'font-size', 'font-style',
        'font-variant', 'font-weight', 'letter-spacing', 'line-height', 'text-align', 'text-decoration',
        'text-indent', 'text-transform', 'white-space', 'height', 'min-height', 'max-height', 'width', 'min-width',
        'max-width', 'margin', 'margin-right', 'margin-bottom', 'margin-left', 'margin-top', 'margin-block-start',
        'margin-block-end', 'margin-inline-start', 'margin-inline-end', 'padding', 'padding-right', 'padding-bottom',
        'padding-left', 'padding-top', 'padding-block-start', 'padding-block-end', 'padding-inline-start',
        'padding-inline-end', 'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink',
        'flex-wrap', 'gap', 'column-gap', 'row-gap', 'grid-template-columns', 'grid-auto-columns',
        'grid-column-start', 'grid-column-end', 'grid-column', 'grid-column-gap', 'grid-template-rows',
        'grid-auto-rows', 'grid-row-start', 'grid-row-end', 'grid-row', 'grid-row-gap', 'grid-gap',
        'justify-content', 'justify-items', 'justify-self', 'align-content', 'align-items', 'align-self', 'clear',
        'cursor', 'direction', 'float', 'list-style-type', 'object-fit', 'object-position', 'opacity', 'overflow',
        'vertical-align', 'writing-mode', 'position', 'top', 'right', 'bottom', 'left', 'z-index', 'box-shadow',
        'aspect-ratio', 'container-type', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity',
        'stroke-width', 'color-interpolation', 'color-interpolation-filters', 'paint-order', 'stop-color',
        'stop-opacity', 'flood-color', 'flood-opacity', 'lighting-color', 'marker', 'marker-end', 'marker-mid',
        'marker-start', 'clip-path', 'clip-rule', 'mask', 'mask-type', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'd',
        'alignment-baseline', 'baseline-shift', 'dominant-baseline', 'glyph-orientation-horizontal',
        'glyph-orientation-vertical', 'text-anchor', 'unicode-bidi', 'word-spacing', 'font-size-adjust',
        'font-stretch', 'color-rendering', 'image-rendering', 'shape-rendering', 'text-rendering', 'vector-effect',
        'transform', 'transform-origin', 'pointer-events', 'visibility', '--*',
    ];

    /** A style attribute's value with only the listed properties kept. */
    public static function style(string $style): string
    {
        return self::css($style);
    }

    /** The CSS functions a value may use (transforms, shapes and the math ones), whatever they hold. */
    private const CSS_FUNCTIONS = '/\b(?:var|calc|min|max|minmax|clamp|repeat|rotate(?:X|Y|Z|3d)?|translate(?:X|Y|Z|3d)?|scale(?:X|Y|Z|3d)?|skew(?:X|Y)?|matrix(?:3d)?|perspective|inset|circle|ellipse|polygon|path)(\((?:[^()]|(?1))*\))/';

    /** The gradients a background may use. */
    private const CSS_GRADIENTS = '/\b(?:repeating-)?(?:linear|radial|conic)-gradient(\((?:[^()]|(?1))*\))/';

    /** The properties that may use a url(). */
    private const CSS_URL_PROPERTIES = ['background', 'background-image', 'cursor', 'filter', 'mask', 'clip-path'];

    /** The properties that may use a gradient. */
    private const CSS_GRADIENT_PROPERTIES = ['background', 'background-image'];

    /**
     * The style attribute's declarations kept as written, as the reference
     * keeps them (probes kses, block-supports): a listed property, named
     * exactly (the list through safe_style_css when the runtime hooks it;
     * --* lets custom properties through), or a bare value; and nothing in
     * the value that could end the declaration or open a function (\ ( & =
     * } or a comment) once the functions it may use are taken out, with a
     * background's gradients and the url()s of allowed schemes a background,
     * a cursor, a filter, a mask or a clip path may use. Each is asked
     * through safecss_filter_attr_allow_css.
     */
    private static function css(string $style): string
    {
        $allowed = self::$styleHooks === null ? self::CSS_PROPERTIES : (self::$styleHooks[0])(self::CSS_PROPERTIES);
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }
            $test = self::cssTestString($declaration, $allowed);
            $allow = $test !== null && preg_match('%[\\\\(&=}]|/\*%', $test) !== 1;
            if (self::$styleHooks !== null) {
                $allow = (self::$styleHooks[1])($allow, $declaration);
            }
            if ($allow) {
                $kept[] = $declaration;
            }
        }
        return implode(';', $kept);
    }

    /**
     * What of a declaration must hold no unsafe character: its value (or the
     * bare value) without the functions it may use; null for a property not
     * listed, or a url() with a scheme not allowed (on a property that cannot
     * use one, its parenthesis fails the test).
     *
     * @param list<string> $allowed
     */
    private static function cssTestString(string $declaration, array $allowed): ?string
    {
        if (!str_contains($declaration, ':')) {
            return (string) preg_replace(self::CSS_FUNCTIONS, '', $declaration);
        }
        [$property, $value] = array_map('trim', explode(':', $declaration, 2));
        $custom = preg_match('/^--[a-zA-Z0-9_-]+$/', $property) === 1 && in_array('--*', $allowed, true);
        if (!$custom && !in_array($property, $allowed, true)) {
            return null;
        }
        if (in_array($property, self::CSS_GRADIENT_PROPERTIES, true)) {
            $value = (string) preg_replace(self::CSS_GRADIENTS, '', $value);
        }
        if (in_array($property, self::CSS_URL_PROPERTIES, true)) {
            $bad = false;
            $value = (string) preg_replace_callback('/\burl\(\s*([\'"]?)(.*?)\1\s*\)/', static function (array $url) use (&$bad): string {
                $bad = $bad || trim($url[2]) === '' || Kses::attributeUrl(trim($url[2])) !== trim($url[2]);
                return '';
            }, $value);
            if ($bad) {
                return null;
            }
        }
        return (string) preg_replace(self::CSS_FUNCTIONS, '', $value);
    }

    /**
     * The runtime's say over style attributes: the property list
     * (safe_style_css) and each declaration (safecss_filter_attr_allow_css).
     * The facade sets these as it loads.
     *
     * @param Closure(list<string>): list<string> $properties
     * @param Closure(bool, string): bool $allow
     */
    public static function styleHooks(Closure $properties, Closure $allow): void
    {
        self::$styleHooks = [$properties, $allow];
    }
}
