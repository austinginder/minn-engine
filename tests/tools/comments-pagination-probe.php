<?php
/**
 * The comment pagination family (probe comments-pagination): a post with
 * three comments, one a page, its comments block (the template, then the
 * pagination) rendered with the post as the main query on each page; the
 * family's callbacks called by name in place of the blocks' own. With
 * arrows, without labels, labels of their own, oldest first, and paging
 * off. The post and comments are removed and the options put back
 * (autoload included) at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
global $wpdb;
$options = [];
foreach (['page_comments', 'comments_per_page', 'default_comments_page'] as $name) {
    $options[$name] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
}
$restore = static function () use ($options): void {
    global $wpdb;
    foreach ($options as $name => $row) {
        if (!is_array($row)) {
            delete_option($name);
            continue;
        }
        update_option($name, $row['option_value']);
        $wpdb->update($wpdb->options, ['autoload' => $row['autoload']], ['option_name' => $name]);
    }
    wp_cache_delete('alloptions', 'options');
};
$made = ['post' => 0, 'comments' => []];
register_shutdown_function(static function () use (&$made, $restore): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_comment($id, true);
        }
    }
    if (is_int($made['post']) && $made['post'] > 1) {
        wp_delete_post($made['post'], true);
    }
    $restore();
});
wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ cpp post', 'post_name' => 'zz-cpp-post', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz']);
wp_set_current_user(0);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['post'] = $post;
foreach ([1, 2, 3] as $i) {
    $comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => "ZZ cpp {$i}", 'comment_author' => 'ZZ cpp', 'comment_approved' => 1, 'comment_date' => "2026-0{$i}-01 10:00:00", 'comment_date_gmt' => "2026-0{$i}-01 10:00:00"]);
    if (!is_int($comment) || $comment <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['comments'][] = $comment;
}
// As the settings screen stores them: strings.
update_option('page_comments', '1');
update_option('comments_per_page', '1');

$host = (string) parse_url(home_url(), PHP_URL_HOST);
$callbacks = [
    'core/comments-pagination' => static fn ($a, $c, $b) => render_block_core_comments_pagination($a, $c, $b),
    'core/comments-pagination-previous' => static fn ($a, $c, $b) => render_block_core_comments_pagination_previous($a, $c, $b),
    'core/comments-pagination-numbers' => static fn ($a, $c, $b) => render_block_core_comments_pagination_numbers($a, $c, $b),
    'core/comments-pagination-next' => static fn ($a, $c, $b) => render_block_core_comments_pagination_next($a, $c, $b),
];
// The post's comments on one page, the pagination's markup alone.
$page = static function (int $cpage, string $attrs = '', string $inner = '<!-- wp:comments-pagination-previous /--><!-- wp:comments-pagination-numbers /--><!-- wp:comments-pagination-next /-->') use ($post, $host, $callbacks) {
    $saved = [];
    foreach ($callbacks as $name => $callback) {
        $type = WP_Block_Type_Registry::get_instance()->get_registered($name);
        $saved[$name] = $type->render_callback;
        $type->render_callback = $callback;
    }
    $GLOBALS['wp_query'] = new WP_Query(['p' => $post, 'cpage' => $cpage]);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $GLOBALS['wp_query']->the_post();
    set_query_var('cpage', $cpage);
    $html = do_blocks('<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comment-template --><!-- wp:comment-content /--><!-- /wp:comment-template --><!-- wp:comments-pagination ' . $attrs . ' -->' . $inner . '<!-- /wp:comments-pagination --></div><!-- /wp:comments -->');
    wp_reset_postdata();
    foreach ($saved as $name => $callback) {
        WP_Block_Type_Registry::get_instance()->get_registered($name)->render_callback = $callback;
    }
    preg_match('#<nav.*</nav>#s', $html, $nav);
    $nav = (string) preg_replace(['#https?://' . preg_quote($host, '#') . '#', '/(wp-container-[a-z0-9-]+-is-layout-)[0-9a-f]{8}/'], ['{site}', '$1HASH'], $nav[0] ?? '');
    return ['nav' => $nav, 'shown' => preg_match_all('/ZZ cpp \d/', $html, $m) ? $m[0] : []];
};
$say('newest first', [
    'the default page' => $page(0),
    'page 1' => $page(1),
    'page 2' => $page(2),
    'page 3' => $page(3),
    'past the last' => $page(9),
]);
$say('arrows and labels', [
    'arrows' => $page(2, '{"paginationArrow":"arrow"}'),
    'chevrons' => $page(2, '{"paginationArrow":"chevron"}'),
    'labels hidden, with arrows' => $page(2, '{"paginationArrow":"arrow","showLabel":false}'),
    'labels of their own' => $page(2, '', '<!-- wp:comments-pagination-previous {"label":"ZZ older"} /--><!-- wp:comments-pagination-next {"label":"ZZ newer"} /-->'),
    'aligned' => $page(2, '{"layout":{"type":"flex","justifyContent":"space-between"}}'),
    'numbers alone' => $page(2, '', '<!-- wp:comments-pagination-numbers /-->'),
]);
update_option('default_comments_page', 'oldest');
$say('oldest first', [
    'the default page' => $page(0),
    'page 2' => $page(2),
    'page 3' => $page(3),
]);
update_option('page_comments', '0');
$say('paging off', $page(2));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
