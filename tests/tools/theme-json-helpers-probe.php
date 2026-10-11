<?php
/**
 * WP_Theme_JSON's small helpers (probe theme-json-helpers): element class
 * names, selectors appended to, prepended to, split, scoped (a selector and
 * a style node's selectors), a ruleset from declarations, a slug put in a
 * string, a tree flattened into custom properties, a boolean read from the
 * data, block style variation selectors, the schema per origin, appearance
 * tools opted in, the custom templates and template parts a theme.json
 * declares, default slugs found and filtered, whether a preset may be
 * overridden, whether a declaration is safe, a block's element selectors,
 * the viewport media queries, and editor settings read as theme.json.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// A protected static method, called from inside the class.
$reach = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, WP_Theme_JSON::class)(...$args));

$say('element class names', array_map(static fn ($e) => WP_Theme_JSON::get_element_class_name($e), ['button' => 'button', 'caption' => 'caption', 'link' => 'link', 'heading' => 'heading', 'zz' => 'zz']));

$say('selectors', [
    'appended' => [$reach('append_to_selector', '.a', ':hover'), $reach('append_to_selector', '.a, .b', ':hover'), $reach('append_to_selector', ' .a ,  .b ', ' .c'), $reach('append_to_selector', '', ':x')],
    'prepended' => [$reach('prepend_to_selector', '.a', '.zz '), $reach('prepend_to_selector', '.a, .b', '.zz '), $reach('prepend_to_selector', ' .a ,  .b ', ':root ')],
    'split' => [$reach('split_selector_list', '.a, .b,.c , :is(.d, .e)'), $reach('split_selector_list', '.a'), $reach('split_selector_list', ':where(.a, .b), .c')],
    'scoped' => [
        WP_Theme_JSON::scope_selector('.scope', '.a, .b'),
        WP_Theme_JSON::scope_selector('.scope', ':root'),
        WP_Theme_JSON::scope_selector('.scope, .other', '.a, .b'),
        WP_Theme_JSON::scope_selector('', '.a'),
        WP_Theme_JSON::scope_selector(null, '.a'),
        WP_Theme_JSON::scope_selector('.scope', ''),
        WP_Theme_JSON::scope_selector('.scope', 'body'),
        WP_Theme_JSON::scope_selector('.scope', '.scope .a'),
    ],
    'a style node scoped' => $reach('scope_style_node_selectors', '.scope', ['path' => ['styles', 'blocks', 'core/image'], 'selector' => '.wp-block-image', 'selectors' => ['root' => '.wp-block-image', 'border' => '.wp-block-image img', 'typography' => ['text' => '.wp-block-image figcaption']], 'duotone' => '.wp-block-image img', 'name' => 'core/image']),
]);

$say('rulesets', [
    $reach('to_ruleset', '.a', [['name' => 'color', 'value' => 'red'], ['name' => 'margin', 'value' => '0']]),
    $reach('to_ruleset', '.a', []),
    $reach('to_ruleset', '', [['name' => 'color', 'value' => 'red']]),
    $reach('to_ruleset', '.a, .b', [['name' => '--wp--custom', 'value' => '1px']]),
]);
$say('slugs in strings', [
    $reach('replace_slug_in_string', 'var(--wp--preset--color--$slug)', 'red'),
    $reach('replace_slug_in_string', 'has-$slug-color', 'vivid-red'),
    $reach('replace_slug_in_string', 'no slug here', 'x'),
    $reach('replace_slug_in_string', 'has-$slug-font-size', 'Large Size'),
]);
$say('trees flattened', [
    $reach('flatten_tree', ['a' => ['b' => '1', 'cC' => '2'], 'd' => '3', 'eF' => ['gH' => ['i' => '4']]]),
    $reach('flatten_tree', ['a' => '1', 'b' => ['c' => '2']], 'zz', '-'),
    $reach('flatten_tree', []),
    $reach('flatten_tree', ['lineHeight' => ['body' => 1.5, 'heading' => '1.2']]),
]);
$say('booleans read', [
    $reach('get_metadata_boolean', ['a' => ['b' => true]], ['a', 'b']),
    $reach('get_metadata_boolean', ['a' => ['b' => false]], ['a', 'b'], true),
    $reach('get_metadata_boolean', ['a' => []], ['a', 'b'], true),
    $reach('get_metadata_boolean', ['a' => ['b' => 'yes']], ['a', 'b']),
    $reach('get_metadata_boolean', ['a' => ['b' => ['c' => true]]], ['a', 'b']),
]);
$say('block style variation selectors', [
    $reach('get_block_style_variation_selector', 'outline', '.wp-block-button'),
    $reach('get_block_style_variation_selector', 'outline', '.wp-block-button, .wp-block-x'),
    $reach('get_block_style_variation_selector', 'zz', ':where(.wp-block-group)'),
    $reach('get_block_style_variation_selector', 'zz', '.wp-block-group .inner'),
    $reach('get_block_style_variation_feature_selector', '.wp-block-button.is-style-outline', '.wp-block-button .wp-block-button__link'),
    $reach('get_block_style_variation_feature_selector', '.wp-block-image.is-style-rounded', '.wp-block-image img, .wp-block-image .components-placeholder'),
]);

$say('the schema per origin', $reach('schema_in_root_and_per_origin', ['palette' => null, 'custom' => null]));
$say('appearance tools opted in', [
    'asked for' => $reach('maybe_opt_in_into_settings', ['version' => 3, 'settings' => ['appearanceTools' => true, 'color' => ['link' => false], 'blocks' => ['core/group' => ['appearanceTools' => true]]]]),
    'not asked for' => $reach('maybe_opt_in_into_settings', ['version' => 3, 'settings' => ['color' => ['link' => true]]]),
    'a context' => $reach('do_opt_in_into_settings', ['appearanceTools' => true, 'border' => ['color' => false]]),
]);

$declared = new WP_Theme_JSON([
    'version' => 3,
    'customTemplates' => [['name' => 'zz-wide', 'title' => 'Zz Wide', 'postTypes' => ['post', 'page']], ['name' => 'zz-plain', 'title' => 'Zz Plain'], ['title' => 'No name']],
    'templateParts' => [['name' => 'zz-header', 'title' => 'Zz Header', 'area' => 'header'], ['name' => 'zz-misc', 'title' => 'Zz Misc'], ['name' => 'zz-bad', 'area' => 'zz-not-an-area']],
]);
$say('templates a theme.json declares', [$try(static fn () => $declared->get_custom_templates()), $try(static fn () => $declared->get_template_parts())]);

$presets = ['color' => ['palette' => ['default' => [['slug' => 'black', 'color' => '#000'], ['slug' => 'white', 'color' => '#fff']], 'theme' => [['slug' => 'black', 'color' => '#111'], ['slug' => 'zz', 'color' => '#123']]]]];
$say('default slugs', [
    'found' => $reach('get_default_slugs', ['color' => ['palette' => [['slug' => 'black'], ['slug' => 'white']]], 'blocks' => []], ['color']),
    'found at the root' => $reach('get_default_slugs', ['color' => ['palette' => [['slug' => 'a']], 'gradients' => [['slug' => 'g']]], 'typography' => ['fontSizes' => [['slug' => 'small']]]], []),
    'filtered' => $reach('filter_slugs', ['color' => ['palette' => [['slug' => 'black', 'color' => '#111'], ['slug' => 'zz', 'color' => '#123']]]], ['color' => ['palette' => ['black']]]),
    'filtered, nothing to filter' => $reach('filter_slugs', ['color' => ['palette' => [['slug' => 'zz']]]], []),
]);
$say('whether a preset may be overridden', [
    $reach('should_override_preset', ['settings' => ['color' => ['defaultPalette' => false]]], ['settings', 'color'], ['color', 'defaultPalette']),
    $reach('should_override_preset', ['settings' => ['color' => ['defaultPalette' => true]]], ['settings', 'color'], ['color', 'defaultPalette']),
    $reach('should_override_preset', ['settings' => ['color' => []]], ['settings', 'color'], ['color', 'defaultPalette']),
    $reach('should_override_preset', [], ['settings', 'color'], true),
    $reach('should_override_preset', [], ['settings', 'color'], false),
]);
$say('whether a declaration is safe', [
    $reach('is_safe_css_declaration', 'color', 'red'),
    $reach('is_safe_css_declaration', 'background', 'url(javascript:alert(1))'),
    $reach('is_safe_css_declaration', 'background-image', 'url("https://example.com/a.png")'),
    $reach('is_safe_css_declaration', 'color', 'expression(alert(1))'),
    $reach('is_safe_css_declaration', 'zz-not-a-property', '1'),
    $reach('is_safe_css_declaration', '--wp--custom', 'red'),
]);
$say('a block\'s element selectors', [$reach('get_block_element_selectors', '.wp-block-zz'), $reach('get_block_element_selectors', '.wp-block-a, .wp-block-b')]);
$say('viewport media queries', [
    $try(static fn () => WP_Theme_JSON::get_viewport_media_queries()),
    $try(static fn () => WP_Theme_JSON::get_viewport_media_queries(['mobile' => '600px', 'tablet' => '900px', 'zz' => 'not-a-size'])),
    $try(static fn () => WP_Theme_JSON::get_viewport_media_queries(null, ['zz' => 1])),
]);
$say('editor settings read as theme.json', [
    $try(static fn () => WP_Theme_JSON::get_from_editor_settings(['colors' => [['slug' => 'red', 'color' => '#f00', 'name' => 'Red']], 'disableCustomColors' => true, 'fontSizes' => [['slug' => 'big', 'size' => '2rem', 'name' => 'Big']], 'enableCustomLineHeight' => true, 'enableCustomUnits' => ['px', 'em'], 'enableCustomSpacing' => true, 'disableCustomGradients' => false])),
    $try(static fn () => WP_Theme_JSON::get_from_editor_settings([])),
    $try(static fn () => WP_Theme_JSON::get_from_editor_settings(['__experimentalFeatures' => ['zz' => 1], 'disableCustomFontSizes' => true])),
]);
$say('deprecations announced', $deprecated);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
