<?php
/**
 * The rest of the block supports (probe block-supports-remaining): custom CSS
 * taken out of saved block markup (slashed and not, nested, beside other
 * styles, none at all), the save filters that do it added and taken away,
 * and the elements support's styles for a parsed block (a link color, a
 * heading's, a button's, none, a block without the support). Nothing is
 * stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// An elements class names a digest the engine computes its own way (contracts/runtime.md).
$mask = static fn ($value) => is_string($value) ? (string) preg_replace('/wp-elements-[0-9a-f]{32}|wp-elements-\d+/', 'wp-elements-{hash}', $value) : $value;

$markup = [
    '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>One</p><!-- /wp:paragraph -->',
    '<!-- wp:paragraph {"style":{"css":"color: red;","color":{"text":"#111111"}}} --><p>Two</p><!-- /wp:paragraph -->',
    '<!-- wp:group {"style":{"css":"padding: 1em;"}} --><div class="wp-block-group"><!-- wp:paragraph {"style":{"css":"color: blue;"}} --><p>Nested</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
    '<!-- wp:paragraph {"style":{\\"css\\":\\"color: red;\\"}} --><p>Slashed</p><!-- /wp:paragraph -->',
    wp_slash('<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Slashed whole</p><!-- /wp:paragraph -->'),
    '<!-- wp:paragraph --><p>No css</p><!-- /wp:paragraph -->',
    '<p>Classic content with "css" in it.</p>',
    '',
];
$say('custom CSS taken out of saved markup', array_map(static fn ($content) => wp_strip_custom_css_from_blocks($content), $markup));

$filters = static fn () => [has_filter('content_save_pre', 'wp_strip_custom_css_from_blocks'), has_filter('content_filtered_save_pre', 'wp_strip_custom_css_from_blocks')];
$before = $filters();
wp_custom_css_kses_init_filters();
$added = $filters();
wp_custom_css_kses_init_filters();
$twice = $filters();
wp_custom_css_remove_filters();
$removed = $filters();
wp_custom_css_remove_filters();
$say('the save filters', [$before, $added, $twice, $removed, $filters()]);

$parsed = static fn (string $name, array $attrs) => ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']];
$styles = [];
foreach ([
    'a link color' => ['core/paragraph', ['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]]]],
    'a link color and a class' => ['core/paragraph', ['className' => 'zz-own', 'style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]]]],
    'a heading color' => ['core/group', ['style' => ['elements' => ['heading' => ['color' => ['text' => '#00ff00']]]]]],
    'a button color' => ['core/group', ['style' => ['elements' => ['button' => ['color' => ['background' => '#0000ff']]]]]],
    'a preset link color' => ['core/paragraph', ['style' => ['elements' => ['link' => ['color' => ['text' => 'var:preset|color|accent-1']]]]]],
    'no elements' => ['core/paragraph', ['style' => ['color' => ['text' => '#111111']]]],
    'nothing' => ['core/paragraph', []],
    'a block without the support' => ['core/html', ['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]]]],
    'an unknown block' => ['zz/none', ['style' => ['elements' => ['link' => ['color' => ['text' => '#ff0000']]]]]],
] as $label => [$name, $attrs]) {
    $result = wp_render_elements_support_styles($parsed($name, $attrs));
    $styles[$label] = array_map($mask, ['className' => $result['attrs']['className'] ?? null, 'keys' => array_keys($result['attrs']), 'same' => $result === $parsed($name, $attrs)]);
}
$say('the elements support styles', $styles);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
