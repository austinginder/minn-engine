<?php
/**
 * Printing scripts and styles (probe script-printing): a script tag from its
 * attributes; the head scripts, then the footer's, then styles queued late;
 * the jQuery UI datepicker told the site's dates; wp_enqueue_scripts fired;
 * block styles loaded on demand for a classic theme (a throwaway one the
 * probe makes, then removes) and what that turns on; and late styles hoisted
 * into the head through the template enhancement buffer. The scripts and
 * styles are the probe's own; nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
// The emoji styles print through wp_print_styles on the reference, deprecated; the probe's styles print alone.
remove_action('wp_print_styles', 'print_emoji_styles');

$tag = static function (array $attributes) {
    ob_start();
    wp_print_script_tag($attributes);
    return (string) ob_get_clean();
};
$say('script tag', [
    'a source' => $tag(['src' => 'https://example.com/zz.js']),
    'flags' => $tag(['src' => 'https://example.com/zz.js', 'async' => true, 'defer' => false, 'nomodule' => true]),
    'a module with data' => $tag(['type' => 'module', 'src' => '/zz/mod.js', 'data-zz' => 'a"b', 'id' => 'zz-<id>']),
    'no attributes' => $tag([]),
    'filtered' => (static function () use ($tag) {
        $filter = static fn ($attributes) => $attributes + ['data-filtered' => 'zz'];
        add_filter('wp_script_attributes', $filter);
        $out = $tag(['src' => 'https://example.com/zz.js']);
        remove_filter('wp_script_attributes', $filter);
        return $out;
    })(),
]);

$scripts = wp_scripts();
$ours = static fn ($handles) => array_values(array_filter((array) $handles, static fn ($handle) => str_starts_with((string) $handle, 'zz-')));
$printed = 0;
$counter = static function () use (&$printed) {
    $printed++;
};
add_action('wp_print_scripts', $counter);
wp_register_script('zz-dep', 'https://example.com/zz-dep.js', [], '1.0');
wp_register_script('zz-head', 'https://example.com/zz-head.js', ['zz-dep'], '2.0');
wp_add_inline_script('zz-head', 'var zzBefore = 1;', 'before');
wp_add_inline_script('zz-head', 'var zzAfter = 2;');
wp_localize_script('zz-head', 'zzData', ['a' => 'b<c', 'chars' => '</script> & "q" \'s\' é / \u{2028}', 'n' => 3, 'nested' => ['x' => '<b>']]);
wp_register_script('zz-foot', 'https://example.com/zz-foot.js', ['zz-head'], false, true);
wp_register_script('zz-defer', 'https://example.com/zz-defer.js', [], null, ['strategy' => 'defer', 'in_footer' => true]);
wp_enqueue_script('zz-foot');
wp_enqueue_script('zz-defer');
$run = static function (Closure $print) use ($ours) {
    ob_start();
    $done = $print();
    return ['printed' => (string) ob_get_clean(), 'returned' => is_array($done) ? $ours($done) : $done];
};
$say('head and footer scripts', [
    'the head' => $run(static fn () => print_head_scripts()),
    'the head again' => $run(static fn () => print_head_scripts()),
    'wp_print_scripts fired' => $printed,
    'fired after wp_print_head_scripts' => (static function () use (&$printed) {
        ob_start();
        wp_print_head_scripts();
        ob_end_clean();
        return $printed;
    })(),
    'the footer' => $run(static fn () => print_footer_scripts()),
    'the footer again' => $run(static fn () => print_footer_scripts()),
    'done' => $ours($scripts->done),
]);
remove_action('wp_print_scripts', $counter);

wp_register_style('zz-late-dep', 'https://example.com/zz-late-dep.css', [], '1');
wp_register_style('zz-late', 'https://example.com/zz-late.css', ['zz-late-dep'], '3', 'print');
wp_add_inline_style('zz-late', '.zz{color:red}');
wp_enqueue_style('zz-late');
$say('late styles', [
    'printed' => $run(static fn () => print_late_styles()),
    'again' => $run(static fn () => print_late_styles()),
    'filtered off' => (static function () use ($run) {
        wp_enqueue_style('zz-late-off', 'https://example.com/zz-late-off.css', [], '4');
        add_filter('print_late_styles', '__return_false');
        $out = $run(static fn () => print_late_styles());
        remove_filter('print_late_styles', '__return_false');
        wp_dequeue_style('zz-late-off');
        return $out;
    })(),
]);

// The datepicker's defaults for each date format, the week starting on a Wednesday. The engine ships no jQuery UI, so
// it holds no datepicker handle; one stands in there, as the reference's own does.
if (!isset($scripts->registered['jquery-ui-datepicker'])) {
    wp_register_script('jquery-ui-datepicker', 'https://example.com/zz-datepicker.js', ['jquery-ui-core']);
}
$picker = static function (string $format, bool $queued = true) use ($scripts) {
    $filter = static fn () => $format;
    $week = static fn () => '3';
    add_filter('pre_option_date_format', $filter);
    add_filter('pre_option_start_of_week', $week);
    $scripts->add_data('jquery-ui-datepicker', 'after', []);
    if ($queued) {
        wp_enqueue_script('jquery-ui-datepicker');
    }
    wp_localize_jquery_ui_datepicker();
    $after = $scripts->get_data('jquery-ui-datepicker', 'after');
    $scripts->add_data('jquery-ui-datepicker', 'after', []);
    wp_dequeue_script('jquery-ui-datepicker');
    remove_filter('pre_option_date_format', $filter);
    remove_filter('pre_option_start_of_week', $week);
    return $after;
};
$say('datepicker', [
    'the default format' => $picker('F j, Y'),
    'day first' => $picker('d/m/Y'),
    'every letter' => $picker('D l N j S w z W M F m n t L o Y y'),
    'escaped letters' => $picker('l, jS \\o\\f F Y'),
    'not queued' => $picker('F j, Y', false),
]);

// Block styles on demand: what it adds to the four hooks it touches.
$touched = ['should_load_separate_core_block_assets', 'should_load_block_assets_on_demand', 'wp_should_output_buffer_template_for_enhancement', 'wp_template_enhancement_output_buffer_started'];
$listing = static function () use ($touched) {
    $out = [];
    foreach ($touched as $hook) {
        foreach ([0, 10] as $priority) {
            foreach (['__return_true', 'wp_hoist_late_printed_styles'] as $callback) {
                if (has_filter($hook, $callback) === $priority) {
                    $out[] = "{$hook} {$priority} {$callback}";
                }
            }
        }
    }
    return $out;
};
$onDemand = static function (?string $hook = null, ?string $callback = null) use ($listing) {
    if ($hook !== null) {
        add_filter($hook, $callback, 5);
    }
    $returned = wp_load_classic_theme_block_styles_on_demand();
    $added = $listing();
    if ($hook !== null) {
        remove_filter($hook, $callback, 5);
    }
    foreach ([['should_load_separate_core_block_assets', '__return_true', 0], ['should_load_block_assets_on_demand', '__return_true', 0], ['wp_should_output_buffer_template_for_enhancement', '__return_true', 0], ['wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles', 10]] as [$name, $function, $priority]) {
        remove_filter($name, $function, $priority);
    }
    return [$returned, $added];
};
$demand = [
    'hooked' => has_action('wp_default_styles', 'wp_load_classic_theme_block_styles_on_demand'),
    'before' => $listing(),
    'a block theme' => $onDemand(),
];
$themes = get_theme_root();
$classic = $themes . '/zz-classic-probe';
register_shutdown_function(static function () use ($classic): void {
    foreach (['style.css', 'index.php'] as $file) {
        if (is_file("{$classic}/{$file}")) {
            unlink("{$classic}/{$file}");
        }
    }
    if (is_dir($classic)) {
        rmdir($classic);
    }
});
wp_mkdir_p($classic);
file_put_contents("{$classic}/style.css", "/*\nTheme Name: ZZ Classic Probe\n*/\n");
file_put_contents("{$classic}/index.php", "<?php\n");
search_theme_directories(true);
$theme = static fn () => 'zz-classic-probe';
add_filter('pre_option_template', $theme);
add_filter('pre_option_stylesheet', $theme);
$demand['the classic theme'] = [get_stylesheet(), wp_is_block_theme()];
$demand['a classic theme'] = $onDemand();
$demand['buffering filtered off'] = $onDemand('wp_should_output_buffer_template_for_enhancement', '__return_false');
$demand['a REST request'] = $onDemand('wp_is_rest_endpoint', '__return_true');
$demand['separate assets filtered off'] = $onDemand('should_load_separate_core_block_assets', '__return_false');
$demand['on demand filtered off'] = $onDemand('should_load_block_assets_on_demand', '__return_false');
remove_filter('pre_option_template', $theme);
remove_filter('pre_option_stylesheet', $theme);
$demand['the theme after'] = get_stylesheet() !== 'zz-classic-probe';
$say('block styles on demand', $demand);

