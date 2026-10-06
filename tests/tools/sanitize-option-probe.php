<?php
/**
 * What sanitize_option makes of each core option's value, clean and
 * messy, and which filters it asks: the reference cleans most options by
 * their own rules before sanitize_option_{$option} runs. Same protocol as
 * api-probe.php; nothing is saved.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$messy = [' <b>Hi</b> & "there" ', '12abc', '-5', 'not a url', 'javascript:alert(1)', 'https://example.com/ path/?q=<x>', 'bad@@mail', '  admin@example.com  ', "two\nlines", '', '0', 'yes', '%postname%', '/%year%/%postname%', 'Y-m-d <b>', 'UTC+5', 'Europe/Paris', 'Mars/Phobos'];
$options = ['admin_email', 'new_admin_email', 'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'medium_large_size_w', 'large_size_w', 'large_size_h', 'mailserver_port', 'comment_max_links', 'page_on_front', 'page_for_posts', 'rss_excerpt_length', 'default_category', 'default_email_category', 'default_link_category', 'close_comments_days_old', 'comments_per_page', 'thread_comments_depth', 'users_can_register', 'start_of_week', 'site_icon', 'posts_per_page', 'posts_per_rss', 'default_ping_status', 'default_comment_status', 'blogdescription', 'blogname', 'blog_charset', 'blog_public', 'date_format', 'time_format', 'mailserver_url', 'mailserver_login', 'mailserver_pass', 'upload_path', 'ping_sites', 'gmt_offset', 'siteurl', 'home', 'WPLANG', 'illegal_names', 'limited_email_domains', 'banned_email_domains', 'timezone_string', 'permalink_structure', 'category_base', 'tag_base', 'default_role', 'moderation_keys', 'disallowed_keys', 'show_on_front', 'zz_plugin_option'];
$seen = [];
$recorder = static function (string $hook) use (&$seen): void {
    if (str_starts_with($hook, 'sanitize_option_') || in_array($hook, ['pre_kses', 'wp_kses_allowed_html'], true)) {
        $seen[] = $hook;
    }
};
foreach ($options as $option) {
    $out = [];
    $hooks = [];
    foreach ($messy as $value) {
        $seen = [];
        add_action('all', $recorder);
        $result = sanitize_option($option, $value);
        remove_action('all', $recorder);
        $out[] = $result;
        $hooks = array_unique([...$hooks, ...$seen]);
    }
    $say($option, ['values' => $out, 'hooks' => array_values($hooks)]);
}
$say('settings errors', array_values(array_unique(array_map(static fn ($e) => $e['setting'] . ' ' . $e['code'] . ' ' . $e['type'] . ': ' . $e['message'], (array) get_settings_errors()))));
// Where sanitize_option falls in a write: first, before the old value is
// read; twice when an update finds no option and adds it.
$order = [];
$watch = ['sanitize_option_zz_probe_order', 'pre_update_option_zz_probe_order', 'pre_update_option', 'pre_option_zz_probe_order', 'default_option_zz_probe_order'];
$tag = static function ($value) use (&$order) {
    $order[] = current_filter() . ':' . json_encode($value);
    return is_string($value) ? $value . '+' : $value;
};
foreach ($watch as $hook) {
    add_filter($hook, $tag);
}
add_option('zz_probe_order', 'a');
$order[] = 'stored ' . get_option('zz_probe_order');
$order[] = 'add again: ' . json_encode(add_option('zz_probe_order', 'z'));
update_option('zz_probe_order', 'b');
$order[] = 'stored ' . get_option('zz_probe_order');
delete_option('zz_probe_order');
update_option('zz_probe_order', 'c');
$order[] = 'stored ' . get_option('zz_probe_order');
foreach ($watch as $hook) {
    remove_filter($hook, $tag);
}
delete_option('zz_probe_order');
$say('write order', $order);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
