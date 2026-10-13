<?php
/**
 * The icons API (probe icons): icon collections and icons registered,
 * refused, looked up, searched and unregistered through the functions and
 * both registries, SVG content sanitized and read from a file, icons
 * printed with wp_get_icon, and the two REST controllers (icons and icon
 * collections) called directly and as routed. The reference's own icon
 * library is GPL art the engine does not ship, so lists are narrowed to
 * the probe's own collection. The probe's SVG file and registrations are
 * removed; it writes nothing else.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$triggered = [];
add_action('wp_trigger_error_run', static function ($function, $message) use (&$triggered): void {
    $triggered[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('wp_trigger_error_trigger_error', '__return_false');
$made_users = [];
register_shutdown_function(static function () use (&$made_users): void {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made_users as $id) {
        if (is_int($id) && $id > 0) {
            // A failed insert casts to user 1: never delete it.
            if (is_int($id) && $id > 1) {
                wp_delete_user($id);
            }
        }
    }
});
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
$file = rtrim(get_temp_dir(), '/') . '/zz-icon-' . getmypid() . '.svg';
register_shutdown_function(static function () use ($file): void {
    if (is_file($file)) {
        unlink($file);
    }
});
file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h22v22H1z"/></svg>');
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $host, $file) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace([$host, $file], ['{host}', '{file}'], $value) : $value;
};
$star = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z"/></svg>';

$collections = WP_Icon_Collections_Registry::get_instance();
$icons = WP_Icons_Registry::get_instance();
$say('the registries', [
    $collections === WP_Icon_Collections_Registry::get_instance(), $icons === WP_Icons_Registry::get_instance(),
    $mask($collections->get_all_registered()),
    $collections->is_registered('core'),
]);

$say('collections registered', [
    $heard(static fn () => wp_register_icon_collection('zz-set', ['label' => 'Zz Set', 'description' => 'Probe icons.'])),
    $heard(static fn () => wp_register_icon_collection('zz-set', ['label' => 'Again'])),
    $heard(static fn () => wp_register_icon_collection('Zz Bad', ['label' => 'Bad'])),
    $heard(static fn () => wp_register_icon_collection('zz-nolabel', [])),
    $heard(static fn () => wp_register_icon_collection('zz-extra', ['label' => 'Extra', 'description' => 5, 'other' => 'x'])),
    $heard(static fn () => wp_register_icon_collection('zz-desc', ['label' => 'Desc', 'description' => 5])),
    $heard(static fn () => wp_register_icon_collection('zz-onlylabel', ['label' => 'Only label'])),
    $heard(static fn () => wp_register_icon_collection('-zz', ['label' => 'Dash'])),
    $heard(static fn () => wp_register_icon_collection('zz_under', ['label' => 'Under'])),
    $heard(static fn () => $collections->get_registered('zz-set')),
    $heard(static fn () => $collections->get_registered('zz-onlylabel')),
    $heard(static fn () => $collections->get_registered('zz-none')),
    $collections->is_registered('zz-set'), $collections->is_registered('zz-none'),
]);

$say('icons registered', [
    $heard(static fn () => wp_register_icon('zz-set/star', ['label' => 'Zz Star', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/square', ['label' => 'Zz Square', 'file_path' => $file])),
    $heard(static fn () => wp_register_icon('zz-set/star', ['label' => 'Twice', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-none/star', ['label' => 'No collection', 'content' => $star])),
    $heard(static fn () => wp_register_icon('star', ['label' => 'No namespace', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/Bad Name', ['label' => 'Bad', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/nolabel', ['content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/nothing', ['label' => 'Nothing'])),
    $heard(static fn () => wp_register_icon('zz-set/both', ['label' => 'Both', 'content' => $star, 'file_path' => $file])),
    $heard(static fn () => wp_register_icon('zz-set/missing-file', ['label' => 'Missing', 'file_path' => $file . '.none'])),
    $heard(static fn () => wp_register_icon('zz-set/unsafe', ['label' => 'Unsafe', 'content' => '<svg onload="alert(1)" viewBox="0 0 24 24"><script>alert(1)</script><path d="M0 0" onclick="x()"/><foreignObject><p>x</p></foreignObject></svg>'])),
    $heard(static fn () => wp_register_icon('zz-set/not-svg', ['label' => 'Not SVG', 'content' => '<p>not an svg</p>'])),
    $heard(static fn () => wp_register_icon('zz-set/extra-prop', ['label' => 'Extra', 'content' => $star, 'zz' => 1])),
    $heard(static fn () => wp_register_icon('zz-set/a/b', ['label' => 'Deep', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/-dash', ['label' => 'Dash', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/under_score', ['label' => 'Under', 'content' => $star])),
    $heard(static fn () => wp_register_icon('zz-set/content-int', ['label' => 'Int', 'content' => 5])),
]);

$inside = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
$say('content', [
    $mask($icons->get_registered_icon('zz-set/star')),
    $mask($icons->get_registered_icon('zz-set/square')),
    $mask($icons->get_registered_icon('zz-set/unsafe')),
    $heard(static fn () => $icons->get_registered_icon('zz-set/never')),
    $mask($inside($icons, 'get_content', 'zz-set/square')),
    $mask($inside($icons, 'get_content', 'zz-set/never')),
    $inside($icons, 'sanitize_icon_content', '<svg viewBox="0 0 24 24" class="x" style="fill:red" data-x="1" aria-hidden="true" focusable="false"><g fill="none"><circle cx="1" cy="1" r="1"/><rect x="1" y="1" width="2" height="2" rx="1"/><polygon points="0,0 1,1"/><line x1="0" y1="0" x2="1" y2="1"/></g></svg>'),
    $inside($icons, 'sanitize_icon_content', '<svg><a href="javascript:alert(1)"><path d="M0"/></a><use href="#x"/><image href="x.png"/></svg>'),
    $inside($icons, 'sanitize_icon_content', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="x" version="1.1" fill="red" width="1" height="2" viewBox="0 0 1 1" role="img" aria-label="l" aria-labelledby="t" aria-describedby="d" id="i" class="c" focusable="false" aria-hidden="true" stroke="s" style="x" preserveAspectRatio="none" transform="t"><title>T</title><desc>D</desc><defs></defs><path d="M0" fill="f" fill-rule="evenodd" clip-rule="evenodd" stroke="s" stroke-width="1" transform="t" opacity="1" class="pc" id="pi" style="x" vector-effect="v"/><polygon points="0,0" fill="f" fill-rule="r" transform="t" class="c" stroke="s"/><polyline points="0"/><ellipse cx="1"/><text>t</text><mask></mask><clipPath id="c"><path d="M1"/></clipPath><linearGradient></linearGradient><stop/><symbol></symbol></svg>'),
    $inside($icons, 'sanitize_icon_content', ''),
    $inside($icons, 'sanitize_icon_content', 'plain text'),
]);

$say('lookups', [
    $icons->is_registered('zz-set/star'), $icons->is_registered('zz-set/never'),
    array_values(array_filter(array_column($mask($icons->get_registered_icons()), 'name'), static fn ($name) => str_starts_with((string) $name, 'zz-'))),
    array_column($mask($icons->get_registered_icons('zz star')), 'name'),
    array_column($mask($icons->get_registered_icons('Square')), 'name'),
    array_values(array_filter(array_column($mask($icons->get_registered_icons('zz-set')), 'name'), static fn ($name) => str_starts_with((string) $name, 'zz-'))),
]);

$say('icons printed', [
    $mask(wp_get_icon('zz-set/star')),
    $mask(wp_get_icon('zz-set/square', ['class' => 'zz-extra', 'size' => 32])),
    $mask(wp_get_icon('zz-set/star', ['size' => '2em', 'label' => 'A star'])),
    $mask(wp_get_icon('zz-set/star', ['size' => 32, 'label' => 'A star'])),
    $mask(wp_get_icon('zz-set/star', ['size' => '2em'])),
    $mask(wp_get_icon('zz-set/star', ['size' => '48'])),
    $mask(wp_get_icon('zz-set/star', ['size' => 0])),
    $mask(wp_get_icon('zz-set/star', ['class' => 'a b', 'label' => '', 'zz' => 'x'])),
    $mask(wp_get_icon('zz-set/missing-file')),
    $mask(wp_get_icon('zz-set/not-svg')),
    $heard(static fn () => wp_get_icon('zz-set/never')),
    $heard(static fn () => wp_get_icon('')),
]);

wp_set_current_user(0);
$controller = new WP_REST_Icons_Controller();
$set_controller = new WP_REST_Icon_Collections_Controller();
$request = static function (string $route, array $params = []) {
    $out = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $out->set_param($key, $value);
    }
    return $out;
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['value', $mask($response)];
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links()), $mask($headers)];
};
$say('rest permissions, nobody', [
    $answer($controller->get_items_permissions_check($request('/wp/v2/icons'))),
    $answer($controller->get_item_permissions_check($request('/wp/v2/icons/zz-set/star', ['name' => 'zz-set/star']))),
    $answer($set_controller->get_items_permissions_check($request('/wp/v2/icon-collections'))),
    $answer($set_controller->get_item_permissions_check($request('/wp/v2/icon-collections/zz-set', ['slug' => 'zz-set']))),
]);
$editor = get_users(['role' => 'editor', 'number' => 1, 'fields' => 'ID']);
$made_users[] = $zz_subscriber = (int) wp_insert_user(['user_login' => 'zz_icons_sub_' . getmypid(), 'user_pass' => wp_generate_password(), 'user_email' => 'zz-icons-' . getmypid() . '@example.com', 'role' => 'subscriber']);
$subscriber = [$zz_subscriber];
$say('rest permissions, signed in', [
    (static function () use ($subscriber, $controller, $set_controller, $request, $answer) {
        if (!$subscriber) {
            return 'no subscriber';
        }
        wp_set_current_user((int) $subscriber[0]);
        return [$answer($controller->get_items_permissions_check($request('/wp/v2/icons'))), $answer($set_controller->get_items_permissions_check($request('/wp/v2/icon-collections')))];
    })(),
    (static function () use ($editor, $controller, $set_controller, $request, $answer) {
        if (!$editor) {
            return 'no editor';
        }
        wp_set_current_user((int) $editor[0]);
        return [$answer($controller->get_items_permissions_check($request('/wp/v2/icons'))), $answer($set_controller->get_items_permissions_check($request('/wp/v2/icon-collections')))];
    })(),
]);
wp_set_current_user(1);
$say('rest schemas', [
    get_parent_class($controller), get_parent_class($set_controller),
    $mask($controller->get_item_schema()), $mask($controller->get_collection_params()),
    $mask($set_controller->get_item_schema()), $mask($set_controller->get_collection_params()),
]);
$say('rest icons', [
    $answer($controller->get_items($request('/wp/v2/icons', ['collection' => 'zz-set']))),
    $answer($controller->get_items($request('/wp/v2/icons', ['collection' => 'zz-set', 'search' => 'star']))),
    $answer($controller->get_items($request('/wp/v2/icons', ['collection' => 'zz-set', 'context' => 'embed']))),
    $answer($controller->get_items($request('/wp/v2/icons', ['collection' => 'zz-none']))),
    $answer($controller->get_item($request('/wp/v2/icons/zz-set/star', ['name' => 'zz-set/star']))),
    $answer($controller->get_item($request('/wp/v2/icons/zz-set/never', ['name' => 'zz-set/never']))),
    $answer($controller->get_icon('zz-set/square')),
    $answer($controller->get_icon('zz-set/never')),
    $answer($controller->prepare_item_for_response($icons->get_registered_icon('zz-set/star'), $request('/wp/v2/icons/zz-set/star', ['_fields' => 'name,label']))),
]);
$say('rest icon collections', [
    $answer($set_controller->get_items($request('/wp/v2/icon-collections'))),
    $answer($set_controller->get_item($request('/wp/v2/icon-collections/zz-set', ['slug' => 'zz-set']))),
    $answer($set_controller->get_item($request('/wp/v2/icon-collections/zz-none', ['slug' => 'zz-none']))),
    $answer($set_controller->get_icon_collection('zz-set')),
    $answer($set_controller->get_icon_collection('zz-none')),
    $answer($set_controller->prepare_item_for_response($collections->get_registered('zz-set'), $request('/wp/v2/icon-collections/zz-set', ['context' => 'embed']))),
]);
$routed = static function (string $route, array $query = []) use ($mask) {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    if (is_array($data) && array_is_list($data)) {
        $data = array_values(array_filter($data, static fn ($item) => str_starts_with((string) ($item['name'] ?? $item['slug'] ?? ''), 'zz-') || ($item['slug'] ?? '') === 'core'));
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($data), $mask($headers)];
};
$say('routed', [
    $routed('/wp/v2/icons', ['collection' => 'zz-set']),
    $routed('/wp/v2/icons/zz-set'),
    $routed('/wp/v2/icons/zz-set/star'),
    $routed('/wp/v2/icons/zz-set/never'),
    $routed('/wp/v2/icon-collections'),
    $routed('/wp/v2/icon-collections/zz-set'),
    (static function () use ($mask) {
        $routes = rest_get_server()->get_routes('wp/v2');
        $out = [];
        foreach ($routes as $route => $handlers) {
            if (str_contains($route, 'icon')) {
                $out[$route] = array_map(static fn ($handler) => [array_keys($handler['methods']), is_array($handler['callback']) ? [get_class($handler['callback'][0]), $handler['callback'][1]] : 'other', is_array($handler['permission_callback'] ?? null) ? $handler['permission_callback'][1] : null, array_map(static fn ($arg) => array_diff_key($arg, ['sanitize_callback' => 1, 'validate_callback' => 1]), $handler['args'])], $handlers);
            }
        }
        return $out;
    })(),
]);
wp_set_current_user(0);

$say('unregistered', [
    $heard(static fn () => wp_unregister_icon('zz-set/star')), $icons->is_registered('zz-set/star'),
    $heard(static fn () => wp_unregister_icon('zz-set/star')),
    $heard(static fn () => wp_unregister_icon_collection('zz-set')),
    $collections->is_registered('zz-set'), $icons->is_registered('zz-set/square'),
    $heard(static fn () => wp_unregister_icon_collection('zz-set')),
]);
foreach (array_keys($icons->get_registered_icons()) as $key) {
    $name = $icons->get_registered_icons()[$key]['name'] ?? '';
    if (str_starts_with((string) $name, 'zz-')) {
        $icons->unregister($name);
    }
}
foreach (['zz-extra', 'zz-desc', 'zz-onlylabel', 'zz_under'] as $slug) {
    if ($collections->is_registered($slug)) {
        $collections->unregister($slug);
    }
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
