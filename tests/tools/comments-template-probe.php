<?php
/**
 * Which comments comments_template loads for a post (probe
 * comments-template): not paged, and paged with the newest or the oldest
 * page first, a page asked for, a page past the end, threading off, the
 * newest first in the list, a signed-in commenter's held comment, and a
 * post behind a password. For each: what it leaves in the main query (the
 * comments in order, the count, the pages, the page shown, the comments by
 * type when asked, COMMENTS_TEMPLATE), the
 * arguments it hands comments_template_query_args and, finding the newest
 * page, comments_template_top_level_query_args, get_comment_pages_count
 * after it (and of all the comments, two a page), and what
 * wp_list_comments then prints (each comment's label, depth and link);
 * and the fallback template's answer for a post behind a password. The
 * probe's own post and comments, removed at the end; settings changed only
 * through pre_option filters. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$editor = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
$post = (int) wp_insert_post(['post_title' => 'Zz Comments Template', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'zz', 'post_name' => 'zz-comments-template', 'post_date' => '2021-01-01 10:00:00', 'comment_status' => 'open']);
$tpl = sys_get_temp_dir() . '/zz-comments-template-' . getmypid() . '.php';
file_put_contents($tpl, "<?php\n");
$ids = [];
register_shutdown_function(static function () use (&$ids, $post, $tpl): void {
    foreach ($ids as $id) {
        wp_delete_comment($id, true);
    }
    wp_delete_post($post, true);
    @unlink($tpl);
});
$add = static function (string $label, int $hour, array $fields = []) use ($post, &$ids): int {
    $date = sprintf('2021-01-02 %02d:00:00', $hour);
    $ids[$label] = (int) wp_insert_comment($fields + ['comment_post_ID' => $post, 'comment_author' => "Zz {$label}", 'comment_author_email' => 'zz-' . strtolower($label) . '@example.com', 'comment_content' => "Zz {$label}", 'comment_approved' => 1, 'comment_date' => $date, 'comment_date_gmt' => $date]);
    return $ids[$label];
};
foreach (['T1', 'T2', 'T3', 'T4', 'T5'] as $n => $label) {
    $add($label, $n + 1);
}
$add('R1', 6, ['comment_parent' => $ids['T1']]);
$add('R1a', 7, ['comment_parent' => $ids['R1']]);
$add('R3', 8, ['comment_parent' => $ids['T3']]);
$add('H', 9, ['comment_approved' => 0, 'user_id' => $editor]);
$add('X', 10, ['comment_approved' => 0]);
$names = array_flip($ids);
$mask = static function ($value) use (&$mask, $names, $post, $editor) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_string($value)) {
        $value = str_replace(home_url(), '{home}', $value);
        return (string) preg_replace_callback('/#comment-(\d+)/', static fn ($m) => '#comment-' . ($names[(int) $m[1]] ?? $m[1]), $value);
    }
    if (is_int($value) && $value === $post) {
        return '{post}';
    }
    return $value;
};
$options = static function (array $values): array {
    $hooks = [];
    foreach ($values as $name => $value) {
        $hooks[$name] = static fn () => $value;
        add_filter("pre_option_{$name}", $hooks[$name]);
    }
    return $hooks;
};
$heard = null;
$top = null;
add_filter('comments_template_query_args', static function ($args) use (&$heard) {
    $heard = $args;
    return $args;
});
add_filter('comments_template_top_level_query_args', static function ($args) use (&$top) {
    $top = $args;
    return $args;
});
$toEmpty = static fn () => $tpl;
add_filter('comments_template', $toEmpty);
$who = static function (?array $args) use ($mask, $editor): ?array {
    if ($args === null) {
        return null;
    }
    $args['post_id'] = $mask((int) $args['post_id']);
    if (isset($args['include_unapproved'])) {
        $args['include_unapproved'] = array_map(static fn ($id) => $id === $editor ? '{editor}' : $id, (array) $args['include_unapproved']);
    }
    return $args;
};
$case = static function (string $label, array $settings, array $vars = [], int $user = 0, bool $separate = false, string $password = '') use ($say, $options, $post, $names, $mask, &$heard, &$top, $who): void {
    $hooks = $options($settings + ['page_comments' => '0', 'comments_per_page' => '2', 'default_comments_page' => 'newest', 'thread_comments' => '1', 'thread_comments_depth' => '5', 'comment_order' => 'asc']);
    wp_set_current_user($user);
    unset($GLOBALS['overridden_cpage']);
    $heard = null;
    $top = null;
    query_posts(['p' => $post] + $vars);
    $GLOBALS['post'] = get_post($post);
    if ($password !== '') {
        $GLOBALS['post'] = clone $GLOBALS['post'];
        $GLOBALS['post']->post_password = $password;
    }
    comments_template('/comments.php', $separate);
    $query = $GLOBALS['wp_query'];
    $listed = wp_list_comments([
        'echo' => false,
        'callback' => static function ($comment, $args, $depth) use ($names, $mask) {
            echo ($names[(int) $comment->comment_ID] ?? '?') . '@' . $depth . ' ' . $mask(get_comment_link($comment, $args)) . "\n";
        },
        'end-callback' => static function () {
        },
    ]);
    $say($label, [
        'comments' => array_map(static fn ($c) => $names[(int) $c->comment_ID] ?? '?', (array) $query->comments),
        'comment_count' => $query->comment_count,
        'max_num_comment_pages' => $query->max_num_comment_pages,
        'cpage' => get_query_var('cpage'),
        'COMMENTS_TEMPLATE' => defined('COMMENTS_TEMPLATE') ? constant('COMMENTS_TEMPLATE') : null,
        'overridden_cpage' => $GLOBALS['overridden_cpage'] ?? null,
        'query args' => $who(is_array($heard) ? $heard : null),
        'top-level count args' => $who(is_array($top) ? $top : null),
        'by type' => array_map(static fn ($group) => array_map(static fn ($c) => $names[(int) $c->comment_ID] ?? '?', $group), (array) ($query->comments_by_type ?? [])),
        'get_comment_pages_count()' => get_comment_pages_count(),
        'get_comment_pages_count(all, 2)' => get_comment_pages_count(get_comments(['post_id' => $post, 'status' => 'approve']), 2),
        'listed' => explode("\n", trim((string) $listed)),
    ]);
    foreach ($hooks as $name => $hook) {
        remove_filter("pre_option_{$name}", $hook);
    }
    wp_set_current_user(0);
};

$case('not paged', []);
$case('paged, newest page first', ['page_comments' => '1']);
$case('paged, oldest page first', ['page_comments' => '1', 'default_comments_page' => 'oldest']);
$case('paged, page 2 asked', ['page_comments' => '1'], ['cpage' => 2]);
$case('paged, page 2 asked, oldest page first', ['page_comments' => '1', 'default_comments_page' => 'oldest'], ['cpage' => 2]);
$case('paged, a page past the end', ['page_comments' => '1'], ['cpage' => 9]);
$case('paged, threading off', ['page_comments' => '1', 'thread_comments' => '0']);
$case('paged, newest first in the list', ['page_comments' => '1', 'comment_order' => 'desc']);
$case('signed in, with a held comment', [], [], $editor);
$case('signed in, paged', ['page_comments' => '1'], [], $editor);
$case('separated by type', [], [], 0, true);
$case('password protected', [], [], 0, false, 'zz');
// The fallback template a theme without comments.php gets, on a post behind a password.
remove_filter('comments_template', $toEmpty);
add_filter('template_directory', static fn () => sys_get_temp_dir() . '/zz-no-theme');
add_filter('stylesheet_directory', static fn () => sys_get_temp_dir() . '/zz-no-theme');
query_posts(['p' => $post]);
$GLOBALS['post'] = clone get_post($post);
$GLOBALS['post']->post_password = 'zz';
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);
ob_start();
comments_template();
$say('the fallback template, password protected', str_replace(home_url(), '{home}', (string) ob_get_clean()));
restore_error_handler();
wp_reset_query();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
