<?php
/**
 * Block supports (probe block-supports): what each support registers on a
 * block type, the wrapper classes and styles it applies for a block's
 * attributes, what its render filter does to a block's HTML, and the layout,
 * state and deprecated helpers around them. A plugin block type with every
 * support is registered for the run and unregistered at the end. Same
 * protocol as api-probe.php.
 */

$log = [];
// A layout container's class names a digest of its layout; the engine's digest is its own (contracts/runtime.md), so both read {hash}.
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    // So do a child layout's, a custom CSS rule's, a block state's and block-level presets' classes.
    // A duotone filter for custom colors and a style variation's instance are numbered by wp_unique_id, a counter the run before it has moved: {n}.
    return is_string($value) ? (string) preg_replace(['/(wp-container-[a-z0-9-]+-is-layout-)[0-9a-f]{8}/', '/(wp-container-content-|wp-custom-css-|wp-states-)[0-9a-f]{8}/', '/(wp-settings-)[0-9a-f]{32}/', '/(wp-duotone-[a-z0-9-]*?-)\d+(?=[\s"\')#;,.{]|$)/', '/(is-style-[a-z0-9-]+?--)\d+/'], ['$1{hash}', '$1{hash}', '$1{hash}', '$1{n}', '$1{n}'], $value) : $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!did_action('init')) {
    do_action('init');
}
$registry = WP_Block_Type_Registry::get_instance();
register_shutdown_function(static function (): void {
    foreach ([['zz/supports', 'zz-fancy'], ['core/group', 'zz-boxed'], ['core/group', 'zz-plain'], ['core/group', 'zz-partial'], ['zz/supports', 'zz-partial']] as [$name, $style]) {
        if (WP_Block_Styles_Registry::get_instance()->is_registered($name, $style)) {
            unregister_block_style($name, $style);
        }
    }
});
register_shutdown_function(static function () use ($registry): void {
    foreach (['zz/supports', 'zz/plain', 'zz/skips', 'zz/experimental', 'zz/duotone', 'zz/layout-only', 'zz/box'] as $name) {
        if ($registry->is_registered($name)) {
            unregister_block_type($name);
        }
    }
});

$all = [
    'align' => true, 'anchor' => true, 'ariaLabel' => true, 'className' => true, 'customClassName' => true, 'html' => false,
    'color' => ['text' => true, 'background' => true, 'gradients' => true, 'link' => true],
    'spacing' => ['margin' => true, 'padding' => true, 'blockGap' => true],
    'typography' => ['fontSize' => true, 'lineHeight' => true, 'fontFamily' => true, 'fontWeight' => true, 'fontStyle' => true, 'textTransform' => true, 'textDecoration' => true, 'letterSpacing' => true, 'writingMode' => true, 'textAlign' => true],
    'border' => ['color' => true, 'radius' => true, 'style' => true, 'width' => true],
    'shadow' => true,
    'dimensions' => ['minHeight' => true, 'aspectRatio' => true],
    'background' => ['backgroundImage' => true, 'backgroundSize' => true],
    'position' => ['sticky' => true],
    'layout' => true,
];
register_block_type('zz/supports', ['supports' => $all, 'render_callback' => static fn () => '<div ' . get_block_wrapper_attributes() . '>x</div>']);
register_block_type('zz/plain', ['render_callback' => static fn () => '<div ' . get_block_wrapper_attributes() . '>x</div>']);
register_block_type('zz/skips', [
    'supports' => [
        'color' => ['text' => true, 'background' => true, '__experimentalSkipSerialization' => true],
        'spacing' => ['padding' => true, 'margin' => true, '__experimentalSkipSerialization' => ['padding']],
        'typography' => ['fontSize' => true, 'lineHeight' => true, '__experimentalSkipSerialization' => ['lineHeight']],
        'border' => ['color' => true, 'width' => true, '__experimentalSkipSerialization' => true],
    ],
    'render_callback' => static fn () => '<div ' . get_block_wrapper_attributes() . '>x</div>',
]);
register_block_type('zz/experimental', [
    'supports' => [
        '__experimentalBorder' => ['color' => true, 'radius' => true, 'style' => true, 'width' => true],
        'typography' => ['fontSize' => true, 'lineHeight' => true, 'textAlign' => true, '__experimentalFontFamily' => true, '__experimentalFontWeight' => true, '__experimentalFontStyle' => true, '__experimentalTextTransform' => true, '__experimentalTextDecoration' => true, '__experimentalLetterSpacing' => true, '__experimentalWritingMode' => true],
        'color' => ['text' => true, 'background' => true],
        'spacing' => ['padding' => true],
    ],
    'render_callback' => static fn () => '<div ' . get_block_wrapper_attributes() . '>x</div>',
]);
register_block_type('zz/layout-only', ['supports' => ['layout' => true], 'render_callback' => static fn () => '<div ' . get_block_wrapper_attributes() . '>x</div>']);
register_block_type('zz/box', ['supports' => ['layout' => ['default' => ['type' => 'flex', 'flexWrap' => 'nowrap']], 'spacing' => ['blockGap' => true, 'padding' => true]]]);
$experimental = $registry->get_registered('zz/experimental');
$supports = $registry->get_registered('zz/supports');
$plain = $registry->get_registered('zz/plain');
$skips = $registry->get_registered('zz/skips');

// The supports, in the order they apply, and what each registers.
$property = new ReflectionProperty(WP_Block_Supports::class, 'block_supports');
$say('WP_Block_Supports registry', array_map(static fn (array $config) => array_map(static fn ($callback) => is_string($callback) ? $callback : gettype($callback), array_diff_key($config, ['name' => true])), $property->getValue(WP_Block_Supports::get_instance())));
$say('lock and metadata on every block type', [array_keys((array) $plain->attributes), (new WP_Block_Type('zz/new'))->get_attributes()]);

// What registering each support adds to a block type's attributes.
$registered = [];
foreach (['alignment', 'anchor', 'aria_label', 'background', 'border', 'colors', 'custom_classname', 'custom_css', 'dimensions', 'layout', 'position', 'shadow', 'spacing', 'typography'] as $support) {
    $type = new WP_Block_Type('zz/fresh-' . str_replace('_', '-', $support), ['supports' => $all + ['customCSS' => true]]);
    $before = array_keys((array) $type->attributes);
    $fn = "wp_register_{$support}_support";
    $try(static fn () => $fn($type));
    $registered[$support] = array_diff_key((array) $type->attributes, array_flip($before));
    $bare = new WP_Block_Type('zz/bare-' . str_replace('_', '-', $support), []);
    $try(static fn () => $fn($bare));
    $registered[$support . ' without supports'] = array_keys((array) $bare->attributes);
}
$say('wp_register_*_support', $registered);

