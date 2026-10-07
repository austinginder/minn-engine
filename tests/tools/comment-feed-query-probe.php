<?php
/**
 * The comments a comments feed carries, as the reference's main query
 * finds them (probe comment-feed-query): the site's comments feed, a
 * post's, a category's and a search's; the clauses the comment_feed_*
 * filters are handed, the comments and posts the query ends with, the
 * comment loop over them, and a plugin narrowing the comments through
 * comment_feed_where. Read only. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
foreach (['comment_feed_join', 'comment_feed_where', 'comment_feed_groupby', 'comment_feed_orderby', 'comment_feed_limits'] as $filter) {
    add_filter($filter, static function ($clause, $query = null) use (&$heard, $filter) {
        // The placeholder escape is wpdb's own per request; it is masked.
        $heard[] = [$filter, preg_replace('/\{[0-9a-f]{64}\}/', '%', (string) $clause), is_object($query) ? get_class($query) : $query];
        return $clause;
    }, 10, 2);
}
$looped = [];
add_action('comment_loop_start', static function () use (&$looped): void {
    $looped[] = 'comment_loop_start';
});
$run = static function (string $label, array $vars) use ($say, &$heard, &$looped): void {
    $heard = [];
    query_posts($vars);
    global $wp_query;
    $looped = [];
    $seen = [];
    while (have_comments()) {
        the_comment();
        $seen[] = (int) get_comment_ID();
    }
    $say($label, [
        'is_comment_feed' => is_comment_feed(),
        'heard' => $heard,
        'comments' => array_map(static fn ($c) => (int) $c->comment_ID, (array) $wp_query->comments),
        'comment_count' => $wp_query->comment_count,
        'posts' => array_map(static fn ($p) => (int) $p->ID, (array) $wp_query->posts),
        'post_count' => $wp_query->post_count,
        'found_posts' => $wp_query->found_posts,
        'looped' => [$seen, $looped, have_comments()],
    ]);
    wp_reset_query();
};
$run('the site\'s comments feed', ['feed' => 'rss2', 'withcomments' => 1]);
$run('a post\'s comments feed', ['feed' => 'rss2', 'p' => 1]);
$run('a post\'s feed without comments', ['feed' => 'rss2', 'p' => 1, 'withoutcomments' => 1]);
$run('a category\'s comments feed', ['feed' => 'rss2', 'withcomments' => 1, 'cat' => (int) get_option('default_category')]);
$run('a search\'s comments feed', ['feed' => 'rss2', 'withcomments' => 1, 's' => 'hello']);
$run('the posts feed', ['feed' => 'rss2']);
add_filter('comment_feed_where', static fn ($where) => $where . ' AND 1=0');
$run('the site\'s comments feed a plugin empties', ['feed' => 'rss2', 'withcomments' => 1]);
$run('a post\'s comments feed a plugin empties', ['feed' => 'rss2', 'p' => 1]);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
