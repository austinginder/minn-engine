<?php
/**
 * The date flags a query raises (probe date-flags): the most specific part
 * named (time, then day, month, year) raises its one flag, m raises one more
 * by its digit count, w raises only is_date, and the document title of a
 * month or day archive follows. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$cases = [
    ['year' => 2026], ['year' => 2026, 'monthnum' => 8], ['year' => 2026, 'monthnum' => 8, 'day' => 5],
    ['monthnum' => 8], ['day' => 5], ['monthnum' => 8, 'day' => 5], ['year' => 2026, 'day' => 5],
    ['m' => '2'], ['m' => '2026'], ['m' => '20260'], ['m' => '202608'], ['m' => '2026081'], ['m' => '20260805'], ['m' => '202608051'],
    ['m' => '2026080512'], ['m' => '20260805123059'], ['m' => 'abc'], ['m' => '2026-08'],
    ['w' => 3], ['year' => 2026, 'w' => 3], ['hour' => 5], ['minute' => 5], ['second' => 5], ['year' => 2026, 'hour' => 5],
    ['year' => 2026, 'monthnum' => 8, 'day' => 5, 'hour' => 5],
    ['m' => '2026', 'monthnum' => 8], ['m' => '202608', 'day' => 5], ['m' => '202608', 'year' => 2025], ['m' => '2026', 'hour' => 5], ['m' => '20260805', 'monthnum' => 8],
];
foreach ($cases as $vars) {
    $query = new WP_Query($vars + ['fields' => 'ids']);
    $on = array_keys(array_filter(['date' => $query->is_date, 'year' => $query->is_year, 'month' => $query->is_month, 'day' => $query->is_day, 'time' => $query->is_time]));
    $say(http_build_query($vars), $on);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "
";
