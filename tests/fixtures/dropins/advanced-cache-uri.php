<?php
/**
 * Drop-in probe: the request path a page cache's early file reads, on a server that left REQUEST_URI empty
 * (the reference makes the server variables whole before it loads this file).
 */
fwrite(STDERR, "\nMINN-URI " . json_encode(['uri' => $_SERVER['REQUEST_URI'] ?? null, 'cron' => defined('DOING_CRON')]) . "\n");
