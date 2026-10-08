<?php
/**
 * The block supports that act on a block as it renders (probe
 * block-supports): render_block filters that add a support's classes and
 * styles to the block's first tag (background, dimensions, position,
 * typography, element styles, visibility, custom CSS, style variations,
 * block-level presets), the render_block_data filters that pick a block's
 * classes before it renders, and the stylesheets they leave: rules in the
 * style engine's block-supports store, a block's custom CSS on the
 * wp-block-custom-css handle, style variations on
 * block-style-variation-styles, a block's own presets in a <style> of their
 * own. The engine's renderer applies layout, element styles and style
 * variations itself to the core blocks it renders (Runtime\BlockFilters).
 */

use Minn\Blocks\Elements;
use Minn\Blocks\RenderState;
use Minn\Blocks\Renderer;
use Minn\Theme\CustomCss;
use Minn\Theme\GlobalStyles;
use Minn\Theme\StylePresets;

/**
 * @internal adds classes, and declarations after a tag's own style, to a block's first tag
 * @param list<string> $classes
 */
function _minn_support_first_tag(string $content, array $classes, string $style = ''): string
{
    $tags = new WP_HTML_Tag_Processor($content);
    if (!$tags->next_tag()) {
        return $content;
    }
    if ($style !== '') {
        $own = trim((string) $tags->get_attribute('style'));
        $tags->set_attribute('style', $own === '' ? $style : rtrim($own, '; ') . ';' . $style);
    }
    foreach ($classes as $class) {
        $tags->add_class($class);
    }
    return $tags->get_updated_html();
}

/** @internal the rules a support records go to the block-supports store (the engine's renderer hands its own over through minn_block_support_rules) */
function _minn_store_block_support_rules($rules)
{
    wp_style_engine_get_stylesheet_from_css_rules((array) $rules, ['context' => 'block-supports']);
}

/** @internal the page's block-support stylesheet, for the global styles the engine prints (minn_block_supports_css) */
function _minn_block_supports_css($css)
{
    return (string) $css . wp_style_engine_get_stylesheet_from_context('block-supports');
}

// Background.

/**
 * A background image as the first tag's styles (a size of cover by default,
 * a contained one centred) with has-background.
 */
function wp_render_background_support($block_content, $block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? ''));
    $background = (array) ($block['attrs']['style']['background'] ?? []);
    if (!block_has_support($block_type, ['background', 'backgroundImage'], false) || wp_should_skip_block_supports_serialization($block_type, 'background', 'backgroundImage') || empty($background['backgroundImage'])) {
        return $block_content;
    }
    $background['backgroundSize'] ??= 'cover';
    if ($background['backgroundSize'] === 'contain' && !isset($background['backgroundPosition'])) {
        $background['backgroundPosition'] = '50% 50%';
    }
    $css = (string) (wp_style_engine_get_styles(['background' => $background])['css'] ?? '');
    return $css === '' ? $block_content : _minn_support_first_tag((string) $block_content, ['has-background'], $css);
}

// Dimensions.

/** Whether an aspect ratio names one: anything but empty and auto. */
function wp_is_explicit_aspect_ratio_value($aspect_ratio)
{
    return is_string($aspect_ratio) && $aspect_ratio !== '' && $aspect_ratio !== 'auto';
}

/**
 * An aspect ratio on the first tag, which unsets the height and minimum
 * height (has-aspect-ratio); a minimum height without one unsets the
 * aspect ratio.
 */
function wp_render_dimensions_support($block_content, $block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? ''));
    $dimensions = (array) ($block['attrs']['style']['dimensions'] ?? []);
    if (!block_has_support($block_type, ['dimensions', 'aspectRatio'], false) || wp_should_skip_block_supports_serialization($block_type, 'dimensions', 'aspectRatio')) {
        return $block_content;
    }
    if (wp_is_explicit_aspect_ratio_value($dimensions['aspectRatio'] ?? null)) {
        return _minn_support_first_tag((string) $block_content, ['has-aspect-ratio'], "aspect-ratio:{$dimensions['aspectRatio']};height:unset;min-height:unset;");
    }
    return empty($dimensions['minHeight']) ? $block_content : _minn_support_first_tag((string) $block_content, [], 'aspect-ratio:unset;');
}

