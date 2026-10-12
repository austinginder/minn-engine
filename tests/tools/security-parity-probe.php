<?php
/**
 * Security parity (probe security-parity), from the 2026-10-12 audit:
 * - redirects sanitized and validated (encoded CR and LF taken out until
 *   none is left, nothing else; a backslash dropped);
 * - REST: a search leaves out non-public types and, for someone signed
 *   out, protected posts; a type with its own controller is answered by
 *   it; only a moderator
 *   names another comment author, an address or the status;
 * - a serialized-looking string stored serialized again, so it reads back
 *   as that string (meta and options);
 * - capabilities when file changes are forbidden: the file_mod_allowed
 *   filter with its contexts, then DISALLOW_FILE_EDIT, then
 *   DISALLOW_FILE_MODS (constants last: they cannot be taken back).
 * Everything the probe makes is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');

if (!class_exists('Zz_Sec_Strict_Controller')) {
    class Zz_Sec_Strict_Controller extends WP_REST_Posts_Controller
    {
        public function get_items_permissions_check($request)
        {
            return current_user_can('edit_pages') ? true : new WP_Error('zz_sec_strict', 'Strict.', ['status' => rest_authorization_required_code()]);
        }
    }
}
register_post_type('zz_sec_private', ['public' => false, 'show_in_rest' => true, 'label' => 'ZZ Private']);
register_post_type('zz_sec_strict', ['public' => true, 'show_in_rest' => true, 'label' => 'ZZ Strict', 'rest_controller_class' => 'Zz_Sec_Strict_Controller']);

$made = [];
$user = wp_insert_user(['user_login' => 'zz_sec_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'zz_sec_sub@example.com', 'role' => 'subscriber']);
if (!is_int($user) || $user <= 1) {
    fwrite(STDERR, "no subscriber\n");
    exit(1);
}
register_shutdown_function(static function () use (&$made, $user): void {
    foreach ($made as [$kind, $id]) {
        if (is_int($id) && $id > 1) {
            $kind === 'post' ? wp_delete_post($id, true) : wp_delete_comment($id, true);
        }
    }
    delete_user_meta(1, 'zz_sec_meta');
    delete_option('zz_sec_option');
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    wp_delete_user($user);
});
// Dated apart, so a search's order never rests on a tie.
foreach ([['zz_sec_private', 'zz sec private item', '', '2001-03-01'], ['zz_sec_strict', 'zz sec strict item', '', '2001-03-02'], ['post', 'zz sec protected title', 'pw', '2001-03-03']] as [$type, $title, $password, $date]) {
    $id = wp_insert_post(['post_type' => $type, 'post_title' => $title, 'post_content' => 'zzsecword inside', 'post_status' => 'publish', 'post_password' => $password, 'post_date' => "{$date} 10:00:00", 'post_date_gmt' => "{$date} 10:00:00"]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no post\n");
        exit(1);
    }
    $made[] = ['post', $id];
}

// Redirects.
$redirects = [];
foreach (['/%0%0%0999/evil.com', '/a%0d%0ab', '/a%0D%0Ab', '/%0%0d0da/b', '/%0%0a0aa/b', '/x%09y', '/x%00y', 'https://evil.com\@site/', "/a\r\nb", '/ok/path?x=1#f', '//evil.com', '/\evil.com', '/caf' . "\xC3\xA9" . '/'] as $location) {
    $redirects[$location] = [wp_sanitize_redirect($location), wp_validate_redirect(wp_sanitize_redirect($location), 'FALLBACK')];
}
$say('redirects sanitized, then validated', $redirects);

// REST, in process.
$ask = static function (string $method, string $route, array $params) use (&$made): array {
    $request = new WP_REST_Request($method, $route);
    $method === 'GET' ? $request->set_query_params($params) : $request->set_body_params($params);
    $response = rest_do_request($request);
    $data = $response->get_data();
    if ($method === 'POST' && is_array($data) && isset($data['id']) && is_int($data['id'])) {
        $made[] = ['comment', $data['id']];
    }
    return [$response->get_status(), is_array($data) && array_is_list($data) ? array_map(static fn ($item) => is_array($item) ? ($item['title']['rendered'] ?? $item['title'] ?? null) : $item, $data) : ($data['code'] ?? ($data['status'] ?? null))];
};
wp_set_current_user(0);
$rest = [
    'search a non-public type, nobody' => $ask('GET', '/wp/v2/search', ['search' => 'zz sec private']),
    'search naming the non-public type' => $ask('GET', '/wp/v2/search', ['search' => 'zz sec', 'subtype' => 'zz_sec_private']),
    'search a protected post, nobody' => $ask('GET', '/wp/v2/search', ['search' => 'zz sec protected']),
    'search its content, nobody' => $ask('GET', '/wp/v2/search', ['search' => 'zzsecword']),
    'a type with its own controller, nobody' => $ask('GET', '/wp/v2/zz_sec_strict', []),
];
wp_set_current_user($user);
$rest += [
    'a comment naming user 1' => $ask('POST', '/wp/v2/comments', ['post' => 1, 'author' => 1, 'content' => 'zz sec other']),
    'a comment naming an address' => $ask('POST', '/wp/v2/comments', ['post' => 1, 'author_ip' => '10.0.0.9', 'content' => 'zz sec address']),
    'a comment naming its status' => $ask('POST', '/wp/v2/comments', ['post' => 1, 'status' => 'approved', 'content' => 'zz sec status']),
    'a comment as oneself' => $ask('POST', '/wp/v2/comments', ['post' => 1, 'author' => $user, 'content' => 'zz sec self']),
];
wp_set_current_user(1);
$rest['search its content, signed in'] = $ask('GET', '/wp/v2/search', ['search' => 'zzsecword']);
$say('REST', $rest);

// Serialized-looking strings, stored as the reference stores them.
$serialized = 'a:1:{s:1:"x";s:1:"y";}';
update_user_meta(1, 'zz_sec_meta', 'O:8:"stdClass":1:{s:1:"x";s:1:"y";}');
update_option('zz_sec_option', $serialized);
global $wpdb;
$say('serialized-looking strings', [
    'meta' => [gettype(get_user_meta(1, 'zz_sec_meta', true)), $wpdb->get_var("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = 1 AND meta_key = 'zz_sec_meta'")],
    'option' => [get_option('zz_sec_option'), $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'zz_sec_option'")],
    'maybe_serialize' => maybe_serialize($serialized),
    'is_serialized' => [is_serialized($serialized), is_serialized('s:1:"x";'), is_serialized('nope'), is_serialized(' N; ')],
]);

// Capabilities when file changes are forbidden.
$caps = ['install_plugins', 'install_themes', 'upload_plugins', 'upload_themes', 'update_plugins', 'update_themes', 'update_core', 'update_php', 'update_https', 'delete_plugins', 'delete_themes', 'install_languages', 'update_languages', 'edit_plugins', 'edit_themes', 'edit_files', 'activate_plugins', 'switch_themes'];
$map = static fn (): array => array_combine($caps, array_map(static fn ($cap) => map_meta_cap($cap, 1), $caps));
$heard = [];
$mapped = ['allowed' => $map()];
add_filter('file_mod_allowed', $no = static function ($allowed, $context) use (&$heard) {
    $heard[] = $context;
    return false;
}, 10, 2);
$mapped['file_mod_allowed says no'] = $map();
$mapped['contexts heard'] = array_values(array_unique($heard));
remove_filter('file_mod_allowed', $no, 10);
define('DISALLOW_FILE_EDIT', true);
$mapped['DISALLOW_FILE_EDIT'] = $map();
define('DISALLOW_FILE_MODS', true);
$mapped['DISALLOW_FILE_MODS'] = $map();
$mapped['current_user_can install_plugins'] = current_user_can('install_plugins');
$say('capabilities when file changes are forbidden', $mapped);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
