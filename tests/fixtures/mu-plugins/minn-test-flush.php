<?php
/**
 * Plugin Name: Minn test flush
 * Description: Fixture for the output-flush suite, loaded by the engine and the reference alike. A request carrying X-Minn-Flush as <run>:<mode>, for a run the suite opened (wp-content/minn-flush/<run>.open exists), ends every output buffer mid-page with wp_ob_end_flush_all, as a plugin that sends its page early does: in wp_head (head), in wp_footer (footer), or after opening a buffer of its own in wp_head (own); each step prints a marker comment. Without such a run the header does nothing.
 * License: MIT
 */

[$minnFlushRun, $minnFlushMode] = array_pad(explode(':', (string) ($_SERVER['HTTP_X_MINN_FLUSH'] ?? ''), 2), 2, '');
$minnFlushDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-flush';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnFlushRun) !== 1 || !is_file("{$minnFlushDir}/{$minnFlushRun}.open")) {
    return;
}
$minnFlushNow = static function (string $where): void {
    echo "<!-- zz-flush before {$where} -->\n";
    wp_ob_end_flush_all();
    echo "<!-- zz-flush after {$where} -->\n";
};
if ($minnFlushMode === 'head') {
    add_action('wp_head', static fn () => $minnFlushNow('head'), 1);
}
if ($minnFlushMode === 'footer') {
    add_action('wp_footer', static fn () => $minnFlushNow('footer'), 1);
}
if ($minnFlushMode === 'own') {
    add_action('wp_head', static function (): void {
        ob_start();
        echo "<!-- zz-flush own buffer -->\n";
    }, 1);
    add_action('wp_footer', static fn () => $minnFlushNow('footer'), 1000);
}
