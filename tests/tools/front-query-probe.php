<?php
/**
 * A static front page's query (probe front-query): a home query that names
 * nothing but paged, page, cpage or preview becomes the front page's (a
 * given paged moves into page, is_paged kept); any other var keeps it the
 * home listing. Same protocol as api-probe.php; the reading settings are
 * put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$saved = [get_option('show_on_front'), get_option('page_on_front')];
try {
    update_option('show_on_front', 'page');
    update_option('page_on_front', 2);
    $cases = [
        ['paged' => 2], ['page' => 2], ['paged' => 2, 'cpage' => 2], ['cpage' => 2], ['paged' => '1'], ['paged' => 2, 'page' => 3],
        ['preview' => 'true'], ['paged' => 2, 'preview' => 'true'], ['paged' => 2, 'foo' => 'x'], ['foo' => 'x'], ['post_type' => 'post'],
        ['paged' => 2, 'posts_per_page' => 5],
    ];
    foreach ($cases as $vars) {
        $query = new WP_Query($vars);
        $say(json_encode($vars), [
            'page' => $query->is_page, 'singular' => $query->is_singular, 'home' => $query->is_home, 'posts' => $query->post_count, 'paged' => $query->is_paged, 'front' => $query->is_front_page(),
            'page_id' => $query->get('page_id'), 'qpaged' => $query->get('paged'), 'qpage' => $query->get('page'),
        ]);
    }
} finally {
    update_option('show_on_front', $saved[0]);
    update_option('page_on_front', $saved[1]);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
