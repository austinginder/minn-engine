<?php
/**
 * wp/v2/block-renderer as the reference answers it, the route the editor's
 * ServerSideRender asks: a plugin's dynamic block rendered from attributes
 * sent as query or body, its defaults filled in, inside a post when one is
 * named; who may ask; a block that does not exist, one that is not
 * dynamic, attributes of the wrong type; and a core dynamic block. Same
 * protocol as api-probe.php; dispatched in process.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_block_type('zz-probe/render', [
    'attributes' => ['count' => ['type' => 'integer', 'default' => 2], 'label' => ['type' => 'string', 'default' => 'items']],
    'render_callback' => static function ($attributes, $content, $block) {
        $post = get_post();
        return sprintf('<div class="zz-probe">%d %s|post:%s|content:%s|context:%s</div>', (int) $attributes['count'], esc_html((string) $attributes['label']), $post ? $post->post_title : 'none', $content === '' ? 'empty' : $content, json_encode($block->context ?? null));
    },
    'uses_context' => ['postId', 'postType'],
]);
$post = (int) wp_insert_post(['post_title' => 'zz probe render host', 'post_status' => 'draft', 'post_author' => 1]);
$mask = static fn ($value) => json_decode(str_replace((string) $post, '{post}', (string) json_encode($value)), true);
$send = static function (string $label, string $method, string $route, array $query = [], ?array $body = null) use ($say, $mask): void {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    $say($label, $mask(['status' => $response->get_status(), 'data' => rest_get_server()->response_to_data($response, false)]));
};
wp_set_current_user(0);
$send('visitor', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit']);
wp_set_current_user(1);
$send('defaults', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit']);
$send('query attributes', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit', 'attributes' => ['count' => '5', 'label' => '<b>things</b>']]);
$send('body attributes', 'POST', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit'], ['attributes' => ['count' => 7]]);
$send('in a post', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit', 'post_id' => $post]);
$send('wrong type', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit', 'attributes' => ['count' => 'many']]);
$send('unknown attribute', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit', 'attributes' => ['nope' => 1]]);
$send('unknown block', 'GET', '/wp/v2/block-renderer/zz-probe/nothing', ['context' => 'edit']);
$send('static block', 'GET', '/wp/v2/block-renderer/core/paragraph', ['context' => 'edit']);
$send('missing post', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit', 'post_id' => 999999]);
$send('view context', 'GET', '/wp/v2/block-renderer/zz-probe/render');
$send('core archives', 'GET', '/wp/v2/block-renderer/core/archives', ['context' => 'edit', 'attributes' => ['showPostCounts' => true]]);
wp_set_current_user(3);
$send('author', 'GET', '/wp/v2/block-renderer/zz-probe/render', ['context' => 'edit']);
wp_set_current_user(0);
wp_delete_post($post, true);
unregister_block_type('zz-probe/render');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
