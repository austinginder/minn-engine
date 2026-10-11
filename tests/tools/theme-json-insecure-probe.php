<?php
/**
 * What WP_Theme_JSON keeps when it removes what is not safe (probe
 * theme-json-insecure): viewport settings made valid and the responsive
 * styles they allow; settings (presets per origin with class-safe slugs and
 * safe values, the typed settings, the viewport at the root); styles (safe
 * declarations keeping their values as written, background images);
 * elements and inner blocks with their pseudo selectors and media queries;
 * a whole theme.json, for a visitor and for a user who may edit CSS (a node
 * with custom CSS kept as written); a user's global styles post filtered on
 * save; and the kses filters that run it.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// Core and the theme register blocks and block styles on init, which the engine's probe runner does not fire.
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$reach = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, WP_Theme_JSON::class)(...$args));
$raw = static fn (array $theme_json) => $try(static fn () => (new WP_Theme_JSON($theme_json))->get_raw_data());
$safe = static fn ($theme_json, $origin = 'theme') => $try(static fn () => WP_Theme_JSON::remove_insecure_properties($theme_json, $origin));
wp_set_current_user(0);

$bad = ['color' => ['text' => 'url(javascript:1)']];
$ok = ['color' => ['text' => 'ok']];
$groupBreakpoints = ['core/group' => ['@mobile' => ['color' => ['text' => 'gm']], '@tablet' => ['color' => ['text' => 'gt']]]];
$viewports = [
    'invalid sizes' => ['mobile' => 'zz', 'tablet' => 5], 'empty' => [], 'tablet alone' => ['tablet' => '900px'], 'a string' => 'zz',
    'mobile valid, tablet not' => ['mobile' => '500px', 'tablet' => 'zz'], 'tablet narrower' => ['mobile' => '900px', 'tablet' => '500px'],
    'the same size' => ['mobile' => '600px', 'tablet' => '600px'], 'em and rem' => ['mobile' => '30em', 'tablet' => '50rem'], 'a number' => ['mobile' => 500],
    'null' => null, 'tablet first' => ['tablet' => '900px', 'mobile' => '500px'], 'other keys' => ['mobile' => '500px', 'zz' => '1px', 'desktop' => '1200px'],
];
$made = [];
foreach ($viewports as $label => $viewport) {
    $made[$label] = $raw(['version' => 3, 'settings' => ['viewport' => $viewport], 'styles' => ['blocks' => $groupBreakpoints]]);
}
$made['no viewport'] = $raw(['version' => 3, 'styles' => ['blocks' => $groupBreakpoints]]);
$made['a viewport for a block'] = $raw(['version' => 3, 'settings' => ['blocks' => ['core/group' => ['viewport' => ['mobile' => '1px']]]]]);
$say('viewports made valid', $made);
$say('responsive styles a theme.json keeps', $raw(['version' => 3, 'styles' => [
    '@mobile' => ['color' => ['text' => 'root']],
    'elements' => ['link' => ['@mobile' => ['color' => ['text' => 'el'], 'zz' => 1, 'elements' => ['x' => 1], ':hover' => ['color' => ['text' => 'q']]], ':hover' => ['@mobile' => ['color' => ['text' => 'hm']], 'color' => ['text' => 'h']]]],
    'blocks' => [
        'core/group' => ['@mobile' => ['color' => ['text' => 'gm'], 'elements' => ['link' => ['color' => ['text' => 'x']]], 'css' => 'a{}'], '@tablet' => ['color' => ['text' => 'gt']], '@zz' => ['color' => ['text' => 'z']], '@desktop' => ['color' => ['text' => 'd']], 'elements' => ['link' => ['@tablet' => ['color' => ['text' => 'glt']]]]],
        'core/button' => [':hover' => ['@mobile' => ['color' => ['text' => 'bhm']]], 'variations' => ['outline' => ['@mobile' => ['color' => ['text' => 'om']], 'elements' => ['link' => ['@mobile' => ['color' => ['text' => 'olm']]]], 'blocks' => ['core/paragraph' => ['@tablet' => ['color' => ['text' => 'opt']], 'elements' => ['link' => ['@mobile' => ['color' => ['text' => 'oplm']]]]]]]]],
    ],
]]));

$typed = [];
foreach (['10px', 'expression(1)', 'url(javascript:1)', 'url(https://example.com/x.png)', 'javascript:alert(1)', 'EXPRESSION(1)', 'calc(1px + 2px)', 'var(--x)', 'var:preset|spacing|50', 'a\\b', '/* c */', 'url(x)', 'red}', '{', '1px !important', '<b>', '', 'red; color: blue', 'rgb(1,2,3)', 'linear-gradient(red,blue)'] as $value) {
    $typed[$value] = $reach('remove_insecure_settings', ['layout' => ['contentSize' => $value, 'wideSize' => $value], 'spacing' => ['blockGap' => $value], 'background' => ['gradient' => $value]]);
}
$slugs = [];
foreach (['ok', 'UPPER', 'a_b', 'a.b', 'é', '1', 'a-b', 'a b', 'a/b', '', 'a:b', '<b>', 'a--b'] as $slug) {
    $slugs[$slug] = $reach('remove_insecure_settings', ['color' => ['palette' => ['theme' => [['slug' => $slug, 'color' => 'red', 'name' => 'N']]]]]);
}
$colors = [];
foreach (['red', '#abc', 'rgb(1,2,3)', 'var(--x)', 'url(javascript:1)', 'expression(1)', 'red; background: blue', '<b>', '', 'url(https://example.com/a.png)', 'var:preset|color|x'] as $color) {
    $colors[$color] = $reach('remove_insecure_settings', ['color' => ['palette' => ['theme' => [['slug' => 's', 'color' => $color, 'name' => 'N']]]]]);
}
$leaves = [];
$walk = static function (array $schema, array $path) use (&$walk, &$leaves): void {
    foreach ($schema as $key => $value) {
        is_array($value) ? $walk($value, array_merge($path, [$key])) : $leaves[] = array_merge($path, [$key]);
    }
};
$walk(WP_Theme_JSON::VALID_SETTINGS, []);
$everyLeaf = [];
foreach (['true' => true, 'false' => false, 'a string' => '10px', 'a number' => 3, 'a list' => ['a'], 'a map' => ['k' => 'v']] as $label => $value) {
    $settings = [];
    foreach ($leaves as $leaf) {
        _wp_array_set($settings, $leaf, $value);
    }
    $everyLeaf[$label] = $reach('remove_insecure_settings', $settings);
}
$say('settings kept', [
    'every valid setting, by value' => $everyLeaf,
    'in order' => $reach('remove_insecure_settings', ['blockVisibility' => ['allowEditing' => true], 'viewport' => ['mobile' => '500px'], 'lightbox' => ['allowEditing' => false, 'enabled' => true], 'background' => ['gradient' => 'g'], 'layout' => ['wideSize' => '9px', 'contentSize' => '8px'], 'spacing' => ['blockGap' => '1px', 'spacingSizes' => ['theme' => [['slug' => 's', 'size' => '1px', 'name' => 'S']]]], 'color' => ['palette' => ['custom' => [['slug' => 'c', 'color' => 'red', 'name' => 'C']], 'theme' => [['slug' => 't', 'color' => 'blue', 'name' => 'T']]]]], true),
    'typed strings' => $typed,
    'typed booleans' => $reach('remove_insecure_settings', ['lightbox' => ['enabled' => 'yes', 'allowEditing' => 1], 'blockVisibility' => ['allowEditing' => null]]),
    'slugs' => $slugs,
    'colors' => $colors,
    'a preset with no value' => $reach('remove_insecure_settings', ['color' => ['palette' => ['theme' => [['slug' => 's', 'name' => 'N'], ['slug' => 'v', 'color' => 'red', 'name' => 'V', 'zz' => 1]]]]]),
    'origins' => $reach('remove_insecure_settings', ['color' => ['palette' => ['zz' => [['slug' => 'z', 'color' => 'red', 'name' => 'Z']], 'custom' => [['slug' => 'c', 'color' => 'red', 'name' => 'C']], 'default' => [['slug' => 'd', 'color' => 'red', 'name' => 'D']], 'blocks' => [['slug' => 'b', 'color' => 'red', 'name' => 'B']], 'theme' => []]]]),
    'a flat list' => $reach('remove_insecure_settings', ['color' => ['palette' => [['slug' => 'f', 'color' => 'red', 'name' => 'F']]]]),
    'gradients' => $reach('remove_insecure_settings', ['color' => ['gradients' => ['theme' => [['slug' => 'g', 'gradient' => 'linear-gradient(red,blue)', 'name' => 'G'], ['slug' => 'h', 'gradient' => 'url(javascript:1)', 'name' => 'H'], ['slug' => 'u', 'gradient' => 'url(https://example.com/x.png)', 'name' => 'U'], ['slug' => 'r', 'gradient' => 'rgb(1,2,3)', 'name' => 'R']]]]]),
    'duotone' => $reach('remove_insecure_settings', ['color' => ['duotone' => ['theme' => [['slug' => 'd', 'colors' => ['#000', '#fff'], 'name' => 'D'], ['slug' => 'j', 'colors' => ['url(javascript:1)'], 'name' => 'J'], ['slug' => 'e.x', 'colors' => [], 'name' => 'E']]]]]),
    'aspect ratios' => $reach('remove_insecure_settings', ['dimensions' => ['aspectRatios' => ['theme' => [['slug' => 'sq', 'ratio' => '1', 'name' => 'Sq'], ['slug' => 'b', 'ratio' => 'expression(1)', 'name' => 'B']]]]]),
    'font sizes' => $reach('remove_insecure_settings', ['typography' => ['fontSizes' => ['theme' => [['slug' => 'f', 'size' => '3rem', 'name' => 'F', 'fluid' => ['min' => '1rem']], ['slug' => 'g', 'size' => 16, 'name' => 'G'], ['slug' => 'a', 'size' => 'rgb(1,2,3)', 'name' => 'A'], ['slug' => 'b', 'size' => '', 'name' => 'B'], ['slug' => 'c', 'size' => 'url(https://example.com/x)', 'name' => 'C'], ['slug' => 'e', 'size' => 'expression(1)', 'name' => 'E']]]]]),
    'font families' => $reach('remove_insecure_settings', ['typography' => ['fontFamilies' => ['theme' => [['slug' => 'f', 'fontFamily' => 'url(javascript:1)', 'name' => 'F'], ['slug' => 'g', 'fontFamily' => '"Inter", serif', 'name' => 'G', 'fontFace' => [['src' => 'x', 'zz' => 1]]], ['slug' => 'r', 'fontFamily' => 'rgb(1,2,3)', 'name' => 'R'], ['slug' => 'e', 'fontFamily' => '', 'name' => 'E']]]]]),
    'shadows and spacing' => $reach('remove_insecure_settings', ['shadow' => ['presets' => ['theme' => [['slug' => 'a', 'shadow' => '1px 1px rgb(1,2,3)', 'name' => 'A'], ['slug' => 'b', 'shadow' => '1px 1px red', 'name' => 'B']]]], 'spacing' => ['spacingSizes' => ['theme' => [['slug' => '1', 'size' => '1px', 'name' => 'One'], ['slug' => '2', 'size' => 'url(x)', 'name' => 'Two']]]]]),
    'other settings' => $reach('remove_insecure_settings', ['color' => ['custom' => false, 'link' => true], 'custom' => ['a' => 'red'], 'appearanceTools' => true, 'typography' => ['fluid' => true]]),
    'the viewport, at the root' => $reach('remove_insecure_settings', ['viewport' => ['mobile' => '500px', 'tablet' => 'zz']], true),
    'the viewport, not at the root' => $reach('remove_insecure_settings', ['viewport' => ['mobile' => '500px']]),
    'an empty viewport, at the root' => $reach('remove_insecure_settings', ['viewport' => []], true),
    'blocks left alone' => $reach('remove_insecure_settings', ['blocks' => ['core/paragraph' => ['color' => ['palette' => ['theme' => [['slug' => 'p', 'color' => 'red', 'name' => 'P']]]]]]], true),
    'nothing' => $reach('remove_insecure_settings', []),
]);

