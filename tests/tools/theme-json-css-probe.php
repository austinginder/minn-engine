<?php
/**
 * The CSS a theme.json tree writes (probe theme-json-css): the stylesheet by
 * type, origin and option, for the site's merged data and a small tree of the
 * probe's own; one block's styles and the root layout rules; the spacing
 * sizes a scale makes, a preset's default name, var: references resolved,
 * and the declarations a block's feature selectors take. The two functions
 * that queue global styles for blocks and the custom properties are called
 * with a fresh style registry. Nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
// The protected methods, reached through a subclass.
if (!class_exists('Minn_Probe_Theme_JSON', false)) {
    eval('class Minn_Probe_Theme_JSON extends WP_Theme_JSON { public function call($method, ...$args) { return $this->$method(...$args); } public static function call_static($method, ...$args) { return static::$method(...$args); } public function declarations($metadata, $node) { $out = $this->get_feature_declarations_for_node($metadata, $node); return [$out, $node]; } }');
}
$own = [
    'version' => 3,
    'settings' => [
        'color' => ['palette' => [['slug' => 'zz-red', 'color' => '#c00', 'name' => 'ZZ Red'], ['slug' => 'zz-ink', 'color' => '#111', 'name' => 'ZZ Ink']], 'gradients' => [['slug' => 'zz-fade', 'gradient' => 'linear-gradient(#fff,#000)', 'name' => 'ZZ Fade']]],
        'typography' => ['fontSizes' => [['slug' => 'zz-big', 'size' => '2rem', 'name' => 'ZZ Big']], 'fontFamilies' => [['slug' => 'zz-serif', 'fontFamily' => 'Georgia, serif', 'name' => 'ZZ Serif']]],
        'spacing' => ['spacingScale' => ['operator' => '*', 'increment' => 1.5, 'steps' => 5, 'mediumStep' => 1.5, 'unit' => 'rem']],
        'layout' => ['contentSize' => '640px', 'wideSize' => '1000px'],
        'custom' => ['zz-line' => ['height' => '1.4']],
        'blocks' => ['core/paragraph' => ['color' => ['palette' => [['slug' => 'zz-para', 'color' => '#0c0', 'name' => 'ZZ Para']]]]],
    ],
    'styles' => [
        'color' => ['background' => 'var:preset|color|zz-ink', 'text' => '#fafafa'],
        'typography' => ['fontFamily' => 'var:preset|font-family|zz-serif', 'lineHeight' => 'var:custom|zz-line|height'],
        'spacing' => ['blockGap' => '1.5rem', 'padding' => ['top' => '10px', 'left' => 'var:preset|spacing|40']],
        'elements' => ['link' => ['color' => ['text' => 'var:preset|color|zz-red'], ':hover' => ['color' => ['text' => '#f00']]], 'h1' => ['typography' => ['fontSize' => 'var:preset|font-size|zz-big']]],
        'blocks' => ['core/paragraph' => ['typography' => ['letterSpacing' => '1px'], 'css' => 'text-wrap: pretty;'], 'core/group' => ['spacing' => ['blockGap' => '2rem']]],
        'css' => 'body { overflow-x: hidden; }',
    ],
];
$tree = new Minn_Probe_Theme_JSON($own);
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$unhost = static fn ($value) => is_string($value) ? str_replace($host, '{host}', $value) : $value;

$say('the stylesheet of a small tree', [
    'default' => $tree->get_stylesheet(),
    'variables' => $tree->get_stylesheet(['variables']),
    'presets' => $tree->get_stylesheet(['presets']),
    'styles' => $tree->get_stylesheet(['styles']),
    'base layout' => $tree->get_stylesheet(['base-layout-styles']),
    'custom css' => $tree->get_stylesheet(['custom-css']),
    'theme origin only' => $tree->get_stylesheet(['variables', 'presets'], ['theme']),
    'default origin only' => $tree->get_stylesheet(['variables', 'presets'], ['default']),
    'a root selector' => $tree->get_stylesheet(['styles'], null, ['root_selector' => '.zz-root']),
    'scoped' => $tree->get_stylesheet(['variables', 'styles', 'presets'], null, ['scope' => '.zz-scope']),
    'no root layout' => $tree->get_stylesheet(['styles'], null, ['skip_root_layout_styles' => true]),
    'nothing asked' => $tree->get_stylesheet([]),
]);
$say('the root selector named as body', $tree->get_stylesheet(['styles'], null, ['root_selector' => 'body']));
// The same tree with block gaps, root-padding-aware alignments, more elements, and a block's elements and feature selectors.
$fuller = $own;
$fuller['settings']['spacing']['blockGap'] = true;
$fuller['settings']['useRootPaddingAwareAlignments'] = true;
$fuller['styles']['spacing']['padding'] = ['top' => '1rem', 'right' => '2rem', 'bottom' => '3rem', 'left' => '4rem'];
$fuller['styles']['elements'] += [
    'button' => ['color' => ['background' => '#000', 'text' => '#fff'], ':focus' => ['color' => ['background' => '#333']], ':active' => ['color' => ['background' => '#555']]],
    'heading' => ['typography' => ['fontWeight' => '700']],
    'caption' => ['typography' => ['fontSize' => '0.8rem']],
    'cite' => ['typography' => ['fontStyle' => 'italic']],
];
$fuller['styles']['elements']['link'][':visited'] = ['color' => ['text' => '#900']];
$fuller['styles']['blocks']['core/group'] += ['elements' => ['link' => ['color' => ['text' => '#00c']]], 'color' => ['text' => '#222']];
$fuller['styles']['blocks']['core/image'] = ['border' => ['radius' => '8px'], 'filter' => ['duotone' => 'var:preset|duotone|zz']];
$fuller['styles']['blocks']['core/heading'] = ['typography' => ['fontWeight' => '600'], 'elements' => ['h2' => ['typography' => ['fontSize' => '3rem']]]];
$fuller['settings']['shadow'] = ['presets' => [['slug' => 'zz-soft', 'shadow' => '0 1px 2px #0003', 'name' => 'ZZ Soft']]];
$fullerTree = new Minn_Probe_Theme_JSON($fuller);
$say('a fuller tree', [
    'variables' => $fullerTree->get_stylesheet(['variables']),
    'styles' => $fullerTree->get_stylesheet(['styles']),
    'presets' => $fullerTree->get_stylesheet(['presets']),
    'base layout' => $fullerTree->get_stylesheet(['base-layout-styles']),
    'root layout rules' => $fullerTree->get_root_layout_rules('body', ['path' => ['styles'], 'selector' => 'body']),
]);
$merged = WP_Theme_JSON_Resolver::get_merged_data();
$say('the stylesheet of the site', [
    'default' => $unhost($merged->get_stylesheet()),
    'variables' => $unhost($merged->get_stylesheet(['variables'])),
    'presets' => $unhost($merged->get_stylesheet(['presets'])),
    'styles' => $unhost($merged->get_stylesheet(['styles'])),
    'base layout' => $unhost($merged->get_stylesheet(['base-layout-styles'])),
]);

$nodes = $tree->get_styles_block_nodes();
$paragraph = current(array_filter($nodes, static fn ($node) => ($node['name'] ?? '') === 'core/paragraph')) ?: null;
$group = current(array_filter($nodes, static fn ($node) => ($node['name'] ?? '') === 'core/group')) ?: null;
$say('one block\'s styles', [
    'paragraph' => $paragraph ? $tree->get_styles_for_block($paragraph) : 'no node',
    'group' => $group ? $tree->get_styles_for_block($group) : 'no node',
    'the root' => $tree->get_styles_for_block(['path' => ['styles'], 'selector' => 'body']),
]);
$say('the root layout rules', [
    'body' => $tree->get_root_layout_rules('body', ['path' => ['styles'], 'selector' => 'body']),
    'a root selector' => $tree->get_root_layout_rules('.zz-root', ['path' => ['styles'], 'selector' => '.zz-root']),
]);
$say('the protected writers', [
    'css variables' => $tree->call('get_css_variables', [['path' => ['settings'], 'selector' => ':root']], ['default', 'theme', 'custom']),
    'preset classes' => $tree->call('get_preset_classes', [['path' => ['settings'], 'selector' => ':root']], ['default', 'theme', 'custom']),
    'block classes' => $paragraph ? $tree->call('get_block_classes', [$paragraph]) : 'no node',
    'layout styles' => $tree->call('get_layout_styles', ['path' => ['styles'], 'selector' => 'body']),
]);

// Data, not CSS. Notices raised along the way are recorded, not printed.
$notices = [];
$hear = static function ($function, $message = '') use (&$notices) {
    $notices[] = [$function, $message];
};
add_action('deprecated_function_run', $hear, 10, 2);
add_action('wp_trigger_error_run', $hear, 10, 2);
add_action('doing_it_wrong_run', $hear, 10, 2);
set_error_handler(static fn (int $level) => in_array($level, [E_USER_NOTICE, E_USER_DEPRECATED, E_USER_WARNING], true));
$scaled = new Minn_Probe_Theme_JSON(['version' => 3, 'settings' => ['spacing' => ['spacingScale' => ['operator' => '+', 'increment' => 0.5, 'steps' => 4, 'mediumStep' => 1, 'unit' => 'rem']]]]);
$scaled->set_spacing_sizes();
$multiplied = new Minn_Probe_Theme_JSON(['version' => 3, 'settings' => ['spacing' => ['spacingScale' => ['operator' => '*', 'increment' => 1.5, 'steps' => 7, 'mediumStep' => 1.5, 'unit' => 'rem']]]]);
$multiplied->set_spacing_sizes();
$none = new Minn_Probe_Theme_JSON(['version' => 3, 'settings' => ['spacing' => ['spacingScale' => ['steps' => 0]]]]);
$none->set_spacing_sizes();
restore_error_handler();
$say('spacing sizes from a scale, notices', $notices);
$say('spacing sizes from a scale', [
    'added' => $scaled->get_settings()['spacing'] ?? null,
    'multiplied' => $multiplied->get_settings()['spacing'] ?? null,
    'no steps' => $none->get_settings()['spacing'] ?? null,
]);
$defaults = new Minn_Probe_Theme_JSON(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'zz-a', 'color' => '#a00', 'name' => 'ZZ A']]]]], 'default');
$say('a preset\'s default name', [
    'found' => $defaults->call('get_name_from_defaults', 'zz-a', ['color', 'palette']),
    'under settings' => $defaults->call('get_name_from_defaults', 'zz-a', ['settings', 'color', 'palette']),
    'a core default' => $defaults->call('get_name_from_defaults', 'black', ['settings', 'color', 'palette']),
    'missing' => $defaults->call('get_name_from_defaults', 'zz-nope', ['settings', 'color', 'palette']),
]);
$resolved = WP_Theme_JSON::resolve_variables(new WP_Theme_JSON($own));
$say('var: references resolved', $resolved instanceof WP_Theme_JSON ? ($resolved->get_raw_data()['styles'] ?? null) : get_debug_type($resolved));
$say('declarations by feature selector', $tree->declarations(
    ['name' => 'core/zz', 'selector' => '.zz-block', 'selectors' => ['root' => '.zz-block', 'typography' => ['root' => '.zz-block .zz-text', 'letterSpacing' => '.zz-block .zz-spaced'], 'color' => '.zz-block .zz-colour']],
    ['typography' => ['letterSpacing' => '2px', 'fontSize' => '20px'], 'color' => ['text' => '#123'], 'spacing' => ['padding' => '5px']]
));

// The functions, on the page's own registry; what they add is read off and put back.
$registry = wp_styles();
$handles = ['wp-block-paragraph', 'wp-block-quote', 'wp-block-pullquote', 'global-styles', 'global-styles-css-custom-properties'];
$registered = array_filter($handles, static fn ($handle) => isset($registry->registered[$handle]));
$after = static fn ($handle) => isset($registry->registered[$handle]) ? array_values(array_filter((array) $registry->get_data($handle, 'after'), 'is_string')) : [];
$kept = array_combine($handles, array_map($after, $handles));
$queued = $registry->queue;
foreach (array_diff(['wp-block-paragraph', 'wp-block-quote', 'wp-block-pullquote', 'global-styles'], $registered) as $handle) {
    wp_register_style($handle, false);
}
wp_enqueue_style('wp-block-quote');
$returned = [wp_enqueue_global_styles_css_custom_properties(), wp_add_global_styles_for_blocks()];
$added = static fn ($handle) => isset($registry->registered[$handle]) ? array_map($unhost, array_slice($after($handle), count($kept[$handle]))) : 'not registered';
$say('queued for blocks and the custom properties', [
    'returned' => $returned,
    'custom properties' => ['queued' => in_array('global-styles-css-custom-properties', $registry->queue, true), 'inline' => $added('global-styles-css-custom-properties')],
    'paragraph' => $added('wp-block-paragraph'),
    'quote' => $added('wp-block-quote'),
    'global styles' => $added('global-styles'),
]);
foreach ($handles as $handle) {
    if (in_array($handle, $registered, true)) {
        $registry->add_data($handle, 'after', $kept[$handle]);
    } elseif (isset($registry->registered[$handle])) {
        wp_deregister_style($handle);
    }
}
$registry->queue = $queued;

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
