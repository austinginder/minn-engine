<?php

/**
 * The reference's theme.json object as plugin code reads it: the merged
 * data the engine builds (get_raw_data, get_data, get_settings) and the
 * custom CSS are real; every other method keeps its inert placeholder, so a
 * call still traces as a gap instead of failing.
 */
#[AllowDynamicProperties]
class WP_Theme_JSON
{
    const ROOT_CSS_PROPERTIES_SELECTOR = ':root';
    const ROOT_BLOCK_SELECTOR = 'body';
    const VALID_ORIGINS = ['default', 'blocks', 'theme', 'custom'];
    const PRESETS_METADATA = [['path' => ['dimensions', 'aspectRatios'], 'prevent_override' => ['dimensions', 'defaultAspectRatios'], 'use_default_names' => false, 'value_key' => 'ratio', 'css_vars' => '--wp--preset--aspect-ratio--$slug', 'classes' => [], 'properties' => ['aspect-ratio']], ['path' => ['color', 'palette'], 'prevent_override' => ['color', 'defaultPalette'], 'use_default_names' => false, 'value_key' => 'color', 'css_vars' => '--wp--preset--color--$slug', 'classes' => ['.has-$slug-color' => 'color', '.has-$slug-background-color' => 'background-color', '.has-$slug-border-color' => 'border-color'], 'properties' => ['color', 'background-color', 'border-color']], ['path' => ['color', 'gradients'], 'prevent_override' => ['color', 'defaultGradients'], 'use_default_names' => false, 'value_key' => 'gradient', 'css_vars' => '--wp--preset--gradient--$slug', 'classes' => ['.has-$slug-gradient-background' => 'background'], 'properties' => ['background']], ['path' => ['color', 'duotone'], 'prevent_override' => ['color', 'defaultDuotone'], 'use_default_names' => false, 'value_func' => NULL, 'css_vars' => NULL, 'classes' => [], 'properties' => ['filter']], ['path' => ['typography', 'fontSizes'], 'prevent_override' => ['typography', 'defaultFontSizes'], 'use_default_names' => true, 'value_func' => 'wp_get_typography_font_size_value', 'css_vars' => '--wp--preset--font-size--$slug', 'classes' => ['.has-$slug-font-size' => 'font-size'], 'properties' => ['font-size']], ['path' => ['typography', 'fontFamilies'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'fontFamily', 'css_vars' => '--wp--preset--font-family--$slug', 'classes' => ['.has-$slug-font-family' => 'font-family'], 'properties' => ['font-family']], ['path' => ['spacing', 'spacingSizes'], 'prevent_override' => ['spacing', 'defaultSpacingSizes'], 'use_default_names' => true, 'value_key' => 'size', 'css_vars' => '--wp--preset--spacing--$slug', 'classes' => [], 'properties' => ['padding', 'margin']], ['path' => ['shadow', 'presets'], 'prevent_override' => ['shadow', 'defaultPresets'], 'use_default_names' => false, 'value_key' => 'shadow', 'css_vars' => '--wp--preset--shadow--$slug', 'classes' => [], 'properties' => ['box-shadow']], ['path' => ['border', 'radiusSizes'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'size', 'css_vars' => '--wp--preset--border-radius--$slug', 'classes' => [], 'properties' => ['border-radius']], ['path' => ['dimensions', 'dimensionSizes'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'size', 'css_vars' => '--wp--preset--dimension--$slug', 'classes' => [], 'properties' => ['width', 'height', 'min-height']]];
    const PROPERTIES_METADATA = ['aspect-ratio' => ['dimensions', 'aspectRatio'], 'background' => ['color', 'gradient'], 'background-color' => ['color', 'background'], 'background-image' => ['background', 'backgroundImage'], 'background-position' => ['background', 'backgroundPosition'], 'background-repeat' => ['background', 'backgroundRepeat'], 'background-size' => ['background', 'backgroundSize'], 'background-attachment' => ['background', 'backgroundAttachment'], 'border-radius' => ['border', 'radius'], 'border-top-left-radius' => ['border', 'radius', 'topLeft'], 'border-top-right-radius' => ['border', 'radius', 'topRight'], 'border-bottom-left-radius' => ['border', 'radius', 'bottomLeft'], 'border-bottom-right-radius' => ['border', 'radius', 'bottomRight'], 'border-color' => ['border', 'color'], 'border-width' => ['border', 'width'], 'border-style' => ['border', 'style'], 'border-top-color' => ['border', 'top', 'color'], 'border-top-width' => ['border', 'top', 'width'], 'border-top-style' => ['border', 'top', 'style'], 'border-right-color' => ['border', 'right', 'color'], 'border-right-width' => ['border', 'right', 'width'], 'border-right-style' => ['border', 'right', 'style'], 'border-bottom-color' => ['border', 'bottom', 'color'], 'border-bottom-width' => ['border', 'bottom', 'width'], 'border-bottom-style' => ['border', 'bottom', 'style'], 'border-left-color' => ['border', 'left', 'color'], 'border-left-width' => ['border', 'left', 'width'], 'border-left-style' => ['border', 'left', 'style'], 'color' => ['color', 'text'], 'text-align' => ['typography', 'textAlign'], 'column-count' => ['typography', 'textColumns'], 'font-family' => ['typography', 'fontFamily'], 'font-size' => ['typography', 'fontSize'], 'font-style' => ['typography', 'fontStyle'], 'font-weight' => ['typography', 'fontWeight'], 'letter-spacing' => ['typography', 'letterSpacing'], 'line-height' => ['typography', 'lineHeight'], 'margin' => ['spacing', 'margin'], 'margin-top' => ['spacing', 'margin', 'top'], 'margin-right' => ['spacing', 'margin', 'right'], 'margin-bottom' => ['spacing', 'margin', 'bottom'], 'margin-left' => ['spacing', 'margin', 'left'], 'min-height' => ['dimensions', 'minHeight'], 'min-width' => ['dimensions', 'minWidth'], 'outline-color' => ['outline', 'color'], 'outline-offset' => ['outline', 'offset'], 'outline-style' => ['outline', 'style'], 'outline-width' => ['outline', 'width'], 'padding' => ['spacing', 'padding'], 'padding-top' => ['spacing', 'padding', 'top'], 'padding-right' => ['spacing', 'padding', 'right'], 'padding-bottom' => ['spacing', 'padding', 'bottom'], 'padding-left' => ['spacing', 'padding', 'left'], '--wp--style--root--padding' => ['spacing', 'padding'], '--wp--style--root--padding-top' => ['spacing', 'padding', 'top'], '--wp--style--root--padding-right' => ['spacing', 'padding', 'right'], '--wp--style--root--padding-bottom' => ['spacing', 'padding', 'bottom'], '--wp--style--root--padding-left' => ['spacing', 'padding', 'left'], 'text-decoration' => ['typography', 'textDecoration'], 'text-shadow' => ['typography', 'textShadow'], 'text-transform' => ['typography', 'textTransform'], 'text-indent' => ['typography', 'textIndent'], 'filter' => ['filter', 'duotone'], 'box-shadow' => ['shadow'], 'height' => ['dimensions', 'height'], 'width' => ['dimensions', 'width'], 'writing-mode' => ['typography', 'writingMode']];
    const INDIRECT_PROPERTIES_METADATA = ['gap' => [['spacing', 'blockGap']], 'column-gap' => [['spacing', 'blockGap', 'left']], 'row-gap' => [['spacing', 'blockGap', 'top']], 'max-width' => [['layout', 'contentSize'], ['layout', 'wideSize']], 'background-image' => [['background', 'backgroundImage', 'url'], ['background', 'gradient']]];
    const PROTECTED_PROPERTIES = ['spacing.blockGap' => ['spacing', 'blockGap']];
    const VALID_TOP_LEVEL_KEYS = ['blockTypes', 'customTemplates', 'description', 'patterns', 'settings', 'slug', 'styles', 'templateParts', 'title', 'version'];
    const VALID_SETTINGS = ['appearanceTools' => NULL, 'useRootPaddingAwareAlignments' => NULL, 'background' => ['backgroundImage' => NULL, 'backgroundSize' => NULL, 'gradient' => NULL], 'border' => ['color' => NULL, 'radius' => NULL, 'radiusSizes' => NULL, 'style' => NULL, 'width' => NULL], 'color' => ['background' => NULL, 'custom' => NULL, 'customDuotone' => NULL, 'customGradient' => NULL, 'defaultDuotone' => NULL, 'defaultGradients' => NULL, 'defaultPalette' => NULL, 'duotone' => NULL, 'gradients' => NULL, 'link' => NULL, 'heading' => NULL, 'button' => NULL, 'caption' => NULL, 'palette' => NULL, 'text' => NULL], 'custom' => NULL, 'dimensions' => ['aspectRatio' => NULL, 'aspectRatios' => NULL, 'defaultAspectRatios' => NULL, 'dimensionSizes' => NULL, 'height' => NULL, 'minHeight' => NULL, 'minWidth' => NULL, 'width' => NULL], 'layout' => ['contentSize' => NULL, 'wideSize' => NULL, 'allowEditing' => NULL, 'allowCustomContentAndWideSize' => NULL], 'lightbox' => ['enabled' => true, 'allowEditing' => true], 'position' => ['fixed' => NULL, 'sticky' => NULL], 'blockVisibility' => ['allowEditing' => true], 'spacing' => ['customSpacingSize' => NULL, 'defaultSpacingSizes' => NULL, 'spacingSizes' => NULL, 'spacingScale' => NULL, 'blockGap' => NULL, 'margin' => NULL, 'padding' => NULL, 'units' => NULL], 'shadow' => ['presets' => NULL, 'defaultPresets' => NULL], 'typography' => ['fluid' => NULL, 'customFontSize' => NULL, 'defaultFontSizes' => NULL, 'dropCap' => NULL, 'fontFamilies' => NULL, 'fontSizes' => NULL, 'fontStyle' => NULL, 'fontWeight' => NULL, 'letterSpacing' => NULL, 'lineHeight' => NULL, 'textAlign' => NULL, 'textColumns' => NULL, 'textDecoration' => NULL, 'textIndent' => NULL, 'textTransform' => NULL, 'writingMode' => NULL], 'viewport' => ['mobile' => NULL, 'tablet' => NULL]];
    const FONT_FAMILY_SCHEMA = [['fontFamily' => NULL, 'name' => NULL, 'slug' => NULL, 'fontFace' => [['ascentOverride' => NULL, 'descentOverride' => NULL, 'fontDisplay' => NULL, 'fontFamily' => NULL, 'fontFeatureSettings' => NULL, 'fontStyle' => NULL, 'fontStretch' => NULL, 'fontVariationSettings' => NULL, 'fontWeight' => NULL, 'lineGapOverride' => NULL, 'sizeAdjust' => NULL, 'src' => NULL, 'unicodeRange' => NULL]]]];
    const VALID_STYLES = ['background' => ['backgroundImage' => NULL, 'backgroundPosition' => NULL, 'backgroundRepeat' => NULL, 'backgroundSize' => NULL, 'backgroundAttachment' => NULL, 'gradient' => NULL], 'border' => ['color' => NULL, 'radius' => NULL, 'style' => NULL, 'width' => NULL, 'top' => NULL, 'right' => NULL, 'bottom' => NULL, 'left' => NULL], 'color' => ['background' => NULL, 'gradient' => NULL, 'text' => NULL], 'dimensions' => ['aspectRatio' => NULL, 'height' => NULL, 'minHeight' => NULL, 'minWidth' => NULL, 'width' => NULL], 'filter' => ['duotone' => NULL], 'outline' => ['color' => NULL, 'offset' => NULL, 'style' => NULL, 'width' => NULL], 'shadow' => NULL, 'spacing' => ['margin' => NULL, 'padding' => NULL, 'blockGap' => NULL], 'typography' => ['fontFamily' => NULL, 'fontSize' => NULL, 'fontStyle' => NULL, 'fontWeight' => NULL, 'letterSpacing' => NULL, 'lineHeight' => NULL, 'textAlign' => NULL, 'textColumns' => NULL, 'textDecoration' => NULL, 'textIndent' => NULL, 'textShadow' => NULL, 'textTransform' => NULL, 'writingMode' => NULL], 'css' => NULL];
    const VALID_ELEMENT_PSEUDO_SELECTORS = ['link' => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active'], 'button' => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active']];
    const VALID_BLOCK_PSEUDO_SELECTORS = ['core/button' => [':hover', ':focus', ':focus-visible', ':active'], 'core/navigation-link' => [':hover', ':focus', ':focus-visible', ':active']];
    const VALID_BLOCK_CUSTOM_STATES = ['core/navigation-link' => ['-current']];
    const DEFAULT_VIEWPORT_BREAKPOINTS = ['mobile' => '480px', 'tablet' => '782px'];
    const ELEMENTS = ['link' => 'a:where(:not(.wp-element-button))', 'heading' => 'h1, h2, h3, h4, h5, h6', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6', 'button' => '.wp-element-button, .wp-block-button__link', 'caption' => '.wp-element-caption, .wp-block-audio figcaption, .wp-block-embed figcaption, .wp-block-gallery figcaption, .wp-block-image figcaption, .wp-block-table figcaption, .wp-block-video figcaption', 'cite' => 'cite', 'textInput' => 'textarea, input:where([type=email],[type=number],[type=password],[type=search],[type=text],[type=tel],[type=url])', 'select' => 'select'];
    const __EXPERIMENTAL_ELEMENT_CLASS_NAMES = ['button' => 'wp-element-button', 'caption' => 'wp-element-caption'];
    const BLOCK_SUPPORT_FEATURE_LEVEL_SELECTORS = ['__experimentalBorder' => 'border', 'color' => 'color', 'dimensions' => 'dimensions', 'spacing' => 'spacing', 'typography' => 'typography'];
    const APPEARANCE_TOOLS_OPT_INS = [['background', 'backgroundImage'], ['background', 'backgroundSize'], ['background', 'gradient'], ['border', 'color'], ['border', 'radius'], ['border', 'style'], ['border', 'width'], ['color', 'link'], ['color', 'heading'], ['color', 'button'], ['color', 'caption'], ['dimensions', 'aspectRatio'], ['dimensions', 'height'], ['dimensions', 'minHeight'], ['dimensions', 'minWidth'], ['dimensions', 'width'], ['position', 'sticky'], ['spacing', 'blockGap'], ['spacing', 'margin'], ['spacing', 'padding'], ['typography', 'lineHeight'], ['typography', 'textColumns']];
    const LATEST_SCHEMA = 3;
    protected $theme_json = NULL;
    protected static $blocks_metadata = [];

    /** @internal what the cached blocks metadata was worked out from */
    private static $minn_blocks_signature = null;

    public static function get_viewport_media_queries($viewport_settings = NULL, $options = [])
    {
        // The breakpoints given that are sizes (the defaults when none are given): mobile up to its size, tablet above mobile up to its own.
        $sizes = !empty($viewport_settings) ? array_filter((array) $viewport_settings, static fn ($size) => self::is_valid_viewport_breakpoint_size($size)) : static::DEFAULT_VIEWPORT_BREAKPOINTS;
        $queries = [];
        if (isset($sizes['mobile'])) {
            $queries['@mobile'] = "@media (width <= {$sizes['mobile']})";
        }
        if (isset($sizes['tablet'])) {
            $queries['@tablet'] = isset($sizes['mobile']) ? "@media ({$sizes['mobile']} < width <= {$sizes['tablet']})" : "@media (width <= {$sizes['tablet']})";
        }
        return $queries;
    }

    private static function is_valid_viewport_breakpoint_size($value)
    {
        return is_string($value) && preg_match('/^\d+(\.\d+)?(px|em|rem)$/', $value) === 1;
    }

    private static function get_viewport_breakpoint_value_in_pixels($value)
    {
        // Pixels as given, em and rem at 16 pixels each; null for anything else.
        if (!is_string($value) || preg_match('/^(\d+(?:\.\d+)?)(px|em|rem)$/', $value, $match) !== 1) {
            return null;
        }
        $pixels = $match[2] === 'px' ? (float) $match[1] : (float) $match[1] * 16;
        return $pixels == (int) $pixels ? (int) $pixels : $pixels;
    }

    private static function sanitize_viewport_settings($viewport_settings)
    {
        // The mobile and tablet sizes that are valid (tablet only when wider than mobile); the defaults when none is.
        $sizes = [];
        foreach (['mobile', 'tablet'] as $device) {
            $size = is_array($viewport_settings) ? ($viewport_settings[$device] ?? null) : null;
            if (self::is_valid_viewport_breakpoint_size($size)) {
                $sizes[$device] = $size;
            }
        }
        if (isset($sizes['mobile'], $sizes['tablet']) && self::get_viewport_breakpoint_value_in_pixels($sizes['tablet']) <= self::get_viewport_breakpoint_value_in_pixels($sizes['mobile'])) {
            unset($sizes['tablet']);
        }
        return $sizes !== [] ? $sizes : static::DEFAULT_VIEWPORT_BREAKPOINTS;
    }

    protected static function schema_in_root_and_per_origin($schema)
    {
        $schema_in_root_and_per_origin = $schema;
        foreach (static::VALID_ORIGINS as $origin) {
            $schema_in_root_and_per_origin[$origin] = $schema;
        }
        return $schema_in_root_and_per_origin;
    }

    private function process_pseudo_selectors($node, $base_selector, $settings, $block_name, $block_metadata = NULL, $style_variation = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::process_pseudo_selectors');
        return null;
    }

    public static function get_element_class_name($element)
    {
        return static::__EXPERIMENTAL_ELEMENT_CLASS_NAMES[$element] ?? '';
    }

    /**
     * A theme.json as an origin brings it (probe theme-json-construct):
     * migrated to the latest schema, shared variations spread to the blocks
     * that have them, sanitized against the registered blocks, elements and
     * variations, appearance tools opted in, and each preset kept under the
     * origin. An origin that is not one is the theme.
     */
    public function __construct($theme_json = ['version' => 3], $origin = 'theme')
    {
        $origin = in_array($origin, static::VALID_ORIGINS, true) ? $origin : 'theme';
        $theme_json = WP_Theme_JSON_Schema::migrate($theme_json, $origin);
        $blocks_metadata = static::get_blocks_metadata();
        $valid_variations = static::get_valid_block_style_variations($blocks_metadata);
        $theme_json = static::unwrap_shared_block_style_variations($theme_json, $valid_variations);
        $theme_json = static::sanitize($theme_json, array_keys($blocks_metadata), array_keys(static::ELEMENTS), $valid_variations);
        $this->theme_json = self::minn_presets_by_origin(static::maybe_opt_in_into_settings($theme_json), $origin);
    }

    /** @internal each preset (and the spacing scale) kept under the origin, at the root and for each block, unless it already is */
    private static function minn_presets_by_origin(array $theme_json, string $origin): array
    {
        $paths = array_merge(array_column(static::PRESETS_METADATA, 'path'), [['spacing', 'spacingScale']]);
        foreach (self::minn_setting_paths($theme_json) as $node) {
            foreach ($paths as $path) {
                $value = _wp_array_get($theme_json, array_merge($node, $path), null);
                $keyed = is_array($value) && $value !== [] && array_diff(array_keys($value), static::VALID_ORIGINS) === [];
                if ($value !== null && !$keyed) {
                    _wp_array_set($theme_json, array_merge($node, $path), [$origin => $value]);
                }
            }
        }
        return $theme_json;
    }

    /** @internal the paths of the settings nodes: the root's and each block's @return list<list<string>> */
    private static function minn_setting_paths(array $theme_json): array
    {
        $paths = isset($theme_json['settings']) ? [['settings']] : [];
        foreach (array_keys((array) ($theme_json['settings']['blocks'] ?? [])) as $block) {
            $paths[] = ['settings', 'blocks', (string) $block];
        }
        return $paths;
    }

    /**
     * @internal the schema a theme.json is sanitized against: the valid
     * styles at the root, for each element (with its pseudo selectors) and
     * each block (with its elements, pseudo selectors, custom states and
     * variations, which may nest elements and blocks); each media query of
     * the viewport for elements, blocks and variations, never the root; the
     * valid settings at the root and for each block (the viewport only at
     * the root), font families by their own schema.
     */
    private static function minn_schema(array $block_names, array $element_names, array $variations, array $queries = []): array
    {
        $elements = [];
        foreach ($element_names as $element) {
            $elements[$element] = static::VALID_STYLES + array_fill_keys(array_merge(static::VALID_ELEMENT_PSEUDO_SELECTORS[$element] ?? [], $queries), static::VALID_STYLES);
        }
        $styled = static::VALID_STYLES + ['elements' => $elements];
        $responsive = $styled + array_fill_keys($queries, $styled);
        $nested = array_fill_keys($block_names, $responsive);
        $blocks = [];
        foreach ($block_names as $block) {
            $states = array_merge(static::VALID_BLOCK_PSEUDO_SELECTORS[$block] ?? [], static::VALID_BLOCK_CUSTOM_STATES[$block] ?? []);
            $blocks[$block] = $responsive + array_fill_keys($states, static::VALID_STYLES) + ['variations' => array_fill_keys((array) ($variations[$block] ?? []), $responsive + ['blocks' => $nested])];
        }
        $settings = ['blocks' => array_fill_keys($block_names, array_diff_key(static::VALID_SETTINGS, ['viewport' => true]))] + static::VALID_SETTINGS;
        $settings['typography']['fontFamilies'] = static::schema_in_root_and_per_origin(static::FONT_FAMILY_SCHEMA);
        return ['styles' => $styled + ['blocks' => $blocks], 'settings' => $settings];
    }

    /** @internal one block's metadata: its selector and selectors, element selectors, duotone, style variations and custom states */
    private static function minn_block_metadata(WP_Block_Type $block_type, array $registered_styles): array
    {
        $selector = (string) wp_get_block_css_selector($block_type);
        $metadata = ['selector' => $selector, 'selectors' => static::get_block_selectors($block_type, $selector), 'elements' => static::get_block_element_selectors($selector)];
        $duotone = wp_get_block_css_selector($block_type, 'filter.duotone');
        if (!empty($duotone)) {
            $metadata['duotone'] = $duotone;
        }
        $variations = [];
        foreach (array_merge((array) $block_type->styles, array_values($registered_styles)) as $style) {
            if (isset($style['name'])) {
                $variations[$style['name']] = static::get_block_style_variation_selector($style['name'], $selector);
            }
        }
        if ($variations !== []) {
            $metadata['styleVariations'] = $variations;
        }
        foreach (static::VALID_BLOCK_CUSTOM_STATES[$block_type->name] ?? [] as $state) {
            if (isset($metadata['selectors']['states'][$state])) {
                $metadata['states'][$state] = $metadata['selectors']['states'][$state];
            }
        }
        return $metadata;
    }

    private static function unwrap_shared_block_style_variations($theme_json, $valid_variations)
    {
        // A shared variation goes to every block that has a style by its name, under the block's own definition of it.
        $shared = $theme_json['styles']['variations'] ?? null;
        if (!is_array($shared)) {
            return $theme_json;
        }
        unset($theme_json['styles']['variations']);
        foreach ($shared as $name => $variation) {
            foreach ((array) $valid_variations as $block => $names) {
                if (is_array($variation) && in_array($name, (array) $names, true)) {
                    $own = $theme_json['styles']['blocks'][$block]['variations'][$name] ?? [];
                    $theme_json['styles']['blocks'][$block]['variations'][$name] = array_replace_recursive($variation, is_array($own) ? $own : []);
                }
            }
        }
        return $theme_json;
    }

    protected static function maybe_opt_in_into_settings($theme_json)
    {
        if (($theme_json['settings']['appearanceTools'] ?? null) === true) {
            static::do_opt_in_into_settings($theme_json['settings']);
        }
        foreach (array_keys((array) ($theme_json['settings']['blocks'] ?? [])) as $block) {
            if (($theme_json['settings']['blocks'][$block]['appearanceTools'] ?? null) === true) {
                static::do_opt_in_into_settings($theme_json['settings']['blocks'][$block]);
            }
        }
        return $theme_json;
    }

    protected static function do_opt_in_into_settings(&$context)
    {
        // Each appearance tool not already set is turned on; the switch itself goes.
        foreach (static::APPEARANCE_TOOLS_OPT_INS as $path) {
            if (_wp_array_get($context, $path, null) === null) {
                _wp_array_set($context, $path, true);
            }
        }
        unset($context['appearanceTools']);
    }

    protected static function sanitize($input, $valid_block_names, $valid_element_names, $valid_variations)
    {
        if (!is_array($input)) {
            return [];
        }
        // The known top-level keys stay; styles and settings keep what the schema has, and go when nothing is left.
        $output = self::minn_viewport_sanitized(array_intersect_key($input, array_flip(static::VALID_TOP_LEVEL_KEYS)));
        $queries = array_keys(static::get_viewport_media_queries($output['settings']['viewport'] ?? null));
        $schema = self::minn_schema((array) $valid_block_names, (array) $valid_element_names, (array) $valid_variations, $queries);
        foreach (['styles', 'settings'] as $subtree) {
            $output[$subtree] = is_array($output[$subtree] ?? null) ? static::remove_keys_not_in_schema($output[$subtree], $schema[$subtree]) : [];
            if (empty($output[$subtree])) {
                unset($output[$subtree]);
            }
        }
        return $output;
    }

    /** @internal a viewport setting, even an empty one, made valid (its media queries decide which responsive styles stay) */
    private static function minn_viewport_sanitized(array $theme_json): array
    {
        if (is_array($theme_json['settings'] ?? null) && array_key_exists('viewport', $theme_json['settings'])) {
            $theme_json['settings']['viewport'] = static::sanitize_viewport_settings($theme_json['settings']['viewport']);
        }
        return $theme_json;
    }

    protected static function append_to_selector($selector, $to_append)
    {
        return implode(', ', array_map(static fn ($part) => $part . $to_append, static::split_selector_list((string) $selector)));
    }

    protected static function prepend_to_selector($selector, $to_prepend)
    {
        return implode(', ', array_map(static fn ($part) => $to_prepend . $part, static::split_selector_list((string) $selector)));
    }

    protected static function split_selector_list($selector)
    {
        // Commas inside parentheses (:is(), :where()) do not split.
        $parts = [];
        $current = '';
        $depth = 0;
        foreach (str_split((string) $selector) as $char) {
            $depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);
        return $parts;
    }

    protected static function get_blocks_metadata()
    {
        // Each registered block's selectors, worked out again only when the registered blocks or block styles change.
        $blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $styles = WP_Block_Styles_Registry::get_instance()->get_all_registered();
        $signature = md5(serialize([array_keys($blocks), array_map('array_keys', (array) $styles)]));
        if (self::$minn_blocks_signature === $signature) {
            return static::$blocks_metadata;
        }
        static::$blocks_metadata = [];
        foreach ($blocks as $name => $block_type) {
            static::$blocks_metadata[$name] = self::minn_block_metadata($block_type, (array) ($styles[$name] ?? []));
        }
        self::$minn_blocks_signature = $signature;
        return static::$blocks_metadata;
    }

    protected static function remove_keys_not_in_schema($tree, $schema)
    {
        // Keys the schema names stay; where it expects more structure, the value is pruned in turn and dropped if nothing is left.
        foreach ($tree as $key => $value) {
            if (!array_key_exists($key, $schema)) {
                unset($tree[$key]);
                continue;
            }
            if (!is_array($schema[$key])) {
                continue;
            }
            if (!is_array($value)) {
                unset($tree[$key]);
                continue;
            }
            if (wp_is_numeric_array($value)) {
                foreach ($value as $index => $item) {
                    $tree[$key][$index] = is_array($item) ? static::remove_keys_not_in_schema($item, (array) ($schema[$key][0] ?? [])) : $item;
                }
                continue;
            }
            $tree[$key] = static::remove_keys_not_in_schema($value, $schema[$key]);
            if (empty($tree[$key])) {
                unset($tree[$key]);
            }
        }
        return $tree;
    }

    public function get_settings()
    {
        return (array) ($this->theme_json['settings'] ?? []);
    }

    public function get_stylesheet($types = ['variables', 'styles', 'presets'], $origins = NULL, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_stylesheet');
        return null;
    }

    /** A block's own CSS under its selector, nested rules unfolded (Minn\Theme\CustomCss::scoped, probe block-supports). */
    public static function process_blocks_custom_css($css, $selector)
    {
        return \Minn\Theme\CustomCss::scoped((string) $css, (string) $selector);
    }

    /** The styles' own CSS: the top-level css, then each block's (deprecated since 6.7.0 for get_stylesheet). */
    public function get_custom_css()
    {
        _deprecated_function(__METHOD__, '6.7.0', 'get_stylesheet');
        return \Minn\Theme\CustomCss::ofStyles((array) ($this->theme_json['styles'] ?? []));
    }

    public function get_custom_templates()
    {
        $templates = [];
        foreach ((array) ($this->theme_json['customTemplates'] ?? []) as $item) {
            if (isset($item['name'])) {
                $templates[$item['name']] = ['title' => $item['title'] ?? '', 'postTypes' => $item['postTypes'] ?? ['page']];
            }
        }
        return $templates;
    }

    public function get_template_parts()
    {
        $parts = [];
        foreach ((array) ($this->theme_json['templateParts'] ?? []) as $item) {
            if (isset($item['name'])) {
                $parts[$item['name']] = ['title' => $item['title'] ?? '', 'area' => $item['area'] ?? ''];
            }
        }
        return $parts;
    }

    protected function get_block_classes($style_nodes)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_classes');
        return null;
    }

    protected function get_layout_styles($block_metadata, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_layout_styles');
        return null;
    }

    protected function get_preset_classes($setting_nodes, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_preset_classes');
        return null;
    }

    protected function get_css_variables($nodes, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_css_variables');
        return null;
    }

    private static function get_feature_selector($feature_selectors, $feature_key, $default_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_feature_selector');
        return '';
    }

    protected static function to_ruleset($selector, $declarations)
    {
        if (empty($declarations)) {
            return '';
        }
        $rules = '';
        foreach ($declarations as $declaration) {
            $rules .= $declaration['name'] . ': ' . $declaration['value'] . ';';
        }
        return $selector . '{' . $rules . '}';
    }

    protected static function compute_preset_classes($settings, $selector, $origins)
    {
        // A utility class per preset slug for each property; at the root bare, on a block inside :where() of its selector.
        $prefix = $selector === static::ROOT_BLOCK_SELECTOR ? '' : ':where(' . $selector . ')';
        $stylesheet = '';
        foreach (static::PRESETS_METADATA as $preset_metadata) {
            if (empty($preset_metadata['classes'])) {
                continue;
            }
            $slugs = static::get_settings_slugs($settings, $preset_metadata, $origins);
            foreach ($preset_metadata['classes'] as $class => $property) {
                foreach ($slugs as $slug) {
                    $value = 'var(' . static::replace_slug_in_string($preset_metadata['css_vars'], $slug) . ') !important';
                    $stylesheet .= static::to_ruleset($prefix . static::replace_slug_in_string($class, $slug), [['name' => $property, 'value' => $value]]);
                }
            }
        }
        return $stylesheet;
    }

    public static function scope_selector($scope, $selector)
    {
        if (!$scope || !$selector) {
            return $selector;
        }
        $scoped = [];
        foreach (static::split_selector_list($scope) as $outer) {
            foreach (static::split_selector_list($selector) as $inner) {
                $scoped[] = $outer . ' ' . $inner;
            }
        }
        return implode(', ', $scoped);
    }

    protected static function scope_style_node_selectors($scope, $node)
    {
        // The node's selector and its feature selectors are scoped; its duotone selector is left alone.
        $node['selector'] = static::scope_selector($scope, $node['selector'] ?? null);
        foreach ((array) ($node['selectors'] ?? []) as $feature => $selector) {
            if (is_array($selector)) {
                foreach ($selector as $part => $subselector) {
                    $node['selectors'][$feature][$part] = static::scope_selector($scope, $subselector);
                }
            } elseif (is_string($selector)) {
                $node['selectors'][$feature] = static::scope_selector($scope, $selector);
            }
        }
        return $node;
    }

    protected static function get_settings_values_by_slug($settings, $preset_metadata, $origins)
    {
        // Each origin's presets in turn, by slug in kebab case: the value key's value, or what the value function makes of the preset.
        $per_origin = _wp_array_get($settings, $preset_metadata['path'], []);
        $values = [];
        foreach ((array) $origins as $origin) {
            foreach ((array) ($per_origin[$origin] ?? []) as $preset) {
                $slug = _wp_to_kebab_case((string) ($preset['slug'] ?? ''));
                if (isset($preset_metadata['value_key'], $preset[$preset_metadata['value_key']])) {
                    $values[$slug] = $preset[$preset_metadata['value_key']];
                } elseif (isset($preset_metadata['value_func']) && is_callable($preset_metadata['value_func'])) {
                    $values[$slug] = call_user_func($preset_metadata['value_func'], $preset, $settings);
                }
            }
        }
        return $values;
    }

    protected static function get_settings_slugs($settings, $preset_metadata, $origins = NULL)
    {
        $per_origin = _wp_array_get($settings, $preset_metadata['path'], []);
        $slugs = [];
        foreach ((array) ($origins ?? static::VALID_ORIGINS) as $origin) {
            foreach ((array) ($per_origin[$origin] ?? []) as $preset) {
                $slug = _wp_to_kebab_case((string) ($preset['slug'] ?? ''));
                $slugs[$slug] = $slug;
            }
        }
        return $slugs;
    }

    protected static function replace_slug_in_string($input, $slug)
    {
        return str_replace('$slug', (string) $slug, (string) $input);
    }

    protected static function compute_preset_vars($settings, $origins)
    {
        $declarations = [];
        foreach (static::PRESETS_METADATA as $preset_metadata) {
            if (empty($preset_metadata['css_vars'])) {
                continue;
            }
            foreach (static::get_settings_values_by_slug($settings, $preset_metadata, $origins) as $slug => $value) {
                $declarations[] = ['name' => static::replace_slug_in_string($preset_metadata['css_vars'], $slug), 'value' => $value];
            }
        }
        return $declarations;
    }

    protected static function compute_theme_vars($settings)
    {
        $declarations = [];
        foreach (static::flatten_tree((array) ($settings['custom'] ?? [])) as $name => $value) {
            $declarations[] = ['name' => '--wp--custom--' . $name, 'value' => $value];
        }
        return $declarations;
    }

    protected static function flatten_tree($tree, $prefix = '', $token = '--')
    {
        // Keys in kebab case, nested ones joined by the token, the prefix before the first.
        $result = [];
        foreach ($tree as $property => $value) {
            $key = $prefix . str_replace('/', '-', strtolower(_wp_to_kebab_case((string) $property)));
            if (is_array($value)) {
                $result = array_merge($result, static::flatten_tree($value, $key . $token, $token));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    protected static function compute_style_properties($styles, $settings = [], $properties = NULL, $theme_json = NULL, $selector = NULL, $use_root_padding = NULL)
    {
        // Each property with a value at its path, in the list's order: an array's sides stand in for its shorthand, presets stay as written.
        $styles = self::minn_background_defaults((array) $styles);
        $root_padding = $use_root_padding && $selector === static::ROOT_BLOCK_SELECTOR && is_array($styles['spacing']['padding'] ?? null);
        $declarations = [];
        foreach ($properties ?? static::PROPERTIES_METADATA as $name => $path) {
            $root = str_starts_with((string) $name, '--wp--style--root--padding');
            $value = $root !== $root_padding && ($root || str_starts_with((string) $name, 'padding')) ? null : self::minn_declared_value((array) $styles, (string) $name, (array) $path, (array) $settings, $theme_json);
            if ($value !== null && $name === 'aspect-ratio') {
                $declarations[] = ['name' => 'min-height', 'value' => 'unset'];
            }
            if ($value !== null) {
                $declarations[] = ['name' => $name, 'value' => $value];
            }
        }
        return $declarations;
    }

    protected static function get_property_value($styles, $path, $theme_json = NULL)
    {
        // The value at the path, '' when there is none; a ref is followed only to a non-empty string.
        $value = _wp_array_get($styles, $path, '');
        if (is_array($value) && isset($value['ref'])) {
            $referenced = _wp_array_get((array) $theme_json, explode('.', (string) $value['ref']));
            if (is_string($referenced) && $referenced !== '') {
                return $referenced;
            }
        }
        return $value;
    }

    protected static function get_setting_nodes($theme_json, $selectors = [])
    {
        // The root's settings, then each block's, with the selectors given for it.
        if (!isset($theme_json['settings'])) {
            return [];
        }
        $nodes = [['path' => ['settings'], 'selector' => static::ROOT_CSS_PROPERTIES_SELECTOR]];
        foreach (array_keys((array) ($theme_json['settings']['blocks'] ?? [])) as $name) {
            $nodes[] = ['path' => ['settings', 'blocks', $name], 'selector' => $selectors[$name]['selector'] ?? null, 'selectors' => $selectors[$name]['selectors'] ?? []];
        }
        return $nodes;
    }

    protected static function get_style_nodes($theme_json, $selectors = [], $options = [])
    {
        // The root, its elements in their own order (each pseudo selector a node of its own), then the blocks.
        if (!isset($theme_json['styles'])) {
            return [];
        }
        $nodes = [['path' => ['styles'], 'selector' => static::ROOT_BLOCK_SELECTOR]];
        foreach (static::ELEMENTS as $element => $selector) {
            if (isset($theme_json['styles']['elements'][$element])) {
                $nodes = array_merge($nodes, self::minn_element_nodes(['styles', 'elements', $element], $selector, (array) $theme_json['styles']['elements'][$element], $element));
            }
        }
        return array_merge($nodes, static::get_block_nodes($theme_json, empty($selectors) ? static::get_blocks_metadata() : $selectors, (array) $options));
    }

    public function get_styles_block_nodes()
    {
        return static::get_block_nodes($this->theme_json, static::get_blocks_metadata());
    }

    private static function update_separator_declarations($declarations)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_separator_declarations');
        return null;
    }

    private static function update_paragraph_text_indent_selector($feature_declarations, $settings, $block_name)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_paragraph_text_indent_selector');
        return null;
    }

    private static function update_button_width_declarations($feature_declarations, $settings)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_button_width_declarations');
        return null;
    }

    private static function get_block_nodes($theme_json, $selectors = [], $options = [])
    {
        // Each block in the order given, with its pseudo selectors, states and elements; only paths when asked.
        $nodes = [];
        foreach ((array) ($theme_json['styles']['blocks'] ?? []) as $name => $block) {
            $nodes = array_merge($nodes, empty($options['include_node_paths_only'])
                ? self::minn_block_nodes((string) $name, (array) $block, (array) ($selectors[$name] ?? []), (array) $options)
                : self::minn_block_paths((string) $name, (array) $block, (array) $options));
        }
        return $nodes;
    }

    /** @internal an uploaded image (one with an id) covers unless sized, and is centred when contained */
    private static function minn_background_defaults(array $styles): array
    {
        if (empty($styles['background']['backgroundImage']['id'])) {
            return $styles;
        }
        $styles['background']['backgroundSize'] ??= 'cover';
        if ($styles['background']['backgroundSize'] === 'contain') {
            $styles['background']['backgroundPosition'] ??= '50% 50%';
        }
        return $styles;
    }

    /** @internal a property's value, null when it has none: a background image as url(), a font size through fluid typography */
    private static function minn_declared_value(array $styles, string $name, array $path, array $settings, $theme_json)
    {
        $value = $name === 'background-image' ? self::minn_background_image($styles, $path, $theme_json) : static::get_property_value($styles, $path, $theme_json);
        if ($value === '' || $value === null || is_array($value)) {
            return null;
        }
        return $name === 'font-size' ? wp_get_typography_font_size_value(['size' => $value], $settings) : $value;
    }

    /** @internal a background image's url() (its ref followed, to an image or a string), or the string given; nothing else */
    private static function minn_background_image(array $styles, array $path, $theme_json)
    {
        $image = _wp_array_get($styles, $path, null);
        if (is_array($image) && isset($image['ref'])) {
            $image = _wp_array_get((array) $theme_json, explode('.', (string) $image['ref']), null);
        }
        if (is_array($image)) {
            return ($image['url'] ?? '') !== '' ? "url('" . $image['url'] . "')" : null;
        }
        return is_string($image) ? $image : null;
    }

    /** @internal an element's node, then one for each of its pseudo selectors that has styles, in their own order */
    private static function minn_element_nodes(array $path, string $selector, array $styles, string $element): array
    {
        $nodes = [['path' => $path, 'selector' => $selector]];
        foreach (static::VALID_ELEMENT_PSEUDO_SELECTORS[$element] ?? [] as $pseudo) {
            if (isset($styles[$pseudo])) {
                $nodes[] = ['path' => $path, 'selector' => static::append_to_selector($selector, $pseudo)];
            }
        }
        return $nodes;
    }

    /** @internal a block's node, its pseudo selectors and states, then its elements the block's metadata knows */
    private static function minn_block_nodes(string $name, array $block, array $meta, array $options): array
    {
        $path = ['styles', 'blocks', $name];
        $selector = $meta['selector'] ?? null;
        $node = ['name' => $name, 'path' => $path, 'selector' => $selector, 'selectors' => $meta['selectors'] ?? null, 'elements' => $meta['elements'] ?? [], 'duotone' => $meta['duotone'] ?? null, 'variations' => [], 'css' => $selector];
        if (!empty($options['include_block_style_variations'])) {
            $node['variations'] = array_map(static fn ($variation) => ['name' => $variation, 'path' => array_merge($path, ['variations', $variation]), 'selector' => $meta['styleVariations'][$variation] ?? null], array_keys((array) ($block['variations'] ?? [])));
        }
        $nodes = array_merge([$node], self::minn_block_state_nodes($node, $block, $meta));
        foreach ((array) ($block['elements'] ?? []) as $element => $styles) {
            if (isset($meta['elements'][$element])) {
                $nodes = array_merge($nodes, self::minn_element_nodes(array_merge($path, ['elements', $element]), $meta['elements'][$element], (array) $styles, (string) $element));
            }
        }
        return $nodes;
    }

    /** @internal a block's pseudo selectors that have styles (appended to each of its selectors), then its custom states */
    private static function minn_block_state_nodes(array $node, array $block, array $meta): array
    {
        $nodes = [];
        foreach (static::VALID_BLOCK_PSEUDO_SELECTORS[$node['name']] ?? [] as $pseudo) {
            if (isset($block[$pseudo])) {
                $selector = static::append_to_selector($node['selector'], $pseudo);
                $nodes[] = array_replace($node, ['path' => array_merge($node['path'], [$pseudo]), 'selector' => $selector, 'selectors' => self::minn_selectors_appended($node['selectors'], $pseudo), 'css' => $selector]);
            }
        }
        foreach (static::VALID_BLOCK_CUSTOM_STATES[$node['name']] ?? [] as $state) {
            if (isset($block[$state])) {
                $nodes[] = array_replace($node, ['path' => array_merge($node['path'], [$state]), 'selector' => $meta['states'][$state] ?? null, 'css' => $meta['states'][$state] ?? null]);
            }
        }
        return $nodes;
    }

    /** @internal each of a block's feature selectors with the pseudo selector appended */
    private static function minn_selectors_appended($selectors, string $pseudo)
    {
        if (!is_array($selectors)) {
            return $selectors;
        }
        return array_map(static fn ($selector) => is_array($selector) ? self::minn_selectors_appended($selector, $pseudo) : static::append_to_selector($selector, $pseudo), $selectors);
    }

    /** @internal a block's path, its variations' when asked, and each of its elements' */
    private static function minn_block_paths(string $name, array $block, array $options): array
    {
        $path = ['styles', 'blocks', $name];
        $node = ['path' => $path];
        if (!empty($options['include_block_style_variations']) && isset($block['variations'])) {
            $node['variations'] = array_map(static fn ($variation) => ['path' => array_merge($path, ['variations', $variation])], array_keys((array) $block['variations']));
        }
        $nodes = [$node];
        foreach (array_keys((array) ($block['elements'] ?? [])) as $element) {
            $nodes[] = ['path' => array_merge($path, ['elements', $element])];
        }
        return $nodes;
    }

    public function get_styles_for_block($block_metadata)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_styles_for_block');
        return null;
    }

    public function get_root_layout_rules($selector, $block_metadata, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_root_layout_rules');
        return null;
    }

    protected static function get_metadata_boolean($data, $path, $default_value = false)
    {
        return _wp_array_get($data, $path, $default_value);
    }

    /** Another layer over this one: maps merge key by key, lists (palettes, sizes) are replaced. */
    public function merge($incoming)
    {
        if ($incoming instanceof WP_Theme_JSON) {
            $this->theme_json = Minn\Theme\Theme::merge((array) $this->theme_json, (array) $incoming->get_raw_data());
        }
    }

    /** The hidden SVG filter of every duotone preset in the given origins, the root's then each block's (probe preview-output-helpers). */
    public function get_svg_filters($origins)
    {
        $settings = (array) ($this->theme_json['settings'] ?? []);
        $nodes = [$settings];
        foreach ((array) ($settings['blocks'] ?? []) as $block) {
            $nodes[] = (array) $block;
        }
        $filters = '';
        foreach ($nodes as $node) {
            foreach ((array) $origins as $origin) {
                foreach ((array) ($node['color']['duotone'][$origin] ?? []) as $preset) {
                    $filters .= WP_Duotone::get_filter_svg_from_preset($preset);
                }
            }
        }
        return $filters;
    }

    protected static function should_override_preset($theme_json, $path, $override)
    {
        _deprecated_function(__METHOD__, '6.0.0', 'get_metadata_boolean');
        if (is_bool($override)) {
            return $override;
        }
        // A preset may be overridden while its defaults are off: the node's own switch first, then the root's.
        foreach ([$path, ['settings']] as $base) {
            $value = _wp_array_get($theme_json, array_merge((array) $base, (array) $override));
            if (isset($value)) {
                return !$value;
            }
        }
        return true;
    }

    protected static function get_default_slugs($data, $node_path)
    {
        $slugs = [];
        foreach (static::PRESETS_METADATA as $metadata) {
            $preset = _wp_array_get($data, array_merge((array) $node_path, $metadata['path'], ['default']), null);
            if (isset($preset)) {
                _wp_array_set($slugs, $metadata['path'], array_column((array) $preset, 'slug'));
            }
        }
        return $slugs;
    }

    protected function get_name_from_defaults($slug, $base_path)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_name_from_defaults');
        return null;
    }

