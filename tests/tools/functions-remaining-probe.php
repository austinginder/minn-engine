<?php
/**
 * The rest of functions.php's helpers (probe functions-remaining): arrays
 * and lists, paths and files, dates, hashes and ids, status lines and the
 * no-cache headers (seen through their filters, as CLI keeps no headers),
 * referers, admin notices, the site's state, every wp_die handler with exit
 * off, the JSON senders and the nonce page caught at wp_die, and the
 * enclosure and header lookups over intercepted HTTP. A temporary folder,
 * the probe's post and the fresh_site option are put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$abs = rtrim(ABSPATH, '/');
$mask = static function ($value) use ($host, $abs, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    // WP-CLI forces the site URL's scheme, so the scheme is masked too.
    $value = (string) preg_replace(['#https?://' . preg_quote($host, '#') . '#', '/(_wpnonce=|_wpnonce" value=")[0-9a-f]{10}/'], ['//{host}', '$1{nonce}'], $value);
    return str_replace([$abs, $host], ['{abs}', '{host}'], $value);
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$echoed = static function (Closure $call): array {
    ob_start();
    $returned = $call();
    return [(string) ob_get_clean(), $returned];
};
$died = [];
foreach (['wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler'] as $filter) {
    add_filter($filter, static function () use ($filter, &$died) {
        return static function ($message, $title = '', $args = []) use ($filter, &$died) {
            $died[] = [$filter, is_wp_error($message) ? ['WP_Error', $message->get_error_codes(), $message->get_error_data()] : $message, $title, $args];
        };
    }, 99);
}
$dies = static function (Closure $call) use (&$died, $mask) {
    $died = [];
    ob_start();
    $result = $call();
    return $mask([$result, (string) ob_get_clean(), $died]);
};
$dir = sys_get_temp_dir() . '/zz-probe-functions-' . getmypid();
@mkdir($dir);
$fresh = $GLOBALS['wpdb']->get_row("SELECT option_value, autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'fresh_site'", ARRAY_A);
$post = 0;
register_shutdown_function(static function () use ($dir, $fresh, &$post): void {
    foreach ((array) glob($dir . '/*') as $file) {
        @unlink((string) $file);
    }
    @rmdir($dir);
    global $wpdb;
    if (is_array($fresh)) {
        $wpdb->update($wpdb->options, ['option_value' => $fresh['option_value'], 'autoload' => $fresh['autoload']], ['option_name' => 'fresh_site']);
    } else {
        $wpdb->delete($wpdb->options, ['option_name' => 'fresh_site']);
    }
    wp_cache_delete('fresh_site', 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    if (is_int($post) && $post > 0) {
        wp_delete_post($post, true);
    }
});

$say('arrays and lists', (static function () {
    $nested = ['a' => ['b' => ['c' => 1]], 'x' => 0, 'n' => null];
    $set = ['a' => ['b' => 1], 's' => 'scalar'];
    _wp_array_set($set, ['a', 'c'], 2);
    _wp_array_set($set, ['new', 'deep', 'er'], 3);
    _wp_array_set($set, ['s', 'inner'], 4);
    _wp_array_set($set, [], 5);
    _wp_array_set($set, 'a', 6);
    $notArray = 'string';
    _wp_array_set($notArray, ['a'], 1);
    $rows = [['name' => 'b', 'n' => 2, 'g' => 'x'], ['name' => 'a', 'n' => 3, 'g' => 'y'], ['name' => 'c', 'n' => 1, 'g' => 'x']];
    $objects = array_map(static fn ($row) => (object) $row, $rows);
    $keyed = ['k1' => $rows[0], 'k2' => $rows[1], 'k3' => $rows[2]];
    $sorted = ['b' => ['z' => 1, 'a' => 2], 'a' => 3, 10 => 4, 2 => ['y' => 1, 'b' => 2]];
    wp_recursive_ksort($sorted);
    return [
        [_wp_array_get($nested, ['a', 'b', 'c']), _wp_array_get($nested, ['a', 'zz'], 'def'), _wp_array_get($nested, ['x']), _wp_array_get($nested, ['n'], 'def'), _wp_array_get($nested, [], 'def'), _wp_array_get($nested, 'a', 'def'), _wp_array_get('nope', ['a'], 'def'), _wp_array_get($nested, ['a', 'b', 'c', 'd'], 'def')],
        [$set, $notArray],
        [wp_list_sort($rows, 'name'), wp_list_sort($rows, 'n', 'DESC'), wp_list_sort($keyed, 'name', 'ASC', true), wp_list_sort($rows, ['g' => 'ASC', 'n' => 'DESC']), wp_list_sort($objects, 'name'), wp_list_sort($rows), wp_list_sort([], 'name')],
        [wp_filter_object_list($rows, ['g' => 'x']), wp_filter_object_list($rows, ['g' => 'x', 'n' => 2], 'or', 'name'), wp_filter_object_list($rows, ['g' => 'x'], 'not', 'n'), wp_filter_object_list($objects, ['name' => 'a'], 'and', 'n'), wp_filter_object_list($keyed, [], 'and', 'name'), wp_filter_object_list($rows, ['zz' => 1])],
        [wp_parse_list('a, b ,c'), wp_parse_list(['a', ' b ', '']), wp_parse_list("a b\tc,,d"), wp_parse_list(''), wp_parse_id_list('1,2,a,-3,2, 4'), wp_parse_id_list([3, '3', 'x', 0]), wp_parse_slug_list('a b, C!d,a'), wp_parse_slug_list(['Hello World', 'hello-world'])],
        $sorted,
        array_map('wp_validate_boolean', [true, false, 'false', 'FALSE', '0', 0, 1, '', 'no', 'yes', null, [], 'off', 'true']),
        [wp_fuzzy_number_match(1.0, 1.5), wp_fuzzy_number_match(1, 3), wp_fuzzy_number_match(1, 3, 2), wp_fuzzy_number_match('2', 2), wp_fuzzy_number_match(1, 2.0001)],
        [wp_is_uuid('6ba7b810-9dad-11d1-80b4-00c04fd430c8'), wp_is_uuid('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 4), wp_is_uuid('f47ac10b-58cc-4372-a567-0e02b2c3d479', 4), wp_is_uuid('F47AC10B-58CC-4372-A567-0E02B2C3D479'), wp_is_uuid('zz'), wp_is_uuid(5), wp_is_uuid('f47ac10b-58cc-4372-a567-0e02b2c3d479', 1)],
        array_map('is_serialized_string', ['s:1:"a";', 'a:0:{}', 's:2:"a";', ' s:1:"a"; ', 'zz', 's:0:"";', 's:1:"a"', 5, 'S:1:"a";']),
        [wp_extract_urls('See https://a.example/x?y=1&z=2, http://b.example/ and <a href="https://c.example/q#f">c</a> or www.d.example, mailto:e@f.example, ftp://g.example/h. Again https://a.example/x?y=1&z=2'), wp_extract_urls('')],
        [get_tag_regex('p'), get_tag_regex('a b'), get_tag_regex(''), get_tag_regex('IMG')],
        [wp_checkdate(2, 29, 2020, '2020-02-29'), wp_checkdate(2, 30, 2020, '2020-02-30'), wp_checkdate('13', '1', '2020', 'x'), wp_checkdate(0, 0, 0, '0000-00-00')],
        [_http_build_query(['a' => 1, 'b' => ['c' => 'd e', 'f' => null], 'g' => true, 'h' => false], null, '&', '', true), _http_build_query(['a' => 'x y', 5 => 'five'], 'p_', ';', '', false), _http_build_query(['a' => ['b' => 1]], null, '&amp;', 'top', true), _http_build_query([], null, '&'), _http_build_query((object) ['o' => 1], null, '&')],
    ];
})());

$say('paths and files', $mask((static function () use ($dir) {
    foreach (['a.txt', 'a-1.txt', 'b.jpg', 'c.png', 'c.webp', 'd-150x150.jpg', 'e.tar.gz'] as $file) {
        file_put_contents($dir . '/' . $file, 'x');
    }
    $heard = [];
    $listen = static function ($filename, ...$rest) use (&$heard) {
        $heard[] = [$filename, count($rest), $rest[0] ?? null];
        return $filename;
    };
    add_filter('wp_unique_filename', $listen, 10, 6);
    $unique = array_map(static fn ($name) => wp_unique_filename($dir, $name), ['a.txt', 'b.jpg', 'B.JPG', 'new.txt', 'c.png', 'c.webp', 'd.jpg', 'e.tar.gz', 'with space.txt', 'Ünïcode.txt', '.htaccess', 'noext']);
    remove_filter('wp_unique_filename', $listen, 10);
    $callback = wp_unique_filename($dir, 'a.txt', static fn ($d, $name, $ext) => 'cb-' . $name . $ext);
    file_put_contents($inside = $dir . '/del.txt', 'x');
    $deleted = [wp_delete_file_from_directory($inside, $dir), is_file($inside), wp_delete_file_from_directory($dir . '/missing.txt', $dir), wp_delete_file_from_directory($dir . '/a.txt', sys_get_temp_dir() . '/zz-elsewhere'), wp_delete_file_from_directory($dir . '/../' . basename($dir) . '/b.jpg', $dir), is_file($dir . '/b.jpg')];
    return [
        array_map('path_is_absolute', ['/a', 'a', 'C:\\x', 'C:/x', 'c:x', '\\\\server\\share', '.', '', './a', '../a', 'http://x', 'C:\\', '\\x', 'c:\\x', '/', 'z:\\a\\b', '1:\\x', 'ab:\\x', '\\\\', ' /a', ABSPATH, 'wp-content']),
        [path_join('/a', 'b'), path_join('/a/', '/b'), path_join('/a', ''), path_join('', 'b'), path_join('a', 'b'), path_join('/a/', 'b/'), path_join('C:\\a', 'b')],
        array_map(static fn ($file) => validate_file($file), ['a/b.php', '../x', './x', 'a/../b', 'C:/x', 'c:\\x', 'a/b', '', 'a\\b', '..', '.../x', 'a/./b', 'x..y', '..\\x', 'a/..', '/../', 'a:b', ':ab', 'C:', '%2e%2e/x', 'a\\..\\b', '/abs/path']),
        [validate_file('a.php', ['a.php', 'b.php']), validate_file('c.php', ['a.php']), validate_file('../a.php', ['../a.php'])],
        array_map('wp_is_stream', ['http://x', 'php://memory', 'file:///x', '/x', 'zz://x', 'HTTP://x', 'C:\\x', 'glob://*']),
        [$unique, $heard, $callback],
        $deleted,
        [array_keys(wp_get_ext_types()), wp_get_ext_types()['image'], wp_get_ext_types()['code']],
        array_map('wp_get_default_extension_for_mime_type', ['image/jpeg', 'text/plain', 'zz/zz', 'video/mp4', 'application/pdf', '']),
        [_wp_upload_dir('2020/05'), array_keys(_wp_upload_dir())],
        [iis7_supports_permalinks(), (static function () {
            add_filter('iis7_supports_permalinks', '__return_true');
            $answer = iis7_supports_permalinks();
            remove_filter('iis7_supports_permalinks', '__return_true');
            return $answer;
        })()],
    ];
})()));

$say('dates, hashes and ids', (static function () {
    $now = current_datetime();
    $zone = wp_timezone();
    return [
        [get_class($now), $now->getTimezone()->getName() === wp_timezone_string(), abs($now->getTimestamp() - time()) < 5, get_class($zone), $zone->getName() === wp_timezone_string()],
        array_map('mysql_to_rfc3339', ['2026-10-07 12:34:56', '2026-10-07', '0000-00-00 00:00:00', 'zz', '2026-10-07T01:02:03']),
        [get_weekstartend('2026-10-07', 1), get_weekstartend('2026-10-07 23:00:00', 0), get_weekstartend('2026-10-04', '6'), get_weekstartend('2026-10-07')],
        [wp_fast_hash('zz'), wp_fast_hash(''), wp_verify_fast_hash('zz', wp_fast_hash('zz')), wp_verify_fast_hash('zy', wp_fast_hash('zz')), wp_verify_fast_hash('zz', md5('zz')), wp_verify_fast_hash('zz', 'zz')],
        [wp_unique_prefixed_id('zz-probe-'), wp_unique_prefixed_id('zz-probe-'), wp_unique_prefixed_id('zz-other-'), wp_unique_prefixed_id('zz-probe-')],
    ];
})());

$say('status lines and headers', (static function () {
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? null;
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $heard = [];
    $listen = static function ($value, ...$rest) use (&$heard) {
        $heard[] = [current_filter(), $value, $rest];
        return $value;
    };
    foreach (['status_header', 'nocache_headers', 'admin_referrer_policy'] as $filter) {
        add_filter($filter, $listen, 10, 4);
    }
    $out = [array_map('get_status_header_desc', [200, 404, 418, 999, '404', 0, 'zz', 103, 451])];
    $known = [];
    for ($code = 100; $code < 600; $code++) {
        if (get_status_header_desc($code) !== '') {
            $known[$code] = get_status_header_desc($code);
        }
    }
    $out[] = $known;
    status_header(404);
    status_header(201, 'Made');
    status_header(999);
    $out[] = $heard;
    $heard = [];
    $out[] = wp_get_nocache_headers();
    nocache_headers();
    wp_admin_headers();
    $out[] = $heard;
    foreach (['status_header', 'nocache_headers', 'admin_referrer_policy'] as $filter) {
        remove_filter($filter, $listen, 10);
    }
    add_filter('status_header', $listen, 10, 4);
    $heard = [];
    foreach (['HTTP/2', 'ZZ/9', 'HTTP/2.0', 'HTTP/3', 'HTTP/1.0', 'HTTP/1.1', '', 'http/2'] as $sent) {
        $_SERVER['SERVER_PROTOCOL'] = $sent;
        status_header(200);
    }
    unset($_SERVER['SERVER_PROTOCOL']);
    status_header(200);
    remove_filter('status_header', $listen, 10);
    $out[] = $heard;
    $_SERVER['SERVER_PROTOCOL'] = $protocol;
    return $out;
})());

$say('referers and notices', $mask((static function () use ($echoed) {
    $saved = [$_REQUEST, $_SERVER['HTTP_REFERER'] ?? null, $_SERVER['REQUEST_URI'] ?? null];
    $out = [];
    $_REQUEST = [];
    unset($_SERVER['HTTP_REFERER']);
    $out[] = [wp_get_raw_referer(), wp_get_original_referer()];
    $_SERVER['HTTP_REFERER'] = home_url('/from-header/');
    $out[] = wp_get_raw_referer();
    $_REQUEST['_wp_http_referer'] = '/from-request/';
    $out[] = wp_get_raw_referer();
    $_REQUEST['_wp_http_referer'] = '';
    $out[] = wp_get_raw_referer();
    $_REQUEST['_wp_original_http_referer'] = home_url('/original/');
    $out[] = wp_get_original_referer();
    $_REQUEST['_wp_original_http_referer'] = 'https://evil.example/x';
    $out[] = wp_get_original_referer();
    $_SERVER['REQUEST_URI'] = '/zz/page/?a=1&b="2"';
    $out[] = [$echoed(static fn () => wp_original_referer_field()), wp_original_referer_field(false), wp_original_referer_field(false, 'previous')];
    unset($_SERVER['HTTP_REFERER']);
    $out[] = wp_original_referer_field(false, 'previous');
    $_REQUEST['_wp_original_http_referer'] = home_url('/original/');
    $out[] = [wp_original_referer_field(false), wp_original_referer_field(false, 'previous')];
    $_REQUEST = ['_wp_http_referer' => 'a\\\'b'];
    $out[] = wp_get_raw_referer();
    [$_REQUEST, $referer, $_SERVER['REQUEST_URI']] = $saved;
    if ($referer !== null) {
        $_SERVER['HTTP_REFERER'] = $referer;
    }
    $out[] = [
        wp_get_admin_notice('Hello <b>there</b>'),
        wp_get_admin_notice('Bad', ['type' => 'error', 'dismissible' => true, 'id' => 'zz-id', 'additional_classes' => ['one', 'two three'], 'attributes' => ['data-x' => 'y"z', 'hidden' => true, 'role' => 'alert']]),
        wp_get_admin_notice('<p>Own paragraph</p>', ['type' => 'success', 'paragraph_wrap' => false]),
        wp_get_admin_notice('Typed', ['type' => 'zz custom']),
        wp_get_admin_notice('', []),
        wp_get_admin_notice('Attrs', ['attributes' => ['class' => 'mine', 'id' => 'dup'], 'id' => 'own']),
    ];
    $heard = [];
    $listen = static function ($value, ...$rest) use (&$heard) {
        $heard[] = [current_filter(), $value, $rest];
        return current_filter() === 'wp_admin_notice_markup' ? $value . '<!-- filtered -->' : $value;
    };
    add_filter('wp_admin_notice_args', $listen, 10, 2);
    add_filter('wp_admin_notice_markup', $listen, 10, 3);
    add_action('wp_admin_notice', $listen, 10, 2);
    $out[] = [$echoed(static fn () => wp_admin_notice('Printed', ['type' => 'warning'])), $heard];
    remove_filter('wp_admin_notice_args', $listen, 10);
    remove_filter('wp_admin_notice_markup', $listen, 10);
    remove_action('wp_admin_notice', $listen, 10);
    $out[] = array_map('wp_check_jsonp_callback', ['cb', 'a.b', 'a b', '', 'a$_1', 'x()', 'a[0]', 'ünï']);
    $out[] = wp_check_jsonp_callback(['x']);
    return $out;
})()));

$say('the site\'s state', $mask((static function () use ($echoed) {
    $out = [is_blog_installed(), get_main_network_id(), is_main_network(), is_main_network(1), is_main_network(2), wp_get_wp_version() === $GLOBALS['wp_version'], wp_is_serving_rest_request()];
    $forced = force_ssl_admin();
    $out[] = [force_ssl_admin(false), force_ssl_admin(), force_ssl_admin(true), force_ssl_admin(), force_ssl_admin($forced) !== null];
    $out[] = [wp_suspend_cache_invalidation(true), wp_suspend_cache_invalidation(false), wp_suspend_cache_invalidation(false), wp_suspend_cache_invalidation()];
    wp_suspend_cache_invalidation(false);
    $encoding = mb_internal_encoding();
    $steps = [mbstring_binary_safe_encoding(), mb_internal_encoding(), mbstring_binary_safe_encoding(), reset_mbstring_encoding(), mb_internal_encoding(), reset_mbstring_encoding(), mb_internal_encoding() === $encoding, mbstring_binary_safe_encoding(true), mb_internal_encoding() === $encoding];
    $out[] = $steps;
    $out[] = array_map('_canonical_charset', ['utf8', 'UTF-8', 'utf-8', 'UTF8', 'Utf8', 'latin1', 'ISO-8859-1', 'iso8859-1', 'ISO8859-1', '', 'zz', 'utf-8 ']);
    wp_set_current_user(0);
    $out[] = [wp_auth_check([]), wp_auth_check(['wp-auth-check' => 'keep', 'other' => 1])];
    wp_set_current_user(1);
    $out[] = wp_auth_check(['x' => 1]);
    wp_set_current_user(0);
    $out[] = $echoed(static fn () => wp_auth_check_html());
    $levels = ob_get_level();
    ob_start();
    ob_start();
    wp_ob_end_flush_all();
    $out[] = [$levels, ob_get_level()];
    $saved = [$_SERVER['HTTP_HOST'] ?? null, $_SERVER['REQUEST_URI'] ?? null, $_SERVER['SCRIPT_FILENAME'] ?? null, $_SERVER['PHP_SELF'] ?? null, $_SERVER['HTTPS'] ?? null];
    $guesses = [];
    foreach ([['/wp-login.php', 'wp-login.php', 'on'], ['/wp-admin/options.php', 'wp-admin/options.php', ''], ['/sub/index.php?x=1', 'sub/index.php', 'on'], ['/index.php', 'index.php', 'off'], ['/blog/wp-login.php', 'wp-login.php', 'on'], ['/blog/wp-admin/options.php?a=b', 'wp-admin/options.php', 'on'], ['/blog/sub/x.php', 'sub/x.php', 'on'], ['/x/y.php', '/elsewhere/y.php', 'on'], ['/blog/index.php/extra', 'index.php', 'on'], ['/a/b/c/wp-admin/network/x.php', 'wp-admin/network/x.php', 'on']] as [$uri, $script, $https]) {
        $_SERVER['HTTP_HOST'] = 'guess.example';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_FILENAME'] = str_starts_with($script, '/') ? $script : ABSPATH . $script;
        $_SERVER['PHP_SELF'] = '/' . $script;
        $_SERVER['HTTPS'] = $https;
        $guesses[] = wp_guess_url();
    }
    foreach (['HTTP_HOST', 'REQUEST_URI', 'SCRIPT_FILENAME', 'PHP_SELF', 'HTTPS'] as $i => $key) {
        if ($saved[$i] === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $saved[$i];
        }
    }
    $out[] = $guesses;
    update_option('fresh_site', '1');
    _delete_option_fresh_site();
    $out[] = [get_option('fresh_site'), $GLOBALS['wpdb']->get_var("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'fresh_site'")];
    return $out;
})()));

$say('the wp_die handlers', $mask((static function () use ($echoed) {
    $error = new WP_Error('zz_first', 'First <b>problem</b>', ['status' => 418, 'title' => 'Data title']);
    $error->add('zz_second', 'Second problem', ['extra' => 1]);
    $exit = ['exit' => false];
    return [
        [_wp_die_process_input('Zz'), _wp_die_process_input('Zz', 'Title'), _wp_die_process_input($error), _wp_die_process_input('Zz', 500), _wp_die_process_input('Zz', 'T', ['response' => 403, 'back_link' => true, 'link_url' => '/x', 'link_text' => 'X']), _wp_die_process_input('Zz', 'T', 'response=401'), _wp_die_process_input(new WP_Error()), _wp_die_process_input(5), _wp_die_process_input($error, 'Own title', ['response' => 400, 'additional_errors' => [['code' => 'mine', 'message' => 'Mine']]])],
        $echoed(static fn () => _default_wp_die_handler('Zz <b>message</b>', 'Zz title', $exit + ['response' => 403])),
        $echoed(static fn () => _default_wp_die_handler($error, '', $exit + ['back_link' => true, 'text_direction' => 'rtl'])),
        $echoed(static fn () => _default_wp_die_handler('<p>Own paragraph</p>', '', $exit + ['link_url' => 'https://zz.example/', 'link_text' => 'Go <i>on</i>', 'charset' => 'iso-8859-1', 'code' => 'zz_code'])),
        (static function () use ($echoed, $exit) {
            $heard = [];
            $listen = static function ($value, ...$rest) use (&$heard) {
                $heard[] = [current_filter(), $value, $rest];
                return $value;
            };
            foreach (['status_header', 'nocache_headers', 'wp_robots'] as $filter) {
                add_filter($filter, $listen, 10, 4);
            }
            $page = $echoed(static fn () => _default_wp_die_handler('Both', '<b>Bold</b> & "title"', $exit + ['back_link' => true, 'link_url' => '/x?a=1&b=2', 'link_text' => 'X & Y', 'response' => 404]));
            foreach (['status_header', 'nocache_headers', 'wp_robots'] as $filter) {
                remove_filter($filter, $listen, 10);
            }
            $single = new WP_Error('zz_one', 'Only <i>one</i>', ['title' => 'One title']);
            return [$page, $heard, $echoed(static fn () => _default_wp_die_handler($single, '', $exit)), $echoed(static fn () => _default_wp_die_handler('', '', $exit)), $echoed(static fn () => _default_wp_die_handler(new WP_Error('zz_empty', ''), '', $exit))];
        })(),
        array_map(static fn ($charset) => _wp_die_process_input('Zz', '', ['charset' => $charset])[2]['charset'], ['iso-8859-1', 'utf8', '', 'UTF-8', 'zz', 'latin1']),
        array_map(static fn ($message) => $echoed(static fn () => _ajax_wp_die_handler($message, '', $exit + ['response' => 200])), ['zz', -1, 0, '', $error, 5]),
        [$echoed(static fn () => _json_wp_die_handler('Zz', 'T', $exit + ['response' => 400])), $echoed(static fn () => _json_wp_die_handler($error, '', $exit)), $echoed(static fn () => _json_wp_die_handler('', '', $exit))],
        [$echoed(static fn () => _xml_wp_die_handler('Zz & <x>', 'T', $exit + ['response' => 400])), $echoed(static fn () => _xml_wp_die_handler($error, '', $exit))],
        [$echoed(static fn () => _scalar_wp_die_handler('zz', '', $exit)), $echoed(static fn () => _scalar_wp_die_handler(5, '', $exit)), $echoed(static fn () => _scalar_wp_die_handler(['x'], '', $exit)), $echoed(static fn () => _scalar_wp_die_handler($error, '', $exit))],
        $echoed(static fn () => _xmlrpc_wp_die_handler('Zz', 'T', $exit + ['response' => 500])),
    ];
})()));

$say('the JSON senders and the nonce page', (static function () use ($dies) {
    $status = [];
    $listen = static function ($header, $code) use (&$status) {
        $status[] = $code;
        return $header;
    };
    add_filter('status_header', $listen, 10, 2);
    add_filter('wp_doing_ajax', '__return_true');
    $out = [
        $dies(static fn () => wp_send_json(['a' => 1, 'b' => 'é/x'])),
        $dies(static fn () => wp_send_json(['a' => 1], 201)),
        $dies(static fn () => wp_send_json('<tag>', null, JSON_HEX_TAG)),
        $dies(static fn () => wp_send_json_success()),
        $dies(static fn () => wp_send_json_success(['x' => 1], 202)),
        $dies(static fn () => wp_send_json_error()),
        $dies(static fn () => wp_send_json_error('bad', 400)),
        $dies(static fn () => wp_send_json_error(new WP_Error('zz_code', 'Zz message'))),
        $dies(static fn () => wp_send_json_error(new WP_Error())),
    ];
    remove_filter('wp_doing_ajax', '__return_true');
    remove_filter('status_header', $listen, 10);
    $out[] = $status;
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    unset($_SERVER['HTTP_REFERER']);
    wp_set_current_user(0);
    $out[] = $dies(static fn () => wp_nonce_ays('log-out'));
    wp_set_current_user(1);
    $out[] = $dies(static fn () => wp_nonce_ays('log-out'));
    $out[] = $dies(static fn () => wp_nonce_ays('zz-action'));
    $_SERVER['HTTP_REFERER'] = home_url('/back/');
    $out[] = $dies(static fn () => wp_nonce_ays('zz-action'));
    $_SERVER['HTTP_REFERER'] = 'https://evil.example/x';
    $out[] = $dies(static fn () => wp_nonce_ays('zz-action'));
    wp_set_current_user(0);
    if ($referer === null) {
        unset($_SERVER['HTTP_REFERER']);
    } else {
        $_SERVER['HTTP_REFERER'] = $referer;
    }
    return $out;
})());

$say('cache invalidation suspended', (static function () {
    $post = get_posts(['numberposts' => 1, 'post_type' => 'any', 'post_status' => 'any', 'fields' => 'ids'])[0] ?? 0;
    $out = [];
    // A user's cache is cleaned either way.
    foreach ([true, false] as $suspended) {
        get_post($post);
        get_userdata(1);
        wp_suspend_cache_invalidation($suspended);
        clean_post_cache($post);
        clean_user_cache(1);
        wp_suspend_cache_invalidation(false);
        $out[] = [wp_cache_get($post, 'posts') !== false, wp_cache_get(1, 'users') !== false];
    }
    return $out;
})());

$say('enclosures and header lookups', (static function () use (&$post) {
    $calls = [];
    $answer = static function ($pre, $args, $url) use (&$calls) {
        $calls[] = [$url, $args['method'] ?? null, $args['limit_response_size'] ?? null, $args['redirection'] ?? null];
        if (str_contains($url, 'fail')) {
            return new WP_Error('http_request_failed', 'Nope');
        }
        $type = str_ends_with((string) parse_url($url, PHP_URL_PATH), '.mp3') ? 'audio/mpeg' : (str_contains($url, 'video') ? 'video/mp4' : 'text/html; charset=UTF-8');
        $headers = new WpOrg\Requests\Utility\CaseInsensitiveDictionary(['content-type' => $type, 'content-length' => '1234', 'x-zz' => 'y']);
        return ['headers' => str_contains($url, 'plain') ? ['content-type' => 'audio/ogg', 'content-length' => '9'] : $headers, 'body' => '', 'response' => ['code' => str_contains($url, 'missing') ? 404 : 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    };
    add_filter('pre_http_request', $answer, 10, 3);
    $out = [];
    $headers = wp_get_http_headers('https://zz.example/page');
    $out[] = [is_object($headers) ? get_class($headers) : $headers, is_object($headers) ? $headers->getAll() : null, wp_get_http_headers('https://zz.example/fail'), wp_get_http_headers('https://zz.example/missing'), wp_get_http_headers('https://zz.example/plain'), $calls];
    $calls = [];
    $post = wp_insert_post(['post_title' => 'Zz enclosure probe', 'post_status' => 'draft', 'post_content' => 'Listen https://zz.example/a.mp3 and watch https://zz.example/video/b and read https://zz.example/page and https://zz.example/fail.mp3 and https://zz.example/missing.mp3 and https://zz.example/plain.ogg and https://zz.example/z.pdf']);
    add_post_meta($post, 'enclosure', "https://zz.example/gone.mp3\n10\naudio/mpeg\n");
    do_enclose(null, $post);
    $out[] = [get_post_meta($post, 'enclosure'), $calls];
    $calls = [];
    do_enclose('Only https://zz.example/c.mp3 now', $post);
    $out[] = [get_post_meta($post, 'enclosure'), $calls];
    $calls = [];
    $heard = [];
    $links = static function ($links, $id) use (&$heard, $post) {
        $heard[] = [$links, $id === $post];
        return array_merge($links, ['https://zz.example/extra.mp3']);
    };
    add_filter('enclosure_links', $links, 10, 2);
    do_enclose('Nothing here', $post);
    remove_filter('enclosure_links', $links, 10);
    $out[] = [get_post_meta($post, 'enclosure'), $calls, $heard, do_enclose('x', 0), do_enclose('x', 999999999)];
    remove_filter('pre_http_request', $answer, 10);
    return $out;
})());

$say('calls done wrong along the way', $wrong);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