$images = [];
foreach ([['url' => 'https://example.com/a b.png'], ['url' => "https://example.com/a'b.png"], ['url' => 'data:image/png;base64,AAAA'], ['url' => 'x.png', 'title' => 'T', 'id' => 4, 'source' => 'file'], ['url' => 'JAVASCRIPT:alert(1)'], ['url' => 'javascript:alert(1)'], ['url' => ''], ['id' => 3], 'url(javascript:1)', "url('https://example.com/a.png'), url('b.png')", 'linear-gradient(red,blue)', 'none', ['ref' => 'styles.x'], 5] as $image) {
    $images[] = [$image, $reach('remove_insecure_styles', ['background' => ['backgroundImage' => $image, 'backgroundSize' => 'cover']])];
}
$say('styles kept', [
    'mixed' => $reach('remove_insecure_styles', ['color' => ['text' => 'red', 'background' => 'url(javascript:alert(1))'], 'spacing' => ['padding' => ['top' => '1px', 'left' => 'expression(1)']], 'typography' => ['fontFamily' => 'var:preset|font-family|f'], 'css' => 'p{color:red}', 'elements' => ['link' => $ok], ':hover' => $ok, '@mobile' => $ok, 'zz' => ['a' => 1]]),
    'in property order' => $reach('remove_insecure_styles', ['color' => ['text' => 'var:custom|a|b', 'background' => 'var(--x)'], 'border' => ['radius' => ['ref' => 'styles.x'], 'top' => ['color' => 'red', 'width' => 'url(javascript:1)']], 'shadow' => 'var:preset|shadow|n', 'filter' => ['duotone' => 'var:preset|duotone|d'], 'dimensions' => ['minHeight' => '1px'], 'background' => ['backgroundImage' => ['url' => 'https://example.com/a.png'], 'backgroundSize' => 'cover'], 'typography' => ['fontSize' => '1rem', 'lineHeight' => 1.5, 'zz' => 'q']]),
    'outline and text shadow' => $reach('remove_insecure_styles', ['outline' => ['color' => 'red', 'offset' => '1px', 'style' => 'solid', 'width' => '1px'], 'typography' => ['textShadow' => '1px 1px red', 'textIndent' => '1em', 'writingMode' => 'vertical-rl']]),
    'a background image last' => $reach('remove_insecure_styles', ['color' => ['text' => 'red'], 'background' => ['backgroundImage' => ['url' => 'u.png']], 'shadow' => 'x']),
    'an uploaded image' => $reach('remove_insecure_styles', ['background' => ['backgroundImage' => ['url' => 'u.png', 'id' => 3]]]),
    'background images' => $images,
    'an aspect ratio' => $reach('remove_insecure_styles', ['dimensions' => ['aspectRatio' => '16/9']]),
    'a font size as written' => $reach('remove_insecure_styles', ['typography' => ['fontSize' => 16]]),
    'sides' => $reach('remove_insecure_styles', ['spacing' => ['padding' => ['top' => '1px', 'left' => 'expression(1)'], 'margin' => '2px'], 'border' => ['radius' => ['topLeft' => '1px']]]),
    'a ref' => $reach('remove_insecure_styles', ['color' => ['text' => ['ref' => 'styles.x']]]),
    'nothing' => $reach('remove_insecure_styles', []),
]);

