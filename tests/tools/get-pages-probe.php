<?php
/**
 * get_pages over a small tree of the probe's own pages, as the reference
 * lists them (probe get-pages): the whole tree, a number (and an offset)
 * of them with and without the hierarchy, the pages under one page, the
 * children of one, a branch left out, and other orders; only the probe's
 * pages count (a meta key marks them). Removed at the end. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$add = static function (string $title, int $parent, int $order) use (&$made): int {
    $id = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_parent' => $parent, 'menu_order' => $order, 'post_content' => 'zz']);
    add_post_meta($id, 'zz_pages_probe', '1');
    return $made[] = $id;
};
$a = $add('Zz A', 0, 3);
$a1 = $add('Zz A 1', $a, 2);
$add('Zz A 1 X', $a1, 1);
$b = $add('Zz B', 0, 1);
$add('Zz B 1', $b, 5);
$ask = static fn (array $args): array => array_map(static fn ($page) => $page->post_title, get_pages($args + ['meta_key' => 'zz_pages_probe', 'meta_value' => '1']));
foreach ([
    'the whole tree' => [],
    'one' => ['number' => 1],
    'two' => ['number' => 2],
    'three' => ['number' => 3],
    'two after one' => ['number' => 2, 'offset' => 1],
    'two, flat' => ['number' => 2, 'hierarchical' => 0],
    'two after two, flat' => ['number' => 2, 'offset' => 2, 'hierarchical' => 0],
    'under A' => ['child_of' => $a],
    'one under A' => ['child_of' => $a, 'number' => 1],
    'the children of A' => ['parent' => $a],
    'the top level' => ['parent' => 0],
    'without the A branch' => ['exclude_tree' => $a],
    'without the A branch, two' => ['exclude_tree' => $a, 'number' => 2],
    'by title, last first' => ['sort_column' => 'post_title', 'sort_order' => 'DESC'],
    'by menu order' => ['sort_column' => 'menu_order'],
    'by menu order, flat' => ['sort_column' => 'menu_order', 'hierarchical' => 0],
] as $label => $args) {
    $say($label, $ask($args));
}
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
