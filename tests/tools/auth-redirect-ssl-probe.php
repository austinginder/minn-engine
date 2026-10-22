<?php
/**
 * auth_redirect over plain HTTP for a signed-in user who chose an HTTPS
 * admin (the use_ssl user option) and one who did not, on a wp-admin
 * address and elsewhere, with secure_auth_redirect saying yes; and for no
 * one signed in. Each answer is where it redirects (caught at wp_redirect),
 * or that it let the request through, with the hooks it asked. A
 * throwaway user, its minted session destroyed and the user removed after.
 * Same protocol as api-probe.php.
 */
final class Zz_Auth_Redirect_Caught extends Exception
{
}
$saved = [$_SERVER, $_COOKIE];
$login = 'zz-auth-redirect-' . wp_generate_password(6, false);
$uid = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(20), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
$out = [];
if (is_int($uid) && $uid > 1) {
    $heard = [];
    $catch = static function ($location, $status) {
        throw new Zz_Auth_Redirect_Caught((string) $location . ' ' . $status);
    };
    $listen = static function (string $hook) use (&$heard) {
        return static function ($value = null) use ($hook, &$heard) {
            $heard[] = $hook;
            return $value;
        };
    };
    add_filter('wp_redirect', $catch, 10, 2);
    $scheme = $listen('auth_redirect_scheme');
    $secure = $listen('secure_auth_redirect');
    $action = $listen('auth_redirect');
    add_filter('auth_redirect_scheme', $scheme);
    add_filter('secure_auth_redirect', $secure);
    add_action('auth_redirect', $action);
    $ask = static function (string $label, string $uri, bool $signedIn, bool $useSsl, ?bool $secureAnswer = null) use (&$out, &$heard, $uid) {
        $heard = [];
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = $uri;
        $_COOKIE = [];
        if ($signedIn) {
            $_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($uid, time() + HOUR_IN_SECONDS, 'auth');
        }
        update_user_option($uid, 'use_ssl', $useSsl ? 1 : 0, true);
        $answer = $secureAnswer === null ? null : static fn () => $secureAnswer;
        if ($answer !== null) {
            add_filter('secure_auth_redirect', $answer, 20);
        }
        try {
            auth_redirect();
            $result = 'let through';
        } catch (Zz_Auth_Redirect_Caught $caught) {
            $result = str_replace(home_url(), '{home}', $caught->getMessage());
        }
        if ($answer !== null) {
            remove_filter('secure_auth_redirect', $answer, 20);
        }
        $out[] = [$label, [$result, $heard]];
    };
    $ask('signed in, use_ssl, wp-admin', '/wp-admin/edit.php?a=1', true, true);
    $ask('signed in, no use_ssl, wp-admin', '/wp-admin/edit.php?a=1', true, false);
    $ask('signed in, use_ssl, elsewhere', '/zz-page/', true, true);
    $ask('signed in, use_ssl, a full address', 'http://example.test/wp-admin/', true, true);
    $ask('signed in, no use_ssl, secure_auth_redirect true', '/wp-admin/', true, false, true);
    $ask('signed in, use_ssl, secure_auth_redirect false', '/wp-admin/', true, true, false);
    $ask('signed in, no use_ssl, secure_auth_redirect false', '/wp-admin/', true, false, false);
    $ask('signed in, use_ssl, secure_auth_redirect false, elsewhere', '/zz-page/', true, true, false);
    $ask('signed in, use_ssl, secure_auth_redirect false, a full address', 'http://example.test/wp-admin/x.php?b=2', true, true, false);
    $ask('signed in, use_ssl, secure_auth_redirect false, a query naming wp-admin', '/zz-page/?to=wp-admin', true, true, false);
    $ask('not signed in, wp-admin', '/wp-admin/', false, false);
    $ask('not signed in, secure_auth_redirect false', '/wp-admin/', false, false, false);
    remove_filter('wp_redirect', $catch, 10);
    remove_filter('auth_redirect_scheme', $scheme);
    remove_filter('secure_auth_redirect', $secure);
    remove_action('auth_redirect', $action);
    WP_Session_Tokens::get_instance($uid)->destroy_all();
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($uid);
}
[$_SERVER, $_COOKIE] = $saved;
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