    protected static function filter_slugs($node, $slugs)
    {
        if (empty($slugs)) {
            return $node;
        }
        $kept = [];
        foreach ((array) $node as $item) {
            if (isset($item['slug']) && !in_array($item['slug'], $slugs, true)) {
                $kept[] = $item;
            }
        }
        return $kept;
    }

    public static function remove_insecure_properties($theme_json, $origin = 'theme')
    {
        // Migrated and sanitized; each style node and settings node keeps only what is safe, and var: values become custom properties.
        $theme_json = WP_Theme_JSON_Schema::migrate($theme_json, $origin);
        $blocks_metadata = static::get_blocks_metadata();
        $theme_json = static::sanitize($theme_json, array_keys($blocks_metadata), array_keys(static::ELEMENTS), static::get_valid_block_style_variations($blocks_metadata));
        $queries = static::get_viewport_media_queries($theme_json['settings']['viewport'] ?? null);
        $secured = ['settings' => self::minn_secure_settings($theme_json), 'styles' => self::minn_secure_styles($theme_json, $blocks_metadata, $queries)];
        foreach ($secured as $subtree => $kept) {
            $theme_json[$subtree] = self::minn_variables_converted($kept);
            if (empty($kept)) {
                unset($theme_json[$subtree]);
            }
        }
        return $theme_json;
    }