// Position.

/**
 * A sticky (or fixed) position the block and the theme both allow: a
 * numbered wp-container-N class (counted for any position named) with
 * is-position-{type}, its offsets (the top one below the admin bar), the
 * position and a z-index in the block-supports store.
 */
function wp_render_position_support($block_content, $block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? ''));
    $position = (array) ($block['attrs']['style']['position'] ?? []);
    $type = (string) ($position['type'] ?? '');
    if ($type === '' || !block_has_support($block_type, 'position', false)) {
        return $block_content;
    }
    $class = wp_unique_prefixed_id('wp-container-');
    if (!in_array($type, ['sticky', 'fixed'], true) || !block_has_support($block_type, ['position', $type], false) || !wp_get_global_settings(['position', $type]) || wp_should_skip_block_supports_serialization($block_type, 'position')) {
        return $block_content;
    }
    $declarations = [];
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        $offset = (string) ($position[$side] ?? '');
        if ($offset !== '') {
            $declarations[$side] = $side === 'top' ? "calc({$offset} + var(--wp-admin--admin-bar--position-offset, 0px))" : $offset;
        }
    }
    $declarations += ['position' => $type, 'z-index' => '10'];
    wp_style_engine_get_stylesheet_from_css_rules([['selector' => ".{$class}", 'declarations' => $declarations]], ['context' => 'block-supports']);
    return _minn_support_first_tag((string) $block_content, [$class, "is-position-{$type}"]);
}

// Typography.

/** A font size preset as its custom property and a semicolon; any other value as it is. */
function wp_typography_get_preset_inline_style_value($style_value, $css_property)
{
    if (!is_string($style_value) || !str_contains($style_value, 'var:preset|')) {
        return $style_value;
    }
    $slug = substr($style_value, (int) strrpos($style_value, '|') + 1);
    return sprintf('var(--wp--preset--%s--%s);', $css_property, _wp_to_kebab_case($slug));
}

/**
 * A custom font size the block stored in its first tag's style, made fluid
 * where the theme's fluid typography changes it; the style rewritten a
 * declaration at a time.
 */
function wp_render_typography_support($block_content, $block)
{
    $size = $block['attrs']['style']['typography']['fontSize'] ?? null;
    if (!is_string($size) || $size === '' || str_starts_with($size, 'var:')) {
        return $block_content;
    }
    $fluid = (string) wp_get_typography_font_size_value(['size' => $size]);
    $tags = new WP_HTML_Tag_Processor((string) $block_content);
    if ($fluid === $size || !$tags->next_tag()) {
        return $block_content;
    }
    $declarations = array_filter(array_map('trim', explode(';', (string) $tags->get_attribute('style'))), 'strlen');
    $changed = false;
    foreach ($declarations as $i => $declaration) {
        [$property, $value] = array_map('trim', explode(':', $declaration, 2)) + [1 => ''];
        if (strtolower($property) === 'font-size' && $value === $size) {
            $declarations[$i] = "font-size:{$fluid}";
            $changed = true;
        }
    }
    if (!$changed) {
        return $block_content;
    }
    $tags->set_attribute('style', implode('', array_map(static fn (string $d) => "{$d};", $declarations)));
    return $tags->get_updated_html();
}

// Element styles.

/** Whether element styles earn a class (a link's text colour, a heading's or a button's colours), each kind unless options skip it. */
function wp_should_add_elements_class_name($block, $options)
{
    $skip = [];
    foreach (['link', 'heading', 'button'] as $kind) {
        $skip[$kind] = !empty($options[$kind]['skip']);
    }
    return Elements::shouldAdd((array) ($block['attrs']['style']['elements'] ?? []), $skip);
}

/** A block's numbered wp-elements-N class, added to its className, with its element rules recorded. */
function wp_render_elements_support_styles($parsed_block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($parsed_block['blockName'] ?? ''));
    $class = Elements::className((array) ($parsed_block['attrs'] ?? []), $block_type instanceof WP_Block_Type ? (array) $block_type->supports : []);
    if ($class !== null) {
        $parsed_block['attrs']['className'] = trim(($parsed_block['attrs']['className'] ?? '') . ' ' . $class);
    }
    return $parsed_block;
}

