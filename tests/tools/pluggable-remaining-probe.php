<?php
/**
 * The rest of pluggable.php (probe pluggable-remaining): users cached in
 * bulk, nonce ticks, salts (judged against the site's own keys), auth cookies
 * parsed, set and cleared (heard through their actions, with a given session
 * token so no session is made), signing out, nonce checks that pass and one
 * that is allowed to fail, redirects and safe redirects (heard through their
 * filters; headers do not leave the command line), passwords set, the new
 * user, password change and comment notifications (caught before they are
 * sent; the engine words its notices itself, so only who hears and the
 * subject are compared). auth_redirect is left out: it ends the request;
 * wp_text_diff too, until the engine draws the diff table. The probe's user, post and comments are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'posts' => [], 'comments' => []];
$cronBefore = wp_get_scheduled_event('wp_update_user_counts');
$moderationNotify = get_option('moderation_notify');
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $cronBefore, $moderationNotify, $pings): void {
    update_option('moderation_notify', $moderationNotify);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
    wp_clear_scheduled_hook('wp_update_user_counts');
    if ($cronBefore) {
        $cronBefore->schedule ? wp_schedule_event($cronBefore->timestamp, $cronBefore->schedule, 'wp_update_user_counts', $cronBefore->args) : wp_schedule_single_event($cronBefore->timestamp, 'wp_update_user_counts', $cronBefore->args);
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
wp_set_current_user(1);
$ids[1] = 'admin';

$made['users'][] = $user = (int) wp_insert_user(['user_login' => 'zz_pl_user', 'user_email' => 'zz-pl-user@zz.example', 'user_pass' => 'zz-first-pass', 'role' => 'author', 'display_name' => 'Zz Pl']);
$ids[$user] = 'user';
$made['users'][] = $other = (int) wp_insert_user(['user_login' => 'zz_pl_other', 'user_email' => 'zz-pl-other@zz.example', 'user_pass' => 'zz-other-pass', 'role' => 'subscriber']);
$ids[$other] = 'other';

wp_cache_delete($user, 'users');
wp_cache_delete($other, 'users');
$cached = [wp_cache_get($user, 'users') !== false, wp_cache_get($other, 'users') !== false];
cache_users([$user, $other, 999999, 0, (string) $user]);
$cached[] = [wp_cache_get($user, 'users') !== false, wp_cache_get($other, 'users') !== false, wp_cache_get('zz_pl_user', 'userlogins'), wp_cache_get('zz-pl-user@zz.example', 'useremail'), wp_cache_get('zz_pl_user', 'userslugs'), wp_cache_get(999999, 'users')];
cache_users([]);
$say('users cached in bulk', $mask($cached));

$tick = static fn ($action = -1) => wp_nonce_tick($action);
$ticks = [$tick() === (int) ceil(time() / (DAY_IN_SECONDS / 2)), $tick('zz') === $tick(), is_int($tick()) || is_float($tick())];
add_filter('nonce_life', $life = static fn ($seconds, $action) => $action === 'zz-short' ? 3600 : $seconds, 10, 2);
$ticks[] = $tick('zz-short') === (int) ceil(time() / 1800);
$ticks[] = $tick('zz-other') === $tick();
remove_filter('nonce_life', $life, 10);
$say('nonce ticks', $ticks);

$salts = [];
foreach (['auth' => ['AUTH_KEY', 'AUTH_SALT'], 'secure_auth' => ['SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'], 'logged_in' => ['LOGGED_IN_KEY', 'LOGGED_IN_SALT'], 'nonce' => ['NONCE_KEY', 'NONCE_SALT']] as $scheme => [$key, $salt]) {
    $salts[] = [$scheme, wp_salt($scheme) === constant($key) . constant($salt), wp_salt($scheme) === wp_salt($scheme)];
}
add_filter('salt', $salted = static fn ($value, $scheme) => $scheme === 'nonce' ? 'zz-salted' : $value, 10, 2);
$salts[] = [wp_salt('nonce'), wp_salt('auth') === AUTH_KEY . AUTH_SALT];
remove_filter('salt', $salted, 10);
$say('salts', $salts);

$expiration = time() + 3600;
$cookie = wp_generate_auth_cookie($user, $expiration, 'auth', 'zz-token');
$parsedCookie = static function ($parsed) use ($expiration) {
    if (!is_array($parsed)) {
        return $parsed;
    }
    $seconds = (int) $parsed['expiration'] - time();
    $parsed['expiration'] = (int) $parsed['expiration'] === $expiration ? '{expiration}' : (is_numeric($parsed['expiration']) && $seconds > 0 ? ($seconds > 1000000 ? 'two weeks on' : 'two days on') : $parsed['expiration']);
    $parsed['hmac'] = strlen((string) $parsed['hmac']);
    return $parsed;
};
$_COOKIE[AUTH_COOKIE] = 'zz_pl_user|' . $expiration . '|zz-cookie-token|abc';
$_COOKIE[LOGGED_IN_COOKIE] = 'zz_pl_user|' . $expiration . '|zz-logged-token|def';
$say('auth cookies parsed', $mask(array_map($parsedCookie, [
    wp_parse_auth_cookie($cookie), wp_parse_auth_cookie($cookie, 'auth'), wp_parse_auth_cookie('a|b|c'), wp_parse_auth_cookie('a|b|c|d|e'), wp_parse_auth_cookie(''), wp_parse_auth_cookie('', 'logged_in'), wp_parse_auth_cookie('', 'auth'), wp_parse_auth_cookie('', 'zz'), wp_parse_auth_cookie('zz%7C1%7Ct%7Ch'),
])));
unset($_COOKIE[AUTH_COOKIE], $_COOKIE[LOGGED_IN_COOKIE]);

$heard = [];
foreach (['set_auth_cookie', 'set_logged_in_cookie', 'clear_auth_cookie', 'wp_logout'] as $hook) {
    add_action($hook, $listeners[$hook] = static function (...$args) use ($hook, &$heard, $parsedCookie, $expiration) {
        $shown = [];
        foreach ($args as $i => $arg) {
            if (is_string($arg) && substr_count($arg, '|') === 3) {
                $shown[] = $parsedCookie(wp_parse_auth_cookie($arg, in_array($i === 0 ? ($args[4] ?? '') : '', ['auth', 'secure_auth', 'logged_in'], true) ? $args[4] : ''));
            } elseif (is_int($arg) && $arg > time()) {
                $shown[] = $arg - time() > 1000000 ? 'two weeks on' : ($arg - time() > 100000 ? 'two days on' : 'later');
            } else {
                $shown[] = $arg;
            }
        }
        $heard[] = [$hook, $shown];
    }, 10, 6);
}
add_filter('auth_cookie_expiration', $expiry = static function ($seconds, $userId, $remember) use (&$heard) {
    $heard[] = ['auth_cookie_expiration', $seconds, $userId, $remember];
    return $seconds;
}, 10, 3);
add_filter('send_auth_cookies', $send = static function ($send, ...$rest) use (&$heard) {
    $heard[] = ['send_auth_cookies', $send, count($rest)];
    return false;
}, 10, 6);
wp_set_auth_cookie($user, false, false, 'zz-token');
wp_set_auth_cookie($user, true, true, 'zz-token');
wp_clear_auth_cookie();
wp_set_current_user($user);
wp_logout();
$loggedOut = get_current_user_id();
foreach ($listeners as $hook => $listener) {
    remove_action($hook, $listener, 10);
}
remove_filter('auth_cookie_expiration', $expiry, 10);
remove_filter('send_auth_cookies', $send, 10);
wp_set_current_user(1);
$say('auth cookies set and cleared, and signing out', $mask([$heard, $loggedOut, (array) get_user_meta($user, 'session_tokens', true)]));

$checks = [];
$_REQUEST['_wpnonce'] = wp_create_nonce('zz-action');
$checks[] = check_admin_referer('zz-action');
$_REQUEST['zz_nonce'] = wp_create_nonce('zz-other');
$checks[] = check_admin_referer('zz-other', 'zz_nonce');
$_REQUEST['_ajax_nonce'] = wp_create_nonce('zz-ajax');
$checks[] = check_ajax_referer('zz-ajax');
$_REQUEST['security'] = wp_create_nonce('zz-ajax');
$checks[] = check_ajax_referer('zz-ajax', 'security');
$_REQUEST['security'] = 'zz-wrong';
$checks[] = check_ajax_referer('zz-ajax', 'security', false);
unset($_REQUEST['_ajax_nonce'], $_REQUEST['security']);
$_REQUEST['_wpnonce'] = wp_create_nonce('zz-ajax');
$checks[] = check_ajax_referer('zz-ajax', false, false);
$heard = [];
add_action('check_admin_referer', $adminHeard = static function ($action, $result) use (&$heard) {
    $heard[] = ['check_admin_referer', $action, $result];
}, 10, 2);
add_action('check_ajax_referer', $ajaxHeard = static function ($action, $result) use (&$heard) {
    $heard[] = ['check_ajax_referer', $action, $result];
}, 10, 2);
$_REQUEST['_wpnonce'] = wp_create_nonce('zz-action');
check_admin_referer('zz-action');
check_ajax_referer('zz-action', false, false);
check_ajax_referer('zz-missing', false, false);
remove_action('check_admin_referer', $adminHeard, 10);
remove_action('check_ajax_referer', $ajaxHeard, 10);
unset($_REQUEST['_wpnonce'], $_REQUEST['zz_nonce']);
$say('nonce checks', [$checks, $heard]);

$redirects = [];
add_filter('wp_redirect', $redirectFilter = static function ($location, $status) use (&$redirects) {
    $redirects[] = ['wp_redirect', $location, $status];
    return $location;
}, 10, 2);
add_filter('wp_redirect_status', $statusFilter = static function ($status, $location) use (&$redirects) {
    $redirects[] = ['wp_redirect_status', $status, $location];
    return $status;
}, 10, 2);
add_filter('x_redirect_by', $byFilter = static function ($by, $status, $location) use (&$redirects) {
    $redirects[] = ['x_redirect_by', $by, $status, $location];
    return $by;
}, 10, 3);
$results = [];
foreach ([
    ['wp_redirect', home_url('/zz-target/'), 302, 'WordPress'], ['wp_redirect', '/relative path?a=b c', 301, 'Zz'], ['wp_redirect', '', 302, 'WordPress'], ['wp_redirect', "https://zz.example/x\r\nInjected: 1", 302, false],
    ['wp_safe_redirect', home_url('/safe/'), 302, 'WordPress'], ['wp_safe_redirect', 'https://evil.example/x', 302, 'WordPress'], ['wp_safe_redirect', '//evil.example/x', 307, 'WordPress'], ['wp_safe_redirect', '/local', 303, 'Zz'],
] as [$function, $location, $status, $by]) {
    $redirects = [];
    $results[] = [$function, $function === 'wp_redirect' ? wp_redirect($location, $status, $by) : wp_safe_redirect($location, $status, $by), $redirects];
}
add_filter('allowed_redirect_hosts', $hosts = static fn ($allowed) => array_merge($allowed, ['partner.example']));
$redirects = [];
$results[] = ['wp_safe_redirect, allowed host', wp_safe_redirect('https://partner.example/ok'), $redirects];
remove_filter('allowed_redirect_hosts', $hosts);
remove_filter('wp_redirect', $redirectFilter, 10);
remove_filter('wp_redirect_status', $statusFilter, 10);
remove_filter('x_redirect_by', $byFilter, 10);
$say('redirects', $mask($results));

update_user_meta($user, 'session_tokens', ['zzhash' => ['expiration' => time() + 100, 'login' => time()]]);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->users, ['user_activation_key' => 'zz:key'], ['ID' => $user]);
clean_user_cache($user);
$heard = [];
add_action('wp_set_password', $passwordHeard = static function ($password, $userId, $old) use (&$heard) {
    $heard[] = ['wp_set_password', $password, $userId, $old instanceof WP_User ? $old->user_login : gettype($old)];
}, 10, 3);
$passwordResult = wp_set_password('zz-new-pass', $user);
remove_action('wp_set_password', $passwordHeard, 10);
clean_user_cache($user);
$fresh = get_userdata($user);
$sessions = get_user_meta($user, 'session_tokens', true);
$say('passwords set', $mask([$passwordResult, wp_check_password('zz-new-pass', $fresh->user_pass, $user), wp_check_password('zz-first-pass', $fresh->user_pass, $user), $fresh->user_activation_key, is_array($sessions) ? array_keys($sessions) : $sessions, $heard]));

$notices = [];
foreach (['', 'admin', 'user', 'both'] as $notify) {
    $mail = [];
    $result = wp_new_user_notification($other, null, $notify);
    $notices[] = [$notify, $result, $mail, (string) get_userdata($other)->user_activation_key !== ''];
}
add_filter('wp_send_new_user_notification_to_admin', $noAdmin = '__return_false');
$mail = [];
wp_new_user_notification($other, null, 'both');
$notices[] = ['both, admin turned off', $mail];
remove_filter('wp_send_new_user_notification_to_admin', $noAdmin);
foreach ([get_userdata($other), get_userdata(1)] as $changed) {
    $mail = [];
    $notices[] = ['password changed', $changed->user_login, wp_password_change_notification($changed), $mail];
}
$say('new user and password notifications', $mask($notices));

$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'zz pl post', 'post_status' => 'publish', 'post_author' => $user, 'post_content' => 'x']);
$ids[$post] = 'post';
$commentOf = static function (array $extra) use (&$made, $post): int {
    $made['comments'][] = $id = (int) wp_insert_comment($extra + ['comment_post_ID' => $post, 'comment_content' => 'zz pl comment', 'comment_author' => 'Reader', 'comment_author_email' => 'reader@zz.example', 'comment_approved' => 1]);
    return $id;
};
$approved = $commentOf([]);
$held = $commentOf(['comment_approved' => 0, 'comment_content' => 'zz pl held']);
$own = $commentOf(['user_id' => $user, 'comment_author_email' => 'zz-pl-user@zz.example', 'comment_content' => 'zz pl own']);
$commentNotices = [];
foreach ([['wp_notify_postauthor', $approved], ['wp_notify_postauthor', $own], ['wp_notify_postauthor', 999999], ['wp_notify_moderator', $held], ['wp_notify_moderator', $approved]] as [$function, $id]) {
    $mail = [];
    $commentNotices[] = [$function, $function === 'wp_notify_postauthor' ? wp_notify_postauthor($id) : wp_notify_moderator($id), $mail];
}
update_option('moderation_notify', '0');
$mail = [];
$commentNotices[] = ['moderation off', wp_notify_moderator($held), $mail];
update_option('moderation_notify', $moderationNotify);
$say('comment notifications', $mask($commentNotices));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
