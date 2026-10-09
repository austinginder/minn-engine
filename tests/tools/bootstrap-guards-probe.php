<?php
/**
 * The guards a request passes on its way in, and the pages that stop it
 * (probe bootstrap-guards): the constants settled at boot, the version,
 * install, favicon and maintenance checks, the database error page, the
 * favicon redirect, the JSONP die handler and wp_die's choice of it, the
 * description of an extension's error, whether the fatal error handler is
 * on, and the file editor's scrape check. Every page that would stop the
 * request is caught where it hands over (wp_die's handler, wp_redirect),
 * so nothing exits; a .maintenance file the probe writes is removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$maintenance = ABSPATH . '.maintenance';
register_shutdown_function(static function () use ($maintenance): void {
    if (is_file($maintenance) && str_contains((string) file_get_contents($maintenance), 'minn-probe')) {
        unlink($maintenance);
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use ($host, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    // WP-CLI forces the site URL's scheme, so the scheme is masked too.
    return is_string($value) ? (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value) : $value;
};
$died = [];
foreach (['wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler', 'wp_die_jsonp_handler', 'wp_die_xmlrpc_handler', 'wp_die_xml_handler'] as $filter) {
    add_filter($filter, static function () use ($filter, &$died) {
        return static function ($message, $title = '', $args = []) use ($filter, &$died) {
            $died[] = [$filter, is_wp_error($message) ? ['WP_Error', $message->get_error_codes(), $message->get_error_data()] : $message, $title, $args];
        };
    }, 99);
}
$dies = static function (Closure $call) use (&$died) {
    $died = [];
    ob_start();
    $result = $call();
    return [$result, (string) ob_get_clean(), $died];
};

$constants = ['KB_IN_BYTES', 'MB_IN_BYTES', 'GB_IN_BYTES', 'TB_IN_BYTES', 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_DEVELOPMENT_MODE', 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG', 'WP_CACHE', 'SCRIPT_DEBUG', 'MEDIA_TRASH', 'SHORTINIT', 'WP_FEATURE_BETTER_PASSWORDS', 'MINUTE_IN_SECONDS', 'HOUR_IN_SECONDS', 'DAY_IN_SECONDS', 'WEEK_IN_SECONDS', 'MONTH_IN_SECONDS', 'YEAR_IN_SECONDS', 'AUTOSAVE_INTERVAL', 'EMPTY_TRASH_DAYS', 'WP_POST_REVISIONS', 'WP_CRON_LOCK_TIMEOUT'];
$values = static fn () => array_map(static fn ($name) => defined($name) ? constant($name) : '(undefined)', array_combine($constants, $constants));
$say('the constants settled at boot', (static function () use ($values) {
    $before = $values();
    ob_start();
    // The reference defines some of these again unguarded and warns on stderr; only what changes is compared.
    $out = [@wp_initial_constants(), @wp_functionality_constants(), (string) ob_get_clean()];
    $out[] = $values() === $before;
    $out[] = $before;
    $out[] = [$GLOBALS['blog_id'] ?? '(unset)', defined('WP_START_TIMESTAMP') && is_float(WP_START_TIMESTAMP), defined('WP_CONTENT_DIR') && WP_CONTENT_DIR === ABSPATH . 'wp-content'];
    return $out;
})());

$say('a request passes the guards', (static function () {
    $uri = $_SERVER['REQUEST_URI'] ?? null;
    $out = [wp_check_php_mysql_versions(), wp_not_installed()];
    foreach (['/zz/', '/favicon.ico/', '/FAVICON.ICO', '/favicon.ico?x', 'favicon.ico', '/sub/favicon.ico', ''] as $path) {
        $_SERVER['REQUEST_URI'] = $path;
        $out[] = [$path, wp_favicon_request()];
    }
    $_SERVER['REQUEST_URI'] = $uri;
    wp_installing(true);
    $out[] = wp_not_installed();
    wp_installing(false);
    return $out;
})());

$say('maintenance', (static function () use ($dies, $maintenance) {
    // WP-CLI turns maintenance off for its own commands; a request has no such filter.
    remove_all_filters('enable_maintenance_mode');
    $heard = [];
    $out = [$dies(static fn () => wp_maintenance())];
    foreach ([700, 0, 599, -30] as $ago) {
        file_put_contents($maintenance, '<?php /* minn-probe */ $upgrading = ' . (time() - $ago) . ';');
        $out[] = [$ago, $dies(static fn () => wp_maintenance()), wp_is_maintenance_mode()];
    }
    $listen = static function ($enable, ...$rest) use (&$heard) {
        $heard[] = [$enable, count($rest), is_int($rest[0] ?? null)];
        return false;
    };
    add_filter('enable_maintenance_mode', $listen, 10, 2);
    file_put_contents($maintenance, '<?php /* minn-probe */ $upgrading = ' . time() . ';');
    $out[] = ['refused', $dies(static fn () => wp_maintenance()), $heard];
    remove_filter('enable_maintenance_mode', $listen, 10);
    file_put_contents($maintenance, '<?php /* minn-probe */ $zz = 1;');
    $out[] = ['no time', $dies(static fn () => wp_maintenance())];
    file_put_contents($maintenance, '<?php /* minn-probe */ $upgrading = ' . time() . ';');
    wp_installing(true);
    $out[] = ['installing', wp_is_maintenance_mode(), $dies(static fn () => wp_maintenance())];
    wp_installing(false);
    unlink($maintenance);
    $out[] = ['removed', $dies(static fn () => wp_maintenance())];
    return $out;
})());

