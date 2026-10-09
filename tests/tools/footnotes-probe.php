<?php
/**
 * Footnotes meta (probe footnotes): the footnotes JSON kept to ids and
 * kses'd content for users without unfiltered_html (and on import), the
 * filter added and removed as the user changes, and a revision's footnotes
 * read back. The probe's post and revision are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$filtered = static fn () => has_filter('sanitize_post_meta_footnotes', '_wp_filter_post_meta_footnotes');

$say('the hooks', [
    has_action('init', '_wp_footnotes_kses_init'),
    has_action('set_current_user', '_wp_footnotes_kses_init'),
    has_filter('force_filtered_html_on_import', '_wp_footnotes_force_filtered_html_on_import_filter'),
    has_filter('_wp_post_revision_field_footnotes', 'wp_get_footnotes_from_revision'),
]);

$say('footnotes kept safe', array_map(static fn ($meta) => _wp_filter_post_meta_footnotes($meta), [
    wp_json_encode([['id' => 'a1', 'content' => 'Note <script>x()</script><strong>b</strong> <a href="https://zz.example" onclick="y">l</a>'], ['id' => 'a2', 'content' => '<em>two</em>']]),
    'not json',
    '',
    wp_json_encode(['x' => 1]),
    wp_json_encode([['content' => 'no id']]),
    wp_json_encode([['id' => 'A B!-c_d', 'content' => 'id sanitized?']]),
    wp_json_encode([['id' => 'e1', 'content' => 'extra', 'extra' => 'key', 'order' => 2]]),
    wp_json_encode([['id' => 'q1', 'content' => 'He said "hi" & it\'s \\ back \\\\ slash é ✓']]),
    wp_json_encode([['id' => 'z1', 'content' => ''], ['id' => '', 'content' => 'empty id'], ['id' => 0, 'content' => 'zero id'], ['id' => 7, 'content' => 'int id']]),
    wp_json_encode([['id' => 'l1', 'content' => "Line\nbreak <p>para</p> <img src=x onerror=y>"]]),
    wp_json_encode('just a string'),
    wp_json_encode([[]]),
]));

$say('the filter follows the user', (static function () use ($filtered) {
    $out = [];
    wp_set_current_user(0);
    _wp_footnotes_remove_filters();
    $out[] = [$filtered()];
    $out[] = [_wp_footnotes_kses_init_filters(), $filtered()];
    $out[] = [_wp_footnotes_remove_filters(), $filtered()];
    $out[] = [_wp_footnotes_kses_init(), $filtered()];
    wp_set_current_user(1);
    $out[] = [$filtered()];
    $out[] = [_wp_footnotes_kses_init(), $filtered()];
    $out[] = [_wp_footnotes_force_filtered_html_on_import_filter('zz-arg'), $filtered()];
    _wp_footnotes_remove_filters();
    $out[] = [_wp_footnotes_force_filtered_html_on_import_filter(false), $filtered()];
    $out[] = [_wp_footnotes_force_filtered_html_on_import_filter(''), $filtered()];
    wp_set_current_user(0);
    $out[] = [$filtered()];
    return $out;
})());

$made[] = $post = (int) wp_insert_post(['post_title' => 'Zz Footnotes', 'post_status' => 'draft', 'post_content' => 'x']);
$dirty = wp_json_encode([['id' => 'f1', 'content' => 'Kept <script>gone()</script><em>em</em>']]);
$say('stored as the user may', [
    (static function () use ($post, $dirty) {
        wp_set_current_user(0);
        update_post_meta($post, 'footnotes', wp_slash($dirty));
        return get_post_meta($post, 'footnotes', true);
    })(),
    (static function () use ($post, $dirty) {
        wp_set_current_user(1);
        update_post_meta($post, 'footnotes', wp_slash($dirty));
        return get_post_meta($post, 'footnotes', true);
    })(),
]);

$made[] = $revision = (int) wp_insert_post(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post, 'post_name' => "{$post}-revision-v1", 'post_title' => 'Zz Footnotes', 'post_content' => 'x']);
add_metadata('post', $revision, 'footnotes', wp_slash(wp_json_encode([['id' => 'r1', 'content' => 'From the revision']])));
$say('a revision\'s footnotes', [
    wp_get_footnotes_from_revision('ignored', 'footnotes', get_post($revision)),
    wp_get_footnotes_from_revision('ignored', 'zz_missing', get_post($revision)),
    apply_filters('_wp_post_revision_field_footnotes', 'ignored', 'footnotes', get_post($revision), 'from'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
