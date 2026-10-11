<?php
/**
 * Small core classes left (probe small-classes2): the XML envelope legacy
 * admin-ajax handlers build, and the document it sends; WordPress's mailer
 * and its wording; the hooks object the HTTP API hands Requests, and the
 * actions it fires; SimplePie's old autoloader; the feed file fetched
 * through the HTTP API (answered by a pre_http_request filter, so nothing
 * leaves the machine); and the kses sanitizer feeds are cleaned with.
 * Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}

// The classes load with their features on the reference, only when asked.
if (!class_exists(WP_Ajax_Response::class)) {
    require_once ABSPATH . WPINC . '/class-wp-ajax-response.php';
}
if (!class_exists(WP_PHPMailer::class)) {
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
}
if (!class_exists(WP_HTTP_Requests_Hooks::class)) {
    require_once ABSPATH . WPINC . '/class-wp-http-requests-hooks.php';
}
if (!class_exists('SimplePie\\SimplePie')) {
    require_once ABSPATH . WPINC . '/class-simplepie.php';
}
if (!class_exists(WP_SimplePie_File::class)) {
    require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
}
if (!class_exists(WP_SimplePie_Sanitize_KSES::class)) {
    require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';
}
if (!class_exists(SimplePie_Autoloader::class)) {
    require_once ABSPATH . WPINC . '/SimplePie/autoloader.php';
}

$response = new WP_Ajax_Response();
$added = [
    'a tag' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => 5, 'data' => '<b>ZZ</b>', 'supplemental' => ['parents' => '1,2', 'zz' => '<i>x</i>']]),
    'a position' => $response->add(['what' => 'cat', 'action' => 'add-cat', 'id' => 7, 'position' => '-1 x<y>', 'data' => 'plain']),
    'an old id' => $response->add(['what' => 'comment', 'action' => 'edit', 'id' => 9, 'old_id' => 8]),
    'an error as the id' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => new WP_Error('zz_bad', 'ZZ went wrong', ['status' => 400])]),
    'an error with text data' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => new WP_Error('zz_text', 'ZZ text', 'ZZ <detail>')]),
    'an error with nested data' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => new WP_Error('zz_nested', 'ZZ nested', ['flag' => true, 'none' => null, 'zero' => 0, 'text' => 'a]]>b'])]),
    'an error with two codes' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => (static function () {
        $error = new WP_Error('zz_one', 'ZZ one', 5);
        $error->add('zz_two', 'ZZ two');
        $error->add('zz_two', 'ZZ two again');
        return $error;
    })()]),
    'an error with no data' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => new WP_Error('zz_plain', 'ZZ plain')]),
    'an error as the data' => $response->add(['what' => 'tag', 'action' => 'add-tag', 'id' => 3, 'data' => new WP_Error('zz_data', 'ZZ data')]),
    'the action from the request' => (static function () use ($response) {
        $_POST['action'] = 'zz-posted';
        $out = $response->add(['what' => 'meta', 'id' => 4]);
        unset($_POST['action']);
        return $out;
    })(),
    'from a query string' => $response->add('what=link&action=zz&id=11&data=ZZ+data'),
    'defaults, the action posted' => (static function () use ($response) {
        $_POST['action'] = 'zz-default';
        $out = $response->add();
        unset($_POST['action']);
        return $out;
    })(),
];
$say('ajax response added', $added);
$say('ajax response made with args', (static function () {
    $made = new WP_Ajax_Response(['what' => 'zz', 'action' => 'made', 'id' => 2]);
    $empty = new WP_Ajax_Response();
    return [count($made->responses), $made->responses, $empty->responses];
})());
// Sent through wp_die's ajax handler, stopped before it exits.
$say('ajax response sent', (static function () {
    $sent = new WP_Ajax_Response(['what' => 'zz', 'action' => 'sent', 'id' => 6, 'data' => 'ZZ']);
    $sent->add(['what' => 'zz', 'action' => 'sent', 'id' => 7]);
    $died = [];
    $handler = static function () use (&$died) {
        return static function ($message = '', $title = '', $args = []) use (&$died) {
            $died[] = [$message, $title];
            throw new RuntimeException('zz-died');
        };
    };
    add_filter('wp_doing_ajax', '__return_true');
    add_filter('wp_die_ajax_handler', $handler);
    ob_start();
    try {
        $sent->send();
    } catch (RuntimeException $e) {
        $died[] = $e->getMessage();
    }
    $out = (string) ob_get_clean();
    remove_filter('wp_die_ajax_handler', $handler);
    remove_filter('wp_doing_ajax', '__return_true');
    return ['printed' => $out, 'died' => $died];
})());

$mailer = new WP_PHPMailer(true);
$exceptions = Closure::bind(fn () => $this->exceptions, $mailer, PHPMailer\PHPMailer\PHPMailer::class)();
$quiet = new WP_PHPMailer();
$say('mailer', [
    'its class' => get_parent_class($mailer),
    'exceptions' => [$exceptions, Closure::bind(fn () => $this->exceptions, $quiet, PHPMailer\PHPMailer\PHPMailer::class)()],
    'its wording' => $mailer->getTranslations(),
    'a language set' => WP_PHPMailer::setLanguage('de'),
    'the wording after' => $mailer->getTranslations() === $quiet->getTranslations(),
    'set with a path' => WP_PHPMailer::setLanguage('en', '/zz/none/'),
]);

$hooks = new WP_HTTP_Requests_Hooks('https://example.com/zz', ['method' => 'GET', 'timeout' => 3]);
$heard = [];
$hooks->register('requests.before_request', static function (&$url) use (&$heard) {
    $heard[] = ['registered', $url];
    $url .= '?zz=1';
});
$listener = static function (...$args) use (&$heard) {
    $heard[] = ['requests-requests.before_request', $args];
};
add_action('requests-requests.before_request', $listener, 10, 5);
$curl = static function ($handle, $request, $url) use (&$heard) {
    $heard[] = ['http_api_curl', $handle, $request, $url];
};
add_action('http_api_curl', $curl, 10, 3);
$sendListener = static function (...$args) use (&$heard) {
    $heard[] = ['requests-curl.before_send', $args];
};
add_action('requests-curl.before_send', $sendListener, 10, 5);
$url = 'https://example.com/zz';
$dispatched = [
    'a hook with a callback' => $hooks->dispatch('requests.before_request', [&$url]),
    'the url after' => $url,
];
$handle = 'zz-handle';
$dispatched['before send'] = $hooks->dispatch('curl.before_send', [&$handle]);
$dispatched['no callbacks'] = $hooks->dispatch('requests.zz_none');
$dispatched['heard'] = $heard;
$dispatched['its fields'] = Closure::bind(fn () => [$this->url, $this->request], $hooks, WP_HTTP_Requests_Hooks::class)();
remove_action('requests-requests.before_request', $listener, 10);
remove_action('http_api_curl', $curl, 10);
remove_action('requests-curl.before_send', $sendListener, 10);
$say('requests hooks', $dispatched);

$autoloader = new SimplePie_Autoloader();
$say('simplepie autoloader', [
    'a loaded class' => $autoloader->autoload('SimplePie_Item'),
    'its class' => get_class($autoloader),
]);

// The feed file: the HTTP API asked, and answered by the filter.
$asked = [];
$answer = null;
$fake = static function ($pre, $args, $url) use (&$asked, &$answer) {
    $asked[] = [$url, array_intersect_key($args, array_flip(['method', 'timeout', 'redirection', 'headers', 'user-agent', 'reject_unsafe_urls', 'stream', 'decompress', 'httpversion', 'blocking']))];
    return $answer;
};
add_filter('pre_http_request', $fake, 10, 3);
$fields = static fn ($file) => ['url' => $file->url, 'useragent' => $file->useragent, 'success' => $file->success, 'headers' => $file->headers, 'body' => $file->body, 'status_code' => $file->status_code, 'error' => $file->error, 'timeout' => $file->timeout, 'method' => $file->method];
$files = [];
$answer = ['headers' => ['Content-Type' => 'application/rss+xml', 'X-ZZ' => ['a', 'b']], 'body' => '<rss>zz</rss>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
$files['fetched'] = $fields(new WP_SimplePie_File('https://example.com/zz-feed', 7, 3, ['Accept' => 'application/rss+xml'], 'ZZ agent'));
$files['defaults'] = $fields(new WP_SimplePie_File('https://example.com/zz-feed-2'));
$answer = ['headers' => [], 'body' => 'gone', 'response' => ['code' => 404, 'message' => 'Not Found'], 'cookies' => [], 'filename' => null];
$files['not found'] = $fields(new WP_SimplePie_File('https://example.com/zz-missing'));
$answer = new WP_Error('http_request_failed', 'ZZ could not connect');
$files['an error'] = $fields(new WP_SimplePie_File('https://example.com/zz-down'));
remove_filter('pre_http_request', $fake, 10);
$files['asked'] = $asked;
$say('feed file', $files);

// As a feed hands it its registry.
$sanitize = new WP_SimplePie_Sanitize_KSES();
$sanitize->set_registry(new SimplePie\Registry());
$html = '<p onclick="zz()">ZZ <a href="javascript:x">link</a><script>bad()</script> &amp; <em>more</em></p>';
$say('feed sanitizer', [
    'html' => $sanitize->sanitize($html, SIMPLEPIE_CONSTRUCT_HTML),
    'xhtml' => $sanitize->sanitize('<div xmlns="http://www.w3.org/1999/xhtml"><b onmouseover="x">ZZ</b></div>', SIMPLEPIE_CONSTRUCT_XHTML),
    'maybe html, with markup' => $sanitize->sanitize($html, SIMPLEPIE_CONSTRUCT_MAYBE_HTML),
    'maybe html, plain' => $sanitize->sanitize('ZZ & "plain" <', SIMPLEPIE_CONSTRUCT_MAYBE_HTML),
    'text' => $sanitize->sanitize('<b>ZZ</b> & "q"', SIMPLEPIE_CONSTRUCT_TEXT),
    'an address' => $sanitize->sanitize('/zz/path?a=1&b=2', SIMPLEPIE_CONSTRUCT_IRI, 'https://example.com/feed/'),
    'base64 html' => $sanitize->sanitize(base64_encode('<i>ZZ</i><script>x</script>'), SIMPLEPIE_CONSTRUCT_BASE64 | SIMPLEPIE_CONSTRUCT_HTML),
    'none' => $sanitize->sanitize('<b>ZZ</b>', SIMPLEPIE_CONSTRUCT_NONE),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
