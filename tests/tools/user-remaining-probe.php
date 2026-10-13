<?php
/**
 * The rest of user.php (probe user-remaining): user counts by role (both
 * strategies), the stored user count, email and user lookups, blog
 * membership, signing in by username or email (with the reference's
 * refusals), the spam check, application password validation without a
 * request, the session token from the sign-in cookie, the users cache's last
 * change, the persisted preferences meta, and new user notifications (caught
 * before they are sent). The probe's users are removed and the options it
 * touches are put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$userCount = get_option('user_count', null);
$cronBefore = wp_get_scheduled_event('wp_update_user_counts');
$cookies = $_COOKIE;
register_shutdown_function(static function () use (&$made, $userCount, $cronBefore, $cookies): void {
    $_COOKIE = $cookies;
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
    $userCount === null ? delete_option('user_count') : update_option('user_count', $userCount);
    wp_clear_scheduled_hook('wp_update_user_counts');
    if ($cronBefore) {
        $cronBefore->schedule ? wp_schedule_event($cronBefore->timestamp, $cronBefore->schedule, 'wp_update_user_counts', $cronBefore->args) : wp_schedule_single_event($cronBefore->timestamp, 'wp_update_user_counts', $cronBefore->args);
    }
});
$ids = [1 => 'admin'];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_User) {
        return $mask(['user' => $value->ID, 'login' => $value->user_login]);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_codes(), 'messages' => $mask($value->get_error_messages()), 'data' => $value->get_error_data()];
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$mail = [];
add_filter('pre_wp_mail', static function ($null, $atts) use (&$mail) {
    $mail[] = [$atts['to'], $atts['subject'], $atts['message'] !== '' ? '(a message)' : ''];
    return true;
}, 10, 2);

$user = static function (string $login, string $role, array $extra = []) use (&$made, &$ids): int {
    $made[] = $id = (int) wp_insert_user($extra + ['user_login' => $login, 'user_email' => str_replace('_', '-', $login) . '@zz.example', 'user_pass' => 'zz-pass-' . $login, 'role' => $role]);
    $ids[$id] = $login;
    return $id;
};
$counted = static function () {
    $counts = [count_users(), count_users('memory'), count_users('time', get_current_blog_id())];
    return [$counts[0] === $counts[1] && $counts[0] === $counts[2], $counts[0]];
};
$counts = [$counted()];
$editor = $user('zz_ur_editor', 'editor');
$author = $user('zz_ur_author', 'author');
$nobody = $user('zz_ur_nobody', '');
$made[] = $multi = (int) wp_insert_user(['user_login' => 'zz_ur_multi', 'user_email' => 'zz-ur-multi@zz.example', 'user_pass' => 'zz-pass-multi', 'role' => 'subscriber']);
$ids[$multi] = 'zz_ur_multi';
(new WP_User($multi))->add_role('contributor');
$counts[] = $counted();
$before = $counts[0][1];
$after = $counts[1][1];
$say('user counts by role', [
    array_keys($after), $after['total_users'] - $before['total_users'],
    array_map(static fn ($role) => ($after['avail_roles'][$role] ?? 0) - ($before['avail_roles'][$role] ?? 0), ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'none']),
    array_keys($after['avail_roles']), $counts[0][0], $counts[1][0], is_int($after['total_users']), array_map('gettype', $after['avail_roles']),
]);
add_filter('pre_count_users', $preset = static fn ($result, $strategy, $site) => $strategy === 'zz' ? ['total_users' => 42, 'avail_roles' => ['zz' => 1]] : $result, 10, 3);
$say('user counts filtered', [count_users('zz'), count_users('time') === $after]);
remove_filter('pre_count_users', $preset, 10);

$stored = [wp_update_user_counts(), (int) get_option('user_count') === (int) $after['total_users']];
update_option('user_count', 1);
$stored[] = wp_maybe_update_user_counts();
$stored[] = (int) get_option('user_count') === (int) $after['total_users'];
$stored[] = wp_update_user_counts(1);
$say('the stored user count', $stored);

$say('lookups and membership', $mask([
    email_exists('zz-ur-editor@zz.example'), email_exists('ZZ-UR-EDITOR@ZZ.EXAMPLE'), email_exists('nobody@zz.example'), email_exists(''), email_exists(' zz-ur-editor@zz.example '),
    get_user($editor), get_user((string) $author), get_user(999999), get_user(0),
    is_user_member_of_blog($editor), is_user_member_of_blog($nobody), is_user_member_of_blog(999999), is_user_member_of_blog(0), is_user_member_of_blog($editor, 1), is_user_member_of_blog($editor, 99),
    (static function () use ($editor) {
        wp_set_current_user($editor);
        $current = [_wp_get_current_user(), is_user_member_of_blog()];
        wp_set_current_user(0);
        $current[] = _wp_get_current_user();
        wp_set_current_user(1);
        return $current;
    })(),
]));

$signIns = [];
foreach ([
    ['wp_authenticate_username_password', null, 'zz_ur_editor', 'zz-pass-zz_ur_editor'], ['wp_authenticate_username_password', null, 'zz_ur_editor', 'wrong'], ['wp_authenticate_username_password', null, 'zz_ur_missing', 'x'],
    ['wp_authenticate_username_password', null, '', 'x'], ['wp_authenticate_username_password', null, 'zz_ur_editor', ''], ['wp_authenticate_username_password', null, '', ''], ['wp_authenticate_username_password', null, 'ZZ_UR_EDITOR', 'zz-pass-zz_ur_editor'],
    ['wp_authenticate_username_password', 'given', 'zz_ur_editor', 'wrong'], ['wp_authenticate_username_password', 'error', 'zz_ur_editor', 'zz-pass-zz_ur_editor'], ['wp_authenticate_username_password', null, 'zz-ur-editor@zz.example', 'zz-pass-zz_ur_editor'],
    ['wp_authenticate_email_password', null, 'zz-ur-author@zz.example', 'zz-pass-zz_ur_author'], ['wp_authenticate_email_password', null, 'zz-ur-author@zz.example', 'wrong'], ['wp_authenticate_email_password', null, 'nobody@zz.example', 'x'],
    ['wp_authenticate_email_password', null, 'zz_ur_author', 'zz-pass-zz_ur_author'], ['wp_authenticate_email_password', null, '', ''], ['wp_authenticate_email_password', null, 'zz-ur-author@zz.example', ''], ['wp_authenticate_email_password', 'given', 'nobody@zz.example', 'x'],
] as [$function, $given, $login, $password]) {
    $start = $given === 'given' ? get_user_by('id', $author) : ($given === 'error' ? new WP_Error('zz_earlier', 'Earlier') : null);
    $signIns[] = [$function, $given, $login, $function === 'wp_authenticate_username_password' ? wp_authenticate_username_password($start, $login, $password) : wp_authenticate_email_password($start, $login, $password)];
}
$heard = [];
add_filter('wp_authenticate_user', $authFilter = static function ($user, $password) use (&$heard) {
    $heard[] = ['wp_authenticate_user', $user instanceof WP_User ? $user->user_login : get_class($user), $password];
    return $user;
}, 10, 2);
add_filter('authenticate', $authenticate = static function ($user) {
    return $user;
}, 99);
wp_authenticate_username_password(null, 'zz_ur_editor', 'zz-pass-zz_ur_editor');
wp_authenticate_email_password(null, 'zz-ur-author@zz.example', 'wrong');
remove_filter('wp_authenticate_user', $authFilter, 10);
remove_filter('authenticate', $authenticate, 99);
$signIns[] = ['filters heard', $heard];
$say('signing in by username or email', $mask($signIns));

$say('spam check and application passwords', $mask([
    wp_authenticate_spam_check(get_user_by('id', $editor)), wp_authenticate_spam_check(null), wp_authenticate_spam_check(new WP_Error('zz', 'z')),
    wp_validate_application_password(false), wp_validate_application_password($editor), wp_validate_application_password(null), wp_validate_application_password(get_user_by('id', $editor)),
]));

$hash = COOKIEHASH;
$tokens = [];
unset($_COOKIE[LOGGED_IN_COOKIE]);
$tokens[] = wp_get_session_token();
$_COOKIE[LOGGED_IN_COOKIE] = 'zz_ur_editor|' . (time() + 3600) . '|zz-session-token|hmac';
$tokens[] = wp_get_session_token();
$_COOKIE[LOGGED_IN_COOKIE] = 'broken';
$tokens[] = wp_get_session_token();
$_COOKIE[LOGGED_IN_COOKIE] = 'zz_ur_editor|' . (time() + 3600) . '|zz-session-token|hmac';
wp_set_current_user($editor);
$manager = WP_Session_Tokens::get_instance($editor);
$tokenKey = $manager->create(time() + 3600);
$_COOKIE[LOGGED_IN_COOKIE] = 'zz_ur_editor|' . (time() + 3600) . '|' . $tokenKey . '|hmac';
$tokens[] = [wp_get_session_token() === $tokenKey, $manager->verify($tokenKey)];
wp_destroy_current_session();
$tokens[] = $manager->verify($tokenKey);
unset($_COOKIE[LOGGED_IN_COOKIE]);
wp_destroy_current_session();
wp_set_current_user(1);
$say('session tokens', $tokens);

wp_cache_delete('last_changed', 'users');
$first = wp_cache_get_last_changed('users');
usleep(2000);
wp_cache_set_users_last_changed();
$second = wp_cache_get_last_changed('users');
$heard = [];
add_action('wp_cache_set_last_changed', $lastChanged = static function ($group, $time, $old) use (&$heard) {
    $heard[] = [$group, is_string($time), $old !== false];
}, 10, 3);
wp_cache_set_users_last_changed();
remove_action('wp_cache_set_last_changed', $lastChanged, 10);
$say('the users cache last change', [$first !== $second, is_string($second), $heard]);

global $wpdb;
$preferenceKey = $wpdb->get_blog_prefix() . 'persisted_preferences';
$userKeys = static fn () => array_keys(get_registered_meta_keys('user', ''));
$preferences = [in_array($preferenceKey, $userKeys(), true)];
unregister_meta_key('user', $preferenceKey);
$preferences[] = in_array($preferenceKey, $userKeys(), true);
wp_register_persisted_preferences_meta();
$registered = get_registered_meta_keys('user', '')[$preferenceKey] ?? null;
$preferences[] = $registered === null ? null : array_intersect_key($registered, array_flip(['type', 'label', 'description', 'single', 'show_in_rest', 'default']));
$say('the persisted preferences meta', $preferences);

$notified = [];
foreach (['both', 'admin', 'user', ''] as $notify) {
    $mail = [];
    wp_send_new_user_notifications($author, $notify);
    $notified[] = [$notify, $mail];
}
$say('new user notifications', $mask($notified));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