// Late styles hoisted: the footer prints its scripts alone, and the buffer puts the late styles before the head closes.
$hoisted = [];
$page = static fn (string $head, string $body) => "<!DOCTYPE html>\n<html><head>\n<title>zz</title>\n{$head}</head>\n<body>\n{$body}</body>\n</html>\n";
$buffer = static fn (string $html) => apply_filters('wp_template_enhancement_output_buffer', $html, $html);
wp_enqueue_script('zz-hoist-foot', 'https://example.com/zz-hoist-foot.js', [], '1', true);
wp_hoist_late_printed_styles();
$hoisted['the buffer, before the footer'] = $buffer($page('', '<p>zz</p>'));
wp_enqueue_style('zz-hoisted', 'https://example.com/zz-hoisted.css', [], '5');
wp_add_inline_style('zz-hoisted', '.zz-hoisted{margin:0}');
ob_start();
do_action('wp_print_footer_scripts');
$footer = (string) ob_get_clean();
$hoisted['the footer'] = $footer;
$hoisted['a page'] = $buffer($page("<link rel='stylesheet' id='zz-first-css' href='https://example.com/zz-first.css' />\n", "<p>zz</p>\n{$footer}"));
$hoisted['upper case'] = $buffer("<HTML><HEAD><TITLE>zz</TITLE></HEAD><BODY>zz</BODY></HTML>");
$hoisted['a closer in a script'] = $buffer("<html><head><script>var zz = '</head>';</script></head><body>zz</body></html>");
$hoisted['a closer in a comment'] = $buffer("<html><head><!-- </head> --><title>zz</title></head><body>zz</body></html>");
$hoisted['no head closer'] = $buffer("<html><head><title>zz</title><body><p>zz</p></body></html>");
$hoisted['no head at all'] = $buffer("<p>zz</p>");
$say('late styles hoisted', $hoisted);

