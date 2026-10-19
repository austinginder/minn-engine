<?php
/**
 * Sessions and application passwords through the user meta API (probe
 * user-meta-writes): wp_generate_auth_cookie with no token makes its
 * session as WordPress makes one (the user read through get_userdata,
 * session_token_manager, attach_session_information, then the store
 * written with update_user_meta's filters and actions); the session
 * manager's destroy, destroy_all (the meta deleted); an application
 * password made, used, renamed and deleted, each read through get_user_meta
 * and written through update_user_meta, and every one deleted (an empty
 * list written, not the meta deleted); what the store keeps. Same
 * protocol as api-probe.php; the probe's user is removed at the end.
 */
$out = [];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zz-agent';
$id = (int) wp_insert_user(['user_login' => 'zz_umw_user', 'user_pass' => 'zz-umw-pass-123', 'user_email' => 'zz-umw@example.com', 'role' => 'subscriber']);
register_shutdown_function(static function () use ($id) { require_once ABSPATH . 'wp-admin/includes/user.php'; if ($id > 1) wp_delete_user($id); });
if ($id <= 1) { fwrite(STDERR, "user-meta-writes: could not make the user\n"); exit(1); }
$seq = [];
$rec = static function ($hook, ...$args) use (&$seq) {
    $metaHooks = ['get_user_metadata', 'add_user_metadata', 'update_user_metadata', 'delete_user_metadata', 'add_user_meta', 'added_user_meta', 'update_user_meta', 'updated_user_meta', 'delete_user_meta', 'deleted_user_meta', 'sanitize_user_meta_session_tokens', 'auth_user_meta_session_tokens', 'update_user_metadata_cache'];
    if (in_array($hook, $metaHooks, true)) {
        $key = null;
        foreach ($args as $a) { if (is_string($a) && $a === 'session_tokens') $key = $a; }
        foreach ($args as $a) { if (is_string($a) && $a === '_application_passwords') $key = $a; }
        if ($key !== null || in_array($hook, ['update_user_metadata_cache'], true)) $seq[] = $hook . ($key === '_application_passwords' ? ' (app)' : '');
    } elseif (in_array($hook, ['attach_session_information', 'session_token_manager', 'auth_cookie', 'auth_cookie_expiration'], true)) {
        $seq[] = $hook;
    }
};
$run = static function (string $label, callable $call) use (&$seq, &$out, $rec) {
    $seq = [];
    add_action('all', $rec, 10, 6);
    $value = $call();
    remove_action('all', $rec, 10);
    $out[$label] = ['hooks' => $seq, 'value' => is_string($value) ? (strlen($value) > 20 ? 'a cookie' : $value) : $value];
};
$cookie = '';
$run('a cookie with a new session', static function () use ($id, &$cookie) { $cookie = wp_generate_auth_cookie($id, time() + 3600, 'auth'); return $cookie; });
$run('another', static fn () => wp_generate_auth_cookie($id, time() + 3600, 'logged_in'));
$run('count', static fn () => count(WP_Session_Tokens::get_instance($id)->get_all()));
$run('destroy one', static function () use ($id, $cookie) { WP_Session_Tokens::get_instance($id)->destroy(explode('|', $cookie)[2]); return count(WP_Session_Tokens::get_instance($id)->get_all()); });
$run('destroy all', static function () use ($id) { WP_Session_Tokens::get_instance($id)->destroy_all(); return get_user_meta($id, 'session_tokens', true); });
$run('a cookie after all were destroyed', static fn () => wp_generate_auth_cookie($id, time() + 3600, 'auth'));
$uuid = '';
$run('an application password', static function () use ($id, &$uuid) { $r = WP_Application_Passwords::create_new_application_password($id, ['name' => 'Zz UMW']); $uuid = is_array($r) ? $r[1]['uuid'] : ''; return is_array($r); });
$run('its use recorded', static fn () => WP_Application_Passwords::record_application_password_usage($id, $uuid));
$run('it renamed', static fn () => WP_Application_Passwords::update_application_password($id, $uuid, ['name' => 'Zz UMW 2']));
$run('it deleted', static fn () => WP_Application_Passwords::delete_application_password($id, $uuid));
WP_Application_Passwords::create_new_application_password($id, ['name' => 'Zz UMW A']);
WP_Application_Passwords::create_new_application_password($id, ['name' => 'Zz UMW B']);
$run('every one deleted', static function () use ($id) {
    global $wpdb;
    $count = WP_Application_Passwords::delete_all_application_passwords($id);
    return [$count, $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_application_passwords'", $id))];
});
// Which hooks, not how often: the engine reads the list once more on the way.
$out['every one deleted']['hooks'] = array_values(array_unique($out['every one deleted']['hooks']));
$out['stored'] = array_values(array_map(static fn ($s) => array_keys($s), (array) get_user_meta($id, 'session_tokens', true)));
echo json_encode(array_map(null, array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
