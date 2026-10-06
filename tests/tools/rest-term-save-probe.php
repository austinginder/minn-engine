<?php
/**
 * What a REST term save hands plugins (rest_pre_insert_{taxonomy}, then
 * pre_insert_term or wp_update_term's changes) and what it answers: a new
 * category with markup in its name and a parent, a name already taken, a
 * missing parent, and an edit. Same protocol as api-probe.php; dispatched in
 * process, as an administrator; the terms are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
foreach (get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'search' => 'zz rest term']) as $old) {
    wp_delete_term($old->term_id, 'category');
}
$made = [];
$mask = static function ($value) use (&$made, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    foreach ($made as $n => $id) {
        if ($value === $id || $value === (string) $id) {
            return '{term' . $n . '}';
        }
        if (is_string($value)) {
            $value = (string) preg_replace('/\b' . $id . '\b/', '{term' . $n . '}', $value);
        }
    }
    return $value;
};
$seen = [];
add_filter('rest_pre_insert_category', static function ($prepared, $request) use (&$seen) {
    $seen[] = ['rest_pre_insert_category', get_object_vars($prepared), get_class($request)];
    return $prepared;
}, 10, 2);
add_filter('pre_insert_term', static function ($term, $taxonomy, $args) use (&$seen) {
    $seen[] = ['pre_insert_term', $term, $taxonomy, $args];
    return $term;
}, 10, 3);
add_action('edit_terms', static function ($id, $taxonomy, $args) use (&$seen) {
    $seen[] = ['edit_terms args', array_intersect_key((array) $args, array_flip(['name', 'description', 'slug', 'parent']))];
}, 10, 3);
$send = static function (string $label, string $method, string $route, array $body) use (&$seen, $say, $mask, &$made): ?array {
    $seen = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_body_params($body);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    if ($response->get_status() === 201 && isset($data['id'])) {
        $made[] = (int) $data['id'];
    }
    $answer = $response->is_error() ? ['code' => $data['code'] ?? null, 'message' => $data['message'] ?? null, 'data' => $data['data'] ?? null, 'additional' => $data['additional_data'] ?? null] : array_intersect_key($data, array_flip(['name', 'slug', 'description', 'parent']));
    $say($label, $mask(['status' => $response->get_status(), 'answer' => $answer, 'handed' => $seen]));
    return is_array($data) ? $data : null;
};
$parent = $send('a parent', 'POST', '/wp/v2/categories', ['name' => 'zz rest term parent']);
$send('a child with markup', 'POST', '/wp/v2/categories', ['name' => 'zz rest term <b>kid</b> & co', 'description' => 'Says <script>x</script> <em>hi</em>', 'parent' => (int) ($parent['id'] ?? 0), 'slug' => 'Zz Rest Kid']);
$send('a name already taken', 'POST', '/wp/v2/categories', ['name' => 'zz rest term parent']);
$send('a missing parent', 'POST', '/wp/v2/categories', ['name' => 'zz rest term orphan', 'parent' => 999999]);
$send('an edit', 'POST', '/wp/v2/categories/' . (int) ($parent['id'] ?? 0), ['name' => 'zz rest term parent <i>renamed</i>', 'description' => 'New']);
$send('an edit to a missing parent', 'POST', '/wp/v2/categories/' . (int) ($parent['id'] ?? 0), ['parent' => 999999]);
foreach (array_reverse($made) as $id) {
    wp_delete_term($id, 'category');
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
