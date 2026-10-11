<?php
/**
 * WP_Theme_JSON's declarations and nodes (probe theme-json-nodes): style
 * properties computed into declarations (the property order, shorthands
 * and their sides, presets left as written, refs, the root padding custom
 * properties, background images and their defaults, fluid font sizes,
 * aspect ratios, a given property list); the settings nodes; the style
 * nodes (the root, its elements and their pseudo selectors, each block with
 * its selectors, pseudo selectors, states and elements, its variations when
 * asked, paths only when asked); and a theme.json's block nodes.
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

$pad = ['spacing' => ['padding' => ['top' => '1px', 'right' => '2px'], 'margin' => ['top' => '3px']]];
$image = static fn (array $background) => ['background' => $background];
$computed = [
    'basic' => [['color' => ['text' => 'red', 'background' => 'blue', 'gradient' => 'linear-gradient(red,blue)']], ['typography' => ['fluid' => false]]],
    'in property order' => [['typography' => ['fontSize' => '1rem'], 'color' => ['text' => 'red'], 'border' => ['radius' => '2px', 'color' => 'red']], ['typography' => ['fluid' => false]]],
    'sides' => [['spacing' => ['padding' => ['top' => '1px', 'left' => '2px'], 'margin' => '3px'], 'border' => ['radius' => ['topLeft' => '1px', 'bottomRight' => '2px']]]],
    'a padding string' => [['spacing' => ['padding' => '5px']]],
    'presets as written' => [['color' => ['text' => 'var:preset|color|x'], 'spacing' => ['padding' => ['top' => 'var:preset|spacing|50']], 'shadow' => 'var:preset|shadow|natural', 'filter' => ['duotone' => 'var:preset|duotone|d'], 'typography' => ['fontFamily' => 'var:custom|a']]],
    'a ref with no tree' => [['color' => ['text' => ['ref' => 'styles.color.background']]]],
    'a ref' => [['color' => ['text' => ['ref' => 'styles.color.background']]], [], null, ['styles' => ['color' => ['background' => 'pink']]]],
    'a ref to an array' => [['border' => ['radius' => ['ref' => 'styles.r']]], [], null, ['styles' => ['r' => ['topLeft' => '9px']]]],
    'a gradient ref' => [['color' => ['gradient' => ['ref' => 'styles.g']]], [], null, ['styles' => ['g' => 'linear-gradient(red,blue)']]],
    'root padding' => [$pad, [], null, null, 'body', true],
    'root padding, a string' => [['spacing' => ['padding' => '4px']], [], null, null, 'body', true],
    'root padding, another selector' => [$pad, [], null, null, ':root', true],
    'root padding off' => [$pad, [], null, null, 'body', false],
    'root padding, no selector' => [$pad, [], null, null, null, true],
    'a background image' => [$image(['backgroundImage' => ['url' => 'https://example.com/a.png'], 'backgroundSize' => 'cover', 'backgroundPosition' => '50% 50%'])],
    'a background image as a string' => [$image(['backgroundImage' => "url('x.png')"])],
    'a background image alone' => [$image(['backgroundImage' => ['url' => 'https://example.com/a.png']])],
    'an uploaded image' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => 3]])],
    'an uploaded image, contained' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => 3], 'backgroundSize' => 'contain'])],
    'an uploaded image, contained and placed' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => 3], 'backgroundSize' => 'contain', 'backgroundPosition' => 'top'])],
    'an image contained' => [$image(['backgroundImage' => ['url' => 'u.png'], 'backgroundSize' => 'contain'])],
    'an uploaded image, auto' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => 3], 'backgroundSize' => 'auto'])],
    'an image with id zero' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => 0]])],
    'an image with a string id' => [$image(['backgroundImage' => ['url' => 'u.png', 'id' => '7']])],
    'an image with no url' => [$image(['backgroundImage' => ['id' => 4]])],
    'an image with an empty url' => [$image(['backgroundImage' => ['url' => '']])],
    'an image url with a quote' => [$image(['backgroundImage' => ['url' => "a'b c.png"]])],
    'an image ref' => [$image(['backgroundImage' => ['ref' => 'styles.x']]), [], null, ['styles' => ['x' => ['url' => 'https://example.com/b.png']]]],
    'an image ref to a string' => [$image(['backgroundImage' => ['ref' => 'styles.x']]), [], null, ['styles' => ['x' => "url('y.png')"]]],
    'a background size alone' => [$image(['backgroundSize' => 'contain'])],
    'repeat and attachment' => [$image(['backgroundRepeat' => 'no-repeat', 'backgroundAttachment' => 'fixed'])],
    'fluid' => [['typography' => ['fontSize' => '3rem']], ['typography' => ['fluid' => true]]],
    'fluid off' => [['typography' => ['fontSize' => '3rem']], ['typography' => ['fluid' => false]]],
    'fluid with a minimum' => [['typography' => ['fontSize' => '3rem']], ['typography' => ['fluid' => ['minFontSize' => '40px']]]],
    'fluid, empty typography' => [['typography' => ['fontSize' => '3rem']], ['typography' => []]],
    'fluid, a preset' => [['typography' => ['fontSize' => 'var:preset|font-size|x']], ['typography' => ['fluid' => true]]],
    'fluid, small' => [['typography' => ['fontSize' => '12px']], ['typography' => ['fluid' => true]]],
    'fluid, em' => [['typography' => ['fontSize' => '2em']], ['typography' => ['fluid' => true]]],
    'fluid, a keyword' => [['typography' => ['fontSize' => 'large']], ['typography' => ['fluid' => true]]],
    'fluid, a number' => [['typography' => ['fontSize' => 16, 'lineHeight' => 1.5]], ['typography' => ['fluid' => true]]],
    'a property list' => [['color' => ['text' => 'red', 'background' => 'blue']], [], ['color' => ['color', 'text']]],
    'a property list of other names' => [['color' => ['text' => 'red'], 'spacing' => ['padding' => ['top' => '1px']]], [], ['padding-top' => ['spacing', 'padding', 'top'], 'zz' => ['color', 'text']]],
    'an empty property list' => [['color' => ['text' => 'red']], [], []],
    'empty and zero' => [['color' => ['text' => ''], 'typography' => ['lineHeight' => 0, 'fontWeight' => '0', 'letterSpacing' => null]]],
    'pseudo selectors, elements and css left alone' => [['color' => ['text' => 'red'], ':hover' => ['color' => ['text' => 'blue']], 'elements' => ['link' => ['color' => ['text' => 'g']]], 'css' => 'p{}']],
    'an aspect ratio' => [['dimensions' => ['aspectRatio' => '16/9', 'minHeight' => '10px']]],
    'an aspect ratio alone' => [['dimensions' => ['aspectRatio' => '16/9']]],
    'a minimum height alone' => [['dimensions' => ['minHeight' => '10px']]],
    'dimensions' => [['dimensions' => ['width' => '10px', 'height' => '20px', 'minWidth' => '5px']]],
    'columns, writing mode, alignment' => [['typography' => ['textColumns' => 3, 'writingMode' => 'vertical-rl', 'textAlign' => 'center']]],
    'text' => [['typography' => ['textDecoration' => 'underline', 'textTransform' => 'uppercase', 'fontStyle' => 'italic', 'textIndent' => '1em', 'textShadow' => '1px 1px red']]],
    'border sides' => [['border' => ['top' => ['color' => 'red', 'width' => '1px'], 'width' => '2px', 'style' => 'dashed']]],
    'outline' => [['outline' => ['color' => 'red', 'offset' => '1px', 'style' => 'solid', 'width' => '2px']]],
    'a shadow' => [['shadow' => '1px 1px red']],
    'arrays where values go' => [['color' => ['text' => ['a' => 1]], 'typography' => ['fontSize' => ['x']]]],
    'nothing' => [[]],
];
$declarations = [];
foreach ($computed as $label => $args) {
    $declarations[$label] = $reach('compute_style_properties', ...$args);
}
$say('style properties computed', $declarations);

$say('settings nodes', [
    'root and blocks' => $reach('get_setting_nodes', ['version' => 3, 'settings' => ['color' => ['custom' => false], 'blocks' => ['core/paragraph' => ['color' => ['custom' => true]], 'core/button' => [], 'zz/none' => ['x' => 1]]]]),
    'with selectors' => $reach('get_setting_nodes', ['settings' => ['blocks' => ['core/paragraph' => [], 'core/image' => [], 'zz/none' => []]]], ['core/paragraph' => ['selector' => '.p', 'selectors' => ['root' => '.p', 'color' => '.c'], 'duotone' => '.d', 'elements' => ['link' => 'a']], 'core/image' => ['selector' => '.wp-block-image']]),
    'a selector alone' => $reach('get_setting_nodes', ['settings' => ['blocks' => ['core/paragraph' => []]]], ['core/paragraph' => ['selector' => '.p', 'zz' => 1]]),
    'nothing' => $reach('get_setting_nodes', []),
    'empty settings' => $reach('get_setting_nodes', ['settings' => []]),
]);

$meta = $reach('get_blocks_metadata');
$some = is_array($meta) ? array_intersect_key($meta, array_flip(['core/group', 'core/button', 'core/paragraph', 'core/navigation-link', 'core/image'])) : [];
$styles = ['version' => 3, 'styles' => [
    'color' => ['text' => 'red'],
    'elements' => ['button' => [':focus' => ['color' => ['text' => 'f']], ':hover' => ['color' => ['text' => 'b']], ':focus-visible' => [], ':zz' => []], 'heading' => ['color' => ['text' => 'h']], 'caption' => [], 'zz' => ['color' => ['text' => 'z']], 'link' => [':visited' => [], ':hover' => ['color' => ['text' => 'lh']]], 'h1' => ['color' => ['text' => 'h1']]],
    'blocks' => [
        'core/image' => ['filter' => ['duotone' => 'x'], 'elements' => ['caption' => []]],
        'core/group' => ['color' => ['text' => 'g'], 'elements' => ['zz' => [], 'button' => [':hover' => []], 'link' => ['color' => ['text' => 'gl']]]],
        'core/button' => [':focus' => [], ':hover' => ['color' => ['text' => 'bh']], 'elements' => ['link' => [':hover' => []], 'zz' => []], 'variations' => ['outline' => ['color' => ['text' => 'o'], 'elements' => ['link' => ['color' => ['text' => 'ol']]], 'blocks' => ['core/paragraph' => ['color' => ['text' => 'p']]]]]],
        'core/navigation-link' => ['-current' => ['color' => ['text' => 'c']], '-zz' => []],
        'zz/none' => ['color' => ['text' => 'n'], 'elements' => ['link' => []]],
    ],
]];
$withVariations = ['version' => 3, 'styles' => ['blocks' => [
    'core/button' => ['variations' => ['outline' => ['color' => ['text' => 'o']], 'fill' => []], ':hover' => []],
    'core/image' => ['variations' => ['rounded' => ['border' => ['radius' => '9px']]]],
    'core/paragraph' => ['color' => ['text' => 'p']],
]]];
$say('style nodes', [
    'some blocks given' => $reach('get_style_nodes', $styles, $some),
    'no blocks given' => $reach('get_style_nodes', $styles),
    'paths only' => $reach('get_style_nodes', $styles, $some, ['include_node_paths_only' => true]),
    'variations' => $reach('get_style_nodes', $withVariations, $some, ['include_block_style_variations' => true]),
    'variations, paths only' => $reach('get_style_nodes', $withVariations, $some, ['include_node_paths_only' => true, 'include_block_style_variations' => true]),
    'variations not asked for' => $reach('get_style_nodes', $withVariations, $some),
    'nothing' => $reach('get_style_nodes', []),
    'empty styles' => $reach('get_style_nodes', ['styles' => []]),
]);

$theme = $try(static fn () => new WP_Theme_JSON(['version' => 3, 'styles' => ['color' => ['text' => 'r'], 'elements' => ['link' => ['color' => ['text' => 'a']]], 'blocks' => ['core/paragraph' => ['color' => ['text' => 'p'], 'elements' => ['link' => ['color' => ['text' => 'l']]]], 'core/button' => [':hover' => ['color' => ['text' => 'h']], 'variations' => ['outline' => ['color' => ['text' => 'o']]]]]]]));
$say('a theme.json\'s block nodes', [
    $theme instanceof WP_Theme_JSON ? $try(static fn () => $theme->get_styles_block_nodes()) : $theme,
    $try(static fn () => (new WP_Theme_JSON(['version' => 3]))->get_styles_block_nodes()),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
