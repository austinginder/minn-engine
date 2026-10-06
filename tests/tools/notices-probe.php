<?php
/**
 * The notices a user is mailed when their password or email address
 * changes through wp_update_user: who gets which, worded how, and what the
 * filters see. Mail is caught at pre_wp_mail, nothing is sent. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace($home, '{home}', $v) : $v;
$mails = [];
$catch = static function ($null, $atts) use (&$mails, $rel) {
    $mails[] = [$atts['to'], $atts['subject'], $rel($atts['message']), $atts['headers']];
    return true;
};
add_filter('pre_wp_mail', $catch, 10, 2);
// A leftover from a crashed run, found by its exact login only: a probe never deletes by search.
$stale = get_user_by('login', 'zz-notice');
if ($stale instanceof WP_User && $stale->user_login === 'zz-notice') {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($stale->ID, 1);
}
$id = wp_insert_user(['user_login' => 'zz-notice', 'user_email' => 'zz-notice@minn-engine.localhost', 'user_pass' => 'Old-pass-1!', 'role' => 'author']);
$mails = [];
$seen = [];
$watch = static function ($email) use (&$seen) {
    $seen[] = [$email['subject'], $email['message']];
    return $email;
};
add_filter('password_change_email', $watch);
add_filter('email_change_email', $watch);
foreach ([
    'a new password' => ['user_pass' => 'New-pass-2!'],
    'a new email' => ['user_email' => 'zz-notice2@minn-engine.localhost'],
    'both' => ['user_pass' => 'Other-3!', 'user_email' => 'zz-notice3@minn-engine.localhost'],
    'neither' => ['first_name' => 'X'],
] as $label => $change) {
    $mails = [];
    wp_update_user(['ID' => $id] + $change);
    $say("wp_update_user with {$label}", $mails);
}
$say('what the filters see', $seen);
add_filter('send_password_change_email', '__return_false');
$mails = [];
wp_update_user(['ID' => $id, 'user_pass' => 'Fourth-4!']);
$say('send_password_change_email false', $mails);
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user($id, 1);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
