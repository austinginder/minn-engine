<?php
/**
 * When a post's edits become revisions and what plugins are asked on the
 * way: a first edit, an edit that changes nothing, a limit of two from
 * wp_revisions_to_keep with older revisions dropped, revisions switched off,
 * a plugin insisting a post has changed, and a plugin's post type with and
 * without revision support. Same protocol as api-probe.php; the posts are
 * its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
register_post_type('zz_revised', ['public' => true, 'supports' => ['title', 'editor', 'revisions']]);
register_post_type('zz_unrevised', ['public' => true, 'supports' => ['title', 'editor']]);
$watch = ['wp_revisions_to_keep', 'wp_post_revisions_to_keep', 'wp_zz_revised_revisions_to_keep', 'wp_zz_unrevised_revisions_to_keep', 'wp_save_post_revision_check_for_changes', '_wp_post_revision_fields', 'wp_save_post_revision_post_has_changed', 'wp_post_revision_meta_keys', 'wp_save_post_revision_revisions_before_deletion', '_wp_put_post_revision', 'wp_delete_post_revision', 'pre_delete_post', 'save_post_revision', 'wp_after_insert_post'];
$made = [];
$steps = [];
$recorder = static function (string $hook) use (&$steps, $watch, &$made): void {
    if (!in_array($hook, $watch, true)) {
        return;
    }
    $args = array_map(static function ($a) {
        if ($a instanceof WP_Post) {
            return 'post:' . $a->post_type;
        }
        if (is_array($a)) {
            $keys = array_keys($a);
            return is_int($keys[0] ?? null) ? 'list(' . count($a) . ')' : 'array[' . implode(',', $keys) . ']';
        }
        return is_object($a) ? 'object' : $a;
    }, array_slice(func_get_args(), 1));
    $steps[] = $hook . ' ' . json_encode($args);
};
$revisions = static fn (int $id): array => array_values(array_map(static fn (WP_Post $r) => $r->post_title, wp_get_post_revisions($id, ['order' => 'ASC'])));
$edit = static function (string $label, int $id, array $changes) use (&$steps, $recorder, $say, $revisions, &$made): void {
    $steps = [];
    add_action('all', $recorder);
    wp_update_post(['ID' => $id] + $changes);
    remove_action('all', $recorder);
    $mask = static function (string $s) use (&$made): string {
        foreach ($made as $n => $postId) {
            $s = (string) preg_replace('/\b' . $postId . '\b/', '{post' . $n . '}', $s);
        }
        return (string) preg_replace('/\b\d{4,}\b/', '{revision}', $s);
    };
    $say($label, ['steps' => array_map($mask, $steps), 'revisions' => $revisions($id)]);
};
$new = static function (string $type, string $title) use (&$made): int {
    $id = (int) wp_insert_post(['post_type' => $type, 'post_title' => $title, 'post_content' => 'Body', 'post_status' => 'publish']);
    $made[] = $id;
    return $id;
};

$post = $new('post', 'zz revision one');
$say('a new post has', $revisions($post));
$edit('a first edit', $post, ['post_title' => 'zz revision two']);
$edit('an edit that changes nothing', $post, ['post_title' => 'zz revision two']);
$limit = static fn () => 2;
add_filter('wp_revisions_to_keep', $limit);
$edit('a third edit, two kept', $post, ['post_title' => 'zz revision three']);
$edit('a fourth edit, two kept', $post, ['post_title' => 'zz revision four']);
remove_filter('wp_revisions_to_keep', $limit);
$off = static fn () => 0;
add_filter('wp_revisions_to_keep', $off);
$edit('revisions switched off', $post, ['post_title' => 'zz revision five']);
remove_filter('wp_revisions_to_keep', $off);
$changed = static fn () => true;
add_filter('wp_save_post_revision_post_has_changed', $changed);
$edit('a plugin says it changed', $post, ['post_title' => 'zz revision five']);
remove_filter('wp_save_post_revision_post_has_changed', $changed);
$edit('a type with revisions', $new('zz_revised', 'zz revised one'), ['post_title' => 'zz revised two']);
$edit('a type without', $new('zz_unrevised', 'zz unrevised one'), ['post_title' => 'zz unrevised two']);
$say('wp_revisions_to_keep by type', [wp_revisions_to_keep(get_post($made[0])), wp_revisions_to_keep(get_post($made[1])), wp_revisions_to_keep(get_post($made[2]))]);

foreach ($made as $id) {
    wp_delete_post($id, true);
}
unregister_post_type('zz_revised');
unregister_post_type('zz_unrevised');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
