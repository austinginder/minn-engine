<?php
/**
 * Saving global styles without unfiltered_html (probe
 * global-styles-unfiltered): POST wp/v2/global-styles/{id} as an
 * administrator who may not post unfiltered HTML (as under
 * DISALLOW_UNFILTERED_HTML), with unsafe values in styles, custom CSS and
 * settings, then the same as one who may; and custom CSS that closes its
 * style tag, refused for everyone. The stored content and the
 * answer's styles and settings are compared; the post, and the revisions
 * the saves make, are put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$deny = static fn ($caps, $cap) => $cap === 'unfiltered_html' ? ['do_not_allow'] : $caps;
$id = (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
if ($id <= 1) {
    fwrite(STDERR, "no user global styles post\n");
    exit(1);
}
$saved = $wpdb->get_row($wpdb->prepare("SELECT post_content, post_title, post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A);
$lastRevision = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision'", $id));
register_shutdown_function(static function () use ($wpdb, $id, $saved, $lastRevision): void {
    $wpdb->update($wpdb->posts, $saved, ['ID' => $id]);
    foreach ($wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' AND ID > %d", $id, $lastRevision)) as $revision) {
        if ((int) $revision > 1) {
            $wpdb->delete($wpdb->postmeta, ['post_id' => (int) $revision]);
            $wpdb->delete($wpdb->posts, ['ID' => (int) $revision]);
        }
    }
    clean_post_cache($id);
});
$unsafe = [
    'styles' => [
        'color' => ['text' => 'red', 'background' => 'url(javascript:alert(1))'],
        'css' => 'body{color:red} a{background:url(javascript:alert(1))}',
        'typography' => ['fontFamily' => 'serif; } body { background: red'],
        'elements' => ['link' => ['color' => ['text' => 'expression(alert(1))']]],
        'blocks' => ['core/paragraph' => ['color' => ['text' => 'blue'], 'css' => '& p { color: green }']],
    ],
    'settings' => [
        'color' => ['palette' => [['slug' => 'zz', 'name' => 'ZZ <b>bold</b>', 'color' => '#123456']]],
        'custom' => ['zz-note' => '<script>alert(1)</script>'],
    ],
];
$save = static function (array $body) use ($id, $wpdb): array {
    $request = new WP_REST_Request('POST', "/wp/v2/global-styles/$id");
    foreach ($body as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    $data = $response->get_data();
    clean_post_cache($id);
    $stored = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id)), true);
    return ['status' => $response->get_status(), 'answered' => is_array($data) && $response->get_status() < 400 ? array_intersect_key($data, array_flip(['styles', 'settings'])) : $data, 'stored' => is_array($stored) ? array_intersect_key($stored, array_flip(['styles', 'settings'])) : $stored];
};

add_filter('map_meta_cap', $deny, 10, 2);
wp_set_current_user(0);
wp_set_current_user(1);
$say('custom CSS closing its style tag', $save(['styles' => ['css' => 'body{color:red}</style><script>alert(1)</script>']]));
$say('without unfiltered_html', ['may' => current_user_can('unfiltered_html'), 'saved' => $save($unsafe)]);
$say('safe settings, without unfiltered_html', $save(['styles' => ['color' => ['text' => 'navy']], 'settings' => ['color' => ['palette' => [['slug' => 'zz', 'name' => 'ZZ plain', 'color' => '#123456']]], 'custom' => ['zz-gap' => '2rem'], 'typography' => ['fontSizes' => [['slug' => 'zz', 'name' => 'ZZ', 'size' => '3rem']]]]]));
$say('one unsafe setting among safe ones, without unfiltered_html', $save(['styles' => ['color' => ['text' => 'navy']], 'settings' => ['color' => ['palette' => [['slug' => 'zz', 'name' => 'ZZ plain', 'color' => '#123456'], ['slug' => 'zz2', 'name' => 'ZZ2', 'color' => 'url(javascript:alert(1))']]], 'custom' => ['zz-gap' => '2rem', 'zz-bad' => '<b>x</b>']]]));
remove_filter('map_meta_cap', $deny, 10);
wp_set_current_user(0);
wp_set_current_user(1);
$say('with unfiltered_html', ['may' => current_user_can('unfiltered_html'), 'saved' => $save($unsafe)]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