    protected static function remove_insecure_element_styles($elements, $responsive_media_queries = NULL)
    {
        // Each element in the elements' own order, even when nothing is left of it: its styles, its pseudo selectors' and its media queries'.
        $output = [];
        foreach (array_keys(static::ELEMENTS) as $element) {
            if (isset($elements[$element])) {
                $styles = (array) $elements[$element];
                $output[$element] = static::remove_insecure_styles($styles);
                foreach (array_merge(static::VALID_ELEMENT_PSEUDO_SELECTORS[$element] ?? [], array_keys((array) $responsive_media_queries)) as $key) {
                    if (isset($styles[$key])) {
                        $output[$element][$key] = static::remove_insecure_styles($styles[$key]);
                    }
                }
            }
        }
        return $output;
    }

    protected static function remove_insecure_inner_block_styles($blocks, $responsive_media_queries = NULL)
    {
        // Each block as given, even when nothing is left of it: its styles, its elements and its media queries.
        $output = [];
        foreach ((array) $blocks as $name => $styles) {
            $output[$name] = static::remove_insecure_styles((array) $styles);
            if (isset($styles['elements'])) {
                $output[$name]['elements'] = static::remove_insecure_element_styles($styles['elements'], $responsive_media_queries);
            }
            $output[$name] += self::minn_responsive_secured((array) $styles, (array) $responsive_media_queries);
        }
        return $output;
    }

