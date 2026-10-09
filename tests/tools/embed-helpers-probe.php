<?php
/**
 * Embed helpers (probe embed-helpers): the default embed handlers and the
 * hooks they hang on, YouTube embed addresses and audio files embedded, the
 * oEmbed format kept to json or xml, untrusted oEmbed answers cut down to a
 * blockquote and a sandboxed iframe sharing a secret, and the site's own
 * posts embedded without a request. No request leaves (pre_oembed_result
 * answers first); the probe's post and oEmbed caches are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_embed, $wpdb;
$made = [];
register_shutdown_function(static function () use (&$made, $wpdb): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache' AND post_content LIKE '%zz-yt%'") as $id) {
        wp_delete_post((int) $id, true);
    }
});
add_filter('pre_http_request', static fn () => new WP_Error('zz_blocked', 'No request leaves the probe.'), 1);
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => json_decode((string) preg_replace(['/data-secret=\\\\"[A-Za-z0-9]{10}\\\\"/', '/#\\?secret=[A-Za-z0-9]{10}/'], ['data-secret=\\"{secret}\\"', '#?secret={secret}'], str_replace($host, '{host}', (string) wp_json_encode($value))), true);

$say('the hooks', [
    has_action('plugins_loaded', 'wp_maybe_load_embeds'),
    has_action('rest_api_init', 'wp_oembed_register_route'),
    has_filter('oembed_dataparse', 'wp_filter_oembed_result'),
    has_filter('oembed_dataparse', 'wp_filter_oembed_iframe_title_attribute'),
    has_filter('pre_oembed_result', 'wp_filter_pre_oembed_result'),
]);

$describe = static fn (array $handlers) => array_map(static fn ($group) => array_map(static fn ($handler) => [$handler['regex'], is_string($handler['callback']) ? $handler['callback'] : 'other'], $group), $handlers);
$saved = $wp_embed->handlers;
$fresh = static function (?Closure $setup = null) use ($wp_embed, $describe) {
    $wp_embed->handlers = [];
    $setup && $setup();
    return [wp_maybe_load_embeds(), $describe($wp_embed->handlers)];
};
$say('the default handlers', [
    $describe($saved),
    $fresh(),
    $fresh(static fn () => add_filter('load_default_embeds', '__return_false')),
    (static function () use ($fresh) {
        remove_filter('load_default_embeds', '__return_false');
        $named = static fn () => 'zz_handler';
        add_filter('wp_audio_embed_handler', $named);
        add_filter('wp_video_embed_handler', $named);
        $out = $fresh();
        remove_filter('wp_audio_embed_handler', $named);
        remove_filter('wp_video_embed_handler', $named);
        return $out;
    })(),
    (static function () use ($wp_embed, $describe) {
        $wp_embed->handlers = [];
        wp_embed_register_handler('zz-one', '#zz-one#', 'zz_one_cb', 5);
        wp_embed_register_handler('zz-two', '#zz-two#', 'zz_two_cb');
        $out = $describe($wp_embed->handlers);
        wp_embed_unregister_handler('zz-one', 5);
        return [$out, $describe($wp_embed->handlers)];
    })(),
]);
$wp_embed->handlers = $saved;

$asked = [];
$answer = static function ($pre, $url, $args) use (&$asked) {
    $asked[] = [$url, $args];
    return '<iframe src="https://www.youtube.com/embed/zz-yt"></iframe>';
};
add_filter('pre_oembed_result', $answer, 1, 3);
$youtube = static function (array $matches, array $attr, string $url, array $rawattr) use (&$asked) {
    $asked = [];
    return [wp_embed_handler_youtube($matches, $attr, $url, $rawattr), $asked];
};
$say('youtube embed addresses', [
    $youtube(['https://www.youtube.com/embed/zzyt1', 'www.', 'zzyt1'], ['width' => 500, 'height' => 750], 'https://www.youtube.com/embed/zzyt1', []),
    $youtube(['https://youtube.com/v/zzyt2', '', 'zzyt2'], ['width' => 300], 'https://youtube.com/v/zzyt2', ['width' => 300]),
    $youtube(['https://youtube.com/v/zz yt&2', '', 'zz yt&2'], [], 'https://youtube.com/v/zz yt&2', []),
    (static function () use ($youtube) {
        $tag = static fn ($html, $attr, $url) => "[zz:{$url}]{$html}";
        add_filter('wp_embed_handler_youtube', $tag, 10, 3);
        $out = $youtube(['https://youtube.com/embed/zzyt3', '', 'zzyt3'], [], 'https://youtube.com/embed/zzyt3', []);
        remove_filter('wp_embed_handler_youtube', $tag, 10);
        return $out;
    })(),
]);
remove_filter('pre_oembed_result', $answer, 1);

$say('audio files', [
    wp_embed_handler_audio([], ['width' => 500], 'https://zz.example/a.mp3', []),
    wp_embed_handler_audio([], [], 'https://zz.example/a b.mp3?x=1&y="2"', ['width' => 9]),
    (static function () {
        $tag = static fn ($html, $attr, $url, $raw) => "{$html}|" . wp_json_encode([$attr, $url, $raw]);
        add_filter('wp_embed_handler_audio', $tag, 10, 4);
        $out = wp_embed_handler_audio(['m'], ['w' => 1], 'https://zz.example/b.ogg', ['r' => 2]);
        remove_filter('wp_embed_handler_audio', $tag, 10);
        return $out;
    })(),
]);

$say('the oEmbed format', array_map(static fn ($format) => wp_oembed_ensure_format($format), ['json', 'xml', 'XML', 'zz', '', null, 0]));

$filtered = static fn ($html, $type = 'rich', $url = 'https://zz.example/') => $mask(wp_filter_oembed_result($html, (object) ['type' => $type, 'html' => $html, 'provider_name' => 'Zz'], $url));
$wpEmbed = '<blockquote class="wp-embedded-content"><a href="https://zz.example/post/">Zz Post</a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" title="Zz" src="https://zz.example/post/embed/" width="600" height="338" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" onload="alert(1)"></iframe>';
$say('untrusted answers made safe', [
    $filtered($wpEmbed),
    $filtered('<iframe src="https://v.example/1"></iframe>', 'video'),
    $filtered('<div>x</div><script>y()</script>'),
    $filtered('<img src="x">', 'photo'),
    $filtered('<iframe src="https://i.example/"></iframe>', 'link'),
    $mask(wp_filter_oembed_result(false, (object) ['type' => 'rich'], 'https://zz.example/')),
    $mask(wp_filter_oembed_result('<iframe src="https://i.example/"></iframe>', (object) ['type' => 'rich'], 'https://zz.example/')),
    $mask(wp_filter_oembed_result('<iframe src="https://i.example/"></iframe>', ['type' => 'rich'], 'https://zz.example/')),
    $filtered('<iframe src="https://i.example/" width="1" height="2" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" title="T" class="c1 c2" style="color: red" sandbox="allow-forms" security="x" allow="autoplay" allowfullscreen name="n" id="i" data-x="y" onload="z" loading="lazy" referrerpolicy="no-referrer"></iframe>'),
    $filtered('<iframe src="https://i.example/" srcdoc="&lt;b&gt;"></iframe>'),
    $filtered('<div>before</div><iframe src="https://i.example/"></iframe><p>after</p>'),
    $filtered('<iframe src="https://i.example/a"></iframe><iframe src="https://i.example/b"></iframe>'),
    $filtered('<blockquote class="q" cite="c" data-x="y"><p>p</p><a href="h" target="_blank">a</a><b>b</b><script>s</script></blockquote><iframe src="https://i.example/"></iframe>'),
    $filtered('<blockquote>one</blockquote><blockquote>two</blockquote><iframe src="https://i.example/"></iframe>'),
    $filtered('<iframe src="https://i.example/"></iframe><blockquote>after</blockquote>'),
    $filtered('<blockquote><iframe src="https://i.example/inner"></iframe></blockquote>'),
    $filtered('text <iframe src="https://i.example/">inner text</iframe> more'),
    $filtered('<iframe src="https://i.example/?q=1#frag"></iframe>'),
    $filtered('<iframe src="https://i.example/?a=1&b=2&amp;c=3"></iframe>'),
    $filtered('<iframe src="https://i.example/a b"></iframe>'),
    $filtered('<iframe src="javascript:alert(1)"></iframe>'),
    $filtered('<iframe src=""></iframe>'),
    $filtered('<iframe></iframe>'),
    $filtered('<iframe src="/relative/path"></iframe>'),
    $filtered('<iframe width="5" src="https://i.example/"></iframe>'),
    $filtered('<IFRAME SRC="https://i.example/"></IFRAME>'),
    $filtered("<iframe\nsrc='https://i.example/'\n></iframe>"),
    $filtered('  <iframe src="https://i.example/" title="a &quot;b&quot; &amp; c"></iframe>  '),
    $filtered('<iframe src="https://www.youtube.com/embed/x" allowfullscreen></iframe>', 'video', 'https://www.youtube.com/watch?v=x'),
    $filtered('<iframe src="https://www.youtube.com/embed/x" allowfullscreen></iframe>', 'video', 'https://vimeo.com/123'),
    $filtered('<iframe src="https://i.example/"></iframe>', 'video', home_url('/x/')),
]);

$titles = static function (string $html, $data) {
    $heard = [];
    $listen = static function (...$a) use (&$heard) {
        $heard[] = [$a[0], count($a)];
        return $a[0];
    };
    add_filter('oembed_iframe_title_attribute', $listen, 10, 4);
    $out = [wp_filter_oembed_iframe_title_attribute($html, $data, 'https://zz.example/'), $heard];
    remove_filter('oembed_iframe_title_attribute', $listen, 10);
    return $out;
};
$say('iframes titled', [
    $titles('<iframe src="s" width="1" title="Old" data-x="y"></iframe>', (object) ['type' => 'rich', 'title' => 'New']),
    $titles('<iframe src="s" width="1" title="Old"></iframe>', (object) ['type' => 'rich', 'title' => '']),
    $titles('<iframe src="s" width="1"></iframe>', (object) ['type' => 'rich', 'title' => 'New "q"']),
    $titles('<iframe src="s" width="1"></iframe>', (object) ['type' => 'rich']),
    $titles('<p>x</p><iframe src="s" TITLE="Up"></iframe><iframe src="t"></iframe>', (object) ['type' => 'video', 'title' => 'New']),
    $titles('<iframe src="s" title="Old"></iframe>', (object) ['type' => 'photo', 'title' => 'New']),
    $titles('<iframe src="s" title="a &amp; b"></iframe>', (object) ['type' => 'rich', 'title' => 'New']),
    $titles('<iframe src="s"></iframe><iframe src="t" title="T2"></iframe>', (object) ['type' => 'rich', 'title' => 'Data']),
    $titles('<iframe src="s" title=\'sq\' title="dup"></iframe>', (object) ['type' => 'video', 'title' => '']),
    $titles('<div>no iframe</div>', (object) ['type' => 'video', 'title' => 'X']),
    (static function () {
        $retitle = static fn () => 'Zz <title> & more';
        add_filter('oembed_iframe_title_attribute', $retitle);
        $out = wp_filter_oembed_iframe_title_attribute('<iframe src="s" title="Old"></iframe>', (object) ['type' => 'rich'], 'u');
        remove_filter('oembed_iframe_title_attribute', $retitle);
        return $out;
    })(),
]);

$made[] = $post = (int) wp_insert_post(['post_title' => 'Zz Embed Target', 'post_status' => 'publish', 'post_content' => 'Embed me.']);
$link = get_permalink($post);
$own = static fn ($value) => $mask(json_decode(str_replace((string) $post, '{id}', (string) wp_json_encode($value)), true));
$say('the site\'s own posts answered locally', [
    $own(wp_filter_pre_oembed_result(null, $link, ['width' => 400, 'height' => 300])),
    $own(wp_filter_pre_oembed_result('<zz>', $link, ['width' => 400])),
    $own(wp_filter_pre_oembed_result(null, home_url('/?p=' . $post), ['width' => 200])),
    wp_filter_pre_oembed_result(null, 'https://zz.example/x/', []),
    wp_filter_pre_oembed_result('<zz>', 'https://zz.example/x/', []),
    wp_filter_pre_oembed_result(false, 'https://zz.example/x/', []),
    wp_filter_pre_oembed_result(null, home_url('/zz-never-post/'), []),
    wp_filter_pre_oembed_result(null, home_url('/'), []),
]);

$say('the oEmbed route', [wp_oembed_register_route(), array_key_exists('/oembed/1.0/embed', rest_get_server()->get_routes())]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
