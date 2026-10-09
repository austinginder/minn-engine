<?php
/**
 * The tabs block's helpers and the template part block's variations (probe
 * tabs-and-parts): the tab ids a tabs block lists from its panels, the
 * context it gives its inner blocks, the area variations built beside a
 * set of part variations, the part variations of the active theme, and the
 * two put together. Nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// wp_unique_id() counts per request, from wherever the stack has got to; only its shape and growth are compared.
$ids = static function ($value) use (&$ids) {
    if (is_array($value)) {
        return array_map($ids, $value);
    }
    return is_string($value) ? (string) preg_replace('/\btabs_\d+/', 'tabs_{n}', $value) : $value;
};
$panel = static fn (array $attrs = []) => '<!-- wp:tab-panel ' . wp_json_encode($attrs ?: new stdClass()) . ' --><div class="wp-block-tab-panel"><p>x</p></div><!-- /wp:tab-panel -->';
$tabs = static fn (array $attrs, string $inner) => parse_blocks('<!-- wp:tabs ' . wp_json_encode($attrs ?: new stdClass()) . ' --><div class="wp-block-tabs">' . $inner . '</div><!-- /wp:tabs -->')[0];
$panels = static fn (string $inner) => '<!-- wp:tab-panels --><div class="wp-block-tab-panels">' . $inner . '</div><!-- /wp:tab-panels -->';
$list = '<!-- wp:tab-list /-->';
$cases = [
    'labels and an anchor' => $tabs([], $list . $panels($panel(['label' => 'One']) . $panel(['label' => 'Two <b>&', 'anchor' => 'zz-two']) . $panel())),
    'the tabs anchored' => $tabs(['anchor' => 'zz-tabs'], $list . $panels($panel(['label' => 'A']))),
    'an empty anchor' => $tabs(['anchor' => ''], $list . $panels($panel(['anchor' => '']))),
    'no panels' => $tabs([], $list),
    'panels outside their container' => $tabs([], $list . $panel(['label' => 'loose'])),
    'other blocks among the panels' => $tabs([], $panels($panel() . '<!-- wp:paragraph --><p>p</p><!-- /wp:paragraph -->' . $panel(['anchor' => 'zz-last']))),
    'two containers' => $tabs([], $panels($panel()) . $panels($panel() . $panel())),
    'a block between unanchored panels' => $tabs([], $panels($panel() . '<!-- wp:paragraph --><p>p</p><!-- /wp:paragraph -->' . $panel())),
];

$say('the hook', has_filter('render_block_context', 'block_core_tabs_provide_context'));
$say('the tab ids listed', array_map(static fn ($block) => [block_core_tabs_generate_tabs_list($block['innerBlocks'], 'zz-id'), block_core_tabs_generate_tabs_list($block['innerBlocks'])], $cases));
$say('the tab ids listed from odd input', [block_core_tabs_generate_tabs_list(), block_core_tabs_generate_tabs_list([], 'zz'), block_core_tabs_generate_tabs_list([['blockName' => 'core/tab-panels', 'attrs' => [], 'innerBlocks' => [['blockName' => 'core/tab-panel', 'attrs' => ['anchor' => 'zz-a']], ['blockName' => 'core/tab-panel']]]], 'zz')]);
$say('the context given', $ids(array_map(static function ($block) {
    $first = block_core_tabs_provide_context(['keep' => 1], $block);
    $second = block_core_tabs_provide_context([], $block);
    return [$first, $second, ($first['core/tabs-id'] ?? null) === ($second['core/tabs-id'] ?? null)];
}, $cases)));
$say('the context of other blocks', [
    block_core_tabs_provide_context(['keep' => 1], ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => []]),
    block_core_tabs_provide_context(['keep' => 1], ['blockName' => 'core/tab-panel', 'attrs' => ['anchor' => 'x'], 'innerBlocks' => []]),
    block_core_tabs_provide_context([], ['blockName' => null, 'attrs' => [], 'innerBlocks' => []]),
]);

$instances = build_template_part_block_instance_variations();
$say('the part variations', $instances);
$say('the area variations', [
    build_template_part_block_area_variations([]),
    build_template_part_block_area_variations($instances),
    build_template_part_block_area_variations([['attributes' => ['area' => 'header']]]),
    build_template_part_block_area_variations([['attributes' => ['area' => 'footer']], ['attributes' => ['area' => 'zz-unknown']]]),
    build_template_part_block_area_variations([['attributes' => ['area' => 'uncategorized']]]),
    (static function () {
        // The areas left out go by name: one tagged div, or drawn as an overlay, still counts.
        $more = static fn ($areas) => array_merge($areas, [['area' => 'zz-sidebar', 'label' => 'Zz Sidebar', 'description' => 'Zz described.', 'icon' => 'sidebar', 'area_tag' => 'aside'], ['area' => 'zz-div', 'label' => 'Zz Div', 'description' => 'd', 'icon' => 'layout', 'area_tag' => 'div'], ['area' => 'zz-nav', 'label' => 'Zz Nav', 'description' => 'd', 'icon' => 'navigation-overlay', 'area_tag' => 'aside']]);
        add_filter('default_wp_template_part_areas', $more);
        $out = build_template_part_block_area_variations([['attributes' => ['area' => 'zz-sidebar']]]);
        remove_filter('default_wp_template_part_areas', $more);
        return $out;
    })(),
]);
$say('the variations together', array_map(static fn ($variation) => [$variation['name'], $variation['scope']], build_template_part_block_variations()));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
