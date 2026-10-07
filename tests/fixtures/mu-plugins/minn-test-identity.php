<?php
/**
 * Plugin Name: Minn test identity
 * Description: Fixture for the identity suite, loaded by the engine and the reference alike. A request that carries X-Minn-Identity naming a token the suite wrote (wp-content/minn-identity/<token>.json, holding a user id) is that user through determine_current_user, as a token plugin (JWT, OAuth, single sign-on) signs requests in; a token holding 0 signs the request out. Without such a file the header does nothing.
 * License: MIT
 */

$minnIdentityToken = (string) ($_SERVER['HTTP_X_MINN_IDENTITY'] ?? '');
$minnIdentityFile = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . "/minn-identity/{$minnIdentityToken}.json";
if (preg_match('/^[a-f0-9]{32}$/', $minnIdentityToken) !== 1 || !is_file($minnIdentityFile)) {
    return;
}
$minnIdentityUser = (int) (json_decode((string) file_get_contents($minnIdentityFile), true)['user'] ?? 0);
add_filter('determine_current_user', static fn ($user) => $minnIdentityUser, 30);
