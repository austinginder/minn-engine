<?php
/**
 * When a query is a search (probe search-flags): naming s at all (an empty
 * one, 0, false, an array; not null), on a re-parse as first asked, never
 * with a single named. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$cases = [
    ['s' => 'x'], ['s' => ''], ['s' => '0'], ['s' => 0], ['s' => false], ['s' => null], ['s' => []], ['s' => ' '],
    ['post_type' => 'post'], ['s' => '', 'post_type' => 'post'], ['s' => 'x', 'category_name' => 'uncategorized'],
    ['s' => 'x', 'p' => 1], ['s' => '', 'p' => 1], ['s' => 'x', 'name' => 'hello-world'], ['s' => 'x', 'page_id' => 2],
    ['s' => 'x', 'pagename' => 'sample-page'], ['s' => 'x', 'attachment' => 'a'],
];
foreach ($cases as $vars) {
    $query = new WP_Query();
    $query->parse_query($vars);
    $say(json_encode($vars), ['search' => $query->is_search, 'singular' => $query->is_singular, 'home' => $query->is_home, 's' => $query->query_vars['s']]);
}
foreach ([['s' => ''], ['post_type' => 'post']] as $vars) {
    $query = new WP_Query();
    $query->parse_query($vars);
    $query->parse_query();
    $say('re-parsed ' . json_encode($vars), $query->is_search);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