// The wrapper attributes each support applies.
$attrs = [
    'align' => 'wide', 'anchor' => 'zz-anchor', 'ariaLabel' => 'Zz label', 'className' => 'zz-custom',
    'textColor' => 'accent', 'backgroundColor' => 'base', 'gradient' => 'sunset', 'fontSize' => 'large', 'fontFamily' => 'heading',
    'style' => [
        'color' => ['text' => '#111111', 'background' => '#eeeeee'],
        'spacing' => ['padding' => ['top' => '10px', 'left' => 'var:preset|spacing|20'], 'margin' => ['bottom' => '2em'], 'blockGap' => '1rem'],
        'typography' => ['lineHeight' => '1.5', 'fontWeight' => '700', 'fontStyle' => 'italic', 'textTransform' => 'uppercase', 'textDecoration' => 'underline', 'letterSpacing' => '1px', 'writingMode' => 'vertical-rl', 'textAlign' => 'center'],
        'border' => ['color' => '#ff0000', 'radius' => '4px', 'style' => 'dashed', 'width' => '2px'],
        'shadow' => 'var:preset|shadow|natural',
        'dimensions' => ['minHeight' => '50vh', 'aspectRatio' => '16/9'],
    ],
];
$custom = ['style' => ['color' => ['text' => '#123456'], 'typography' => ['fontSize' => '20px'], 'border' => ['radius' => ['topLeft' => '3px', 'bottomRight' => '6px'], 'top' => ['color' => '#000', 'width' => '1px']], 'shadow' => '2px 2px 4px #000', 'dimensions' => ['minHeight' => '10px']]];
$applied = [];
foreach (['alignment', 'anchor', 'aria_label', 'border', 'colors', 'custom_classname', 'dimensions', 'shadow', 'spacing', 'typography'] as $support) {
    $fn = "wp_apply_{$support}_support";
    $applied[$support] = [
        $try(static fn () => $fn($supports, $attrs)),
        $try(static fn () => $fn($supports, $custom)),
        $try(static fn () => $fn($plain, $attrs)),
        $try(static fn () => $fn($skips, $attrs)),
        $try(static fn () => $fn($supports, [])),
    ];
}
$say('wp_apply_*_support', $applied);
$experimentalAttrs = ['fontFamily' => 'heading', 'fontSize' => 'small', 'style' => ['border' => ['color' => '#ff0000', 'radius' => '4px', 'style' => 'dashed', 'width' => '2px'], 'typography' => ['lineHeight' => '1.5', 'fontWeight' => '700', 'fontStyle' => 'italic', 'textTransform' => 'uppercase', 'textDecoration' => 'underline', 'letterSpacing' => '1px', 'writingMode' => 'vertical-rl', 'textAlign' => 'right'], 'color' => ['text' => '#111111']]];
$experimentalBorders = ['borderColor' => 'accent', 'style' => ['border' => ['radius' => ['topLeft' => '3px', 'bottomRight' => '6px'], 'top' => ['color' => '#000', 'width' => '1px', 'style' => 'solid'], 'left' => ['color' => 'var:preset|color|accent']]]];
$say('experimental keys', [
    $try(static fn () => wp_apply_border_support($experimental, $experimentalAttrs)),
    $try(static fn () => wp_apply_border_support($experimental, $experimentalBorders)),
    $try(static fn () => wp_apply_typography_support($experimental, $experimentalAttrs)),
    $try(static fn () => wp_apply_colors_support($experimental, $experimentalAttrs)),
    $try(static fn () => render_block(['blockName' => 'zz/experimental', 'attrs' => $experimentalAttrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
    $try(static fn () => render_block(['blockName' => 'zz/experimental', 'attrs' => $experimentalBorders, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
]);
$wrapper = static fn (string $name, array $a) => $try(static fn () => render_block(['blockName' => $name, 'attrs' => $a, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$say('get_block_wrapper_attributes with every support', [$wrapper('zz/supports', $attrs), $wrapper('zz/supports', $custom), $wrapper('zz/plain', $attrs), $wrapper('zz/skips', $attrs)]);
$supported = static function (string $name, array $a, ?array $extra = null) use ($try) {
    $previous = WP_Block_Supports::$block_to_render;
    WP_Block_Supports::$block_to_render = ['blockName' => $name, 'attrs' => $a, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
    $out = $try(static fn () => $extra === null ? WP_Block_Supports::get_instance()->apply_block_supports() : get_block_wrapper_attributes($extra));
    WP_Block_Supports::$block_to_render = $previous;
    return $out;
};
$say('WP_Block_Supports::apply_block_supports', [$supported('zz/supports', $attrs), $supported('zz/supports', $custom), $supported('zz/skips', $attrs), $supported('zz/plain', []), $supported('zz/unknown', $attrs)]);
$say('get_block_wrapper_attributes with extras', [
    $supported('zz/skips', $attrs, ['class' => 'extra zz-custom', 'style' => 'color:red;', 'id' => 'mine', 'data-x' => '1']),
    $supported('zz/skips', $attrs, ['class' => '', 'style' => '']),
    $supported('zz/plain', [], ['data-x' => '1', 'class' => 'only']),
    $supported('zz/plain', [], []),
    $supported('zz/supports', $custom, ['style' => 'color:red', 'aria-label' => 'Mine']),
    $supported('zz/plain', [], ['style' => ' color: red; ;margin:0 ;', 'data-y' => 'a"b']),
    $supported('zz/supports', ['anchor' => 'theirs', 'ariaLabel' => 'Theirs'], ['data-x' => '1', 'aria-label' => 'Mine', 'id' => 'mine']),
]);

// Layout.
$say('wp_get_layout_definitions', $try(static fn () => wp_get_layout_definitions()));
$say('wp_sanitize_block_gap_value', array_map(static fn ($v) => $try(static fn () => wp_sanitize_block_gap_value($v)), ['1rem', 'var:preset|spacing|30', ['top' => '1rem', 'left' => '2rem'], ['top' => 'var:preset|spacing|20'], '', 'bad;value', null, ['top' => '1rem', 'bogus' => 'x'], 12, ['top' => ['nested']], 'calc(1rem + 2px)']));
$layouts = [
    ['type' => 'default'],
    ['type' => 'constrained', 'contentSize' => '640px', 'wideSize' => '1200px'],
    ['type' => 'constrained', 'contentSize' => '640px', 'justifyContent' => 'left'],
    ['type' => 'flex', 'orientation' => 'horizontal', 'justifyContent' => 'space-between', 'flexWrap' => 'nowrap', 'verticalAlignment' => 'center'],
    ['type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'center'],
    ['type' => 'grid', 'columnCount' => 3],
    ['type' => 'grid', 'minimumColumnWidth' => '12rem'],
];
$styles = [];
foreach ($layouts as $layout) {
    $styles[] = [
        $try(static fn () => wp_get_layout_style('.zz-sel', $layout)),
        $try(static fn () => wp_get_layout_style('.zz-sel', $layout, true, '2rem')),
        $try(static fn () => wp_get_layout_style('.zz-sel', $layout, true, ['top' => '1rem', 'left' => '3rem'], false, '0.5em')),
        $try(static fn () => wp_get_layout_style('.zz-sel', $layout, true, '2rem', true)),
    ];
}
$say('wp_get_layout_style', $styles);
$say('wp_get_layout_style edges', [
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'flex'], true, null)),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'grid', 'columnCount' => 3], true, null, false, '1.5em')),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'default'], true, null)),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'flex'], true, 'var:preset|spacing|30')),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'default'], true, ['top' => 'var:preset|spacing|20', 'left' => '3px'])),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'grid', 'columnCount' => 3, 'minimumColumnWidth' => '10rem'], true, '1rem')),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'constrained', 'contentSize' => '600px'], false, null, false, '0.5em', ['padding' => ['left' => '20px', 'right' => '0']])),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'constrained', 'contentSize' => 'var:preset|spacing|50', 'wideSize' => 'calc(100% - 2rem)'])),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'flex', 'justifyContent' => 'stretch', 'verticalAlignment' => 'bottom', 'orientation' => 'vertical', 'flexWrap' => 'nowrap'], true, '10px')),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'flex', 'justifyContent' => 'left', 'verticalAlignment' => 'top'])),
    $try(static fn () => wp_get_layout_style('.zz-e', ['type' => 'bogus'], true, '1rem')),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'grid', 'columnCount' => 3, 'minimumColumnWidth' => '10rem'], true, null, false, '0.75em')),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'grid', 'columnCount' => 2, 'minimumColumnWidth' => '8rem'])),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'grid', 'columnCount' => 2, 'minimumColumnWidth' => '8rem'], true, ['top' => '1rem', 'left' => '2rem'])),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'flex', 'orientation' => 'horizontal', 'justifyContent' => 'stretch', 'verticalAlignment' => 'space-between'])),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'constrained', 'wideSize' => '1000px'])),
    $try(static fn () => wp_get_layout_style('.zz-f', ['type' => 'constrained', 'contentSize' => '50%;color:red'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'constrained', 'justifyContent' => 'left'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'constrained', 'justifyContent' => 'right'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'constrained', 'justifyContent' => 'center'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'flex', 'orientation' => 'vertical', 'verticalAlignment' => 'space-between', 'justifyContent' => 'left'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'flex', 'verticalAlignment' => 'stretch', 'justifyContent' => 'right'])),
    $try(static fn () => wp_get_layout_style('.zz-g', ['type' => 'flex', 'flexWrap' => 'wrap'], true, '0')),
    $try(static fn () => wp_get_layout_style('.zz-h', ['type' => 'flex', 'orientation' => 'vertical'])),
    $try(static fn () => wp_get_layout_style('.zz-h', ['type' => 'flex', 'orientation' => 'vertical', 'verticalAlignment' => 'center'])),
    $try(static fn () => wp_get_layout_style('.zz-h', ['type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'bogus'])),
    $try(static fn () => wp_get_layout_style('.zz-h', ['type' => 'flex', 'justifyContent' => 'bogus', 'verticalAlignment' => 'bogus'])),
]);
$mixed = [['type' => 'flex', 'justifyContent' => 'left', 'selfStretch' => 'fill', 'flexSize' => '1', 'columnSpan' => 2, 'rowSpan' => 1, 'columnStart' => 1, 'rowStart' => 2], ['selfStretch' => 'fit'], [], ['inherit' => true, 'contentSize' => '600px']];
$say('wp_get_layout_container_values and wp_get_layout_child_values', array_map(static fn ($layout) => [$try(static fn () => wp_get_layout_container_values($layout)), $try(static fn () => wp_get_layout_child_values($layout))], [...$layouts, ...$mixed]));
$say('wp_get_child_layout_style_rules', [
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fixed', 'flexSize' => '200px'], ['type' => 'flex'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fill'], ['type' => 'flex'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['columnSpan' => 2, 'rowSpan' => 3], ['type' => 'grid', 'columnCount' => 4])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['columnStart' => 2, 'rowStart' => 1], ['type' => 'grid'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['columnStart' => 3, 'columnSpan' => 2], ['type' => 'grid', 'minimumColumnWidth' => '10rem'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fixed'], ['type' => 'flex'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', [], ['type' => 'flex'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fixed', 'flexSize' => '200px'], [])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fixed', 'flexSize' => '200px'], ['type' => 'grid'])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['selfStretch' => 'fill'], [])),
    $try(static fn () => wp_get_child_layout_style_rules('.zz-child', ['columnSpan' => 2, 'columnStart' => 1, 'rowStart' => 2, 'rowSpan' => 2], ['type' => 'grid', 'columnCount' => 3, 'minimumColumnWidth' => '200px'])),
]);
$parsed = ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']];
$parent = new WP_Block(['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'flex']], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
$say('wp_add_parent_layout_to_parsed_block', [$try(static fn () => wp_add_parent_layout_to_parsed_block($parsed, $parsed, $parent)), $try(static fn () => wp_add_parent_layout_to_parsed_block($parsed, $parsed, null))]);
$say('wp_get_block_style_variation_name_from_registered_style', [
    $try(static fn () => wp_get_block_style_variation_name_from_registered_style('is-style-outline zz', ['outline' => ['name' => 'outline']])),
    $try(static fn () => wp_get_block_style_variation_name_from_registered_style('is-style-outline', [])),
    $try(static fn () => wp_get_block_style_variation_name_from_registered_style('plain', ['outline' => ['name' => 'outline']])),
]);
$groupHtml = '<div class="wp-block-group"><div class="wp-block-group__inner-container"><p>x</p></div></div>';
$say('wp_restore_group_inner_container', [
    $try(static fn () => wp_restore_group_inner_container($groupHtml, ['blockName' => 'core/group', 'attrs' => []])),
    $try(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'default']]])),
    $try(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/paragraph', 'attrs' => []])),
]);
$say('wp_restore_image_outer_container', [
    $try(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image alignleft size-large"><img src="x.jpg" alt=""/></figure>', ['blockName' => 'core/image', 'attrs' => ['align' => 'left']])),
    $try(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image"><img src="x.jpg" alt=""/></figure>', ['blockName' => 'core/image', 'attrs' => []])),
]);

// Render filters on a block's HTML.
$html = '<div class="wp-block-zz-supports">content</div>';
$block = static fn (string $name, array $a) => ['blockName' => $name, 'attrs' => $a, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
$filters = [
    'wp_render_background_support' => [['style' => ['background' => ['backgroundImage' => ['url' => 'https://x.example/bg.jpg'], 'backgroundSize' => 'contain']]], ['style' => ['background' => ['backgroundImage' => ['url' => 'https://x.example/bg.jpg']]]], []],
    'wp_render_dimensions_support' => [['style' => ['dimensions' => ['aspectRatio' => '4/3']]], ['style' => ['dimensions' => ['aspectRatio' => 'auto', 'minHeight' => '10px']]], []],
    'wp_render_position_support' => [['style' => ['position' => ['type' => 'sticky', 'top' => '0px']]], ['style' => ['position' => ['type' => 'fixed']]], []],
    'wp_render_typography_support' => [['style' => ['typography' => ['fontSize' => '20px']]], ['fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '2rem']]], []],
    'wp_render_elements_class_name' => [['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]]], ['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]], 'className' => 'zz'], []],
    'wp_render_layout_support_flag' => [['layout' => ['type' => 'flex', 'justifyContent' => 'center']], ['layout' => ['type' => 'constrained']], []],
    'wp_render_block_visibility_support' => [['metadata' => ['blockVisibility' => false]], ['metadata' => ['blockVisibility' => ['viewport' => ['mobile' => false]]]], []],
    'wp_render_custom_css_class_name' => [['style' => ['css' => 'color: red;']], []],
    'wp_render_block_style_variation_class_name' => [['className' => 'is-style-outline'], []],
    'wp_render_block_states_support' => [['style' => ['states' => [':hover' => ['color' => ['text' => '#ff0000']]]]], []],
];
$rendered = [];
foreach ($filters as $fn => $cases) {
    $rendered[$fn] = array_map(static fn (array $a) => $try(static fn () => $fn($html, $block('zz/supports', $a))), $cases);
}
$say('render filters', $rendered);
$say('wp_render_typography_support on saved font sizes', [
    $try(static fn () => wp_render_typography_support('<p class="has-custom" style="font-size:48px">x</p>', $block('zz/supports', ['style' => ['typography' => ['fontSize' => '48px']]]))),
    $try(static fn () => wp_render_typography_support('<p style="color:red;font-size:48px;">x</p>', $block('zz/supports', ['style' => ['typography' => ['fontSize' => '48px']]]))),
    $try(static fn () => wp_render_typography_support('<p style="font-size:10px">x</p>', $block('zz/supports', ['style' => ['typography' => ['fontSize' => '10px']]]))),
    $try(static fn () => wp_render_typography_support('<p style="font-size:48px">x</p>', $block('zz/plain', ['style' => ['typography' => ['fontSize' => '48px']]]))),
    $try(static fn () => wp_render_typography_support('<p style="font-size:48px">x</p>', $block('zz/supports', ['fontSize' => 'large', 'style' => ['typography' => ['fontSize' => '48px']]]))),
]);
$say('wp_should_add_elements_class_name', [
    $try(static fn () => wp_should_add_elements_class_name($block('zz/supports', ['style' => ['elements' => ['link' => ['color' => ['text' => '#f00']]]]]), ['color' => ['link' => true]])),
    $try(static fn () => wp_should_add_elements_class_name($block('zz/supports', []), ['color' => ['link' => true]])),
]);
$say('wp_is_explicit_aspect_ratio_value', array_map(static fn ($v) => $try(static fn () => wp_is_explicit_aspect_ratio_value($v)), ['16/9', 'auto', '', '4 / 3', 'square', null]));
$say('wp_typography_get_preset_inline_style_value', [$try(static fn () => wp_typography_get_preset_inline_style_value('var:preset|font-size|large', 'font-size')), $try(static fn () => wp_typography_get_preset_inline_style_value('20px', 'font-size'))]);
$say('wp_mark_auto_generate_control_attributes', [$try(static fn () => wp_mark_auto_generate_control_attributes(['attributes' => ['title' => ['type' => 'string']]])), $try(static fn () => wp_mark_auto_generate_control_attributes(['supports' => ['autoRegister' => true], 'attributes' => ['title' => ['type' => 'string'], 'count' => ['type' => 'number'], 'hidden' => ['type' => 'string', 'role' => 'local']]]))]);

// States.
$say('state helpers', [
    $try(static fn () => wp_split_selector_list('.a, .b:is(.c, .d), .e')),
    $try(static fn () => wp_build_state_selector('.wp-block-button', '.wp-block-button .wp-block-button__link', ':hover')),
    $try(static fn () => wp_get_block_state_element_selectors('.zz-root')),
    $try(static fn () => wp_normalize_state_preset_vars('var:preset|color|accent')),
    $try(static fn () => wp_normalize_state_style_for_css_output(['color' => ['text' => 'var:preset|color|accent'], 'border' => ['width' => '1px']])),
    $try(static fn () => wp_get_state_declarations_with_background_resets(['background-color' => '#fff'])),
    $try(static fn () => wp_get_state_declarations_with_fallback_border_styles(['border-width' => '2px'])),
    $try(static fn () => wp_get_state_style_with_fallback_dimension_styles(['dimensions' => ['minHeight' => '10px']])),
    $try(static fn () => wp_get_root_state_style(['color' => ['text' => '#f00'], 'elements' => ['link' => []]], ['elements'])),
    $try(static fn () => wp_get_block_state_unique_class('core/button', ['.x{color:red}'])),
]);

$button = $registry->get_registered('core/button');
$say('state style rules', [
    $try(static fn () => wp_get_block_state_style_rules([':hover' => ['color' => ['text' => '#ff0000', 'background' => 'var:preset|color|accent'], 'border' => ['width' => '2px']], ':focus' => ['typography' => ['textDecoration' => 'underline']]], $button)),
    $try(static fn () => wp_get_block_state_style_rules([':hover' => ['color' => ['text' => '#ff0000']]], $button, '@media (min-width: 600px)')),
    $try(static fn () => wp_get_block_state_style_rules([':hover' => ['elements' => ['link' => ['color' => ['text' => '#00ff00']]]]], $supports)),
    $try(static fn () => wp_get_block_state_style_rules([':bogus' => ['color' => ['text' => '#ff0000']], 'plain' => []], $supports)),
    $try(static fn () => wp_get_state_style_groups(['color' => ['text' => '#f00'], 'elements' => ['link' => ['color' => ['text' => '#0f0']]]], ['root' => '.zz-root', 'color' => '.zz-root .zz-inner'])),
    $try(static function () {
        $rules = [];
        wp_add_block_state_style_rule($rules, ':hover', '.zz-a', ['color' => ['text' => '#ff0000']]);
        wp_add_block_state_style_rule($rules, ':focus', '.zz-a, .zz-b', ['border' => ['color' => '#000']], '@media print');
        wp_add_block_state_style_rule($rules, ':hover', '.zz-c', []);
        return $rules;
    }),
    $try(static function () {
        $groups = [];
        wp_add_state_style_group($groups, '.zz-a', ['color' => ['text' => '#ff0000']]);
        wp_add_state_style_group($groups, '.zz-a', ['border' => ['width' => '1px']]);
        wp_add_state_style_group($groups, '.zz-b', []);
        return $groups;
    }),
]);

// A theme without theme.json (the active theme's name and folders filtered for the call, nothing saved).
$classic = static function (callable $run) use ($try) {
    $name = static fn () => 'zz-classic';
    $folder = static fn () => '/nonexistent/zz-classic';
    foreach (['stylesheet', 'template'] as $hook) {
        add_filter($hook, $name, 999);
        add_filter("{$hook}_directory", $folder, 999);
    }
    $out = $try($run);
    foreach (['stylesheet', 'template'] as $hook) {
        remove_filter($hook, $name, 999);
        remove_filter("{$hook}_directory", $folder, 999);
    }
    return $out;
};
$say('a theme without theme.json', [
    $classic(static fn () => wp_theme_has_theme_json()),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group zz"><p>x</p><p>y</p></div>', ['blockName' => 'core/group', 'attrs' => []])),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><div class="wp-block-group__inner-container"><p>x</p></div></div>', ['blockName' => 'core/group', 'attrs' => []])),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'flex']]])),
    $classic(static fn () => wp_restore_group_inner_container('<section class="wp-block-group"><p>x</p></section>', ['blockName' => 'core/group', 'attrs' => ['tagName' => 'section']])),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'grid']]])),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'constrained']]])),
    $classic(static fn () => wp_restore_group_inner_container('<div class="wp-block-group"><p>x</p></div>', ['blockName' => 'core/paragraph', 'attrs' => []])),
    $classic(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image alignleft size-large"><img src="x.jpg" alt=""/></figure>', ['blockName' => 'core/image', 'attrs' => ['align' => 'left']])),
    $classic(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image aligncenter is-style-rounded"><img src="x.jpg" alt=""/><figcaption>c</figcaption></figure>', ['blockName' => 'core/image', 'attrs' => ['align' => 'center', 'className' => 'is-style-rounded']])),
    $classic(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image size-large"><img src="x.jpg" alt=""/></figure>', ['blockName' => 'core/image', 'attrs' => []])),
    $classic(static fn () => wp_restore_image_outer_container('<figure class="wp-block-image alignwide"><img src="x.jpg" alt=""/></figure>', ['blockName' => 'core/image', 'attrs' => ['align' => 'wide']])),
    $classic(static fn () => WP_Duotone::restore_image_outer_container('<div class="wp-block-image"><figure class="alignleft wp-duotone-zz"><img src="x.jpg"/></figure></div>')),
    $classic(static fn () => WP_Duotone::restore_image_outer_container('<figure class="wp-block-image"><img src="x.jpg"/></figure>')),
]);

// Settings presets, custom CSS, block style variations.
$say('settings presets', [
    $try(static fn () => _wp_add_block_level_presets_class('<div class="wp-block-group">x</div>', $block('core/group', ['settings' => ['color' => ['palette' => ['custom' => [['slug' => 'zz', 'color' => '#123456', 'name' => 'Zz']]]]]]))),
    $try(static fn () => _wp_add_block_level_presets_class('<div class="wp-block-group">x</div>', $block('core/group', []))),
    $try(static fn () => _wp_add_block_level_preset_styles(null, $block('core/group', []))),
]);
$say('custom css support', [
    $try(static fn () => wp_render_custom_css_support_styles($block('zz/supports', ['style' => ['css' => 'color: red;']]))),
    $try(static fn () => [wp_custom_css_kses_init(), has_filter('pre_kses', 'wp_filter_global_styles_post') !== false]),
    $try(static fn () => wp_custom_css_force_filtered_html_on_import_filter('zz')),
]);
$say('block style variations', [
    $try(static fn () => wp_get_block_style_variation_name_from_class('is-style-outline zz')),
    $try(static fn () => wp_get_block_style_variation_name_from_class('zz')),
    $try(static function () {
        $variation = ['color' => ['text' => ['ref' => 'styles.color.text'], 'background' => ['ref' => 'styles.missing']], 'elements' => ['link' => ['color' => ['text' => ['ref' => 'styles.elements.link.color.text']]]], 'blocks' => ['core/button' => ['color' => ['text' => ['ref' => 'styles.color.text']]]]];
        $returned = wp_resolve_block_style_variation_ref_values($variation, ['styles' => ['color' => ['text' => '#abcdef'], 'elements' => ['link' => ['color' => ['text' => '#123123']]]]]);
        return [$returned, $variation];
    }),
]);

// Deprecated: tinycolor, duotone, skipped serialization, and the rest; each call's deprecation notices too.
$notices = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$notices): void {
    $notices[] = [$function, $replacement, $version];
}, 10, 3);
$noted = static function (callable $run) use (&$notices, $try) {
    $notices = [];
    $out = $try($run);
    return ['out' => $out, 'notices' => $notices];
};
$say('tinycolor', [
    $noted(static fn () => wp_tinycolor_bound01(128, 255)),
    $noted(static fn () => wp_tinycolor_bound01('50%', 100)),
    $noted(static fn () => array_map(static fn ($c) => wp_tinycolor_bound01(...$c), [[1, 255], ['1.0', 255], [300, 255], [-5, 255], ['abc', 255], [0.5, 1], ['50%', 255]])),
    $noted(static fn () => _wp_tinycolor_bound_alpha(2)),
    $noted(static fn () => array_map('_wp_tinycolor_bound_alpha', [0.5, -1, 'x', '0.3'])),
    $noted(static fn () => wp_tinycolor_rgb_to_rgb(['r' => 255, 'g' => '50%', 'b' => 0])),
    $noted(static fn () => wp_tinycolor_hue_to_rgb(0.1, 0.9, 0.5)),
    $noted(static fn () => wp_tinycolor_hsl_to_rgb(['h' => 120, 's' => '50%', 'l' => '50%'])),
    $noted(static fn () => wp_tinycolor_hsl_to_rgb(['h' => 0, 's' => 0, 'l' => 0.5])),
    $noted(static fn () => wp_tinycolor_string_to_rgb('#ff8800')),
    $noted(static fn () => wp_tinycolor_string_to_rgb('rgba(10, 20, 30, 0.5)')),
    $noted(static fn () => wp_tinycolor_string_to_rgb('hsl(240, 100%, 50%)')),
    $noted(static fn () => array_map('wp_tinycolor_string_to_rgb', ['nope', 'red', 'transparent', '#abc', '#abcd', '#11223344', 'hsla(120, 50%, 50%, 0.3)', 'rgb(100%, 0%, 0%)', 'rgb 10 20 30', 'RGB(1,2,3)', ' #ff0000 ', 'hsv(0, 100%, 100%)', 'rgba(1,2,3)', 'rgb(1 2 3)', 'ff0000', '#ggg'])),
]);
$say('duotone deprecated', [
    $noted(static fn () => wp_get_duotone_filter_id(['slug' => 'zz-duo'])),
    $noted(static fn () => wp_get_duotone_filter_property(['slug' => 'zz-duo', 'colors' => ['#000000', '#ffffff']])),
    $noted(static fn () => wp_get_duotone_filter_property(['slug' => 'zz-duo', 'colors' => 'unset'])),
    $noted(static fn () => wp_get_duotone_filter_svg(['slug' => 'zz-duo', 'colors' => ['#000000', '#ffffff']])),
    $noted(static fn () => wp_render_duotone_filter_preset(['slug' => 'zz-duo', 'colors' => ['#000000', '#ffffff']])),
    $noted(static function () {
        $types = [];
        foreach ([['filter' => ['duotone' => true]], ['color' => ['__experimentalDuotone' => 'img']], ['color' => ['text' => true]]] as $i => $supports) {
            $type = new WP_Block_Type('zz/duo-' . $i, ['supports' => $supports]);
            wp_register_duotone_support($type);
            $own = new WP_Block_Type('zz/duo-own-' . $i, ['supports' => $supports]);
            WP_Duotone::register_duotone_support($own);
            $types[] = [array_keys((array) $type->attributes), array_keys((array) $own->attributes)];
        }
        return $types;
    }),
    $noted(static fn () => wp_render_duotone_support('<figure class="x"><img/></figure>', ['blockName' => 'core/image', 'attrs' => ['style' => ['color' => ['duotone' => 'var:preset|duotone|dark-grayscale']]]])),
]);
$say('skipped serialization deprecated', [
    $noted(static fn () => wp_skip_border_serialization($skips)),
    $noted(static fn () => wp_skip_dimensions_serialization($supports)),
    $noted(static fn () => wp_skip_spacing_serialization($skips)),
    $noted(static fn () => wp_skip_border_serialization($experimental)),
    $noted(static fn () => wp_typography_get_css_variable_inline_style(['typography' => ['fontSize' => 'var:preset|font-size|large']], 'fontSize', 'font-size')),
    $noted(static fn () => wp_typography_get_css_variable_inline_style(['style' => ['typography' => ['fontSize' => 'var:preset|font-size|large']]], 'fontSize', 'font-size')),
    $noted(static fn () => wp_typography_get_css_variable_inline_style(['style' => ['typography' => ['fontSize' => '20px']]], 'fontSize', 'font-size')),
]);
$say('the other deprecated block helpers', [
    $noted(static fn () => _wp_multiple_block_styles(['name' => 'zz/x', 'style' => ['zz-a', 'zz-b'], 'editorStyle' => 'zz-e'])),
    $noted(static fn () => wp_render_elements_support('<div class="x">y</div>', ['blockName' => 'core/paragraph', 'attrs' => ['style' => ['elements' => ['link' => ['color' => ['text' => '#f00']]]]]])),
    $noted(static fn () => wp_render_elements_support('<div class="x">y</div>', ['blockName' => 'core/paragraph', 'attrs' => ['className' => 'wp-elements-9']])),
    $noted(static fn () => [wp_create_block_style_variation_instance_name(['blockName' => 'core/group', 'attrs' => []], 'zz'), wp_create_block_style_variation_instance_name(['blockName' => 'core/group', 'attrs' => ['className' => 'x']], 'zz')]),
    $noted(static fn () => wp_get_global_styles_custom_css()),
    $noted(static fn () => WP_Theme_JSON::process_blocks_custom_css('color: green; & a { color: red; } &:hover{color:blue}', '.zz-scope')),
    $noted(static function () {
        global $wp_filter;
        $count = static fn (string $hook): int => isset($wp_filter[$hook]) ? array_sum(array_map('count', $wp_filter[$hook]->callbacks)) : 0;
        $before = [$count('wp_loaded'), $count('wp_enqueue_scripts'), $count('admin_init')];
        _wp_theme_json_webfonts_handler();
        return [$count('wp_loaded') - $before[0], $count('wp_enqueue_scripts') - $before[1], $count('admin_init') - $before[2]];
    }),
    $noted(static function () {
        $had = has_action('wp_head', 'wp_custom_css_cb');
        if (!wp_style_is('global-styles', 'registered')) {
            wp_register_style('global-styles', false);
        }
        $inline = static fn (): array => (array) (wp_styles()->get_data('global-styles', 'after') ?: []);
        $before = count($inline());
        wp_enqueue_global_styles_custom_css();
        $out = [$had, has_action('wp_head', 'wp_custom_css_cb'), array_slice($inline(), $before)];
        if ($had !== false) {
            add_action('wp_head', 'wp_custom_css_cb', $had);
        }
        return $out;
    }),
    $noted(static fn () => array_map(static fn ($c) => block_core_navigation_submenu_build_css_colors(...$c), [
        [['textColor' => 'accent', 'customBackgroundColor' => '#fff', 'overlayTextColor' => 'base', 'customOverlayBackgroundColor' => '#000'], ['style' => ['color' => ['text' => '#123']]], false],
        [['textColor' => 'accent', 'customBackgroundColor' => '#fff', 'overlayTextColor' => 'base', 'customOverlayBackgroundColor' => '#000'], [], true],
        [[], [], false],
        [['customTextColor' => '#111', 'backgroundColor' => 'base'], [], false],
        [['customOverlayTextColor' => '#222', 'overlayBackgroundColor' => 'contrast', 'textColor' => 'x'], [], true],
        [['customTextColor' => '#111', 'customBackgroundColor' => '#fff'], [], false],
        [['overlayTextColor' => 'a b'], [], true],
    ])),
]);
$notices = [];

