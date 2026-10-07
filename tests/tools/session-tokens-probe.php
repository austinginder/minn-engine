<?php
/**
 * The session token API plugins use, as the reference answers it (probe
 * session-tokens): a session created with what attach_session_information
 * adds, read back, verified, listed, and ended one by one, all but one,
 * and all; an old-style session stored as a bare expiration; and the
 * manager session_token_manager names. Over the probe's own user, removed
 * at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// Where a session says it came from, the same on both stacks.
$_SERVER['REMOTE_ADDR'] = '192.0.2.7';
$_SERVER['HTTP_USER_AGENT'] = 'Zz Agent';
$user = (int) wp_insert_user(['user_login' => 'zz_sessions', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-sessions@example.com', 'role' => 'subscriber']);
$heard = [];
add_filter('attach_session_information', static function ($session, $userId) use (&$heard, $user) {
    $heard[] = [$session, $userId === $user ? '{user}' : $userId];
    return $session + ['zz_plugin' => ['switched_from' => 7, 'note' => 'a "quoted" value']];
}, 10, 2);
$shape = static fn ($session) => is_array($session) ? array_map(static fn ($v) => is_int($v) && $v > 1000000000 ? 'time' : $v, $session) : $session;
$manager = WP_Session_Tokens::get_instance($user);
$say('the manager', get_class($manager));
$first = $manager->create(time() + 3600);
$second = $manager->create(time() + 7200);
$say('what create made', [strlen($first), ctype_alnum($first), $heard]);
$say('a session read back', $shape($manager->get($first)));
$say('verified', [$manager->verify($first), $manager->verify('zz-not-a-token')]);
$say('all of them', array_map($shape, $manager->get_all()));
$stored = get_user_meta($user, 'session_tokens', true);
$say('as stored', [is_array($stored), count((array) $stored), array_map(static fn ($key) => strlen((string) $key), array_keys((array) $stored))]);
$manager->update($first, ['expiration' => time() + 60, 'zz' => 'updated']);
$say('one updated', $shape($manager->get($first)));
$manager->destroy_others($second);
$say('all but one ended', [count($manager->get_all()), $manager->verify($first), $manager->verify($second)]);
$manager->destroy($second);
$say('the last ended', [count($manager->get_all()), metadata_exists('user', $user, 'session_tokens'), get_user_meta($user, 'session_tokens', true)]);
$third = $manager->create(time() + 60);
$manager->destroy_all();
$say('all ended', [count($manager->get_all()), $manager->verify($third)]);
update_user_meta($user, 'session_tokens', [hash('sha256', 'zz-old') => time() + 60, hash('sha256', 'zz-gone') => time() - 60]);
$say('an old-style session', [$shape($manager->get('zz-old')), $manager->get('zz-gone'), array_map($shape, $manager->get_all())]);
$manager->destroy_others('zz-not-there');
$say('ending all but a session that is not there', count($manager->get_all()));
add_filter('session_token_manager', $named = static fn () => 'WP_User_Meta_Session_Tokens');
$say('the manager a plugin names', get_class(WP_Session_Tokens::get_instance($user)));
remove_filter('session_token_manager', $named);
if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
wp_delete_user($user);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
