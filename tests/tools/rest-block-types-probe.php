<?php
/**
 * wp/v2/block-types as the reference answers it: who may read it, every
 * registered block type by name in the reference's order, a namespace's
 * share, full items for a spread of core blocks (static, dynamic, with
 * variations, styles and selectors) and for a block a plugin registers,
 * an unknown block, _fields, and a fingerprint of the whole list so no
 * item drifts unseen. Same protocol as api-probe.php; dispatched in
 * process; site addresses masked.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace([rest_url(), includes_url(), home_url()], ['{rest}/', '{includes}/', '{home}'], $value) : $value;
};
// Styles a theme registers on init are cleared (the engine's probe runtime stops before init; HTTP
// compares them), and one is registered here instead, beside a block's own.
foreach (WP_Block_Styles_Registry::get_instance()->get_all_registered() as $block => $styles) {
    foreach (array_keys($styles) as $style) {
        unregister_block_style($block, $style);
    }
}
register_block_style('core/image', ['name' => 'zz-probe-frame', 'label' => 'Framed', 'inline_style' => '.is-style-zz-probe-frame { border: 1px solid; }']);
register_block_type('zz-probe/thing', [
    'title' => 'Probe Thing',
    'description' => 'A block a plugin registers.',
    'category' => 'widgets',
    'icon' => 'star-filled',
    'keywords' => ['probe'],
    'attributes' => ['count' => ['type' => 'integer', 'default' => 3], 'label' => ['type' => 'string']],
    'supports' => ['align' => true, 'html' => false],
    'uses_context' => ['postId'],
    'render_callback' => static fn ($attributes) => '<p>' . (int) ($attributes['count'] ?? 0) . '</p>',
]);
$send = static function (string $route, array $query = []) use ($mask): array {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    return [$response->get_status(), $mask(rest_get_server()->response_to_data($response, false))];
};
wp_set_current_user(0);
$say('visitor list', $send('/wp/v2/block-types'));
wp_set_current_user(2);
[$status, $all] = $send('/wp/v2/block-types');
$say('editor list', [$status, is_array($all) ? array_column($all, 'name') : $all]);
$say('fingerprint', is_array($all) ? md5((string) json_encode($all)) : null);
wp_set_current_user(1);
$say('namespace route', ($r = $send('/wp/v2/block-types/core'))[0] === 200 ? [200, count($r[1]), array_slice(array_column($r[1], 'name'), 0, 5)] : $r);
$say('namespace param', ($r = $send('/wp/v2/block-types', ['namespace' => 'zz-probe']))[0] === 200 ? [200, array_column($r[1], 'name')] : $r);
foreach (['core/paragraph', 'core/archives', 'core/heading', 'core/group', 'core/image', 'core/navigation', 'core/post-title', 'zz-probe/thing'] as $name) {
    $say($name, $send('/wp/v2/block-types/' . $name));
}
$say('unknown', $send('/wp/v2/block-types/zz-probe/nothing'));
$say('fields', $send('/wp/v2/block-types/core/paragraph', ['_fields' => 'name,title,is_dynamic']));
$say('edit context', ($r = $send('/wp/v2/block-types/core/quote', ['context' => 'edit']))[0] === 200 ? [200, array_keys($r[1])] : $r);
wp_set_current_user(0);
unregister_block_type('zz-probe/thing');
unregister_block_style('core/image', 'zz-probe-frame');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
