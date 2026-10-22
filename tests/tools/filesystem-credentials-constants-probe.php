<?php
/**
 * request_filesystem_credentials with the FTP constants a wp-config.php
 * sets, defined one by one (a constant stays for the rest of the run):
 * FTP_SSL, the key paths, FTP_PASS, then the host and user that complete
 * the connection (with an error asked again, and the REST plugins
 * controller's filesystem check), then FTP_SSH beside FTP_SSL. The form
 * each leaves, what each returns and stores. Same protocol as
 * api-probe.php.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
$out = [];
$heldFilters = $GLOBALS['wp_filter']['filesystem_method'] ?? null;
remove_all_filters('filesystem_method');
$heldOption = get_option('ftp_credentials', null);
delete_option('ftp_credentials');
$heldPost = $_POST;
$nonce = wp_create_nonce('filesystem-credentials');
$norm = static fn ($value) => is_string($value) ? str_replace([$nonce, home_url()], ['{nonce}', '{home}'], $value) : $value;
$ask = static function (string $label, array $args, array $post = []) use (&$out, $norm) {
    $_POST = $post;
    ob_start();
    $answer = request_filesystem_credentials(...$args);
    $page = $norm((string) ob_get_clean());
    $out[] = [$label, [$answer, $page, get_option('ftp_credentials', null)]];
    delete_option('ftp_credentials');
};
$ssh = static fn ($types) => $types + ['ssh' => 'SSH2'];
add_filter('filesystem_method', static fn () => 'ftpext');
$url = home_url('/wp-admin/x.php');
define('FTP_SSL', true);
$ask('FTP_SSL: the form', [$url, '', false, false, null]);
$ask('FTP_SSL: a type posted', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'password' => 'p', 'connection_type' => 'ftp', '_fs_nonce' => $nonce]);
define('FTP_PUBKEY', '/c.pub');
define('FTP_PRIKEY', '/c');
add_filter('fs_ftp_connection_types', $ssh);
$ask('the key paths: the form', [$url, '', false, false, null]);
remove_filter('fs_ftp_connection_types', $ssh);
$ask('the key paths, ftps: host and user posted', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', '_fs_nonce' => $nonce]);
define('FTP_PASS', 'cp');
$ask('FTP_PASS: the form', [$url, '', false, false, null]);
$ask('FTP_PASS: host and user posted', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'password' => 'ignored', '_fs_nonce' => $nonce]);
define('FTP_HOST', 'ch.invalid:2121');
define('FTP_USER', 'cu');
$ask('complete', [$url, '', false, false, null], ['hostname' => 'ignored', 'username' => 'ignored', '_fs_nonce' => $nonce]);
$ask('complete, an error', [$url, '', true, false, null]);
$ask('complete, an error object', [$url, '', new WP_Error('zz', 'Zz'), false, null]);
$controller = new WP_REST_Plugins_Controller();
$check = new ReflectionMethod($controller, 'is_filesystem_available');
ob_start();
$available = $check->invoke($controller);
$out[] = ['rest: complete', [is_wp_error($available) ? $available->get_error_code() : $available, ob_get_clean(), get_option('ftp_credentials', null)]];
delete_option('ftp_credentials');
define('FTP_SSH', true);
$ask('FTP_SSH beside FTP_SSL', [$url, '', false, false, null]);

$_POST = $heldPost;
if ($heldOption === null) {
    delete_option('ftp_credentials');
} else {
    update_option('ftp_credentials', $heldOption);
}
remove_all_filters('filesystem_method');
if ($heldFilters !== null) {
    $GLOBALS['wp_filter']['filesystem_method'] = $heldFilters;
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
