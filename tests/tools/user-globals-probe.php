<?php
/**
 * The old user globals ($user_ID, $user_login, $userdata, $user_level,
 * $user_email, $user_url, $user_identity) as the reference sets them (probe
 * user-globals): what wp_set_current_user() leaves in them, whether a
 * set_current_user callback already sees them, and what setup_userdata()
 * writes for the current user, another user, and none. The probe's user
 * changes are put back on the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$globals = static function (): array {
    $out = [];
    foreach (['user_ID', 'user_login', 'userdata', 'user_level', 'user_email', 'user_url', 'user_identity'] as $name) {
        if (!array_key_exists($name, $GLOBALS)) {
            $out[$name] = 'unset';
            continue;
        }
        $value = $GLOBALS[$name];
        $out[$name] = is_object($value) ? [get_class($value), $value->ID ?? null] : [gettype($value), $value];
    }
    return $out;
};
$was = get_current_user_id();
wp_set_current_user(0);
$say('after wp_set_current_user(0)', $globals());
$heard = null;
$listen = static function () use (&$heard, $globals): void {
    $heard = $globals();
};
add_action('set_current_user', $listen);
wp_set_current_user(1);
remove_action('set_current_user', $listen);
$say('a set_current_user callback sees', $heard);
$say('after wp_set_current_user(1)', $globals());
wp_set_current_user(3);
$say('after wp_set_current_user(3)', $globals());
setup_userdata(1);
$say('setup_userdata(1) while user 3 is current', $globals());
setup_userdata();
$say('setup_userdata() names the current user', $globals());
setup_userdata(987654);
$say('setup_userdata() of a user who does not exist', $globals());
wp_set_current_user(0);
$say('back to no one', $globals());
wp_set_current_user($was);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
