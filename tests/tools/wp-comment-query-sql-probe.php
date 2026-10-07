<?php
/**
 * WP_Comment_Query's SQL as plugin code meets it, as the reference builds
 * it (probe wp-comment-query-sql): for each set of arguments, the clauses
 * comments_clauses is handed, the request, the comment query filters that
 * run in their order, what comes back (comment ids by name, a count, or a
 * thread) and the found and page counts, how many queries ran (a thread
 * takes one a level) and what the query keeps. Then filters that change
 * the clauses, answer before the query, rewrite the results or the count.
 * Each query starts after a bump of the comment last_changed, so none is
 * answered from the reference's query cache (the engine keeps none). The
 * probe's own post, comments and meta, removed at the end. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$ids = [];
$ids['post'] = (int) wp_insert_post(['post_title' => 'Zz Comments Post', 'post_name' => 'zz-comments-post', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'open']);
$ids['other'] = (int) wp_insert_post(['post_title' => 'Zz Comments Page', 'post_type' => 'page', 'post_name' => 'zz-comments-page', 'post_status' => 'publish', 'post_author' => 1]);
$add = static function (string $name, array $data) use (&$ids): void {
    $ids[$name] = (int) wp_insert_comment($data + ['comment_post_ID' => $ids['post'], 'comment_author' => 'Zz ' . ucfirst($name), 'comment_author_email' => "{$name}@zz-comments.test", 'comment_content' => "zz {$name} words", 'comment_approved' => 1, 'comment_type' => 'comment']);
};
$add('ann', ['comment_date' => '2025-01-01 10:00:00', 'comment_date_gmt' => '2025-01-01 10:00:00', 'comment_karma' => 2, 'comment_author_url' => 'https://ann.zz-comments.test']);
$add('bob', ['comment_date' => '2025-02-01 10:00:00', 'comment_date_gmt' => '2025-02-01 10:00:00', 'user_id' => 1]);
$add('reply', ['comment_date' => '2025-02-02 10:00:00', 'comment_date_gmt' => '2025-02-02 10:00:00', 'comment_parent' => $ids['ann']]);
$add('reply2', ['comment_date' => '2025-02-01 12:00:00', 'comment_date_gmt' => '2025-02-01 12:00:00', 'comment_parent' => $ids['ann']]);
$add('bobreply', ['comment_date' => '2025-02-04 10:00:00', 'comment_date_gmt' => '2025-02-04 10:00:00', 'comment_parent' => $ids['bob']]);
$add('heldreply', ['comment_date' => '2025-02-06 10:00:00', 'comment_date_gmt' => '2025-02-06 10:00:00', 'comment_parent' => $ids['bob'], 'comment_approved' => 0]);
$add('deep', ['comment_date' => '2025-02-03 10:00:00', 'comment_date_gmt' => '2025-02-03 10:00:00', 'comment_parent' => $ids['reply']]);
$add('held', ['comment_date' => '2025-03-01 10:00:00', 'comment_date_gmt' => '2025-03-01 10:00:00', 'comment_approved' => 0]);
$add('spam', ['comment_date' => '2025-03-02 10:00:00', 'comment_date_gmt' => '2025-03-02 10:00:00', 'comment_approved' => 'spam']);
$add('ping', ['comment_date' => '2025-03-03 10:00:00', 'comment_date_gmt' => '2025-03-03 10:00:00', 'comment_type' => 'pingback']);
$add('page', ['comment_date' => '2025-04-01 10:00:00', 'comment_date_gmt' => '2025-04-01 10:00:00', 'comment_post_ID' => $ids['other']]);
add_comment_meta($ids['ann'], 'zz_rank', 5);
add_comment_meta($ids['bob'], 'zz_rank', 9);

$mask = static function ($value) use (&$ids) {
    $byId = [];
    foreach (array_filter($ids) as $name => $id) {
        $byId[$id] ??= $name;
    }
    $walk = static function ($v) use (&$walk, $byId) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $item) {
                $out[is_int($k) && isset($byId[$k]) ? '{' . $byId[$k] . '}' : $k] = $walk($item);
            }
            return $out;
        }
        if (is_int($v) && isset($byId[$v])) {
            return '{' . $byId[$v] . '}';
        }
        if (!is_string($v)) {
            return $v;
        }
        $v = (string) preg_replace('/\{[0-9a-f]{64}\}/', '%', $v);
        foreach ($byId as $id => $name) {
            $v = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $v);
        }
        return $v;
    };
    return $walk($value);
};
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(parse_comment_query|pre_get_comments|comments_pre_query|comments_clauses|found_comments_query|the_comments|get_meta_sql|get_date_sql|comment_feed_)/', $hook)) {
        $fired[] = $hook;
    }
});
// A comment as its name; for a threaded query, its children's names too
// (read only then: a filter still hooked would answer the children's own
// query, and a thread is never deeper than the probe's three levels).
$shape = static function ($comments, bool $threads = false, int $depth = 0) use (&$shape) {
    if (!is_array($comments) || $depth > 4) {
        return $comments;
    }
    $out = [];
    foreach ($comments as $key => $comment) {
        if (!$comment instanceof WP_Comment) {
            $out[$key] = $comment;
            continue;
        }
        $children = $threads ? $comment->get_children(['format' => 'tree', 'status' => 'all']) : [];
        $out[$key] = $children === [] ? (int) $comment->comment_ID : [(int) $comment->comment_ID => $shape($children, true, $depth + 1)];
    }
    return $out;
};
$query = static function (array $args) use (&$fired, $mask, $shape): array {
    $clauses = null;
    $grab = static function ($pieces) use (&$clauses) {
        $clauses ??= $pieces;
        return $pieces;
    };
    add_filter('comments_clauses', $grab);
    wp_cache_set_last_changed('comment');
    $fired = [];
    $q = new WP_Comment_Query();
    $result = $q->query($args);
    remove_filter('comments_clauses', $grab);
    $runs = count(array_keys($fired, 'pre_get_comments', true));
    return $mask([
        'clauses' => $clauses,
        'request' => preg_replace('/\s+/', ' ', trim((string) $q->request)),
        'filters' => array_values(array_unique($fired)),
        'runs' => $runs,
        'comments' => $shape($result, ($args['hierarchical'] ?? '') === 'threaded'),
        'found' => [$q->found_comments, $q->max_num_pages],
        'kept' => is_array($q->comments) ? count($q->comments) : $q->comments,
    ]);
};
$ours = ['post__in' => [$ids['post'], $ids['other']]];
// A comment's children, asked for before any query has filled them in.
$ann = get_comment($ids['ann']);
$fired = [];
$say('children, as a tree', $mask($shape($ann->get_children(['format' => 'tree', 'status' => 'all']), true)));
$flat = $ann->get_children(['format' => 'flat', 'status' => 'all']);
$say('children, flat', $mask([array_keys($flat), array_map(static fn ($c) => (int) $c->comment_ID, $flat)]));
$say('children\'s queries', $fired);
$fired = [];
$fresh = get_comment($ids['ann']);
$say('children, flat first', $mask(array_map(static fn ($c) => (int) $c->comment_ID, $fresh->get_children(['format' => 'flat']))));
$say('a fresh copy is another object', $fresh !== $ann);
$bob = get_comment($ids['bob']);
$say('approved children only', $mask(array_keys($bob->get_children(['status' => 'approve']))));
$say('approved children again, all asked', $mask(array_keys($bob->get_children(['status' => 'all']))));
$say('one child', $mask((int) ($bob->get_child($ids['bobreply'])->comment_ID ?? 0)));
$cases = [
    'the default' => [],
    'approved' => ['status' => 'approve'],
    'held' => ['status' => 'hold'],
    'spam' => ['status' => 'spam'],
    'approved or held' => ['status' => ['approve', 'hold']],
    'approved, and one reader\'s held' => ['status' => 'approve', 'include_unapproved' => ['held@zz-comments.test']],
    'one post' => ['post_id' => $ids['post'], 'post__in' => []],
    'not this post' => ['post__not_in' => [$ids['other']]],
    'on pages' => ['post_type' => 'page'],
    'on published posts by an author' => ['post_status' => 'publish', 'post_author' => 1],
    'on a post by name' => ['post_name' => 'zz-comments-post'],
    'pings only' => ['type' => 'pings'],
    'comments only' => ['type' => 'comment'],
    'no pingbacks' => ['type__not_in' => ['pingback']],
    'top level' => ['parent' => 0],
    'replies to ann' => ['parent__in' => [$ids['ann']]],
    'not replies to ann' => ['parent__not_in' => [$ids['ann']]],
    'by email' => ['author_email' => 'bob@zz-comments.test'],
    'by url' => ['author_url' => 'https://ann.zz-comments.test'],
    'by user' => ['user_id' => 1],
    'by authors' => ['author__in' => [1]],
    'not by authors' => ['author__not_in' => [1]],
    'by karma' => ['karma' => 2],
    'a search' => ['search' => 'deep'],
    'included' => ['comment__in' => [$ids['bob'], $ids['ann']], 'orderby' => 'comment__in'],
    'excluded' => ['comment__not_in' => [$ids['bob']]],
    'oldest first' => ['order' => 'ASC'],
    'by id' => ['orderby' => 'comment_ID', 'order' => 'ASC'],
    'by author name' => ['orderby' => 'comment_author'],
    'by two keys' => ['orderby' => ['comment_approved' => 'DESC', 'comment_ID' => 'ASC']],
    'by a meta value' => ['meta_key' => 'zz_rank', 'orderby' => 'meta_value_num'],
    'a meta query' => ['meta_query' => [['key' => 'zz_rank', 'value' => 6, 'compare' => '>', 'type' => 'NUMERIC']]],
    'no order' => ['orderby' => 'none'],
    'two a page' => ['number' => 2],
    'two a page, found' => ['number' => 2, 'no_found_rows' => false],
    'page two' => ['number' => 2, 'paged' => 2, 'no_found_rows' => false],
    'a page past the end, found' => ['number' => 2, 'paged' => 9, 'no_found_rows' => false],
    'an offset' => ['number' => 2, 'offset' => 1],
    'counted' => ['count' => true],
    'ids' => ['fields' => 'ids'],
    'a date range' => ['date_query' => [['after' => '2025-01-15', 'before' => '2025-03-15']]],
    'threaded' => ['hierarchical' => 'threaded', 'status' => 'approve', 'post_id' => $ids['post'], 'post__in' => []],
    'flat' => ['hierarchical' => 'flat', 'status' => 'approve', 'post_id' => $ids['post'], 'post__in' => [], 'parent' => 0],
    'threaded, every status' => ['hierarchical' => 'threaded', 'post_id' => $ids['post'], 'post__in' => []],
    'flat, oldest first' => ['hierarchical' => 'flat', 'status' => 'approve', 'order' => 'ASC'],
    'threaded, a page found' => ['hierarchical' => 'threaded', 'status' => 'approve', 'number' => 2, 'no_found_rows' => false],
    'threaded ids' => ['hierarchical' => 'threaded', 'fields' => 'ids'],
    'threaded, counted' => ['hierarchical' => 'threaded', 'count' => true],
    'threaded under a parent' => ['hierarchical' => 'threaded', 'parent' => $ids['ann']],
    'any status' => ['status' => 'any'],
    'trash' => ['status' => 'trash'],
    'every status and one reader\'s' => ['include_unapproved' => ['held@zz-comments.test']],
    'approved, and a user\'s held' => ['status' => 'approve', 'include_unapproved' => [1, 'heldreply@zz-comments.test']],
    'notes' => ['type' => 'note'],
    'every type' => ['type' => 'all'],
    'types in' => ['type__in' => ['comment', 'pingback']],
    'a type and types in' => ['type' => 'comment', 'type__in' => ['pingback']],
    'posts or pages' => ['post_type' => ['post', 'page']],
    'a post parent' => ['post_parent' => 0],
    'by post_ID' => ['post_ID' => $ids['post']],
    'post_id and post__in' => ['post_id' => $ids['post']],
    'a parent and parents in' => ['parent' => 0, 'parent__in' => [$ids['ann']]],
    'karma zero' => ['karma' => 0],
    'user zero' => ['user_id' => 0],
    'a search with spaces and a percent' => ['search' => 'zz 5% ann'],
    'a date query on a column' => ['date_query' => [['column' => 'comment_date_gmt', 'year' => 2025, 'month' => 2]]],
    'counted with a meta join' => ['count' => true, 'meta_key' => 'zz_rank'],
    'ids, found' => ['fields' => 'ids', 'number' => 2, 'no_found_rows' => false],
    'by date' => ['orderby' => 'comment_date'],
    'by post' => ['orderby' => 'comment_post_ID'],
    'by karma, ascending' => ['orderby' => 'comment_karma', 'order' => 'asc'],
    'by parent' => ['orderby' => 'comment_parent'],
    'by type' => ['orderby' => 'comment_type'],
    'by user id' => ['orderby' => 'user_id'],
    'by author email' => ['orderby' => 'comment_author_email'],
    'by meta value' => ['meta_key' => 'zz_rank', 'orderby' => 'meta_value'],
    'by the meta key' => ['meta_key' => 'zz_rank', 'orderby' => 'zz_rank'],
    'by a named meta clause' => ['meta_query' => ['rank' => ['key' => 'zz_rank', 'type' => 'NUMERIC']], 'orderby' => 'rank'],
    'by meta value without a key' => ['orderby' => 'meta_value_num'],
    'by an unknown key' => ['orderby' => 'nonsense'],
    'a list in a string' => ['orderby' => 'comment_author comment_date_gmt'],
    'a map, mixed directions' => ['orderby' => ['comment_author' => 'ASC', 'comment_karma' => 'DESC']],
    'a bad direction' => ['order' => 'sideways'],
    'an offset and a page' => ['number' => 2, 'offset' => 1, 'paged' => 3],
    'a page without a number' => ['paged' => 2],
    'a number as text' => ['number' => '2'],
    'by karma, ASC' => ['orderby' => 'comment_karma', 'order' => 'ASC'],
    'by date, oldest first' => ['orderby' => 'comment_date', 'order' => 'ASC'],
    'by author then date, oldest first' => ['orderby' => 'comment_author comment_date_gmt', 'order' => 'ASC'],
    'by unknown, oldest first' => ['orderby' => 'nonsense', 'order' => 'ASC'],
    'by a map with the date ascending' => ['orderby' => ['comment_karma' => 'DESC', 'comment_date' => 'ASC']],
    'by date gmt, named' => ['orderby' => 'comment_date_gmt'],
    'by agent' => ['orderby' => 'comment_agent'],
    'by content' => ['orderby' => 'comment_content'],
    'by ip' => ['orderby' => 'comment_author_IP'],
    'by author url' => ['orderby' => 'comment_author_url'],
    'by ID' => ['orderby' => 'ID'],
    'counted, two a page' => ['count' => true, 'number' => 2],
    'a comma list of statuses' => ['status' => 'approve,hold'],
    'one reader as text' => ['status' => 'approve', 'include_unapproved' => 'held@zz-comments.test'],
    'post fields at once' => ['post_author' => 1, 'post_name' => 'zz-comments-post', 'post_parent' => $ids['other'], 'post_status' => ['publish', 'draft'], 'post_type' => 'post'],
    'post authors in' => ['post_author__in' => [1]],
    'nothing to narrow' => ['status' => 'any', 'type' => 'all', 'post__in' => [], 'number' => 1, 'orderby' => 'comment_ID'],
    'an offset of zero on page two' => ['number' => 2, 'offset' => 0, 'paged' => 2],
    'included as text' => ['comment__in' => "{$ids['ann']},{$ids['bob']}"],
    'everything at once' => ['status' => 'approve', 'include_unapproved' => ['held@zz-comments.test'], 'comment__in' => [$ids['ann'], $ids['bob']], 'comment__not_in' => [$ids['held']], 'post_id' => $ids['post'], 'parent__in' => [0], 'parent__not_in' => [$ids['reply']], 'post__not_in' => [$ids['other']], 'author_email' => 'ann@zz-comments.test', 'author_url' => 'https://ann.zz-comments.test', 'karma' => 2, 'type' => 'comment', 'type__not_in' => ['pingback'], 'parent' => 0, 'user_id' => 0, 'author__in' => [0], 'author__not_in' => [7], 'post_author__in' => [1], 'post_author__not_in' => [7], 'post_author' => 1, 'post_name' => 'zz-comments-post', 'post_parent' => 0, 'post_status' => 'publish', 'post_type' => 'post', 'search' => 'ann', 'meta_query' => [['key' => 'zz_rank', 'compare' => 'EXISTS']], 'date_query' => [['year' => 2025]]],
];
foreach ($cases as $label => $args) {
    $say($label, $query($args + $ours));
}
$say('ids are', array_map('gettype', (new WP_Comment_Query(['fields' => 'ids', 'number' => 1] + $ours))->comments));
// The request as written, and the query's meta and date objects, for a few shapes.
foreach ([
    'the default' => [],
    'a count with a meta join' => ['count' => true, 'meta_key' => 'zz_rank'],
    'a page, found' => ['number' => 2, 'no_found_rows' => false, 'meta_key' => 'zz_rank', 'orderby' => 'meta_value_num'],
    'every piece' => $cases['everything at once'],
] as $label => $args) {
    wp_cache_set_last_changed('comment');
    $q = new WP_Comment_Query($args + $ours);
    $say("as written: {$label}", $mask(['request' => $q->request, 'meta' => $q->meta_query instanceof WP_Meta_Query, 'date' => is_object($q->date_query) ? get_class($q->date_query) : $q->date_query]));
}
// What each query of a thread starts from: the first, then one a level.
$vars = [];
add_action('pre_get_comments', $note = static function ($q) use (&$vars) {
    $vars[] = array_filter($q->query_vars, static fn ($v) => $v !== '' && $v !== [] && $v !== null && $v !== false);
});
wp_cache_set_last_changed('comment');
new WP_Comment_Query(['hierarchical' => 'threaded', 'status' => 'approve', 'number' => 5, 'orderby' => 'comment_date', 'order' => 'ASC'] + $ours);
remove_action('pre_get_comments', $note);
$say('a thread\'s queries', $mask($vars));
wp_cache_set_last_changed('comment');
$say('get_comments', $mask($shape(get_comments(['post_id' => $ids['post'], 'status' => 'approve']))));
wp_cache_set_last_changed('comment');
$say('get_comments counted', get_comments(['post_id' => $ids['post'], 'count' => true]));

$with = static function (string $label, string $hook, callable $callback, array $args = []) use ($say, $query, $ours): void {
    add_filter($hook, $callback);
    $say($label, $query($args + $ours));
    remove_filter($hook, $callback);
};
$with('clauses changed', 'comments_clauses', static fn ($c) => ['where' => $c['where'] . " AND comment_author != 'Zz Bob'"] + $c);
$with('answered before the query', 'comments_pre_query', static fn () => [get_comment($ids['ann'])]);
$with('results rewritten', 'the_comments', static fn ($comments) => array_reverse($comments));
$with('the count query replaced', 'found_comments_query', static fn () => 'SELECT 42', ['number' => 1, 'no_found_rows' => false]);
add_action('pre_get_comments', $pre = static function ($q) {
    $q->query_vars['order'] = 'ASC';
    $q->query_vars['number'] = 2;
});
$say('pre_get_comments changes the query', $query($ours));
remove_action('pre_get_comments', $pre);

foreach (['ann', 'bob', 'reply', 'reply2', 'bobreply', 'heldreply', 'deep', 'held', 'spam', 'ping', 'page'] as $name) {
    wp_delete_comment($ids[$name], true);
}
wp_delete_post($ids['post'], true);
wp_delete_post($ids['other'], true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