    private static function preserve_valid_typed_settings($input, &$output, $schema, $path = [])
    {
        // A setting of a known type is kept when its value has that type (a string also safe as its property's value).
        foreach ($schema as $key => $type) {
            $value = $input[$key] ?? null;
            if (is_array($type) && !isset($type['type'])) {
                static::preserve_valid_typed_settings(is_array($value) ? $value : [], $output, $type, array_merge($path, [$key]));
            } elseif (is_array($type) && ($type['type'] === 'boolean' ? is_bool($value) : is_string($value) && static::is_safe_css_declaration($type['property'], $value))) {
                _wp_array_set($output, array_merge($path, [$key]), $value);
            }
        }
    }

    protected static function remove_insecure_settings($input, $is_root = false)
    {
        // Presets per origin whose slugs are class-safe and values safe for their properties, then the typed settings; the viewport at the root.
        $output = [];
        foreach (static::PRESETS_METADATA as $metadata) {
            foreach (static::VALID_ORIGINS as $origin) {
                $kept = self::minn_safe_presets(_wp_array_get((array) $input, array_merge($metadata['path'], [$origin]), null), $metadata);
                if ($kept !== []) {
                    _wp_array_set($output, array_merge($metadata['path'], [$origin]), $kept);
                }
            }
        }
        static::preserve_valid_typed_settings((array) $input, $output, self::minn_typed_settings());
        if ($is_root && array_key_exists('viewport', (array) $input)) {
            $output['viewport'] = static::sanitize_viewport_settings($input['viewport']);
        }
        return $output;
    }

