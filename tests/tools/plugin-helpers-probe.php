<?php
/**
 * Helpers plugins call that the engine once answered with a constant: the
 * deferred-count and cache-addition switches (they remember what they were
 * told), timer_start, wp_raise_memory_limit and its context filters,
 * wp_remote_fopen over a faked response, wp_mime_type_icon,
 * update_meta_cache's answer, get_custom_logo for a logo kept as a theme
 * mod, wp_oembed_get through pre_oembed_result, and rest_filter_response_fields
 * trimming a plugin route's answer to _fields. Same protocol as
 * api-probe.php; what it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$say('defer term counting', [wp_defer_term_counting(), wp_defer_term_counting(true), wp_defer_term_counting(), wp_defer_term_counting('yes'), wp_defer_term_counting(false), wp_defer_term_counting()]);
$say('defer comment counting', [wp_defer_comment_counting(), wp_defer_comment_counting(true), wp_defer_comment_counting(), wp_defer_comment_counting(false), wp_defer_comment_counting()]);
$say('suspend cache addition', [
    wp_suspend_cache_addition(), wp_suspend_cache_addition(true), wp_suspend_cache_addition(),
    wp_cache_add('zz_probe_key', 1, 'zz_probe'), wp_cache_get('zz_probe_key', 'zz_probe'),
    wp_suspend_cache_addition(false), wp_cache_add('zz_probe_key', 2, 'zz_probe'), wp_cache_get('zz_probe_key', 'zz_probe'),
]);
wp_cache_delete('zz_probe_key', 'zz_probe');
$say('timer', [timer_start(), is_float($GLOBALS['timestart'] ?? null), (bool) preg_match('/^\d+\.\d{2}$/', timer_stop(0, 2))]);

// Memory: from a limit below the most WordPress allows, each context raised through its own filter.
$limit = ini_get('memory_limit');
$asked = [];
foreach (['admin_memory_limit', 'image_memory_limit', 'zz_probe_memory_limit'] as $hook) {
    add_filter($hook, static function ($value) use (&$asked, $hook) {
        $asked[] = [$hook, $value];
        return $value;
    });
}
$raised = [];
foreach (['admin', 'image', 'zz_probe'] as $context) {
    ini_set('memory_limit', '200M');
    $raised[] = [$context, wp_raise_memory_limit($context), ini_get('memory_limit')];
}
foreach (['1G', '-1', '64M'] as $wanted) {
    $higher = static fn () => $wanted;
    add_filter('zz_probe_memory_limit', $higher, 20);
    ini_set('memory_limit', '200M');
    $raised[] = ["filtered to {$wanted}", wp_raise_memory_limit('zz_probe'), ini_get('memory_limit')];
    remove_filter('zz_probe_memory_limit', $higher, 20);
}
ini_set('memory_limit', '-1');
$raised[] = ['from unlimited', wp_raise_memory_limit('admin'), ini_get('memory_limit')];
ini_set('memory_limit', $limit);
// The most WordPress allows follows the PHP it runs under; it is named, not compared.
$say('memory', ['raised' => array_map(static fn ($row) => array_map(static fn ($v) => $v === WP_MAX_MEMORY_LIMIT ? '{max}' : $v, $row), $raised), 'filters' => array_map(static fn ($row) => array_map(static fn ($v) => $v === WP_MAX_MEMORY_LIMIT ? '{max}' : $v, $row), $asked)]);

$fetched = [];
$fake = static function ($pre, $args, $url) use (&$fetched) {
    $fetched[] = [$url, $args['method'] ?? null, $args['timeout'] ?? null, $args['reject_unsafe_urls'] ?? null];
    $code = str_contains($url, 'missing') ? 404 : 200;
    return ['headers' => [], 'body' => "body of {$url}", 'response' => ['code' => $code, 'message' => $code === 200 ? 'OK' : 'Not Found'], 'cookies' => [], 'filename' => null];
};
add_filter('pre_http_request', $fake, 10, 3);
$say('remote fopen', [wp_remote_fopen('https://example.com/file.txt'), wp_remote_fopen('https://example.com/missing'), $fetched]);
remove_filter('pre_http_request', $fake, 10);

$mimes = ['application/pdf', 'image/png', 'image', 'video/mp4', 'audio/mpeg', 'text/plain', 'text/html', 'application/zip', 'application/vnd.ms-excel', 'application/x-unknown', 'archive', 'nonsense'];
$icon = static fn ($url) => is_string($url) ? str_replace(includes_url(), '{includes}/', $url) : $url;
$say('mime icons', array_map(static fn ($m) => $icon(wp_mime_type_icon($m)), $mimes));
// Each type's icon is kept once found: asked again for another kind of file, the first answer stands.
$say('mime icons svg', array_map(static fn ($m) => $icon(wp_mime_type_icon($m, '.svg')), $mimes));
$fresh = ['spreadsheet', 'text/csv', 'application/msword', 'code', 'interactive', 'document_x'];
$iconHooks = [];
add_filter('wp_mime_type_icon', static function ($url, $mime, $id) use (&$iconHooks, $icon) {
    $iconHooks[] = [$icon($url), $mime, $id];
    return $url;
}, 10, 3);
$say('mime icons svg first', [array_map(static fn ($m) => $icon(wp_mime_type_icon($m, '.svg')), $fresh), array_map(static fn ($m) => $icon(wp_mime_type_icon($m)), $fresh), $iconHooks]);

$post = wp_insert_post(['post_title' => 'zz probe helpers', 'post_status' => 'draft']);
add_post_meta($post, 'zz_a', 'one');
add_post_meta($post, 'zz_a', 'two');
add_post_meta($post, 'zz_b', ['x' => 1]);
wp_cache_delete($post, 'post_meta');
$cache = update_meta_cache('post', [$post]);
$mine = static fn ($meta) => is_array($meta) ? array_intersect_key($meta, array_flip(['zz_a', 'zz_b'])) : $meta;
$say('meta cache', [
    array_keys((array) $cache) === [$post],
    $mine($cache[$post] ?? null),
    $mine((update_meta_cache('post', (string) $post))[$post] ?? null),
    update_meta_cache('nonsense', [$post]),
    update_meta_cache('post', []),
    update_meta_cache('post', [0]),
]);

// A logo kept as a theme mod: the image linked home, its alt the site's name unless it has its own.
$logo = wp_insert_attachment(['post_title' => 'zz probe logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], '2026/10/zz-probe-logo.png');
update_post_meta($logo, '_wp_attached_file', '2026/10/zz-probe-logo.png');
update_post_meta($logo, '_wp_attachment_metadata', ['width' => 200, 'height' => 100, 'file' => '2026/10/zz-probe-logo.png', 'sizes' => []]);
$hadLogo = get_theme_mod('custom_logo');
$mask = static fn ($html) => str_replace([(string) $logo, wp_get_upload_dir()['baseurl'], home_url()], ['{logo}', '{uploads}', '{home}'], (string) $html);
$handed = [];
add_filter('get_custom_logo_image_attributes', static function ($attrs, $id) use (&$handed, $logo) {
    $handed[] = ['image attributes', $attrs, $id === $logo];
    return $attrs;
}, 10, 2);
add_filter('get_custom_logo', static function ($html, $blog) use (&$handed, $mask) {
    $handed[] = ['get_custom_logo', $mask($html), $blog];
    return $html;
}, 10, 2);
$logos = [has_custom_logo(), $mask(get_custom_logo())];
set_theme_mod('custom_logo', $logo);
$logos[] = has_custom_logo();
$logos[] = $mask(get_custom_logo());
update_post_meta($logo, '_wp_attachment_image_alt', 'Our mark');
$logos[] = $mask(get_custom_logo());
ob_start();
the_custom_logo();
$logos[] = $mask(ob_get_clean());
$doc = wp_insert_attachment(['post_title' => 'zz probe doc', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'], '2026/10/zz-probe-doc.pdf');
update_post_meta($doc, '_wp_attached_file', '2026/10/zz-probe-doc.pdf');
$say('attachment icons', [$icon(wp_mime_type_icon($logo)), $icon(wp_mime_type_icon($doc)), $icon(wp_mime_type_icon($doc, '.svg'))]);
// On the front page the link says so; a theme that unlinks the home page logo gets it unlinked there.
global $wp_query;
$wasHome = $wp_query->is_home;
$wp_query->is_home = true;
$logos[] = [is_front_page(), $mask(get_custom_logo())];
add_theme_support('custom-logo', ['unlink-homepage-logo' => true]);
delete_post_meta($logo, '_wp_attachment_image_alt');
$logos[] = $mask(get_custom_logo());
$wp_query->is_home = $wasHome;
$logos[] = $mask(get_custom_logo());
remove_theme_support('custom-logo');
$say('custom logo', ['answers' => $logos, 'handed' => array_map(static fn ($row) => is_array($row[1] ?? null) ? [$row[0], array_map(static fn ($v) => is_string($v) ? $mask($v) : $v, $row[1]), $row[2]] : $row, $handed)]);
$hadLogo === false ? remove_theme_mod('custom_logo') : set_theme_mod('custom_logo', $hadLogo);

$pre = static fn ($result, $url, $args) => str_contains($url, 'zz-probe') ? '<iframe src="' . esc_url($url) . '" width="' . ($args['width'] ?? '') . '"></iframe>' : $result;
add_filter('pre_oembed_result', $pre, 10, 3);
$say('oembed', [wp_oembed_get('https://www.youtube.com/watch?v=zz-probe'), wp_oembed_get('https://example.com/zz-probe', ['width' => 300]), wp_oembed_get('https://example.com/nothing-here', ['discover' => false])]);
remove_filter('pre_oembed_result', $pre, 10);

$data = ['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => 4];
$trim = static function ($fields, $body) {
    $request = new WP_REST_Request('GET', '/zz-probe/v1/thing');
    if ($fields !== null) {
        $request->set_param('_fields', $fields);
    }
    $response = rest_filter_response_fields(new WP_REST_Response($body), rest_get_server(), $request);
    return $response instanceof WP_REST_Response ? $response->get_data() : get_class($response);
};
$say('response fields', [
    (bool) has_filter('rest_post_dispatch', 'rest_filter_response_fields'),
    $trim(null, $data),
    $trim('a,b.d', $data),
    $trim(['e', 'nothing'], $data),
    $trim('b', $data),
    $trim('a', [$data, ['a' => 5, 'b' => 6]]),
    $trim('', $data),
    has_filter('rest_post_dispatch', 'rest_filter_response_fields'),
]);

wp_delete_post($post, true);
wp_delete_post($logo, true);
wp_delete_post($doc, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
