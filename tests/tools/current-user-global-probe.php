<?php
/**
 * The $current_user global as the reference keeps it (probe
 * current-user-global): the user wp_get_current_user() answers is the
 * global, wp_set_current_user() replaces it (or hands back the same object
 * for the same user), and the object carries its capabilities, which plugins
 * read straight from the global. The probe's user changes are put back on
 * the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$was = get_current_user_id();
$user = wp_get_current_user();
$say('the global is the current user', ($GLOBALS['current_user'] ?? null) === $user);
$admin = wp_set_current_user(1);
$say('after wp_set_current_user(1)', [
    'the global' => ($GLOBALS['current_user'] ?? null) instanceof WP_User ? $GLOBALS['current_user']->ID : null,
    'is the object returned' => ($GLOBALS['current_user'] ?? null) === $admin,
    'is wp_get_current_user()' => wp_get_current_user() === ($GLOBALS['current_user'] ?? null),
    'allcaps an array' => is_array($GLOBALS['current_user']->allcaps ?? null),
    'manage_options in allcaps' => !empty($GLOBALS['current_user']->allcaps['manage_options']),
    'administrator in allcaps' => !empty($GLOBALS['current_user']->allcaps['administrator']),
]);
$say('the same user again is the same object', wp_set_current_user(1) === $admin);
$author = wp_set_current_user(3);
$say('after wp_set_current_user(3)', [($GLOBALS['current_user'] ?? null) === $author, get_current_user_id(), $GLOBALS['current_user']->ID ?? null]);
$nobody = wp_set_current_user(0);
$say('after wp_set_current_user(0)', [($GLOBALS['current_user'] ?? null) === $nobody, $GLOBALS['current_user']->ID ?? null, is_user_logged_in()]);
wp_set_current_user($was);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
