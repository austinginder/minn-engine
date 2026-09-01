<?php
/**
 * Behaviour probe for the Block Hooks API: the hooked_block_types filter
 * (arguments, contexts, fire pattern), block_hooks registration, the
 * hooked_block / hooked_block_{name} filters, ignoredHookedBlocks
 * metadata, and where insertion actually happens (template parts,
 * patterns, do_blocks). Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

// A hooked block declared through registration, and one through the filter.
register_block_type('minn-probe/hooked-reg', [
    'block_hooks' => ['core/paragraph' => 'after'],
    'render_callback' => static fn () => '<div class="hooked-reg"></div>',
]);
register_block_type('minn-probe/hooked-flt', [
    'render_callback' => static fn () => '<div class="hooked-flt"></div>',
]);

$trace = [];
$traceFilter = static function ($types, $position, $anchor, $context) use (&$trace) {
    $kind = is_object($context) ? get_class($context) : (is_array($context) ? 'array[' . implode(',', array_slice(array_keys($context), 0, 6)) . ']' : gettype($context));
    $trace[] = [$position, $anchor, $kind];
    return $types;
};
$inject = static function ($types, $position, $anchor) {
    if ($anchor === 'core/navigation' && $position === 'after') {
        $types[] = 'minn-probe/hooked-flt';
    }
    return $types;
};

// A) get_hooked_blocks: the registry map from block_hooks declarations.
$say('get_hooked_blocks', get_hooked_blocks());

// B) Insertion into arbitrary content: apply_block_hooks_to_content.
$content = "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:navigation /--><!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->";
add_filter('hooked_block_types', $inject, 10, 3);
add_filter('hooked_block_types', $traceFilter, 20, 4);
$applied = function_exists('apply_block_hooks_to_content') ? apply_block_hooks_to_content($content, null, 'insert_hooked_blocks') : 'missing';
$say('apply_block_hooks_to_content', $applied);
$say('apply trace', $trace);
$trace = [];

// C) The same with an ignoredHookedBlocks metadata on the anchor.
$ignored = str_replace('<!-- wp:navigation /-->', '<!-- wp:navigation {"metadata":{"ignoredHookedBlocks":["minn-probe/hooked-flt"]}} /-->', $content);
$say('ignoredHookedBlocks suppresses', apply_block_hooks_to_content($ignored, null, 'insert_hooked_blocks'));

// D) The metadata pass: what a template save would stamp on anchors.
$say('set_ignored_hooked_blocks_metadata', function_exists('apply_block_hooks_to_content') ? apply_block_hooks_to_content($content, null, 'set_ignored_hooked_blocks_metadata') : 'missing');
$trace = [];

// E) hooked_block + hooked_block_{name}: default parsed shape, a filtered
// attribute, and null to suppress.
$shapes = [];
add_filter('hooked_block', static function ($block, $name, $position, $anchor) use (&$shapes) {
    $shapes[] = [$name, $position, is_array($anchor) ? ($anchor['blockName'] ?? null) : gettype($anchor), $block];
    return $block;
}, 10, 4);
add_filter('hooked_block_minn-probe/hooked-flt', static function ($block) {
    if (is_array($block)) {
        $block['attrs']['tuned'] = true;
    }
    return $block;
}, 10, 1);
$say('hooked_block filters', apply_block_hooks_to_content($content, null, 'insert_hooked_blocks'));
$say('hooked_block shapes', $shapes);
remove_all_filters('hooked_block_minn-probe/hooked-flt');
add_filter('hooked_block_minn-probe/hooked-flt', '__return_null');
$say('hooked_block null suppresses', apply_block_hooks_to_content($content, null, 'insert_hooked_blocks'));
remove_all_filters('hooked_block_minn-probe/hooked-flt');
remove_all_filters('hooked_block');

// F) Registration-declared hooks insert without any filter.
remove_filter('hooked_block_types', $inject, 10);
$say('block_hooks registration inserts', apply_block_hooks_to_content("<!-- wp:paragraph -->\n<p>anchor</p>\n<!-- /wp:paragraph -->", null, 'insert_hooked_blocks'));
add_filter('hooked_block_types', $inject, 10, 3);

// G) A registered pattern typed as a header template part: does retrieval
// and render insert, and with which context?
register_block_pattern('minn-probe/hdr', [
    'title' => 'Probe header',
    'blockTypes' => ['core/template-part/header'],
    'content' => $content,
]);
$trace = [];
$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('minn-probe/hdr');
$say('pattern retrieval content', $pattern['content'] ?? null);
$say('pattern retrieval trace', $trace);
$trace = [];
// The render itself is engine-shaped HTML (container hashes and all); what
// the contract pins is that a registered pattern renders at all and brings
// its hooked block with it.
$rendered = do_blocks('<!-- wp:pattern {"slug":"minn-probe/hdr"} /-->');
$say('pattern render carries', [
    str_contains($rendered, 'wp-block-navigation'),
    str_contains($rendered, 'hooked-flt'),
    str_contains($rendered, 'wp-block-group'),
]);
$trace = [];

// H) The file template part through get_block_template: context class and
// whether the returned content carries insertions for its own anchors.
add_filter('hooked_block_types', static function ($types, $position, $anchor) {
    if ($anchor === 'core/pattern' && $position === 'after') {
        $types[] = 'minn-probe/hooked-flt';
    }
    return $types;
}, 10, 3);
$part = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
$say('template part content', $part ? $part->content : null);
$say('template part context', array_values(array_unique(array_map(static fn (array $row): string => $row[2], $trace))));
$say('template part trace count', count($trace));

remove_all_filters('hooked_block_types');
unregister_block_pattern('minn-probe/hdr');
WP_Block_Type_Registry::get_instance()->unregister('minn-probe/hooked-reg');
WP_Block_Type_Registry::get_instance()->unregister('minn-probe/hooked-flt');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