// Whether this is a REST request: not here, yes inside a dispatch, and as filtered.
$say('rest endpoint', (static function () {
    $heard = [];
    $listener = static function ($value) use (&$heard) {
        $heard[] = $value;
        return $value;
    };
    add_filter('wp_is_rest_endpoint', $listener);
    $plain = wp_is_rest_endpoint();
    $inside = null;
    $peek = static function ($result) use (&$inside) {
        $inside = wp_is_rest_endpoint();
        return $result;
    };
    add_filter('rest_pre_dispatch', $peek);
    rest_do_request(new WP_REST_Request('GET', '/'));
    remove_filter('rest_pre_dispatch', $peek);
    remove_filter('wp_is_rest_endpoint', $listener);
    add_filter('wp_is_rest_endpoint', '__return_true');
    $filtered = wp_is_rest_endpoint();
    remove_filter('wp_is_rest_endpoint', '__return_true');
    return ['here' => $plain, 'inside a dispatch' => $inside, 'heard' => $heard, 'filtered' => $filtered, 'after' => wp_is_rest_endpoint()];
})());

// Last, as what the theme and core hang on it queue would reach the sections after it.
$say('enqueue scripts fired', (static function () {
    $heard = [];
    $listener = static function (...$args) use (&$heard) {
        $heard[] = $args;
    };
    add_action('wp_enqueue_scripts', $listener, 99999, 3);
    wp_enqueue_scripts();
    remove_action('wp_enqueue_scripts', $listener, 99999);
    return $heard;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