/** The wp-elements-N class a block's className carries, on its first tag. */
function wp_render_elements_class_name($block_content, $block)
{
    if (preg_match('/\bwp-elements-\S+/', (string) ($block['attrs']['className'] ?? ''), $class) !== 1) {
        return $block_content;
    }
    return _minn_support_first_tag((string) $block_content, [$class[0]]);
}

// Visibility.

/**
 * A block hidden everywhere renders nothing; one hidden on some viewports
 * gets a wp-block-hidden-{viewport} class each (in name order), whose rule
 * hides it under that viewport's media query.
 */
function wp_render_block_visibility_support($block_content, $block)
{
    $visibility = $block['attrs']['metadata']['blockVisibility'] ?? null;
    if ($visibility === false) {
        return '';
    }
    $queries = ['desktop' => '@media (width > 782px)', 'mobile' => '@media (width <= 480px)', 'tablet' => '@media (480px < width <= 782px)'];
    $hidden = array_keys(array_intersect_key(array_filter((array) ($visibility['viewport'] ?? []), static fn ($shown) => $shown === false), $queries));
    if ($hidden === []) {
        return $block_content;
    }
    sort($hidden);
    $classes = [];
    foreach ($hidden as $viewport) {
        $classes[] = "wp-block-hidden-{$viewport}";
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => ".wp-block-hidden-{$viewport}", 'declarations' => ['display' => 'none !important'], 'rules_group' => $queries[$viewport]]], ['context' => 'block-supports']);
    }
    return _minn_support_first_tag((string) $block_content, $classes);
}

// Custom CSS.

/** Strips custom CSS from the blocks a post saves (who may not edit CSS writes none). */
function wp_custom_css_kses_init_filters()
{
    add_filter('content_save_pre', 'wp_strip_custom_css_from_blocks', 8);
    add_filter('content_filtered_save_pre', 'wp_strip_custom_css_from_blocks', 8);
}

/** Takes the custom CSS stripping off the content filters. */
function wp_custom_css_remove_filters()
{
    remove_filter('content_save_pre', 'wp_strip_custom_css_from_blocks', 8);
    remove_filter('content_filtered_save_pre', 'wp_strip_custom_css_from_blocks', 8);
}

/** Custom CSS stripping for a user who may not edit CSS (on init and when the user changes). */
function wp_custom_css_kses_init()
{
    wp_custom_css_remove_filters();
    if (!current_user_can('edit_css')) {
        wp_custom_css_kses_init_filters();
    }
}

/** An import that forces filtered HTML strips custom CSS too; the value passes through. */
function wp_custom_css_force_filtered_html_on_import_filter($arg)
{
    if ($arg) {
        wp_custom_css_kses_init_filters();
    }
    return $arg;
}

/**
 * A block's own CSS (style.css): a wp-custom-css-* class added to its
 * className and the CSS, nested rules unfolded under that class, added to
 * the wp-block-custom-css handle.
 */
function wp_render_custom_css_support_styles($parsed_block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($parsed_block['blockName'] ?? ''));
    $css = $parsed_block['attrs']['style']['css'] ?? null;
    if (!is_string($css) || trim($css) === '' || !block_has_support($block_type, 'customCSS', true)) {
        return $parsed_block;
    }
    $class = 'wp-custom-css-' . substr(md5($css), 0, 8);
    if (!wp_style_is('wp-block-custom-css', 'registered')) {
        wp_register_style('wp-block-custom-css', false);
    }
    wp_add_inline_style('wp-block-custom-css', CustomCss::scoped($css, ".{$class}"));
    $parsed_block['attrs']['className'] = trim(($parsed_block['attrs']['className'] ?? '') . ' ' . $class);
    return $parsed_block;
}