    protected static function remove_insecure_styles($input)
    {
        // Each safe declaration keeps the value as written at its path; a background image object is rebuilt after the rest.
        $output = [];
        foreach (static::compute_style_properties((array) $input) as $declaration) {
            $path = static::PROPERTIES_METADATA[$declaration['name']] ?? null;
            $value = $path === null ? null : _wp_array_get((array) $input, $path, null);
            if ($value !== null && !is_array($value) && static::is_safe_css_declaration($declaration['name'], $declaration['value'])) {
                _wp_array_set($output, $path, $value);
            }
        }
        static::remove_indirect_properties((array) $input, $output);
        return $output;
    }

    /** @internal the settings nodes, each keeping only what is safe */
    private static function minn_secure_settings(array $theme_json): array
    {
        $output = [];
        foreach (static::get_setting_nodes($theme_json) as $node) {
            $kept = static::remove_insecure_settings(_wp_array_get($theme_json, $node['path'], []), $node['path'] === ['settings']);
            if (!empty($kept)) {
                _wp_array_set($output, $node['path'], $kept);
            }
        }
        return $output['settings'] ?? [];
    }

    /** @internal the style nodes in order, each keeping what is safe (set only when something is), then each block's variations */
    private static function minn_secure_styles(array $theme_json, array $blocks_metadata, array $queries): array
    {
        $output = [];
        foreach (static::get_style_nodes($theme_json, $blocks_metadata, ['include_block_style_variations' => true]) as $node) {
            $kept = self::minn_secure_node((array) _wp_array_get($theme_json, $node['path'], []), $node['path'], $queries);
            if (!empty($kept)) {
                _wp_array_set($output, $node['path'], $kept);
            }
            foreach ($node['variations'] ?? [] as $variation) {
                $kept = self::minn_secure_variation((array) _wp_array_get($theme_json, $variation['path'], []), $queries);
                if (!empty($kept)) {
                    _wp_array_set($output, $variation['path'], $kept);
                }
            }
        }
        return $output['styles'] ?? [];
    }

