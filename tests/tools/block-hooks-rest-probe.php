<?php
/**
 * Block Hooks around REST writes and reads (probe block-hooks-rest): the
 * template part tagName kept to the allowed tags, ignored hooked blocks
 * marked in a template's content before it is saved, a post's REST answer
 * given its hooked blocks (and a navigation's root list kept in meta),
 * the ignored list stored on update, and the content filters a block's
 * own content gets. The probe's posts are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value);
    }
    return str_replace($host, '{host}', $value);
};
add_filter('hooked_block_types', static function ($hooked, $position, $anchor, $context) {
    if ($anchor === 'core/paragraph' && $position === 'after') {
        $hooked[] = 'core/separator';
    }
    if ($anchor === 'core/navigation' && $position === 'last_child') {
        $hooked[] = 'core/home-link';
    }
    return $hooked;
}, 10, 4);

$say('the hooks', [has_filter('rest_pre_insert_wp_template', 'inject_ignored_hooked_blocks_metadata_attributes'), has_filter('rest_pre_insert_wp_template_part', 'inject_ignored_hooked_blocks_metadata_attributes')]);

$say('a template part\'s tag name', array_map(static fn ($case) => filter_block_core_template_part_attributes($case[0], $case[1], $case[2]), [
    ['header', 'tagName', []], ['header', 'tagName', ['header' => []]], ['div', 'tagName', ['div' => []]], ['zz', 'tagName', ['zz' => true]], ['section', 'tagName', ['div' => []]],
    ['DIV', 'tagName', ['div' => []]], ['', 'tagName', ['' => true]], ['Header', 'slug', []], ['a--b', 'slug', []], ['<!-- c -->', 'theme', []], ['zz <x>', 'area', []], [['a'], 'slug', []], [5, 'slug', []],
]));

$template = static function (array $changes) use ($mask) {
    $result = inject_ignored_hooked_blocks_metadata_attributes((object) $changes);
    if (is_wp_error($result)) {
        return [$result->get_error_code(), $result->get_error_message()];
    }
    $vars = get_object_vars($result);
    if (isset($vars['post_modified'])) {
        $vars['post_modified'] = preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $vars['post_modified']) === 1 ? '{date}' : $vars['post_modified'];
    }
    return $mask($vars);
};
$made[] = $existing = (int) wp_insert_post(['post_type' => 'wp_template', 'post_name' => 'zz-existing', 'post_title' => 'Zz', 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
$ids[$existing] = 'existing';
wp_set_post_terms($existing, 'twentytwentyfive', 'wp_theme');
$paragraph = '<!-- wp:paragraph --><p>t</p><!-- /wp:paragraph -->';
$say('ignored hooked blocks marked in a template', [
    $template(['post_content' => $paragraph, 'post_type' => 'wp_template', 'post_name' => 'zz-tpl', 'tax_input' => ['wp_theme' => 'twentytwentyfive']]),
    $template(['post_content' => $paragraph, 'post_type' => 'wp_template_part', 'post_name' => 'zz-part', 'tax_input' => ['wp_theme' => 'twentytwentyfive', 'wp_template_part_area' => 'header']]),
    $template(['post_type' => 'wp_template', 'post_name' => 'x']),
    $template(['post_content' => $paragraph, 'post_type' => 'wp_template', 'post_name' => 'zz-tpl']),
    $template(['ID' => $existing, 'post_content' => $paragraph]),
    (static function () use ($template, $paragraph) {
        wp_set_current_user(1);
        $out = $template(['post_content' => $paragraph, 'post_type' => 'wp_template', 'post_name' => 'zz-tpl', 'tax_input' => ['wp_theme' => 'twentytwentyfive']]);
        wp_set_current_user(0);
        return $out;
    })(),
    $template(['post_content' => '<!-- wp:heading --><h2>h</h2><!-- /wp:heading -->', 'post_type' => 'wp_template', 'post_name' => 'zz-tpl', 'tax_input' => ['wp_theme' => 'twentytwentyfive']]),
]);

$made[] = $block = (int) wp_insert_post(['post_type' => 'wp_block', 'post_title' => 'Zz BH', 'post_status' => 'publish', 'post_content' => $paragraph]);
$made[] = $nav = (int) wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => 'Zz Nav', 'post_status' => 'publish', 'post_content' => '<!-- wp:navigation-link {"label":"a","url":"/a"} /-->']);
$ids[$block] = 'block';
$ids[$nav] = 'nav';
$answer = static fn (array $data, int $id) => $mask(insert_hooked_blocks_into_rest_response(new WP_REST_Response($data), get_post($id))->get_data());
$say('a REST answer given its hooked blocks', [
    $answer(['content' => ['raw' => $paragraph, 'rendered' => 'r', 'block_version' => 1], 'id' => $block], $block),
    $answer(['id' => $block], $block),
    $answer(['content' => ['raw' => '', 'rendered' => 'r']], $block),
    $answer(['content' => ['raw' => '<!-- wp:heading --><h2>other</h2><!-- /wp:heading -->', 'rendered' => 'r']], $block),
    $answer(['content' => ['raw' => get_post($nav)->post_content, 'rendered' => 'r']], $nav),
    (static function () use ($answer, $nav) {
        update_post_meta($nav, '_wp_ignored_hooked_blocks', wp_slash(wp_json_encode(['core/search'])));
        return $answer(['content' => ['raw' => get_post($nav)->post_content, 'rendered' => 'r']], $nav);
    })(),
]);

$updated = static fn (array $changes) => $mask(get_object_vars(update_ignored_hooked_blocks_postmeta((object) $changes)));
$say('the ignored list stored on update', [
    $updated(['ID' => $block, 'post_type' => 'wp_block', 'post_content' => $paragraph]),
    $updated(['ID' => $block, 'post_type' => 'wp_block']),
    $updated(['ID' => $nav, 'post_type' => 'wp_navigation', 'post_content' => '<!-- wp:navigation-link {"label":"a","url":"/a"} /-->']),
    (static function () use ($updated, $nav) {
        delete_post_meta($nav, '_wp_ignored_hooked_blocks');
        return $updated(['ID' => $nav, 'post_type' => 'wp_navigation', 'post_content' => '<!-- wp:navigation-link {"label":"a","url":"/a"} /-->', 'meta_input' => ['zz' => 'kept']]);
    })(),
    $updated(['ID' => $block, 'post_content' => $paragraph]),
]);

add_shortcode('zzq', static fn () => '"quoted" -- ]]> :) <!-- wp:paragraph --><p>inner</p><!-- /wp:paragraph -->');
$filtered = static fn ($content, ...$rest) => $mask(_wp_apply_block_content_filters($content, ...$rest));
$say('a block\'s own content filtered', [
    $filtered('<!-- wp:paragraph --><p>a "b" -- c</p><!-- /wp:paragraph -->'),
    $filtered('plain text "q"'),
    $filtered('[zzq]'),
    $filtered("<p>[zzq]</p>\n"),
    $filtered(':) smile'),
    $filtered("a\n\nb"),
    $filtered('<img src="https://zz.example/i.png">'),
    $filtered('<img src="https://zz.example/i.png">', 'zz-context'),
    $filtered('https://zz.example/'),
    $filtered('Wordpress and ]]> end'),
    $filtered('<!-- wp:block {"ref":999999} /-->'),
    (static function () use ($mask) {
        $seen = ['zz' => true, 7 => true];
        $out = [_wp_apply_block_content_filters('x', 'ctx', $seen, 7), _wp_apply_block_content_filters('y', 'ctx', $seen, 8), $seen];
        return $mask($out);
    })(),
    (static function () {
        $heard = [];
        $listen = static function ($content) use (&$heard) {
            $heard[] = 'the_content';
            return $content;
        };
        add_filter('the_content', $listen);
        _wp_apply_block_content_filters('x');
        remove_filter('the_content', $listen);
        return $heard;
    })(),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
