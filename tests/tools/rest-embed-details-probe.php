<?php
/**
 * The oEmbed and URL-details REST controllers as classes (probe
 * rest-embed-details): WP_oEmbed_Controller and
 * WP_REST_URL_Details_Controller called directly, as a plugin would. The
 * site's own oEmbed for a post (sizes, a format, a missing post), the
 * proxy's permission check for nobody, an administrator and a contributor
 * and its answer for one of the site's posts and for an address no
 * provider takes, and a page's details as the editor's link previews read
 * them: its title, icon, description and image, from pages the probe
 * writes into uploads (one with everything, one with nothing, one in
 * another encoding), a missing page and an address that is not one, and
 * the second ask answered from the cache. The probe's files, user and
 * cached answers are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$upload = wp_get_upload_dir();
$files = [];
$cachedBefore = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_oembed\\_%' OR option_name LIKE '\\_transient\\_timeout\\_oembed\\_%' OR option_name LIKE '\\_site\\_transient\\_g\\_url\\_details\\_%' OR option_name LIKE '\\_site\\_transient\\_timeout\\_g\\_url\\_details\\_%'");
$made = ['users' => []];
register_shutdown_function(static function () use (&$files, &$made, $cachedBefore): void {
    global $wpdb;
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $cachedNow = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_oembed\\_%' OR option_name LIKE '\\_transient\\_timeout\\_oembed\\_%' OR option_name LIKE '\\_site\\_transient\\_g\\_url\\_details\\_%' OR option_name LIKE '\\_site\\_transient\\_timeout\\_g\\_url\\_details\\_%'");
    foreach (array_diff($cachedNow, $cachedBefore) as $name) {
        delete_option($name);
    }
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    wp_cache_flush();
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $host) {
    if (is_object($value) && !$value instanceof WP_Error) {
        $value = get_object_vars($value);
    }
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    // An embed's secret is drawn at random; its script is each stack's own, so only the address its sourceURL names is compared
    // (without the scheme WP-CLI forces).
    $value = (string) preg_replace('/(secret=|data-secret=")[A-Za-z0-9]{10}/', '$1{secret}', str_replace($host, '{host}', $value));
    return (string) preg_replace_callback('#<script>(.*?)</script>#s', static fn ($m) => '<script>{script}' . (preg_match('#//\# sourceURL=(?:https?://)?(\S+)#', $m[1], $source) === 1 ? " sourceURL={$source[1]}" : '') . '</script>', $value);
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $mask($response->get_error_message()), $mask($response->get_error_data())];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $mask($response)];
    }
    return [$response->get_status(), $mask($response->get_data())];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $route, array $params) {
    $request = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
$page = static function (string $name, string $html) use ($upload, &$files): string {
    $file = $upload['basedir'] . '/' . $name;
    file_put_contents($file, $html);
    $files[] = $file;
    return $upload['baseurl'] . '/' . $name;
};
$full = $page('zz-details-full.html', '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Zz Details &amp; More</title><meta name="description" content="A page the probe wrote."><meta property="og:image" content="/wp-content/uploads/zz-details-image.png"><meta property="og:description" content="The og description."><link rel="icon" href="/wp-content/uploads/zz-details-icon.png" sizes="32x32"><link rel="shortcut icon" href="/zz-other.ico"></head><body><h1>Body</h1></body></html>');
$bare = $page('zz-details-bare.html', '<html><body>No head here.</body></html>');
$latin = $page('zz-details-latin.html', '<html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><title>Caf' . "\xE9" . ' Zz</title><meta name="twitter:image" content="https://example.com/zz.png"></head></html>');

// Which tag wins when a page has only some of them.
$ogOnly = $page('zz-details-og.html', '<html><head><title>  Zz  spaced
 title </title><meta property="og:description" content="Only og."><meta property="og:image" content="images/zz-rel.png"><link rel="apple-touch-icon" href="/zz-apple.png"></head></html>');
$twitterOnly = $page('zz-details-twitter.html', '<html><head><meta name="twitter:description" content="Only twitter."><meta name="twitter:image" content="//cdn.example.com/zz.png"><link rel="shortcut icon" href="zz-short.ico"><meta property="og:title" content="An og title"></head></html>');
$ordered = $page('zz-details-order.html', '<html><head><meta property="og:description" content="og first"><meta name="description" content="named second"><meta name="twitter:image" content="/zz-twitter.png"><meta property="og:image" content="/zz-og.png"><link rel="icon" href="/zz-a.png"><link rel="icon" href="/zz-b.png"><title>A &lt;b&gt;title&lt;/b&gt;</title></head></html>');

$contributor = wp_insert_user(['user_login' => 'zzembedcontributor', 'user_email' => 'zzembedcontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-embed-details: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;
$post = get_post(1);
if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
    fwrite(STDERR, "rest-embed-details: post 1 is not there to embed\n");
    exit(1);
}

// The site's pages are served over HTTPS by a local authority no bundled certificate list trusts.
add_filter('rest_url_details_http_request_args', static fn ($args) => ['sslverify' => false] + (array) $args);
$oembed = new WP_oEmbed_Controller();
$details = new WP_REST_URL_Details_Controller();
$say('the classes', [get_parent_class($oembed), get_parent_class($details), $details->get_item_schema()]);

$say('the site\'s own oEmbed', [
    'a post' => $try(static fn () => $oembed->get_item($request('/oembed/1.0/embed', ['url' => get_permalink(1), 'format' => 'json', 'maxwidth' => 600]))),
    'narrow' => $try(static fn () => $oembed->get_item($request('/oembed/1.0/embed', ['url' => get_permalink(1), 'format' => 'json', 'maxwidth' => 120]))),
    'by its short link' => $try(static fn () => $oembed->get_item($request('/oembed/1.0/embed', ['url' => home_url('/?p=1'), 'format' => 'json', 'maxwidth' => 600]))),
    'a missing post' => $try(static fn () => $oembed->get_item($request('/oembed/1.0/embed', ['url' => home_url('/zz-no-such-post/'), 'format' => 'json', 'maxwidth' => 600]))),
]);

// The XML an embed is served as, and the text in it: entity references, ampersands, markup.
$say('the XML document\'s text', array_map(static fn ($text) => $mask(_oembed_create_xml(['html' => $text, 'title' => 'Zz &#8220;' . $text . '&#8221;'])), [
    'a &#8220; b', 'x && y', 'p &amp; q', 'n &nbsp; m', 'h &#x2014; i', 'lt &lt;b&gt; gt', 'raw <b>bold</b> "q" \'s\'', 'amp & alone', 'e &eacute; f', 'a&#8220;b&c',
]));

foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", [$try(static fn () => $oembed->get_proxy_item_permissions_check()), $try(static fn () => $details->permissions_check())]);
}

wp_set_current_user(1);
// The embed handlers answer what no provider takes: core's for media files, and one a plugin registers that queues a script.
wp_scripts();
wp_embed_register_handler('zzhandler', '#https://zz-handler\\.example/(\\w+)#i', static function ($matches) {
    wp_enqueue_script('zz-embed-script', 'https://zz-handler.example/zz.js', [], '1.0');
    return '<div class="zz-handled">' . esc_html($matches[1]) . '</div>';
});
$say('the proxy', [
    'one of the site\'s posts' => $try(static fn () => $oembed->get_proxy_item($request('/oembed/1.0/proxy', ['url' => get_permalink(1), 'format' => 'json', 'maxwidth' => 400, 'discover' => false]))),
    'an address no provider takes' => $try(static fn () => $oembed->get_proxy_item($request('/oembed/1.0/proxy', ['url' => 'https://zz-no-provider.invalid/watch', 'format' => 'json', 'maxwidth' => 400, 'discover' => false]))),
    'a video file' => $try(static fn () => $oembed->get_proxy_item($request('/oembed/1.0/proxy', ['url' => 'https://zz-media.example/zz.mp4', 'format' => 'json', 'maxwidth' => 400, 'discover' => false]))),
    'an audio file' => $try(static fn () => $oembed->get_proxy_item($request('/oembed/1.0/proxy', ['url' => 'https://zz-media.example/zz.mp3', 'format' => 'json', 'maxwidth' => 400, 'discover' => false]))),
    'a plugin\'s handler' => $try(static fn () => $oembed->get_proxy_item($request('/oembed/1.0/proxy', ['url' => 'https://zz-handler.example/thing', 'format' => 'json', 'maxwidth' => 400, 'discover' => false]))),
]);

$say('a page\'s details', [
    'everything' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $full]))),
    'nothing in the head' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $bare]))),
    'another encoding' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $latin]))),
    'og tags only' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $ogOnly]))),
    'twitter tags only' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $twitterOnly]))),
    'every kind, in an order' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $ordered]))),
    'a missing page' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $upload['baseurl'] . '/zz-details-none.html']))),
    'not an address' => $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => 'not an address']))),
]);
// The rules one by one, on pages answered by pre_http_request: which address is fetched and with what, and which tags count.
$fetched = [];
$canned = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$fetched, &$canned, $mask) {
    if (!str_starts_with($url, 'https://zz-details.example/')) {
        return $pre;
    }
    $fetched[] = [$url, $mask(array_intersect_key((array) $args, ['limit_response_size' => 1, 'user-agent' => 1]))];
    [$code, $body] = $canned[$url] ?? [200, ''];
    return ['headers' => [], 'body' => $body, 'response' => ['code' => $code, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, 10, 3);
$heard = [];
add_filter('rest_url_details_cache_expiration', static function ($ttl) use (&$heard) {
    $heard[] = ['rest_url_details_cache_expiration', $ttl];
    return $ttl;
});
add_filter('rest_prepare_url_details', static function ($response, $url, $request, $body) use (&$heard) {
    $heard[] = ['rest_prepare_url_details', get_class($response), $url, $request->get_route(), strlen($body)];
    return $response;
}, 10, 4);
$rule = static function (string $path, string $html, int $code = 200) use (&$canned, &$fetched, $details, $request, $try) {
    $url = 'https://zz-details.example/' . $path;
    $canned[rtrim($url, '/')] = [$code, $html];
    $fetched = [];
    return [$try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $url]))), $fetched];
};
$say('the rules', [
    'the address, with its slashes trimmed' => $rule('slash///', '<html><head><title>Slashed</title></head></html>'),
    'attributes in another order, single quotes' => $rule('order', "<html><head><meta content='Reversed.' name='description'><link href='/zz-href-first.png' rel='icon'><meta content='/zz-og-reversed.png' property='og:image'></head></html>"),
    'og:image:url and an upper-case rel' => $rule('ogurl', '<html><head><meta property="og:image:url" content="/zz-ogurl.png"><link rel="ICON" href="/zz-upper.png"></head></html>'),
    'protocol-relative and absolute addresses' => $rule('absolute', '<html><head><link rel="icon" href="//cdn.example.com/zz.ico"><meta property="og:image" content="http://other.example/zz.png"></head></html>'),
    'og:image by name' => $rule('ogname', '<html><head><meta name="og:image" content="/zz-by-name.png"><meta property="description" content="By property."></head></html>'),
    'entities in the description and an upper-case title' => $rule('entities', '<html><head><TITLE>Upper &#8211; Case</TITLE><meta name="description" content="A &amp; B &quot;quoted&quot; &lt;i&gt;"></head></html>'),
    'tags after the head' => $rule('body', '<html><head></head><body><title>Body title</title><meta name="description" content="In the body."><link rel="icon" href="/zz-body.png"></body></html>'),
    'no head element' => $rule('headless', '<html><title>Headless</title><meta name="description" content="Headless."><link rel="icon" href="/zz-headless.png"></html>'),
    'a head left open' => $rule('open', '<html><head><title>Open</title><meta name="description" content="  Spaced out.  "><body><p>Body.</p></body></html>'),
    'tags with nothing in them' => $rule('hollow', '<html><head><title>   </title><meta name="description"><meta property="og:image" content=""><link rel="icon"></head></html>'),
    'two titles' => $rule('titles', '<html><head><title>First</title><title>Second</title></head></html>'),
    'an empty page' => $rule('empty', ''),
    'a server error' => $rule('error', '<html><head><title>Oops</title></head></html>', 500),
    'a redirect answered as it is' => $rule('moved', '', 301),
    'no address' => [$try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => ''])))],
]);
$say('the hooks heard', $heard);

// The second ask comes from the cache: the page changed on disk, the answer did not.
file_put_contents($upload['basedir'] . '/zz-details-full.html', '<html><head><title>Changed</title></head></html>');
$say('a page\'s details again', $try(static fn () => $details->parse_url_details($request('/wp-block-editor/v1/url-details', ['url' => $full]))));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
