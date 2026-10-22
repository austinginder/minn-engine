<?php
/**
 * When a block's element class (wp-elements-N), its layout classes and the
 * paragraph's own class reach its markup, as a plugin's filters see them:
 * render_block_data after core's, render_block before, beside and after
 * core's supports, and render_block_{name} before and after; each hears
 * whether the markup has the classes yet and what the parsed block's
 * className holds. Through
 * do_blocks for a paragraph, a heading and a group (inside it a paragraph)
 * carrying link colours, a paragraph without, and a dynamic block; then
 * the same paragraph through render_block() and through WP_Block::render()
 * alone. Element numbers are read as {n}. Same protocol as api-probe.php.
 */
// Blocks enqueue their styles as they render; outside a page that is a notice this probe does not measure.
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = [];
$mark = static function (string $html): array {
    return [
        'elements' => preg_match('/class="[^"]*\bwp-elements-\d+/', $html) === 1,
        'paragraph' => str_contains($html, 'wp-block-paragraph'),
        'layout' => str_contains($html, 'is-layout-'),
    ];
};
$numbers = static fn (string $text): string => (string) preg_replace('/wp-elements-\d+/', 'wp-elements-{n}', $text);
$listen = static function (string $stage) use (&$heard, $mark, $numbers) {
    return static function ($content, $block = null) use ($stage, &$heard, $mark, $numbers) {
        $name = (string) ($block['blockName'] ?? '');
        if ($name !== '') {
            $heard[] = [$stage, $name, $mark((string) $content), $numbers((string) ($block['attrs']['className'] ?? ''))];
        }
        return $content;
    };
};
$data = static function ($parsed) use (&$heard, $numbers) {
    if (!empty($parsed['blockName'])) {
        $heard[] = ['data 11', $parsed['blockName'], $numbers((string) ($parsed['attrs']['className'] ?? ''))];
    }
    return $parsed;
};
$filters = [
    ['render_block', $listen('render 5'), 5],
    ['render_block', $listen('render 10'), 10],
    ['render_block', $listen('render 11'), 11],
    ['render_block_core/paragraph', $listen('paragraph 5'), 5],
    ['render_block_core/paragraph', $listen('paragraph 11'), 11],
    ['render_block_core/heading', $listen('heading 11'), 11],
];
add_filter('render_block_data', $data, 11);
foreach ($filters as [$hook, $callback, $priority]) {
    add_filter($hook, $callback, $priority, 2);
}
$link = '"style":{"elements":{"link":{"color":{"text":"#a00000"}}}}';
$cases = [
    'paragraph with a link colour' => "<!-- wp:paragraph {{$link}} -->\n<p>One <a href=\"#\">link</a></p>\n<!-- /wp:paragraph -->",
    'heading with a link colour' => "<!-- wp:heading {{$link}} -->\n<h2 class=\"wp-block-heading\">Two <a href=\"#\">link</a></h2>\n<!-- /wp:heading -->",
    'group with a link colour' => "<!-- wp:group {{$link},\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>Inside</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
    'plain paragraph' => "<!-- wp:paragraph -->\n<p>Plain</p>\n<!-- /wp:paragraph -->",
    'dynamic block with a link colour' => "<!-- wp:site-title {{$link}} /-->",
];
$out = [];
foreach ($cases as $label => $markup) {
    $heard = [];
    $html = do_blocks($markup);
    $out[] = [$label, [$numbers($html), $heard]];
}
$paragraph = parse_blocks($cases['paragraph with a link colour'])[0];
$heard = [];
$out[] = ['render_block()', [$numbers(render_block($paragraph)), $heard]];
$heard = [];
$out[] = ['WP_Block::render()', [$numbers((new WP_Block($paragraph))->render()), $heard]];
remove_filter('render_block_data', $data, 11);
foreach ($filters as [$hook, $callback, $priority]) {
    remove_filter($hook, $callback, $priority);
}
remove_filter('doing_it_wrong_trigger_error', '__return_false');
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
