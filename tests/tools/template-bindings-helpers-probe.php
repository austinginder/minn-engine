<?php
/**
 * Template-part and block-binding helpers (probe template-bindings-helpers):
 * a template part's area checked, the allowed areas, the theme attribute
 * put on and taken off template-part blocks, a template file's suffix
 * stripped; and the values the post data, post meta, term data and pattern
 * overrides sources read for a block. The post, term and meta the probe
 * makes are removed again; doing-it-wrong notices are silenced and recorded.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$notices = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$notices) {
    $notices[] = ['wrong', $function, $version];
}, 10, 3);
add_action('wp_trigger_error_run', static function ($function, $message, $level) use (&$notices) {
    $notices[] = ['error', $function, $message, $level];
}, 10, 3);
$heard = static function (Closure $run) use (&$notices) {
    $notices = [];
    $result = $run();
    return [$result, $notices];
};
if (!did_action('init')) {
    do_action('init');
}

$say('template part areas', [
    'header' => $heard(static fn () => _filter_block_template_part_area('header')),
    'footer' => $heard(static fn () => _filter_block_template_part_area('footer')),
    'uncategorized' => $heard(static fn () => _filter_block_template_part_area('uncategorized')),
    'an unknown one' => $heard(static fn () => _filter_block_template_part_area('zz-area')),
    'nothing' => $heard(static fn () => _filter_block_template_part_area('')),
    'the allowed areas' => get_allowed_block_template_part_areas(),
]);

$part = static fn (array $attrs) => ['blockName' => 'core/template-part', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
$injected = static function (array $block) {
    $result = _inject_theme_attribute_in_template_part_block($block);
    return [$result, $block];
};
$removed = static function (array $block) {
    $result = _remove_theme_attribute_from_template_part_block($block);
    return [$result, $block];
};
$say('the theme attribute', [
    'put on a part' => $injected($part(['slug' => 'header'])),
    'a part naming a theme' => $injected($part(['slug' => 'header', 'theme' => 'zz-theme'])),
    'another block' => $injected(['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]),
    'taken off a part' => $removed($part(['slug' => 'footer', 'theme' => 'zz-theme'])),
    'a part without one' => $removed($part(['slug' => 'footer'])),
    'another block, off' => $removed(['blockName' => 'core/group', 'attrs' => ['theme' => 'zz'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]),
]);

$say('template file suffixes', array_map(static fn ($file) => _strip_template_file_suffix($file), ['single.php' => 'single.php', 'page-about.html' => 'page-about.html', 'plain' => 'plain', 'archive.php.php' => 'archive.php.php', 'templates/index.html' => 'templates/index.html']));

// The binding sources' values, for a paragraph given the context they read.
wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ tbh post', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz', 'post_date' => '2026-03-04 05:06:07', 'post_date_gmt' => '2026-03-04 05:06:07']);
$term = wp_insert_term('ZZ tbh term', 'category', ['description' => 'A ZZ term.', 'slug' => 'zz-tbh-term']);
$term = is_array($term) ? (int) $term['term_id'] : 0;
wp_set_current_user(0);
register_shutdown_function(static function () use ($post, $term): void {
    if (is_int($post) && $post > 1) {
        wp_delete_post($post, true);
    }
    if (is_int($term) && $term > 1) {
        wp_delete_term($term, 'category');
    }
});
if (!is_int($post) || $post <= 1 || $term <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
register_post_meta('post', 'zz_tbh_public', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
register_post_meta('post', 'zz_tbh_hidden', ['type' => 'string', 'single' => true]);
update_post_meta($post, 'zz_tbh_public', 'ZZ public value');
update_post_meta($post, 'zz_tbh_hidden', 'ZZ hidden value');
update_post_meta($post, '_zz_tbh_protected', 'ZZ protected');
// A paragraph bound to a source, handed the context that source reads (set on the block: under the CLI no core block
// declares every key the sources read, so construction alone would filter it away on both stacks).
$paragraph = static function (array $context, array $attrs = [], string $source = 'core/post-data', array $args = []) {
    $attrs['metadata'] = ($attrs['metadata'] ?? []) + ['bindings' => ['content' => ['source' => $source, 'args' => $args]]];
    $block = new WP_Block(['blockName' => 'core/paragraph', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]);
    $block->context = $context;
    return $block;
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => json_decode(str_replace([$host, (string) $post, (string) $term], ['{host}', '{post}', '{term}'], (string) json_encode($value, JSON_UNESCAPED_SLASHES)), true);
$onPost = ['postId' => $post, 'postType' => 'post'];
$say('post data', $mask([
    'the date' => _block_bindings_post_data_get_value(['field' => 'date'], $paragraph($onPost)),
    'modified' => _block_bindings_post_data_get_value(['field' => 'modified'], $paragraph($onPost)),
    'the link' => _block_bindings_post_data_get_value(['field' => 'link'], $paragraph($onPost)),
    'the date by key' => _block_bindings_post_data_get_value(['key' => 'date'], $paragraph($onPost)),
    'an unknown field' => _block_bindings_post_data_get_value(['field' => 'zz'], $paragraph($onPost)),
    'no post' => _block_bindings_post_data_get_value(['field' => 'date'], $paragraph([])),
]));
$say('post meta', $mask([
    'shown in REST' => _block_bindings_post_meta_get_value(['key' => 'zz_tbh_public'], $paragraph($onPost, [], 'core/post-meta')),
    'not shown in REST' => _block_bindings_post_meta_get_value(['key' => 'zz_tbh_hidden'], $paragraph($onPost, [], 'core/post-meta')),
    'protected' => _block_bindings_post_meta_get_value(['key' => '_zz_tbh_protected'], $paragraph($onPost, [], 'core/post-meta')),
    'not registered' => _block_bindings_post_meta_get_value(['key' => 'zz_tbh_none'], $paragraph($onPost, [], 'core/post-meta')),
    'no key' => _block_bindings_post_meta_get_value([], $paragraph($onPost, [], 'core/post-meta')),
    'no post' => _block_bindings_post_meta_get_value(['key' => 'zz_tbh_public'], $paragraph([], [], 'core/post-meta')),
]));
$onTerm = ['termId' => $term, 'taxonomy' => 'category'];
$say('term data', $mask([
    'the name' => _block_bindings_term_data_get_value(['field' => 'name'], $paragraph($onTerm, [], 'core/term-data')),
    'the description' => _block_bindings_term_data_get_value(['field' => 'description'], $paragraph($onTerm, [], 'core/term-data')),
    'the link' => _block_bindings_term_data_get_value(['field' => 'link'], $paragraph($onTerm, [], 'core/term-data')),
    'the slug' => _block_bindings_term_data_get_value(['field' => 'slug'], $paragraph($onTerm, [], 'core/term-data')),
    'the id' => _block_bindings_term_data_get_value(['field' => 'id'], $paragraph($onTerm, [], 'core/term-data')),
    'by key' => _block_bindings_term_data_get_value(['key' => 'name'], $paragraph($onTerm, [], 'core/term-data')),
    'no term' => _block_bindings_term_data_get_value(['field' => 'name'], $paragraph([], [], 'core/term-data')),
]));
$overrides = ['pattern/overrides' => ['zz-name' => ['content' => 'ZZ overridden', 'url' => '']]];
$say('pattern overrides', [
    'an override' => _block_bindings_pattern_overrides_get_value([], $paragraph($overrides, ['metadata' => ['name' => 'zz-name']], 'core/pattern-overrides'), 'content'),
    'an empty one' => _block_bindings_pattern_overrides_get_value([], $paragraph($overrides, ['metadata' => ['name' => 'zz-name']], 'core/pattern-overrides'), 'url'),
    'another attribute' => _block_bindings_pattern_overrides_get_value([], $paragraph($overrides, ['metadata' => ['name' => 'zz-name']], 'core/pattern-overrides'), 'dropCap'),
    'another name' => _block_bindings_pattern_overrides_get_value([], $paragraph($overrides, ['metadata' => ['name' => 'zz-other']], 'core/pattern-overrides'), 'content'),
    'no name' => _block_bindings_pattern_overrides_get_value([], $paragraph($overrides, [], 'core/pattern-overrides'), 'content'),
    'no overrides' => _block_bindings_pattern_overrides_get_value([], $paragraph([], ['metadata' => ['name' => 'zz-name']], 'core/pattern-overrides'), 'content'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
