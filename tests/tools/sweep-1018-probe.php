<?php
/**
 * Small rules the security sweep of 2026-10-18 checked against the
 * reference (probe sweep-1018): which statement types
 * wpdb::_insert_replace_helper() will build, seen through the query it
 * last sent, against a table that does not exist, so nothing is written;
 * which meta keys are protected once invisible characters are set aside;
 * the site description as bloginfo() prints it (through a pre_option
 * filter, nothing stored); the uploads folder asked for a time; and which
 * schemes the logged-in cookie alone satisfies.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$suppressed = $wpdb->suppress_errors(true);
$types = [];
foreach (['INSERT', 'insert', 'REPLACE', 'Replace', 'INSERT IGNORE', 'DELETE', 'UPDATE', '', 'INSERT INTO zz_other SELECT * FROM wp_users; --'] as $type) {
    $wpdb->last_query = 'zz-before';
    $result = $wpdb->_insert_replace_helper('zz_sweep_no_such_table', ['zz_col' => 'v'], null, $type);
    // Whether any query went out (the reference reads the table's columns first; the engine does not need to).
    $types[$type] = ['result' => $result, 'sent' => $wpdb->last_query === 'zz-before' ? 'nothing' : 'a query'];
}
$wpdb->suppress_errors($suppressed);
$say('_insert_replace_helper by type', $types);

// Which meta keys count as protected once invisible characters are set aside.
$keys = ['_x', 'x', ' _x', "\x01_x", "\x7f_x", "\u{200B}_x", "\u{00A0}_x", "\u{2028}_x", "\u{FEFF}_x", "\u{00E9}_x", "\t_x", '__x', '', '_'];
$say('is_protected_meta', array_map(static fn (string $key) => [rawurlencode($key), is_protected_meta($key, 'post')], $keys));

// The site's name and description as bloginfo() prints them: texturized, characters converted, escaped.
$say('bloginfo display filters', array_map(static fn (string $callback) => has_filter('bloginfo', $callback), ['wptexturize', 'convert_chars', 'esc_html']));
$description = static fn () => "It's <b>bold</b> & \"quoted\" -- <script>x</script>";
add_filter('pre_option_blogdescription', $description);
ob_start();
bloginfo('description');
$printed = ob_get_clean();
$say('bloginfo printed', ['bloginfo' => $printed, 'display' => get_bloginfo('description', 'display'), 'raw' => get_bloginfo('description')]);
remove_filter('pre_option_blogdescription', $description);

// A navigation's custom overlay colours, as a submenu's style attribute carries them.
$nav = do_blocks('<!-- wp:navigation {"overlayMenu":"never","customOverlayBackgroundColor":"red\" data-zz=\"1","customOverlayTextColor":"blue;background:url(javascript:alert(1))"} --><!-- wp:navigation-submenu {"label":"Zz","url":"/zz/"} --><!-- wp:navigation-link {"label":"Zz child","url":"/zz/child/"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->');
preg_match_all('/<[a-z]+ [^>]*(?:style|data-zz)="[^"]*"[^>]*>/', $nav, $tags);
$say('navigation overlay colours', array_values(array_filter(array_map(static fn (string $tag) => preg_match_all('/\s(style|data-zz)="([^"]*)"/', $tag, $m) ? array_combine($m[1], $m[2]) : null, $tags[0]))));

// The uploads folder for a given time (media_handle_upload asks for the parent post's), the folder not made.
$say('wp_upload_dir for a time', array_map(static fn ($time) => array_intersect_key(wp_upload_dir($time, false), array_flip(['subdir', 'error'])), ['2019/03', '2019-03-07 10:00:00']));
$say('wp_upload_dir with no time is this month', wp_upload_dir(null, false)['subdir'] === '/' . current_time('Y/m'));

// Which schemes the logged-in cookie alone satisfies, asked with no cookie given (a plugin's own auth_redirect asks so).
// The session made for it is destroyed again, and the request's cookies put back.
$sessions = WP_Session_Tokens::get_instance(1);
$token = $sessions->create(time() + 3600);
$cookie = wp_generate_auth_cookie(1, time() + 3600, 'logged_in', $token);
$cookies = $_COOKIE;
unset($_COOKIE[AUTH_COOKIE], $_COOKIE[SECURE_AUTH_COOKIE]);
$_COOKIE[LOGGED_IN_COOKIE] = $cookie;
$schemes = [];
foreach (['auth', 'secure_auth', 'logged_in', ''] as $scheme) {
    $schemes["no cookie given, scheme '{$scheme}'"] = wp_validate_auth_cookie('', $scheme);
}
$schemes['the logged-in cookie given, scheme auth'] = wp_validate_auth_cookie($cookie, 'auth');
$_COOKIE = $cookies;
$sessions->destroy($token);
$say('wp_validate_auth_cookie with only the logged-in cookie', $schemes);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