$queries = WP_Theme_JSON::get_viewport_media_queries();
$say('elements and inner blocks kept', [
    'elements, every media query' => $reach('remove_insecure_element_styles', ['link' => ['color' => ['text' => 'a'], '@mobile' => ['color' => ['text' => 'm']], '@tablet' => $bad, '@zz' => $ok, ':hover' => ['color' => ['text' => 'h'], '@mobile' => ['color' => ['text' => 'hm']]]]], $queries),
    'elements, no media queries' => $reach('remove_insecure_element_styles', ['link' => ['color' => ['text' => 'a'], '@mobile' => ['color' => ['text' => 'm']]]]),
    'elements, in their order' => $reach('remove_insecure_element_styles', ['button' => $ok, 'zz' => $ok, 'link' => $ok, 'heading' => $ok + [':hover' => $ok], 'h2' => $bad, 'h3' => [':hover' => $bad]]),
    'elements, pseudo selectors left empty' => $reach('remove_insecure_element_styles', ['link' => [':hover' => $bad, ':focus' => []]]),
    'elements, mixed' => $reach('remove_insecure_element_styles', ['link' => ['color' => ['text' => 'blue', 'background' => 'url(javascript:1)'], ':hover' => ['color' => ['text' => 'url(javascript:1)', 'background' => 'green']], 'css' => 'a{}']]),
    'no elements' => $reach('remove_insecure_element_styles', []),
    'inner blocks, every media query' => $reach('remove_insecure_inner_block_styles', ['core/paragraph' => ['color' => ['text' => 'p'], '@mobile' => ['color' => ['text' => 'pm']], '@zz' => $ok, 'elements' => ['link' => ['color' => ['text' => 'l'], '@mobile' => ['color' => ['text' => 'lm']]]]]], $queries),
    'inner blocks, no media queries' => $reach('remove_insecure_inner_block_styles', ['core/paragraph' => ['color' => ['text' => 'p'], '@mobile' => ['color' => ['text' => 'pm']]]]),
    'inner blocks, as given' => $reach('remove_insecure_inner_block_styles', ['zz/none' => $ok, 'core/paragraph' => $bad, 'core/group' => ['elements' => ['link' => $bad]], 'core/image' => $ok + ['elements' => ['link' => $bad], 'css' => 'x', ':hover' => $ok]], $queries),
    'inner blocks, a media query left empty' => $reach('remove_insecure_inner_block_styles', ['core/paragraph' => ['@mobile' => $bad]], $queries),
]);

