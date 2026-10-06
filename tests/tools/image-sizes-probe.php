<?php
/**
 * Where the registered image sizes live: the two big ones every site has
 * are in $_wp_additional_image_sizes beside a plugin's own (Smush hashes
 * that global), and remove_image_size takes one of them away. Same
 * protocol as api-probe.php; what it changes it puts back.
 */

$log = [];
$log[] = ['the global', $GLOBALS['_wp_additional_image_sizes'] ?? null];
$log[] = ['wp_get_additional_image_sizes', wp_get_additional_image_sizes()];
$log[] = ['get_intermediate_image_sizes', get_intermediate_image_sizes()];
add_image_size('zz-probe', 640, 480, ['center', 'top']);
$log[] = ['a plugin adds one', array_keys(wp_get_additional_image_sizes())];
add_image_size('zz-probe-int', 10, 10, 1);
add_image_size('zz-probe-string', 10, 10, 'yes');
$registered = wp_get_registered_image_subsizes();
$log[] = ['as registered', [$registered['zz-probe'] ?? null, $registered['zz-probe-int'] ?? null, $registered['zz-probe-string'] ?? null]];
$log[] = ['as added', [wp_get_additional_image_sizes()['zz-probe-int'] ?? null, wp_get_additional_image_sizes()['zz-probe-string'] ?? null]];
remove_image_size('zz-probe-int');
remove_image_size('zz-probe-string');
$log[] = ['remove a big one', remove_image_size('2048x2048')];
$log[] = ['then', get_intermediate_image_sizes()];
$log[] = ['has_image_size', [has_image_size('1536x1536'), has_image_size('2048x2048'), has_image_size('thumbnail')]];
remove_image_size('zz-probe');
add_image_size('2048x2048', 2048, 2048);
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
