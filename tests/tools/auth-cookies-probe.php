<?php
/**
 * The sign-in cookies checked (probe auth-cookies): wp_validate_auth_cookie
 * for each scheme's cookie (auth, secure_auth, logged_in) given or read from
 * $_COOKIE, a cookie under the wrong scheme, expired (and the hour's grace a
 * POST gets), with a bad hash, an unknown user, a session no longer live, or
 * malformed, with the auth_cookie_* action each fires; and
 * wp_validate_logged_in_cookie. The probe's user and sessions are removed.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => []];
register_shutdown_function(static function () use (&$made): void {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
});
$id = (int) wp_insert_user(['user_login' => 'zz_ac_user', 'user_pass' => 'zz-ac-pass-123', 'user_email' => 'zz-ac-user@example.com', 'role' => 'subscriber']);
$made['users'][] = $id;

$heard = [];
$listeners = [];
foreach (['auth_cookie_malformed', 'auth_cookie_expired', 'auth_cookie_bad_username', 'auth_cookie_bad_hash', 'auth_cookie_bad_session_token', 'auth_cookie_valid'] as $hook) {
    $listeners[$hook] = static function ($first, $second = null) use ($hook, &$heard, $id): void {
        $describe = static function ($value) use ($id) {
            if (is_array($value)) {
                return ['keys' => array_keys($value), 'username' => $value['username'] ?? null, 'scheme' => $value['scheme'] ?? null];
            }
            if ($value instanceof WP_User) {
                return ['user' => $value->ID === $id];
            }
            return is_string($value) && strlen($value) > 20 ? 'a cookie' : $value;
        };
        $heard[] = [$hook, $describe($first), $describe($second)];
    };
    add_action($hook, $listeners[$hook], 10, 2);
}
$check = static function (Closure $call) use (&$heard, $id) {
    $heard = [];
    $result = $call();
    return [$result === $id ? 'the user' : $result, $heard];
};
$names = ['auth' => AUTH_COOKIE, 'secure_auth' => SECURE_AUTH_COOKIE, 'logged_in' => LOGGED_IN_COOKIE];
$withCookies = static function (array $jar, Closure $call) {
    $was = $_COOKIE;
    $_COOKIE = $jar;
    $out = $call();
    $_COOKIE = $was;
    return $out;
};

$auth = wp_generate_auth_cookie($id, time() + HOUR_IN_SECONDS, 'auth');
$secure = wp_generate_auth_cookie($id, time() + HOUR_IN_SECONDS, 'secure_auth');
$logged = wp_generate_auth_cookie($id, time() + HOUR_IN_SECONDS, 'logged_in');
[$user, $expiration, $token, $hmac] = explode('|', $auth);
$say('cookies given', [
    $check(static fn () => wp_validate_auth_cookie($auth, 'auth')),
    $check(static fn () => wp_validate_auth_cookie($secure, 'secure_auth')),
    $check(static fn () => wp_validate_auth_cookie($logged, 'logged_in')),
    $check(static fn () => wp_validate_auth_cookie($auth)),
    $check(static fn () => wp_validate_auth_cookie($auth, 'logged_in')),
    $check(static fn () => wp_validate_auth_cookie($logged, 'auth')),
    $check(static fn () => wp_validate_auth_cookie($auth, 'nonsense')),
]);

$expired = wp_generate_auth_cookie($id, time() - 30, 'auth');
$long = wp_generate_auth_cookie($id, time() - 2 * HOUR_IN_SECONDS, 'auth');
$say('expired', [
    $check(static fn () => wp_validate_auth_cookie($expired, 'auth')),
    (static function () use ($check, $expired, $long) {
        $was = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $out = [$check(static fn () => wp_validate_auth_cookie($expired, 'auth')), $check(static fn () => wp_validate_auth_cookie($long, 'auth'))];
        if ($was === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $was;
        }
        return $out;
    })(),
]);

$dead = wp_generate_auth_cookie($id, time() + HOUR_IN_SECONDS, 'auth');
WP_Session_Tokens::get_instance($id)->destroy(explode('|', $dead)[2]);
$say('refused', [
    $check(static fn () => wp_validate_auth_cookie($user . '|' . $expiration . '|' . $token . '|' . str_repeat('0', 64), 'auth')),
    $check(static fn () => wp_validate_auth_cookie('zz_ac_nobody|' . $expiration . '|' . $token . '|' . $hmac, 'auth')),
    $check(static fn () => wp_validate_auth_cookie($dead, 'auth')),
    $check(static fn () => wp_validate_auth_cookie('not a cookie', 'auth')),
    $check(static fn () => wp_validate_auth_cookie('a|b|c', 'auth')),
    $check(static fn () => wp_validate_auth_cookie($user . '|abc|' . $token . '|' . $hmac, 'auth')),
]);

$say('from $_COOKIE', [
    $withCookies([], static fn () => $check(static fn () => wp_validate_auth_cookie())),
    $withCookies([$names['auth'] => $auth], static fn () => $check(static fn () => wp_validate_auth_cookie())),
    $withCookies([$names['auth'] => $auth], static fn () => $check(static fn () => wp_validate_auth_cookie('', 'auth'))),
    $withCookies([$names['secure_auth'] => $secure], static fn () => $check(static fn () => wp_validate_auth_cookie('', 'secure_auth'))),
    $withCookies([$names['logged_in'] => $logged], static fn () => $check(static fn () => wp_validate_auth_cookie('', 'logged_in'))),
    $withCookies([$names['logged_in'] => $logged], static fn () => $check(static fn () => wp_validate_auth_cookie())),
]);

$say('logged-in cookie', [
    $withCookies([], static fn () => $check(static fn () => wp_validate_logged_in_cookie(false))),
    $withCookies([], static fn () => $check(static fn () => wp_validate_logged_in_cookie(7))),
    $withCookies([$names['logged_in'] => $logged], static fn () => $check(static fn () => wp_validate_logged_in_cookie(false))),
    $withCookies([$names['logged_in'] => $logged], static fn () => $check(static fn () => wp_validate_logged_in_cookie(7))),
    $withCookies([$names['logged_in'] => 'junk'], static fn () => $check(static fn () => wp_validate_logged_in_cookie(false))),
    $withCookies([$names['auth'] => $auth], static fn () => $check(static fn () => wp_validate_logged_in_cookie(false))),
]);

foreach ($listeners as $hook => $listener) {
    remove_action($hook, $listener, 10);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
