<?php

use Minn\Blocks\Duotone;

/**
 * Duotone filters on blocks (probe block-supports): a block that supports
 * them gets a wp-duotone-* class for its preset or its own colors (those
 * numbered, as each list is its own filter), the CSS that points its
 * duotone selector at the filter, and the hidden SVG filters, printed once
 * in the footer for the filters the page used.
 */
class WP_Duotone
{
    private static $used_global_styles_presets = [];
    private static $used_svg_filter_data = [];
    private static $block_css_declarations = [];

    /** The filter's id for a preset (deprecated in the reference since 6.3.0). */
    public static function get_filter_id_from_preset($preset)
    {
        _deprecated_function(__FUNCTION__, '6.3.0');
        return self::filter_id((string) ($preset['slug'] ?? ''));
    }

    /** The hidden SVG filter for a preset's colors (deprecated since 6.3.0). */
    public static function get_filter_svg_from_preset($preset)
    {
        _deprecated_function(__FUNCTION__, '6.3.0');
        return Duotone::svg(self::get_filter_id_from_preset($preset), (array) ($preset['colors'] ?? []));
    }

    /** The filter value a preset sets: "unset" for an unset preset, else the filter's url (deprecated since 6.3.0). */
    public static function get_filter_css_property_value_from_preset($preset)
    {
        _deprecated_function(__FUNCTION__, '6.3.0');
        if (isset($preset['colors']) && is_string($preset['colors'])) {
            return $preset['colors'];
        }
        return 'url(#' . self::get_filter_id_from_preset($preset) . ')';
    }

    /** @internal a filter's id */
    private static function filter_id(string $slug): string
    {
        return 'wp-duotone-' . $slug;
    }

    /** @internal what a filter sets: unset colors as they are, else the filter's url */
    private static function css_value(string $slug, $colors): string
    {
        return is_string($colors) ? $colors : 'url(#' . self::filter_id($slug) . ')';
    }

    /** The style attribute for a block type that supports duotone. */
    public static function register_duotone_support($block_type)
    {
        if (block_has_support($block_type, ['filter', 'duotone'], false)) {
            if (!is_array($block_type->attributes)) {
                $block_type->attributes = [];
            }
            $block_type->attributes['style'] ??= ['type' => 'object'];
        }
    }

