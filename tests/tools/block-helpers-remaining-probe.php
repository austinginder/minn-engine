<?php
/**
 * The rest of blocks.php (probe block-helpers-remaining): block asset handles
 * and their paths, registering a block's script and style handles from a
 * block folder in the theme (the stacks share the themes folder), the block
 * metadata i18n schema and the old typography shape, serialized parent
 * blocks, traversal and serialization with callbacks, hooked blocks and the
 * ignored-hooked-blocks metadata, pattern blocks resolved, block content
 * filtered through kses, the footnotes taken out of excerpts, and the wpautop
 * hook put back. The probe's folder and pattern are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = get_stylesheet_directory() . '/zz-bh-block';
register_shutdown_function(static function () use ($dir): void {
    foreach (['block.json', 'index.js', 'index.asset.php', 'view.js', 'style.css', 'editor.css', 'style-rtl.css'] as $file) {
        if (is_file("{$dir}/{$file}")) {
            unlink("{$dir}/{$file}");
        }
    }
    if (is_dir($dir) && str_contains($dir, 'zz-bh-block')) {
        rmdir($dir);
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value)) {
        return $value;
    }
    return (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
};
$notices = [];
// The engine's probe runner has not run init, so its "too early" notices for scripts and styles are left out.
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$notices) {
    if (!str_contains((string) $message, 'should not be registered or enqueued until')) {
        $notices[] = [$function, (string) $message, $version];
    }
}, 10, 3);

$say('asset handles and paths', [
    array_map(static fn ($args) => generate_block_asset_handle(...$args), [['core/paragraph', 'editorScript'], ['core/paragraph', 'style'], ['core/paragraph', 'viewScriptModule'], ['zz/thing', 'editorScript'], ['zz/thing', 'script', 1], ['zz/thing', 'viewStyle', 2], ['zz/thing', 'editorStyle', 0], ['plain', 'style']]),
    array_map(static fn ($path) => remove_block_asset_path_prefix($path), ['file:./index.js', 'file:index.js', './index.js', 'wp-block-handle', 'file:../x.css', '', 'FILE:./a.js']),
]);

wp_mkdir_p($dir);
file_put_contents("{$dir}/block.json", '{"name":"zz/bh-block"}');
file_put_contents("{$dir}/index.js", 'console.log(1);');
file_put_contents("{$dir}/index.asset.php", "<?php return ['dependencies' => ['wp-blocks', 'wp-element'], 'version' => 'zz123'];");
file_put_contents("{$dir}/view.js", 'console.log(2);');
file_put_contents("{$dir}/style.css", 'p{}');
file_put_contents("{$dir}/style-rtl.css", 'p{}');
file_put_contents("{$dir}/editor.css", 'p{}');
$metadata = ['name' => 'zz/bh-block', 'file' => wp_normalize_path("{$dir}/block.json"), 'editorScript' => 'file:./index.js', 'script' => ['file:./index.js', 'zz-existing-handle'], 'viewScript' => 'file:./view.js', 'style' => ['file:./style.css', 'file:./missing.css'], 'editorStyle' => 'zz-other-handle', 'textdomain' => 'zz', 'version' => '1.2.3'];
$script = static function (string $handle) {
    $registered = wp_scripts()->registered[$handle] ?? null;
    return $registered === null ? null : [$registered->src, $registered->deps, $registered->ver, wp_scripts()->get_data($handle, 'group'), wp_scripts()->get_data($handle, 'strategy')];
};
$style = static function (string $handle) {
    $registered = wp_styles()->registered[$handle] ?? null;
    return $registered === null ? null : [$registered->src, $registered->deps, $registered->ver, wp_styles()->get_data($handle, 'rtl'), wp_styles()->get_data($handle, 'path') !== null];
};
$notices = [];
$handles = [
    register_block_script_handle($metadata, 'editorScript'), register_block_script_handle($metadata, 'script', 0), register_block_script_handle($metadata, 'script', 1), register_block_script_handle($metadata, 'viewScript'),
    register_block_script_handle($metadata, 'missingField'), register_block_script_handle(['name' => 'zz/bh-block', 'file' => $metadata['file'], 'editorScript' => 'file:./nope.js'], 'editorScript'),
    register_block_script_handle(['name' => 'zz/bh-fresh', 'file' => $metadata['file'], 'editorScript' => 'file:./nope.js'], 'editorScript'), register_block_style_handle(['name' => 'zz/bh-fresh', 'file' => $metadata['file'], 'style' => 'file:./nope.css'], 'style'),
    register_block_style_handle($metadata, 'style', 0), register_block_style_handle($metadata, 'style', 1), register_block_style_handle($metadata, 'editorStyle'), register_block_style_handle($metadata, 'missingField'),
];
$say('script and style handles', $mask([
    $handles, $script('zz-bh-block-editor-script'), $script('zz-bh-block-script'), $script('zz-bh-block-view-script'), $style('zz-bh-block-style'), $script('zz-bh-fresh-editor-script'), $style('zz-bh-fresh-style'), $notices,
    register_block_style_handle(['name' => 'core/paragraph', 'style' => 'file:./style.css', 'file' => ABSPATH . 'wp-includes/blocks/paragraph/block.json'], 'style'),
]));

$say('metadata i18n schema and old typography', [
    get_block_metadata_i18n_schema(),
    wp_migrate_old_typography_shape(['name' => 'zz/old', 'supports' => ['fontSize' => true, 'lineHeight' => true, '__experimentalFontFamily' => true, 'color' => true]]),
    wp_migrate_old_typography_shape(['name' => 'zz/mixed', 'supports' => ['typography' => ['fontSize' => false], 'fontSize' => true, '__experimentalTextTransform' => true]]),
    wp_migrate_old_typography_shape(['name' => 'zz/all', 'supports' => ['__experimentalFontStyle' => true, '__experimentalFontWeight' => true, '__experimentalLetterSpacing' => true, '__experimentalTextDecoration' => true, '__experimentalWritingMode' => true, 'textAlign' => true]]),
    wp_migrate_old_typography_shape(['name' => 'zz/none', 'supports' => ['color' => true]]),
    wp_migrate_old_typography_shape(['name' => 'zz/none']),
    $notices,
]);

$parent = serialize_block(['blockName' => 'core/group', 'attrs' => ['layout' => ['type' => 'flex']], 'innerBlocks' => [['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>a</p>', 'innerContent' => ['<p>a</p>']], ['blockName' => 'core/separator', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<hr/>', 'innerContent' => ['<hr/>']]], 'innerHTML' => '<div class="wp-block-group"></div>', 'innerContent' => ['<div class="wp-block-group">', null, null, '</div>']]);
$say('serialized parent blocks', [
    $parent, extract_serialized_parent_block($parent), remove_serialized_parent_block($parent),
    extract_serialized_parent_block('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'), remove_serialized_parent_block('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'),
    extract_serialized_parent_block('no blocks'), remove_serialized_parent_block('no blocks'), extract_serialized_parent_block('<!-- wp:spacer /-->'), remove_serialized_parent_block('<!-- wp:spacer /-->'),
]);

$parsed = parse_blocks($parent)[0];
$visited = [];
$traversed = traverse_and_serialize_block($parsed, static function (&$block, $parentBlock = null, $prev = null) use (&$visited) {
    $visited[] = ['pre', $block['blockName'], $parentBlock['blockName'] ?? null, $prev['blockName'] ?? null];
    $block['attrs']['zzSeen'] = true;
    return $block['blockName'] === 'core/separator' ? '<!-- zz before -->' : '';
}, static function (&$block, $parentBlock = null, $next = null) use (&$visited) {
    $visited[] = ['post', $block['blockName'], $parentBlock['blockName'] ?? null, $next['blockName'] ?? null];
    return $block['blockName'] === 'core/paragraph' ? '<!-- zz after -->' : '';
});
$say('traversal and serialization', [$traversed, $visited, traverse_and_serialize_block($parsed), traverse_and_serialize_block(parse_blocks('<!-- wp:spacer /-->')[0], null, null)]);

$anchor = parse_blocks('<!-- wp:paragraph {"metadata":{"ignoredHookedBlocks":["zz/old"]}} --><p>anchor</p><!-- /wp:paragraph -->')[0];
$hooked = ['before' => ['core/paragraph' => ['zz/before', 'zz/old']], 'after' => ['core/paragraph' => ['zz/after']], 'first_child' => ['core/paragraph' => ['zz/child']], 'last_child' => []];
$context = new WP_Block_Template();
$context->slug = 'zz-template';
$context->type = 'wp_template';
$context->theme = get_stylesheet();
add_filter('hooked_block_types', $types = static function ($types, $position, $anchorType, $ctx) {
    return $anchorType === 'core/paragraph' && $position === 'after' ? array_merge($types, ['zz/filtered']) : $types;
}, 10, 4);
add_filter('hooked_block_zz/filtered', $attrs = static function ($block) {
    if ($block !== null) {
        $block['attrs']['zz'] = 'set';
    }
    return $block;
});
add_filter('hooked_block_zz/before', $nulled = static fn ($block) => null);
$hooks = [];
foreach (['before', 'after', 'first_child', 'last_child'] as $position) {
    $copy = $anchor;
    $hooks[] = [$position, insert_hooked_blocks($copy, $position, $hooked, $context), $copy['attrs']['metadata'] ?? null];
    $copy = $anchor;
    $hooks[] = [$position, set_ignored_hooked_blocks_metadata($copy, $position, $hooked, $context), $copy['attrs']['metadata'] ?? null];
    $copy = $anchor;
    $hooks[] = [$position, insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata($copy, $position, $hooked, $context), $copy['attrs']['metadata'] ?? null];
}
remove_filter('hooked_block_types', $types, 10);
remove_filter('hooked_block_zz/filtered', $attrs);
remove_filter('hooked_block_zz/before', $nulled);
$say('hooked blocks', $hooks);

register_block_pattern('zz/bh-pattern', ['title' => 'Zz pattern', 'content' => '<!-- wp:paragraph --><p>from the pattern</p><!-- /wp:paragraph --><!-- wp:pattern {"slug":"zz/bh-inner"} /-->']);
register_block_pattern('zz/bh-inner', ['title' => 'Zz inner', 'content' => '<!-- wp:heading --><h2>inner</h2><!-- /wp:heading -->']);
register_block_pattern('zz/bh-loop', ['title' => 'Zz loop', 'content' => '<!-- wp:pattern {"slug":"zz/bh-loop"} /-->']);
$shape = static function (array $blocks) use (&$shape): array {
    return array_map(static fn ($block) => [$block['blockName'], $block['attrs'], trim((string) $block['innerHTML']), $shape($block['innerBlocks'])], $blocks);
};
$resolved = static fn ($markup) => $shape(resolve_pattern_blocks(parse_blocks($markup)));
register_block_pattern('zz/bh-single', ['title' => 'Zz single', 'content' => '<!-- wp:quote --><blockquote><!-- wp:pattern {"slug":"zz/bh-inner"} /--></blockquote><!-- /wp:quote -->']);
$say('pattern blocks resolved', [
    $resolved('<!-- wp:pattern {"slug":"zz/bh-pattern"} /--><!-- wp:separator --><hr/><!-- /wp:separator -->'),
    $resolved('<!-- wp:group --><div><!-- wp:pattern {"slug":"zz/bh-inner"} /--></div><!-- /wp:group -->'),
    $resolved('<!-- wp:pattern {"slug":"zz/missing"} /-->'), $resolved('<!-- wp:pattern {"slug":"zz/bh-loop"} /-->'), $resolved('<!-- wp:pattern /-->'),
    $resolved('<!-- wp:pattern {"slug":"zz/bh-single"} /-->'), $resolved('<!-- wp:pattern {"slug":"zz/bh-inner","className":"zz"} /-->'),
]);
unregister_block_pattern('zz/bh-single');
unregister_block_pattern('zz/bh-pattern');
unregister_block_pattern('zz/bh-inner');
unregister_block_pattern('zz/bh-loop');

$withAttributes = '<!-- wp:paragraph {"zz":"<script>x</script>","safe":"<b>ok</b>","nested":{"a":"<img src=x onerror=1>"},"list":["<i>i</i>",3,true]} --><p onclick="x">text <script>bad</script></p><!-- /wp:paragraph -->';
$say('block content filtered', [
    filter_block_content($withAttributes), filter_block_content($withAttributes, 'data'), filter_block_content($withAttributes, ['p' => []]), filter_block_content('<!-- wp:html --><a href="javascript:x">j</a><!-- /wp:html -->', 'post', ['https']),
    filter_block_content('plain <b>text</b> <script>s</script>'), filter_block_content('<!-- wp:zz/void {"u":"https://x.example"} /-->', 'post'),
    filter_block_kses_value('<b>a</b><script>s</script>', 'post'), filter_block_kses_value(['k' => '<i>i</i>', '<x>' => 'v', 'n' => 5, 'deep' => ['<u>u</u>']], ['i' => [], 'u' => []]), filter_block_kses_value(true, 'post'), filter_block_kses_value(null, 'post'),
    filter_block_kses_value('<a href="ftp://x">f</a>', 'post', ['https']), filter_block_kses_value('<b>a</b>', 'post', [], ['blockName' => 'core/paragraph', 'attrs' => []]),
    _filter_block_content_callback(['<!-- wp:paragraph {"x":"<b>y</b>"} -->', '<!-- wp:paragraph {"x":"<b>y</b>"} -->']),
]);

$footnotes = '<!-- wp:footnotes /--><!-- wp:paragraph --><p>keep</p><!-- /wp:paragraph --><!-- wp:footnotes {"x":1} /-->';
$say('footnotes out of excerpts', [excerpt_remove_footnotes($footnotes), excerpt_remove_footnotes('no blocks here'), excerpt_remove_footnotes('<!-- wp:footnotes --><ol></ol><!-- /wp:footnotes -->text'), excerpt_remove_footnotes('see <sup data-fn="x" class="fn"><a href="#x" id="x-link">1</a></sup> here')]);

$priority = static fn () => [has_filter('the_content', 'wpautop'), has_filter('the_content', '_restore_wpautop_hook')];
remove_filter('the_content', 'wpautop', 10);
add_filter('the_content', '_restore_wpautop_hook', 11);
$restore = [$priority(), _restore_wpautop_hook('<p>same</p>'), $priority()];
remove_filter('the_content', 'wpautop', 10);
add_filter('the_content', 'wpautop', 10);
$restore[] = $priority();
$say('the wpautop hook put back', $restore);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
