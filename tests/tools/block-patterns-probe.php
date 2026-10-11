<?php
/**
 * Block patterns as core and the theme register them (probe
 * block-patterns): the categories and patterns registered by init (core's
 * own, the theme's from its patterns folder, the theme's categories),
 * each kept property and the content as a length and hash; then each
 * registration called again on emptied registries, with core patterns
 * turned off, and with the theme's titles and descriptions translated
 * (tagged with their context and domain so what is translated shows); a
 * remote pattern normalized; and the pattern slugs a theme.json lists.
 * Registrations, supports and filters live for the request only.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// Core and the theme register on init, which the engine's probe runner does not fire.
if (!did_action('init')) {
    do_action('init');
}
$themeDir = get_stylesheet_directory();
$real = (string) realpath($themeDir);
$patterns = WP_Block_Patterns_Registry::get_instance();
$categories = WP_Block_Pattern_Categories_Registry::get_instance();
$shape = static function (array $pattern) use ($themeDir, $real): array {
    if (isset($pattern['content'])) {
        $pattern['content'] = [strlen($pattern['content']), md5($pattern['content'])];
    }
    if (isset($pattern['filePath'])) {
        $pattern['filePath'] = str_replace([$themeDir, $real], '{theme}', (string) $pattern['filePath']);
    }
    return $pattern;
};
$listed = static fn () => [
    'categories' => array_map(static fn ($c) => [$c['name'], $c['label'], $c['description'] ?? null], array_values($categories->get_all_registered())),
    'patterns' => array_map($shape, array_values($patterns->get_all_registered())),
];
$empty = static function () use ($patterns, $categories): void {
    foreach ($patterns->get_all_registered() as $p) {
        $patterns->unregister($p['name']);
    }
    foreach ($categories->get_all_registered() as $c) {
        $categories->unregister($c['name']);
    }
};
$names = static fn () => array_column($patterns->get_all_registered(), 'name');

$say('core patterns are on', [get_theme_support('core-block-patterns'), current_theme_supports('core-block-patterns')]);
$say('registered by init', $listed());

$empty();
_register_core_block_patterns_and_categories();
$say('core\'s alone', ['categories' => count($categories->get_all_registered()), 'patterns' => $names()]);
_register_core_block_patterns_and_categories();
$say('core\'s again', ['categories' => count($categories->get_all_registered()), 'patterns' => count($names())]);

$empty();
remove_theme_support('core-block-patterns');
_register_core_block_patterns_and_categories();
$say('core\'s with its patterns turned off', ['categories' => count($categories->get_all_registered()), 'patterns' => $names()]);
add_theme_support('core-block-patterns');

$empty();
_register_theme_block_patterns();
$theme = $names();
$say('the theme\'s alone', ['count' => count($theme), 'first' => array_slice($theme, 0, 5), 'last' => array_slice($theme, -3)]);
$patterns->unregister('twentytwentyfive/banner-intro');
register_block_pattern('twentytwentyfive/hidden-404', ['title' => 'Zz Taken First', 'content' => '<!-- wp:paragraph --><p>Zz</p><!-- /wp:paragraph -->']);
_register_theme_block_patterns();
$say('the theme\'s again, one missing and one taken', [
    'count' => count($names()),
    'the missing one is back' => $patterns->is_registered('twentytwentyfive/banner-intro'),
    'the taken one keeps its title' => $patterns->get_registered('twentytwentyfive/hidden-404')['title'] ?? null,
]);

$empty();
$tag = static fn ($translation, $text, $context, $domain) => "[{$context}|{$domain}] {$translation}";
add_filter('gettext_with_context', $tag, 10, 4);
_register_theme_block_patterns();
_register_core_block_patterns_and_categories();
remove_filter('gettext_with_context', $tag, 10);
$translated = array_values(array_filter($patterns->get_all_registered(), static fn ($p) => in_array($p['name'], ['twentytwentyfive/banner-about-book', 'twentytwentyfive/hidden-404', 'twentytwentyfive/footer'], true)));
$say('the theme\'s, translated', array_map(static fn ($p) => [$p['name'], $p['title'], $p['description'] ?? null], $translated));

$say('a remote pattern, normalized', [
    'from the directory' => wp_normalize_remote_block_pattern(['id' => 7, 'title' => 'Zz Remote', 'content' => '<!-- wp:paragraph /-->', 'categories' => ['text'], 'keywords' => ['zz'], 'block_types' => ['core/query'], 'viewport_width' => 1200, 'description' => 'Zz described', 'meta' => ['wpop_viewport_width' => 1200]]),
    'already local' => wp_normalize_remote_block_pattern(['title' => 'Zz Local', 'blockTypes' => ['core/group'], 'viewportWidth' => 800]),
    'both' => wp_normalize_remote_block_pattern(['title' => 'Zz Both', 'blockTypes' => ['core/group'], 'block_types' => ['core/query'], 'viewportWidth' => 800, 'viewport_width' => 1000]),
    'empty' => wp_normalize_remote_block_pattern([]),
]);

$say('pattern slugs a theme.json lists', [
    'the theme\'s' => wp_get_theme_directory_pattern_slugs(),
]);
add_filter('wp_theme_json_data_theme', static fn ($data) => $data->update_with(['version' => 3, 'patterns' => ['zz-one', 'zz-two']]));
WP_Theme_JSON_Resolver::clean_cached_data();
$say('pattern slugs a theme.json lists, once a filter adds some', wp_get_theme_directory_pattern_slugs());

// A pattern's content goes through the block hooks pass: template parts get the theme, every block is written out again.
$markup = '<!-- wp:group {"style":{"spacing":{}},"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:template-part {"slug":"header"} /--><!-- wp:template-part {"slug":"footer","theme":"zz"} /--></div><!-- /wp:group -->' . "\n\n" . '<!-- wp:paragraph --><p>Zz</p><!-- /wp:paragraph -->';
$pass = ['no blocks hooked' => apply_block_hooks_to_content($markup), 'only the metadata' => apply_block_hooks_to_content($markup, null, 'set_ignored_hooked_blocks_metadata'), 'plain text' => apply_block_hooks_to_content('plain text, no blocks')];
$hook = static function ($types, $position, $anchor) {
    if ($anchor === 'core/paragraph' && $position === 'after') {
        $types[] = 'core/separator';
    }
    return $types;
};
add_filter('hooked_block_types', $hook, 10, 3);
$pass['a block hooked'] = apply_block_hooks_to_content($markup, null, 'insert_hooked_blocks');
remove_filter('hooked_block_types', $hook, 10);
$say('the block hooks pass', $pass);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
