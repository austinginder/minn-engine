<?php
/**
 * Behaviour probe for the small functions most plugins call at load time
 * and on the front end. Runs unchanged on the reference (wp eval-file) and
 * on the engine's facade (tests/api.test.php); prints one JSON transcript.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$out = static function (callable $fn): string { ob_start(); $fn(); return (string) ob_get_clean(); };
// The dev environment's helper mu-plugin rewrites URLs; take it out of both stacks.
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}

// Options.
delete_option('minn_probe_arr');
delete_option('minn_probe_str');
$say('get missing', get_option('minn_probe_missing'));
$say('get missing default', get_option('minn_probe_missing', 'dflt'));
$say('add array', add_option('minn_probe_arr', ['a' => 1, 'b' => [true, null, 1.5, 's', 'k' => 'v']]));
$say('add existing', add_option('minn_probe_arr', 'x'));
$say('get array', get_option('minn_probe_arr'));
$say('update same', update_option('minn_probe_arr', ['a' => 1, 'b' => [true, null, 1.5, 's', 'k' => 'v']]));
$say('update new', update_option('minn_probe_arr', ['a' => 2]));
$say('get updated', get_option('minn_probe_arr'));
$say('update creates', update_option('minn_probe_str', 5));
$say('get int stored', get_option('minn_probe_str'));
update_option('minn_probe_str', false);
$say('get false stored', get_option('minn_probe_str'));
update_option('minn_probe_str', true);
$say('get true stored', get_option('minn_probe_str'));
update_option('minn_probe_str', null);
$say('get null stored', get_option('minn_probe_str'));
update_option('minn_probe_str', 'yes');
$say('get yes', get_option('minn_probe_str'));
update_option('minn_probe_str', ' padded ');
$say('get padded', get_option('minn_probe_str'));
$say('delete', delete_option('minn_probe_str'));
$say('delete again', delete_option('minn_probe_str'));
$say('delete arr', delete_option('minn_probe_arr'));
add_filter('pre_option_minn_probe_pre', static fn ($pre) => 'short');
$say('pre_option', get_option('minn_probe_pre'));
add_filter('option_blogname', static fn ($v) => $v . '!');
$say('option_ filter', get_option('blogname') === get_option('blogname') && str_ends_with(get_option('blogname'), '!'));
remove_all_filters('option_blogname');
add_filter('default_option_minn_probe_dflt', static fn ($d) => 'fromfilter');
$say('default_option filter', get_option('minn_probe_dflt'));
$say('default_option filter with default', get_option('minn_probe_dflt', 'given'));
$say('siteurl trailing', get_option('siteurl'));
$say('home', get_option('home'));

// Transients.
delete_transient('minn_probe_t');
$say('transient missing', get_transient('minn_probe_t'));
$say('set transient', set_transient('minn_probe_t', ['x' => 1], 60));
$say('get transient', get_transient('minn_probe_t'));
$say('set transient same', set_transient('minn_probe_t', ['x' => 1], 60));
$say('transient rows', [get_option('_transient_minn_probe_t'), is_int(get_option('_transient_timeout_minn_probe_t')) || ctype_digit((string) get_option('_transient_timeout_minn_probe_t'))]);
$say('delete transient', delete_transient('minn_probe_t'));
$say('delete transient again', delete_transient('minn_probe_t'));
$say('set transient no expiry', set_transient('minn_probe_t2', 'v'));
$say('no-expiry timeout row', get_option('_transient_timeout_minn_probe_t2'));
delete_transient('minn_probe_t2');
set_transient('minn_probe_t3', 'old', -1);
$say('expired transient', get_transient('minn_probe_t3'));
$say('expired transient row gone', get_option('_transient_minn_probe_t3'));

// Environment predicates.
$say('is_admin', is_admin());
$say('wp_doing_ajax', wp_doing_ajax());
$say('wp_doing_cron', wp_doing_cron());
$say('is_multisite', is_multisite());
$say('wp_installing', wp_installing());
$say('is_customize_preview', is_customize_preview());
$say('wp_is_block_theme', wp_is_block_theme());
$say('is_rtl', is_rtl());
$say('is_ssl', is_ssl());
$say('wp_version', $GLOBALS['wp_version']);
$say('constants', [WPINC, basename(WP_CONTENT_DIR), basename(WP_PLUGIN_DIR), basename(WPMU_PLUGIN_DIR), WP_CONTENT_URL, WP_PLUGIN_URL, basename(WP_LANG_DIR), defined('WP_DEBUG'), MINUTE_IN_SECONDS, HOUR_IN_SECONDS, DAY_IN_SECONDS, WEEK_IN_SECONDS, MONTH_IN_SECONDS, YEAR_IN_SECONDS, OBJECT, ARRAY_A, ARRAY_N, OBJECT_K, EP_PERMALINK, EP_ALL]);

// Plugin paths.
$file = WP_PLUGIN_DIR . '/foo/foo.php';
$say('plugin_basename', plugin_basename($file));
$say('plugin_basename mu', plugin_basename(WPMU_PLUGIN_DIR . '/bar.php'));
$say('plugin_basename backslash', plugin_basename(str_replace('/', '\\', WP_PLUGIN_DIR) . '\\baz\\baz.php'));
$say('plugin_basename elsewhere', plugin_basename('/tmp/else/x.php'));
$say('plugin_dir_path', str_replace(WP_PLUGIN_DIR, '{PLUGINS}', plugin_dir_path($file)));
$say('plugin_dir_url', plugin_dir_url($file));
$say('plugins_url empty', plugins_url());
$say('plugins_url rel', plugins_url('foo/x.css'));
$say('plugins_url slash', plugins_url('/foo/x.css'));
$say('plugins_url with plugin', plugins_url('x.css', $file));
$say('plugins_url with plugin slash', plugins_url('/css/x.css', $file));
$say('plugins_url absolute', plugins_url('https://cdn.example/x.js'));
$say('plugins_url mu', plugins_url('y.js', WPMU_PLUGIN_DIR . '/bar.php'));
$say('plugins_url mu dir', plugins_url('', WPMU_PLUGIN_DIR . '/bar.php'));

// i18n (English passthrough).
$say('__', __('Hello'));
$say('__ domain', __('Hello', 'nope'));
$say('_e', $out(static fn () => _e('Hi <b>')));
$say('_x', _x('Post', 'noun'));
$say('_n 1', _n('%s item', '%s items', 1));
$say('_n 2', _n('%s item', '%s items', 2));
$say('_nx', _nx('%s a', '%s b', 0, 'ctx'));
$say('esc_html__', esc_html__('<b>&amp; & "q"'));
$say('esc_attr__', esc_attr__('<b>&amp; & "q" \'s'));
$say('esc_html_e', $out(static fn () => esc_html_e('<i>')));
$say('esc_attr_e', $out(static fn () => esc_attr_e('"')));
$say('esc_html_x', esc_html_x('<i>', 'ctx'));
$say('load_plugin_textdomain', load_plugin_textdomain('minn-probe', false, 'minn-probe/languages'));
$say('is_textdomain_loaded', is_textdomain_loaded('minn-probe'));
$say('get_locale', get_locale());
$say('determine_locale', determine_locale());
$say('translate string type', gettype(__('x')));

// Escaping and formatting.
$in = '<a href="x">T&amp;C & &#039; "q" \'s &copy; &#8217; &bogus; é</a>';
$say('esc_html', esc_html($in));
$say('esc_attr', esc_attr($in));
$say('esc_textarea', esc_textarea($in));
$say('esc_js', esc_js("a'b\"c\nd\\e</script>"));
$say('esc_html empty', esc_html(''));
$say('esc_html entity normalization', array_map(static fn (string $c): array => [esc_html($c), esc_attr($c)], ['&#36;16', '&#0036;', '&#x24;', '&#X24;', '&hellip;', '&bogus;', '&#999999999;', 'a&#36;b&amp;c<d']));
// A handle counts as enqueued when it only rides in as another handle's
// dependency, however deep: plugin code gates inline data on exactly this
// (WooCommerce attaches wcSettings only when it finds wc-settings enqueued,
// and nothing ever queues that handle by name).
$say('wp_script_is enqueued through deps', (static function (): array {
    wp_register_script('minn-probe-leaf', '/leaf.js', [], '1');
    wp_register_script('minn-probe-mid', '/mid.js', ['minn-probe-leaf'], '1');
    wp_register_script('minn-probe-top', '/top.js', ['minn-probe-mid'], '1');
    $before = wp_script_is('minn-probe-leaf', 'enqueued');
    wp_enqueue_script('minn-probe-top');
    $out = [$before, wp_script_is('minn-probe-leaf', 'enqueued'), wp_script_is('minn-probe-mid', 'enqueued'), wp_script_is('minn-probe-top', 'enqueued'), wp_script_is('minn-probe-nope', 'enqueued')];
    wp_dequeue_script('minn-probe-top');
    foreach (['minn-probe-leaf', 'minn-probe-mid', 'minn-probe-top'] as $h) {
        wp_deregister_script($h);
    }
    return $out;
})());
// The footer printer hangs off the wp_print_footer_scripts ACTION, so a
// plugin can hook that action ahead of it to add inline data. Printing
// straight from wp_footer would run before every such callback.
$say('footer script hook wiring', (static function (): array {
    global $wp_filter;
    $names = static function (string $hook) use ($wp_filter): array {
        $out = [];
        foreach (($wp_filter[$hook] ?? []) as $priority => $cbs) {
            foreach ($cbs as $cb) {
                if (is_string($cb['function'])) {
                    $out[] = $priority . ':' . $cb['function'];
                }
            }
        }
        return $out;
    };
    return [
        in_array('20:wp_print_footer_scripts', $names('wp_footer'), true),
        in_array('10:_wp_footer_scripts', $names('wp_print_footer_scripts'), true),
    ];
})());
$say('WP_Scripts::print_translations', [wp_scripts()->print_translations('wp-i18n', false), wp_scripts()->print_translations('minn-probe-nope', false)]);
// A dependency list is an ARRAY of handles; anything else is discarded
// rather than cast. Casting '' would register a dependency on the empty
// string, which nothing satisfies, so the asset silently stops printing
// (WooCommerce registers woocommerce-general with deps => '').
$say('dependency list normalization', (static function (): array {
    wp_register_style('minn-probe-dep-base', '/base.css', [], '1');
    wp_register_style('minn-probe-dep-empty', '/e.css', '', '1');
    wp_register_style('minn-probe-dep-scalar', '/s.css', 'minn-probe-dep-base', '1');
    wp_register_style('minn-probe-dep-null', '/n.css', null, '1');
    wp_register_style('minn-probe-dep-false', '/f.css', false, '1');
    wp_register_style('minn-probe-dep-array', '/a.css', ['minn-probe-dep-base'], '1');
    $styles = wp_styles();
    $deps = static fn (string $h): array => (array) ($styles->registered[$h]->deps ?? ['MISSING']);
    wp_enqueue_style('minn-probe-dep-empty');
    ob_start();
    wp_print_styles(['minn-probe-dep-empty']);
    $printed = str_contains((string) ob_get_clean(), 'minn-probe-dep-empty');
    $out = [
        $deps('minn-probe-dep-empty'),
        $deps('minn-probe-dep-scalar'),
        $deps('minn-probe-dep-null'),
        $deps('minn-probe-dep-false'),
        $deps('minn-probe-dep-array'),
        $printed,
    ];
    foreach (['base', 'empty', 'scalar', 'null', 'false', 'array'] as $suffix) {
        wp_deregister_style('minn-probe-dep-' . $suffix);
    }
    return $out;
})());
// Plugins hang block-theme stylesheets off enqueue_block_assets, which
// wp_common_block_scripts_and_styles fires from wp_enqueue_scripts.
$say('enqueue_block_assets fires', (static function (): array {
    $seen = 0;
    $mark = static function () use (&$seen): void {
        $seen++;
    };
    add_action('enqueue_block_assets', $mark);
    wp_common_block_scripts_and_styles();
    remove_action('enqueue_block_assets', $mark);
    return [$seen, has_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles') !== false];
})());
$say('esc_html number', esc_html(5));
$say('esc_html null', esc_html(null));
foreach (['http://x.com/?a=1&b=2', 'http://x.com/?a=1&amp;b=2', ' javascript:alert(1)', 'JaVaScRiPt:x', 'x.com', 'x.com/path', '/path?x=1&y=2', 'mailto:a@b.c', 'http://x.com/a b', '', 'data:text/html,x', 'http://x.com/"onclick="x', "http://x.com/\x00nul", '#anchor', '?q=1', 'ftp://x/y', 'tel:123', 'http://[::1]/x', '//cdn.example/x.js', 'http://x.com/%20a%2Fb', 'http://x.com/é', 'https://x.com/a?b=c&d=e#f', 'http://x.com/a\'b', 'http://x.com/a<b>', 'foo:bar', 'http://x.com:8080/y', 'http:/x'] as $u) {
    $say("esc_url {$u}", esc_url($u));
    $say("esc_url_raw {$u}", esc_url_raw($u));
}
$say('esc_url protocols', esc_url('ftp://x/y', ['http', 'https']));
$say('esc_url_raw protocols', esc_url_raw('ftp://x/y', ['http']));
$say('sanitize_text_field', sanitize_text_field(" <b>x</b>\n y  %41 %zz \t z  <script>a</script> &amp; < > \x00 "));
$say('sanitize_text_field lt', sanitize_text_field('a < b and c > d <x'));
$say('sanitize_textarea_field', sanitize_textarea_field(" <b>x</b>\n y \r\n z  "));
$say('wp_strip_all_tags', wp_strip_all_tags("<p>a</p><script>bad</script><style>s</style>\n b <br/>c"));
$say('wp_strip_all_tags breaks', wp_strip_all_tags("<p>a</p>\n\n b\t c", true));
$say('trailingslashit', [trailingslashit('a'), trailingslashit('a/'), trailingslashit('a\\'), trailingslashit('a//'), trailingslashit('')]);
$say('untrailingslashit', [untrailingslashit('a/'), untrailingslashit('a//\\/'), untrailingslashit('a'), untrailingslashit('/')]);
$say('wp_parse_args string', wp_parse_args('a=1&b[]=2&b[]=3&c=', ['c' => 3, 'd' => 4]));
$say('wp_parse_args array', wp_parse_args(['a' => 1], ['a' => 0, 'b' => 2]));
$say('wp_parse_args object', wp_parse_args((object) ['a' => 1], ['b' => 2]));
$say('wp_parse_args empty', wp_parse_args('', ['b' => 2]));
$say('wp_json_encode', wp_json_encode(['a' => 'é', 'b' => '/', 'c' => "bad\xB1"]));
$say('wp_json_encode flags', wp_json_encode(['a' => '/'], JSON_UNESCAPED_SLASHES));
$say('absint', [absint('-5'), absint('3.7'), absint('x'), absint(-2.9), absint(null)]);
$say('sanitize_key', sanitize_key('Hello World_1-x!Ä'));
$say('sanitize_html_class', [sanitize_html_class('Hello World_1-x!Ä'), sanitize_html_class('', 'fallback'), sanitize_html_class('%2F')]);
$say('sanitize_title', [sanitize_title('Hello World! é ñ -- x'), sanitize_title('  '), sanitize_title('', 'fallback'), sanitize_title('Ünicode & <b>tags</b> 100%')]);
$say('sanitize_title_with_dashes', sanitize_title_with_dashes('A B_c.d\'e"f&g'));
$say('sanitize_file_name', sanitize_file_name('my file (1)?.tar.gz'));
$say('sanitize_email', [sanitize_email(' A.b+c@Ex-ample.com '), sanitize_email('bad@@x'), sanitize_email('<a@b.co>')]);
$say('is_email', [is_email('a@b.co'), is_email('a@b'), is_email('a b@c.co')]);
$say('wp_unslash', wp_unslash(['a\\\'b', ['c\\"d']]));
$say('wp_slash', wp_slash(['a\'b', ['c"d']]));
$say('stripslashes_deep', stripslashes_deep(['a\\\'b']));
$say('wp_kses odd allowed', wp_kses('<b>x</b> a > b <i class="c">y</i>', ["'", '"']));
$say('wp_kses allowed', wp_kses('<b class="c" onclick="x">x</b><a href="javascript:x" title="t">l</a><u>u</u><p>p</p>', ['b' => ['class' => true], 'a' => ['href' => true, 'title' => true], 'p' => []]));
$say('wp_kses allowed strings', wp_kses('<b>x</b><i>y</i>', ['b' => []]));
$say('wp_kses_post', wp_kses_post('<p onclick="x" class="c" style="color:red;behavior:url(x)">t</p><script>s</script><a href="javascript:x" href2="y">l</a><img src="x.png" alt="a" onerror="e" /><iframe src="x"></iframe><svg><g/></svg>'));
$say('wp_kses_data', wp_kses_data('<p>t</p><b>b</b><a href="http://x">l</a><img src="x">'));
$say('wp_kses_allowed_html post keys', count(wp_kses_allowed_html('post')));
$say('wp_kses_allowed_html post a', wp_kses_allowed_html('post')['a']);
$say('wp_kses_allowed_html data keys', array_keys(wp_kses_allowed_html('data')));
$say('wp_kses_allowed_html strip', wp_kses_allowed_html('strip'));
$say('wp_kses_allowed_html entities', wp_kses_allowed_html('entities'));
$say('wp_kses_allowed_html custom', wp_kses_allowed_html(['x' => []]));
$say('wp_filter_nohtml_kses', wp_filter_nohtml_kses('<b>x</b> & y'));
$say('wp_kses_no_null', wp_kses_no_null("a\x00b\\0c"));
$say('wp_kses_normalize_entities', wp_kses_normalize_entities('&amp; & &bogus; &#65; &#x41; &#999999999;'));
$say('wp_kses_bad_protocol', [wp_kses_bad_protocol('javascript:alert(1)', ['http']), wp_kses_bad_protocol('http://x', ['http']), wp_kses_bad_protocol('  jav&#x61;script:x', ['http'])]);
$say('wp_kses_allowed_protocols', wp_allowed_protocols());
$say('wp_kses_split', wp_kses_split('<b>x</b><script>y</script>', ['b' => []], []));
$say('wp_kses hair', wp_kses('<b>x</b>hair', ['b' => []]));

// Links and site info.
$say('home_url', [home_url(), home_url('/x'), home_url('x'), home_url('/x/?a=1'), home_url('/x', 'https'), home_url('/x', 'http'), home_url('/x', 'relative'), home_url('', 'rest')]);
$say('site_url', [site_url(), site_url('/x'), site_url('x', 'relative')]);
$say('admin_url', [admin_url(), admin_url('options.php'), admin_url('/edit.php?post_type=page'), admin_url('', 'relative')]);
$say('content_url', [content_url(), content_url('x'), content_url('/x')]);
$say('includes_url', [includes_url(), includes_url('js/x.js')]);
$say('wp_login_url', [wp_login_url(), wp_login_url('https://x/back')]);
$say('wp_logout_url shape', preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce=N', wp_logout_url())); 
$say('get_rest_url', [get_rest_url(), get_rest_url(null, '/wp/v2/posts'), rest_url('wp/v2/posts')]);
$say('get_stylesheet_directory_uri', get_stylesheet_directory_uri());
$say('get_template_directory_uri', get_template_directory_uri());
$say('get_stylesheet_directory', basename(get_stylesheet_directory()));
$say('get_theme_root_uri', get_theme_root_uri());
$say('get_stylesheet', get_stylesheet());
$say('get_template', get_template());
foreach (['name', 'description', 'url', 'wpurl', 'version', 'charset', 'language', 'stylesheet_url', 'stylesheet_directory', 'template_url', 'template_directory', 'admin_email', 'html_type', 'pingback_url', 'rss2_url', 'rss_url', 'atom_url', 'comments_rss2_url', 'comments_atom_url', 'text_direction', 'siteurl', 'home', 'bogus', ''] as $show) {
    $say("get_bloginfo {$show}", get_bloginfo($show));
}
$say('get_bloginfo display', get_bloginfo('name', 'display'));
$say('bloginfo', $out(static fn () => bloginfo('name')));
$say('wp_customize_url', wp_customize_url());
$say('wp_upload_dir keys', array_keys(wp_upload_dir()));
$say('wp_upload_dir', [basename(wp_upload_dir()['basedir']), wp_upload_dir()['baseurl'], wp_upload_dir()['subdir'] === '/' . gmdate('Y') . '/' . gmdate('m') || wp_upload_dir()['subdir'] === '', wp_upload_dir()['error']]);
$say('wp_get_upload_dir', wp_get_upload_dir()['baseurl']);
$say('get_admin_url', get_admin_url(null, 'x.php'));
$say('network_home_url', network_home_url());
$say('self_admin_url', self_admin_url('a.php'));
$say('set_url_scheme', [set_url_scheme('http://x.com/a', 'https'), set_url_scheme('https://x.com/a', 'http'), set_url_scheme('//x.com/a', 'relative'), set_url_scheme('http://x.com/a?b', 'relative')]);
$say('wp_normalize_path', wp_normalize_path('C:\\a\\\\b/../c//d'));
$say('add_query_arg', [add_query_arg('a', '1', 'http://x/?b=2'), add_query_arg(['a' => '1', 'b' => false], 'http://x/?b=2&c=3#h'), add_query_arg('a', 'x y&z', '/p'), add_query_arg(['a' => ['k' => 'v']], '/p'), add_query_arg('a', '1', 'http://x/p?a=0')]);
$say('remove_query_arg', [remove_query_arg('b', 'http://x/?a=1&b=2'), remove_query_arg(['a', 'b'], 'http://x/?a=1&b=2&c=3')]);

// Users (anonymous in both stacks here).
$say('is_user_logged_in', is_user_logged_in());
$say('get_current_user_id', get_current_user_id());
$say('current_user_can', [current_user_can('manage_options'), current_user_can('read'), current_user_can('exist')]);
$say('wp_get_current_user', [wp_get_current_user()->ID, wp_get_current_user()->exists(), wp_get_current_user()->user_login, get_class(wp_get_current_user())]);
$say('user_can', [user_can(1, 'manage_options'), user_can(0, 'read'), user_can(2, 'manage_options'), user_can(2, 'edit_others_posts')]);
$say('get_userdata', [get_userdata(1)->user_login, get_userdata(1)->ID, get_userdata(999), get_userdata(1)->roles, get_userdata(2)->display_name, get_userdata(1)->has_cap('manage_options'), get_userdata(2)->has_cap('manage_options')]);
$say('get_user_by', [get_user_by('login', 'admin')->ID, get_user_by('id', 2)->user_login, get_user_by('email', get_userdata(1)->user_email)->ID, get_user_by('slug', 'admin')->ID, get_user_by('login', 'nobody')]);
$say('WP_User props', array_values(array_intersect(array_keys(get_object_vars(get_userdata(1))), ['ID', 'caps', 'cap_key', 'roles', 'allcaps', 'filter', 'data'])));
$say('WP_User data keys', array_keys(get_object_vars(get_userdata(1)->data)));

// Shortcodes.
add_shortcode('minn_probe', static fn ($atts, $content = null, $tag = '') => '{' . json_encode($atts) . '|' . $content . '|' . $tag . '}');
$say('shortcode_exists', [shortcode_exists('minn_probe'), shortcode_exists('nope')]);
$say('do_shortcode', do_shortcode('a [minn_probe a="1" b=\'2\' c=3 flag]x[/minn_probe] [[minn_probe]] [minn_probe/] [nope] b'));
$say('do_shortcode nested same', do_shortcode('[minn_probe][minn_probe]in[/minn_probe][/minn_probe]'));
$say('has_shortcode', [has_shortcode('a [minn_probe] b', 'minn_probe'), has_shortcode('a b', 'minn_probe')]);
$say('shortcode_atts', shortcode_atts(['a' => 'd', 'b' => 'e'], ['a' => '1', 'z' => '9']));
$say('shortcode_atts filter', (static function () { add_filter('shortcode_atts_minn_probe', static fn ($o, $p, $a, $s) => $o + ['filtered' => $s], 10, 4); return shortcode_atts(['a' => 'd'], [], 'minn_probe'); })());
$say('strip_shortcodes', strip_shortcodes('a [minn_probe]x[/minn_probe] [nope] b'));
$say('remove_shortcode', (static function () { remove_shortcode('minn_probe'); return shortcode_exists('minn_probe'); })());
$say('shortcode_parse_atts', shortcode_parse_atts('a="1" b=\'2\' c=3 flag "quoted" d="x y"'));

// Misc plugin-load helpers.
$say('wp_parse_url', wp_parse_url('https://u:p@x.com:8080/p/a?q=1#f'));
$say('wp_parse_url component', wp_parse_url('https://x.com/p', PHP_URL_HOST));
$say('wp_list_pluck', wp_list_pluck([['id' => 1, 'n' => 'a'], (object) ['id' => 2, 'n' => 'b']], 'n', 'id'));
$say('wp_list_filter', wp_list_filter([['a' => 1, 'b' => 2], ['a' => 2, 'b' => 2]], ['a' => 2]));
$say('wp_array_slice_assoc', wp_array_slice_assoc(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c', 'z']));
$say('wp_is_numeric_array', [wp_is_numeric_array([1, 2]), wp_is_numeric_array(['a' => 1]), wp_is_numeric_array([]), wp_is_numeric_array('x')]);
$say('wp_rand shape', wp_rand(5, 5));
$say('wp_generate_password shape', [strlen(wp_generate_password(8, false)), strlen(wp_generate_password())]);
$say('size_format', [size_format(1024), size_format(1536, 1), size_format(0), size_format(1024 ** 3)]);
$say('wp_hash shape', strlen(wp_hash('x')));
$say('wp_hash_password verify', wp_check_password('pw', wp_hash_password('pw')));
$say('wp_html_excerpt', [wp_html_excerpt('<b>Hello</b> world', 7), wp_html_excerpt('Hello world', 5, '…')]);
$say('wp_trim_words', [wp_trim_words('one two three four five', 3), wp_trim_words('one two', 3, '!'), wp_trim_words('<p>one</p> two three', 2)]);
$say('wptexturize', wptexturize('"Hi" -- it\'s 6\'2" & <code>"x"</code>...'));
$say('wpautop', wpautop("a\n\nb\nc<div>d</div>"));
$say('make_clickable', make_clickable('go to http://x.com/a?b=1 and a@b.co now'));
$say('wp_specialchars_decode', wp_specialchars_decode('&lt;b&gt; &amp;amp; &quot;q&quot; &#039;s'));
$say('_wp_specialchars', [_wp_specialchars('<a> & &amp; "q" \'s'), _wp_specialchars('<a> & &amp; "q" \'s', ENT_QUOTES), _wp_specialchars('<a> & &amp;', ENT_QUOTES, false, true)]);
$say('zeroise', [zeroise(5, 3), zeroise(1234, 3)]);
$say('wp_basename', wp_basename('/a/b/c%20d.txt'));
$say('is_serialized', [is_serialized('a:1:{i:0;s:1:"x";}'), is_serialized('s:1:"x";'), is_serialized('x'), is_serialized('N;'), is_serialized('b:1;'), is_serialized('i:5;'), is_serialized('a:1:{i:0;s:1:"x";')]);
$say('maybe_serialize', [maybe_serialize(['a' => 1]), maybe_serialize('s'), maybe_serialize(5), maybe_serialize(null), maybe_serialize('a:1:{i:0;s:1:"x";}')]);
$say('maybe_unserialize', [maybe_unserialize('a:1:{i:0;s:1:"x";}'), maybe_unserialize('s'), maybe_unserialize('i:5;'), maybe_unserialize('b:0;'), maybe_unserialize('N;'), gettype(maybe_unserialize('O:8:"stdClass":1:{s:1:"a";i:1;}'))]);
$say('wp_cache', [wp_cache_set('k', 'v', 'minn'), wp_cache_get('k', 'minn'), wp_cache_get('nope', 'minn'), wp_cache_delete('k', 'minn'), wp_cache_get('k', 'minn'), wp_cache_add('k', 'v1', 'minn'), wp_cache_add('k', 'v2', 'minn'), wp_cache_get('k', 'minn'), wp_cache_flush()]);
$say('wp_cache found', (static function () { $f = null; wp_cache_get('nope', 'minn', false, $f); return $f; })());
$say('current_time', [strlen(current_time('mysql')), is_int(current_time('timestamp')), current_time('Y') === gmdate('Y'), strlen(current_time('mysql', true))]);
$say('wp_timezone_string', wp_timezone_string());
$say('get_gmt_from_date', get_gmt_from_date('2026-01-02 03:04:05'));
$say('get_date_from_gmt', get_date_from_gmt('2026-01-02 03:04:05'));
$say('mysql2date', [mysql2date('Y-m-d', '2026-01-02 03:04:05'), mysql2date('U', '2026-01-02 03:04:05'), mysql2date('Y', '')]);
$say('date_i18n', [date_i18n('Y-m-d', 86400), date_i18n('F j, Y', 0, true)]);
$say('wp_date', wp_date('Y-m-d H:i', 0));
$say('human_time_diff', [human_time_diff(0, 90), human_time_diff(0, 3600 * 5), human_time_diff(0, 86400 * 40), human_time_diff(0, 20)]);
$say('number_format_i18n', [number_format_i18n(1234567.891, 2), number_format_i18n(1000)]);
$say('checked/selected/disabled', [checked(1, 1, false), checked('a', 'b', false), selected('a', 'a', false), disabled(true, true, false), checked(true, 1, false)]);
$say('wp_nonce shape', [strlen(wp_create_nonce('x')), wp_verify_nonce(wp_create_nonce('x'), 'x'), wp_verify_nonce('bad', 'x'), wp_verify_nonce(wp_create_nonce('x'), 'y')]);
$say('wp_nonce_field shape', preg_replace('/value="[a-f0-9]+"/', 'value="N"', wp_nonce_field('x', 'f', true, false)));
$say('wp_nonce_url shape', preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce=N', wp_nonce_url('http://x/?a=1', 'x')));
$say('wp_referer_field', wp_referer_field(false));
$say('wp_get_referer', wp_get_referer());
$say('wp_die shape', (static function () { add_filter('wp_die_handler', static fn () => static function ($m, $t, $a) { throw new RuntimeException(is_string($m) ? $m : 'wp_error'); }); try { wp_die('boom'); } catch (RuntimeException $e) { return $e->getMessage(); } })());
$say('is_wp_error', [is_wp_error(new WP_Error('c', 'm')), is_wp_error('x')]);
$e = new WP_Error('code1', 'msg1', ['d' => 1]);
$e->add('code2', 'msg2');
$e->add('code1', 'msg1b');
$say('WP_Error', [$e->get_error_code(), $e->get_error_codes(), $e->get_error_message(), $e->get_error_message('code2'), $e->get_error_messages(), $e->get_error_messages('code1'), $e->get_error_data(), $e->get_error_data('code2'), $e->has_errors(), (new WP_Error())->has_errors(), (new WP_Error())->get_error_code(), $e->errors, $e->error_data]);
$e2 = new WP_Error('x', 'y');
$e2->add_data('dd');
$e2->merge_from($e);
$e2->remove('x');
$say('WP_Error merge/remove', [$e2->get_error_codes(), $e2->get_error_data('x'), $e2->get_all_error_data('code1'), (static function () use ($e2) { $e3 = new WP_Error(); $e2->export_to($e3); return $e3->get_error_codes(); })()]);
$say('wp_get_theme', [wp_get_theme()->get('Name'), wp_get_theme()->get_stylesheet(), wp_get_theme()->get_template(), wp_get_theme()->parent() ? wp_get_theme()->parent()->get_stylesheet() : false, wp_get_theme()->exists(), wp_get_theme('nope')->exists(), wp_get_theme()->is_block_theme(), (string) wp_get_theme(), wp_get_theme()->get('Version') !== '', wp_get_theme()->get('TextDomain'), wp_get_theme()->get_theme_root() === get_theme_root(), basename(wp_get_theme()->get_stylesheet_directory())]);
$say('get_theme_mod', [get_theme_mod('nope'), get_theme_mod('nope', 'd')]);
$say('wp_get_environment_type', wp_get_environment_type());
$say('wp_get_development_mode', wp_get_development_mode());
$say('is_wp_version_compatible', [is_wp_version_compatible('6.0'), is_wp_version_compatible('99.0')]);
$say('is_php_version_compatible', is_php_version_compatible('7.4'));
$say('get_bloginfo url as home', get_bloginfo('url') === home_url());
$say('wp_get_mime_types count', count(wp_get_mime_types()) > 50);
$say('wp_check_filetype', [wp_check_filetype('a.jpg'), wp_check_filetype('a.JPEG'), wp_check_filetype('a.exe'), wp_check_filetype('a.tar.gz')]);
$say('wp_ext2type', [wp_ext2type('jpg'), wp_ext2type('mp3'), wp_ext2type('x')]);
$say('get_allowed_mime_types count', count(get_allowed_mime_types()) > 30);
$say('wp_get_attachment_url none', wp_get_attachment_url(999999));
$say('wp_mkdir_p', wp_mkdir_p(WP_CONTENT_DIR . '/uploads'));
$say('wp_is_writable', wp_is_writable(WP_CONTENT_DIR . '/uploads'));
$say('wp_using_ext_object_cache', wp_using_ext_object_cache());
$say('apply_filters plugins_url filter', (static function () { add_filter('plugins_url', static fn ($u, $p, $pl) => $u . '#f', 10, 3); $r = plugins_url('a', WP_PLUGIN_DIR . '/x/x.php'); remove_all_filters('plugins_url'); return $r; })());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
