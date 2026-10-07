<?php
/**
 * Where a comment's link points once comments are paged, as the reference
 * works it out (probe comment-pages): get_page_of_comment for top-level
 * comments and replies (threaded or not, newest or oldest page first),
 * with the arguments its filters are handed; and get_comment_link for
 * each, with pretty and plain links, an explicit page, and comments not
 * paged at all. The probe's own post and comments, removed at the end;
 * the discussion settings changed only through pre_option filters. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$post = (int) wp_insert_post(['post_title' => 'Zz Comment Pages', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'zz', 'post_name' => 'zz-comment-pages']);
$ids = [];
foreach (range(1, 5) as $n) {
    $ids["c{$n}"] = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => "Zz {$n}", 'comment_content' => "Zz comment {$n}", 'comment_approved' => 1, 'comment_date' => "2026-09-02 10:0{$n}:00", 'comment_date_gmt' => "2026-09-02 10:0{$n}:00"]);
}
$ids['r1'] = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_parent' => $ids['c1'], 'comment_author' => 'Zz reply', 'comment_content' => 'Zz reply to 1', 'comment_approved' => 1, 'comment_date' => '2026-09-02 11:00:00', 'comment_date_gmt' => '2026-09-02 11:00:00']);
$ids['r2'] = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_parent' => $ids['r1'], 'comment_author' => 'Zz reply 2', 'comment_content' => 'Zz reply to the reply', 'comment_approved' => 1, 'comment_date' => '2026-09-02 11:01:00', 'comment_date_gmt' => '2026-09-02 11:01:00']);
$names = array_flip($ids);
$mask = static fn ($value) => is_string($value) ? (string) preg_replace(['/\?p=' . $post . '\b/'], ['?p={post}'], (string) preg_replace_callback('/#comment-(\d+)/', static fn ($m) => '#comment-' . ($names[(int) $m[1]] ?? $m[1]), $value)) : $value;
$heard = [];
add_filter('get_page_of_comment', static function ($page, $args, $original, $id) use (&$heard, $names) {
    $heard[] = ['get_page_of_comment', $page, $args, $original, $names[(int) $id] ?? $id];
    return $page;
}, 10, 4);
add_filter('get_comment_link', static function ($link, $comment, $args, $cpage) use (&$heard, $mask) {
    $heard[] = ['get_comment_link', $mask($link), $args, $cpage];
    return $link;
}, 10, 4);
$options = static function (array $values): array {
    $hooks = [];
    foreach ($values as $name => $value) {
        $hooks[$name] = static fn () => $value;
        add_filter("pre_option_{$name}", $hooks[$name]);
    }
    return $hooks;
};
$unset = static function (array $hooks): void {
    foreach ($hooks as $name => $hook) {
        remove_filter("pre_option_{$name}", $hook);
    }
};
$walk = static function (string $label) use ($say, $ids, &$heard, $mask): void {
    $out = [];
    foreach ($ids as $name => $id) {
        $heard = [];
        $out[$name] = [get_page_of_comment($id), $mask(get_comment_link($id)), $heard];
    }
    $say($label, $out);
};
$walk('comments not paged');
$hooks = $options(['page_comments' => '1', 'comments_per_page' => '2', 'default_comments_page' => 'newest', 'thread_comments' => '1', 'thread_comments_depth' => '5']);
$walk('paged two at a time, threaded, newest page first');
$unset($hooks);
$hooks = $options(['page_comments' => '1', 'comments_per_page' => '2', 'default_comments_page' => 'oldest', 'thread_comments' => '0']);
$walk('paged two at a time, flat, oldest page first');
$unset($hooks);
$hooks = $options(['page_comments' => '1', 'comments_per_page' => '2', 'default_comments_page' => 'oldest', 'thread_comments' => '1', 'thread_comments_depth' => '5', 'permalink_structure' => '']);
$walk('paged, threaded, oldest first, plain links');
$heard = [];
$say('an explicit page and per-page', [get_page_of_comment($ids['c5'], ['per_page' => 1]), $mask(get_comment_link($ids['c5'], ['page' => 3])), $mask(get_comment_link($ids['c5'], ['cpage' => 2])), $mask(get_comment_link($ids['c5'], ['per_page' => 1, 'max_depth' => 1])), $heard]);
$unset($hooks);
$say('a comment that is not there', [get_page_of_comment(999999999), get_comment_link(999999999)]);

foreach (array_reverse($ids) as $id) {
    wp_delete_comment($id, true);
}
wp_delete_post($post, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