    /**
     * The block's duotone class and the filter it uses, recorded for the
     * styles and the footer: a preset by its slug (its CSS the preset's
     * custom property), custom colors or unset numbered (the filter's url,
     * or unset).
     */
    public static function render_duotone_support($block_content, $block, $wp_block)
    {
        $value = $block['attrs']['style']['color']['duotone'] ?? null;
        $selector = self::get_selector(WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? '')));
        if (empty($value) || $selector === null || $block_content === '') {
            return $block_content;
        }
        if (is_string($value) && str_starts_with($value, 'var:preset|duotone|')) {
            $slug = substr($value, strlen('var:preset|duotone|'));
            self::$used_global_styles_presets[$slug] = true;
            self::$used_svg_filter_data[self::filter_id($slug)] = ['slug' => $slug, 'colors' => self::preset_colors($slug)];
            $css = "var(--wp--preset--duotone--{$slug})";
        } else {
            $slug = ($value === 'unset' ? 'unset' : implode('-', array_map('sanitize_key', (array) $value))) . '-' . wp_unique_id();
            if (is_array($value)) {
                self::$used_svg_filter_data[self::filter_id($slug)] = ['slug' => $slug, 'colors' => $value];
            }
            $css = self::css_value($slug, $value);
        }
        $filter_id = self::filter_id($slug);
        self::$block_css_declarations[] = ['selector' => self::scoped(".{$filter_id}", $selector), 'declarations' => ['filter' => $css]];
        $tags = new WP_HTML_Tag_Processor($block_content);
        if ($tags->next_tag()) {
            $tags->add_class($filter_id);
        }
        return $tags->get_updated_html();
    }

    /** In an image's restored outer container (a theme without theme.json), the duotone classes move from the figure to the container. */
    public static function restore_image_outer_container($block_content)
    {
        if (wp_theme_has_theme_json() || preg_match('/^(\s*<div\b[^>]*\bclass=")([^"]*\bwp-block-image\b[^"]*)("[^>]*>\s*<figure\b[^>]*\bclass=")([^"]*)"/', (string) $block_content, $m) !== 1) {
            return $block_content;
        }
        $figure = explode(' ', $m[4]);
        $duotone = array_values(array_filter($figure, static fn (string $class) => str_starts_with($class, 'wp-duotone-')));
        if ($duotone === []) {
            return $block_content;
        }
        $outer = implode(' ', [...explode(' ', $m[2]), ...$duotone]);
        $inner = implode(' ', array_diff($figure, $duotone));
        return $m[1] . $outer . $m[3] . $inner . '"' . substr((string) $block_content, strlen($m[0]));
    }

    /** The duotone CSS of the blocks rendered, kept with the other block-support styles. */
    public static function output_block_styles()
    {
        if (self::$block_css_declarations !== []) {
            wp_style_engine_get_stylesheet_from_css_rules(self::$block_css_declarations, ['context' => 'block-supports']);
        }
    }

    /** The duotone presets the page used, as custom properties beside the global styles. */
    public static function output_global_styles()
    {
        $css = '';
        foreach (array_keys(self::$used_global_styles_presets) as $slug) {
            $css .= '--wp--preset--duotone--' . $slug . ':' . self::css_value((string) $slug, self::preset_colors((string) $slug)) . ';';
        }
        if ($css !== '') {
            wp_add_inline_style('global-styles', ":root{{$css}}");
        }
    }

    /** The hidden SVG filters the page used, in the order it used them. */
    public static function output_footer_assets()
    {
        foreach (self::$used_svg_filter_data as $filter) {
            echo Duotone::svg(self::filter_id((string) $filter['slug']), (array) $filter['colors']);
        }
    }

    /** The editor's settings, unchanged: the block editor is a dead end here. */
    public static function add_editor_settings($settings)
    {
        return $settings;
    }

    /** A block.json's old color.__experimentalDuotone selector moved to filter.duotone and its selector. */
    public static function migrate_experimental_duotone_support_flag($settings, $metadata)
    {
        $duotone = $metadata['supports']['color']['__experimentalDuotone'] ?? null;
        if (empty($duotone)) {
            return $settings;
        }
        $settings['supports']['filter']['duotone'] ??= (bool) $duotone;
        if (is_string($duotone)) {
            $settings['selectors']['filter']['duotone'] ??= self::scoped('.wp-block-' . str_replace(['core/', '/'], ['', '-'], (string) ($metadata['name'] ?? '')), $duotone);
        }
        unset($settings['supports']['color']['__experimentalDuotone']);
        return $settings;
    }

    /** @internal a block type's duotone selector (its metadata's, else its class), or null when it does not support duotone */
    private static function get_selector($block_type)
    {
        if (!$block_type instanceof WP_Block_Type || !block_has_support($block_type, ['filter', 'duotone'], false)) {
            return null;
        }
        $selector = $block_type->selectors['filter']['duotone'] ?? null;
        return is_string($selector) && $selector !== '' ? $selector : '.' . wp_get_block_default_classname($block_type->name);
    }

    /** @internal a selector list scoped to a root: the root joined to each (a leading combinator stays) */
    private static function scoped(string $root, string $selectors): string
    {
        $root = str_starts_with($root, '.') ? $root : ".{$root}";
        return implode(', ', array_map(static fn (string $s) => $root . (preg_match('/^[>~+ ]/', ltrim($s, ' ')) === 1 ? ' ' . ltrim($s) : (str_starts_with(trim($s), '.') ? trim($s) : ' ' . trim($s))), explode(',', $selectors)));
    }

    /** @internal a duotone preset's colors from the theme's settings */
    private static function preset_colors(string $slug): array
    {
        foreach ((array) wp_get_global_settings(['color', 'duotone']) as $presets) {
            foreach ((array) $presets as $preset) {
                if (($preset['slug'] ?? null) === $slug) {
                    return (array) ($preset['colors'] ?? []);
                }
            }
        }
        return [];
    }
}
