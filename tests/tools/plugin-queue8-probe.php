<?php
/**
 * The eighth wave of the plugin catalogue's queue (probe plugin-queue8), as
 * the reference answers it: the last modifier, durations read, the old
 * escapes, a post's pings, the custom CSS printed, a revision's version,
 * the old user globals, category ids, shortcodes in content, password
 * resets allowed, starter content, the theme's templates and parts, the
 * sitemap flag, users of the blog and their post counts, page paths, tax
 * SQL, a video embedded by address, a callback's plugin, the all hook, a
 * post's parent, users without a role, comment caches, trackback lists,
 * block template info, template paths, the font folder, theme features,
 * script concatenation, WebP facts, file signatures, meta rows, the old
 * widget registrations, object validation, and magic quotes last.
 * Everything made is removed or put back at the end. Same protocol as
 * api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$mods = get_option('theme_mods_' . get_stylesheet());
$base = sys_get_temp_dir() . '/zz-queue8-' . getmypid();
register_shutdown_function(static function () use (&$made, $mods, $base): void {
    foreach (array_reverse($made) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    // The theme's mods as they were, whether or not the run got that far.
    if ($mods === false) {
        delete_option('theme_mods_' . get_stylesheet());
    } else {
        update_option('theme_mods_' . get_stylesheet(), $mods);
    }
    foreach (glob($base . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($base);
});
@mkdir($base, 0755, true);
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING);
if (!did_action('init')) {
    do_action('init');
}
foreach (['get_meta_keys' => 'post.php', 'get_theme_feature_list' => 'theme.php', 'verify_file_signature' => 'file.php'] as $function => $file) {
    if (!function_exists($function)) {
        require_once ABSPATH . 'wp-admin/includes/' . $file;
    }
}
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    if (!in_array([$function, $replacement, $version], $deprecated, true)) {
        $deprecated[] = [$function, $replacement, $version];
    }
}, 10, 3);
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host, $base) {
    if (is_wp_error($value)) {
        return ['error' => $value->get_error_code()];
    }
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace(['/private' . $base, $base, get_stylesheet_directory(), WP_CONTENT_DIR, ABSPATH, 'https://' . $host, 'http://' . $host, $GLOBALS['wpdb']->prefix], ['{base}', '{base}', '{theme-dir}', '{content}', '{abspath}/', '{home}', '{home}', '{prefix}'], $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (array $fields, string $label) use (&$made, &$ids): int {
    $id = wp_insert_post($fields + ['post_status' => 'publish', 'post_type' => 'post']);
    $id = is_int($id) ? $id : 0;
    if ($id > 0) {
        $made[] = $id;
        $ids[$id] = $label;
    }
    return $id;
};

// The last modifier.
$edited = $post(['post_title' => 'ZZ Queue Eight', 'to_ping' => "http://a.example/\nhttp://b.example/", 'pinged' => "http://c.example/"], 'post');
update_post_meta($edited, '_edit_last', 2);
$plain = $post(['post_title' => 'ZZ Queue Eight Plain'], 'plain');
$GLOBALS['post'] = get_post($edited);
$heard = null;
$spy = static function ($name) use (&$heard) {
    $heard = $name;
    return $name . ' [zz]';
};
add_filter('the_modified_author', $spy);
ob_start();
the_modified_author();
$say('the modified author', [get_the_modified_author(), ob_get_clean(), $heard]);
remove_filter('the_modified_author', $spy);
$GLOBALS['post'] = get_post($plain);
$say('the modified author of a post nobody edited', $try(static fn () => get_the_modified_author()));
unset($GLOBALS['post']);

// Durations, old escapes, pings.
$say('human_readable_duration', array_map(static fn ($d) => $try(static fn () => human_readable_duration($d)), ['3:05', '01:02:03', '0:00', '59', '1:00:00:00', 'abc', '', '10:61', '-3:05', '1:01', '2:00:01']));
$say('attribute_escape and clean_url', [attribute_escape('<"x"> & \'y\''), clean_url('javascript:alert(1)'), clean_url('example.com/a b?c=<d>'), clean_url('https://example.com/?a=1&b=2', null, 'db')]);
$say('get_pung and get_to_ping', [get_pung($edited), get_to_ping($edited), get_pung($plain), get_to_ping($plain), $try(static fn () => get_pung(999999999))]);
$say('sanitize_trackback_urls', sanitize_trackback_urls("http://a.example/\nnot a url\n  http://b.example/  \nftp://c.example/\n\nhttps://d.example/x?y=1 https://e.example/"));

// The custom CSS.
$saved = wp_update_custom_css_post('.zz-custom { color: red; }');
if (is_object($saved) && isset($saved->ID)) {
    $made[] = (int) $saved->ID;
    $ids[(int) $saved->ID] = 'css';
}
ob_start();
wp_custom_css_cb();
$say('wp_custom_css_cb', $mask(ob_get_clean()));

// Revisions, users, categories, shortcodes.
$say('_wp_get_post_revision_version', array_map(static fn ($name) => _wp_get_post_revision_version(new WP_Post((object) ['ID' => 5, 'post_name' => $name, 'post_type' => 'revision'])), ['12-revision-v1', '12-revision-v7', '12-autosave-v1', '12-revision', 'zz']));
setup_userdata(2);
$say('setup_userdata', [$GLOBALS['user_login'] ?? null, $GLOBALS['user_ID'] ?? null, $GLOBALS['user_email'] ?? null, $GLOBALS['user_identity'] ?? null, $GLOBALS['user_level'] ?? null, isset($GLOBALS['userdata'])]);
setup_userdata(999999999);
$say('setup_userdata of nobody', [$GLOBALS['user_login'] ?? null, $GLOBALS['user_ID'] ?? null]);
$say('get_all_category_ids', array_map('intval', (array) get_all_category_ids()));
$say('get_shortcode_tags_in_content', [get_shortcode_tags_in_content('[gallery] [caption x="1"]a[/caption] [zz-nope] [[audio]] [audio /] [gallery]'), get_shortcode_tags_in_content('no codes'), get_shortcode_tags_in_content('')]);
$say('wp_is_password_reset_allowed_for_user', $mask([wp_is_password_reset_allowed_for_user(get_userdata(2)), $try(static fn () => wp_is_password_reset_allowed_for_user(null)), $try(static function () {
    add_filter('allow_password_reset', '__return_false');
    $allowed = wp_is_password_reset_allowed_for_user(get_userdata(2));
    remove_filter('allow_password_reset', '__return_false');
    return $allowed;
})]));
$say('get_currentuserinfo', $try(static function () {
    $user = get_currentuserinfo();
    return [is_object($user) ? get_class($user) : $user, is_object($user) ? (int) $user->ID : null];
}));

// The theme's data.
$say('get_theme_starter_content', array_keys((array) get_theme_starter_content()));
$say('wp_get_theme_data_custom_templates', wp_get_theme_data_custom_templates());
$say('wp_get_theme_data_template_parts', wp_get_theme_data_template_parts());
$say('is_sitemap', is_sitemap());
$say('get_theme_feature_list', $try(static function () {
    return get_theme_feature_list(false);
}));

// Users of the blog and their posts.
$say('get_users_of_blog', $try(static function () {
    $users = get_users_of_blog();
    return [count($users), array_map(static fn ($u) => (int) $u->user_id, $users), array_keys((array) ($users[0] ?? []))];
}));
$say('count_many_users_posts', [count_many_users_posts([1, 2, 3, 999999999]), count_many_users_posts([1, 2], 'page'), count_many_users_posts([1], 'post', true), count_many_users_posts([])]);
$say('wp_get_users_with_no_role', $try(static fn () => array_map('intval', (array) wp_get_users_with_no_role())));

// Pages, tax SQL, embeds, callbacks, hooks.
$hierarchy = [(object) ['ID' => 2, 'post_name' => 'sample-page', 'post_parent' => 0], (object) ['ID' => 6, 'post_name' => 'docs', 'post_parent' => 2], (object) ['ID' => 7, 'post_name' => 'deep', 'post_parent' => 6], (object) ['ID' => 9, 'post_name' => 'lost', 'post_parent' => 99]];
$say('get_page_hierarchy', [get_page_hierarchy($hierarchy), (function () {
    $pages = [];
    return get_page_hierarchy($pages);
})()]);
$say('get_tax_sql', $mask([get_tax_sql([['taxonomy' => 'category', 'terms' => [1]]], $wpdb->posts, 'ID'), get_tax_sql([], $wpdb->posts, 'ID'), get_tax_sql(['relation' => 'OR', ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['zz-nope']], ['taxonomy' => 'category', 'operator' => 'EXISTS']], $wpdb->posts, 'ID')]));
$videoSpy = static fn ($video, $attr, $url, $rawattr) => $video . '<!-- zz ' . $url . ' ' . count($attr) . ' -->';
add_filter('wp_embed_handler_video', $videoSpy, 10, 4);
$say('wp_embed_handler_video', $mask([wp_embed_handler_video(['https://x.example/v.mp4'], [], 'https://x.example/v.mp4', []), wp_embed_handler_video(['https://x.example/w.webm'], ['width' => 300, 'height' => 200], 'https://x.example/w.webm', ['width' => 300])]));
remove_filter('wp_embed_handler_video', $videoSpy, 10);
$say('_get_plugin_from_callback', [_get_plugin_from_callback('wp_head'), _get_plugin_from_callback('strlen'), _get_plugin_from_callback(static fn () => 1), _get_plugin_from_callback(['WP_Query', 'get'])]);
$allHeard = [];
$allSpy = static function (...$args) use (&$allHeard) {
    $allHeard[] = $args;
};
add_action('all', $allSpy);
_wp_call_all_hook(['zz_hook', 1, 'two']);
remove_action('all', $allSpy);
$say('_wp_call_all_hook', $allHeard);
$say('has_post_parent', [has_post_parent(6), has_post_parent(2), has_post_parent(999999999), has_post_parent()]);
$say('_prime_comment_caches', [_prime_comment_caches([1]), _prime_comment_caches([], false)]);
$say('wp_get_user_request_data', $try(static fn () => wp_get_user_request_data(0)));

// Block templates and fonts.
$say('_add_block_template_info', $mask([_add_block_template_info(['slug' => 'page-no-title', 'path' => 'x', 'theme' => 't', 'type' => 'wp_template']), _add_block_template_info(['slug' => 'index', 'path' => 'x', 'theme' => 't', 'type' => 'wp_template'])]));
$say('_add_block_template_part_area_info', $mask([_add_block_template_part_area_info(['slug' => 'header', 'path' => 'x', 'theme' => 't', 'type' => 'wp_template_part']), _add_block_template_part_area_info(['slug' => 'zz-nope', 'path' => 'x', 'theme' => 't', 'type' => 'wp_template_part'])]));
$paths = (array) _get_block_templates_paths(get_stylesheet_directory() . '/templates');
sort($paths);
$say('_get_block_templates_paths', [$mask($paths), _get_block_templates_paths($base . '/nope')]);
$say('wp_get_font_dir', $mask([wp_get_font_dir(), $try(static function () {
    $moved = static fn ($dir) => array_merge($dir, ['path' => $dir['path'] . '-zz', 'url' => $dir['url'] . '-zz']);
    add_filter('font_dir', $moved);
    $dir = wp_get_font_dir();
    remove_filter('font_dir', $moved);
    return $dir;
})]));

// Scripts, images, signatures, meta.
script_concat_settings();
$say('script_concat_settings', [$GLOBALS['concatenate_scripts'] ?? null, $GLOBALS['compress_scripts'] ?? null, $GLOBALS['compress_css'] ?? null]);
$image = imagecreatetruecolor(30, 20);
imagewebp($image, $base . '/zz.webp');
imagewebp($image, $base . '/zz-lossless.webp', IMG_WEBP_LOSSLESS);
$alpha = imagecreatetruecolor(41, 17);
imagesavealpha($alpha, true);
imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 10, 20, 30, 64));
imagewebp($alpha, $base . '/zz-alpha.webp');
imagepng($image, $base . '/zz.png');
$say('wp_get_webp_info', [wp_get_webp_info($base . '/zz.webp'), wp_get_webp_info($base . '/zz-lossless.webp'), wp_get_webp_info($base . '/zz-alpha.webp'), wp_get_webp_info($base . '/zz.png'), wp_get_webp_info($base . '/nope.webp')]);
file_put_contents($base . '/zz.zip', 'not a zip');
$say('verify_file_signature', $mask([verify_file_signature($base . '/zz.zip', [], 'zz.zip'), verify_file_signature($base . '/zz.zip', ['not-base64!'], 'zz.zip'), verify_file_signature($base . '/zz.zip', base64_encode(str_repeat('a', 64)), 'zz.zip')]));
$mid = add_post_meta($edited, 'zz_queue_eight', 'v');
$say('get_meta_keys', [in_array('zz_queue_eight', (array) get_meta_keys(), true), in_array('_edit_last', (array) get_meta_keys(), true)]);
$say('delete_meta', [delete_meta($mid), get_post_meta($edited, 'zz_queue_eight', true), delete_meta(999999999)]);

// The old widget registrations.
global $wp_registered_widgets, $wp_registered_widget_controls;
register_sidebar_widget('ZZ Old Widget', 'zz_old_widget_cb', 'zz-class');
register_widget_control('ZZ Old Widget', 'zz_old_widget_control', 300, 200);
$widget = $wp_registered_widgets['zz-old-widget'] ?? null;
$control = $wp_registered_widget_controls['zz-old-widget'] ?? null;
$say('register_sidebar_widget', $widget === null ? null : [$widget['name'], $widget['id'], $widget['callback'], $widget['classname'] ?? null]);
$say('register_widget_control', $control === null ? null : [$control['name'], $control['id'], $control['callback'], $control['width'] ?? null, $control['height'] ?? null]);
wp_unregister_sidebar_widget('zz-old-widget');

// Object validation.
$schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'integer', 'required' => true]], 'additionalProperties' => false];
$say('rest_validate_object_value_from_schema', $mask([
    rest_validate_object_value_from_schema(['a' => 'x', 'b' => 1], $schema, 'p'),
    rest_validate_object_value_from_schema(['a' => 'x'], $schema, 'p'),
    rest_validate_object_value_from_schema(['b' => 1, 'c' => 2], $schema, 'p'),
    rest_validate_object_value_from_schema('nope', $schema, 'p'),
    rest_validate_object_value_from_schema((object) ['b' => 2], $schema, 'p'),
    rest_validate_object_value_from_schema(['b' => 1, 'x1' => 'y'], ['type' => 'object', 'patternProperties' => ['^x\d$' => ['type' => 'integer']]], 'p'),
    rest_validate_object_value_from_schema([], ['type' => 'object', 'minProperties' => 1], 'p'),
]));

// Magic quotes, last: they slash the request globals.
$get = $_GET;
$postGlobals = $_POST;
$request = $_REQUEST;
$_GET['zz_q'] = "a'b";
$_POST['zz_p'] = 'c"d';
wp_magic_quotes();
$say('wp_magic_quotes', [$_GET['zz_q'], $_POST['zz_p'], $_REQUEST['zz_q'] ?? null, $_REQUEST['zz_p'] ?? null]);
$_GET = $get;
$_POST = $postGlobals;
$_REQUEST = $request;

$say('deprecated, as reported', $deprecated);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