$document = ['version' => 3, 'title' => 'T', 'zz' => 1,
    'settings' => ['color' => ['custom' => false, 'palette' => [['slug' => 'ok', 'color' => '#abc', 'name' => 'Ok']], 'gradients' => ['theme' => [['slug' => 'g', 'gradient' => 'linear-gradient(red,blue)', 'name' => 'G']]]], 'custom' => ['a' => 'red'], 'layout' => ['contentSize' => '600px'], 'blocks' => ['core/paragraph' => ['color' => ['palette' => ['theme' => [['slug' => 'p', 'color' => '#000', 'name' => 'P']]]], 'layout' => ['contentSize' => '1px'], 'lightbox' => ['enabled' => true]], 'zz/none' => ['layout' => ['contentSize' => '1px']]]],
    'styles' => ['color' => ['text' => 'red', 'background' => 'url(javascript:alert(1))'], 'spacing' => ['padding' => ['top' => '1px', 'left' => 'expression(1)']], 'typography' => ['fontFamily' => 'var:preset|font-family|f'], 'css' => 'p{color:red}', 'elements' => ['link' => ['color' => ['text' => 'blue'], ':hover' => ['color' => ['text' => 'url(javascript:1)', 'background' => 'green']]]],
        'blocks' => ['core/group' => ['color' => ['text' => 'g', 'background' => 'expression(1)'], 'css' => '& a{}', 'elements' => ['link' => ['color' => ['text' => 'gl']]], 'zz' => 1], 'core/button' => ['variations' => ['outline' => ['color' => ['text' => 'o', 'background' => 'url(javascript:1)'], 'css' => 'x']]], 'zz/none' => ['color' => ['text' => 'n']]]]];
