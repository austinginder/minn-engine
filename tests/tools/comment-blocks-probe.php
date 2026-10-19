<?php
/**
 * The comment blocks through the comment template tags (probe
 * comment-blocks): an author name through get_comment_author and
 * get_comment_author_url (linked unless isLink is off), a comment's text
 * through get_comment_text and comment_text with its defaults, a date
 * through get_comment_date and get_comment_link, a reply link through
 * get_comment_reply_link (the post's link as it is; none when the site does
 * not thread), an avatar through get_avatar, each item's classes through
 * get_comment_class (odd and even running on across the request), whether
 * comments are open asked; what a plugin's filters make of them; replies in
 * a plain list inside their parent. Same protocol as api-probe.php; the
 * probe's post and comments are removed and thread_comments put back.
 */
$out = [];
$made = [];
$threaded = get_option('thread_comments');
register_shutdown_function(static function () use (&$made, $threaded) {
    update_option('thread_comments', $threaded);
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$post = wp_insert_post(['post_type' => 'post', 'post_status' => 'private', 'post_title' => 'Zz comment filters', 'post_content' => 'x', 'comment_status' => 'open']);
if (!is_int($post) || $post <= 1) { fwrite(STDERR, "comment-blocks: could not make the post\n"); exit(1); }
$made[] = $post;
wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => "zz text with a https://example.com link\n\nand a second paragraph", 'comment_author' => 'Zz Author', 'comment_author_url' => 'https://example.com/zz', 'comment_author_email' => 'zz@example.com', 'comment_approved' => 1, 'comment_date' => '2026-01-01 10:00:00', 'comment_date_gmt' => '2026-01-01 10:00:00']);
$filters = ['get_comment_author', 'get_comment_author_url', 'get_comment_author_link', 'comment_author', 'get_comment_text', 'comment_text', 'get_comment_date', 'get_comment_time', 'comment_class', 'comments_open', 'comment_reply_link', 'comment_reply_link_args', 'get_comment_link', 'get_edit_comment_link', 'edit_comment_link', 'pre_get_avatar', 'get_avatar_url', 'get_avatar', 'get_comment_excerpt', 'get_comment_ID', 'get_comment_type', 'comment_excerpt', 'pre_comment_content', 'get_comment_author_IP', 'get_comment_author_email'];
$heard = [];
$listeners = [];
foreach ($filters as $f) {
    $listeners[$f] = static function ($value, ...$rest) use ($f, &$heard) { $heard[] = $f; return $value; };
    add_filter($f, $listeners[$f], 10, 6);
}
$blocks = [
    'author name' => '<!-- wp:comment-author-name /-->',
    'author name, not linked' => '<!-- wp:comment-author-name {"isLink":false} /-->',
    'content' => '<!-- wp:comment-content /-->',
    'date' => '<!-- wp:comment-date /-->',
    'date, linked' => '<!-- wp:comment-date {"isLink":true} /-->',
    'reply link' => '<!-- wp:comment-reply-link /-->',
    'edit link' => '<!-- wp:comment-edit-link /-->',
    'avatar' => '<!-- wp:avatar /-->',
];
$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);
foreach ($blocks as $label => $inner) {
    $heard = [];
    $html = do_blocks('<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comment-template -->' . $inner . '<!-- /wp:comment-template --></div><!-- /wp:comments -->');
    preg_match('#<li[^>]*>(.*)</li>#s', $html, $m);
    $out[$label] = ['heard' => array_values(array_unique($heard)), 'html' => preg_replace(['/comment-\d+/', '/\?p=\d+/', '/replytocom=\d+/', '/data-commentid="\d+"/', '/data-postid="\d+"/'], ['comment-N', '?p=N', 'replytocom=N', 'data-commentid="N"', 'data-postid="N"'], $m[0] ?? $html)];
}
foreach ($listeners as $f => $l) remove_filter($f, $l, 10);
$mark = static fn ($value) => is_string($value) ? $value . ' [zz]' : $value;
foreach (['get_comment_author', 'comment_text', 'get_comment_date', 'comment_reply_link', 'get_comment_author_url'] as $f) add_filter($f, $mark);
add_filter('comment_class', static fn ($classes) => [...$classes, 'zz-class']);
$html = do_blocks('<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- wp:comment-date /--><!-- wp:comment-reply-link /--><!-- /wp:comment-template --></div><!-- /wp:comments -->');
preg_match('#<ol.*</ol>#s', $html, $m);
$out['filtered'] = preg_replace(['/comment-\d+/', '/\?p=\d+/', '/replytocom=\d+/', '/data-commentid="\d+"/', '/data-postid="\d+"/'], ['comment-N', '?p=N', 'replytocom=N', 'data-commentid="N"', 'data-postid="N"'], $m[0] ?? $html);
remove_all_filters('get_comment_author'); remove_all_filters('comment_class'); remove_filter('comment_text', $mark); remove_filter('get_comment_date', $mark); remove_filter('comment_reply_link', $mark); remove_filter('get_comment_author_url', $mark);
$first = (int) get_comments(['post_id' => $post, 'number' => 1, 'orderby' => 'comment_ID', 'order' => 'ASC', 'fields' => 'ids'])[0];
wp_insert_comment(['comment_post_ID' => $post, 'comment_parent' => $first, 'comment_content' => 'zz reply', 'comment_author' => 'Zz & <b>Bold</b>', 'comment_approved' => 1, 'comment_date' => '2026-01-02 10:00:00']);
$tree = '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-reply-link /--><!-- /wp:comment-template --></div><!-- /wp:comments -->';
$mask = static fn ($h) => preg_replace(['/comment-\d+/', '/\?p=\d+/', '/replytocom=\d+/', '/data-commentid="\d+"/', '/data-postid="\d+"/'], ['comment-N', '?p=N', 'replytocom=N', 'data-commentid="N"', 'data-postid="N"'], $h);
$out['threaded'] = $mask(do_blocks($tree));
update_option('thread_comments', '0');
$out['not threaded'] = $mask(do_blocks($tree));
update_option('thread_comments', $threaded);
wp_reset_postdata();
echo json_encode(array_map(null, array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
