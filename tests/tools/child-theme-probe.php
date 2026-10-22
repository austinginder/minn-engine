<?php
/**
 * A child theme of a block theme, read while it is the active theme (suite
 * child-theme makes it so): the templates and parts the site has, named
 * after the child; a template looked up by the child's name and the
 * parent's; resolving a page with the child's own PHP template as the
 * fallback, and without. Same protocol as api-probe.php.
 */
$log = [];
$log[] = ['the theme', [get_stylesheet(), get_template(), wp_is_block_theme(), current_theme_supports('block-templates')]];
$ids = array_map(static fn ($t) => $t->id . '|' . $t->source . '|' . ($t->has_theme_file ? 'file' : 'none'), get_block_templates(['slug__in' => ['page', 'index', 'single', 'home']]));
sort($ids);
$log[] = ['templates', $ids];
$parts = array_map(static fn ($t) => $t->id, get_block_templates(['slug__in' => ['header', 'footer']], 'wp_template_part'));
sort($parts);
$log[] = ['parts', $parts];
$log[] = ['by the child\'s name', ($t = get_block_template(get_stylesheet() . '//page')) ? $t->id : null];
$log[] = ['by the parent\'s name', ($t = get_block_template(get_template() . '//page')) ? $t->id : null];
$child = get_stylesheet_directory();
foreach (['the child\'s page.php as fallback' => ['page', ['page-zz.php', 'page.php', 'singular.php', 'index.php'], $child . '/page.php'], 'cut to it, as locate_block_template cuts' => ['page', ['page-zz.php', 'page.php'], $child . '/page.php'], 'no fallback' => ['page', ['page-zz.php', 'page.php', 'singular.php', 'index.php'], ''], 'the parent\'s index.php as fallback' => ['page', ['page.php', 'index.php'], get_template_directory() . '/index.php'], 'single, the child\'s page.php given' => ['single', ['single-post.php', 'single.php', 'singular.php', 'index.php'], $child . '/page.php']] as $case => [$type, $templates, $fallback]) {
    $t = resolve_block_template($type, $templates, $fallback);
    $log[] = ["resolve: $case", $t ? [$t->slug, $t->source, $t->theme] : null];
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