$nodes = ['version' => 3, 'styles' => ['elements' => ['link' => ['color' => ['text' => 'a']], 'button' => [':hover' => ['color' => ['text' => 'b']], ':zz' => ['color' => ['text' => 'z']], ':focus' => $bad], 'zz' => ['color' => ['text' => 'q']]],
    'blocks' => ['core/button' => [':hover' => ['color' => ['text' => 'bh']], 'variations' => ['outline' => ['elements' => ['link' => ['color' => ['text' => 'vl']]], 'blocks' => ['core/paragraph' => ['color' => ['text' => 'vp', 'background' => 'expression(1)'], 'elements' => ['link' => ['color' => ['text' => 'vpl']]]]]]]], 'core/navigation-link' => ['-current' => ['color' => ['text' => 'cur']]], 'core/group' => ['elements' => ['link' => [':hover' => ['color' => ['text' => 'gh']]]]]]]];
$scrambled = ['version' => 3, 'styles' => [
    'elements' => ['link' => ['@tablet' => ['color' => ['text' => 'lt']], '@mobile' => ['color' => ['text' => 'lm']], ':focus' => ['color' => ['text' => 'lf']], ':hover' => ['color' => ['text' => 'lh']], 'css' => 'x{}', 'color' => ['text' => 'l']], 'button' => $bad, 'heading' => [':hover' => $bad], 'h1' => ['@mobile' => $bad]],
    'css' => 'root{}', 'color' => ['text' => 'r'],
    'blocks' => [
        'core/button' => ['elements' => ['link' => [':hover' => ['color' => ['text' => 'belh']], 'color' => ['text' => 'bel']]], ':hover' => ['color' => ['text' => 'bh']], ':focus' => ['color' => ['text' => 'bf']], 'variations' => ['outline' => ['@mobile' => ['color' => ['text' => 'om']], 'blocks' => ['core/paragraph' => ['@mobile' => ['color' => ['text' => 'opm']], 'elements' => ['link' => ['color' => ['text' => 'opl']]], 'css' => 'op{}', 'color' => ['text' => 'op']]], 'elements' => ['link' => ['color' => ['text' => 'ol']]], 'css' => 'o{}', 'color' => ['text' => 'o']], 'fill' => $bad], '@tablet' => ['color' => ['text' => 'bt']], '@mobile' => ['color' => ['text' => 'bm']], 'css' => 'b{}', 'color' => ['text' => 'b']],
        'core/navigation-link' => ['-current' => ['color' => ['text' => 'cur']], ':hover' => ['color' => ['text' => 'nh']]],
        'core/group' => $bad + ['elements' => ['link' => $bad]],
        'core/image' => ['elements' => ['link' => ['@mobile' => $bad]], ':hover' => $bad],
    ]]];
