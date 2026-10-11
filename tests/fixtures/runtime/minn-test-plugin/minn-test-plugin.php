<?php
/**
 * Plugin Name: Minn runtime probe
 * Description: A WordPress plugin, unchanged, that marks the page from every lifecycle seam the engine fires. The runtime suite switches it on and reads the marks.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MINN_TEST_PLUGIN_FILE', __FILE__);
$GLOBALS['minn_test_plugin_order'] = [];

function minn_test_plugin_mark($hook)
{
    $GLOBALS['minn_test_plugin_order'][] = $hook;
}

foreach (['plugins_loaded', 'init', 'wp_loaded', 'template_redirect'] as $hook) {
    add_action($hook, static function () use ($hook) {
        minn_test_plugin_mark($hook);
    });
}

add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('minn-test-plugin', plugins_url('probe.css', MINN_TEST_PLUGIN_FILE), [], '1.0.0');
    wp_enqueue_script('minn-test-plugin', plugins_url('probe.js', MINN_TEST_PLUGIN_FILE), [], '1.0.0', true);
    wp_localize_script('minn-test-plugin', 'minnTestPlugin', ['home' => home_url('/'), 'admin' => is_admin()]);
});

add_action('wp_head', static function () {
    minn_test_plugin_mark('wp_head');
    echo '<meta name="minn-test-plugin" content="' . esc_attr(implode(',', $GLOBALS['minn_test_plugin_order'])) . '">' . "\n";
}, 5);

add_filter('body_class', static function ($classes) {
    $classes[] = 'minn-test-plugin-body';
    return $classes;
});

add_filter('the_content', static function ($content) {
    return $content . '<p class="minn-test-plugin-content">' . esc_html(get_option('blogname')) . '</p>';
}, 20);

add_shortcode('minn_test', static function ($atts) {
    $atts = shortcode_atts(['word' => 'none'], $atts, 'minn_test');
    return '<span class="minn-test-shortcode">' . esc_html($atts['word']) . '</span>';
});

add_action('wp_footer', static function () {
    echo '<!-- minn-test-plugin footer: ' . esc_html(wp_get_theme()->get('Name')) . ' -->' . "\n";
});

// A REST route of its own, answered on both stacks from the same code.
add_action('rest_api_init', static function () {
    register_rest_route('minn-test/v1', '/echo', [
        [
            'methods' => 'GET',
            'callback' => static fn (WP_REST_Request $request) => ['echo' => $request->get_param('word'), 'n' => $request->get_param('n'), 'user' => get_current_user_id(), 'title' => get_the_title(1)],
            'permission_callback' => '__return_true',
            'args' => ['word' => ['required' => true, 'type' => 'string'], 'n' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 5]],
        ],
        [
            'methods' => 'POST',
            'callback' => static fn (WP_REST_Request $request) => new WP_REST_Response(['made' => $request->get_param('word')], 201),
            'permission_callback' => static fn () => current_user_can('edit_posts'),
        ],
    ]);
    register_rest_route('minn-test/v1', '/items/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => static fn (WP_REST_Request $request) => rest_ensure_response(['id' => (int) $request['id'], 'exists' => get_post((int) $request['id']) !== null]),
        'permission_callback' => '__return_true',
    ]);
    // Deprecated calls inside a REST request, one case per request (?case=): under WP_DEBUG the reference reports them in response headers.
    register_rest_route('minn-test/v1', '/deprecated', [
        'methods' => 'GET',
        'callback' => static function (WP_REST_Request $request) {
            $cases = [
                'function' => static fn () => _deprecated_function('minn_test_old', '1.2.3', 'minn_test_new'),
                'function-bare' => static fn () => _deprecated_function('minn_test_gone', '1.2.4'),
                'argument' => static fn () => _deprecated_argument('minn_test_fn', '2.0.0', 'The zz argument is gone.'),
                'argument-bare' => static fn () => _deprecated_argument('minn_test_fn_quiet', '2.0.1'),
                'wrong' => static fn () => _doing_it_wrong('minn_test_fn', 'Call <code>it</code> later.', '3.0.0'),
                'wrong-bare' => static fn () => _doing_it_wrong('minn_test_fn_unversioned', 'No version.', ''),
                'all' => static function () {
                    _deprecated_function('minn_test_first', '1.0.0');
                    _deprecated_function('minn_test_second', '1.0.1', 'minn_test_third');
                    _deprecated_argument('minn_test_a', '1.0.0', 'First.');
                    _doing_it_wrong('minn_test_b', 'First.', '1.0.0');
                    _doing_it_wrong('minn_test_c', 'Second.', '1.0.1');
                },
            ];
            $case = (string) $request->get_param('case');
            if (isset($cases[$case])) {
                $cases[$case]();
            }
            return ['case' => $case];
        },
        'permission_callback' => '__return_true',
    ]);
    // A string cut mid-character: the reference still answers with the data, the bad byte as "?".
    register_rest_route('minn-test/v1', '/cut', [
        'methods' => 'GET',
        'callback' => static fn () => ['title' => substr('Café au lait', 0, 4), 'url' => 'https://example.com/a'],
        'permission_callback' => '__return_true',
    ]);
});

// A routed page of its own, the CaptainCore Manager shape: a rewrite rule
// maps the path onto plugin query vars and template_include takes the
// response over when the flag var is present.
add_action('init', static function () {
    add_rewrite_rule('^minn-test-app/?$', 'index.php?minn_test_app=1', 'top');
    add_rewrite_rule('^minn-test-app/(.+?)/?$', 'index.php?minn_test_app=1&minn_test_route=$matches[1]', 'top');
});

add_filter('query_vars', static function ($vars) {
    $vars[] = 'minn_test_app';
    $vars[] = 'minn_test_route';
    return $vars;
});

add_filter('template_include', static function ($template) {
    if (get_query_var('minn_test_app')) {
        return __DIR__ . '/app-template.php';
    }
    return $template;
});

// The functions that only send headers, each called by name on a request of its own (?minn-test-headers=).
add_action('init', static function () {
    $which = isset($_GET['minn-test-headers']) ? (string) $_GET['minn-test-headers'] : '';
    $send = [
        'nosniff' => static fn () => send_nosniff_header(),
        'frame' => static fn () => send_frame_options_header(),
        'javascript' => static fn () => cache_javascript_headers(),
        'link' => static fn () => rest_output_link_header(),
    ];
    if (isset($send[$which])) {
        $send[$which]();
        echo 'minn-test-headers ' . $which;
        exit;
    }
}, 999);
