<?php
/**
 * HTTPS detection and migration, and http.php's host helpers (probe
 * https-helpers): the PHP_URL constants named, a request host allowed when
 * it is the site's own or a redirect host, the errors a check of the https
 * home finds (its requests answered by a pre_http_request filter, so no
 * network), whether a page's HTML came from this site, the migration flag
 * kept as the home URL changes, and the URLs moved to https (the writes
 * caught by filters, so the stored URLs never change). The options the
 * probe touches are restored in place.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$saved = [];
foreach (['https_detection_errors', 'https_migration_required'] as $name) {
    $saved[$name] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
}
register_shutdown_function(static function () use ($saved, $wpdb): void {
    foreach ($saved as $name => $row) {
        if ($row === null) {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
        } else {
            $wpdb->update($wpdb->options, $row, ['option_name' => $name]);
        }
        wp_cache_delete($name, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use ($host, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($host, '{host}', $value) : $value;
};
$kind = static fn ($value) => is_wp_error($value) ? ['WP_Error', $value->get_error_codes()] : $value;

$say('the hooks', [
    has_filter('http_request_host_is_external', 'allowed_http_request_hosts'),
    has_filter('http_request_host_is_external', 'ms_allowed_http_request_hosts'),
    has_action('update_option_home', 'wp_update_https_migration_required'),
    has_action('wp_https_detection', 'wp_update_https_detection_errors'),
    has_filter('cron_request', 'wp_cron_conditionally_prevent_sslverify'),
]);

$say('a PHP_URL constant named', array_map(static fn ($constant) => _wp_translate_php_url_constant_to_key($constant), [PHP_URL_SCHEME, PHP_URL_HOST, PHP_URL_PORT, PHP_URL_USER, PHP_URL_PASS, PHP_URL_PATH, PHP_URL_QUERY, PHP_URL_FRAGMENT, -1, 99, '1', null, true, false, '']));
$say('a parsed URL\'s component', array_map(static fn ($component) => _get_component_from_parsed_url_array(['scheme' => 'https', 'host' => 'zz.example', 'path' => '/p'], $component), [-1, '-1', 1, '1', true, false, 0, 5, 99, null, '']));

$allowed = static fn ($external, $name) => allowed_http_request_hosts($external, $name);
$say('a request host allowed', $mask([
    $allowed(false, $host), $allowed(false, strtoupper($host)), $allowed(false, 'zz.example'), $allowed(true, 'zz.example'), $allowed('', $host), $allowed(0, 'zz.example'),
    $allowed(false, 'www.' . $host), $allowed(false, ''), $allowed('yes', 'zz.example'), $allowed(false, $host . ':8080'), $allowed(false, $host . '/zz'), $allowed(false, 'zz.example@' . $host), $allowed(false, $host . '@zz.example'), $allowed(null, $host),
    (static function () use ($allowed) {
        $more = static fn ($hosts) => array_merge($hosts, ['zz.allowed']);
        add_filter('allowed_redirect_hosts', $more);
        $out = [$allowed(false, 'zz.allowed'), $allowed(false, 'ZZ.allowed'), $allowed(false, 'zz.example')];
        remove_filter('allowed_redirect_hosts', $more);
        $out[] = $allowed(false, 'zz.allowed');
        return $out;
    })(),
    // Past an external host the multisite check asks the network, which a single site does not load.
    [ms_allowed_http_request_hosts(true, 'zz.example'), ms_allowed_http_request_hosts('yes', $host), ms_allowed_http_request_hosts(1, '')],
    apply_filters('http_request_host_is_external', false, $host, 'https://' . $host . '/'),
    apply_filters('http_request_host_is_external', false, 'zz.example', 'https://zz.example/'),
]));

$say('a redirect with credentials', $mask(array_map(static fn ($url) => wp_validate_redirect($url, 'fallback'), [
    'http://zz.example@' . $host, 'http://user:pw@' . $host . '/x', 'http://' . $host . '@zz.example', 'https://u@' . $host, '//u@' . $host . '/', 'http://:@' . $host, 'http://@' . $host, 'http://u:@' . $host, 'http://:p@' . $host, 'ftp://u@' . $host,
])));

// The engine's block-theme head prints these links itself, so the two wp_head actions the next checks ask about are put in place.
add_action('wp_head', 'rsd_link', 10, 0);
add_action('wp_head', 'rest_output_link_wp_head', 10, 0);
$page = static function (Closure $head): string {
    ob_start();
    $head();
    return (string) ob_get_clean();
};
$rsd = $page(static fn () => rsd_link());
$rest = $page(static fn () => rest_output_link_wp_head());
$local = static fn ($html) => wp_is_local_html_output($html);
$say('HTML from this site', [
    $local('<html><head>' . $rsd . '</head></html>'),
    $local('<html><head>' . $rest . '</head></html>'),
    $local('<html><head><link rel="EditURI" type="application/rsd+xml" href="https://zz.example/xmlrpc.php?rsd"></head></html>'),
    $local('<p>nothing</p>'),
    $local(''),
    $local(str_replace('https://', 'http://', $rsd)),
    $local(esc_url(site_url('xmlrpc.php?rsd', 'rpc'))),
    $local(site_url('xmlrpc.php?rsd', 'rpc')),
    $local(get_rest_url()),
    $local(str_replace('https://', '', site_url('xmlrpc.php?rsd', 'rpc'))),
    $local(str_replace('https://', '//', site_url('xmlrpc.php?rsd', 'rpc'))),
    $local(str_replace('https://', 'HTTPS://', site_url('xmlrpc.php?rsd', 'rpc'))),
    $local(strtoupper(site_url('xmlrpc.php?rsd', 'rpc'))),
    $local(site_url('xmlrpc.php?rsd', 'rpc') . '=1&more'),
    $local(str_replace('https://', 'ftp://', site_url('xmlrpc.php?rsd', 'rpc'))),
    (static function () use ($local, $rsd, $rest) {
        $hooks = [has_action('wp_head', 'rsd_link'), has_action('wp_head', 'rest_output_link_wp_head')];
        remove_action('wp_head', 'rsd_link');
        $out = [$local($rsd), $local($rest), $local('<p>x</p>')];
        $out[] = [$local(get_rest_url()), $local(str_replace('https://', '', get_rest_url())), $local(str_replace('https://', 'http://', get_rest_url())), $local(rtrim(get_rest_url(), '/')), $local(get_rest_url(null, 'wp/v2')), $local(str_replace('https://', '//', get_rest_url())), $local(strtoupper(get_rest_url()))];
        remove_action('wp_head', 'rest_output_link_wp_head');
        $out[] = $local($rsd);
        $out[] = $local($rest);
        $out[] = $local('<p>x</p>');
        add_action('wp_head', 'rsd_link', $hooks[0]);
        $out[] = $local($rest);
        $out[] = $local($rsd);
        add_action('wp_head', 'rest_output_link_wp_head', $hooks[1]);
        return $out;
    })(),
]);

$requests = [];
$answers = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$requests, &$answers, $mask) {
    $requests[] = $mask([$url, $args['method'] ?? null, $args['sslverify'] ?? null, $args['headers'] ?? null, $args['redirection'] ?? null, $args['timeout'] ?? null]);
    $answer = array_shift($answers);
    if ($answer === null) {
        return $pre;
    }
    if (is_string($answer)) {
        return new WP_Error('http_request_failed', $answer);
    }
    if ($answer[0] === 'error') {
        return new WP_Error($answer[1], $answer[2]);
    }
    return ['headers' => [], 'body' => $answer[1], 'response' => ['code' => $answer[0], 'message' => $answer[2] ?? 'zz'], 'cookies' => [], 'filename' => null];
}, 10, 3);
$detected = static function (array $script) use (&$requests, &$answers, $mask) {
    $requests = [];
    $answers = $script;
    $errors = wp_get_https_detection_errors();
    return [$mask($errors), $requests];
};
$body = '<html><head>' . $rsd . '</head></html>';
$say('the https home checked', [
    $detected([[200, $body]]),
    $detected([[200, '<p>elsewhere</p>']]),
    $detected([[500, $body]]),
    $detected([[301, $body]]),
    $detected([[200, '']]),
    $detected(['cURL error 60: SSL certificate problem', [200, $body]]),
    $detected(['cURL error 60: SSL certificate problem', [200, '<p>elsewhere</p>']]),
    $detected([['error', 'zz_other', 'other'], [200, $body]]),
    $detected([['error', 'zz_other', 'other'], ['error', 'zz_more', 'more']]),
    $detected([[500, $body, ''], [200, $body]]),
    $detected([[404, '<p>elsewhere</p>', 'Not Found']]),
    (static function () use ($detected) {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'rest_output_link_wp_head');
        $out = $detected([[200, '<p>elsewhere</p>']]);
        add_action('wp_head', 'rsd_link');
        add_action('wp_head', 'rest_output_link_wp_head', 10, 0);
        return $out;
    })(),
    $detected(['cURL error 60: SSL certificate problem', 'cURL error 7: refused']),
    $detected(['cURL error 60: SSL certificate problem', [404, $body]]),
    $detected(['cURL error 7: refused', [200, $body]]),
]);
$say('the check filtered first', (static function () use ($detected) {
    $out = [];
    foreach ([['zz' => ['said']], [], new WP_Error('zz_code', 'zz said'), 'text', false, new WP_Error(), (static function () {
        $error = new WP_Error('zz_one', 'first');
        $error->add('zz_one', 'second', ['data']);
        $error->add('zz_two', 'third');
        return $error;
    })()] as $answer) {
        $pre = static fn () => $answer;
        add_filter('pre_wp_get_https_detection_errors', $pre);
        $result = $detected([[200, '<p>elsewhere</p>']]);
        $result[0] = is_wp_error($result[0]) ? ['WP_Error', $result[0]->get_error_codes()] : $result[0];
        $out[] = $result;
        remove_filter('pre_wp_get_https_detection_errors', $pre);
    }
    $heard = [];
    $listen = static function (...$args) use (&$heard) {
        $heard[] = $args;
        return new WP_Error();
    };
    add_filter('pre_wp_get_https_detection_errors', $listen, 10, 5);
    wp_get_https_detection_errors();
    remove_filter('pre_wp_get_https_detection_errors', $listen, 10);
    $out[] = $heard;
    return $out;
})());

$supported = static function (array $script) use (&$requests, &$answers) {
    $requests = [];
    $answers = $script;
    $result = wp_is_https_supported();
    return [$result, count($requests), get_option('https_detection_errors')];
};
$say('https supported', (static function () use ($supported, $body) {
    $out = [$supported([[200, $body]]), $supported([[200, '<p>elsewhere</p>']])];
    $pre = static fn () => [];
    add_filter('pre_wp_get_https_detection_errors', $pre);
    $out[] = $supported([[200, '<p>elsewhere</p>']]);
    remove_filter('pre_wp_get_https_detection_errors', $pre);
    $said = static fn () => ['zz' => ['x']];
    add_filter('pre_wp_get_https_detection_errors', $said);
    $out[] = $supported([[200, $body]]);
    remove_filter('pre_wp_get_https_detection_errors', $said);
    $error = static fn () => new WP_Error('zz', 'x');
    add_filter('pre_wp_get_https_detection_errors', $error);
    $out[] = $supported([]);
    remove_filter('pre_wp_get_https_detection_errors', $error);
    return $out;
})());

$say('the stored detection errors', (static function () use (&$requests, &$answers, $body, $mask) {
    $heard = [];
    $listen = static function ($function, $replacement, $version) use (&$heard) {
        $heard[] = [$function, $replacement, $version];
    };
    add_action('deprecated_function_run', $listen, 10, 3);
    add_filter('deprecated_function_trigger_error', '__return_false');
    $out = [];
    foreach ([[[200, '<p>elsewhere</p>']], [[200, $body]], ['cURL error 60: x', 'cURL error 7: y']] as $script) {
        $requests = [];
        $answers = $script;
        $out[] = [wp_update_https_detection_errors(), count($requests), $mask(get_option('https_detection_errors'))];
    }
    $pre = static fn () => ['zz' => ['said']];
    add_filter('pre_wp_get_https_detection_errors', $pre);
    $requests = [];
    $answers = [[200, $body]];
    $out[] = [wp_update_https_detection_errors(), count($requests), get_option('https_detection_errors')];
    remove_filter('pre_wp_get_https_detection_errors', $pre);
    $pre = static fn () => new WP_Error('zz_code', 'zz said');
    add_filter('pre_wp_get_https_detection_errors', $pre);
    $out[] = [wp_update_https_detection_errors(), get_option('https_detection_errors')];
    remove_filter('pre_wp_get_https_detection_errors', $pre);
    remove_action('deprecated_function_run', $listen, 10);
    remove_filter('deprecated_function_trigger_error', '__return_false');
    $out[] = $heard;
    return $out;
})());

$say('the migration flag', (static function () use ($host) {
    $flag = static fn () => [get_option('https_migration_required', 'unset'), wp_load_alloptions()['https_migration_required'] ?? 'not autoloaded'];
    $out = [];
    delete_option('https_migration_required');
    foreach ([
        ['http://' . $host, 'https://' . $host], ['http://' . $host, 'https://' . $host . '/'], ['http://' . $host . '/', 'https://' . $host], ['https://' . $host, 'https://' . $host],
        ['http://' . $host, 'http://' . $host], ['http://zz.example', 'https://' . $host], ['http://' . $host . '/sub', 'https://' . $host . '/sub'], ['HTTP://' . $host, 'https://' . $host],
        ['https://' . $host, 'http://' . $host], ['http://' . $host, 'https://zz.example'], ['', 'https://' . $host], ['http://' . $host, ''],
        ['http://' . $host . '/http://x', 'https://' . $host . '/https://x'], ['http://' . $host . '//', 'https://' . $host], ['', ''], ['/', ''], [null, null], ['http://' . $host, 'https://' . $host . '?q'],
    ] as [$old, $new]) {
        $out[] = [str_replace($host, '{host}', $old . ' > ' . $new), wp_update_https_migration_required($old, $new), $flag()];
    }
    update_option('https_migration_required', true);
    $out[] = ['set first', wp_update_https_migration_required('http://' . $host, 'http://zz.example'), $flag()];
    update_option('https_migration_required', true);
    $out[] = ['set first', wp_update_https_migration_required('http://' . $host, 'https://' . $host), $flag()];
    delete_option('https_migration_required');
    add_filter('pre_option_fresh_site', '__return_true');
    $out[] = ['fresh', wp_update_https_migration_required('http://' . $host, 'https://' . $host), $flag()];
    update_option('https_migration_required', true);
    $out[] = ['fresh after set', wp_update_https_migration_required('http://' . $host, 'https://' . $host), $flag()];
    delete_option('https_migration_required');
    remove_filter('pre_option_fresh_site', '__return_true');
    $one = static fn () => '1';
    add_filter('pre_option_fresh_site', $one);
    $out[] = ['fresh 1', wp_update_https_migration_required('http://' . $host, 'https://' . $host), $flag()];
    remove_filter('pre_option_fresh_site', $one);
    update_option('https_migration_required', true);
    // Read once installing is over: while it lasts, the reference's get_option() skips its cache.
    wp_installing(true);
    $result = wp_update_https_migration_required('http://' . $host, 'https://zz.example');
    wp_installing(false);
    $out[] = ['installing', $result, $flag()];
    $out[] = ['installed', wp_update_https_migration_required('http://' . $host, 'https://zz.example'), $flag()];
    $out[] = ['as an action', (static function () use ($host, $flag) {
        do_action('update_option_home', 'http://' . $host, 'https://' . $host, 'home');
        return $flag();
    })()];
    delete_option('https_migration_required');
    return $out;
})());

$say('the URLs moved to https', (static function () use ($host, $mask) {
    $fake = [];
    $writes = [];
    $read = static function ($value, $option) use (&$fake) {
        return $fake[$option] ?? $value;
    };
    $write = static function ($value, $old, $option) use (&$fake, &$writes) {
        $writes[] = [$option, $value];
        $fake[$option] = $value;
        return $old;
    };
    foreach (['home', 'siteurl'] as $option) {
        add_filter("option_{$option}", $read, 99, 2);
        add_filter("pre_update_option_{$option}", $write, 99, 3);
    }
    $run = static function (array $start) use (&$fake, &$writes, $mask) {
        $fake = $start;
        $writes = [];
        $result = wp_update_urls_to_https();
        return $mask([$result, $writes, $fake]);
    };
    $out = [
        $run([]),
        $run(['home' => 'http://' . $host, 'siteurl' => 'http://' . $host]),
        $run(['home' => 'http://' . $host]),
        $run(['siteurl' => 'http://' . $host . '/wp']),
        $run(['home' => 'http://' . $host . '/http://x', 'siteurl' => 'http://' . $host]),
        $run(['home' => 'ftp://' . $host, 'siteurl' => 'http://' . $host]),
    ];
    $insecure = static fn ($url) => str_replace('https://', 'http://', $url);
    add_filter('home_url', $insecure);
    $out[] = $run(['home' => 'http://' . $host, 'siteurl' => 'http://' . $host]);
    remove_filter('home_url', $insecure);
    foreach (['home', 'siteurl'] as $option) {
        remove_filter("option_{$option}", $read, 99);
        remove_filter("pre_update_option_{$option}", $write, 99);
    }
    $out[] = $mask([get_option('home'), get_option('siteurl')]);
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