/** The wp-custom-css-* class a block's className carries, and has-custom-css, on its first tag. */
function wp_render_custom_css_class_name($block_content, $block)
{
    if (preg_match('/\bwp-custom-css-\S+/', (string) ($block['attrs']['className'] ?? ''), $class) !== 1) {
        return $block_content;
    }
    return _minn_support_first_tag((string) $block_content, [$class[0], 'has-custom-css']);
}

/** Enqueues the blocks' custom CSS (wp_enqueue_scripts). */
function wp_enqueue_block_custom_css()
{
    if (wp_style_is('wp-block-custom-css', 'registered')) {
        wp_enqueue_style('wp-block-custom-css');
    }
}

// Block style variations.

/** The style variation names a class string carries (is-style-{name}). */
function wp_get_block_style_variation_name_from_class($class_string)
{
    if (!is_string($class_string)) {
        return null;
    }
    preg_match_all('/\bis-style-(?!default)(\S+)\b/', $class_string, $matches);
    return $matches[1];
}

/**
 * A variation's references ({ref: "styles.color.text"}) replaced by the
 * values they point to in the theme's data, and dropped when they point at
 * nothing.
 */
function wp_resolve_block_style_variation_ref_values(&$variation_data, $theme_json)
{
    foreach ((array) $variation_data as $key => $value) {
        if (is_array($value) && isset($value['ref']) && is_string($value['ref'])) {
            $resolved = _wp_array_get((array) $theme_json, explode('.', $value['ref']), null);
            if ($resolved === null) {
                unset($variation_data[$key]);
            } else {
                $variation_data[$key] = $resolved;
            }
        } elseif (is_array($value)) {
            wp_resolve_block_style_variation_ref_values($variation_data[$key], $theme_json);
        }
    }
}

/** Registers each theme.json partial's variation as a block style for the block types it names. */
function wp_register_block_style_variations_from_theme_json_partials($variations)
{
    foreach ((array) $variations as $variation) {
        $name = (string) ($variation['slug'] ?? _wp_to_kebab_case((string) ($variation['title'] ?? '')));
        foreach ((array) ($variation['blockTypes'] ?? []) as $block_type) {
            if ($name !== '' && !WP_Block_Styles_Registry::get_instance()->is_registered($block_type, $name)) {
                register_block_style($block_type, ['name' => $name, 'label' => (string) ($variation['title'] ?? $name)]);
            }
        }
    }
}

/** @internal a block style variation's styles: a registered style's style_data under the theme's own (minn_block_style_variation) */
function _minn_block_style_variation($variation, $block_name, $style)
{
    $registered = (array) (WP_Block_Styles_Registry::get_instance()->get_registered((string) $block_name, (string) $style)['style_data'] ?? []);
    $theme = (array) _wp_array_get(wp_get_global_styles(), ['blocks', (string) $block_name, 'variations', (string) $style], []);
    $styles = array_replace_recursive($registered, $theme);
    return $styles === [] ? $variation : $styles;
}

/** @internal a numbered variation's CSS on the block-style-variation-styles handle (minn_block_style_variation_used) */
function _minn_block_style_variation_css($block_name, $style, $instance, $variation)
{
    if (!wp_style_is('block-style-variation-styles', 'registered')) {
        wp_register_style('block-style-variation-styles', false);
    }
    wp_add_inline_style('block-style-variation-styles', GlobalStyles::variationCss((string) $block_name, (string) $style, (int) $instance, (array) $variation));
}

/** A block whose className names a style variation with styles of its own gets that variation's numbered class (is-style-{name}--N) too. */
function wp_render_block_style_variation_support_styles($parsed_block)
{
    $numbered = Renderer::numberedStyle((string) ($parsed_block['blockName'] ?? ''), (string) ($parsed_block['attrs']['className'] ?? ''));
    if ($numbered !== null) {
        $parsed_block['attrs']['className'] = trim($parsed_block['attrs']['className'] . ' ' . $numbered);
    }
    return $parsed_block;
}

/** The numbered variation class a block's className carries, on its first tag. */
function wp_render_block_style_variation_class_name($block_content, $block)
{
    if (preg_match('/\bis-style-\S+--\d+\b/', (string) ($block['attrs']['className'] ?? ''), $class) !== 1) {
        return $block_content;
    }
    return _minn_support_first_tag((string) $block_content, [$class[0]]);
}

