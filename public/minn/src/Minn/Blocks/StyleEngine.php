<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * What a block's style object comes to, as the reference's style engine
 * reads it (probe style-engine): the declarations each style property
 * makes and the class names a preset or a set colour earns.
 *
 * - Groups in the order background, colour, border, shadow, dimensions,
 *   spacing, typography, and properties in their order within a group.
 * - A value goes into the declarations as given. Whether it makes CSS is
 *   for the declarations holder to judge.
 * - A preset ("var:preset|color|base") becomes a custom property only
 *   where the definition names one for its kind (a colour, a gradient,
 *   spacing, a font size or family, a shadow, a dimension, a border radius,
 *   a border side's colour). Elsewhere it makes nothing, and with presets
 *   turned into class names it makes none anywhere.
 * - Sides and corners ("padding" => ["top" => ...]) name their own
 *   properties in the order given. Border sides come after the whole
 *   border.
 */
final class StyleEngine
{
    /** The reference's style definitions, by group and property (WP_Style_Engine::BLOCK_STYLE_DEFINITIONS_METADATA). */
    public const DEFINITIONS = ['background' => ['backgroundImage' => ['property_keys' => ['default' => 'background-image'], 'value_func' => ['WP_Style_Engine', 'get_url_or_value_css_declaration'], 'path' => ['background', 'backgroundImage']], 'backgroundPosition' => ['property_keys' => ['default' => 'background-position'], 'path' => ['background', 'backgroundPosition']], 'backgroundRepeat' => ['property_keys' => ['default' => 'background-repeat'], 'path' => ['background', 'backgroundRepeat']], 'backgroundSize' => ['property_keys' => ['default' => 'background-size'], 'path' => ['background', 'backgroundSize']], 'backgroundAttachment' => ['property_keys' => ['default' => 'background-attachment'], 'path' => ['background', 'backgroundAttachment']], 'gradient' => ['property_keys' => ['default' => 'background-image'], 'css_vars' => ['gradient' => '--wp--preset--gradient--$slug'], 'path' => ['background', 'gradient'], 'classnames' => ['has-background' => true]]], 'color' => ['text' => ['property_keys' => ['default' => 'color'], 'path' => ['color', 'text'], 'css_vars' => ['color' => '--wp--preset--color--$slug'], 'classnames' => ['has-text-color' => true, 'has-$slug-color' => 'color']], 'background' => ['property_keys' => ['default' => 'background-color'], 'path' => ['color', 'background'], 'css_vars' => ['color' => '--wp--preset--color--$slug'], 'classnames' => ['has-background' => true, 'has-$slug-background-color' => 'color']], 'gradient' => ['property_keys' => ['default' => 'background'], 'path' => ['color', 'gradient'], 'css_vars' => ['gradient' => '--wp--preset--gradient--$slug'], 'classnames' => ['has-background' => true, 'has-$slug-gradient-background' => 'gradient']]], 'border' => ['color' => ['property_keys' => ['default' => 'border-color', 'individual' => 'border-%s-color'], 'path' => ['border', 'color'], 'classnames' => ['has-border-color' => true, 'has-$slug-border-color' => 'color']], 'radius' => ['property_keys' => ['default' => 'border-radius', 'individual' => 'border-%s-radius'], 'path' => ['border', 'radius'], 'css_vars' => ['border-radius' => '--wp--preset--border-radius--$slug']], 'style' => ['property_keys' => ['default' => 'border-style', 'individual' => 'border-%s-style'], 'path' => ['border', 'style']], 'width' => ['property_keys' => ['default' => 'border-width', 'individual' => 'border-%s-width'], 'path' => ['border', 'width']], 'top' => ['value_func' => ['WP_Style_Engine', 'get_individual_property_css_declarations'], 'path' => ['border', 'top'], 'css_vars' => ['color' => '--wp--preset--color--$slug']], 'right' => ['value_func' => ['WP_Style_Engine', 'get_individual_property_css_declarations'], 'path' => ['border', 'right'], 'css_vars' => ['color' => '--wp--preset--color--$slug']], 'bottom' => ['value_func' => ['WP_Style_Engine', 'get_individual_property_css_declarations'], 'path' => ['border', 'bottom'], 'css_vars' => ['color' => '--wp--preset--color--$slug']], 'left' => ['value_func' => ['WP_Style_Engine', 'get_individual_property_css_declarations'], 'path' => ['border', 'left'], 'css_vars' => ['color' => '--wp--preset--color--$slug']]], 'shadow' => ['shadow' => ['property_keys' => ['default' => 'box-shadow'], 'path' => ['shadow'], 'css_vars' => ['shadow' => '--wp--preset--shadow--$slug']]], 'dimensions' => ['aspectRatio' => ['property_keys' => ['default' => 'aspect-ratio'], 'path' => ['dimensions', 'aspectRatio'], 'classnames' => ['has-aspect-ratio' => true]], 'height' => ['property_keys' => ['default' => 'height'], 'path' => ['dimensions', 'height'], 'css_vars' => ['dimension' => '--wp--preset--dimension--$slug']], 'minHeight' => ['property_keys' => ['default' => 'min-height'], 'path' => ['dimensions', 'minHeight'], 'css_vars' => ['dimension' => '--wp--preset--dimension--$slug']], 'minWidth' => ['property_keys' => ['default' => 'min-width'], 'path' => ['dimensions', 'minWidth'], 'css_vars' => ['dimension' => '--wp--preset--dimension--$slug']], 'objectFit' => ['property_keys' => ['default' => 'object-fit'], 'path' => ['dimensions', 'objectFit']], 'width' => ['property_keys' => ['default' => 'width'], 'path' => ['dimensions', 'width'], 'css_vars' => ['dimension' => '--wp--preset--dimension--$slug']]], 'spacing' => ['padding' => ['property_keys' => ['default' => 'padding', 'individual' => 'padding-%s'], 'path' => ['spacing', 'padding'], 'css_vars' => ['spacing' => '--wp--preset--spacing--$slug']], 'margin' => ['property_keys' => ['default' => 'margin', 'individual' => 'margin-%s'], 'path' => ['spacing', 'margin'], 'css_vars' => ['spacing' => '--wp--preset--spacing--$slug']]], 'typography' => ['fontSize' => ['property_keys' => ['default' => 'font-size'], 'path' => ['typography', 'fontSize'], 'css_vars' => ['font-size' => '--wp--preset--font-size--$slug'], 'classnames' => ['has-$slug-font-size' => 'font-size']], 'fontFamily' => ['property_keys' => ['default' => 'font-family'], 'css_vars' => ['font-family' => '--wp--preset--font-family--$slug'], 'path' => ['typography', 'fontFamily'], 'classnames' => ['has-$slug-font-family' => 'font-family']], 'fontStyle' => ['property_keys' => ['default' => 'font-style'], 'path' => ['typography', 'fontStyle']], 'fontWeight' => ['property_keys' => ['default' => 'font-weight'], 'path' => ['typography', 'fontWeight']], 'lineHeight' => ['property_keys' => ['default' => 'line-height'], 'path' => ['typography', 'lineHeight']], 'textColumns' => ['property_keys' => ['default' => 'column-count'], 'path' => ['typography', 'textColumns']], 'textDecoration' => ['property_keys' => ['default' => 'text-decoration'], 'path' => ['typography', 'textDecoration']], 'textIndent' => ['property_keys' => ['default' => 'text-indent'], 'path' => ['typography', 'textIndent']], 'textTransform' => ['property_keys' => ['default' => 'text-transform'], 'path' => ['typography', 'textTransform']], 'letterSpacing' => ['property_keys' => ['default' => 'letter-spacing'], 'path' => ['typography', 'letterSpacing']], 'writingMode' => ['property_keys' => ['default' => 'writing-mode'], 'path' => ['typography', 'writingMode']]]];

    /**
     * A style object's declarations and class names.
     *
     * @param array<string, mixed> $styles
     * @param array<string, mixed> $options convert_vars_to_classnames turns presets into class names alone
     * @return array{declarations: array<string, mixed>, classnames: list<string>}
     */
    public static function parse(array $styles, array $options = []): array
    {
        $classesOnly = !empty($options['convert_vars_to_classnames']);
        $declarations = [];
        $classes = [];
        foreach (self::DEFINITIONS as $properties) {
            foreach ($properties as $definition) {
                $value = self::at($styles, $definition['path']);
                if (!self::present($value)) {
                    continue;
                }
                $classes = [...$classes, ...self::classes($value, $definition['classnames'] ?? [])];
                // Presets turned into class names make no CSS: as though the definition named no custom property.
                $declarations += self::declarations($value, $definition, $classesOnly ? [] : (array) ($definition['css_vars'] ?? []));
            }
        }
        return ['declarations' => $declarations, 'classnames' => array_values(array_unique($classes))];
    }

    /** A preset's slug for a kind ("var:preset|color|base" for color is "base"), or null. */
    public static function slug(mixed $value, string $kind): ?string
    {
        $kind = self::kebab($kind);
        if (!is_string($value) || !str_contains($value, "var:preset|{$kind}|")) {
            return null;
        }
        return self::kebab(substr($value, (int) strrpos($value, '|') + 1));
    }

    /** A preset as the custom property the definition names for its kind, or null. @param array<string, string> $vars */
    public static function var(mixed $value, array $vars): ?string
    {
        foreach ($vars as $kind => $pattern) {
            $slug = self::slug($value, (string) $kind);
            if ($slug !== null && self::present($slug)) {
                return 'var(' . str_replace('$slug', $slug, $pattern) . ')';
            }
        }
        return null;
    }

    /** @param list<string> $path */
    private static function at(array $styles, array $path): mixed
    {
        $value = $styles;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    private static function present(mixed $value): bool
    {
        return $value === '0' || !empty($value);
    }

    /** @param array<string, string|bool> $names @return list<string> */
    private static function classes(mixed $value, array $names): array
    {
        $out = [];
        foreach ($names as $name => $kind) {
            if ($kind === true) {
                $out[] = $name;
            } elseif (($slug = self::slug($value, (string) $kind)) !== null && self::present($slug)) {
                $out[] = str_replace('$slug', $slug, $name);
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $definition @param array<string, string> $vars the custom properties presets may become @return array<string, mixed> */
    private static function declarations(mixed $value, array $definition, array $vars): array
    {
        $func = (string) (is_array($definition['value_func'] ?? null) ? $definition['value_func'][1] : '');
        $keys = (array) ($definition['property_keys'] ?? []);
        return match (true) {
            $func === 'get_url_or_value_css_declaration' => self::url($value, (string) $keys['default']),
            $func === 'get_individual_property_css_declarations' => is_array($value) ? self::side($value, (string) end($definition['path']), $vars) : [],
            is_array($value) => isset($keys['individual']) ? self::sides($value, (string) $keys['individual'], $vars) : [],
            default => self::one($value, (string) $keys['default'], $vars),
        };
    }

    /** @param array<string, string> $vars @return array<string, mixed> */
    private static function one(mixed $value, string $property, array $vars): array
    {
        if (is_string($value) && str_contains($value, 'var:')) {
            $css = $vars === [] ? null : self::var($value, $vars);
            return $css === null ? [] : [$property => $css];
        }
        return [$property => $value];
    }

    /** @param array<string|int, mixed> $value @param array<string, string> $vars @return array<string, mixed> */
    private static function sides(array $value, string $pattern, array $vars): array
    {
        $out = [];
        foreach ($value as $side => $part) {
            if (self::present($part)) {
                $out += self::one($part, sprintf($pattern, self::kebab((string) $side)), $vars);
            }
        }
        return $out;
    }

    /** A border side's parts in the order given, the colour taking a preset. @param array<string|int, mixed> $value @param array<string, string> $vars @return array<string, mixed> */
    private static function side(array $value, string $side, array $vars): array
    {
        $out = [];
        foreach ($value as $part => $partValue) {
            if (self::present($partValue)) {
                $out += self::one($partValue, 'border-' . $side . '-' . self::kebab((string) $part), array_intersect_key($vars, [(string) $part => true]));
            }
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private static function url(mixed $value, string $property): array
    {
        if (is_array($value)) {
            return isset($value['url']) && is_string($value['url']) && $value['url'] !== '' ? [$property => "url('{$value['url']}')"] : [];
        }
        return is_string($value) ? [$property => $value] : [];
    }

    private static function kebab(string $text): string
    {
        preg_match_all('/[A-Z]{2,}(?=[A-Z][a-z]+[0-9]*|\b)|[A-Z]?[a-z]+[0-9]*|[A-Z]|[0-9]+/', str_replace("'", '', $text), $words);
        return strtolower(implode('-', $words[0]));
    }
}
