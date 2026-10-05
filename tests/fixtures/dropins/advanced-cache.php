<?php
/** Drop-in probe: a page cache's early file. It records what exists when it loads, and serves nothing. */

$GLOBALS['minn_probe_advanced_cache'] = [
    'add_action' => function_exists('add_action'),
    'get_option' => function_exists('get_option'),
    'wpdb' => isset($GLOBALS['wpdb']),
    'object cache' => isset($GLOBALS['wp_object_cache']),
    'muplugins_loaded' => function_exists('did_action') ? did_action('muplugins_loaded') : null,
];
