<?php
/**
 * get_filesystem_method and request_filesystem_credentials, as an upgrader
 * or a plugin's own screen asks them: the method by context (a folder the
 * site can write, one it cannot, one that is not there), what
 * filesystem_method hears; then, with a method that is not direct, the
 * credentials form (its connection types and their filter, an error, the
 * extra fields, a stored connection, a posted one with and without its
 * nonce, ports, SSH keys) and what a complete submission returns and
 * stores; the REST plugins controller's filesystem check; FS_METHOD last.
 * WP-CLI's own filesystem_method filter is set aside while it runs. Same
 * protocol as api-probe.php.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
$out = [];
$heldFilters = $GLOBALS['wp_filter']['filesystem_method'] ?? null;
remove_all_filters('filesystem_method');
$heldOption = get_option('ftp_credentials', null);
delete_option('ftp_credentials');
$heldPost = $_POST;
$nonce = wp_create_nonce('filesystem-credentials');
$content = untrailingslashit(WP_CONTENT_DIR);
$norm = static fn ($value) => is_string($value) ? str_replace([$nonce, home_url(), $content], ['{nonce}', '{home}', '{content}'], $value) : $value;
$heard = [];
$listen = static function ($method, $args, $context, $relaxed) use (&$heard, $norm) {
    $heard[] = [$method, $args, $norm($context), $relaxed];
    return $method;
};
add_filter('filesystem_method', $listen, 20, 4);
$method = static function (string $label, array $args) use (&$out, &$heard) {
    $heard = [];
    $out[] = [$label, [get_filesystem_method(...$args), $heard]];
};
$method('method', []);
$method('method: content, relaxed', [['x' => 1], WP_CONTENT_DIR, true]);
$method('method: a folder it cannot write', [[], '/']);
$method('method: a folder that is not there', [[], '/nonexistent/zz']);
$method('method: a folder it cannot write, relaxed', [[], '/usr/', true]);

$ask = static function (string $label, array $args, array $post = [], ?callable $before = null) use (&$out, &$heard, $norm) {
    $_POST = $post;
    $heard = [];
    if ($before !== null) {
        $before();
    }
    ob_start();
    $answer = request_filesystem_credentials(...$args);
    $page = $norm((string) ob_get_clean());
    $out[] = [$label, [is_array($answer) ? array_map($norm, $answer) : $answer, $page, get_option('ftp_credentials', null), $heard]];
    delete_option('ftp_credentials');
};
$url = home_url('/wp-admin/x.php');
$ask('direct', [$url]);
$ftp = static fn () => 'ftpext';
add_filter('filesystem_method', $ftp);
$ask('ftp: the form', [$url, '', false, false, null]);
$ask('ftp: a context and relaxed ownership', [$url, '', false, '/usr', null, true]);
$ask('a type named', [$url, 'ftpsockets', false, '/usr', null, true]);
$ask('a type named direct', [$url, 'direct']);
$notices = [];
$noticeListen = static function ($message, $args) use (&$notices) {
    $notices[] = [$message, $args];
};
add_action('wp_admin_notice', $noticeListen, 10, 2);
$ask('an error object', [$url, '', new WP_Error('zz', 'Zz <b>bad</b> & co'), false, null]);
$ask('an error object with no message', [$url, '', new WP_Error(), false, null]);
$ask('an error that is true', [$url, '', true, false, null]);
$ask('an error that is text', [$url, '', 'Zz text', false, null]);
remove_action('wp_admin_notice', $noticeListen, 10);
$out[] = ['errors: the notices', $notices];
$ask('extra fields', [$url, '', false, false, ['zz_a', 'zz_b', 'zz_c', 'zz_d']], ['zz_a' => 'A "q" <x>', 'zz_b' => "b\\'b", 'zz_c' => '', 'zz_e' => 'e']);
$ask('the address escaped', [home_url('/wp-admin/x.php?a=1&b="2"<'), '', false, false, null]);
$ask('a stored connection', [$url, '', false, false, null], [], static fn () => update_option('ftp_credentials', ['hostname' => 'h.example:99', 'username' => 'zzuser', 'connection_type' => 'ftps']));
$ask('posted, no nonce', [$url, '', false, false, null], ['hostname' => 'h.example', 'username' => 'u1', 'password' => 'p1', 'connection_type' => 'ftp']);
$ask('posted, a wrong nonce', [$url, '', false, false, null], ['hostname' => 'h.example', 'username' => 'u1', 'password' => 'p1', '_fs_nonce' => 'abc']);
$ask('posted with a port', [$url, '', false, false, null], ['hostname' => 'h.example:2121', 'username' => 'u1', 'password' => 'p1', 'connection_type' => 'ftp', '_fs_nonce' => $nonce]);
$_POST = ['hostname' => 'h', 'username' => 'u', 'password' => 'p', '_fs_nonce' => $nonce];
ob_start();
request_filesystem_credentials($url, '', false, false, null);
ob_end_clean();
$out[] = ['stored: autoloaded', $GLOBALS['wpdb']->get_var("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'ftp_credentials'")];
delete_option('ftp_credentials');
$ask('posted, no password', [$url, '', false, false, null], ['hostname' => 'h.example:2121', 'username' => 'u1', 'connection_type' => 'ftp', '_fs_nonce' => $nonce]);
$ask('posted, no type', [$url, '', false, false, null], ['hostname' => ' h.example ', 'username' => 'u1', 'password' => 'p1', '_fs_nonce' => $nonce]);
$ask('posted, an unknown type', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'password' => 'p', 'connection_type' => 'zz', '_fs_nonce' => $nonce]);
$ask('posted, no host', [$url, '', false, false, null], ['hostname' => '', 'username' => 'u1', 'password' => 'p1', '_fs_nonce' => $nonce]);
$ask('posted, no user', [$url, '', false, false, null], ['hostname' => 'h', 'username' => '', 'password' => 'p1', '_fs_nonce' => $nonce]);
$ask('posted, slashed', [$url, '', false, false, null], ['hostname' => "h\\'x", 'username' => "u\\'1", 'password' => "p\\'1", '_fs_nonce' => $nonce]);
$ask('posted, values to escape', [$url, '', false, false, null], ['hostname' => 'h"<x>', 'username' => 'u"<y>', '_fs_nonce' => $nonce]);
foreach (['h:0', 'h:', 'h:21x', 'h: 21', 'h:+21', 'h:1e3', 'h:021', '[::1]:21', 'a:b:21'] as $host) {
    $ask("posted host {$host}", [$url, '', false, false, null], ['hostname' => $host, 'username' => 'u', 'password' => 'p', '_fs_nonce' => $nonce]);
    $ask("posted host {$host}, no password", [$url, '', false, false, null], ['hostname' => $host, 'username' => 'u', '_fs_nonce' => $nonce]);
}
$ask('stored, a password posted', [$url, '', false, false, null], ['password' => 'p1', '_fs_nonce' => $nonce], static fn () => update_option('ftp_credentials', ['hostname' => 'oh.example:99', 'username' => 'ou', 'connection_type' => 'ftps']));
$ask('stored with keys of its own', [$url, '', false, false, null], ['password' => 'p', '_fs_nonce' => $nonce], static fn () => update_option('ftp_credentials', ['zz' => 'keep', 'hostname' => 'oh', 'username' => 'ou', 'port' => '77']));
$ask('stored, empty values posted', [$url, '', false, false, null], ['hostname' => '', 'username' => '', 'password' => 'p', 'connection_type' => '', '_fs_nonce' => $nonce], static fn () => update_option('ftp_credentials', ['hostname' => 'oh', 'username' => 'ou', 'connection_type' => 'ftps']));
$ask('stored, a host of 0 posted', [$url, '', false, false, null], ['hostname' => '0', 'username' => 'u', 'password' => 'p', '_fs_nonce' => $nonce], static fn () => update_option('ftp_credentials', ['hostname' => 'oh', 'username' => 'ou']));
$ask('posted ssh, keys, no password', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'connection_type' => 'ssh', 'public_key' => '/k.pub', 'private_key' => '/k', '_fs_nonce' => $nonce]);
$ask('posted ssh, one key, no password', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'connection_type' => 'ssh', 'public_key' => '/k.pub', '_fs_nonce' => $nonce]);
$typesWith = static fn (array $types) => static fn ($given) => $types === [] ? $given : (array_key_exists(0, $types) ? $given + $types[0] : $types);
foreach ([
    'ssh added' => [['ssh' => 'SSH2']],
    'only ssh' => ['ssh' => 'SSH2'],
    'only ftps' => ['ftps' => 'FTPS'],
    'ftps and ssh' => ['ftps' => 'FTPS', 'ssh' => 'SSH2'],
    'one of its own' => ['zz' => 'Only'],
    'one of its own added' => [['zz' => 'Zz Type']],
] as $label => $types) {
    $filter = $typesWith($types);
    add_filter('fs_ftp_connection_types', $filter);
    $ask("types, {$label}", [$url, '', false, false, null]);
    if ($label === 'ssh added') {
        $ask('types, ssh added, ssh named', [$url, 'ssh', false, false, null]);
        $ask('types, ssh added, ssh stored', [$url, '', false, false, null], [], static fn () => update_option('ftp_credentials', ['hostname' => 'h', 'username' => 'u', 'connection_type' => 'ssh']));
        $ask('types, ssh added, one key posted', [$url, '', false, false, null], ['hostname' => 'h', 'username' => 'u', 'connection_type' => 'ssh', 'public_key' => '/k.pub', '_fs_nonce' => $nonce]);
    }
    remove_filter('fs_ftp_connection_types', $filter);
}
$typesHeard = [];
$typesListen = static function ($types, $credentials, $type, $error, $context) use (&$typesHeard) {
    $typesHeard[] = [$types, $credentials, $type, is_object($error) ? get_class($error) : $error, $context];
    return $types;
};
add_filter('fs_ftp_connection_types', $typesListen, 10, 5);
$ask('types: what the filter hears', [$url, 'ftpsockets', true, '/usr', null]);
remove_filter('fs_ftp_connection_types', $typesListen, 10);
$out[] = ['types: heard', $typesHeard];
$requestHeard = [];
$requestListen = static function ($value, ...$args) use (&$requestHeard) {
    $requestHeard[] = $args;
    return $value;
};
add_filter('request_filesystem_credentials', $requestListen, 10, 7);
$ask('request filter: what it hears', [$url, '', false, false, ['zz'], true]);
remove_filter('request_filesystem_credentials', $requestListen, 10);
$out[] = ['request filter: heard', $requestHeard];
$answered = static fn () => ['zz' => 1];
add_filter('request_filesystem_credentials', $answered);
$ask('request filter: an answer', [$url, '', false, false, null]);
remove_filter('request_filesystem_credentials', $answered);
$controller = new WP_REST_Plugins_Controller();
$check = new ReflectionMethod($controller, 'is_filesystem_available');
$requestHeard = [];
add_filter('request_filesystem_credentials', $requestListen, 10, 7);
ob_start();
$available = $check->invoke($controller);
$out[] = ['rest: no credentials', [is_wp_error($available) ? [$available->get_error_code(), $available->get_error_message(), $available->get_error_data()] : $available, ob_get_clean(), array_map(static fn (array $args) => array_map($norm, $args), $requestHeard)]];
update_option('ftp_credentials', ['hostname' => 'h', 'username' => 'u']);
ob_start();
$available = $check->invoke($controller);
$out[] = ['rest: stored, no password', [is_wp_error($available) ? $available->get_error_code() : $available, ob_get_clean()]];
remove_filter('request_filesystem_credentials', $requestListen, 10);
delete_option('ftp_credentials');
remove_filter('filesystem_method', $ftp);
$false = static fn () => false;
add_filter('filesystem_method', $false);
$ask('a method of false', [$url, '', false, false, null]);
remove_filter('filesystem_method', $false);
define('FS_METHOD', 'ftpsockets');
$method('FS_METHOD', []);
$method('FS_METHOD, a folder it cannot write', [[], '/']);

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