$say('the database error page', (static function () use ($dies) {
    global $wpdb;
    $saved = $wpdb->error ?? null;
    $out = [$dies(static fn () => dead_db())];
    $wpdb->error = 'zz db error';
    $out[] = $dies(static fn () => dead_db());
    wp_installing(true);
    $out[] = $dies(static fn () => dead_db());
    $wpdb->error = new WP_Error('zz_db', 'zz db said', ['status' => 503]);
    $out[] = $dies(static fn () => dead_db());
    wp_installing(false);
    $wpdb->error = $saved;
    return $out;
})());

$say('the favicon redirect', $mask((static function () use ($dies) {
    $heard = [];
    $listen = static function (...$args) use (&$heard) {
        $heard[] = ['do_faviconico', $args];
    };
    $icon = static function ($url, ...$rest) use (&$heard) {
        $heard[] = ['get_site_icon_url', $url, $rest];
        return $url;
    };
    $redirect = static function ($location, $status) {
        throw new RuntimeException(wp_json_encode([$location, $status]));
    };
    add_action('do_faviconico', $listen, 10, 5);
    add_filter('get_site_icon_url', $icon, 10, 3);
    add_filter('wp_redirect', $redirect, 1, 2);
    $out = [];
    try {
        do_favicon();
        $out[] = 'returned';
    } catch (RuntimeException $caught) {
        $out[] = json_decode($caught->getMessage(), true);
    }
    $out[] = $heard;
    $other = static fn () => 'https://zz.example/icon.png';
    add_filter('get_site_icon_url', $other, 20);
    try {
        do_favicon();
    } catch (RuntimeException $caught) {
        $out[] = json_decode($caught->getMessage(), true);
    }
    remove_filter('get_site_icon_url', $other, 20);
    remove_action('do_faviconico', $listen, 10);
    remove_filter('get_site_icon_url', $icon, 10);
    remove_filter('wp_redirect', $redirect, 1);
    return $out;
})()));

$jsonp = static function ($callback, $message, $title = '', $args = []) {
    if ($callback === null) {
        unset($_GET['_jsonp']);
    } else {
        $_GET['_jsonp'] = $callback;
    }
    ob_start();
    @_jsonp_wp_die_handler($message, $title, $args + ['exit' => false]);
    $printed = (string) ob_get_clean();
    unset($_GET['_jsonp']);
    return $printed;
};
$error = new WP_Error('zz_first', 'First <b>', ['status' => 409, 'title' => 'Zz title']);
$error->add('zz_second', 'Second', 'string data');
$error->add('zz_third', 'Third');
$say('the JSONP die handler', [
    $jsonp('zz.cb', 'msg <b> "q"'),
    $jsonp('zz.cb', 'msg', 'Title', ['response' => 418, 'code' => 'zz_code']),
    $jsonp('cb', $error),
    $jsonp('cb', $error, '', ['response' => 401]),
    $jsonp(null, 'no callback'),
    $jsonp('', 'empty callback'),
    $jsonp('bad()', 'bad callback'),
    $jsonp('cb', ''),
    $jsonp('cb', 'x', 'T', ['response' => '404', 'charset' => 'zz']),
    $jsonp('cb', ['an', 'array']),
]);

