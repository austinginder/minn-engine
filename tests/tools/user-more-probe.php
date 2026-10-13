<?php
/**
 * user.php's remaining functions (probe user-more): the profile's extra meta
 * keys and contact methods, the sign-in cookie as an authenticate filter, the
 * users listed (wp_list_users), the user-count event scheduled, and the
 * confirmation sent when a profile asks for a new email address. The probe's
 * users, sessions and mail are removed or caught. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'tokens' => []];
$cronBefore = wp_get_scheduled_event('wp_update_user_counts');
register_shutdown_function(static function () use (&$made, $cronBefore): void {
    foreach ($made['tokens'] as [$userId, $token]) {
        WP_Session_Tokens::get_instance($userId)->destroy($token);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
    wp_clear_scheduled_hook('wp_update_user_counts');
    if ($cronBefore) {
        $cronBefore->schedule ? wp_schedule_event($cronBefore->timestamp, $cronBefore->schedule, 'wp_update_user_counts', $cronBefore->args) : wp_schedule_single_event($cronBefore->timestamp, 'wp_update_user_counts', $cronBefore->args);
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value);
    $value = (string) preg_replace('/\b[0-9a-f]{32}\b/', '{hash}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$user = static function (string $label, array $fields) use (&$made, &$ids): int {
    $id = (int) wp_insert_user($fields + ['user_login' => 'zz_um_' . str_replace(' ', '_', $label), 'user_pass' => 'zz-um-pass-123', 'user_email' => 'zz-um-' . str_replace(' ', '-', $label) . '@example.com', 'role' => 'subscriber']);
    $made['users'][] = $id;
    $ids[$id] = $label;
    return $id;
};
$error = static fn ($e) => is_wp_error($e) ? ['error', $e->get_error_codes(), $e->get_error_messages()] : ($e instanceof WP_User ? ['user', $e->ID] : $e);

// The profile's extra keys and contact methods.
$one = $user('one', ['first_name' => 'Ada', 'last_name' => 'Zed', 'display_name' => 'Ada Z.', 'user_url' => 'https://ada.example/']);
$two = $user('two', ['display_name' => 'Bea <b>Bold</b>', 'first_name' => 'Bea', 'last_name' => 'Bold']);
$say('additional user keys', [_get_additional_user_keys(get_userdata($one)), _wp_get_user_contactmethods(), _wp_get_user_contactmethods(get_userdata($one)), wp_get_user_contact_methods()]);
$methods = static function ($methods, $user) {
    $methods['zz_phone'] = 'Phone';
    if ($user instanceof WP_User) {
        $methods['zz_user'] = 'For ' . $user->user_login;
    }
    return $methods;
};
add_filter('user_contactmethods', $methods, 10, 2);
$say('additional user keys, filtered', $mask([_get_additional_user_keys(get_userdata($one)), _wp_get_user_contactmethods(), _wp_get_user_contactmethods(get_userdata($one))]));
remove_filter('user_contactmethods', $methods, 10);

// The sign-in cookie as an authenticate filter.
global $wp_filter;
$chain = [];
foreach ($wp_filter['authenticate']->callbacks as $priority => $callbacks) {
    foreach ($callbacks as $callback) {
        if (is_string($callback['function'])) {
            $chain[] = [$priority, $callback['function'], $callback['accepted_args']];
        }
    }
}
$say('authenticate chain', $chain);
$cookie = static function (int $userId, string $scheme) use (&$made): string {
    $value = wp_generate_auth_cookie($userId, time() + HOUR_IN_SECONDS, $scheme);
    $made['tokens'][] = [$userId, explode('|', $value)[2]];
    return $value;
};
$authName = is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
$withCookie = static function (?string $value, Closure $call) use ($authName) {
    $was = $_COOKIE[$authName] ?? null;
    if ($value === null) {
        unset($_COOKIE[$authName]);
    } else {
        $_COOKIE[$authName] = $value;
    }
    $out = $call();
    if ($was === null) {
        unset($_COOKIE[$authName]);
    } else {
        $_COOKIE[$authName] = $was;
    }
    return $out;
};
$good = $cookie($one, is_ssl() ? 'secure_auth' : 'auth');
$say('the cookie as authenticate', $mask([
    $error(wp_authenticate_cookie(get_userdata($two), '', '')),
    $error(wp_authenticate_cookie(null, 'someone', 'pass')),
    $error(wp_authenticate_cookie(null, '', 'pass')),
    $withCookie(null, static fn () => $error(wp_authenticate_cookie(null, '', ''))),
    $withCookie($good, static fn () => $error(wp_authenticate_cookie(null, '', ''))),
    $withCookie($good, static fn () => $error(wp_authenticate_cookie(new WP_Error('earlier', 'Earlier'), '', ''))),
    $withCookie('zz|bad|cookie|value', static fn () => $error(wp_authenticate_cookie(null, '', ''))),
    $withCookie($good, static fn () => $error(wp_authenticate_cookie(null, 'someone', ''))),
    $withCookie($good, static fn () => $error(wp_authenticate_cookie('a string', '', ''))),
]));

// The users listed.
$three = $user('three', ['display_name' => 'Cy & Co', 'role' => 'administrator']);
$four = $user('four', ['display_name' => 'admin']);
$include = ['include' => [$one, $two, $three], 'echo' => false];
$say('users listed', $mask([
    wp_list_users($include),
    wp_list_users($include + ['exclude_admin' => false]),
    wp_list_users($include + ['show_fullname' => true, 'orderby' => 'name', 'order' => 'DESC']),
    wp_list_users($include + ['style' => 'none', 'html' => true]),
    wp_list_users($include + ['html' => false]),
    wp_list_users($include + ['feed' => 'RSS', 'feed_type' => 'rss2']),
    wp_list_users($include + ['feed_image' => 'https://img.example/feed.png', 'feed' => 'Feed']),
    wp_list_users($include + ['exclude' => [$one]]),
    wp_list_users(['include' => [999999999], 'echo' => false]),
    wp_list_users(['include' => [$one, $four], 'echo' => false]),
    wp_list_users(['include' => [$one, $four], 'echo' => false, 'exclude_admin' => false]),
    wp_list_users(['include' => [$one, $two, $three], 'echo' => false, 'number' => 2]),
    (static function () use ($include) {
        ob_start();
        $returned = wp_list_users(['echo' => true] + $include);
        return [ob_get_clean(), $returned];
    })(),
]));

// The user-count event.
wp_clear_scheduled_hook('wp_update_user_counts');
$scheduled = [has_action('init', 'wp_schedule_update_user_counts'), has_action('wp_update_user_counts', 'wp_update_user_counts'), wp_next_scheduled('wp_update_user_counts')];
wp_schedule_update_user_counts();
$event = wp_get_scheduled_event('wp_update_user_counts');
$scheduled[] = $event ? [$event->schedule, abs($event->timestamp - time()) < 60, $event->args] : false;
$at = wp_next_scheduled('wp_update_user_counts');
wp_schedule_update_user_counts();
$scheduled[] = wp_next_scheduled('wp_update_user_counts') === $at;
$say('user counts scheduled', $scheduled);

// A new email address asked for on the profile.
$mail = [];
$catch = static function ($return, $atts) use (&$mail) {
    $mail[] = [$atts['to'], $atts['subject'], $atts['message']];
    return true;
};
add_filter('pre_wp_mail', $catch, 10, 2);
$confirm = static function (array $post, ?int $asUser, $userId = 0) use (&$mail) {
    global $errors;
    $errors = null;
    $mail = [];
    $_POST = $post;
    wp_set_current_user($asUser ?? 0);
    $returned = send_confirmation_on_profile_email($userId);
    $mail[] = ['the posted email after', $_POST['email'] ?? null];
    $meta = get_user_meta((int) ($post['user_id'] ?? 0), '_new_email', true);
    $out = [$returned, is_wp_error($errors) ? [$errors->get_error_codes(), $errors->get_error_messages(), $errors->get_error_data('user_email')] : $errors, $mail, is_array($meta) ? $meta['newemail'] ?? null : $meta];
    $_POST = [];
    wp_set_current_user(0);
    return $out;
};
$pending = static function (int $id) {
    $meta = get_user_meta($id, '_new_email', true);
    return is_array($meta) ? [array_keys($meta), $meta['newemail'] ?? null, isset($meta['hash']) ? strlen($meta['hash']) : null] : $meta;
};
$say('new email asked for', $mask([
    $confirm(['user_id' => $one, 'email' => 'zz-um-one-new@example.com'], $one),
    $pending($one),
    $confirm(['user_id' => $one, 'email' => 'zz-um-one@example.com'], $one),
    $confirm(['user_id' => $one, 'email' => 'not an email'], $one),
    $confirm(['user_id' => $one, 'email' => 'zz-um-two@example.com'], $one),
    $confirm(['user_id' => $two, 'email' => 'zz-um-other@example.com'], $one),
    $confirm(['email' => 'zz-um-one-again@example.com'], $one),
    $confirm(['user_id' => $one, 'email' => 'zz-um-one-third@example.com'], 0),
    $confirm(['email' => 'zz-um-one-fifth@example.com'], $one, $one),
    $confirm(['user_id' => $one, 'email' => ' ZZ-UM-ONE-FOURTH@EXAMPLE.COM '], $one),
    $pending($one),
]));
remove_filter('pre_wp_mail', $catch, 10);
delete_user_meta($one, '_new_email');

$say('meta read for no object', [get_user_meta(0, '_new_email', true), get_user_meta(0, '_new_email'), get_post_meta(0, 'x', true), get_metadata('post', -5, 'x', true), get_metadata('post', 'abc', 'x', true), get_metadata('post', '0', 'x'), get_metadata('nope', 1, 'x', true), get_metadata('post', 999999999, 'x', true), get_metadata('post', 999999999, 'x')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
