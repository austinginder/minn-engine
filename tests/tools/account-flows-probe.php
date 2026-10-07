<?php
/**
 * The account functions a sign-in page and plugins call, as the reference
 * answers them (probe account-flows): retrieve_password (a user, nobody,
 * no name, reset not allowed), check_password_reset_key (good and bad),
 * reset_password, and register_new_user (a new user, a taken name, empty
 * and malformed fields, a plugin's registration error). Each answer as its
 * shape, the hooks it fires, and the mail it would send (who to, the
 * subject), mail held back through pre_wp_mail. The probe's own users,
 * removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$mail = [];
add_filter('pre_wp_mail', static function ($sent, $atts) use (&$mail) {
    $mail[] = [is_array($atts['to']) ? implode(',', $atts['to']) : (string) $atts['to'], (string) $atts['subject']];
    return true;
}, 10, 2);
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(lostpassword_post|lostpassword_user_data|retrieve_password|retrieve_password_key|allow_password_reset|retrieve_password_title|retrieve_password_message|retrieve_password_notification_email|lostpassword_errors|send_retrieve_password_email|password_reset|after_password_reset|validate_password_reset|wp_set_password|register_post|registration_errors|user_register|register_new_user|wp_new_user_notification_email|wp_new_user_notification_email_admin|wp_send_new_user_notification_to_admin|wp_send_new_user_notification_to_user|illegal_user_logins|password_reset_expiration)$/', $hook)) {
        $fired[] = $hook;
    }
});
$made = [];
$userId = (int) wp_insert_user(['user_login' => 'zz_flows', 'user_pass' => 'Zz-old-pass-1', 'user_email' => 'zz-flows@example.com', 'role' => 'subscriber']);
$made[] = $userId;
$site = (string) get_option('blogname');
$admin = (string) get_option('admin_email');
// An answer as its shape: errors by code and message, users and ids by kind, the site's name and address masked.
$shape = static function ($value) use ($userId, $site, $admin) {
    if ($value instanceof WP_Error) {
        $out = [];
        foreach ($value->get_error_codes() as $code) {
            $out[$code] = $value->get_error_message($code);
        }
        return ['errors' => $out];
    }
    if ($value instanceof WP_User) {
        return ['user' => $value->ID === $userId ? 'zz_flows' : $value->user_login];
    }
    return is_string($value) ? str_replace([$site, $admin], ['{site}', '{admin}'], $value) : $value;
};
$call = static function (string $label, callable $fn) use ($say, $shape, &$fired, &$mail, $site, $admin): mixed {
    $fired = [];
    $mail = [];
    $result = $fn();
    $say($label, ['result' => is_int($result) && $result > 1 ? 'an id' : $shape($result), 'hooks' => array_values(array_unique($fired)), 'mail' => array_map(static fn ($m) => array_map(static fn ($s) => str_replace([$site, $admin], ['{site}', '{admin}'], $s), $m), $mail)]);
    return $result;
};

$call('retrieve a user\'s password', static fn () => retrieve_password('zz_flows'));
$call('retrieve by email', static fn () => retrieve_password('zz-flows@example.com'));
$call('retrieve nobody\'s password', static fn () => retrieve_password('zz_nobody'));
$call('retrieve with no name', static fn () => retrieve_password(''));
add_filter('allow_password_reset', '__return_false');
$call('retrieve when reset is not allowed', static fn () => retrieve_password('zz_flows'));
remove_filter('allow_password_reset', '__return_false');
$key = get_password_reset_key(get_userdata($userId));
$call('check a good key', static fn () => check_password_reset_key($key, 'zz_flows'));
$call('check a bad key', static fn () => check_password_reset_key('zzbadkey', 'zz_flows'));
$call('check a key for nobody', static fn () => check_password_reset_key($key, 'zz_nobody'));
$call('reset the password', static fn () => reset_password(get_userdata($userId), 'Zz-new-pass-2'));
$say('the new password works', wp_check_password('Zz-new-pass-2', get_userdata($userId)->user_pass, $userId));

$new = $call('register a user', static fn () => register_new_user('zz_flows_new', 'zz-flows-new@example.com'));
if (is_int($new)) {
    $made[] = $new;
    $say('the registered user', [get_userdata($new)->user_login, get_userdata($new)->user_email, get_userdata($new)->roles, (bool) get_user_meta($new, 'default_password_nag', true)]);
}
$call('register a taken name and email', static fn () => register_new_user('zz_flows', 'zz-flows@example.com'));
$call('register with nothing', static fn () => register_new_user('', ''));
$call('register malformed', static fn () => register_new_user('zz bad!', 'not-an-email'));
add_filter('registration_errors', static function ($errors) {
    $errors->add('zz_refused', 'Zz: no new accounts today.');
    return $errors;
});
$call('register refused by a plugin', static fn () => register_new_user('zz_flows_other', 'zz-flows-other@example.com'));

if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
foreach (array_merge($made, array_filter([username_exists('zz_flows_other')])) as $id) {
    wp_delete_user((int) $id, 1);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
