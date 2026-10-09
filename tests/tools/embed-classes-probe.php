<?php
/**
 * The embed classes (probe embed-classes): WP_oEmbed (providers found by URL,
 * early registration, discovery from a page's links, fetching JSON and XML,
 * data turned into HTML, newlines stripped) and WP_Embed (handlers
 * registered and run, the [embed] shortcode with its post meta cache,
 * autoembed of bare URLs, links made, the oembed_cache post found, caches
 * deleted). No request leaves: every HTTP request answers through
 * pre_http_request. The probe's post, meta and caches are removed. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
register_shutdown_function(static function () use (&$made): void {
    global $wpdb;
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache' AND post_content LIKE '%zz-embed%'") as $id) {
        wp_delete_post((int) $id, true);
    }
});
$requests = [];
$answer = static function ($pre, $args, $url) use (&$requests) {
    $requests[] = [$url, $args['method'] ?? 'GET'];
    if (str_contains($url, 'zz-page.example/discover')) {
        return ['headers' => ['content-type' => 'text/html'], 'body' => '<html><head><link rel="alternate" type="application/json+oembed" href="https://zz-provider.example/oembed?url=x&amp;format=json" /><link rel="alternate" type="text/xml+oembed" href="https://zz-provider.example/oembed?format=xml" /></head></html>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if (str_contains($url, 'zz-provider.example/oembed') && str_contains($url, 'format=xml')) {
        return ['headers' => ['content-type' => 'text/xml'], 'body' => '<?xml version="1.0"?><oembed><type>link</type><version>1.0</version><title>Zz XML</title><url>https://zz-provider.example/x</url></oembed>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if (str_contains($url, 'zz-provider.example/oembed')) {
        return ['headers' => ['content-type' => 'application/json'], 'body' => json_encode(['type' => 'video', 'version' => '1.0', 'title' => 'Zz Video', 'html' => "<iframe src=\"https://zz-provider.example/embed/1\"\nwidth=\"640\"></iframe>", 'width' => 640, 'height' => 360]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if (str_contains($url, 'zz-provider.example/missing')) {
        return ['headers' => [], 'body' => '', 'response' => ['code' => 404, 'message' => 'Not Found'], 'cookies' => [], 'filename' => null];
    }
    return ['headers' => [], 'body' => 'plain', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
};
add_filter('pre_http_request', $answer, 10, 3);
// The global WP_oEmbed already read the oembed_providers filter, so the probe's provider is added to it directly.
wp_oembed_add_provider('#https?://zz-embed\.example/.*#i', 'https://zz-provider.example/oembed', true);

$oembed = _wp_oembed_get_object();
$say('providers', [
    get_class($oembed) === WP_oEmbed::class,
    $oembed->get_provider('https://www.youtube.com/watch?v=zz'),
    $oembed->get_provider('https://zz-embed.example/video/1'),
    $oembed->get_provider('https://zz-embed.example/video/1', ['discover' => false]),
    $oembed->get_provider('https://nothing.example/'),
    $oembed->get_provider('https://nothing.example/', ['discover' => false]),
    count($oembed->providers) > 30,
    (static function () {
        WP_oEmbed::_add_provider_early('#https?://zz-early\.example/.*#i', 'https://zz-provider.example/oembed', true);
        WP_oEmbed::_remove_provider_early('#https?://zz-gone\.example/.*#i');
        global $wp_oembed;
        $fresh = new WP_oEmbed();
        return [isset($fresh->providers['#https?://zz-early\.example/.*#i']), $fresh->get_provider('https://zz-early.example/a', ['discover' => false])];
    })(),
]);

$requests = [];
$say('discovered and fetched', [
    $oembed->discover('https://zz-page.example/discover'),
    $oembed->discover('https://zz-page.example/plain'),
    $oembed->fetch('https://zz-provider.example/oembed', 'https://zz-embed.example/video/1'),
    $oembed->fetch('https://zz-provider.example/oembed', 'https://zz-embed.example/video/1', ['width' => 300, 'height' => 200, 'format' => 'xml']),
    $oembed->fetch('https://zz-provider.example/missing', 'https://zz-embed.example/video/1'),
    $oembed->get_data('https://zz-embed.example/video/1'),
    $oembed->get_data('https://nothing.example/', ['discover' => false]),
    $requests,
]);
$say('turned into html', [
    $oembed->get_html('https://zz-embed.example/video/1'),
    $oembed->get_html('https://nothing.example/', ['discover' => false]),
    $oembed->data2html((object) ['type' => 'photo', 'url' => 'https://img.example/p.jpg', 'width' => 100, 'height' => 80, 'title' => 'A "photo"'], 'https://zz-embed.example/p'),
    $oembed->data2html((object) ['type' => 'photo', 'url' => 'https://img.example/p.jpg'], 'https://zz-embed.example/p'),
    $oembed->data2html((object) ['type' => 'video', 'html' => '<video></video>'], 'https://zz-embed.example/v'),
    $oembed->data2html((object) ['type' => 'rich', 'html' => '<div>rich</div>'], 'https://zz-embed.example/r'),
    $oembed->data2html((object) ['type' => 'link', 'title' => 'Zz Link'], 'https://zz-embed.example/l'),
    $oembed->data2html((object) ['type' => 'nonsense'], 'https://zz-embed.example/n'),
    $oembed->data2html(false, 'https://zz-embed.example/n'),
    $oembed->_strip_newlines("<p>a\nb</p>\r\n<p>c</p>", (object) ['type' => 'rich'], 'https://zz-embed.example/'),
    $oembed->_strip_newlines("<pre>a\nb</pre>", (object) [], 'https://zz-embed.example/'),
    $oembed->_strip_newlines('', (object) [], ''),
]);

// WP_Embed: handlers, the shortcode, autoembed, links, caches.
global $wp_embed;
$post = (int) wp_insert_post(['post_title' => 'zz embed post', 'post_status' => 'publish', 'post_content' => 'x']);
$made['posts'][] = $post;
$GLOBALS['post'] = get_post($post);
$wp_embed->register_handler('zz_handler', '#https?://zz-handled\.example/(\d+)#i', static fn ($matches, $attr, $url, $rawattr) => '<span data-id="' . $matches[1] . '" data-w="' . ($attr['width'] ?? '') . '">' . esc_html($url) . '</span>');
$say('handlers', [
    get_class($wp_embed) === WP_Embed::class,
    $wp_embed->get_embed_handler_html(['width' => 200, 'height' => 100], 'https://zz-handled.example/42'),
    $wp_embed->get_embed_handler_html([], 'https://not-handled.example/'),
    $wp_embed->shortcode(['width' => 300], 'https://zz-handled.example/7'),
    (static function () use ($wp_embed) {
        $wp_embed->unregister_handler('zz_handler');
        return $wp_embed->get_embed_handler_html([], 'https://zz-handled.example/42');
    })(),
]);
$cacheKeys = static function () use ($post) {
    $keys = array_values(array_filter(array_keys(get_post_meta($post)), static fn ($key) => str_starts_with((string) $key, '_oembed_')));
    return [count($keys), array_map(static fn ($key) => str_starts_with($key, '_oembed_time_') ? '_oembed_time_{hash}' : '_oembed_{hash}', $keys)];
};
$requests = [];
$say('the shortcode', [
    $wp_embed->shortcode([], 'https://zz-embed.example/video/1'),
    $wp_embed->shortcode(['width' => 300], 'https://zz-embed.example/video/1'),
    $wp_embed->shortcode([], ''),
    $wp_embed->shortcode([], 'https://nothing.example/plain'),
    $wp_embed->shortcode([], 'not a url'),
    $cacheKeys(),
    get_post_meta($post, '_oembed_' . md5('https://zz-embed.example/video/1' . serialize(wp_parse_args([], wp_embed_defaults('https://zz-embed.example/video/1')))), true) !== '',
    get_post_meta($post, '_oembed_' . md5('https://nothing.example/plain' . serialize(wp_parse_args([], wp_embed_defaults('https://nothing.example/plain')))), true),
    count($requests),
    $wp_embed->shortcode([], 'https://zz-embed.example/video/1'),
    count($requests),
    $wp_embed->run_shortcode('Before [embed]https://zz-embed.example/video/1[/embed] after [embed width="100"]https://zz-handled.example/3[/embed]'),
]);
$say('autoembed', [
    $wp_embed->autoembed("Text\nhttps://zz-embed.example/video/1\nmore"),
    $wp_embed->autoembed('<p>https://zz-embed.example/video/1</p>'),
    $wp_embed->autoembed('Inline https://zz-embed.example/video/1 not alone'),
    $wp_embed->autoembed("<pre>\nhttps://zz-embed.example/video/1\n</pre>"),
    $wp_embed->autoembed_callback(['https://zz-embed.example/video/1', '', 'https://zz-embed.example/video/1', '']),
    $wp_embed->maybe_make_link('https://nothing.example/x'),
    (static function () use ($wp_embed) {
        $wp_embed->linkifunknown = false;
        $out = $wp_embed->maybe_make_link('https://nothing.example/x');
        $wp_embed->linkifunknown = true;
        return $out;
    })(),
    (static function () use ($wp_embed) {
        $wp_embed->return_false_on_fail = true;
        $out = $wp_embed->maybe_make_link('https://nothing.example/x');
        $wp_embed->return_false_on_fail = false;
        return $out;
    })(),
]);
$say('caches', [
    $wp_embed->find_oembed_post_id(md5('zz-embed none')),
    (static function () use ($wp_embed, $post, $cacheKeys) {
        $wp_embed->delete_oembed_caches($post);
        return $cacheKeys();
    })(),
    (static function () use ($wp_embed, $post) {
        $wp_embed->cache_oembed($post);
        return array_values(array_filter(array_keys(get_post_meta($post)), static fn ($key) => str_starts_with((string) $key, '_oembed_')));
    })(),
    (static function () use ($wp_embed) {
        ob_start();
        $wp_embed->maybe_run_ajax_cache();
        return ob_get_clean();
    })(),
]);
unset($GLOBALS['post']);
remove_filter('pre_http_request', $answer, 10);
wp_oembed_remove_provider('#https?://zz-embed\.example/.*#i');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
