<?php
/**
 * The Query Loop block's posts as the reference finds them (probe
 * query-loop): block markup through do_blocks for each query setting (post
 * type, order, terms, exclusions, offset and page size, parents, search,
 * authors, stickies), the
 * titles each lists, and the arguments query_loop_block_query_vars was
 * handed. Then a plugin's filter and pre_get_posts changing the loop. The
 * probe's own type, taxonomy and posts, removed at the end. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_post_type('zz_ql', ['public' => true, 'hierarchical' => true, 'show_in_rest' => true, 'supports' => ['title', 'editor', 'page-attributes']]);
register_taxonomy('zz_qlt', ['zz_ql'], ['public' => true, 'show_in_rest' => true, 'hierarchical' => true]);

$ids = [];
$made = ['posts' => [], 'terms' => []];
$term = wp_insert_term('Zz Ql Red', 'zz_qlt', ['slug' => 'zz-ql-red']);
$ids['red'] = (int) ($term['term_id'] ?? 0);
$made['terms'][] = $ids['red'];
foreach (['one' => ['2025-01-01', 3, true], 'two' => ['2025-02-01', 1, false], 'three' => ['2025-03-01', 2, true], 'four' => ['2025-04-01', 4, false]] as $slug => [$date, $menu, $red]) {
    $id = (int) wp_insert_post(['post_type' => 'zz_ql', 'post_title' => 'Zz Ql ' . ucfirst($slug), 'post_name' => "zz-ql-{$slug}", 'post_status' => 'publish', 'post_date' => "{$date} 10:00:00", 'post_date_gmt' => "{$date} 10:00:00", 'menu_order' => $menu, 'post_content' => "zz {$slug} words", 'post_author' => 1]);
    $ids[$slug] = $id;
    $made['posts'][] = $id;
    if ($red) {
        wp_set_object_terms($id, [$ids['red']], 'zz_qlt');
    }
}
$ids['child'] = (int) wp_insert_post(['post_type' => 'zz_ql', 'post_title' => 'Zz Ql Child', 'post_name' => 'zz-ql-child', 'post_status' => 'publish', 'post_parent' => $ids['two'], 'post_date' => '2025-05-01 10:00:00', 'post_date_gmt' => '2025-05-01 10:00:00', 'post_author' => 1]);
$made['posts'][] = $ids['child'];

$names = array_flip(array_filter($ids));
$mask = static function ($value) use ($names) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = is_int($v) && isset($names[$v]) ? '{' . $names[$v] . '}' : (is_array($v) ? json_decode((string) json_encode($v), true) : $v);
        }
        return json_decode((string) preg_replace_callback('/(?<=[\[:,])(\d+)(?=[,\]}])/', static fn ($m) => isset($names[(int) $m[1]]) ? '"{' . $names[(int) $m[1]] . '}"' : $m[1], (string) json_encode($out)), true);
    }
    return $value;
};
$handed = [];
add_filter('query_loop_block_query_vars', static function ($query, $block, $page) use (&$handed) {
    $handed[] = [$query, $page];
    return $query;
}, 10, 3);
$loop = static function (array $query, int $queryId = 7) use (&$handed, $mask): array {
    $handed = [];
    $markup = '<!-- wp:query ' . json_encode(['queryId' => $queryId, 'query' => $query]) . ' --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->';
    $html = do_blocks($markup);
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $m);
    return ['titles' => array_map(static fn ($t) => trim(wp_strip_all_tags($t)), $m[1]), 'handed' => array_map(static fn ($h) => [$mask($h[0]), $h[1]], $handed)];
};
$base = ['postType' => 'zz_ql', 'perPage' => 10, 'order' => 'desc', 'orderBy' => 'date', 'inherit' => false];
$cases = [
    'the default' => [],
    'oldest first' => ['order' => 'asc'],
    'by title' => ['orderBy' => 'title', 'order' => 'asc'],
    'by menu order' => ['orderBy' => 'menu_order', 'order' => 'asc'],
    'two per page' => ['perPage' => 2],
    'two, after one' => ['perPage' => 2, 'offset' => 1],
    'in a term' => ['taxQuery' => ['zz_qlt' => [$ids['red']]]],
    'excluding some' => ['exclude' => [$ids['one'], $ids['three']]],
    'under a parent' => ['parents' => [$ids['two']]],
    'a search' => ['search' => 'three'],
    'by an author' => ['author' => '1'],
    'by authors as a list' => ['author' => [1]],
    'no such author' => ['author' => '99999'],
    'an unviewable type' => ['postType' => 'zz_none'],
    'an author by number' => ['author' => 1],
    'a bad order' => ['order' => 'sideways'],
    'page size as text' => ['perPage' => '2', 'offset' => '1'],
    'no page size' => ['perPage' => null],
    'excluding zero' => ['exclude' => [0, $ids['four']]],
    'in a term, and formats' => ['taxQuery' => ['zz_qlt' => [$ids['red']]], 'format' => ['standard', 'aside', 'zz-bogus']],
    'a format only' => ['format' => ['aside']],
    'standard only' => ['format' => ['standard']],
    'parents of a flat type' => ['postType' => 'post', 'parents' => [$ids['two']]],
    'old category and tag ids' => ['postType' => 'post', 'categoryIds' => [1, 0], 'tagIds' => [999]],
    'stickies ignored' => ['postType' => 'post', 'perPage' => 3, 'sticky' => 'ignore'],
    'a term list with an empty taxonomy' => ['taxQuery' => ['zz_qlt' => [], 'category' => [1], 'zz_none' => [5]]],
];
foreach ($cases as $label => $query) {
    $say($label, $loop($query + $base));
}
$say('posts, stickies only', $loop(['postType' => 'post', 'perPage' => 3, 'sticky' => 'only', 'inherit' => false]));
$say('posts, stickies left out', $loop(['postType' => 'post', 'perPage' => 3, 'sticky' => 'exclude', 'inherit' => false]));
$say('posts, the default', $loop(['postType' => 'post', 'perPage' => 3, 'inherit' => false]));

// A plugin's say in the loop.
$say('a filtered loop', (static function () use ($loop, $base) {
    $f = static fn ($query) => ['orderby' => 'title', 'order' => 'ASC'] + $query;
    add_filter('query_loop_block_query_vars', $f, 20);
    $out = $loop($base);
    remove_filter('query_loop_block_query_vars', $f, 20);
    return $out;
})());
$say('pre_get_posts on the loop', (static function () use ($loop, $base) {
    $f = static function ($q) {
        if ($q->get('post_type') === 'zz_ql') {
            $q->set('posts_per_page', 1);
        }
    };
    add_action('pre_get_posts', $f);
    $out = $loop($base);
    remove_action('pre_get_posts', $f);
    return $out;
})());

foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach ($made['terms'] as $id) {
    wp_delete_term($id, 'zz_qlt');
}
unregister_taxonomy('zz_qlt');
unregister_post_type('zz_ql');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