// Whole renders of a plugin block through the render_block filters, and the stylesheet they leave.
$renders = [
    'layout flex' => ['layout' => ['type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'center', 'flexWrap' => 'wrap'], 'style' => ['spacing' => ['blockGap' => '2rem']]],
    'layout constrained' => ['layout' => ['type' => 'constrained', 'contentSize' => '600px', 'wideSize' => '900px', 'justifyContent' => 'right']],
    'layout grid' => ['layout' => ['type' => 'grid', 'columnCount' => 2, 'minimumColumnWidth' => null], 'style' => ['spacing' => ['blockGap' => ['top' => '1rem', 'left' => '2rem']]]],
    'layout child' => ['style' => ['layout' => ['selfStretch' => 'fixed', 'flexSize' => '300px']]],
    'position sticky' => ['style' => ['position' => ['type' => 'sticky', 'top' => '10px']]],
    'elements link' => ['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000'], ':hover' => ['color' => ['text' => '#00ff00']]], 'heading' => ['color' => ['text' => '#0000ff']]]]],
    'elements with a class' => ['className' => 'zz-mine', 'style' => ['elements' => ['button' => ['color' => ['background' => '#111111']], 'caption' => ['typography' => ['fontSize' => '12px']], 'cite' => ['color' => ['text' => '#222']]]]],
    'fluid font' => ['style' => ['typography' => ['fontSize' => '48px']]],
    'aspect ratio' => ['style' => ['dimensions' => ['aspectRatio' => '1', 'minHeight' => '20px']]],
    'background' => ['style' => ['background' => ['backgroundImage' => ['url' => 'https://x.example/bg.jpg', 'id' => 0], 'backgroundPosition' => '10% 20%', 'backgroundRepeat' => 'no-repeat', 'backgroundSize' => '50%', 'backgroundAttachment' => 'fixed']]],
    'visibility' => ['metadata' => ['blockVisibility' => ['viewport' => ['tablet' => false, 'desktop' => false]]]],
    'custom css' => ['style' => ['css' => 'color: green; & a { color: red; }']],
    'states' => ['style' => ['states' => [':hover' => ['color' => ['text' => '#ff0000', 'background' => '#000'], 'border' => ['width' => '1px']]]]],
    'style variation' => ['className' => 'is-style-zz-fancy zz'],
    'settings presets' => ['settings' => ['color' => ['palette' => ['custom' => [['slug' => 'zz-red', 'color' => '#ff0000', 'name' => 'Zz red']]]]]],
];
$renders += [
    'layout inherit' => ['layout' => ['inherit' => true]],
    'layout gap preset' => ['layout' => ['type' => 'flex'], 'style' => ['spacing' => ['blockGap' => 'var:preset|spacing|30']]],
    'constrained with padding' => ['layout' => ['type' => 'constrained', 'contentSize' => '500px'], 'style' => ['spacing' => ['padding' => ['left' => '10px', 'right' => 'var:preset|spacing|20']]]],
    'constrained justified' => ['layout' => ['type' => 'constrained', 'justifyContent' => 'left']],
    'flex horizontal only' => ['layout' => ['type' => 'flex', 'orientation' => 'horizontal']],
    'grid default' => ['layout' => ['type' => 'grid']],
];
$wholes = [];
foreach ($renders as $label => $a) {
    $wholes[$label] = $try(static fn () => render_block(['blockName' => 'zz/supports', 'attrs' => $a, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
}
$wholes['layout only, with a gap'] = $try(static fn () => render_block(['blockName' => 'zz/layout-only', 'attrs' => ['layout' => ['type' => 'flex'], 'style' => ['spacing' => ['blockGap' => '3rem']]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$child = static fn (array $layout) => ['blockName' => 'zz/supports', 'attrs' => ['style' => ['layout' => $layout]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
$box = static fn (array $attrs, array $children) => ['blockName' => 'zz/box', 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '<div class="wp-block-zz-box"></div>', 'innerContent' => ['<div class="wp-block-zz-box">', ...array_fill(0, count($children), null), '</div>']];
$wholes['static box with children'] = $try(static fn () => render_block($box([], [$child(['selfStretch' => 'fill']), $child(['selfStretch' => 'fixed', 'flexSize' => '120px'])])));
$wholes['static grid box with a spanning child'] = $try(static fn () => render_block($box(['layout' => ['type' => 'grid', 'columnCount' => 3], 'style' => ['spacing' => ['blockGap' => '1rem']]], [$child(['columnSpan' => 2])])));
$say('whole renders', $wholes);
// Style variations with styles of their own, a block's custom CSS, its own presets, its states.
$block = static fn (string $name, array $a, string $html = '') => ['blockName' => $name, 'attrs' => $a, 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => $html === '' ? [] : [$html]];
register_block_style('zz/supports', ['name' => 'zz-fancy', 'label' => 'Fancy', 'style_data' => ['color' => ['text' => '#123456'], 'elements' => ['link' => ['color' => ['text' => '#654321']]]]]);
register_block_style('core/group', ['name' => 'zz-boxed', 'label' => 'Boxed', 'style_data' => ['border' => ['width' => '2px', 'style' => 'solid'], 'spacing' => ['padding' => '1rem']]]);
register_block_style('core/group', ['name' => 'zz-plain', 'label' => 'Plain']);
// Registered styles reach the merged theme.json data the variations are read from once its cache is cleared.
wp_clean_theme_json_cache();
$inline = static fn (string $handle) => wp_style_is($handle, 'registered') ? array_values((array) (wp_styles()->get_data($handle, 'after') ?: [])) : null;
$say('style variations', [
    'name from class' => $try(static fn () => [wp_get_block_style_variation_name_from_class('is-style-zz-fancy is-style-other x'), wp_get_block_style_variation_name_from_class('x')]),
    'support styles' => $try(static fn () => wp_render_block_style_variation_support_styles($block('zz/supports', ['className' => 'is-style-zz-fancy zz']))),
    'plugin block' => $wrapper('zz/supports', ['className' => 'is-style-zz-fancy zz']),
    'core block' => $try(static fn () => render_block($block('core/group', ['className' => 'is-style-zz-boxed'], '<div class="wp-block-group is-style-zz-boxed"></div>'))),
    'a style without data' => $try(static fn () => render_block($block('core/group', ['className' => 'is-style-zz-plain'], '<div class="wp-block-group is-style-zz-plain"></div>'))),
    'class name filter' => $try(static fn () => wp_render_block_style_variation_class_name('<div class="x">y</div>', $block('zz/supports', ['className' => 'is-style-zz-fancy is-style-zz-fancy--7']))),
    'their styles' => $try(static fn () => $inline('block-style-variation-styles')),
    'enqueued' => $try(static function () {
        wp_enqueue_block_style_variation_styles();
        return wp_style_is('block-style-variation-styles', 'enqueued');
    }),
    'from theme.json partials' => $try(static function () {
        wp_register_block_style_variations_from_theme_json_partials([['title' => 'Zz Partial', 'slug' => 'zz-partial', 'blockTypes' => ['core/group', 'zz/supports'], 'styles' => ['color' => ['text' => '#123']]]]);
        $registry = WP_Block_Styles_Registry::get_instance();
        return [$registry->get_registered('core/group', 'zz-partial'), $registry->get_registered('zz/supports', 'zz-partial')];
    }),
]);
$say('custom css', [
    'render' => $wrapper('zz/supports', ['style' => ['css' => 'color: green; & a { color: red; } & .x:hover { color: blue; }']]),
    'its styles' => $try(static fn () => $inline('wp-block-custom-css')),
    'enqueued' => $try(static function () {
        wp_enqueue_block_custom_css();
        return wp_style_is('wp-block-custom-css', 'enqueued');
    }),
]);
$say('block-level presets', $try(static function () use ($block) {
    $group = $block('core/group', ['settings' => ['color' => ['palette' => ['custom' => [['slug' => 'zz-red', 'color' => '#ff0000', 'name' => 'Zz red']]], 'gradients' => [['slug' => 'zz-fade', 'gradient' => 'linear-gradient(red, blue)', 'name' => 'Fade']]], 'typography' => ['fontSizes' => [['slug' => 'zz-huge', 'size' => '3rem', 'name' => 'Huge']]]]], '<div class="wp-block-group"></div>');
    $html = render_block($group);
    $every = $block('core/group', ['settings' => ['color' => ['palette' => ['theme' => [['slug' => 'zz-t', 'color' => '#010101', 'name' => 'T']], 'default' => [['slug' => 'zz-d', 'color' => '#020202', 'name' => 'D']]], 'gradients' => ['custom' => [['slug' => 'zz-fade', 'gradient' => 'linear-gradient(red, blue)', 'name' => 'Fade']]]], 'typography' => ['fontSizes' => ['custom' => [['slug' => 'zz-huge', 'size' => '3rem', 'name' => 'Huge']]], 'fontFamilies' => ['blocks' => [['slug' => 'zz-mono', 'fontFamily' => 'monospace', 'name' => 'Mono']]]], 'spacing' => ['spacingSizes' => ['custom' => [['slug' => 'zz-s', 'size' => '9px', 'name' => 'S']]]]]], '<div class="wp-block-group"></div>');
    $html .= render_block($every);
    ob_start();
    do_action('wp_head');
    preg_match_all('/<style>[^<]*wp-settings-[^<]*<\/style>/', (string) ob_get_clean(), $printed);
    return [$html, $printed[0]];
}));
$say('block states', [
    'core button' => $try(static fn () => render_block($block('core/button', ['style' => [':hover' => ['color' => ['text' => '#ff0000', 'background' => 'var:preset|color|accent'], 'border' => ['width' => '2px']], ':focus' => ['color' => ['background' => '#000']], ':focus-visible' => ['dimensions' => ['minHeight' => '3px']], ':visited' => ['color' => ['text' => '#00f']]]], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">B</a></div>'))),
    'navigation link' => $try(static fn () => wp_render_block_states_support('<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content">L</a></li>', $block('core/navigation-link', ['style' => [':hover' => ['color' => ['text' => '#f00'], 'typography' => ['textDecoration' => 'underline']], ':focus' => ['color' => ['background' => '#000']]]]))),
    'not a states block' => $try(static fn () => wp_render_block_states_support('<div class="x">y</div>', $block('core/group', ['style' => [':hover' => ['color' => ['text' => '#f00']]]]))),
    'empty' => $try(static fn () => wp_render_block_states_support('', $block('core/button', ['style' => [':hover' => ['color' => ['text' => '#f00']]]]))),
]);
$say('the block-supports stylesheet', $try(static fn () => wp_style_engine_get_stylesheet_from_context('block-supports')));

// Duotone: a plugin block that supports it, with a preset and with custom colors.
register_block_type('zz/duotone', ['supports' => ['filter' => ['duotone' => true]], 'selectors' => ['filter' => ['duotone' => '.wp-block-zz-duotone img']], 'render_callback' => static fn () => '<figure ' . get_block_wrapper_attributes() . '><img src="x.jpg" alt=""/></figure>']);
$duotoneType = $registry->get_registered('zz/duotone');
$say('duotone', [
    'attributes' => array_keys((array) $duotoneType->attributes),
    'preset' => $try(static fn () => render_block(['blockName' => 'zz/duotone', 'attrs' => ['style' => ['color' => ['duotone' => 'var:preset|duotone|dark-grayscale']]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
    'custom' => $try(static fn () => render_block(['blockName' => 'zz/duotone', 'attrs' => ['style' => ['color' => ['duotone' => ['#ff0000', 'rgb(0, 0, 255)']]]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
    'unset' => $try(static fn () => render_block(['blockName' => 'zz/duotone', 'attrs' => ['style' => ['color' => ['duotone' => 'unset']]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
    'none' => $try(static fn () => render_block(['blockName' => 'zz/duotone', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []])),
    'filter id from preset' => $try(static fn () => WP_Duotone::get_filter_id_from_preset(['slug' => 'zz-duo'])),
    'svg from preset' => $try(static fn () => WP_Duotone::get_filter_svg_from_preset(['slug' => 'zz-duo', 'colors' => ['#ff8800', 'hsl(240, 100%, 50%)', '#00ff0080']])),
    'css property from preset' => $try(static fn () => WP_Duotone::get_filter_css_property_value_from_preset(['slug' => 'zz-duo', 'colors' => ['#000', '#fff']])),
    'css property unset' => $try(static fn () => WP_Duotone::get_filter_css_property_value_from_preset(['slug' => 'zz-duo', 'colors' => 'unset'])),
    'migrate flag' => $try(static fn () => WP_Duotone::migrate_experimental_duotone_support_flag(['supports' => ['color' => ['__experimentalDuotone' => '> .wp-block-zz img', 'text' => true]]], [])),
    'restore image outer container' => $try(static fn () => WP_Duotone::restore_image_outer_container('<div class="wp-block-image"><figure class="alignleft"><img src="x.jpg"/></figure></div>')),
    'block styles' => $try(static function () {
        ob_start();
        WP_Duotone::output_block_styles();
        WP_Duotone::output_footer_assets();
        return ob_get_clean();
    }),
]);

// What duotone leaves once its styles are written: rules in the block-supports store, the presets' custom properties.
$say('duotone styles', $try(static function () {
    if (!wp_style_is('global-styles', 'registered')) {
        wp_register_style('global-styles', false);
    }
    $before = count((array) (wp_styles()->get_data('global-styles', 'after') ?: []));
    WP_Duotone::output_block_styles();
    WP_Duotone::output_global_styles();
    $rules = array_values(array_filter(explode('}', wp_style_engine_get_stylesheet_from_context('block-supports')), static fn (string $rule) => str_contains($rule, 'duotone')));
    return [$rules, array_slice((array) (wp_styles()->get_data('global-styles', 'after') ?: []), $before)];
}));

// The filters an inner block passes through as its parent renders it: pre_render_block and render_block_data see the parent.
$say('inner blocks through the block filters', $try(static function () use ($box, $child) {
    $seen = [];
    $pre = static function ($pre_render, $parsed, $parent) use (&$seen) {
        $seen[] = ['pre_render_block', $parsed['blockName'] ?? null, $parent instanceof WP_Block ? $parent->name : null];
        return $pre_render;
    };
    $data = static function ($parsed, $source, $parent) use (&$seen) {
        $seen[] = ['render_block_data', $parsed['blockName'] ?? null, $parent instanceof WP_Block ? $parent->name : null, $parsed['parentLayout'] ?? null];
        return $parsed;
    };
    add_filter('pre_render_block', $pre, 99, 3);
    add_filter('render_block_data', $data, 99, 3);
    $html = render_block($box(['layout' => ['type' => 'grid', 'columnCount' => 2]], [$child(['columnSpan' => 2]), ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]]));
    remove_filter('pre_render_block', $pre, 99);
    remove_filter('render_block_data', $data, 99);
    return [$html, $seen];
}));

restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
