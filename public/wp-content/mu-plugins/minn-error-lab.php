<?php
/**
 * Plugin Name: Minn Error Lab
 * Description: Triggers each class of failure on demand so the engine's error handling can be seen. Inert unless ?minn-error= names a case.
 *
 * Nothing here runs on a normal request: every case is behind an explicit
 * query parameter, so the suites and the marketing site are unaffected.
 *
 *   /?minn-error=fatal      an undefined function, the shape most plugin bugs take
 *   /?minn-error=exception  an uncaught exception from plugin code
 *   /?minn-error=error      an uncaught Error (a TypeError from bad arguments)
 *   /?minn-error=warning    a non-fatal warning; the page still renders
 *   /?minn-error=notice     an undefined array key; the page still renders
 *   /?minn-error=late       a fatal after output has begun, mid-render
 *   /?minn-error=rest       a REST route that throws (see /wp-json/minn-error-lab/v1/boom)
 */

add_action('init', static function (): void {
    $case = isset($_GET['minn-error']) ? (string) $_GET['minn-error'] : '';
    if ($case === '') {
        return;
    }
    switch ($case) {
        case 'fatal':
            /** @phpstan-ignore-next-line deliberately undefined */
            minn_error_lab_no_such_function();
            break;
        case 'exception':
            throw new RuntimeException('Minn Error Lab: an uncaught exception from plugin code.');
        case 'error':
            (static fn (int $n): int => $n)('not an int');
            break;
        case 'warning':
            trigger_error('Minn Error Lab: a non-fatal warning from plugin code.', E_USER_WARNING);
            break;
        case 'notice':
            $empty = [];
            /** @phpstan-ignore-next-line deliberately missing */
            $ignored = $empty['no_such_key'];
            break;
        case 'late':
            add_action('wp_footer', static function (): void {
                /** @phpstan-ignore-next-line deliberately undefined */
                minn_error_lab_late_fatal();
            }, 1);
            break;
    }
});

// A REST route that throws, to show what a failure inside the API looks like.
add_action('rest_api_init', static function (): void {
    register_rest_route('minn-error-lab/v1', '/boom', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => static function () {
            throw new RuntimeException('Minn Error Lab: an exception inside a REST handler.');
        },
    ]);
});