$documents = [
    'a theme.json, the theme' => [$document, 'theme'],
    'a theme.json, custom' => [$document, 'custom'],
    'a theme.json, default' => [$document, 'default'],
    'a theme.json, an origin that is not one' => [$document, 'zz'],
    'empty' => [[]],
    'a version alone' => [['version' => 3]],
    'version 2' => [['version' => 2, 'settings' => ['typography' => ['fontSizes' => ['theme' => [['slug' => 's', 'size' => '1px', 'name' => 'S']]]]]]],
    'no version' => [['styles' => ['color' => ['text' => 'red']]]],
    'nodes' => [$nodes],
    'scrambled' => [$scrambled],
    'other settings and top-level keys' => [['version' => 3, 'settings' => ['custom' => ['a' => 'red'], 'color' => ['custom' => true]], 'styles' => ['color' => ['text' => 'red']], 'customTemplates' => [['name' => 'x']], 'templateParts' => [['name' => 'y', 'area' => 'header']], 'patterns' => ['p'], 'isGlobalStylesUserThemeJSON' => true, 'slug' => 's', 'description' => 'D', 'blockTypes' => ['core/group'], '$schema' => 'x']],
    'presets by origin' => [['version' => 3, 'settings' => ['color' => ['palette' => ['custom' => [['slug' => 'c', 'color' => '#c', 'name' => 'C']], 'theme' => [['slug' => 't', 'color' => '#t', 'name' => 'T']]]]]]],
    'var: values' => [['version' => 3, 'title' => 'var:a|b', 'customTemplates' => [['name' => 'var:c', 'title' => 'var:d']], 'settings' => ['layout' => ['contentSize' => 'var:preset|spacing|50'], 'color' => ['palette' => ['theme' => [['slug' => 'p', 'color' => 'var:preset|color|x', 'name' => 'var:e']]]], 'background' => ['gradient' => 'var:f']], 'styles' => ['color' => ['text' => 'var:custom|a|b', 'background' => 'var(--wp--preset--color--x)'], 'spacing' => ['padding' => ['top' => 'var:preset|spacing|50']], 'border' => ['radius' => ['ref' => 'styles.x']], 'elements' => ['link' => ['color' => ['text' => 'var:preset|color|y']]], 'blocks' => ['core/button' => ['variations' => ['outline' => ['color' => ['text' => 'var:preset|color|z']]]]]]]],
    'refs' => [['version' => 3, 'styles' => ['color' => ['text' => ['ref' => 'styles.blocks.core/group.color.text']], 'blocks' => ['core/group' => ['color' => ['text' => 'red']]]]]],
    'background images and sizes' => [['version' => 3, 'styles' => ['background' => ['backgroundImage' => ['url' => 'u.png', 'id' => 3]], 'typography' => ['fontSize' => '3rem'], 'dimensions' => ['aspectRatio' => '1']]]],
    'responsive styles' => [['version' => 3, 'styles' => ['color' => ['text' => 'r'], '@mobile' => ['color' => ['text' => 'rm']], 'elements' => ['link' => ['@tablet' => ['color' => ['text' => 'lt']]]], 'blocks' => ['core/group' => ['@mobile' => ['color' => ['text' => 'gm'], 'zz' => 1], 'elements' => ['link' => ['@mobile' => ['color' => ['text' => 'glm']]]]], 'core/button' => ['variations' => ['outline' => ['@mobile' => ['color' => ['text' => 'om']], 'blocks' => ['core/paragraph' => ['@mobile' => ['color' => ['text' => 'opm']]]]]]]]]]],
    'responsive styles left empty' => [['version' => 3, 'styles' => ['blocks' => ['core/group' => $ok + ['@mobile' => $bad], 'core/paragraph' => ['@mobile' => $bad], 'core/columns' => ['@mobile' => $ok + ['elements' => ['link' => $ok], 'css' => 'a{}']], 'core/button' => ['variations' => ['outline' => ['@mobile' => $bad]]]]]]],
    'a viewport' => [['version' => 3, 'settings' => ['viewport' => ['mobile' => '500px', 'tablet' => 'zz']], 'styles' => ['@mobile' => ['color' => ['text' => 'rm']], 'blocks' => ['core/group' => ['@mobile' => ['color' => ['text' => 'gm']], '@tablet' => ['color' => ['text' => 'gt']]]]]]],
    'shared variations' => [['version' => 3, 'styles' => ['variations' => ['outline' => ['color' => ['text' => 'shared']]], 'blocks' => ['core/button' => ['variations' => ['outline' => ['color' => ['background' => 'own']]]]]]]],
    'nothing safe at the root' => [['version' => 3, 'styles' => $bad]],
    'nothing safe at the root, a safe element' => [['version' => 3, 'styles' => $bad + ['elements' => ['link' => $ok]]]],
    'nothing safe in an element' => [['version' => 3, 'styles' => ['elements' => ['link' => $bad, 'heading' => $ok]]]],
    'nothing safe in a block' => [['version' => 3, 'styles' => $ok + ['blocks' => ['core/group' => $bad, 'core/paragraph' => $ok]]]],
    'nothing safe in a variation' => [['version' => 3, 'styles' => ['blocks' => ['core/button' => $ok + ['variations' => ['outline' => $bad, 'fill' => $ok]]]]]],
    'a variation\'s children left empty' => [['version' => 3, 'styles' => ['blocks' => ['core/button' => ['variations' => ['outline' => $ok + ['blocks' => ['core/paragraph' => $bad], 'elements' => ['link' => $bad]], 'fill' => ['blocks' => ['core/paragraph' => $bad]]]]]]]],
    'custom css alone' => [['version' => 3, 'styles' => ['css' => 'a{}', 'blocks' => ['core/group' => ['css' => 'g{}'], 'core/paragraph' => $ok]]]],
    'pseudo selectors with nothing safe' => [['version' => 3, 'styles' => ['elements' => ['link' => $ok + [':hover' => $bad], 'button' => [':hover' => $bad, ':focus' => $ok]], 'blocks' => ['core/button' => [':hover' => $bad], 'core/navigation-link' => $ok + [':hover' => $bad]]]]],
];
$visitor = [];
foreach ($documents as $label => $args) {
    $visitor[$label] = $safe(...$args);
}
$say('a theme.json made safe for a visitor', $visitor);

