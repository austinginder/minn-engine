<?php
/**
 * The rest of embed.php (probe embed-remaining): oEmbed XML, the REST
 * response served as XML, oEmbed data for a site URL and the rich data for
 * posts and images, the post embed HTML (its secret masked), the embed
 * template's pieces (site title, excerpt and its more link, attachment
 * excerpts, comments and sharing buttons, the sharing dialog), discovery
 * links, the embed scripts and styles (their text judged by whether it is
 * the reference's, then by shape), and the host script. The probe's posts and
 * comments are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    $value = (string) preg_replace('/(secret=|data-secret=(?:&quot;|["\'])|#\?secret=)[A-Za-z0-9]{10}/', '$1{secret}', $value);
    $value = (string) preg_replace('/(wp-embed-share-[a-z]+-[a-z]+-\d+-)\d+/', '$1{random}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
wp_set_current_user(1);

$say('oEmbed XML', [
    _oembed_create_xml(['version' => '1.0', 'type' => 'rich', 'title' => 'Zz & <co>', 'width' => 600, 'html' => '<blockquote>q</blockquote>']),
    _oembed_create_xml(['a' => ['b' => 'c', 'd' => [1, 2]], 'e' => true, 'f' => null, 'g' => 0]),
    _oembed_create_xml([]),
    _oembed_create_xml(['x' => 'y'], new SimpleXMLElement('<zz/>')),
]);

$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'zz em post "q" & co', 'post_name' => 'zz-em-post', 'post_status' => 'publish', 'post_content' => str_repeat('Words in the post. ', 80), 'post_excerpt' => '', 'post_date' => '1986-02-02 10:00:00']);
$ids[$post] = 'post';
$made['posts'][] = $short = (int) wp_insert_post(['post_title' => 'zz em short', 'post_status' => 'publish', 'post_content' => 'Short.', 'post_excerpt' => 'A given excerpt.', 'post_date' => '1986-02-03 10:00:00']);
$ids[$short] = 'short';
$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz em image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_excerpt' => 'Caption text'], '2026/10/zz-em-image.jpg', $post);
$ids[$image] = 'image';
wp_update_attachment_metadata($image, ['width' => 1200, 'height' => 800, 'file' => '2026/10/zz-em-image.jpg', 'sizes' => ['medium' => ['file' => 'zz-em-image-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'], 'large' => ['file' => 'zz-em-image-1024x683.jpg', 'width' => 1024, 'height' => 683, 'mime-type' => 'image/jpeg']]]);
$made['posts'][] = $file = (int) wp_insert_attachment(['post_title' => 'zz em file', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'], '2026/10/zz-em-file.pdf', $post);
$ids[$file] = 'file';
foreach (['first', 'second'] as $name) {
    $made['comments'][] = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => "zz em {$name}", 'comment_approved' => 1]);
}

$say('rich data', $mask([
    get_oembed_response_data_rich(['type' => 'rich', 'version' => '1.0'], get_post($post), 600, 338),
    get_oembed_response_data_rich(['type' => 'link'], get_post($post), 300, 200),
    get_oembed_response_data_rich(['type' => 'rich'], get_post($image), 500, 400),
    get_oembed_response_data_rich(['type' => 'rich'], get_post($file), 500, 400),
    get_oembed_response_data_rich(['type' => 'rich'], get_post($post), 0, 0),
]));
$say('oEmbed data for a URL', $mask([
    get_oembed_response_data_for_url(get_permalink($post), ['width' => 400]), get_oembed_response_data_for_url(get_permalink($image), ['width' => 300, 'height' => 300]),
    get_oembed_response_data_for_url(home_url('/zz-missing/'), ['width' => 400]), get_oembed_response_data_for_url('https://elsewhere.example/x', ['width' => 400]),
    get_oembed_response_data_for_url(home_url('/'), []),
]));
$ratioHeard = [];
add_filter('image_get_intermediate_size', $ratioFilter = static function ($data, $id, $size) use (&$ratioHeard) {
    $ratioHeard[] = [$data ? $data['file'] : $data, $size];
    return $data;
}, 10, 3);
$say('size ratios', [
    array_map(static fn ($four) => wp_image_matches_ratio(...$four), [[300, 200, 300, 169], [300, 169, 300, 200], [300, 200, 254, 169], [1200, 800, 300, 200], [1200, 800, 300, 199], [1200, 800, 300, 198], [200, 300, 169, 300], [300, 300, 300, 299], [301, 200, 300, 200], [1, 1, 1, 1]]),
    array_map(static function ($size) use ($image) {
        $found = image_get_intermediate_size($image, $size);
        return $found ? $found['file'] : $found;
    }, [[300, 169], [300, 200], [500, 0], [1024, 600], [299, 200]]),
    $ratioHeard,
]);
remove_filter('image_get_intermediate_size', $ratioFilter, 10);
$say('post embed HTML', $mask([get_post_embed_html(600, 338, $post), get_post_embed_html(200, 100, get_post($short)), get_post_embed_html(600, 338, 999999)]));

$restXml = static function (array $params, $result) use ($echoed) {
    $request = new WP_REST_Request('GET', '/oembed/1.0/embed');
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $served = null;
    $printed = $echoed(static function () use (&$served, $result, $request) {
        $served = _oembed_rest_pre_serve_request(false, $result, $request, rest_get_server());
    });
    return [$served, $printed];
};
$say('the REST response as XML', $mask([
    $restXml(['format' => 'xml', 'url' => get_permalink($post)], new WP_REST_Response(['version' => '1.0', 'type' => 'rich', 'title' => 'T'])),
    $restXml(['format' => 'json'], new WP_REST_Response(['version' => '1.0'])),
    $restXml([], new WP_REST_Response(['version' => '1.0'])),
    (static function () use ($echoed) {
        $request = new WP_REST_Request('GET', '/wp/v2/posts');
        $request->set_param('format', 'xml');
        $served = null;
        $printed = $echoed(static function () use (&$served, $request) {
            $served = _oembed_rest_pre_serve_request(false, new WP_REST_Response(['x' => 1]), $request, rest_get_server());
        });
        return [$served, $printed];
    })(),
]));

$embedContext = static function (int $id) {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query(['p' => $id, 'post_type' => 'any', 'post_status' => ['publish', 'inherit'], 'embed' => true]);
    $wp_query->is_embed = true;
    $wp_query->the_post();
};
$pieces = static fn () => [
    $echoed(static fn () => the_embed_site_title()), $echoed(static fn () => the_excerpt_embed()), $echoed(static fn () => print_embed_comments_button()), $echoed(static fn () => print_embed_sharing_button()), $echoed(static fn () => print_embed_sharing_dialog()),
    wp_embed_excerpt_more(' [&hellip;]'), wp_embed_excerpt_attachment('given content'),
];
$embedContext($post);
$templates = [$pieces()];
$embedContext($short);
$templates[] = $pieces();
$embedContext($image);
$templates[] = $pieces();
$embedContext($file);
$templates[] = $pieces();
$say('the embed template pieces', $mask($templates));
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['p' => $post]);
$GLOBALS['wp_query']->the_post();
$say('the pieces outside an embed', $mask([wp_embed_excerpt_more(' [&hellip;]'), wp_embed_excerpt_attachment('given content'), $echoed(static fn () => wp_oembed_add_discovery_links())]));
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 1]);
$say('discovery links off a single post', $mask($echoed(static fn () => wp_oembed_add_discovery_links())));

$heard = [];
add_action('enqueue_embed_scripts', $embedHeard = static function () use (&$heard) {
    $heard[] = 'enqueue_embed_scripts';
});
enqueue_embed_scripts();
remove_action('enqueue_embed_scripts', $embedHeard);
$scripts = $echoed(static fn () => print_embed_scripts());
$styleBefore = has_action('embed_head', 'print_embed_styles');
wp_enqueue_embed_styles();
$inline = (array) (wp_styles()->get_data('wp-embed-template', 'after') ?: []);
$say('embed scripts and styles', $mask([
    $heard, preg_match('#^<script>\n?.*</script>\n$#s', $scripts) === 1, str_contains($scripts, '//# sourceURL='), substr_count($scripts, '<script'),
    $styleBefore, has_action('embed_head', 'print_embed_styles'), wp_style_is('wp-embed-template', 'enqueued'), wp_styles()->registered['wp-embed-template']->src ?? null, count($inline), strlen(implode('', $inline)) > 100,
]));

$hostHeard = [];
add_action('deprecated_function_run', $deprecation = static function ($function, $replacement, $version) use (&$hostHeard) {
    $hostHeard[] = [$function, $replacement, $version];
}, 10, 3);
$hostPrinted = $echoed(static fn () => wp_oembed_add_host_js());
remove_action('deprecated_function_run', $deprecation, 10);
$say('the host script', [$hostPrinted, $hostHeard, wp_script_is('wp-embed', 'enqueued')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
