<?php
/**
 * Block template helpers (probe block-template-helpers): the title and
 * viewport tags a block template's head prints, the old skip-link filter,
 * the post context a template drops, a template object built from a post
 * with the terms and meta given, and the titles a single-post or term
 * template gets. The probe's posts and terms are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made['posts']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$printed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};

$say('the head tags', [$printed(static fn () => _block_template_render_title_tag()), $printed(static fn () => _block_template_viewport_meta_tag())]);
$say('the old skip link filter', array_map(static fn ($html) => _block_template_add_skip_link($html), ['<header>h</header><main class="x">m</main>', '<main id="zz">m</main>', '<div>no main</div>', '']));
$say('a template drops the post context', [
    has_filter('render_block_context', '_block_template_render_without_post_block_context'),
    array_map(static fn ($context) => _block_template_render_without_post_block_context($context), [['postId' => 5, 'postType' => 'wp_template', 'queryId' => 1], ['postId' => 5, 'postType' => 'wp_template_part'], ['postType' => 'wp_template'], ['postId' => 5], [], ['postId' => 5, 'postType' => 'post'], ['postId' => 0, 'postType' => 'wp_template']]),
]);

$post = static function (array $args) use (&$made): WP_Post {
    $made['posts'][] = $id = (int) wp_insert_post($args + ['post_status' => 'publish', 'post_content' => 'x']);
    return get_post($id);
};
$ids = [];
$shape = static function ($template) use (&$ids) {
    if (is_wp_error($template)) {
        return [$template->get_error_code(), $template->get_error_message()];
    }
    $vars = get_object_vars($template);
    $vars['wp_id'] = $ids[$vars['wp_id']] ?? $vars['wp_id'];
    unset($vars['modified'], $vars['date']);
    return $vars + ['dated' => $template->modified !== null && $template->date !== null];
};
$tpl = $post(['post_type' => 'wp_template', 'post_title' => 'Zz Tpl', 'post_name' => 'zz-tpl', 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'post_excerpt' => 'Zz desc']);
$part = $post(['post_type' => 'wp_template_part', 'post_title' => 'Zz Part', 'post_name' => 'zz-part']);
$ids[$tpl->ID] = '{tpl}';
$ids[$part->ID] = '{part}';
$as = static fn (WP_Post $post, string $slug) => (object) array_merge(get_object_vars($post), ['post_name' => $slug]);
$say('a template built from a post', [
    $shape(_build_block_template_object_from_post_object($tpl, ['wp_theme' => 'twentytwentyfive'], [])),
    $shape(_build_block_template_object_from_post_object($tpl, ['wp_theme' => 'zz-other'], ['origin' => 'plugin', 'is_wp_suggestion' => true, 'plugin' => 'zz-plugin', 'zz' => 1])),
    $shape(_build_block_template_object_from_post_object($tpl, [], [])),
    $shape(_build_block_template_object_from_post_object($tpl, ['wp_theme' => ''], [])),
    $shape(_build_block_template_object_from_post_object($tpl, ['wp_theme' => 'twentytwentyfive'], ['is_wp_suggestion' => '0'])),
    $shape(_build_block_template_object_from_post_object($tpl, ['wp_theme' => 'twentytwentyfive', 'wp_template_part_area' => 'header'], [])),
    $shape(_build_block_template_object_from_post_object($as($tpl, 'index'), ['wp_theme' => 'twentytwentyfive'], [])),
    $shape(_build_block_template_object_from_post_object($as($tpl, 'index'), ['wp_theme' => 'zz-other'], [])),
    $shape(_build_block_template_object_from_post_object($as($tpl, 'page-no-title'), ['wp_theme' => 'twentytwentyfive'], [])),
    $shape(_build_block_template_object_from_post_object($as($tpl, 'single'), ['wp_theme' => 'twentytwentyfive'], ['is_wp_suggestion' => false])),
    $shape(_build_block_template_object_from_post_object($part, ['wp_theme' => 'twentytwentyfive', 'wp_template_part_area' => 'header'], [])),
    $shape(_build_block_template_object_from_post_object($part, ['wp_theme' => 'twentytwentyfive'], [])),
    $shape(_build_block_template_object_from_post_object($part, ['wp_theme' => 'twentytwentyfive', 'wp_template_part_area' => 'zz-area'], [])),
    $shape(_build_block_template_object_from_post_object($as($part, 'header'), ['wp_theme' => 'twentytwentyfive'], [])),
]);

$post(['post_title' => 'Zz Tt <b>Post</b>', 'post_name' => 'zz-tt-post']);
$post(['post_title' => 'Zz Tt Draft', 'post_name' => 'zz-tt-draft', 'post_status' => 'draft']);
$post(['post_title' => 'Zz Dup', 'post_name' => 'zz-dup-a', 'post_date' => '2020-01-01 00:00:00']);
$post(['post_title' => 'Zz Dup', 'post_name' => 'zz-dup-b', 'post_date' => '2020-01-02 00:00:00']);
$post(['post_title' => 'Zz Solo Page', 'post_name' => 'zz-solo', 'post_type' => 'page']);
$made['terms'][] = (int) (wp_insert_term('Zz Tt Cat', 'category', ['slug' => 'zz-tt-cat'])['term_id'] ?? 0);
$made['terms'][] = (int) (wp_insert_term('Zz Tt Cat', 'category', ['slug' => 'zz-tt-cat-2'])['term_id'] ?? 0);
$made['terms'][] = (int) (wp_insert_term('Zz Tt Unique', 'category', ['slug' => 'zz-tt-unique'])['term_id'] ?? 0);
$titled = static function (Closure $build): array {
    $template = new WP_Block_Template();
    $template->title = 'before';
    $template->description = 'before d';
    $found = $build($template);
    return [$found, $template->title, $template->description];
};
$single = static fn (string $type, string $slug) => $titled(static fn ($template) => _wp_build_title_and_description_for_single_post_type_block_template($type, $slug, $template));
$taxonomy = static fn (string $type, string $slug) => $titled(static fn ($template) => _wp_build_title_and_description_for_taxonomy_block_template($type, $slug, $template));
$say('titles for a single post\'s template', [
    $single('post', 'zz-tt-post'),
    $single('post', 'zz-tt-draft'),
    $single('post', 'zz-never'),
    $single('post', 'zz-dup-a'),
    $single('post', 'zz-dup-b'),
    $single('page', 'zz-tt-post'),
    $single('page', 'zz-solo'),
]);
$say('titles for a term\'s template', [
    $taxonomy('category', 'zz-tt-cat'),
    $taxonomy('category', 'zz-tt-cat-2'),
    $taxonomy('category', 'zz-tt-unique'),
    $taxonomy('category', 'zz-never'),
    $taxonomy('post_tag', 'zz-tt-cat'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
