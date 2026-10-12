<?php
/**
 * image_resize_dimensions at its edges: an asked size larger than the image,
 * one equal to it, one a pixel short, crop and no crop, with
 * wp_image_resize_identical_dimensions saying no and then yes.
 */
$seen = [];
$allow = false;
add_filter('wp_image_resize_identical_dimensions', static function ($v, $w, $h) use (&$seen, &$allow) { $seen[] = [$v, $w, $h]; return $allow; }, 10, 3);
$out = [];
$cases = [
    [100, 100, 100, 100, false], [100, 100, 200, 200, false], [100, 100, 100, 0, false], [100, 100, 0, 100, false],
    [100, 100, 100, 200, false], [100, 100, 200, 100, false], [100, 100, 150, 0, false], [100, 100, 0, 150, false],
    [1600, 1000, 2048, 2048, false], [1600, 1000, 1600, 1600, false], [1600, 1000, 1600, 1000, false], [1600, 1000, 2000, 1000, false],
    [100, 100, 100, 100, true], [100, 100, 200, 200, true], [100, 100, 100, 50, true], [100, 50, 100, 100, true], [100, 100, 100, 0, true], [100, 100, 0, 100, true],
    [100, 100, 99, 100, false], [100, 100, 100, 99, false], [1600, 1000, 1601, 999, false],
];
foreach ([false, true] as $a) {
    $allow = $a;
    foreach ($cases as $c) {
        $seen = [];
        $r = image_resize_dimensions(...$c);
        $out[] = [($a ? 'allow ' : '') . implode(',', array_map('json_encode', $c)), [$r, $seen]];
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES);