$say('an extension\'s error described', array_map(static fn ($type) => wp_get_extension_error_description(['type' => $type, 'line' => 12, 'file' => '/zz/path/file.php', 'message' => 'Boom <b> & "q"']), [
    E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, 2048, E_RECOVERABLE_ERROR, E_DEPRECATED, E_USER_DEPRECATED, E_ALL, 3, 99, 0, '1', 'E_ERROR',
]));

$say('the fatal error handler enabled', (static function () {
    $out = [wp_is_fatal_error_handler_enabled()];
    foreach ([false, 'yes', 0, null, '0'] as $answer) {
        $said = static fn () => $answer;
        add_filter('wp_fatal_error_handler_enabled', $said);
        $out[] = wp_is_fatal_error_handler_enabled();
        remove_filter('wp_fatal_error_handler_enabled', $said);
    }
    return $out;
})());

$say('the file editor\'s scrape', (static function () {
    $request = $_REQUEST;
    $out = [];
    foreach ([[], ['wp_scrape_key' => 'ZZ key'], ['wp_scrape_key' => 'ZZ key', 'wp_scrape_nonce' => ''], ['wp_scrape_key' => '', 'wp_scrape_nonce' => 'n'], ['wp_scrape_key' => 'ZZ key', 'wp_scrape_nonce' => 'n'], ['wp_scrape_key' => '!!', 'wp_scrape_nonce' => 'n']] as $fields) {
        $_REQUEST = $fields;
        ob_start();
        $out[] = [wp_start_scraping_edited_file_errors(), (string) ob_get_clean()];
    }
    $_REQUEST = $request;
    foreach (['zz-key', 'Zz Key! <b>', ''] as $key) {
        error_clear_last();
        @trigger_error('zz warn', E_USER_WARNING);
        ob_start();
        $result = wp_finalize_scraping_edited_file_errors($key);
        $out[] = [$result, (string) ob_get_clean()];
    }
    error_clear_last();
    ob_start();
    wp_finalize_scraping_edited_file_errors('clean');
    $out[] = (string) ob_get_clean();
    return $out;
})());

// Last: the REST request this constant declares cannot be taken back.
$say('wp_die picks the JSONP handler', (static function () use ($dies) {
    $asked = static function () {
        $out = [];
        foreach ([null, 'zz.cb', 'bad()', '', 'a.b_c9'] as $callback) {
            if ($callback === null) {
                unset($_GET['_jsonp']);
            } else {
                $_GET['_jsonp'] = $callback;
            }
            $out[] = wp_is_jsonp_request();
        }
        $_GET['_jsonp'] = 'zz.cb';
        add_filter('rest_jsonp_enabled', '__return_false');
        $out[] = wp_is_jsonp_request();
        remove_filter('rest_jsonp_enabled', '__return_false');
        return $out;
    };
    $out = [$asked()];
    $_GET['_jsonp'] = 'zz.cb';
    $out[] = $dies(static fn () => wp_die('before REST'));
    define('REST_REQUEST', true);
    $out[] = $asked();
    $_GET['_jsonp'] = 'zz.cb';
    $out[] = $dies(static fn () => wp_die('jsonp'));
    $_GET['_jsonp'] = 'bad()';
    $out[] = $dies(static fn () => wp_die('bad callback'));
    $_GET['_jsonp'] = 'zz.cb';
    add_filter('rest_jsonp_enabled', '__return_false');
    $out[] = $dies(static fn () => wp_die('jsonp off'));
    remove_filter('rest_jsonp_enabled', '__return_false');
    unset($_GET['_jsonp']);
    $out[] = $dies(static fn () => wp_die('plain REST'));
    return $out;
})());

// The favicon request ends the request outright, so the log is printed as the run shuts down, after it.
$ended = true;
register_shutdown_function(static function () use (&$log, &$ended): void {
    $log[] = ['a favicon request ends it', $ended];
    echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
});
$_SERVER['REQUEST_URI'] = '/favicon.ico';
ob_start();
wp_favicon_request();
ob_end_clean();
$ended = false;
