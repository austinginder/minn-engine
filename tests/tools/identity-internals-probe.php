<?php
/**
 * The identity classes' remaining internals (probe identity-internals):
 * WP_Roles and WP_User reached the old ways (the deprecated reinit,
 * _init, _init_caps and for_blog, through __call too) and the roles data
 * they read; WP_Application_Passwords hashing and checking a password
 * (new hashes and old phpass ones) and storing a user's list; and phpass
 * itself, PasswordHash: its properties, its old-style constructor, its
 * base-64 alphabet, its salts and its hashing, portable or not. The
 * probe's user is removed at the end; the administrator's roles option is
 * only read.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => []];
register_shutdown_function(static function () use (&$made): void {
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// WP-CLI loads phpass only when something asks for it, as wp_hash_password does.
if (!class_exists('PasswordHash')) {
    require_once ABSPATH . WPINC . '/class-phpass.php';
}
// A protected method, called from inside its class.
$reach = static fn (object $object, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args);
$reachStatic = static fn (string $class, string $method, ...$args) => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, $class)(...$args);

$subscriber = wp_insert_user(['user_login' => 'zzidentity', 'user_email' => 'zzidentity@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
if (!is_int($subscriber) || $subscriber < 2) {
    fwrite(STDERR, "identity-internals: the subscriber could not be made\n");
    exit(1);
}
$made['users'][] = $subscriber;

$roles = wp_roles();
$data = $reach($roles, 'get_roles_data');
$say('the roles data', [
    'the role names' => is_array($data) ? array_keys($data) : $data,
    'the subscriber\'s capabilities' => $data['subscriber']['capabilities'] ?? null,
    'what get_option holds' => is_array($data) && $data === get_option(wp_roles()->role_key),
]);
$deprecated = [];
$say('roles reached the old ways', [
    'reinit' => $try(static fn () => [$roles->reinit(), array_keys($roles->get_names())]),
    '_init through __call' => $try(static fn () => [$roles->_init(), count($roles->roles)]),
    'something else through __call' => $try(static fn () => $roles->zz_not_a_method('x')),
    '_init from inside' => $try(static fn () => [$reach($roles, '_init'), count($roles->role_objects)]),
    'deprecations announced' => $deprecated,
]);

$deprecated = [];
$user = new WP_User($subscriber);
$user->caps = ['zz-stale' => true];
$userState = static fn (WP_User $u) => ['cap_key' => $u->cap_key, 'caps' => $u->caps, 'roles' => $u->roles, 'has read' => $u->has_cap('read'), 'has zz-stale' => $u->has_cap('zz-stale')];
$say('a user reached the old ways', [
    '_init_caps through __call' => $try(static fn () => [$user->_init_caps(), $userState($user)]),
    '_init_caps with a key of its own' => $try(static function () use ($user, $reach, $userState) {
        $returned = $reach($user, '_init_caps', 'zz_no_such_capabilities');
        return [$returned, $userState($user)];
    }),
    '_init_caps from inside, the default key' => $try(static fn () => [$reach($user, '_init_caps'), $userState($user)]),
    'for_blog' => $try(static fn () => [$user->for_blog(), $user->get_site_id(), $userState($user)]),
    'something else through __call' => $try(static fn () => $user->zz_not_a_method('x')),
    'deprecations announced' => $deprecated,
]);

// Application passwords: the hash is the site's fast hash, the same for the same password.
$hash = WP_Application_Passwords::hash_password('zz-app-password-1234');
$phpass = (new PasswordHash(8, true))->HashPassword('zz-app-password-1234');
$say('application password hashes', [
    'a hash' => $hash,
    'the same again' => WP_Application_Passwords::hash_password('zz-app-password-1234') === $hash,
    'another password' => WP_Application_Passwords::hash_password('zz-other'),
    'an empty one' => WP_Application_Passwords::hash_password(''),
    'checked' => WP_Application_Passwords::check_password('zz-app-password-1234', $hash),
    'checked with the wrong one' => WP_Application_Passwords::check_password('zz-wrong', $hash),
    'an old phpass hash, checked' => WP_Application_Passwords::check_password('zz-app-password-1234', $phpass),
    'an old phpass hash, wrong' => WP_Application_Passwords::check_password('zz-wrong', $phpass),
    'a bcrypt hash, checked' => WP_Application_Passwords::check_password('zz-app-password-1234', password_hash('zz-app-password-1234', PASSWORD_BCRYPT, ['cost' => 4])),
    'nothing stored' => WP_Application_Passwords::check_password('zz-app-password-1234', ''),
]);
$list = [['uuid' => '0b7d1a6e-0000-4000-8000-0000000000aa', 'app_id' => '', 'name' => 'Zz Stored', 'password' => $hash, 'created' => 1700000000, 'last_used' => null, 'last_ip' => null]];
$say('a user\'s passwords stored', [
    'stored' => $try(static fn () => is_int($id = $reachStatic(WP_Application_Passwords::class, 'set_user_application_passwords', $subscriber, $list)) && $id > 0 ? 'a new meta id' : $id),
    'read back' => WP_Application_Passwords::get_user_application_passwords($subscriber),
    'stored again, the same' => $try(static fn () => $reachStatic(WP_Application_Passwords::class, 'set_user_application_passwords', $subscriber, $list)),
    'stored empty' => $try(static fn () => $reachStatic(WP_Application_Passwords::class, 'set_user_application_passwords', $subscriber, [])),
    'read back empty' => WP_Application_Passwords::get_user_application_passwords($subscriber),
]);

// phpass: everything but the random state is the same on both stacks.
$portable = new PasswordHash(8, true);
$strong = new PasswordHash(10, false);
$vars = static fn (PasswordHash $h) => array_diff_key(get_object_vars($h), ['random_state' => 1]);
$fixed = "\x01\x23\x45\x67\x89\xab\xcd\xef\xfe\xdc\xba\x98\x76\x54\x32\x10";
$say('phpass', [
    'properties, portable' => $vars($portable),
    'properties, not portable, wide count' => $vars($strong),
    'counts kept in range' => [$vars(new PasswordHash(2, true))['iteration_count_log2'] ?? null, $vars(new PasswordHash(40, true))['iteration_count_log2'] ?? null],
    'the old-style constructor' => $try(static function () use ($vars) {
        $h = new PasswordHash(8, true);
        $returned = $h->PasswordHash(12, false);
        return [$returned, $vars($h)];
    }),
    'encode64' => [$portable->encode64($fixed, 16), $portable->encode64($fixed, 6), $portable->encode64("\xff\xff\xff", 3)],
    'gensalt_private' => [$portable->gensalt_private($fixed), (new PasswordHash(30, true))->gensalt_private($fixed)],
    'gensalt_blowfish' => [$strong->gensalt_blowfish($fixed), $portable->gensalt_blowfish($fixed)],
    'crypt_private' => [
        $portable->crypt_private('zz-secret', '$P$Bdefghijk'),
        $portable->crypt_private('zz-secret', $portable->gensalt_private($fixed)),
        $portable->crypt_private('zz-secret', '$H$9abcdefgh'),
        $portable->crypt_private('zz-secret', '*0'),
        $portable->crypt_private('zz-secret', '$P$Babc'),
        $portable->crypt_private('zz-secret', '$X$Babcdefgh'),
    ],
    'a portable hash' => [strlen($p = $portable->HashPassword('zz-secret')), substr($p, 0, 4), $portable->CheckPassword('zz-secret', $p), $portable->CheckPassword('zz-wrong', $p)],
    'a strong hash' => [strlen($b = $strong->HashPassword('zz-secret')), substr($b, 0, 7), $strong->CheckPassword('zz-secret', $b), $strong->CheckPassword('zz-wrong', $b), $portable->CheckPassword('zz-secret', $b)],
    'a password too long' => [$portable->HashPassword(str_repeat('z', 4097)), $portable->CheckPassword(str_repeat('z', 4097), $p)],
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
