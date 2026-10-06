<?php
/**
 * batch/v1 as the reference answers it: several writes in one request,
 * each answered as {body, status, headers}, to a visitor and an
 * administrator; a route that does not take part in batches; a method a
 * batch does not allow; require-all-validate refusing the whole batch for
 * one bad request; more than the batch limit; and a plugin's route that
 * opts in. Same protocol as api-probe.php; dispatched in process; the
 * posts it makes go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_action('rest_api_init', static function () {
    register_rest_route('zz-probe/v1', '/echo', ['methods' => 'POST', 'callback' => static fn ($r) => ['echo' => $r['word'] ?? null], 'permission_callback' => '__return_true', 'allow_batch' => ['v1' => true], 'args' => ['word' => ['type' => 'string', 'required' => true]]]);
    register_rest_route('zz-probe/v1', '/solo', ['methods' => 'POST', 'callback' => static fn () => ['solo' => true], 'permission_callback' => '__return_true']);
});
$made = [];
$mask = static function ($value) use (&$mask, &$made) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_string($value)) {
        foreach ($made as $n => $id) {
            $value = (string) preg_replace('/\b' . $id . '\b/', '{post' . $n . '}', $value);
        }
        return str_replace([rest_url(), home_url()], ['{rest}/', '{home}'], $value);
    }
    if (is_int($value) && ($n = array_search($value, $made, true)) !== false) {
        return '{post' . $n . '}';
    }
    return $value;
};
$batch = static function (string $label, array $body) use ($say, $mask, &$made): void {
    $request = new WP_REST_Request('POST', '/batch/v1');
    $request->set_header('content-type', 'application/json');
    $request->set_body((string) json_encode($body));
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    foreach ((array) ($data['responses'] ?? []) as $one) {
        if (($one['status'] ?? 0) === 201 && isset($one['body']['id'])) {
            $made[] = (int) $one['body']['id'];
        }
    }
    $trim = static fn ($one) => is_array($one) ? ['status' => $one['status'] ?? null, 'headers' => $one['headers'] ?? null, 'body' => is_array($one['body'] ?? null) ? array_intersect_key($one['body'], array_flip(['id', 'title', 'status', 'code', 'message', 'data', 'echo', 'deleted'])) : ($one['body'] ?? null)] : $one;
    $say($label, $mask(['status' => $response->get_status(), 'failed' => $data['failed'] ?? null, 'responses' => array_map($trim, (array) ($data['responses'] ?? [])), 'error' => isset($data['code']) ? array_intersect_key($data, array_flip(['code', 'message', 'data'])) : null]));
};
wp_set_current_user(0);
$batch('visitor writes', ['requests' => [['path' => '/wp/v2/posts', 'body' => ['title' => 'zz probe batch one']]]]);
wp_set_current_user(1);
$batch('admin writes', ['requests' => [
    ['path' => '/wp/v2/posts', 'body' => ['title' => 'zz probe batch one', 'status' => 'draft']],
    ['path' => '/wp/v2/posts', 'body' => ['title' => 'zz probe batch two', 'status' => 'draft']],
    ['method' => 'PUT', 'path' => '/wp/v2/posts/999999', 'body' => ['title' => 'nope']],
]]);
$batch('admin update and delete', ['requests' => [
    ['method' => 'POST', 'path' => '/wp/v2/posts/' . ($made[0] ?? 0), 'body' => ['title' => 'zz probe batch renamed']],
    ['method' => 'DELETE', 'path' => '/wp/v2/posts/' . ($made[1] ?? 0) . '?force=true'],
]]);
$batch('not batchable', ['requests' => [['path' => '/wp/v2/settings', 'body' => ['title' => 'x']], ['path' => '/zz-probe/v1/solo']]]);
$batch('unknown route', ['requests' => [['path' => '/zz-probe/v1/nowhere']]]);
$batch('get method', ['requests' => [['method' => 'GET', 'path' => '/wp/v2/posts']]]);
$batch('plugin route', ['requests' => [['path' => '/zz-probe/v1/echo', 'body' => ['word' => 'hello']], ['path' => '/zz-probe/v1/echo', 'body' => []]]]);
$batch('require all validate', ['validation' => 'require-all-validate', 'requests' => [['path' => '/zz-probe/v1/echo', 'body' => ['word' => 'fine']], ['path' => '/zz-probe/v1/echo', 'body' => ['word' => ['not', 'a', 'string']]]]]);
$batch('too many', ['requests' => array_fill(0, 26, ['path' => '/zz-probe/v1/echo', 'body' => ['word' => 'x']])]);
$batch('no requests', []);
wp_set_current_user(0);
foreach ($made as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
