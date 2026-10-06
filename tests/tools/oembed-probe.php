<?php
/**
 * oEmbed as the reference answers it: the endpoint address, a post's
 * embed data and markup (its size limits, the filters it passes, a draft
 * refused), oembed/1.0/embed for the site's own addresses and nothing
 * else, and oembed/1.0/proxy fetching another site's embed for the editor
 * (who may ask, the provider address it fetches, the iframe titled, the
 * answer cached, the site's own address answered locally). Embed secrets
 * are random and masked; the provider is faked through pre_http_request.
 * Same protocol as api-probe.php; dispatched in process; its cache entries
 * and posts go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$mask = static function ($value) use (&$mask) {
    if (is_array($value) || is_object($value)) {
        return array_map($mask, (array) $value);
    }
    return is_string($value) ? (string) preg_replace('/secret(=|="|%3D)[A-Za-z0-9]{10}/', 'secret$1{secret}', $value) : $value;
};
$published = get_posts(['numberposts' => 1, 'post_status' => 'publish', 'orderby' => 'ID', 'order' => 'ASC'])[0];
$url = get_permalink($published);
$draft = (int) wp_insert_post(['post_title' => 'zz probe embed draft', 'post_status' => 'draft']);
$say('endpoint', [get_oembed_endpoint_url(), get_oembed_endpoint_url($url), get_oembed_endpoint_url($url, 'xml'), get_post_embed_url($published)]);
$handed = [];
add_filter('oembed_response_data', static function ($data, $post, $width, $height) use (&$handed) {
    $handed[] = ['oembed_response_data', $data['type'] ?? null, $post->ID > 0, $width, $height];
    return $data;
}, 20, 4);
add_filter('embed_html', static function ($html, $post, $width, $height) use (&$handed) {
    $handed[] = ['embed_html', $post->ID > 0, $width, $height];
    return $html;
}, 10, 4);
$say('data', [$mask(get_oembed_response_data($published, 300)), get_oembed_response_data($draft, 300), get_oembed_response_data(0, 300), $handed]);
$narrow = static fn () => ['min' => 100, 'max' => 400];
add_filter('oembed_min_max_width', $narrow);
$say('narrowed', array_intersect_key((array) get_oembed_response_data($published, 1000), array_flip(['width', 'height'])));
remove_filter('oembed_min_max_width', $narrow);
$send = static function (string $label, string $route, array $query) use ($say, $mask): void {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    $say($label, [$response->get_status(), $mask(rest_get_server()->response_to_data($response, false))]);
};
foreach (['default' => [], 'narrow' => ['maxwidth' => 300], 'wide' => ['maxwidth' => 1000], 'word' => ['maxwidth' => 'abc'], 'xml' => ['format' => 'xml']] as $label => $extra) {
    $send("embed {$label}", '/oembed/1.0/embed', ['url' => $url] + $extra);
}
$send('embed missing page', '/oembed/1.0/embed', ['url' => home_url('/zz-nothing-here/')]);
$send('embed elsewhere', '/oembed/1.0/embed', ['url' => 'https://example.com/x']);
$send('embed no url', '/oembed/1.0/embed', []);
$fetched = [];
$fake = static function ($pre, $args, $address) use (&$fetched) {
    $fetched[] = $address;
    if (str_contains($address, 'youtube.com/oembed')) {
        return ['headers' => ['content-type' => 'application/json'], 'body' => json_encode(['type' => 'video', 'version' => '1.0', 'title' => 'Probe Video', 'html' => '<iframe src="https://www.youtube.com/embed/zzprobe"></iframe>', 'width' => 200, 'height' => 113, 'provider_name' => 'YouTube']), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    return ['headers' => [], 'body' => 'nope', 'response' => ['code' => 404, 'message' => 'Not Found'], 'cookies' => [], 'filename' => null];
};
add_filter('pre_http_request', $fake, 10, 3);
$video = 'https://www.youtube.com/watch?v=zzprobe' . wp_rand(1000, 9999);
wp_set_current_user(0);
$send('proxy visitor', '/oembed/1.0/proxy', ['url' => $video]);
wp_set_current_user(3);
$send('proxy video', '/oembed/1.0/proxy', ['url' => $video]);
$send('proxy video again', '/oembed/1.0/proxy', ['url' => $video]);
$send('proxy video narrow', '/oembed/1.0/proxy', ['url' => $video, 'maxwidth' => 300]);
$send('proxy unknown', '/oembed/1.0/proxy', ['url' => 'https://example.com/nothing', 'discover' => false]);
$send('proxy own post', '/oembed/1.0/proxy', ['url' => $url, 'maxwidth' => 300]);
wp_set_current_user(0);
remove_filter('pre_http_request', $fake, 10);
$say('fetched', array_map(static fn ($address) => str_replace((string) parse_url($video, PHP_URL_QUERY), 'v={video}', urldecode($address)), $fetched));
$say('iframe titles', [
    wp_filter_oembed_iframe_title_attribute('<iframe src="x"></iframe>', (object) ['title' => 'A "quoted" title'], 'x'),
    wp_filter_oembed_iframe_title_attribute('<iframe title="Kept" src="x"></iframe>', (object) ['title' => 'New'], 'x'),
    wp_filter_oembed_iframe_title_attribute('<div>no frame</div>', (object) ['title' => 'New'], 'x'),
    wp_filter_oembed_iframe_title_attribute('<iframe src="x"></iframe>', (object) [], 'x'),
]);
global $wpdb;
foreach ($wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_oembed\\_%'") as $row) {
    if (str_contains((string) $row->option_value, 'zzprobe')) {
        delete_transient(substr($row->option_name, strlen('_transient_')));
    }
}
wp_delete_post($draft, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
