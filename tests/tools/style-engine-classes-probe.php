<?php
/**
 * The style engine's classes used directly (probe style-engine-classes):
 * block styles parsed to declarations and class names, declarations
 * compiled to CSS and a stylesheet, rules stored, and a declarations set
 * added to, removed from and printed. Protected helpers are reached
 * through a subclass, or reflection where the class is final. Nothing is
 * stored past the probe's own store.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

// WP_Style_Engine is final: its protected helpers are reached by reflection.
final class Zz_Probe_Style_Engine
{
    public static function peek(string $method, ...$args)
    {
        $reflected = new ReflectionMethod('WP_Style_Engine', $method);
        $reflected->setAccessible(true);
        return $reflected->invoke(null, ...$args);
    }
}
class Zz_Probe_CSS_Declarations extends WP_Style_Engine_CSS_Declarations
{
    public function sanitized(string $property)
    {
        return $this->sanitize_property($property);
    }

    public static function filtered(...$args)
    {
        return static::filter_declaration(...$args);
    }
}

$styles = [
    'color' => ['text' => 'var:preset|color|zz-red', 'background' => '#fff', 'gradient' => 'var:preset|gradient|zz-sunset'],
    'spacing' => ['padding' => ['top' => '1px', 'left' => 'var:preset|spacing|20'], 'margin' => '2em', 'blockGap' => '3px'],
    'border' => ['radius' => '4px', 'width' => '1px', 'color' => 'var:preset|color|zz-blue', 'top' => ['style' => 'dashed', 'color' => 'red']],
    'typography' => ['fontSize' => 'var:preset|font-size|large', 'fontFamily' => 'var:preset|font-family|zz-mono', 'lineHeight' => '1.5', 'textDecoration' => 'underline'],
    'dimensions' => ['minHeight' => '10vh'],
    'background' => ['backgroundImage' => ['url' => 'https://zz.example/i.png?a=1&b=2'], 'backgroundSize' => 'cover'],
    'shadow' => 'var:preset|shadow|natural',
    'elements' => ['link' => ['color' => ['text' => 'blue']]],
    'zz' => ['unknown' => 'x'],
];
$say('block styles parsed', [
    WP_Style_Engine::parse_block_styles($styles, []),
    WP_Style_Engine::parse_block_styles($styles, ['convert_vars_to_classnames' => true]),
    WP_Style_Engine::parse_block_styles(['spacing' => ['padding' => '1px']], ['css_vars' => ['spacing' => '--zz--$slug']]),
    WP_Style_Engine::parse_block_styles([], []),
    WP_Style_Engine::parse_block_styles('not an array', []),
    WP_Style_Engine::parse_block_styles(['color' => ['text' => ['not' => 'scalar']], 'spacing' => ['padding' => '']], []),
]);

$say('declarations compiled', [
    WP_Style_Engine::compile_css(['color' => 'red', 'padding-top' => '1px'], '.zz'),
    WP_Style_Engine::compile_css(['color' => 'red'], ''),
    WP_Style_Engine::compile_css([], '.zz'),
    WP_Style_Engine::compile_css(['COLOR' => 'red', 'bad;prop' => 'x', 'margin' => 'expression(x)', 'font-family' => '"A", serif'], '.zz'),
]);

$say('rules stored and compiled', (static function () {
    $store = WP_Style_Engine::get_store('zz-probe-store');
    $again = WP_Style_Engine::get_store('zz-probe-store');
    $rule = WP_Style_Engine::store_css_rule('zz-probe-store', '.zz-a', ['color' => 'red']);
    WP_Style_Engine::store_css_rule('zz-probe-store', '.zz-b', ['color' => 'red', 'margin' => '0']);
    WP_Style_Engine::store_css_rule('zz-probe-store', '.zz-a', ['padding' => '1px']);
    WP_Style_Engine::store_css_rule('zz-probe-store', '.zz-c', ['color' => 'blue'], '@media (min-width: 600px)');
    $rules = $store->get_all_rules();
    $out = [
        get_class($store), $store === $again, $store->get_name(), $rule, array_keys($rules),
        WP_Style_Engine::compile_stylesheet_from_css_rules($rules),
        WP_Style_Engine::compile_stylesheet_from_css_rules($rules, ['optimize' => true]),
        WP_Style_Engine::compile_stylesheet_from_css_rules($rules, ['prettify' => true]),
        WP_Style_Engine::compile_stylesheet_from_css_rules([]),
        WP_Style_Engine::store_css_rule('', '.zz', ['color' => 'red']),
        WP_Style_Engine::store_css_rule('zz-probe-store', '', ['color' => 'red']),
        WP_Style_Engine::get_store(''),
    ];
    return array_map(static fn ($value) => is_object($value) ? get_class($value) : $value, $out);
})());

$say('the protected helpers', [
    Zz_Probe_Style_Engine::peek('get_slug_from_preset_value', 'var:preset|color|zz-red', 'color'),
    Zz_Probe_Style_Engine::peek('get_slug_from_preset_value', 'var:preset|font-size|large', 'font-size'),
    Zz_Probe_Style_Engine::peek('get_slug_from_preset_value', '#fff', 'color'),
    Zz_Probe_Style_Engine::peek('get_slug_from_preset_value', 'var:preset|color|a|b', 'color'),
    Zz_Probe_Style_Engine::peek('get_css_var_value', 'var:preset|color|zz-red', ['color' => '--wp--preset--color--$slug']),
    Zz_Probe_Style_Engine::peek('get_css_var_value', 'var:preset|color|zz-red', ['spacing' => '--x--$slug']),
    Zz_Probe_Style_Engine::peek('get_css_var_value', 'red', ['color' => '--wp--preset--color--$slug']),
    array_map(static fn ($value) => Zz_Probe_Style_Engine::peek('is_valid_style_value', $value), ['red', '', '0', 0, null, [], ['a'], false, true, ' ']),
    Zz_Probe_Style_Engine::peek('get_classnames', 'var:preset|color|zz-red', ['classnames' => ['has-text-color' => true, 'has-$slug-color' => 'color'], 'path' => ['color', 'text']]),
    Zz_Probe_Style_Engine::peek('get_classnames', '#fff', ['classnames' => ['has-text-color' => true, 'has-$slug-color' => 'color']]),
    Zz_Probe_Style_Engine::peek('get_css_declarations', '1px', ['property_keys' => ['default' => 'padding', 'individual' => 'padding-%s'], 'path' => ['spacing', 'padding']]),
    Zz_Probe_Style_Engine::peek('get_css_declarations', ['top' => '1px', 'zz' => '2px'], ['property_keys' => ['default' => 'padding', 'individual' => 'padding-%s'], 'path' => ['spacing', 'padding']]),
    Zz_Probe_Style_Engine::peek('get_individual_property_css_declarations', ['top' => ['style' => 'dashed', 'width' => '2px']], ['path' => ['border', 'top'], 'property_keys' => ['default' => 'border-top']]),
    Zz_Probe_Style_Engine::peek('get_url_or_value_css_declaration', ['url' => 'https://zz.example/x.png'], ['property_keys' => ['default' => 'background-image']]),
    Zz_Probe_Style_Engine::peek('get_url_or_value_css_declaration', 'none', ['property_keys' => ['default' => 'background-image']]),
]);

$say('a declarations set', (static function () {
    $set = new Zz_Probe_CSS_Declarations(['color' => 'red', 'Padding-Top' => '1px', 'bad prop' => 'x', 'margin' => '']);
    $out = [$set->get_declarations()];
    $set->add_declaration('background', 'url("a.png")');
    $set->add_declaration('color', 'blue');
    $set->add_declaration('width', 'calc(100% - 1px)');
    $set->add_declaration('--zz-var', 'value');
    $set->add_declaration('', 'empty');
    $out[] = $set->get_declarations();
    $set->add_declarations(['height' => '1px', 'width' => '2px']);
    $set->remove_declaration('color');
    $set->remove_declarations(['height', 'nope']);
    $out[] = $set->get_declarations();
    $out[] = $set->get_declaration_options();
    $out[] = $set->get_declarations_string();
    $out[] = $set->get_declarations_string(true);
    $out[] = $set->get_declarations_string(true, 2);
    $out[] = array_map(static fn ($p) => $set->sanitized($p), ['Color', 'bad prop', '--Zz-Var', 'margin-top', 'x;y', '']);
    $out[] = [Zz_Probe_CSS_Declarations::filtered('color', 'red'), Zz_Probe_CSS_Declarations::filtered('color', 'red', ' '), Zz_Probe_CSS_Declarations::filtered('margin', 'expression(x)'), Zz_Probe_CSS_Declarations::filtered('', 'red'), Zz_Probe_CSS_Declarations::filtered('--x', 'y')];
    $plain = new WP_Style_Engine_CSS_Declarations(['color' => 'red', 'zz-unknown' => 'x', 'margin' => 'expression(y)']);
    $out[] = [$plain->get_declarations(), $plain->get_declarations_string(), (new WP_Style_Engine_CSS_Declarations('color:red'))->get_declarations()];
    $options = new Zz_Probe_CSS_Declarations();
    $options->add_declaration('color', 'red', ['zz' => 1]);
    $out[] = [$options->get_declarations(), $options->get_declaration_options()];
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