// A user who may edit CSS keeps a node with custom CSS as written.
wp_set_current_user(1);
$editor = [];
foreach (['a theme.json, the theme', 'scrambled', 'custom css alone', 'nodes', 'nothing safe in a block'] as $label) {
    $editor[$label] = $safe(...$documents[$label]);
}
$editor['scrambled, no custom css'] = $safe(['version' => 3, 'styles' => ['elements' => ['link' => [':hover' => $ok, 'color' => ['text' => 'l']]], 'color' => ['text' => 'r']]]);
$editor['custom css with values to convert'] = $safe(['version' => 3, 'styles' => ['css' => 'a{color:var:preset|color|x}', 'color' => ['text' => 'var:preset|color|y', 'background' => 'url(javascript:1)'], 'blocks' => ['core/group' => ['css' => 'g', 'color' => ['text' => 'var:custom|z']]]]]);
$editor['custom css in a pseudo selector'] = $safe(['version' => 3, 'styles' => ['blocks' => ['core/button' => [':hover' => ['css' => 'h{}', 'color' => ['text' => 'url(javascript:1)']]]]]]);
$editor['custom css in a block\'s element'] = $safe(['version' => 3, 'styles' => ['blocks' => ['core/group' => ['elements' => ['link' => ['css' => 'h{}', 'color' => ['text' => 'url(javascript:1)']]]]]]]);
$editor['custom css at the root, blocks with nothing safe'] = $safe(['version' => 3, 'styles' => ['css' => 'a{}', 'blocks' => ['core/group' => $bad, 'core/paragraph' => $ok + ['zz' => 1]]]]);
$editor['styles alone'] = $reach('remove_insecure_styles', ['css' => 'a{}', 'color' => ['text' => 'r']]);
$editor['elements alone'] = $reach('remove_insecure_element_styles', ['link' => ['css' => 'a{}', 'color' => ['text' => 'url(javascript:1)']]]);
$editor['inner blocks alone'] = $reach('remove_insecure_inner_block_styles', ['core/paragraph' => ['css' => 'a{}', 'color' => ['text' => 'url(javascript:1)']]]);
$noCss = static fn ($caps, $cap) => $cap === 'edit_css' ? ['do_not_allow'] : $caps;
add_filter('map_meta_cap', $noCss, 10, 2);
$editor['without edit_css'] = $safe(...$documents['scrambled']);
remove_filter('map_meta_cap', $noCss, 10);
$say('a theme.json made safe for a user who may edit CSS', $editor);
wp_set_current_user(0);

