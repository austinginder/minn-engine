<?php
/**
 * oEmbed answers kept outside a post (probe oembed-cache): the oembed_cache
 * post an embed made without a post is stored in and found by
 * (WP_Embed::find_oembed_post_id and its cache group), reused while
 * usecache is on or the post is younger than oembed_ttl, refreshed when it
 * is stale, {{unknown}} for an address that fails, one post per address
 * and attributes. No request leaves; the probe's cache posts are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_embed, $wpdb;
$mine = static fn () => array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache' AND (post_content LIKE '%zzoc%' OR post_name IN ('" . implode("','", array_map('esc_sql', $GLOBALS['zz_oc_keys'] ?? [])) . "'))"));
register_shutdown_function(static function () use ($mine): void {
    foreach ($mine() as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
add_filter('pre_http_request', static fn () => new WP_Error('zz_blocked', 'No request leaves the probe.'), 1);
$asked = [];
$answers = ['https://zzoc.example/a' => '<iframe src="https://zzoc.example/a" title="zz \'q\' &amp; more"></iframe>', 'https://zzoc.example/fail' => false];
add_filter('pre_oembed_result', static function ($pre, $url, $args) use (&$asked, $answers) {
    $asked[] = $url;
    return $answers[$url] ?? null;
}, 1, 3);
$heard = [];
add_filter('embed_oembed_html', static function ($html, $url, $attr, $post_id) use (&$heard) {
    $heard[] = [$url, $post_id];
    return $html;
}, 10, 4);
$wp_embed->post_ID = null;
$GLOBALS['post'] = null;
$key = static fn (string $url, array $attr = []) => md5($url . serialize(wp_parse_args($attr, wp_embed_defaults($url))));
$GLOBALS['zz_oc_keys'] = [$key('https://zzoc.example/a'), $key('https://zzoc.example/fail'), $key('https://zzoc.example/a', ['width' => 300])];
$run = static function (string $url, array $attr = []) use ($wp_embed, &$asked, &$heard): array {
    $asked = [];
    $heard = [];
    return [$wp_embed->shortcode($attr, $url), $asked, $heard];
};
$ids = [];
$named = static fn ($id) => $id === null ? null : (isset($ids[$id]) ? $ids[$id] : 'post');

$first = $key('https://zzoc.example/a');
$say('made once, then found', [
    $wp_embed->find_oembed_post_id($first),
    $run('https://zzoc.example/a'),
    (static function () use ($wp_embed, $first) {
        $id = $wp_embed->find_oembed_post_id($first);
        $post = get_post($id);
        return [is_int($id), wp_cache_get($first, 'oembed_cache_post') === $id, $post ? [$post->post_type, $post->post_status, $post->post_name === $first, $post->post_title, $post->post_content, (int) $post->post_author, (int) $post->post_parent, $post->comment_status, $post->ping_status] : null];
    })(),
    $run('https://zzoc.example/a'),
    $wp_embed->find_oembed_post_id('zz-no-such-key'),
]);

$age = static function (string $gmt) use ($wp_embed, $wpdb, $first): void {
    $id = (int) $wp_embed->find_oembed_post_id($first);
    $wpdb->update($wpdb->posts, ['post_modified_gmt' => $gmt, 'post_modified' => $gmt], ['ID' => $id]);
    clean_post_cache($id);
};
$say('reused, or refreshed when stale', [
    (static function () use ($wp_embed, $run) {
        $wp_embed->usecache = false;
        return $run('https://zzoc.example/a');
    })(),
    (static function () use ($age, $run, $wp_embed, $first) {
        $age('2000-01-01 00:00:00');
        $out = $run('https://zzoc.example/a');
        $out[] = get_post($wp_embed->find_oembed_post_id($first))->post_modified_gmt !== '2000-01-01 00:00:00';
        return $out;
    })(),
    (static function () use ($age, $run) {
        $age('2000-01-01 00:00:00');
        $short = static fn () => 60;
        add_filter('oembed_ttl', $short);
        $out = $run('https://zzoc.example/a');
        remove_filter('oembed_ttl', $short);
        return $out;
    })(),
    (static function () use ($age, $run, $wp_embed) {
        $wp_embed->usecache = true;
        $age('2000-01-01 00:00:00');
        return $run('https://zzoc.example/a');
    })(),
]);

$say('addresses that fail', [
    $run('https://zzoc.example/fail'),
    (static function () use ($wp_embed, $key) {
        $id = $wp_embed->find_oembed_post_id($key('https://zzoc.example/fail'));
        return $id ? get_post($id)->post_content : null;
    })(),
    $run('https://zzoc.example/fail'),
    (static function () use ($wp_embed, $run) {
        $wp_embed->linkifunknown = false;
        $out = $run('https://zzoc.example/fail');
        $wp_embed->linkifunknown = true;
        return $out;
    })(),
]);

$say('one post per address and attributes', [
    $run('https://zzoc.example/a', ['width' => 300]),
    count($mine()),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
