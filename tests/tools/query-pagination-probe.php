<?php
/**
 * Query pagination's own render callbacks (probe query-pagination): the
 * previous, next and numbers blocks called by name with a block in a query's
 * context, outside a render (so no block-supports classes), for the main
 * query (inherit) and a custom one (its page read from query-{id}-page) on
 * the first, a middle and the last page, with labels given and not, each
 * arrow, the label hidden, middle sizes for the numbers, a single page, and
 * a page past the end. The probe's posts are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => is_string($value) ? (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value) : $value;
foreach (range(1, 5) as $n) {
    $id = wp_insert_post(['post_title' => "zz qpg item {$n}", 'post_status' => 'publish', 'post_content' => 'x', 'post_date' => "1978-02-0{$n} 10:00:00"]);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "query-pagination: a post could not be made\n");
        exit(1);
    }
    $made[] = $id;
}
$_SERVER['REQUEST_URI'] = '/';
$block = static fn (string $name, array $attrs, array $context) => new WP_Block(['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], $context);
$calls = static function (array $attrs, array $context) use ($block, $mask): array {
    $out = [];
    foreach ([
        'previous' => static fn () => render_block_core_query_pagination_previous($attrs, '', $block('core/query-pagination-previous', $attrs, $context)),
        'next' => static fn () => render_block_core_query_pagination_next($attrs, '', $block('core/query-pagination-next', $attrs, $context)),
        'numbers' => static fn () => render_block_core_query_pagination_numbers($attrs, '', $block('core/query-pagination-numbers', $attrs, $context)),
    ] as $which => $call) {
        try {
            $out[$which] = $mask($call());
        } catch (Throwable $e) {
            $out[$which] = ['threw' => get_class($e)];
        }
    }
    return $out;
};
// The main request sets the global page as well as the query's.
$main = static function (int $paged, int $perPage = 2) {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query(['s' => 'zz qpg item', 'posts_per_page' => $perPage, 'paged' => $paged, 'orderby' => 'date', 'order' => 'ASC']);
    $GLOBALS['paged'] = $paged;
};

$inherit = ['queryId' => 0, 'query' => ['inherit' => true]];
$results = [];
foreach ([1, 2, 3, 4] as $paged) {
    $main($paged);
    $results["the main query, page {$paged}"] = $calls([], $inherit);
}
$main(2);
$results['labels given'] = $calls(['label' => 'Older <b>posts</b>'], $inherit);
foreach (['arrow', 'chevron', 'none', 'zz'] as $arrow) {
    $results["the {$arrow} arrow"] = $calls([], $inherit + ['paginationArrow' => $arrow]);
}
$results['the label hidden'] = $calls([], $inherit + ['paginationArrow' => 'arrow', 'showLabel' => false]);
$results['the label hidden, no arrow'] = $calls([], $inherit + ['showLabel' => false]);
$main(3, 1);
$results['a middle size'] = $calls(['midSize' => 1], $inherit);
$results['no middle size'] = $calls(['midSize' => 0], $inherit);
$results['the default middle size'] = $calls([], $inherit);
$main(1, 10);
$results['a single page'] = $calls([], $inherit);

$custom = ['queryId' => 7, 'query' => ['perPage' => 2, 'postType' => 'post', 'search' => 'zz qpg item', 'order' => 'asc', 'orderBy' => 'date', 'inherit' => false, 'pages' => 0, 'offset' => 0]];
$main(1);
foreach ([null, '1', '2', '3', '9'] as $page) {
    $page === null ? $_GET = [] : $_GET = ['query-7-page' => $page];
    $results['a custom query, page ' . ($page ?? 'none')] = $calls([], $custom);
}
$_GET = ['query-7-page' => '2'];
$results['a custom query capped by pages'] = $calls([], ['queryId' => 7, 'query' => ['pages' => 2] + $custom['query']]);
$results['a custom query, an arrow and a label'] = $calls(['label' => 'Onward'], $custom + ['paginationArrow' => 'chevron']);
$_GET = [];
$say('pagination callbacks', $results);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
