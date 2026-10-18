<?php
/**
 * has_filter() and WP_Hook::has_filter() asked about one priority, as the
 * reference answers (probe hook-priority): with a callback and a whole-number
 * priority, whether the callback is hooked at that one; a priority given as
 * a string, or no callback, answers as if none was given. Hooks of its own,
 * removed on the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$h = 'minn_probe_has_priority';
$say('nothing hooked', has_filter($h));
add_filter($h, 'strtolower', 5);
add_filter($h . '_zero', 'strtoupper', 0);
$say('has_filter', [
    'any' => has_filter($h),
    'the callback' => has_filter($h, 'strtolower'),
    'at its priority' => has_filter($h, 'strtolower', 5),
    'at another' => has_filter($h, 'strtolower', 10),
    'priority as a string' => has_filter($h, 'strtolower', '5'),
    'no callback, a priority' => has_filter($h, false, 5),
    'no callback, another priority' => has_filter($h, false, 10),
    'another callback at it' => has_filter($h, 'trim', 5),
    'hooked at 0' => has_filter($h . '_zero', 'strtoupper'),
    'at 0' => has_filter($h . '_zero', 'strtoupper', 0),
    'has_action at its priority' => has_action($h, 'strtolower', 5),
]);
// Hooked at two priorities: each is asked on its own.
add_filter($h, 'strtolower', 20);
$say('hooked at 5 and 20', ['the callback' => has_filter($h, 'strtolower'), 'at 20' => has_filter($h, 'strtolower', 20), 'at 5' => has_filter($h, 'strtolower', 5), 'at 10' => has_filter($h, 'strtolower', 10)]);
$hook = $GLOBALS['wp_filter'][$h];
$say('WP_Hook, hooked at 5 and 20', ['at 20' => $hook->has_filter($h, 'strtolower', 20), 'at 10' => $hook->has_filter($h, 'strtolower', 10)]);
remove_filter($h, 'strtolower', 20);
$say('WP_Hook::has_filter', [
    'at its priority' => $hook->has_filter($h, 'strtolower', 5),
    'at another' => $hook->has_filter($h, 'strtolower', 10),
    'no priority' => $hook->has_filter($h, 'strtolower'),
    'no callback, a priority' => $hook->has_filter($h, false, 5),
]);
remove_all_filters($h);
remove_all_filters($h . '_zero');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
