<?php
/**
 * An author archive's flags and queried author, by each author variable
 * (one id, a list, one that names nobody, a name, a list alone in
 * author__in), beside the category and tag lists; and core/post-author-name
 * rendered with no post to read on those archives, with a post's context,
 * and off an archive. Same protocol as api-probe.php.
 */
$log = [];
$saved = [$GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, $GLOBALS['post'] ?? null];
$cases = [
    'author=1' => ['author' => 1], 'author=1,2' => ['author' => '1,2'], 'author=2,1' => ['author' => '2,1'], 'author=-1' => ['author' => '-1'],
    'author=-1,2' => ['author' => '-1,2'], 'author=999999' => ['author' => 999999], 'author=0' => ['author' => 0], 'author=abc' => ['author' => 'abc'],
    'author= 3' => ['author' => ' 3'], 'author_name=admin' => ['author_name' => 'admin'], 'author_name=nobody' => ['author_name' => 'zz-nobody'],
    'author__in' => ['author__in' => [1]], 'author__in two' => ['author__in' => [1, 2]], 'author__in and author' => ['author__in' => [2], 'author' => 1],
    'author__not_in' => ['author__not_in' => [1]], 'category__in' => ['category__in' => [1]], 'category__not_in' => ['category__not_in' => [1]],
    'tag__in' => ['tag__in' => [1]], 'tag_slug__and' => ['tag_slug__and' => ['a', 'b']],
];
foreach ($cases as $label => $vars) {
    $q = new WP_Query($vars + ['posts_per_page' => 1, 'no_found_rows' => true]);
    $o = $q->get_queried_object();
    $log[] = ["query: $label", [$q->is_author(), $q->is_category(), $q->is_tag(), $q->is_archive(), $q->is_home(), $q->get_queried_object_id(), is_object($o) ? get_class($o) . ':' . ($o->ID ?? $o->term_id ?? '') : $o, $q->get('author')]];
}
$author = (int) get_post_field('post_author', (int) $GLOBALS['wpdb']->get_var("SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_author > 1 ORDER BY ID LIMIT 1"));
$other = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_author = %d ORDER BY ID LIMIT 1", $author));
foreach (['author=1' => ['author' => 1], 'author=1,2' => ['author' => '1,2'], 'author=999999' => ['author' => 999999], 'author_name=admin' => ['author_name' => 'admin'], 'author__in' => ['author__in' => [1]], 'category' => ['cat' => 1], 'single post' => ['p' => 1]] as $label => $vars) {
    $GLOBALS['wp_query'] = new WP_Query($vars);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $GLOBALS['post'] = null;
    $log[] = ["post-author-name, no post: $label", [do_blocks('<!-- wp:post-author-name /-->'), do_blocks('<!-- wp:post-author-name {"isLink":true} /-->')]];
    $log[] = ["post-author-name, another author's post: $label", (new WP_Block(['blockName' => 'core/post-author-name', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['postId' => $other, 'postType' => 'post']))->render()];
}
[$GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['post']] = $saved;
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
