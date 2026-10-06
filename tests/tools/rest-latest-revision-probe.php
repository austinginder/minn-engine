<?php
/**
 * Which revision a post's links name as its latest (probe
 * rest-latest-revision): predecessor-version and the version-history
 * count, with revisions and an autosave at dates and ids set apart, and
 * the same post's revisions route. Same protocol as api-probe.php;
 * dispatched in process as an administrator; what it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
wp_set_current_user(1);
$made = [];
// A revision row written as the store keeps one, at the date asked for.
$row = static function (int $parent, string $name, string $date) use ($wpdb, &$made): int {
    $wpdb->insert($wpdb->posts, [
        'post_author' => 1, 'post_date' => $date, 'post_date_gmt' => $date, 'post_content' => 'zz', 'post_title' => 'Zz Latest Revision',
        'post_excerpt' => '', 'post_status' => 'inherit', 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_name' => $name,
        'to_ping' => '', 'pinged' => '', 'post_modified' => $date, 'post_modified_gmt' => $date, 'post_content_filtered' => '',
        'post_parent' => $parent, 'guid' => '', 'menu_order' => 0, 'post_type' => 'revision', 'post_mime_type' => '', 'comment_count' => 0,
    ]);
    $made[] = (int) $wpdb->insert_id;
    return (int) $wpdb->insert_id;
};
$cases = [
    'revision newer, higher id' => [['autosave', '2026-01-02 00:00:00'], ['revision', '2026-01-03 00:00:00']],
    'autosave newer, lower id' => [['autosave', '2026-01-05 00:00:00'], ['revision', '2026-01-03 00:00:00']],
    'autosave newer, higher id' => [['revision', '2026-01-03 00:00:00'], ['autosave', '2026-01-05 00:00:00']],
    'same date, autosave higher id' => [['revision', '2026-01-03 00:00:00'], ['autosave', '2026-01-03 00:00:00']],
    'revisions only, older one higher id' => [['revision', '2026-01-04 00:00:00'], ['revision', '2026-01-03 00:00:00']],
    'autosave only' => [['autosave', '2026-01-03 00:00:00']],
];
foreach ($cases as $label => $rows) {
    $post = (int) wp_insert_post(['post_title' => 'Zz Latest Revision', 'post_status' => 'draft', 'post_content' => 'zz']);
    $made[] = $post;
    $names = [];
    foreach ($rows as $i => [$kind, $date]) {
        $id = $row($post, $kind === 'autosave' ? "{$post}-autosave-v1" : "{$post}-revision-v1", $date);
        $names[$id] = "{$kind} " . ($i + 1);
    }
    clean_post_cache($post);
    $request = new WP_REST_Request('GET', '/wp/v2/posts/' . $post);
    $links = rest_get_server()->response_to_data(rest_do_request($request), false)['_links'] ?? [];
    $latest = $links['predecessor-version'][0]['id'] ?? null;
    $request = new WP_REST_Request('GET', '/wp/v2/posts/' . $post . '/revisions');
    $listed = array_column((array) rest_get_server()->response_to_data(rest_do_request($request), false), 'id');
    $say($label, [
        'latest' => $latest === null ? null : ($names[$latest] ?? 'other'),
        'count' => $links['version-history'][0]['count'] ?? null,
        'revisions route' => array_map(static fn ($id) => $names[$id] ?? 'other', $listed),
    ]);
}
wp_set_current_user(0);
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
