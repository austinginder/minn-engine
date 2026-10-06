<?php
/**
 * The widget routes as the reference answers them (wp/v2/sidebars,
 * widget-types, widgets): who may read them, a sidebar the probe registers
 * beside the inactive widgets, the widget types with one up close, widgets
 * in the view and edit contexts, a legacy widget and a block widget
 * created, updated, moved and deleted, a sidebar reordered, and a widget
 * type's encode (its render, a whole preview page, is not compared). Instance hashes use the site's salts and are
 * masked; the widget ids it makes are masked. Same protocol as
 * api-probe.php; dispatched in process; the widget options are put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$kept = [];
foreach (['sidebars_widgets', 'widget_block', 'widget_search', 'widget_text', 'widget_archives'] as $option) {
    $kept[$option] = get_option($option, null);
}
register_sidebar(['id' => 'zz-probe-side', 'name' => 'Probe Side', 'description' => 'A probe sidebar.', 'before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>', 'before_title' => '<h2 class="widget-title">', 'after_title' => '</h2>']);
$made = [];
$mask = static function ($value) use (&$mask, &$made) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $key === 'hash' ? '{hash}' : $mask($item);
        }
        return $out;
    }
    if (is_string($value)) {
        foreach ($made as $n => $id) {
            $value = str_replace($id, '{widget' . $n . '}', $value);
        }
        return (string) preg_replace('/"_wpnonce" value="[0-9a-f]+"/', '"_wpnonce" value="{nonce}"', $value);
    }
    return $value;
};
$send = static function (string $label, string $method, string $route, array $query = [], ?array $body = null) use ($say, $mask, &$made): ?array {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    if ($response->get_status() === 201 && isset($data['id'])) {
        $made[] = (string) $data['id'];
    }
    $say($label, $mask(['status' => $response->get_status(), 'data' => $data]));
    return is_array($data) ? $data : null;
};
wp_set_current_user(0);
$send('visitor sidebars', 'GET', '/wp/v2/sidebars');
$send('visitor widgets', 'GET', '/wp/v2/widgets');
wp_set_current_user(2);
$send('editor widgets', 'GET', '/wp/v2/widgets');
wp_set_current_user(1);
$send('sidebars', 'GET', '/wp/v2/sidebars');
$send('one sidebar', 'GET', '/wp/v2/sidebars/zz-probe-side');
$send('unknown sidebar', 'GET', '/wp/v2/sidebars/zz-nope');
$send('widget types', 'GET', '/wp/v2/widget-types', ['_fields' => 'id,name,is_multi,classname']);
$send('one widget type', 'GET', '/wp/v2/widget-types/search');
$send('unknown widget type', 'GET', '/wp/v2/widget-types/zz-nope');
$send('create legacy', 'POST', '/wp/v2/widgets', [], ['id_base' => 'search', 'sidebar' => 'zz-probe-side', 'instance' => ['raw' => ['title' => 'Find things']]]);
$send('create block', 'POST', '/wp/v2/widgets', [], ['id_base' => 'block', 'sidebar' => 'zz-probe-side', 'instance' => ['raw' => ['content' => '<!-- wp:paragraph --><p>Probe words</p><!-- /wp:paragraph -->']]]);
$send('widgets in sidebar', 'GET', '/wp/v2/widgets', ['sidebar' => 'zz-probe-side', 'context' => 'edit']);
$send('one widget', 'GET', '/wp/v2/widgets/' . ($made[1] ?? 'none'));
$send('update legacy', 'PUT', '/wp/v2/widgets/' . ($made[0] ?? 'none'), [], ['instance' => ['raw' => ['title' => 'Find more']]]);
$send('move to inactive', 'PUT', '/wp/v2/widgets/' . ($made[1] ?? 'none'), [], ['sidebar' => 'wp_inactive_widgets']);
$send('reorder sidebar', 'PUT', '/wp/v2/sidebars/zz-probe-side', [], ['widgets' => array_values(array_filter([$made[1] ?? null, $made[0] ?? null]))]);
$send('encode', 'POST', '/wp/v2/widget-types/search/encode', [], ['instance' => [], 'form_data' => 'widget-search[0][title]=Encoded']);
$send('delete', 'DELETE', '/wp/v2/widgets/' . ($made[0] ?? 'none'), ['force' => 'true']);
$send('trash', 'DELETE', '/wp/v2/widgets/' . ($made[1] ?? 'none'));
wp_set_current_user(0);
foreach ($kept as $option => $value) {
    $value === null ? delete_option($option) : update_option($option, $value);
}
unregister_sidebar('zz-probe-side');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
