<?php
/**
 * What wp_insert_post's filters are handed, in order, for the saves a REST
 * write makes: a draft, a post published with no slug, an edit, and one a
 * plugin changes through each filter. Same protocol as api-probe.php; the
 * posts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$watch = ['wp_insert_post_empty_content', 'wp_insert_post_parent', 'pre_wp_unique_post_slug', 'wp_unique_post_slug_is_bad_flat_slug', 'wp_unique_post_slug', 'wp_insert_post_data', 'content_save_pre', 'title_save_pre', 'pre_post_title', 'excerpt_save_pre', 'status_save_pre', 'pre_post_name', 'name_save_pre', 'pre_post_status'];
$describe = static function ($value) {
    if (is_array($value)) {
        $keys = array_keys($value);
        sort($keys);
        return 'array[' . implode(',', $keys) . ']';
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_string($value)) {
        return preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) ? '{date}' : $value;
    }
    return $value;
};
$seen = [];
$recorder = static function (string $hook) use (&$seen, $watch, $describe): void {
    if (in_array($hook, $watch, true)) {
        $seen[] = [$hook, array_map($describe, array_slice(func_get_args(), 1))];
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
    // The post's own id differs by stack; it reads as {id}.
    $mask = static fn ($a) => is_int($id) && $id > 0 && $a === $id ? '{id}' : (is_scalar($a) || $a === null ? var_export($a, true) : (string) $a);
    $say($label, ['result' => is_wp_error($id) ? $id->get_error_code() : (is_int($id) && $id > 0 ? 'id' : $id), 'filters' => array_map(static fn ($row) => $row[0] . '(' . implode(' | ', array_map($mask, $row[1])) . ')', $seen)]);
};

$run('a draft', static fn () => wp_insert_post(wp_slash(['post_title' => 'zz insert probe draft', 'post_content' => 'Body "quoted"', 'post_status' => 'draft', 'post_type' => 'post']), true, false));
$run('a publish with no slug', static fn () => wp_insert_post(wp_slash(['post_title' => 'zz insert probe live', 'post_content' => 'Body', 'post_status' => 'publish', 'post_type' => 'post']), true, false));
$run('an edit', static function () use (&$made) {
    return wp_update_post(wp_slash(['ID' => (int) end($made), 'post_title' => 'zz insert probe live edited']), true, false);
});
$run('nothing at all', static fn () => wp_insert_post(wp_slash(['post_title' => '', 'post_content' => '', 'post_excerpt' => '', 'post_status' => 'draft', 'post_type' => 'post']), true, false));

// A plugin's say through each filter.
$changes = [
    'wp_insert_post_data' => static function (array $data, array $postarr) {
        $data['post_excerpt'] = 'set by wp_insert_post_data';
        return $data;
    },
    'wp_unique_post_slug' => static fn (string $slug) => $slug . '-zz',
    'title_save_pre' => static fn (string $title) => $title . ' (saved)',
];
foreach ($changes as $hook => $callback) {
    add_filter($hook, $callback, 10, 2);
}
$changed = wp_insert_post(wp_slash(['post_title' => 'zz insert probe changed', 'post_content' => 'Body', 'post_status' => 'publish', 'post_type' => 'post']), true, false);
foreach ($changes as $hook => $callback) {
    remove_filter($hook, $callback, 10);
}
$after = is_int($changed) ? get_post($changed) : null;
$say('a plugin changes the save', $after ? [$after->post_title, $after->post_name, $after->post_excerpt] : 'none');
if (is_int($changed)) {
    $made[] = $changed;
}
add_filter('wp_insert_post_empty_content', '__return_true');
$refused = wp_insert_post(wp_slash(['post_title' => 'zz insert probe refused', 'post_status' => 'draft', 'post_type' => 'post']), true, false);
remove_filter('wp_insert_post_empty_content', '__return_true');
$say('wp_insert_post_empty_content refuses', is_wp_error($refused) ? [$refused->get_error_code(), $refused->get_error_message()] : 'saved');

foreach ($made as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