    /**
     * @internal one style node: kept as written when it has custom CSS and
     * the user may edit CSS; otherwise its safe styles, an element's pseudo
     * selectors, and its media queries (a block's with their elements)
     */
    private static function minn_secure_node(array $input, array $path, array $queries): array
    {
        if (isset($input['css']) && current_user_can('edit_css')) {
            return $input;
        }
        $element = ($path[count($path) - 2] ?? null) === 'elements' ? (string) end($path) : null;
        if ($element !== null) {
            return static::remove_insecure_element_styles([$element => $input], $queries)[$element] ?? [];
        }
        return static::remove_insecure_styles($input) + self::minn_responsive_secured($input, $queries);
    }

    /** @internal a variation: its safe styles, its inner blocks, its elements, then its media queries */
    private static function minn_secure_variation(array $input, array $queries): array
    {
        $output = static::remove_insecure_styles($input);
        if (isset($input['blocks'])) {
            $output['blocks'] = static::remove_insecure_inner_block_styles($input['blocks'], $queries);
        }
        if (isset($input['elements'])) {
            $output['elements'] = static::remove_insecure_element_styles($input['elements'], $queries);
        }
        return $output + self::minn_responsive_secured($input, $queries);
    }

    /** @internal each media query's styles that are safe, with its elements' (kept even when nothing is left) */
    private static function minn_responsive_secured(array $input, array $queries): array
    {
        $output = [];
        foreach (array_keys($queries) as $query) {
            if (isset($input[$query])) {
                $output[$query] = static::remove_insecure_styles((array) $input[$query]);
                if (isset($input[$query]['elements'])) {
                    $output[$query]['elements'] = static::remove_insecure_element_styles($input[$query]['elements']);
                }
            }
        }
        return $output;
    }

