<?php
/**
 * The functions the most-installed fleet plugins call that were still
 * placeholders: what each answers on the reference. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace([$home, str_replace('https://', 'http://', $home)], '{home}', $v) : $v;
wp_set_current_user(1);

// --- sanitize_sql_orderby: a list of columns and directions, or false.
foreach (['post_date DESC', 'post_title ASC, ID DESC', 'RAND()', 'rand(5)', 'meta_value+0 DESC', 'field(ID,1,2)', 'post_date; DROP TABLE x', 'ID', 'wp_posts.ID desc', '', 'post_date DESC LIMIT 1', 'CAST(meta_value AS SIGNED)', '`post_date` ASC', 'ID desc', 'wp_posts.ID', 'rand()', 'post_date  DESC', ' post_date', 'post_date DESC,', 'menu_order ASC, post_title', 'RAND(), ID', 'post-date', 'ID ASC DESC'] as $i => $orderby) {
    $say("sanitize_sql_orderby #{$i}", sanitize_sql_orderby($orderby));
}

// --- is_protected_meta: an underscore makes a key protected, and the filter may say otherwise.
foreach (['_edit_lock', 'color', ' _spaced', '__double', '', "\x01_ctrl", '_'] as $i => $key) {
    $say("is_protected_meta #{$i}", is_protected_meta($key, 'post'));
}
$protect = static fn ($protected, $key, $type) => $key === 'color' ? [$type] : $protected;
add_filter('is_protected_meta', $protect, 10, 3);
$say('is_protected_meta filtered', [is_protected_meta('color', 'user'), is_protected_meta('_x', 'user')]);
remove_filter('is_protected_meta', $protect, 10);

// --- wp_removable_query_args and the image sizes plugins registered.
$say('wp_removable_query_args', wp_removable_query_args());
$removable = static fn ($args) => array_merge(array_slice($args, 0, 1), ['zz-mine']);
add_filter('removable_query_args', $removable);
$say('wp_removable_query_args filtered', wp_removable_query_args());
remove_filter('removable_query_args', $removable);
add_image_size('zz-probe-size', 321, 123, true);
$say('wp_get_additional_image_sizes', wp_get_additional_image_sizes()['zz-probe-size'] ?? null);
$say('_wp_additional_image_sizes global', $GLOBALS['_wp_additional_image_sizes']['zz-probe-size'] ?? null);
remove_image_size('zz-probe-size');
$say('wp_get_additional_image_sizes removed', array_key_exists('zz-probe-size', wp_get_additional_image_sizes()));

// --- wp_get_split_term: false unless the term was split.
$say('wp_get_split_term none', wp_get_split_term(1, 'category'));
$splits = get_option('_split_terms', 'MISSING');
update_option('_split_terms', [5 => ['category' => 9]]);
$say('wp_get_split_term recorded', [wp_get_split_term(5, 'category'), wp_get_split_term(5, 'post_tag'), wp_get_split_term('5', 'category')]);
$splits === 'MISSING' ? delete_option('_split_terms') : update_option('_split_terms', $splits);

// --- get_user_setting: the user's settings, from the cookie or the stored option.
delete_user_option(1, 'user-settings');
$say('get_user_setting none', get_user_setting('editor', 'tinymce'));
update_user_option(1, 'user-settings', 'editor=html&mfold=o');
update_user_option(1, 'user-settings-time', time());
$_COOKIE = [];
$say('get_user_setting stored', [get_user_setting('editor'), get_user_setting('mfold'), get_user_setting('missing', 'dflt')]);
$say('get_all_user_settings', get_all_user_settings());
// The first read is kept for the request; a fresh request reads the stored option, then the cookie.
unset($GLOBALS['_updated_user_settings']);
$say('get_all_user_settings fresh', get_all_user_settings());
$say('get_user_setting fresh', [get_user_setting('editor'), get_user_setting('mfold'), get_user_setting('missing', 'dflt')]);
unset($GLOBALS['_updated_user_settings']);
$_COOKIE['wp-settings-1'] = 'editor=tinymce&x<y>=1&z=2"';
$say('get_all_user_settings cookie', get_all_user_settings());
unset($GLOBALS['_updated_user_settings']);
$_COOKIE['wp-settings-1'] = 'nothing';
$say('get_all_user_settings cookie without pairs', get_all_user_settings());
unset($GLOBALS['_updated_user_settings']);
$_COOKIE = [];
wp_set_current_user(0);
$say('get_all_user_settings signed out', [get_all_user_settings(), get_user_setting('editor', 'd')]);
wp_set_current_user(1);
unset($GLOBALS['_updated_user_settings']);
delete_user_option(1, 'user-settings');
delete_user_option(1, 'user-settings-time');

// --- sanitize_post: each field through its filters for the context.
$post = get_post(1);
foreach (['raw', 'edit', 'db', 'display', 'attribute', 'js'] as $context) {
    $clean = sanitize_post(get_post(1), $context);
    $say("sanitize_post {$context}", is_object($clean) ? [get_class($clean), $clean->filter, $clean->post_title, $clean->ID, gettype($clean->ID)] : gettype($clean));
}
$say('sanitize_post array', (function () use ($post) { $a = sanitize_post($post->to_array(), 'edit'); return [$a['filter'], gettype($a['ID']), $a['post_title']]; })());
$say('sanitize_post_field title display', sanitize_post_field('post_title', 'A "quote" & <b>tag</b>', 1, 'display'));
$say('sanitize_post_field title edit', sanitize_post_field('post_title', 'A "quote" & <b>tag</b>', 1, 'edit'));
$say('sanitize_post_field title attribute', sanitize_post_field('post_title', 'A "quote" & <b>tag</b>', 1, 'attribute'));
$say('sanitize_post_field ID raw', sanitize_post_field('ID', '12', 1, 'raw'));
$say('sanitize_post_field title js', sanitize_post_field('post_title', 'A "quote" & <b>tag</b>\'s', 1, 'js'));
$say('sanitize_post_field content edit', sanitize_post_field('post_content', '<p>a & "b"</p>', 1, 'edit'));
$say('sanitize_post_field excerpt edit', sanitize_post_field('post_excerpt', '<p>a & "b"</p>', 1, 'edit'));
$say('sanitize_post_field status attribute', sanitize_post_field('post_status', 'publish"', 1, 'attribute'));
$say('sanitize_post_field menu_order edit', sanitize_post_field('menu_order', '3', 1, 'edit'));

// --- The hooks each context runs: a column named post_* and one without the prefix name theirs differently.
$fired = [];
$recorder = static function (string $hook) use (&$fired): void {
    $fired[] = $hook;
};
foreach (['post_title', 'post_content', 'comment_status', 'ID'] as $field) {
    foreach (['raw', 'edit', 'db', 'display', 'attribute', 'js'] as $context) {
        $fired = [];
        add_action('all', $recorder);
        sanitize_post_field($field, 'x', 1, $context);
        remove_action('all', $recorder);
        $bare = str_starts_with($field, 'post_') ? substr($field, 5) : $field;
        $say("sanitize_post_field hooks {$field} {$context}", array_values(array_filter($fired, static fn ($h) => str_contains($h, $bare) || $h === 'user_can_richedit')));
    }
}
foreach (['post_excerpt', 'post_password', 'comment_status', 'guid', 'post_name', 'post_mime_type'] as $field) {
    $say("sanitize_post_field {$field} edit", sanitize_post_field($field, 'a "b" <c> & d', 1, 'edit'));
}
foreach (['post_title', 'post_content', 'post_excerpt', 'post_password', 'comment_status', 'post_name'] as $field) {
    $say("sanitize_post_field {$field} edit with an entity", sanitize_post_field($field, 'a &amp; b', 1, 'edit'));
}
add_filter('user_can_richedit', '__return_true');
$say('sanitize_post_field content edit with the rich editor', sanitize_post_field('post_content', '<p>a & "b"</p>', 1, 'edit'));
remove_filter('user_can_richedit', '__return_true');

// --- get_post hands out a fresh object; changing one leaves the next alone.
$first = get_post(1);
$first->post_title = 'zz changed';
$say('get_post fresh object', [get_post(1)->post_title, get_post(1) === get_post(1)]);
$edit = get_post(1, OBJECT, 'edit');
$say('get_post edit filter', [$edit->filter, get_post(1)->filter, get_post(1)->filter('display')->filter, get_post(1)->filter('raw')->filter]);

// --- The archive titles, on an archive and off it.
$tag = get_terms(['taxonomy' => 'post_tag', 'number' => 1, 'hide_empty' => false])[0] ?? null;
if ($tag) {
    $GLOBALS['wp_query'] = new WP_Query(['tag_id' => $tag->term_id]);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $say('single_tag_title on a tag archive', [single_tag_title('', false), single_tag_title('Tag: ', false)]);
    ob_start();
    single_tag_title('>');
    $say('single_tag_title printed', ob_get_clean());
}
$GLOBALS['wp_query'] = new WP_Query(['year' => 2026, 'monthnum' => 8]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('single_month_title on a month archive', [single_month_title('', false), single_month_title(' - ', false)]);
$GLOBALS['wp_query'] = new WP_Query(['post_type' => 'post', 'p' => 1]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('archive titles off an archive', [single_tag_title('', false), single_month_title('', false), post_type_archive_title('', false)]);
$say('term titles off an archive', [single_cat_title('', false), single_term_title('', false)]);
$GLOBALS['wp_query'] = new WP_Query(['m' => '202608']);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('single_month_title by m', [single_month_title('', false), single_month_title(' ', false)]);
ob_start();
$printed = single_month_title('|');
$say('single_month_title printed', [ob_get_clean(), $printed]);
$GLOBALS['wp_query'] = new WP_Query(['year' => 2026]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('single_month_title on a year archive', single_month_title('', false));
register_post_type('zzprobe', ['public' => true, 'has_archive' => true, 'labels' => ['name' => 'Probes', 'singular_name' => 'Probe']]);
$GLOBALS['wp_query'] = new WP_Query(['post_type' => 'zzprobe']);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$retitle = static fn ($title, $type) => "{$title} ({$type})";
$say('post_type_archive_title on an archive', [is_post_type_archive(), post_type_archive_title('', false), post_type_archive_title('All ', false)]);
add_filter('post_type_archive_title', $retitle, 10, 2);
ob_start();
$printed = post_type_archive_title('>');
$say('post_type_archive_title printed', [ob_get_clean(), $printed]);
remove_filter('post_type_archive_title', $retitle, 10);
$GLOBALS['wp_query'] = new WP_Query(['post_type' => ['post', 'zzprobe']]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('post_type_archive_title over two types', [is_post_type_archive(), post_type_archive_title('', false)]);
unregister_post_type('zzprobe');

// --- get_users narrows; it never widens to every account.
$logins = static fn (array $users): array => array_map(static fn ($u) => is_object($u) ? $u->user_login : $u, $users);
foreach ([
    'search with a trailing star' => ['search' => 'edi*'],
    'search with a leading star' => ['search' => '*ibe'],
    'search exact' => ['search' => 'admin'],
    'search for nobody' => ['search' => 'zz-nobody-*'],
    'search in the login column' => ['search' => 'scr*', 'search_columns' => ['user_login']],
    'search by email' => ['search' => '*@minn-engine.localhost'],
    'role' => ['role' => 'editor'],
    'include' => ['include' => [1, 3]],
    'exclude' => ['exclude' => [1]],
    'number' => ['number' => 2],
    'fields ID' => ['fields' => 'ID'],
    'orderby ID desc' => ['orderby' => 'ID', 'order' => 'DESC'],
] as $label => $query) {
    $say("get_users {$label}", $logins(get_users($query)));
}

// --- _prime_post_caches answers nothing; it only warms the cache.
$say('_prime_post_caches', _prime_post_caches([1, 2]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
