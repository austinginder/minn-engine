<?php
/**
 * wp/v2/statuses as the reference answers it: the list and one status, in
 * the view and edit contexts, to an administrator and to a visitor, with a
 * plugin's own statuses beside core's (one private, one shown in REST, one
 * internal), the embed context, _fields on one status, an unknown status, and what
 * rest_prepare_status hands plugins. Same protocol as api-probe.php;
 * dispatched in process.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_post_status('zz_probe_review', ['label' => 'In review', 'public' => false, 'protected' => true, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => false]);
register_post_status('zz_probe_shown', ['label' => 'Shown', 'public' => true, 'show_in_rest' => true, 'date_floating' => true]);
register_post_status('zz_probe_internal', ['label' => 'Internal', 'internal' => true, 'show_in_rest' => true]);
$handed = [];
add_filter('rest_prepare_status', static function ($response, $status, $request) use (&$handed) {
    $handed[] = [$status->name, get_class($response), $request->get_param('context')];
    return $response;
}, 10, 3);
$send = static function (string $label, string $route, array $query = []) use ($say, &$handed): void {
    $handed = [];
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    if (!$response->is_error() && is_array($data)) {
        $links = static fn ($item) => is_array($item) && isset($item['_links']) ? ['_links' => array_map(static fn ($rel) => array_map(static fn ($l) => str_replace(rest_url(), '{rest}/', $l['href'] ?? ''), $rel), $item['_links'])] + $item : $item;
        $data = isset($data['name']) ? $links($data) : array_map($links, $data);
    }
    $say($label, ['status' => $response->get_status(), 'data' => $data, 'handed' => $handed]);
};
wp_set_current_user(0);
$send('visitor list', '/wp/v2/statuses');
$send('visitor one', '/wp/v2/statuses/publish');
$send('visitor private one', '/wp/v2/statuses/draft');
$send('visitor edit context', '/wp/v2/statuses', ['context' => 'edit']);
$send('visitor unknown', '/wp/v2/statuses/zz_nothing');
$send('visitor one edit context', '/wp/v2/statuses/publish', ['context' => 'edit']);
wp_set_current_user(1);
$send('admin list', '/wp/v2/statuses');
$send('admin edit context', '/wp/v2/statuses', ['context' => 'edit']);
$send('admin one', '/wp/v2/statuses/draft', ['context' => 'edit']);
$send('admin plugin status', '/wp/v2/statuses/zz_probe_review');
$send('admin internal status', '/wp/v2/statuses/zz_probe_internal');
$send('admin unknown', '/wp/v2/statuses/zz_nothing');
$send('admin embed context', '/wp/v2/statuses', ['context' => 'embed']);
$send('admin one fields', '/wp/v2/statuses/publish', ['_fields' => 'name']);
wp_set_current_user(0);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
