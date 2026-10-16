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

// A plugin that needs pluggable functions while it loads, before the reference
// defines them (?minn-test-early-pluggable, as Really Simple Security makes its key:
// it requires wp-includes/pluggable.php and calls wp_generate_password), or asks
// who is signed in then (?minn-test-early-user, as All in One SEO does). What it
// heard is noted in the page.
if (isset($_GET['minn-test-early-pluggable']) || isset($_GET['minn-test-early-user'])) {
    $minnTestEarly = [function_exists('wp_generate_password'), get_current_user_id()];
    if (isset($_GET['minn-test-early-pluggable']) && !function_exists('wp_generate_password')) {
        require_once ABSPATH . WPINC . '/pluggable.php';
        $minnTestEarly[] = strlen(wp_generate_password(12, false));
    }
    add_action('wp_footer', static function () use ($minnTestEarly) {
        echo '<!-- minn-test-early ' . json_encode($minnTestEarly) . ' -->';
    });
}

// A comments_template filter that hands back a path that is no file
// (?minn-test-comments-template; Colibri's is relative to its theme): the
// comments template printed in the footer, noted by its length and hash.
if (isset($_GET['minn-test-comments-template'])) {
    add_action('wp_footer', static function () {
        add_filter('comments_template', static fn () => 'template-parts/blog/comments.php', 99);
        ob_start();
        comments_template();
        $printed = (string) ob_get_clean();
        echo '<!-- minn-test-comments-template ' . strlen($printed) . ' ' . md5($printed) . ' -->';
    }, 1);
}

// The comment form in the format a caller asks for (?minn-test-comment-form=xhtml,
// or =novalidate for an unchecked form), as a remembered commenter sees it: noted
// by its length and hash.
if (isset($_GET['minn-test-comment-form'])) {
    add_action('wp_footer', static function () {
        $args = $_GET['minn-test-comment-form'] === 'novalidate' ? ['novalidate' => true] : ['format' => 'xhtml'];
        add_filter('wp_get_current_commenter', static fn () => ['comment_author' => 'Reader', 'comment_author_email' => 'reader@example.com', 'comment_author_url' => '']);
        ob_start();
        comment_form($args, get_queried_object_id());
        $printed = (string) ob_get_clean();
        echo '<!-- minn-test-comment-form ' . strlen($printed) . ' ' . md5($printed) . ' -->';
    }, 1);
}

// Blocks that render nothing (?minn-test-empty-blocks, =keep with
// enqueue_empty_block_content_assets asking for their assets): an empty block,
// one of whitespace and a full one in the post's content, and an empty one
// rendered directly; each enqueues as it renders. What stays queued is noted.
if (isset($_GET['minn-test-empty-blocks'])) {
    add_action('init', static function () {
        foreach (['early', 'content', 'spaced', 'direct', 'full'] as $handle) {
            wp_register_script("minn-test-empty-{$handle}", plugins_url("probe.js?{$handle}", __FILE__), [], '1.0.0');
        }
        wp_register_style('minn-test-empty-style', plugins_url('probe.css?empty', __FILE__), [], '1.0.0');
        register_block_type('minn-test/empty', ['attributes' => ['as' => ['type' => 'string', 'default' => 'content']], 'render_callback' => static function ($attributes) {
            wp_enqueue_script('minn-test-empty-early');
            wp_enqueue_script('minn-test-empty-' . $attributes['as']);
            wp_enqueue_style('minn-test-empty-style');
            return $attributes['as'] === 'spaced' ? " \n " : '';
        }]);
        register_block_type('minn-test/full', ['render_callback' => static function () {
            wp_enqueue_script('minn-test-empty-full');
            return '<p>full</p>';
        }]);
    });
    add_action('wp_enqueue_scripts', static fn () => wp_enqueue_script('minn-test-empty-early'));
    if ($_GET['minn-test-empty-blocks'] === 'keep') {
        add_filter('enqueue_empty_block_content_assets', '__return_true');
    }
    add_filter('the_content', static fn ($content) => $content . '<!-- wp:minn-test/empty /--><!-- wp:minn-test/empty {"as":"spaced"} /--><!-- wp:minn-test/full /-->', 1);
    add_action('wp_footer', static function () {
        render_block(parse_blocks('<!-- wp:minn-test/empty {"as":"direct"} /-->')[0]);
        $queued = [];
        foreach (['early', 'content', 'spaced', 'direct', 'full'] as $handle) {
            $queued[$handle] = wp_script_is("minn-test-empty-{$handle}", 'enqueued');
        }
        $queued['style'] = wp_style_is('minn-test-empty-style', 'enqueued');
        echo '<!-- minn-test-empty-blocks ' . json_encode($queued) . ' -->';
    }, 1);
}

// Content a plugin adds in the_content around core's defaults
// (?minn-test-content-order): block markup at 5, before do_blocks; a shortcode
// at 10, before do_shortcode; another at 20, after it. The post's content is
// noted in the page between markers.
if (isset($_GET['minn-test-content-order'])) {
    add_shortcode('minn_test_order', static fn () => '<span class="minn-test-order">ran</span>');
    add_filter('the_content', static fn ($content) => $content . '<!-- wp:paragraph --><p>added at 5</p><!-- /wp:paragraph -->', 5);
    add_filter('the_content', static fn ($content) => $content . '[minn_test_order]', 10);
    add_filter('the_content', static fn ($content) => '<!--minn-order-->' . $content . '[minn_test_order] at 20<!--/minn-order-->', 20);
}

// The template-enhancement buffer (?minn-test-enhance): the action before the
// template, the buffer opening, and the whole page handed to the filter, noted
// in the page itself.
if (isset($_GET['minn-test-enhance'])) {
    $GLOBALS['minn_test_enhance'] = [];
    add_action('wp_before_include_template', static function (...$args) {
        $GLOBALS['minn_test_enhance'][] = ['before', count($args), basename((string) ($args[0] ?? ''))];
    }, 10, 5);
    add_action('wp_template_enhancement_output_buffer_started', static function () {
        $GLOBALS['minn_test_enhance'][] = ['started'];
    });
    add_filter('wp_template_enhancement_output_buffer', static function ($output, ...$rest) {
        $heard = [...$GLOBALS['minn_test_enhance'], ['filtered', 1 + count($rest), str_starts_with(ltrim((string) $output), '<!DOCTYPE'), str_contains((string) $output, '</html>')]];
        return str_replace('</body>', '<!-- minn-test-enhance ' . json_encode($heard) . ' --></body>', (string) $output);
    }, 10, 5);
}

// Menus read by anyone when asked (?minn-test-menus-open), through rest_menu_read_access.
add_filter('rest_menu_read_access', static function ($open, $request, $controller) {
    return isset($_GET['minn-test-menus-open']) && $request instanceof WP_REST_Request && is_object($controller) ? true : $open;
}, 10, 3);

// A nonce lifetime of the caller's choosing (?minn-test-nonce-life=), through nonce_life: the auth suite signs in under it.
if (isset($_GET['minn-test-nonce-life'])) {
    add_filter('nonce_life', static fn () => (int) $_GET['minn-test-nonce-life']);
}

// A search handler of the plugin's own: /wp/v2/search?type=minn-test.
add_filter('wp_rest_search_handlers', static function (array $handlers): array {
    require_once __DIR__ . '/search-handler.php';
    $handlers[] = new Minn_Test_Search_Handler();
    return $handlers;
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
