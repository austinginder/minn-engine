<?php
/**
 * map_meta_cap for edit_post, delete_post and read_post over posts of every
 * status by two authors (an administrator and an author), the trashed ones
 * by what they were trashed from, and pages: what each user is asked for.
 * Same protocol as api-probe.php; the posts are its own and go at the end.
 */
$log = [];
$made = [];
$make = static function (int $author, string $status, string $type = 'post') use (&$made): int {
    $date = $status === 'future' ? gmdate('Y-m-d H:i:s', time() + 86400 * 30) : '';
    $id = wp_insert_post(['post_title' => "zz caps {$type} {$status} {$author}", 'post_status' => $status === 'trash-live' || $status === 'trash-draft' ? ($status === 'trash-live' ? 'publish' : 'draft') : $status, 'post_type' => $type, 'post_author' => $author, 'post_date' => $date, 'post_date_gmt' => $date]);
    if (str_starts_with($status, 'trash')) {
        wp_trash_post($id);
    }
    $made[] = $id;
    return $id;
};
wp_set_current_user(1);
$posts = [];
foreach ([1, 3] as $author) {
    foreach (['publish', 'future', 'draft', 'pending', 'private', 'trash-live', 'trash-draft'] as $status) {
        $posts["post {$status} by {$author}"] = $make($author, $status);
    }
    $posts["page private by {$author}"] = $make($author, 'private', 'page');
}
foreach ($posts as $label => $id) {
    foreach ([1, 2, 3] as $user) {
        foreach (['edit_post', 'delete_post', 'read_post'] as $cap) {
            $log[] = ["{$cap} {$label} as user {$user}", map_meta_cap($cap, $user, $id)];
        }
    }
}
foreach ($made as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
