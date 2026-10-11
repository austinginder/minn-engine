<?php
/**
 * Placeholders made real (probe placeholder-helpers): an option printed for
 * a form field; tags added to a post; the timezone choices; the annotation
 * under a PHP update notice; the heartbeat's settings; and the update
 * caches cleared. The post and tags the probe makes are removed, and the
 * update transients it clears are put back (autoload included).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$host = (string) parse_url(home_url(), PHP_URL_HOST);

$printed = static function (Closure $run) {
    ob_start();
    $returned = $run();
    return [(string) ob_get_clean(), $returned];
};
$say('form option', [
    'the site title' => $printed(static fn () => form_option('blogname')),
    'not set' => $printed(static fn () => form_option('zz_ph_none')),
    'markup in it' => (static function () use ($printed) {
        $filter = static fn () => 'a "b" <c> & d';
        add_filter('pre_option_zz_ph_marked', $filter);
        $out = $printed(static fn () => form_option('zz_ph_marked'));
        remove_filter('pre_option_zz_ph_marked', $filter);
        return $out;
    })(),
]);

$made = ['post' => 0];
register_shutdown_function(static function () use (&$made): void {
    if (is_int($made['post']) && $made['post'] > 1) {
        wp_delete_post($made['post'], true);
    }
    foreach (['zz-ph-one', 'zz-ph-two', 'zz-ph-three'] as $slug) {
        $tag = get_term_by('slug', $slug, 'post_tag');
        if ($tag instanceof WP_Term && $tag->term_id > 1) {
            wp_delete_term($tag->term_id, 'post_tag');
        }
    }
});
wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ ph post', 'post_status' => 'publish', 'post_content' => 'zz']);
wp_set_current_user(0);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['post'] = $post;
$tags = static fn () => wp_get_post_tags($post, ['fields' => 'names']);
$say('post tags added', [
    'a list in a string' => [is_array(wp_add_post_tags($post, 'ZZ ph one, ZZ ph two')), $tags()],
    'more, appended' => [is_array(wp_add_post_tags($post, ['ZZ ph three'])), $tags()],
    'nothing' => [wp_add_post_tags($post, ''), $tags()],
    'no post' => wp_add_post_tags(0, 'ZZ ph one'),
]);

$choices = static function (string $zone, $locale = null) {
    $html = $locale === null ? wp_timezone_choice($zone) : wp_timezone_choice($zone, $locale);
    preg_match_all('/<option[^>]*selected[^>]*>[^<]*<\/option>/', $html, $selected);
    return ['length' => strlen($html), 'md5' => md5($html), 'selected' => $selected[0], 'start' => substr($html, 0, 160), 'end' => substr($html, -160)];
};
$say('timezone choices', [
    'a city' => $choices('America/New_York'),
    'UTC' => $choices('UTC'),
    'an offset' => $choices('UTC+5.5'),
    'none' => $choices(''),
    'not a zone' => $choices('Zz/Nowhere'),
    'a zone outside the list' => $choices('Etc/GMT+5'),
    'an old name' => $choices('US/Eastern'),
    'a sub-city' => $choices('America/Indiana/Indianapolis'),
    'a locale given' => $choices('Europe/Paris', 'en_US'),
]);

$say('php update annotation', [
    'the default address' => $printed(static fn () => wp_update_php_annotation()),
    'a host\'s address' => (static function () use ($printed) {
        $address = static fn () => 'https://zz.example/update-php';
        add_filter('wp_update_php_url', $address);
        $out = [
            'printed' => $printed(static fn () => wp_update_php_annotation()),
            'wrapped, returned' => wp_update_php_annotation('<b>', '</b>', false),
        ];
        remove_filter('wp_update_php_url', $address);
        return $out;
    })(),
    'returned, the default address' => wp_update_php_annotation('<b>', '</b>', false),
]);

$heartbeat = static function (array $settings) use ($host) {
    $out = wp_heartbeat_settings($settings);
    $nonce = $out['nonce'] ?? null;
    if ($nonce !== null) {
        $out['nonce'] = $nonce === wp_create_nonce('heartbeat-nonce') ? '{heartbeat nonce}' : $nonce;
    }
    return json_decode(str_replace($host, '{host}', (string) json_encode($out, JSON_UNESCAPED_SLASHES)), true);
};
$say('heartbeat settings', [
    'hooked' => has_filter('heartbeat_settings', 'wp_heartbeat_settings'),
    'a visitor' => $heartbeat([]),
    'a visitor, settings kept' => $heartbeat(['interval' => 30, 'ajaxurl' => 'zz']),
    'signed in' => (static function () use ($heartbeat) {
        wp_set_current_user(1);
        $out = $heartbeat(['zz' => true]);
        wp_set_current_user(0);
        return $out;
    })(),
]);

// The update caches: the three update transients set to the probe's values, cleared, then put back as they were.
global $wpdb;
$names = ['update_core', 'update_plugins', 'update_themes'];
$rows = [];
foreach ($names as $name) {
    foreach (["_site_transient_{$name}", "_site_transient_timeout_{$name}"] as $option) {
        $rows[$option] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option), ARRAY_A);
    }
}
register_shutdown_function(static function () use ($rows): void {
    global $wpdb;
    foreach ($rows as $option => $row) {
        $wpdb->delete($wpdb->options, ['option_name' => $option]);
        if (is_array($row)) {
            $wpdb->insert($wpdb->options, ['option_name' => $option, 'option_value' => $row['option_value'], 'autoload' => $row['autoload']]);
        }
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
});
foreach ($names as $name) {
    set_site_transient($name, (object) ['zz' => $name], 3600);
}
$before = array_map(static fn ($name) => get_site_transient($name) !== false, array_combine($names, $names));
$returned = wp_clean_update_cache();
$say('update caches cleared', ['before' => $before, 'returned' => $returned, 'after' => array_map(static fn ($name) => get_site_transient($name) !== false, array_combine($names, $names))]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