    /** @internal one origin's presets: those with a class-safe slug whose value is safe for each of the preset's properties */
    private static function minn_safe_presets($items, array $metadata): array
    {
        $kept = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $value = isset($metadata['value_key']) ? ($item[$metadata['value_key']] ?? null) : (is_callable($metadata['value_func'] ?? null) ? call_user_func($metadata['value_func'], $item) : null);
            $safe = !isset($metadata['value_key']) && !is_callable($metadata['value_func'] ?? null) ? true : array_filter((array) $metadata['properties'], static fn ($property) => !static::is_safe_css_declaration($property, (string) $value)) === [];
            if (isset($item['slug']) && sanitize_html_class($item['slug']) === $item['slug'] && $safe) {
                $kept[] = $item;
            }
        }
        return $kept;
    }

    /** @internal the settings kept by type, in the order they are kept: strings checked as the property they set, booleans */
    private static function minn_typed_settings(): array
    {
        return [
            'spacing' => ['blockGap' => ['type' => 'string', 'property' => 'gap']],
            'layout' => ['contentSize' => ['type' => 'string', 'property' => 'max-width'], 'wideSize' => ['type' => 'string', 'property' => 'max-width']],
            'background' => ['gradient' => ['type' => 'string', 'property' => 'background']],
            'lightbox' => ['enabled' => ['type' => 'boolean'], 'allowEditing' => ['type' => 'boolean']],
            'blockVisibility' => ['allowEditing' => ['type' => 'boolean']],
        ];
    }

    /** @internal every var: value in a tree as its custom property */
    private static function minn_variables_converted($tree)
    {
        return is_array($tree) ? array_map(static fn ($value) => self::minn_variables_converted($value), $tree) : self::convert_custom_properties($tree);
    }

    protected static function is_safe_css_declaration($property_name, $property_value)
    {
        return trim(esc_html(safecss_filter_attr($property_name . ': ' . $property_value))) !== '';
    }

    private static function remove_indirect_properties($input, &$output)
    {
        // A background image given as an object keeps only its url, and only when the url is empty or safe.
        $image = $input['background']['backgroundImage'] ?? null;
        if (is_array($image) && is_string($image['url'] ?? null) && ($image['url'] === '' || static::is_safe_css_declaration('background-image', "url('" . $image['url'] . "')"))) {
            $output['background']['backgroundImage'] = ['url' => $image['url']];
        }
    }

    public function get_raw_data()
    {
        return $this->theme_json;
    }

    public static function get_from_editor_settings($settings)
    {
        // The editor's older settings, in the order the reference reads them; a disable flag turns its setting the other way.
        $map = [
            'disableCustomColors' => [['color', 'custom'], true], 'disableCustomGradients' => [['color', 'customGradient'], true],
            'disableCustomFontSizes' => [['typography', 'customFontSize'], true], 'enableCustomLineHeight' => [['typography', 'lineHeight'], false],
            'enableCustomUnits' => [['spacing', 'units'], false], 'colors' => [['color', 'palette'], false], 'gradients' => [['color', 'gradients'], false],
            'fontSizes' => [['typography', 'fontSizes'], false], 'enableCustomSpacing' => [['spacing', 'padding'], false],
        ];
        $theme_settings = ['version' => static::LATEST_SCHEMA, 'settings' => []];
        foreach ($map as $key => [$path, $negate]) {
            if (isset($settings[$key])) {
                $value = $negate ? !$settings[$key] : $settings[$key];
                _wp_array_set($theme_settings['settings'], $path, $key === 'enableCustomUnits' && $value === true ? ['px', 'em', 'rem', 'vh', 'vw', '%'] : $value);
            }
        }
        return $theme_settings;
    }

    /** The pattern directory slugs the data lists (probe block-patterns); [] when it lists none. */
    public function get_patterns()
    {
        return is_array($this->theme_json['patterns'] ?? null) ? $this->theme_json['patterns'] : [];
    }

    /** The data as a theme.json reads: each preset flattened (the theme's, a custom one replacing it by slug; the defaults left out), every key sorted (probe theme-json-construct). */
    public function get_data()
    {
        $output = $this->theme_json;
        foreach (self::minn_setting_paths((array) $output) as $node) {
            foreach (static::PRESETS_METADATA as $metadata) {
                $path = array_merge($node, $metadata['path']);
                $preset = _wp_array_get($output, $path, null);
                if ($preset === null) {
                    continue;
                }
                $items = [];
                foreach (['theme', 'custom'] as $origin) {
                    foreach ((array) ($preset[$origin] ?? []) as $item) {
                        $slug = (string) ($item['slug'] ?? '');
                        unset($item['slug']);
                        $items[$slug] = $item;
                    }
                }
                _wp_array_set($output, $path, array_map(static fn ($slug, $item) => ['slug' => (string) $slug] + $item, array_keys($items), array_values($items)));
            }
            $output = self::minn_opt_in_folded($output, $node);
        }
        wp_recursive_ksort($output);
        return $output;
    }

    /**
     * @internal a node whose every appearance tool is set reads as having
     * asked for them: the ones turned on go (and any group left empty), and
     * appearanceTools is true; one turned off stays as it is.
     */
    private static function minn_opt_in_folded(array $output, array $node): array
    {
        foreach (static::APPEARANCE_TOOLS_OPT_INS as $path) {
            if (_wp_array_get($output, array_merge($node, $path), null) === null) {
                return $output;
            }
        }
        foreach (static::APPEARANCE_TOOLS_OPT_INS as $path) {
            [$group, $tool] = $path;
            $settings = &$output;
            foreach ($node as $key) {
                $settings = &$settings[$key];
            }
            if (($settings[$group][$tool] ?? null) === true) {
                unset($settings[$group][$tool]);
                if ($settings[$group] === []) {
                    unset($settings[$group]);
                }
            }
            $settings['appearanceTools'] = true;
            unset($settings);
        }
        return $output;
    }

    public function set_spacing_sizes()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::set_spacing_sizes');
        return null;
    }

    private static function merge_spacing_sizes($base, $incoming)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::merge_spacing_sizes');
        return null;
    }

    private static function compute_spacing_sizes($spacing_scale)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_spacing_sizes');
        return null;
    }

    private static function convert_custom_properties($value)
    {
        // A var:a|b value as the custom property var(--wp--a--b); anything else as given.
        return is_string($value) && str_starts_with($value, 'var:') ? 'var(--wp--' . str_replace('|', '--', substr($value, 4)) . ')' : $value;
    }

    private static function resolve_custom_css_format($tree)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::resolve_custom_css_format');
        return null;
    }

    protected static function get_block_selectors($block_type, $root_selector)
    {
        // A block's own selectors as given; without any, its root and the feature selectors its supports name.
        if (!empty($block_type->selectors)) {
            return $block_type->selectors;
        }
        $selectors = ['root' => $root_selector];
        foreach (static::BLOCK_SUPPORT_FEATURE_LEVEL_SELECTORS as $support => $feature) {
            $feature_selector = wp_get_block_css_selector($block_type, $support);
            if ($feature_selector !== null) {
                $selectors[$feature] = ['root' => $feature_selector];
            }
        }
        return $selectors;
    }

    protected static function get_block_element_selectors($root_selector)
    {
        // Each element under each of the block's selectors, as given (a selector after a comma keeps its leading space).
        $element_selectors = [];
        foreach (static::ELEMENTS as $element => $element_selector) {
            $list = [];
            foreach (explode(',', (string) $root_selector) as $block_selector) {
                if ($block_selector === $element_selector) {
                    $list = [$element_selector];
                    break;
                }
                $list[] = static::prepend_to_selector($element_selector, $block_selector . ' ');
            }
            $element_selectors[$element] = implode(',', $list);
        }
        return $element_selectors;
    }

    protected function get_feature_declarations_for_node($metadata, &$node)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_feature_declarations_for_node');
        return null;
    }

    private static function convert_variables_to_value($styles, $values)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::convert_variables_to_value');
        return null;
    }

    public static function resolve_variables($theme_json)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::resolve_variables');
        return null;
    }

    protected static function get_block_style_variation_selector($variation_name, $block_selector)
    {
        // The variation's class goes after the first run of characters that are neither space nor colon, in each selector of the list.
        $class = '.is-style-' . $variation_name;
        if (!$block_selector) {
            return $class;
        }
        $result = [];
        foreach (explode(',', (string) $block_selector) as $part) {
            if (preg_match('/[^\s:]+/', $part, $match, PREG_OFFSET_CAPTURE) === 1) {
                $end = $match[0][1] + strlen($match[0][0]);
                $part = substr($part, 0, $end) . $class . substr($part, $end);
            }
            $result[] = $part;
        }
        return implode(',', $result);
    }

    protected static function get_block_style_variation_feature_selector($style_variation, $feature_selector)
    {
        // A named variation's class goes into the feature selector; an unnamed one answers its own selector.
        if (isset($style_variation['name'])) {
            return static::get_block_style_variation_selector($style_variation['name'], $feature_selector);
        }
        return $style_variation['selector'] ?? $feature_selector;
    }

    protected static function get_valid_block_style_variations($blocks_metadata = [])
    {
        $valid = [];
        foreach (empty($blocks_metadata) ? static::get_blocks_metadata() : $blocks_metadata as $block => $metadata) {
            if (isset($metadata['styleVariations'])) {
                $valid[$block] = array_keys($metadata['styleVariations']);
            }
        }
        return $valid;
    }

    private static function get_block_name_from_metadata_path($block_metadata)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_name_from_metadata_path');
        return null;
    }
}