$posts = [];
foreach ([
    'a user\'s global styles' => wp_slash(wp_json_encode(['version' => 3, 'isGlobalStylesUserThemeJSON' => true, 'settings' => ['color' => ['custom' => true]], 'styles' => ['color' => ['text' => 'red', 'background' => 'url(javascript:1)'], 'css' => 'p{}']])),
    'not a user\'s' => wp_slash(wp_json_encode(['version' => 3, 'styles' => ['color' => ['background' => 'url(javascript:1)']]])),
    'flagged false' => wp_slash(wp_json_encode(['version' => 3, 'isGlobalStylesUserThemeJSON' => false, 'styles' => ['color' => ['background' => 'url(javascript:1)']]])),
    'flagged 1, version 2' => wp_slash(wp_json_encode(['isGlobalStylesUserThemeJSON' => 1, 'version' => 2, 'styles' => ['color' => ['text' => 'var:preset|color|x']]])),
    'not JSON' => 'hello',
    'a JSON number' => '5',
    'a JSON list' => '[1,2]',
    'the flag alone' => wp_slash(wp_json_encode(['isGlobalStylesUserThemeJSON' => true])),
    'text to escape' => wp_slash(wp_json_encode(['isGlobalStylesUserThemeJSON' => true, 'version' => 3, 'title' => 'Ünï "q" / \\', 'styles' => ['typography' => ['fontFamily' => '"Ünï", serif']]])),
] as $label => $data) {
    $posts[$label] = $try(static fn () => wp_filter_global_styles_post($data));
}
wp_set_current_user(1);
$posts['saved by a user who may edit CSS'] = $try(static fn () => wp_filter_global_styles_post(wp_slash(wp_json_encode(['isGlobalStylesUserThemeJSON' => true, 'version' => 3, 'styles' => ['css' => 'p{}', 'color' => ['background' => 'url(javascript:1)']]]))));
wp_set_current_user(0);
$say('a user\'s global styles filtered on save', $posts);

$hooks = static fn () => [has_filter('content_save_pre', 'wp_filter_global_styles_post'), has_filter('content_filtered_save_pre', 'wp_filter_global_styles_post'), has_filter('excerpt_save_pre', 'wp_filter_global_styles_post'), has_filter('content_save_pre', 'wp_filter_post_kses')];
kses_remove_filters();
$removed = $hooks();
kses_init_filters();
$initialized = $hooks();
kses_remove_filters();
$say('the kses filters', ['removed' => $removed, 'initialized' => $initialized, 'removed again' => $hooks()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
