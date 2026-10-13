<?php
/**
 * Which user changes end a password reset key (probe reset-key-changes;
 * security sweep 2026-10-13): a new address or password does, through
 * wp_update_user, REST or wp_set_password; a display name, a URL or the
 * same address again does not, as on the reference. Same protocol as
 * api-probe.php; every user it makes is removed at the end.
 */

$made = [];
register_shutdown_function(static function () use (&$made): void {
    if (!function_exists('wp_delete_user')) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
    foreach ($made as $id) { if (is_int($id) && $id > 1) { wp_delete_user($id); } }
});
$changes = [
    'email' => static fn (int $id) => wp_update_user(['ID' => $id, 'user_email' => "zz_secrk_{$id}_new@example.com"]),
    'password' => static fn (int $id) => wp_update_user(['ID' => $id, 'user_pass' => 'Zz-new-pass-2']),
    'display name' => static fn (int $id) => wp_update_user(['ID' => $id, 'display_name' => 'Zz New Name']),
    'url' => static fn (int $id) => wp_update_user(['ID' => $id, 'user_url' => 'https://example.com/zz']),
    'same email again' => static fn (int $id) => wp_update_user(['ID' => $id, 'user_email' => get_userdata($id)->user_email]),
    'rest email' => static function (int $id) { wp_set_current_user(1); $r = new WP_REST_Request('POST', "/wp/v2/users/{$id}"); $r->set_body_params(['email' => "zz_secrk_{$id}_rest@example.com"]); rest_do_request($r); },
    'rest password' => static function (int $id) { wp_set_current_user(1); $r = new WP_REST_Request('POST', "/wp/v2/users/{$id}"); $r->set_body_params(['password' => 'Zz-rest-pass-3']); rest_do_request($r); },
    'wp_set_password' => static fn (int $id) => wp_set_password('Zz-set-pass-4', $id),
];
$out = [];
foreach ($changes as $label => $change) {
    $login = 'zz_secrk_' . substr(md5($label), 0, 8);
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => 'Zz-old-pass-1', 'user_email' => "{$login}@example.com", 'role' => 'subscriber']);
    if (!is_int($id) || $id <= 1) { $out[$label] = 'setup failed'; continue; }
    $made[] = $id;
    $key = get_password_reset_key(get_userdata($id));
    $change($id);
    clean_user_cache($id);
    $out[$label] = is_wp_error(check_password_reset_key($key, $login)) ? 'key invalid' : 'key valid';
}
echo json_encode(array_map(static fn ($label, $value) => [$label, $value], array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES);
