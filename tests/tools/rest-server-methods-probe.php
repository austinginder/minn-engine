<?php
/**
 * The REST server's remaining methods (probe rest-server-methods): the
 * target hints a link carries for the caller; linked resources embedded in
 * a response's data (all of them, the rels asked for, none); and the index
 * with its active-theme link, site logo and site icon, for a visitor and an
 * administrator, with and without a logo and an icon set. The options it
 * sets are put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// Both stacks run this without HTTPS, where application passwords are off; the reference's own must-use plugin turns them on.
add_filter('wp_is_application_passwords_available', '__return_true');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$server = rest_get_server();
$call = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $server, WP_REST_Server::class)(...$args));
$static = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(static fn (...$given) => static::$method(...$given), null, WP_REST_Server::class)(...$args));

$options = [];
foreach (['site_logo', 'site_icon'] as $name) {
    $options[$name] = get_option($name, null);
}
$made = [];
register_shutdown_function(static function () use ($options, &$made): void {
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ rsm post', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz']);
require_once ABSPATH . 'wp-admin/includes/user.php';
$author = wp_insert_user(['user_login' => 'zz_rsm_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rsm@example.com', 'role' => 'author']);
$subscriber = wp_insert_user(['user_login' => 'zz_rsm_subscriber', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rsm-s@example.com', 'role' => 'subscriber']);
register_shutdown_function(static function () use ($author, $subscriber): void {
    foreach ([$author, $subscriber] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
$image = wp_insert_attachment(['post_title' => 'ZZ rsm logo', 'post_mime_type' => 'image/png', 'post_author' => 1, 'post_status' => 'inherit']);
foreach ([$post, $image] as $id) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[] = $id;
}
wp_set_current_user(0);
$ids = $made;
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    $json = preg_replace('/\b20\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d\b/', '{date}', $json);
    return json_decode($json, true);
};

$links = static fn () => [
    'a post' => $static('get_target_hints_for_link', ['href' => rest_url('wp/v2/posts/' . $post), 'attributes' => []]),
    'the posts' => $static('get_target_hints_for_link', ['href' => rest_url('wp/v2/posts'), 'attributes' => []]),
    'a user' => $static('get_target_hints_for_link', ['href' => rest_url('wp/v2/users/1'), 'attributes' => []]),
    'with a query' => $static('get_target_hints_for_link', ['href' => rest_url('wp/v2/posts/' . $post) . '?context=edit', 'attributes' => []]),
    'no route' => $static('get_target_hints_for_link', ['href' => rest_url('zz/v1/none'), 'attributes' => []]),
    'elsewhere' => $static('get_target_hints_for_link', ['href' => 'https://example.com/wp-json/wp/v2/posts/1', 'attributes' => []]),
    'already hinted' => $static('get_target_hints_for_link', ['href' => rest_url('wp/v2/posts/' . $post), 'attributes' => ['targetHints' => ['allow' => ['GET']]]]),
];
$visitorLinks = $links();
wp_set_current_user(1);
$say('target hints', $masked(['a visitor' => $visitorLinks, 'an administrator' => $links()]));

// A post's data with its links, as response_to_data leaves them, embedded three ways.
$response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/posts/' . $post));
$data = $server->response_to_data($response, false);
$embedded = static function ($embed) use ($call, $data) {
    $out = $call('embed_links', $data, $embed);
    if (!is_array($out) || !isset($out['_embedded'])) {
        return is_array($out) ? ['keys' => array_keys($out)] : $out;
    }
    // Each embedded resource as its id, type and fields (its full shape is the posts' and users' probes' business).
    return ['rels' => array_keys($out['_embedded']), 'items' => array_map(static fn ($items) => array_map(static fn ($item) => is_array($item) ? ['id' => $item['id'] ?? null, 'code' => $item['code'] ?? null, 'keys' => array_keys($item)] : $item, (array) $items), $out['_embedded'])];
};
$say('embedded', $masked([
    'everything' => $embedded(true),
    'the author' => $embedded(['author']),
    'replies and terms' => $embedded(['replies', 'wp:term']),
    'nothing' => $embedded(false),
    'an unknown rel' => $embedded(['zz']),
    'no links' => $call('embed_links', ['id' => 1], true),
    'through response_to_data' => (static function () use ($server, $response) {
        $out = $server->response_to_data($response, ['author']);
        return isset($out['_embedded']) ? array_map(static fn ($items) => array_map(static fn ($item) => array_keys((array) $item), $items), $out['_embedded']) : array_keys($out);
    })(),
    'through response_to_data, not embedding' => array_keys($server->response_to_data($response, false)),
    'a link to nowhere' => (static function () use ($call) {
        $out = $call('embed_links', ['id' => 1, '_links' => ['author' => [['href' => rest_url('wp/v2/users/999999'), 'embeddable' => true]], 'about' => [['href' => rest_url('wp/v2/types/post')]]]], true);
        return $out['_embedded'] ?? array_keys($out);
    })(),
]));

$index = static function () use ($server) {
    $response = $server->get_index(new WP_REST_Request('GET', '/'));
    $data = $response->get_data();
    unset($data['routes'], $data['namespaces']);
    return ['data' => $data, 'links' => $response->get_links()];
};
$parts = static function () use ($call) {
    $out = [];
    foreach (['add_active_theme_link_to_index', 'add_site_logo_to_index', 'add_site_icon_to_index'] as $method) {
        $response = new WP_REST_Response(['kept' => true]);
        $call($method, $response);
        $out[$method] = ['data' => $response->get_data(), 'links' => $response->get_links()];
    }
    $response = new WP_REST_Response([]);
    $call('add_image_to_index', $response, $GLOBALS['zz_rsm_image'] ?? 0, 'zz_image');
    $out['an image'] = ['data' => $response->get_data(), 'links' => $response->get_links()];
    $response = new WP_REST_Response([]);
    $call('add_image_to_index', $response, 0, 'zz_none');
    $out['no image'] = ['data' => $response->get_data(), 'links' => $response->get_links()];
    return $out;
};
$GLOBALS['zz_rsm_image'] = $image;
$state = [];
delete_option('site_logo');
delete_option('site_icon');
wp_set_current_user(0);
$state['a visitor, nothing set'] = [$parts(), $index()];
wp_set_current_user(1);
$state['an administrator, nothing set'] = [$parts(), $index()];
update_option('site_logo', $image);
update_option('site_icon', $image);
$state['an administrator, both set'] = [$parts(), $index()];
wp_set_current_user(0);
$state['a visitor, both set'] = [$parts(), $index()];
$state['who gets the active theme link'] = (static function () use ($call, $author, $subscriber) {
    $out = [];
    foreach (['an author' => $author, 'a subscriber' => $subscriber] as $who => $id) {
        wp_set_current_user($id);
        $response = new WP_REST_Response([]);
        $call('add_active_theme_link_to_index', $response);
        $out[$who] = array_keys($response->get_links());
    }
    wp_set_current_user(0);
    return $out;
})();
$say('the index', $masked($state));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
