<?php
/**
 * Minn\Http over a real wire: starts tests/fixtures/http/server.php on a
 * free loopback port and sends to it through curl, proving what the unit
 * cases cannot (they answer from the fake): the private-address refusal
 * after DNS, the size cap with and without a Content-Length, timeouts,
 * redirects curl never sees, cookies and repeated headers as a server
 * sends them, and Download's messages. Needs no oracle and no network.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
define('ABSPATH', __DIR__ . '/fixtures/');
define('MINN_ENGINE_DIR', $root . '/public/minn');
define('MINN_ENGINE_VERSION', 'test');
require MINN_ENGINE_DIR . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/fixtures/http/server.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(static fn () => proc_terminate($server));
for ($try = 0; $try < 50 && @fsockopen('127.0.0.1', $port) === false; $try++) {
    usleep(50000);
}
$base = "http://127.0.0.1:{$port}";
$local = ['127.0.0.1'];

$reply = Minn\Http::get("{$base}/echo", query: ['q' => 'two words'], private: $local);
$echo = (array) $reply->json();
$check('a GET reaches a listed private host and reads back as JSON', $reply->ok() && ($echo['method'] ?? '') === 'GET' && ($echo['query'] ?? null) === ['q' => 'two words'] && ($echo['agent'] ?? '') === 'Minn/test', $reply->body);

$reply = Minn\Http::get("{$base}/echo");
$check('the same host is refused when it is not listed', $reply->failed() && $reply->errno === 0 && $reply->error === '127.0.0.1 is a private address. List it in private: to reach it.', (string) $reply->error);

$reply = Minn\Http::get("http://localhost:{$port}/echo");
$check('a name is judged by where it resolves', $reply->failed() && $reply->errno === 0 && str_starts_with((string) $reply->error, 'localhost resolves to 127.0.0.1'), (string) $reply->error);
$reply = Minn\Http::get("http://localhost:{$port}/echo", private: ['localhost']);
$check('and reaches it once listed', $reply->ok(), (string) $reply->error);

$echo = (array) Minn\Http::post("{$base}/echo", json: ['email' => 'a@b.c'], private: $local)->json();
$check('json: arrives as a JSON body with its type', ($echo['body'] ?? '') === '{"email":"a@b.c"}' && ($echo['type'] ?? '') === 'application/json', json_encode($echo));
$echo = (array) Minn\Http::post("{$base}/echo", form: ['email' => 'a@b.c'], private: $local)->json();
$check('form: arrives as a form PHP parses', ($echo['form'] ?? null) === ['email' => 'a@b.c'], json_encode($echo));

$reply = Minn\Http::post("{$base}/redirect?to=/echo", form: ['a' => '1'], headers: ['Authorization' => 'Bearer t'], private: $local);
$echo = (array) $reply->json();
$check('a redirect is followed hop by hop: the POST becomes a GET, same-origin credentials ride along', ($echo['method'] ?? '') === 'GET' && $reply->url === "{$base}/echo" && ($echo['authorization'] ?? '') === 'Bearer t', json_encode([$reply->url, $echo]));
$reply = Minn\Http::get("http://localhost:{$port}/redirect?to=" . rawurlencode("{$base}/echo"), private: ['localhost']);
$check('a redirect to a private address the caller did not list is refused at that hop', $reply->failed() && $reply->errno === 0 && $reply->error === '127.0.0.1 is a private address. List it in private: to reach it.', (string) $reply->error);
$reply = Minn\Http::get("{$base}/redirect?to=" . rawurlencode("http://localhost:{$port}/echo"), private: $local);
$check('listing an address lets any name that resolves to it through', $reply->ok(), (string) $reply->error);

$reply = Minn\Http::get("{$base}/big?bytes=200000&announce=1", private: $local, maxBytes: 1000);
$check('a body over maxBytes fails when its length is announced', $reply->failed() && $reply->errno === CURLE_FILESIZE_EXCEEDED && $reply->error === 'The response is larger than the 1000 bytes allowed.', "{$reply->errno} {$reply->error}");
$reply = Minn\Http::get("{$base}/big?bytes=200000", private: $local, maxBytes: 1000);
$check('and when it is not', $reply->failed() && $reply->errno === CURLE_FILESIZE_EXCEEDED, "{$reply->errno} {$reply->error}");
$reply = Minn\Http::get("{$base}/big?bytes=500", private: $local, maxBytes: 1000);
$check('a body under maxBytes arrives whole', $reply->ok() && strlen($reply->body) === 500, (string) strlen($reply->body));

$reply = Minn\Http::get("{$base}/cookies", private: $local);
$check('cookies and repeated headers read as a server sent them', $reply->cookie('flagged') === 'two words' && $reply->cookie('plain') === 'one' && $reply->header('X-Multi') === 'one, two' && $reply->head[0] === 'HTTP/1.1 200 OK', json_encode([$reply->cookies, $reply->headers]));

$reply = Minn\Http::head("{$base}/echo", private: $local);
$check('a HEAD brings the status and headers without a body', $reply->code === 200 && $reply->body === '' && $reply->header('content-type') === 'application/json', (string) $reply->code);

$reply = Minn\Http::get("{$base}/nowhere", private: $local);
$check('a 404 is a response: not failed, not ok', !$reply->failed() && !$reply->ok() && $reply->code === 404 && $reply->body === 'none', (string) $reply->code);

$refusal = static function (string $url): string {
    try {
        Minn\Http\Download::https($url, 1048576);
        return 'downloaded';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
};
$check('Download keeps to https', $refusal("{$base}/echo") === 'Downloads are fetched over https only.', $refusal("{$base}/echo"));
$check('Download refuses a private address in its own words', $refusal("https://127.0.0.1:{$port}/echo") === 'The download led to 127.0.0.1, which is not a host this package may come from.', $refusal("https://127.0.0.1:{$port}/echo"));

$started = microtime(true);
$reply = Minn\Http::get("{$base}/slow?ms=2000", timeout: 0.5, private: $local);
$check('a timeout ends the exchange as a transport failure', $reply->failed() && $reply->errno === CURLE_OPERATION_TIMEDOUT && microtime(true) - $started < 1.5, "{$reply->errno} {$reply->error}");

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
