<?php
/**
 * The style engine as the reference answers it (probe style-engine):
 * wp_style_engine_get_styles over every style group a block can carry
 * (colour, spacing, typography, border, dimensions, shadow, background),
 * plain values and presets, with presets turned into class names or kept
 * as custom properties, with a selector, prettified, and with values it
 * refuses; stylesheets from rules (merged, grouped, prettified); and the
 * named stores a context keeps. Same protocol as api-probe.php; nothing is
 * written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$styles = static fn (array $style, array $options = []) => wp_style_engine_get_styles($style, $options);

$say('colour', [
    'text' => $styles(['color' => ['text' => '#fff']]),
    'text preset' => $styles(['color' => ['text' => 'var:preset|color|base']]),
    'text preset as class' => $styles(['color' => ['text' => 'var:preset|color|base']], ['convert_vars_to_classnames' => true]),
    'background' => $styles(['color' => ['background' => '#000']]),
    'background preset' => $styles(['color' => ['background' => 'var:preset|color|contrast']]),
    'background preset as class' => $styles(['color' => ['background' => 'var:preset|color|contrast']], ['convert_vars_to_classnames' => true]),
    'gradient' => $styles(['color' => ['gradient' => 'linear-gradient(135deg,#000 0%,#fff 100%)']]),
    'gradient preset' => $styles(['color' => ['gradient' => 'var:preset|gradient|vivid-cyan-blue']]),
    'gradient preset as class' => $styles(['color' => ['gradient' => 'var:preset|gradient|vivid-cyan-blue']], ['convert_vars_to_classnames' => true]),
    'all three' => $styles(['color' => ['text' => '#111', 'background' => '#222', 'gradient' => 'var:preset|gradient|a']]),
    'all three as classes' => $styles(['color' => ['text' => 'var:preset|color|a', 'background' => 'var:preset|color|b', 'gradient' => 'var:preset|gradient|c']], ['convert_vars_to_classnames' => true]),
]);

$say('spacing', [
    'padding' => $styles(['spacing' => ['padding' => '10px']]),
    'padding sides' => $styles(['spacing' => ['padding' => ['top' => '1px', 'right' => '2px', 'bottom' => '3px', 'left' => '4px']]]),
    'padding preset side' => $styles(['spacing' => ['padding' => ['top' => 'var:preset|spacing|40', 'left' => '0']]]),
    'padding preset as class' => $styles(['spacing' => ['padding' => 'var:preset|spacing|30']], ['convert_vars_to_classnames' => true]),
    'margin' => $styles(['spacing' => ['margin' => ['top' => '5px', 'bottom' => 'auto']]]),
    'margin plain' => $styles(['spacing' => ['margin' => '0 auto']]),
    'block gap' => $styles(['spacing' => ['blockGap' => '2rem']]),
    'unknown side' => $styles(['spacing' => ['padding' => ['middle' => '9px', 'top' => '1px']]]),
]);

$say('typography', [
    'font size' => $styles(['typography' => ['fontSize' => '12px']]),
    'font size preset' => $styles(['typography' => ['fontSize' => 'var:preset|font-size|large']]),
    'font size preset as class' => $styles(['typography' => ['fontSize' => 'var:preset|font-size|large']], ['convert_vars_to_classnames' => true]),
    'font family preset' => $styles(['typography' => ['fontFamily' => 'var:preset|font-family|body']]),
    'font family preset as class' => $styles(['typography' => ['fontFamily' => 'var:preset|font-family|body']], ['convert_vars_to_classnames' => true]),
    'the rest' => $styles(['typography' => ['fontStyle' => 'italic', 'fontWeight' => '700', 'lineHeight' => '1.5', 'letterSpacing' => '1px', 'textDecoration' => 'underline', 'textTransform' => 'uppercase', 'writingMode' => 'vertical-rl', 'textColumns' => '2', 'textAlign' => 'center']]),
    'numbers' => $styles(['typography' => ['fontWeight' => 700, 'lineHeight' => 1.2]]),
]);

$say('border', [
    'all sides' => $styles(['border' => ['color' => '#f00', 'radius' => '4px', 'style' => 'solid', 'width' => '1px']]),
    'colour preset' => $styles(['border' => ['color' => 'var:preset|color|accent', 'width' => '2px']]),
    'colour preset as class' => $styles(['border' => ['color' => 'var:preset|color|accent']], ['convert_vars_to_classnames' => true]),
    'radius corners' => $styles(['border' => ['radius' => ['topLeft' => '1px', 'topRight' => '2px', 'bottomLeft' => '3px', 'bottomRight' => '4px']]]),
    'one side' => $styles(['border' => ['top' => ['color' => '#0f0', 'style' => 'dashed', 'width' => '3px'], 'left' => ['width' => '1px']]]),
    'side preset colour' => $styles(['border' => ['bottom' => ['color' => 'var:preset|color|base']]]),
]);

$say('dimensions shadow background', [
    'min height' => $styles(['dimensions' => ['minHeight' => '50vh']]),
    'aspect ratio' => $styles(['dimensions' => ['aspectRatio' => '16/9']]),
    'height width' => $styles(['dimensions' => ['height' => '10px', 'width' => '20px']]),
    'shadow preset' => $styles(['shadow' => 'var:preset|shadow|natural']),
    'shadow raw' => $styles(['shadow' => '0 0 1px #000']),
    'background image' => $styles(['background' => ['backgroundImage' => ['url' => 'https://example.com/a b.jpg', 'id' => 5]]]),
    'background image string' => $styles(['background' => ['backgroundImage' => "url('https://example.com/c.jpg')"]]),
    'background rest' => $styles(['background' => ['backgroundSize' => 'cover', 'backgroundPosition' => '50% 50%', 'backgroundRepeat' => 'no-repeat', 'backgroundAttachment' => 'fixed']]),
]);

$say('options', [
    'selector' => $styles(['color' => ['text' => '#fff'], 'spacing' => ['padding' => '1px']], ['selector' => '.zz-a']),
    'selector pretty' => $styles(['color' => ['text' => '#fff'], 'spacing' => ['padding' => '1px']], ['selector' => '.zz-a', 'prettify' => true]),
    'pretty without selector' => $styles(['color' => ['text' => '#fff'], 'spacing' => ['padding' => '1px']], ['prettify' => true]),
    'combined' => $styles(['border' => ['width' => '1px'], 'color' => ['text' => '#1'], 'typography' => ['fontSize' => '2px'], 'spacing' => ['margin' => '3px'], 'dimensions' => ['minHeight' => '4px'], 'shadow' => '5px', 'background' => ['backgroundSize' => 'contain']]),
]);

$say('orders', [
    'spacing' => $styles(['spacing' => ['margin' => '1px', 'padding' => '2px']]),
    'typography' => $styles(['typography' => ['writingMode' => 'a', 'letterSpacing' => 'b', 'textTransform' => 'c', 'textDecoration' => 'd', 'textColumns' => 'e', 'lineHeight' => 'f', 'fontWeight' => 'g', 'fontStyle' => 'h', 'fontFamily' => 'i', 'fontSize' => 'j']]),
    'dimensions' => $styles(['dimensions' => ['width' => '1px', 'minHeight' => '2px', 'height' => '3px', 'aspectRatio' => '4/3']]),
    'border' => $styles(['border' => ['left' => ['width' => '1px'], 'width' => '2px', 'top' => ['width' => '3px'], 'style' => 'solid', 'radius' => '4px', 'color' => '#000']]),
    'border side parts' => $styles(['border' => ['top' => ['width' => '1px', 'style' => 'dotted', 'color' => '#111', 'radius' => '9px']]]),
    'background' => $styles(['background' => ['backgroundAttachment' => 'a', 'backgroundSize' => 'b', 'backgroundRepeat' => 'c', 'backgroundPosition' => 'd', 'backgroundImage' => ['url' => 'e.jpg']]]),
    'colour' => $styles(['color' => ['gradient' => 'a', 'background' => 'b', 'text' => 'c']]),
    'groups' => $styles(['typography' => ['fontSize' => '1px'], 'spacing' => ['padding' => '2px'], 'dimensions' => ['height' => '3px'], 'shadow' => '4px', 'border' => ['width' => '5px'], 'color' => ['text' => '#6'], 'background' => ['backgroundSize' => '7px']]),
    'presets everywhere' => $styles(['dimensions' => ['minHeight' => 'var:preset|spacing|50'], 'typography' => ['letterSpacing' => 'var:preset|spacing|10', 'lineHeight' => 'var:preset|custom|x'], 'spacing' => ['margin' => ['left' => 'var:preset|spacing|20']], 'border' => ['radius' => 'var:preset|spacing|5', 'width' => 'var:preset|border|thin']]),
]);
$say('refused', [
    'braces' => $styles(['color' => ['text' => '#fff}body{color:red']]),
    'script' => $styles(['color' => ['background' => 'javascript:alert(1)']]),
    'list where text belongs' => $styles(['color' => ['text' => ['#fff']]]),
    'unknown group' => $styles(['zz' => ['text' => '#fff']]),
    'empty' => $styles([]),
    'not an array' => $styles(['color' => '#fff']),
    'empty value' => $styles(['color' => ['text' => '']]),
    'bad preset' => $styles(['color' => ['text' => 'var:preset|color|a b']]),
    'preset without slug' => $styles(['color' => ['text' => 'var:preset|color|']]),
    'other var' => $styles(['color' => ['text' => 'var:custom|zz']]),
]);

$rules = [
    ['selector' => '.zz-a', 'declarations' => ['color' => 'red', 'padding' => '1px']],
    ['selector' => '.zz-b', 'declarations' => ['color' => 'red', 'padding' => '1px']],
    ['selector' => '.zz-a', 'declarations' => ['margin' => '2px']],
    ['selector' => '.zz-c', 'declarations' => ['color' => 'blue'], 'rules_group' => '@media (min-width: 600px)'],
    ['selector' => '.zz-d', 'declarations' => []],
    ['selector' => '', 'declarations' => ['color' => 'green']],
];
$say('stylesheet from rules', [
    'optimized' => wp_style_engine_get_stylesheet_from_css_rules($rules),
    'not optimized' => wp_style_engine_get_stylesheet_from_css_rules($rules, ['optimize' => false]),
    'pretty' => wp_style_engine_get_stylesheet_from_css_rules($rules, ['prettify' => true]),
    'none' => wp_style_engine_get_stylesheet_from_css_rules([]),
    'into a context' => wp_style_engine_get_stylesheet_from_css_rules($rules, ['context' => 'zz-probe-rules']),
    'that context' => wp_style_engine_get_stylesheet_from_context('zz-probe-rules'),
]);

wp_style_engine_get_styles(['spacing' => ['padding' => '10px', 'margin' => ['top' => '2px']]], ['context' => 'zz-probe', 'selector' => '.zz-a']);
wp_style_engine_get_styles(['color' => ['text' => '#fff', 'background' => 'var:preset|color|base']], ['context' => 'zz-probe', 'selector' => '.zz-b']);
wp_style_engine_get_styles(['typography' => ['fontSize' => '12px']], ['context' => 'zz-probe', 'selector' => '.zz-a']);
wp_style_engine_get_styles(['typography' => ['fontSize' => '13px']], ['context' => 'zz-probe']);
$say('stylesheet from a context', [
    'plain' => wp_style_engine_get_stylesheet_from_context('zz-probe'),
    'pretty' => wp_style_engine_get_stylesheet_from_context('zz-probe', ['prettify' => true]),
    'unoptimized' => wp_style_engine_get_stylesheet_from_context('zz-probe', ['optimize' => false]),
    'unknown' => wp_style_engine_get_stylesheet_from_context('zz-none'),
]);

$store = WP_Style_Engine_CSS_Rules_Store::get_store('zz-probe');
$say('stores', [
    'name' => $store->get_name(),
    'rule selectors' => array_keys($store->get_all_rules()),
    'a rule' => [$store->get_all_rules()['.zz-a']->get_selector(), $store->get_all_rules()['.zz-a']->get_css(), $store->get_all_rules()['.zz-a']->get_css(true)],
    'stores include ours' => in_array('zz-probe', array_keys(WP_Style_Engine_CSS_Rules_Store::get_stores()), true),
    'empty name' => WP_Style_Engine_CSS_Rules_Store::get_store(''),
    'not a string' => WP_Style_Engine_CSS_Rules_Store::get_store(5),
]);
$store->remove_rule('.zz-a');
$say('a rule removed', array_keys($store->get_all_rules()));
$rule = new WP_Style_Engine_CSS_Rule('.zz-x', ['color' => 'red', 'margin' => '1px'], '@media print');
$say('rule object', [$rule->get_selector(), $rule->get_rules_group(), $rule->get_css(), $rule->get_css(true), $rule->get_css(true, 1), $rule->get_declarations()->get_declarations()]);
$processor = new WP_Style_Engine_Processor();
$processor->add_rules([new WP_Style_Engine_CSS_Rule('.zz-p', ['color' => 'red']), new WP_Style_Engine_CSS_Rule('.zz-q', ['color' => 'red'])]);
$say('processor', [$processor->get_css(), $processor->get_css(['optimize' => false]), $processor->get_css(['prettify' => true])]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
