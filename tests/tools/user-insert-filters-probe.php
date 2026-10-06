<?php
/**
 * What wp_insert_user's filters are handed, in order, and what comes of
 * them: a new account, an edit, a login on illegal_user_logins, a login
 * validate_username refuses, and a save a plugin changes through
 * wp_pre_insert_user_data, insert_user_meta and pre_user_display_name.
 * Same protocol as api-probe.php; the accounts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
foreach (['zzinsertprobe', 'zzinsertchanged', 'zzinsertbad'] as $leftover) {
    $old = get_user_by('login', $leftover);
    if ($old) {
        wp_delete_user($old->ID, 1);
    }
}
$watch = ['sanitize_user', 'validate_username', 'illegal_user_logins', 'pre_user_login', 'pre_user_nicename', 'pre_user_email', 'pre_user_url', 'pre_user_nickname', 'pre_user_first_name', 'pre_user_last_name', 'pre_user_display_name', 'pre_user_description', 'wp_pre_insert_user_data', 'insert_user_meta', 'insert_custom_user_meta', 'user_registration_email'];
$describe = static function ($value) {
    if (is_array($value)) {
        $keys = array_keys($value);
        sort($keys);
        return 'array[' . implode(',', $keys) . ']';
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    return $value;
};
$seen = [];
$recorder = static function (string $hook) use (&$seen, $watch, $describe): void {
    if (in_array($hook, $watch, true)) {
        $seen[] = $hook . '(' . implode(' | ', array_map(static fn ($a) => var_export($describe($a), true), array_slice(func_get_args(), 1))) . ')';
    }
};
$made = [];
$run = static function (string $label, callable $save) use (&$seen, $recorder, $say, &$made): void {
    $seen = [];
    add_action('all', $recorder);
    $id = $save();
    remove_action('all', $recorder);
    if (is_int($id) && $id > 0) {
        $made[] = $id;
    }
    $say($label, ['result' => is_wp_error($id) ? [$id->get_error_code(), $id->get_error_message()] : (is_int($id) && $id > 0 ? 'id' : $id), 'filters' => array_map(static fn (string $f) => is_int($id) ? str_replace((string) $id, '{id}', $f) : $f, $seen)]);
};

$run('a new account', static fn () => wp_insert_user(['user_login' => 'zzinsertprobe', 'user_email' => 'zzinsertprobe@example.com', 'user_pass' => 'Probe-pass-1!', 'first_name' => 'Zed', 'last_name' => 'Probe', 'display_name' => 'Zed <b>Probe</b>', 'user_url' => 'elsewhere.example', 'role' => 'author']));
$run('an edit', static function () use (&$made) {
    return wp_update_user(['ID' => (int) end($made), 'first_name' => 'Zedd', 'description' => 'About <script>x</script> me']);
});
$after = get_userdata((int) end($made));
$say('the edit stored', $after ? [$after->first_name, $after->description, $after->display_name, $after->user_url, $after->user_nicename] : null);
$illegal = static fn (array $logins): array => [...$logins, 'zzinsertbad'];
add_filter('illegal_user_logins', $illegal);
$run('an illegal login', static fn () => wp_insert_user(['user_login' => 'zzinsertbad', 'user_email' => 'zzinsertbad@example.com', 'user_pass' => 'Probe-pass-1!']));
remove_filter('illegal_user_logins', $illegal);
$invalid = static fn ($valid, $login) => $login === 'zzinsertbad' ? false : $valid;
add_filter('validate_username', $invalid, 10, 2);
$run('a login validate_username refuses', static fn () => wp_insert_user(['user_login' => 'zzinsertbad', 'user_email' => 'zzinsertbad@example.com', 'user_pass' => 'Probe-pass-1!']));
remove_filter('validate_username', $invalid, 10);
$run('a login that is no login', static fn () => wp_insert_user(['user_login' => '   ', 'user_email' => 'zzinsertblank@example.com', 'user_pass' => 'Probe-pass-1!']));

$changes = [
    'wp_pre_insert_user_data' => static function (array $data) {
        $data['user_url'] = 'https://changed.example';
        return $data;
    },
    'insert_user_meta' => static function (array $meta) {
        $meta['nickname'] = 'changed nick';
        return $meta;
    },
    'pre_user_display_name' => static fn ($name) => $name . ' (saved)',
];
foreach ($changes as $hook => $callback) {
    add_filter($hook, $callback);
}
$changed = wp_insert_user(['user_login' => 'zzinsertchanged', 'user_email' => 'zzinsertchanged@example.com', 'user_pass' => 'Probe-pass-1!', 'display_name' => 'Changed']);
foreach ($changes as $hook => $callback) {
    remove_filter($hook, $callback);
}
if (is_int($changed)) {
    $made[] = $changed;
    $user = get_userdata($changed);
    $say('a plugin changes the save', [$user->user_url, $user->nickname, $user->display_name]);
}

foreach ($made as $id) {
    wp_delete_user($id, 1);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
