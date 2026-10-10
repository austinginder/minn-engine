<?php
/**
 * The rest of deprecated.php (probe deprecated-remaining): every function's
 * return, what it prints, and the deprecation it announces (heard through
 * deprecated_function_run, with the errors themselves switched off). Author
 * fields, the old permission checks, the old link and rel helpers, the
 * robots tags (on a public and a private site), page by title, the
 * disallowed list check, the loading attribute default, and the functions
 * that now do nothing. The probe's posts, category and options are removed
 * or put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
$options = ['blog_public' => get_option('blog_public', null), 'disallowed_keys' => get_option('disallowed_keys', null)];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $options, $pings): void {
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace([ABSPATH, get_stylesheet_directory()], ['{abspath}/', '{theme}'], $value);
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$heard = [];
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('deprecated_argument_trigger_error', '__return_false');
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$heard) {
    $heard[] = [$function, $replacement, $version];
}, 10, 3);
add_action('deprecated_argument_run', static function ($function, $message, $version) use (&$heard) {
    $heard[] = ['argument', $function, $version];
}, 10, 3);
// One call: what it returns, what it prints, and the deprecations it announced.
$call = static function (Closure $run) use (&$heard, $mask): array {
    $heard = [];
    ob_start();
    $returned = $run();
    $printed = (string) ob_get_clean();
    return $mask([$returned, $printed, $heard]);
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$single = static function (int $id) {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query(['p' => $id, 'post_type' => 'any']);
    $wp_query->the_post();
};

$made['terms'][] = [$category = (int) wp_insert_term('zz dp cat', 'category')['term_id'], 'category'];
$ids[$category] = 'zz-dp-cat';
$first = $post('zz dp first', ['post_category' => [$category], 'post_date' => '1982-01-02 03:04:05']);
$second = $post('zz dp second', ['post_category' => [$category], 'post_date' => '1982-02-03 04:05:06']);
$page = $post('zz dp page', ['post_type' => 'page']);
wp_set_current_user(1);
$single($second);

$author = [];
foreach (['ID', 'aim', 'description', 'firstname', 'icq', 'lastname', 'login', 'msn', 'nickname', 'url', 'yim'] as $field) {
    $author[$field] = match ($field) {
        'ID' => [$call(static fn () => get_the_author_ID()), $call(static fn () => the_author_ID())],
        'aim' => [$call(static fn () => get_the_author_aim()), $call(static fn () => the_author_aim())],
        'description' => [$call(static fn () => get_the_author_description()), $call(static fn () => the_author_description())],
        'firstname' => [$call(static fn () => get_the_author_firstname()), $call(static fn () => the_author_firstname())],
        'icq' => [$call(static fn () => get_the_author_icq()), $call(static fn () => the_author_icq())],
        'lastname' => [$call(static fn () => get_the_author_lastname()), $call(static fn () => the_author_lastname())],
        'login' => [$call(static fn () => get_the_author_login()), $call(static fn () => the_author_login())],
        'msn' => [$call(static fn () => get_the_author_msn()), $call(static fn () => the_author_msn())],
        'nickname' => [$call(static fn () => get_the_author_nickname()), $call(static fn () => the_author_nickname())],
        'url' => [$call(static fn () => get_the_author_url()), $call(static fn () => the_author_url())],
        'yim' => [$call(static fn () => get_the_author_yim()), $call(static fn () => the_author_yim())],
    };
}
$say('author fields', $author);

$say('old permission checks', [
    $call(static fn () => user_can_create_post(1)), $call(static fn () => user_can_create_draft(1)), $call(static fn () => user_can_set_post_date(1)),
    $call(static fn () => user_can_edit_post(1, $second)), $call(static fn () => user_can_delete_post(1, $second)), $call(static fn () => user_can_edit_post_comments(1, $second)),
    $call(static fn () => user_can_delete_post_comments(1, $second)), $call(static fn () => user_can_edit_post_date(1, $second)), $call(static fn () => user_can_edit_user(1, 1)),
    $call(static fn () => current_user_can_for_blog(1, 'edit_posts')), $call(static fn () => current_user_can_for_blog(1, 'zz_no_such_cap')), $call(static fn () => current_user_can_for_blog(1, 'edit_post', $second)),
]);

$say('old links and rel tags', [
    $call(static fn () => get_index_rel_link()), $call(static fn () => index_rel_link()), $call(static fn () => start_post_rel_link('%title', true)), $call(static fn () => start_post_rel_link('Start: %title', true, (string) $category)),
    $call(static fn () => comments_rss()), $call(static fn () => permalink_link()), $call(static fn () => permalink_single_rss()), $call(static fn () => get_shortcut_link()), $call(static fn () => get_link(999999)),
]);

update_option('blog_public', '1');
$robots = [$call(static fn () => noindex()), $call(static fn () => wp_no_robots()), $call(static fn () => wp_sensitive_page_meta())];
update_option('blog_public', '0');
$robots[] = $call(static fn () => noindex());
$robots[] = $call(static fn () => wp_no_robots());
update_option('blog_public', $options['blog_public'] ?? '1');
$say('robots tags', $robots);

$say('page by title', [
    $call(static fn () => get_page_by_title('zz dp page')?->ID), $call(static fn () => array_keys((array) get_page_by_title('zz dp page', ARRAY_A))), $call(static fn () => get_page_by_title('zz dp first', OBJECT, 'post')?->ID),
    $call(static fn () => get_page_by_title('zz dp first')), $call(static fn () => get_page_by_title('zz dp nothing')), $call(static fn () => get_page_by_title('zz dp first', ARRAY_N, ['page', 'post']) === null ? null : 'found'),
]);

update_option('disallowed_keys', "zzbadword\n10.9.8.7");
$say('the disallowed list', [
    $call(static fn () => wp_blacklist_check('Zz', 'zz@example.com', '', 'a fine comment', '127.0.0.1', 'agent')), $call(static fn () => wp_blacklist_check('Zz', 'zz@example.com', '', 'with zzbadword inside', '127.0.0.1', 'agent')),
    $call(static fn () => wp_blacklist_check('Zz', 'zz@example.com', '', 'fine', '10.9.8.7', 'agent')),
]);
update_option('disallowed_keys', $options['disallowed_keys'] ?? '');

$say('the loading attribute default', [
    $call(static fn () => wp_get_loading_attr_default('the_content')), $call(static fn () => wp_get_loading_attr_default('wp_get_attachment_image')), $call(static fn () => wp_get_loading_attr_default('template_part_header')),
    $call(static fn () => wp_get_loading_attr_default('zz_context')), $call(static fn () => wp_get_loading_attr_default('the_post_thumbnail')),
]);

$say('small helpers', [
    $call(static fn () => addslashes_strings_only("it's \"quoted\"")), $call(static fn () => addslashes_strings_only(5)), $call(static fn () => addslashes_strings_only(['it\'s'])), $call(static fn () => addslashes_strings_only(null)),
    $call(static fn () => like_escape('50% of a_b \\ c')), $call(static fn () => wp_timezone_supported()), $call(static fn () => gzip_compression()), $call(static fn () => get_autotoggle(3)),
    $call(static fn () => is_comments_popup()), $call(static fn () => is_plugin_page()), $call(static fn () => get_comments_popup_template()), $call(static fn () => get_paged_template()), $call(static fn () => rich_edit_exists()),
]);

$say('the ones that do nothing now', [
    $call(static fn () => _save_post_hook()), $call(static fn () => comments_popup_script()), $call(static fn () => preview_theme()), $call(static fn () => wp_unregister_GLOBALS()),
    $call(static fn () => wlwmanifest_link()), $call(static fn () => wp_print_auto_sizes_contain_css_fix()), $call(static fn () => the_block_template_skip_link()),
]);

$say('styles printed the old way', [$call(static fn () => print_emoji_styles()), $call(static fn () => print_emoji_styles()), $call(static fn () => print_embed_styles())]);

$say('theme support taken away', [$call(static fn () => remove_custom_background()), $call(static fn () => remove_custom_image_header()), current_theme_supports('custom-background'), current_theme_supports('custom-header')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
