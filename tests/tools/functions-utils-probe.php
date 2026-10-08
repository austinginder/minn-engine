<?php
/**
 * functions.php's small utilities (probe functions-utils): the WP_HOME and
 * WP_SITEURL option filters, the JSON encoder's helpers, the unique-filename
 * helpers, the hierarchy-loop finders, smilies_init's globals, the dirsize
 * cache, the HTTPS-update links and the PHP update annotation, the
 * admin-email change notice (caught at pre_wp_mail), and the small checks
 * (cache ids, lighttpd, site meta, UTF-8 charsets, HEIC types). The
 * transient it touches is put back at shutdown. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// A doing_it_wrong or deprecation notice reads as its function name.
$notices = [];
$hear = static function (...$args) use (&$notices) { $notices[] = $args; };
add_action('doing_it_wrong_run', $hear, 10, 3);
add_action('deprecated_function_run', $hear, 10, 3);
$dirsize = get_transient('dirsize_cache');
register_shutdown_function(static function () use ($dirsize): void {
    $dirsize === false ? delete_transient('dirsize_cache') : set_transient('dirsize_cache', $dirsize);
});

$say('validate cache id', array_map(static function ($id) use (&$notices) {
    $notices = [];
    return [$id, _validate_cache_id($id), $notices];
}, [5, '7', '7a', 0, -3, 1.5, '', null, true, [1], '0012']));

// The JSON helpers: what a value becomes on its way to json_encode.
$say('json convert string', array_map(static fn ($s) => bin2hex((string) _wp_json_convert_string($s)), ['plain', "caf\xC3\xA9", "bad \xE9 byte", '', "\xFF\xFE"]));
$notices = [];
$say('json prepare data', [_wp_json_prepare_data(['a' => 1]), _wp_json_prepare_data('x'), _wp_json_prepare_data(null), $notices]);
$sanity = static function ($value, $depth) {
    try {
        return ['ok', json_decode(json_encode(_wp_json_sanity_check($value, $depth), JSON_INVALID_UTF8_SUBSTITUTE), true)];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage()];
    }
};
$say('json sanity check', [
    $sanity(['a' => "ok", 'b' => ["deep" => "caf\xC3\xA9"]], 512),
    $sanity(['bad' => "x \xE9 y", "k\xE9y" => 1], 512),
    $sanity((object) ['p' => "x \xE9"], 512),
    $sanity([[['too' => 'deep']]], 1),
    $sanity("x \xE9", 512),
    $sanity(42, 512),
]);

// The unique-filename helpers.
$say('check existing file names', [
    _wp_check_existing_file_names('photo.jpg', ['photo-150x150.jpg', 'other.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['other.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['photo-scaled.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['photo-1-150x150.jpg']),
    _wp_check_existing_file_names('doc.pdf', ['doc-pdf.jpg', 'doc-150x150.jpg']),
    _wp_check_existing_file_names('photo.jpg', []),
    _wp_check_existing_file_names('photo.jpg', ['photo-e1700000000000.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['photo-rotated.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['photo.jpg']),
    _wp_check_existing_file_names('photo.jpg', ['PHOTO-150X150.JPG']),
    _wp_check_existing_file_names('photo.jpg', ['photo-150x150.png']),
    _wp_check_existing_file_names('photo.jpg', ['photo-150x150.webp']),
    _wp_check_existing_file_names('my.photo.jpg', ['my.photo-300x200.jpg']),
    _wp_check_existing_file_names('noext', ['noext-150x150']),
]);
$dir = sys_get_temp_dir() . '/zz-functions-utils-probe';
@mkdir($dir);
touch($dir . '/taken.webp');
$say('check alternate file names', [
    _wp_check_alternate_file_names(['taken.webp'], $dir, []),
    _wp_check_alternate_file_names(['free.webp'], $dir, []),
    _wp_check_alternate_file_names(['free.webp'], $dir, ['free-150x150.webp']),
    _wp_check_alternate_file_names([], $dir, []),
    _wp_check_alternate_file_names(['free.avif', 'taken.webp'], $dir, []),
    _wp_check_alternate_file_names(['taken.webp'], $dir . '/', []),
    _wp_check_alternate_file_names(['free.avif', 'taken.webp'], $dir . '/', []),
]);
@unlink($dir . '/taken.webp');
@rmdir($dir);

// The hierarchy-loop finders over a parent map.
$parents = ['a' => 'b', 'b' => 'c', 'c' => 'a', 'd' => 'e', 'e' => '', 'f' => 'f', 'g' => 'h', 'h' => 'i', 'i' => 'h'];
$parent = static function ($id, $map = null) use ($parents) {
    $map ??= $parents;
    return $map[$id] ?? null;
};
$hierarchy = [];
foreach ([['a', 'b'], ['d', 'e'], ['f', 'f'], ['g', 'h'], ['x', 'a'], ['e', 'd']] as [$start, $startParent]) {
    $hierarchy[] = [$start, $startParent, wp_find_hierarchy_loop($parent, $start, $startParent), wp_find_hierarchy_loop_tortoise_hare($parent, $start), wp_find_hierarchy_loop_tortoise_hare($parent, $start, [], [], true)];
}
$hierarchy[] = ['override', wp_find_hierarchy_loop_tortoise_hare($parent, 'd', ['e' => 'd']), wp_find_hierarchy_loop_tortoise_hare($parent, 'd', ['e' => 'd'], [], true)];
$hierarchy[] = ['callback args', wp_find_hierarchy_loop($parent, 'q', 'r', [['q' => 'r', 'r' => 's', 's' => 'q']])];
$say('hierarchy loops', $hierarchy);

// smilies_init's two globals.
unset($GLOBALS['wpsmiliestrans'], $GLOBALS['wp_smiliessearch']);
smilies_init();
$say('smilies init', [count((array) ($GLOBALS['wpsmiliestrans'] ?? [])), array_slice((array) ($GLOBALS['wpsmiliestrans'] ?? []), 0, 6, true), $GLOBALS['wp_smiliessearch'] ?? null]);
unset($GLOBALS['wpsmiliestrans'], $GLOBALS['wp_smiliessearch']);
add_filter('smilies', $s = static fn ($table) => ['zz)' => 'zz.png', ':zz' => 'zz2.png', ':z' => 'z.png']);
smilies_init();
$say('smilies init, filtered', [$GLOBALS['wpsmiliestrans'] ?? null, $GLOBALS['wp_smiliessearch'] ?? null]);
remove_filter('smilies', $s);
unset($GLOBALS['wpsmiliestrans'], $GLOBALS['wp_smiliessearch']);
add_filter('pre_option_use_smilies', '__return_zero');
smilies_init();
$say('smilies init, smilies off', [isset($GLOBALS['wpsmiliestrans']), $GLOBALS['wp_smiliessearch'] ?? 'unset']);
remove_filter('pre_option_use_smilies', '__return_zero');
unset($GLOBALS['wpsmiliestrans'], $GLOBALS['wp_smiliessearch']);
smilies_init();

// The dirsize cache: an entry and every folder above it go; the rest stay.
set_transient('dirsize_cache', ['/srv/a' => 10, '/srv/a/b' => 4, '/srv/a/b/c' => 1, '/srv/x' => 7, '/srv' => 30]);
clean_dirsize_cache('/srv/a/b');
$after = get_transient('dirsize_cache');
ksort($after);
set_transient('dirsize_cache', ['/srv/a' => 10]);
clean_dirsize_cache('/srv/zz');
$untouched = get_transient('dirsize_cache');
$say('clean dirsize cache', [$after, $untouched]);

$software = $_SERVER['SERVER_SOFTWARE'] ?? null;
$lighttpd = [];
foreach (['lighttpd/1.4.59', 'lighttpd/1.5.0', 'lighttpd/1.4', 'nginx/1.25', 'Apache', '', 'lighttpd'] as $value) {
    $_SERVER['SERVER_SOFTWARE'] = $value;
    $lighttpd[] = [$value, is_lighttpd_before_150()];
}
$software === null ? $_SERVER['SERVER_SOFTWARE'] = '' : $_SERVER['SERVER_SOFTWARE'] = $software;
$say('lighttpd before 1.5', $lighttpd);
$say('site meta supported', [is_site_meta_supported()]);
$say('utf-8 charset', array_map(static fn ($c) => [$c, is_utf8_charset($c)], ['UTF-8', 'utf-8', 'utf8', 'UTF8', 'utf-16', 'ISO-8859-1', '', null]));
$say('heic types', array_map(static fn ($m) => [$m, wp_is_heic_image_mime_type($m)], ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence', 'image/avif', 'IMAGE/HEIC', '']));

// The HTTPS-update links and the PHP annotation.
$say('update https urls', [wp_get_default_update_https_url(), wp_get_direct_update_https_url(), wp_get_update_https_url(), wp_get_update_php_annotation()]);
add_filter('wp_update_https_url', $u = static fn () => 'https://host.example/https');
add_filter('wp_direct_update_https_url', $d = static fn () => 'https://host.example/direct');
add_filter('wp_update_php_url', $p = static fn () => 'https://host.example/php');
$say('update https urls, filtered', [wp_get_update_https_url(), wp_get_direct_update_https_url(), wp_get_update_php_annotation()]);
remove_filter('wp_update_https_url', $u);
remove_filter('wp_direct_update_https_url', $d);
remove_filter('wp_update_php_url', $p);

// The admin-email change notice, caught before it is sent.
$mail = [];
add_filter('pre_wp_mail', $m = static function ($null, $atts) use (&$mail) { $mail[] = $atts; return true; }, 10, 2);
wp_site_admin_email_change_notification('old@example.test', 'new@example.test', 'admin_email');
add_filter('send_site_admin_email_change_email', '__return_false');
wp_site_admin_email_change_notification('old2@example.test', 'new2@example.test', 'admin_email');
remove_filter('send_site_admin_email_change_email', '__return_false');
wp_site_admin_email_change_notification('old@example.test', 'old@example.test', 'admin_email');
wp_site_admin_email_change_notification('', 'new@example.test', 'admin_email');
wp_site_admin_email_change_notification('you@example.com', 'new@example.test', 'admin_email');
wp_site_admin_email_change_notification('not-an-email', 'new@example.test', 'admin_email');
$sendArgs = [];
add_filter('send_site_admin_email_change_email', $sa = static function ($send, ...$rest) use (&$sendArgs) { $sendArgs[] = array_merge([$send], $rest); return $send; }, 10, 3);
wp_site_admin_email_change_notification('', 'x@example.test', 'admin_email');
wp_site_admin_email_change_notification('a@example.test', 'x@example.test', 'admin_email');
remove_filter('send_site_admin_email_change_email', $sa, 10);
remove_filter('pre_wp_mail', $m, 10);
$say('admin email change notice', [$mail, $sendArgs]);

$say('maybe load widgets', (static function () {
    $before = has_action('_admin_menu', 'wp_widgets_add_menu');
    remove_action('_admin_menu', 'wp_widgets_add_menu');
    wp_maybe_load_widgets();
    $loaded = has_action('_admin_menu', 'wp_widgets_add_menu');
    remove_action('_admin_menu', 'wp_widgets_add_menu');
    add_filter('load_default_widgets', '__return_false');
    wp_maybe_load_widgets();
    $off = has_action('_admin_menu', 'wp_widgets_add_menu');
    remove_filter('load_default_widgets', '__return_false');
    if ($before !== false) {
        add_action('_admin_menu', 'wp_widgets_add_menu', $before);
    }
    return [$before, $loaded, $off];
})());

// The option filters, last: defining WP_HOME and WP_SITEURL lasts the rest of the process.
$say('config home and siteurl, undefined', [_config_wp_home('https://zz.example/'), _config_wp_siteurl('https://zz.example/'), _config_wp_home(), defined('WP_HOME'), defined('WP_SITEURL')]);
if (!defined('WP_HOME')) {
    define('WP_HOME', 'https://home.zz.example/');
}
if (!defined('WP_SITEURL')) {
    define('WP_SITEURL', 'https://site.zz.example/wp/');
}
$say('config home and siteurl, defined', [_config_wp_home('https://zz.example/'), _config_wp_siteurl('https://zz.example/'), _config_wp_home()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
