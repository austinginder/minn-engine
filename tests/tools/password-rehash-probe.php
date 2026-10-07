<?php
/**
 * Password hashes and their upkeep (probe password-rehash): wp_hash_password
 * at the PHP default bcrypt cost and through wp_hash_password_options and
 * wp_hash_password_algorithm, wp_password_needs_rehash for each kind of
 * stored hash and through password_needs_rehash, and a sign-in that finds an
 * outdated hash and writes a fresh one (wp_set_password). Costs are reported
 * against the running PHP's default, so the rows hold on PHP 8.3 (cost 10)
 * and 8.4+ (cost 12) alike. Same protocol as api-probe.php; the user it makes
 * is removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$default = (int) substr(password_hash('x', PASSWORD_BCRYPT), 4, 2);
$cost = static fn (string $hash): ?int => preg_match('/^(?:\$wp)?\$2y\$(\d\d)\$/', $hash, $m) ? (int) $m[1] - $default : null;
$pre = static fn (string $password): string => base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));

$hash = wp_hash_password('pw');
$say('wp_hash_password: scheme, cost against the default, verifies', [substr($hash, 0, 7), $cost($hash), wp_check_password('pw', $hash)]);

$heard = [];
$options = static function ($options, $algorithm = null) use (&$heard) {
    $heard[] = ['wp_hash_password_options', $options, $algorithm];
    return ['cost' => 11];
};
$algorithm = static function ($value) use (&$heard) {
    $heard[] = ['wp_hash_password_algorithm', $value];
    return $value;
};
add_filter('wp_hash_password_options', $options, 10, 2);
add_filter('wp_hash_password_algorithm', $algorithm);
$filtered = wp_hash_password('pw');
$say('wp_hash_password with options: absolute cost, filters heard', [preg_match('/\$2y\$(\d\d)\$/', $filtered, $m) ? (int) $m[1] : null, $heard]);
$heard = [];
$say('wp_password_needs_rehash under those options', [wp_password_needs_rehash($filtered), wp_password_needs_rehash($hash)]);
$say('…and the filters heard while asking', array_map(static fn ($h) => $h[0], $heard));
remove_filter('wp_hash_password_options', $options, 10);
remove_filter('wp_hash_password_algorithm', $algorithm);

$older = '$wp' . password_hash($pre('pw'), PASSWORD_BCRYPT, ['cost' => $default + 1]);
$plain = password_hash('pw', PASSWORD_BCRYPT, ['cost' => $default]);
$phpass = '$P$BWVxQFrPkxBwTMDdpoK2Pg8u8rGTV.1';
$say('wp_password_needs_rehash: default, other cost, plain bcrypt, phpass, empty', [wp_password_needs_rehash($hash), wp_password_needs_rehash($older), wp_password_needs_rehash($plain), wp_password_needs_rehash($phpass), wp_password_needs_rehash('')]);
$asked = [];
$needs = static function ($needs, $stored = null, $user = null) use (&$asked) {
    $asked[] = [$needs, is_string($stored) ? substr($stored, 0, 7) : $stored, $user];
    return $needs;
};
add_filter('password_needs_rehash', $needs, 10, 3);
wp_password_needs_rehash($older, 7);
remove_filter('password_needs_rehash', $needs, 10);
$say('password_needs_rehash heard', $asked);

if (!class_exists('PasswordHash')) {
    require_once ABSPATH . WPINC . '/class-phpass.php';
}
$portable = (new PasswordHash(8, true))->HashPassword('pw');
$say('a legacy phpass hash: verifies, needs a rehash', [substr($portable, 0, 3), wp_check_password('pw', $portable), wp_check_password('nope', $portable), wp_password_needs_rehash($portable)]);
if (defined('PASSWORD_ARGON2ID')) {
    $argon = static fn () => PASSWORD_ARGON2ID;
    add_filter('wp_hash_password_algorithm', $argon);
    $a = wp_hash_password('pw');
    $say('with the algorithm argon2id: scheme, verifies, needs a rehash, a $wp$ hash needs one', [substr($a, 0, 10), wp_check_password('pw', $a), wp_password_needs_rehash($a), wp_password_needs_rehash($hash)]);
    remove_filter('wp_hash_password_algorithm', $argon);
    $say('…back on bcrypt, the argon2id hash still verifies and needs a rehash', [wp_check_password('pw', $a), wp_password_needs_rehash($a)]);
}

$login = 'zz-rehash-' . substr(md5((string) microtime(true)), 0, 6);
$id = wp_insert_user(['user_login' => $login, 'user_pass' => 'pw', 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
$set = [];
$onSet = static function ($password, $user) use (&$set) {
    $set[] = [$password, $user > 0];
};
try {
    $wpdb->update($wpdb->users, ['user_pass' => $older], ['ID' => $id]);
    clean_user_cache($id);
    add_action('wp_set_password', $onSet, 10, 2);
    $user = wp_authenticate($login, 'pw');
    remove_action('wp_set_password', $onSet, 10);
    $after = (string) $wpdb->get_var($wpdb->prepare("SELECT user_pass FROM {$wpdb->users} WHERE ID = %d", $id));
    $say('a sign-in with an outdated hash: signed in, cost after, wp_set_password heard', [$user instanceof WP_User, $cost($after), $set]);
    $set = [];
    add_action('wp_set_password', $onSet, 10, 2);
    wp_authenticate($login, 'pw');
    remove_action('wp_set_password', $onSet, 10);
    $say('…a second sign-in leaves the fresh hash alone', [$set, (string) $wpdb->get_var($wpdb->prepare("SELECT user_pass FROM {$wpdb->users} WHERE ID = %d", $id)) === $after]);
    $wpdb->update($wpdb->users, ['user_pass' => $older], ['ID' => $id]);
    clean_user_cache($id);
    wp_authenticate($login, 'wrong');
    $say('a failed sign-in leaves the outdated hash', (string) $wpdb->get_var($wpdb->prepare("SELECT user_pass FROM {$wpdb->users} WHERE ID = %d", $id)) === $older);
    $wpdb->update($wpdb->users, ['user_pass' => $portable], ['ID' => $id]);
    clean_user_cache($id);
    $user = wp_authenticate($login . '@example.test', 'pw');
    $after = (string) $wpdb->get_var($wpdb->prepare("SELECT user_pass FROM {$wpdb->users} WHERE ID = %d", $id));
    $say('a sign-in by email with a phpass hash: signed in, scheme and cost after', [$user instanceof WP_User, substr($after, 0, 7), $cost($after)]);
} finally {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($id);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