/** Enqueues the numbered variations' styles (wp_enqueue_scripts). */
function wp_enqueue_block_style_variation_styles()
{
    if (wp_style_is('block-style-variation-styles', 'registered')) {
        wp_enqueue_style('block-style-variation-styles');
    }
}

// Block-level presets.

/** @internal whether a block carries presets of its own that its type lets it set */
function _minn_block_level_presets(array $block): bool
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? ''));
    return !empty($block['attrs']['settings']) && block_has_support($block_type, '__experimentalSettings', false);
}

/**
 * A block's own presets, as custom properties and has-* classes under its
 * wp-settings-* class, printed in a <style> of their own; the render goes
 * on. A preset list counts keyed by origin (default, blocks, theme,
 * custom), as the editor saves it; a plain list does not.
 */
function _wp_add_block_level_preset_styles($pre_render, $block)
{
    if (!_minn_block_level_presets((array) $block)) {
        return $pre_render;
    }
    $settings = (array) $block['attrs']['settings'];
    $presets = [];
    foreach (['color' => [['color', 'palette'], 'color'], 'gradient' => [['color', 'gradients'], 'gradient'], 'font-size' => [['typography', 'fontSizes'], 'size'], 'font-family' => [['typography', 'fontFamilies'], 'fontFamily'], 'spacing' => [['spacing', 'spacingSizes'], 'size']] as $kind => [$path, $key]) {
        $list = _wp_array_get($settings, $path, []);
        $presets[$kind] = [];
        foreach (is_array($list) && !array_is_list($list) ? ['default', 'blocks', 'theme', 'custom'] : [] as $origin) {
            foreach ((array) ($list[$origin] ?? []) as $preset) {
                $presets[$kind][] = ['slug' => (string) ($preset['slug'] ?? ''), 'value' => (string) ($preset[$key] ?? '')];
            }
        }
    }
    $class = _wp_get_presets_class_name($block);
    $properties = StylePresets::presetProperties($presets);
    $css = ($properties === '' ? '' : ".{$class} :root{{$properties}}") . preg_replace('/(^|})\.has-/', "\$1:where(.{$class} :root).has-", StylePresets::presetClasses($presets));
    if ($css !== '') {
        wp_enqueue_block_support_styles($css);
    }
    return $pre_render;
}

/**
 * A block's wp-settings-* class on its first tag, for a block with presets
 * of its own: ahead of its layout classes, which the reference's layout
 * filter adds after this one (the engine's renderer has added them already
 * to a core block it renders).
 */
function _wp_add_block_level_presets_class($block_content, $block)
{
    if (!_minn_block_level_presets((array) $block)) {
        return $block_content;
    }
    $tags = new WP_HTML_Tag_Processor((string) $block_content);
    if (!$tags->next_tag()) {
        return $block_content;
    }
    $classes = preg_split('/\s+/', trim((string) $tags->get_attribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $at = count($classes);
    foreach ($classes as $i => $class) {
        if (preg_match('/^(?:has-global-padding|is-layout-|is-vertical$|is-horizontal$|is-content-justification-|is-nowrap$|wp-container-|wp-block-[\w-]+-is-layout-)/', $class) === 1) {
            $at = $i;
            break;
        }
    }
    array_splice($classes, $at, 0, [_wp_get_presets_class_name($block)]);
    $tags->set_attribute('class', implode(' ', array_unique($classes)));
    return $tags->get_updated_html();
}

// Auto-registered controls.

/** A block type that opts into autoRegister marks each attribute that is not local with autoGenerateControl. */
function wp_mark_auto_generate_control_attributes(array $args): array
{
    if (empty($args['supports']['autoRegister']) || empty($args['attributes']) || !is_array($args['attributes'])) {
        return $args;
    }
    foreach ($args['attributes'] as $name => $attribute) {
        if (is_array($attribute) && ($attribute['role'] ?? null) !== 'local') {
            $args['attributes'][$name]['autoGenerateControl'] = true;
        }
    }
    return $args;
}
