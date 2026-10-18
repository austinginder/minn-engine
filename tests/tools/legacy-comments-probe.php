<?php
/**
 * The legacy comments blocks as the reference renders them (probe
 * legacy-comments): post-comments, which Twenty Twenty-Two's single template
 * still holds, and a comments block saved before it held inner blocks
 * (legacy: true), each in a single post's loop. Their wrapper and classes,
 * the comments inside (comments_template()), a post that takes no comments
 * and has none, a protected post, and the comment-reply script. Posts the
 * probe makes are deleted on the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$at = static function (int $id): void {
    $GLOBALS['wp_query'] = new WP_Query(['p' => $id, 'post_type' => 'any']);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $GLOBALS['wp_query']->the_post();
};
// The rendered markup, whitespace folded and the comment's avatar and date masked.
$tidy = static fn (string $html): string => trim((string) preg_replace(['/\s+/', '#https?://[^/"\'\s]+#', '/avatar\/[0-9a-f]+/', '/\d{4}-\d{2}-\d{2}T[\d:+-]+/', '/>\s*[A-Z][a-z]+ \d{1,2}, \d{4}[^<]*</'], [' ', 'HOST', 'avatar/H', 'DATE', '>DATE<'], $html));
$open = static function (string $html): string {
    preg_match('/^<div[^>]*>/', $html, $m);
    return $m[0] ?? '';
};

$at(1);
$say('post-comments', $tidy(do_blocks('<!-- wp:post-comments /-->')));
$say('legacy comments', $tidy(do_blocks('<!-- wp:comments {"legacy":true} /-->')));
$say('comments without inner blocks', do_blocks('<!-- wp:comments /-->'));
$say('wrappers', [
    'post-comments with supports' => $open(do_blocks('<!-- wp:post-comments {"textAlign":"center","fontSize":"large","backgroundColor":"black","className":"extra"} /-->')),
    'post-comments aligned' => $open(do_blocks('<!-- wp:post-comments {"align":"wide","textColor":"white"} /-->')),
    'legacy comments with supports' => $open(do_blocks('<!-- wp:comments {"legacy":true,"align":"wide","className":"extra","textColor":"white"} /-->')),
    'legacy comments, another tag' => $open(do_blocks('<!-- wp:comments {"legacy":true,"tagName":"section"} /-->')),
]);
$say('comment-reply queued', wp_script_is('comment-reply', 'enqueued'));
// Without threaded comments.
$threaded = get_option('thread_comments');
update_option('thread_comments', '0');
wp_dequeue_script('comment-reply');
do_blocks('<!-- wp:post-comments /-->');
$say('comment-reply queued, comments not threaded', wp_script_is('comment-reply', 'enqueued'));
update_option('thread_comments', $threaded);
wp_dequeue_script('comment-reply');

$closed = wp_insert_post(['post_title' => 'Minn probe closed', 'post_status' => 'publish', 'comment_status' => 'closed'], true);
$locked = wp_insert_post(['post_title' => 'Minn probe locked', 'post_status' => 'publish', 'post_password' => 'secret'], true);
foreach ([$closed, $locked] as $id) {
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "could not make the probe posts\n");
        exit(1);
    }
    $made[] = $id;
}
$at($closed);
$say('closed, no comments', [do_blocks('<!-- wp:post-comments /-->'), do_blocks('<!-- wp:comments {"legacy":true} /-->')]);
$at($locked);
$say('protected', $tidy(do_blocks('<!-- wp:post-comments /-->')));
wp_reset_postdata();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
