<?php
/**
 * Behaviour probe for a shortcode whose callback is not callable when it is
 * added: a plugin naming a class it declares further down its file
 * (Header Footer Code Manager adds [hfcm] that way). The reference keeps the
 * callback, and only asks whether it can be called when the shortcode runs:
 * until then the shortcode stays as written, with a notice; once the class
 * exists it runs. Same protocol as api-probe.php.
 */
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
$listen = static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
};
add_action('doing_it_wrong_run', $listen, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');

add_shortcode('minn_probe_later', ['Minn_Probe_Later_Shortcode', 'render']);
$say('a callback not callable yet is kept', [shortcode_exists('minn_probe_later'), isset($GLOBALS['shortcode_tags']['minn_probe_later'])]);
$say('run before it can be called: left as written, with a notice', [do_shortcode('a [minn_probe_later x="1"]in[/minn_probe_later] b'), $wrong]);

if (!class_exists('Minn_Probe_Later_Shortcode')) {
    class Minn_Probe_Later_Shortcode
    {
        public static function render($atts, $content = null)
        {
            return 'later:' . ($atts['x'] ?? '') . ':' . $content;
        }
    }
}
$wrong = [];
$say('once the class is declared it runs', [do_shortcode('a [minn_probe_later x="1"]in[/minn_probe_later] b'), $wrong]);

add_shortcode('minn_probe_never', 'minn_probe_no_such_function');
$wrong = [];
$say('a function that never exists', [shortcode_exists('minn_probe_never'), do_shortcode('[minn_probe_never]'), $wrong]);

remove_shortcode('minn_probe_later');
remove_shortcode('minn_probe_never');
remove_action('doing_it_wrong_run', $listen, 10);
remove_filter('doing_it_wrong_trigger_error', '__return_false');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
