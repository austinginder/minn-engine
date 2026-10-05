<?php
/** Drop-in probe for a page request: reports, at shutdown, what existed when the page cache's early file loaded and whether its top-level variable became a global. */
$minn_probe_top_level = 'set at the top of the file';
$GLOBALS['minn_probe_advanced_cache'] = [
    'add_action' => function_exists('add_action'),
    'get_option' => function_exists('get_option'),
    'wpdb' => isset($GLOBALS['wpdb']),
    'object cache' => isset($GLOBALS['wp_object_cache']),
];
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'MINN-PROBE ' . json_encode($GLOBALS['minn_probe_advanced_cache'] + ['top-level variable is global' => isset($GLOBALS['minn_probe_top_level'])]) . "\n");
});
