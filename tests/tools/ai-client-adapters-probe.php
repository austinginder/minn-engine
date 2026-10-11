<?php
/**
 * The AI client's adapters (probe ai-client-adapters): the cache over the
 * object cache, the event dispatcher's hooks, the HTTP client over the HTTP
 * API (requests caught by pre_http_request, answered by the probe), the
 * client discovery strategy, and the request options they take. Nothing
 * leaves the machine; the cache keys are the probe's own and removed.
 */

use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$fail = static fn (Throwable $e) => ['threw' => get_class($e), 'message' => $e->getMessage()];

// The cache.
$cache = new WP_AI_Client_Cache();
$out = ['a cache' => $cache instanceof WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface];
$out['set'] = $cache->set('zz-ai-a', ['x' => 1]);
$out['get'] = $cache->get('zz-ai-a');
$out['missing'] = [$cache->get('zz-ai-missing'), $cache->get('zz-ai-missing', 'fallback')];
$out['has'] = [$cache->has('zz-ai-a'), $cache->has('zz-ai-missing')];
$groups = [];
foreach (['wp-ai-client', 'wp_ai_client', 'ai-client', 'ai_client', 'default', ''] as $group) {
    $groups[$group] = wp_cache_get('zz-ai-a', $group) !== false;
}
$out['object cache group'] = array_keys(array_filter($groups));
$out['a null value'] = [$cache->set('zz-ai-null', null), $cache->has('zz-ai-null'), $cache->get('zz-ai-null', 'fallback')];
$out['false'] = [$cache->set('zz-ai-false', false), $cache->has('zz-ai-false'), $cache->get('zz-ai-false', 'fallback')];
$out['ttl'] = [
    'seconds' => [$cache->set('zz-ai-ttl', 1, 60), $cache->get('zz-ai-ttl')],
    'an interval' => [$cache->set('zz-ai-interval', 2, new DateInterval('PT1H')), $cache->get('zz-ai-interval')],
    'zero' => [$cache->set('zz-ai-zero', 3, 0), $cache->has('zz-ai-zero')],
    'negative' => [$cache->set('zz-ai-ttl', 4, -1), $cache->has('zz-ai-ttl')],
];
$out['several'] = [
    'set' => $cache->setMultiple(['zz-ai-b' => 'B', 'zz-ai-c' => 'C']),
    'get' => $cache->getMultiple(['zz-ai-b', 'zz-ai-c', 'zz-ai-d'], 'none'),
    'from an iterator' => $cache->getMultiple(new ArrayIterator(['zz-ai-b']), 'none'),
    'set from a generator' => $cache->setMultiple((static function () {
        yield 'zz-ai-e' => 'E';
    })()),
    'delete' => $cache->deleteMultiple(['zz-ai-b', 'zz-ai-missing']),
    'after' => [$cache->has('zz-ai-b'), $cache->has('zz-ai-c'), $cache->get('zz-ai-e')],
];
$out['delete'] = [$cache->delete('zz-ai-a'), $cache->delete('zz-ai-missing'), $cache->has('zz-ai-a')];
$out['clear'] = [$cache->clear(), $cache->has('zz-ai-c'), $cache->has('zz-ai-e')];
$say('the cache', $out);
foreach (['zz-ai-a', 'zz-ai-null', 'zz-ai-false', 'zz-ai-ttl', 'zz-ai-interval', 'zz-ai-zero', 'zz-ai-b', 'zz-ai-c', 'zz-ai-e'] as $key) {
    $cache->delete($key);
}

// The event dispatcher: which hook each event fires, and what it hands back.
if (!class_exists('Zz\\AiProbe\\BeforeThingEvent', false)) {
    eval('namespace Zz\\AiProbe; class BeforeThingEvent { public $seen = 0; } class AfterGenerateResultEvent {} class Plain {} class HTTPRequestSentEvent {} class event {}');
}
$dispatcher = new WP_AI_Client_Event_Dispatcher();
$heard = [];
$listen = static function (...$args) use (&$heard) {
    if (str_contains((string) current_filter(), 'ai')) {
        $heard[] = [current_filter(), count($args), is_object($args[0] ?? null) ? get_class($args[0]) : get_debug_type($args[0] ?? null)];
    }
    return $args[0] ?? null;
};
$events = [];
foreach (['Zz\\AiProbe\\BeforeThingEvent', 'Zz\\AiProbe\\AfterGenerateResultEvent', 'Zz\\AiProbe\\Plain', 'Zz\\AiProbe\\HTTPRequestSentEvent', 'Zz\\AiProbe\\event', 'stdClass'] as $class) {
    $heard = [];
    $event = new $class();
    add_filter('all', $listen, 10, 99);
    $returned = $dispatcher->dispatch($event);
    remove_filter('all', $listen, 10);
    $events[$class] = ['same object back' => $returned === $event, 'heard' => $heard];
}
$say('the event dispatcher', ['a dispatcher' => $dispatcher instanceof WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface, 'events' => $events]);

// The HTTP client: what reaches the HTTP API, and the response it makes.
$factory = new Psr17Factory();
$client = new WP_AI_Client_HTTP_Client($factory, $factory);
$sent = [];
$answer = ['headers' => ['content-type' => 'application/json', 'x-zz' => ['1', '2']], 'body' => '{"ok":true}', 'response' => ['code' => 201, 'message' => 'Created'], 'cookies' => [], 'filename' => null];
$catch = static function ($pre, $args, $url) use (&$sent, &$answer) {
    $keep = array_intersect_key($args, array_flip(['method', 'timeout', 'redirection', 'httpversion', 'blocking', 'headers', 'body', 'compress', 'decompress', 'sslverify', 'stream', 'filename', 'limit_response_size', 'reject_unsafe_urls']));
    ksort($keep);
    $sent[] = ['url' => $url, 'args' => $keep];
    return $answer;
};
add_filter('pre_http_request', $catch, 10, 3);
$request = $factory->createRequest('POST', 'https://zz-ai.example/v1/chat?a=1')
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('X-Many', ['a', 'b'])
    ->withBody($factory->createStream('{"q":1}'));
$describe = static fn ($response) => [
    'status' => $response->getStatusCode(), 'reason' => $response->getReasonPhrase(), 'headers' => $response->getHeaders(),
    'body' => (string) $response->getBody(), 'version' => $response->getProtocolVersion(), 'a response' => $response instanceof WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface,
];
$http = ['a client' => [$client instanceof WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface, $client instanceof WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface]];
$http['a POST'] = $describe($client->sendRequest($request));
$options = new RequestOptions();
$options->setTimeout(12.5);
$options->setConnectTimeout(3.0);
$options->setMaxRedirects(0);
$http['with options'] = $describe($client->sendRequestWithOptions($request, $options));
$more = new RequestOptions();
$more->setMaxRedirects(4);
$http['with redirects allowed'] = $describe($client->sendRequestWithOptions($factory->createRequest('GET', 'https://zz-ai.example/v1/models'), $more));
$answer = ['headers' => [], 'body' => '', 'response' => ['code' => 204, 'message' => ''], 'cookies' => [], 'filename' => null];
$http['no content'] = $describe($client->sendRequest($factory->createRequest('DELETE', 'https://zz-ai.example/v1/x')));
$answer = new WP_Error('http_request_failed', 'ZZ refused');
try {
    $client->sendRequest($factory->createRequest('GET', 'https://zz-ai.example/v1/down'));
    $http['an error'] = 'no exception';
} catch (Throwable $e) {
    $http['an error'] = $fail($e) + ['code' => $e->getCode(), 'a client exception' => $e instanceof WordPress\AiClientDependencies\Psr\Http\Client\ClientExceptionInterface];
}
remove_filter('pre_http_request', $catch, 10);
$http['sent'] = $sent;
$say('the HTTP client', $http);

$say('request options', [
    'empty' => (new RequestOptions())->toArray(),
    'set' => $options->toArray(),
    'getters' => [$options->getTimeout(), $options->getConnectTimeout(), $options->allowsRedirects(), $options->getMaxRedirects(), $more->allowsRedirects(), $more->getMaxRedirects(), (new RequestOptions())->allowsRedirects()],
    'from an array' => RequestOptions::fromArray(['timeout' => 5, 'maxRedirects' => 2])->toArray(),
    'json' => json_encode($options),
]);

// The discovery strategy hands the SDK the WordPress client.
if (!class_exists('Minn_Probe_AI_Discovery', false)) {
    eval('class Minn_Probe_AI_Discovery extends WP_AI_Client_Discovery_Strategy { public static function make($factory) { return static::createClient($factory); } }');
}
$made = Minn_Probe_AI_Discovery::make($factory);
$say('the discovery strategy', [
    'a client' => get_class($made),
    'candidates for a client' => array_map(static fn ($candidate) => is_array($candidate) ? array_map(static fn ($value) => is_object($value) ? get_class($value) : (is_callable($value) && !is_string($value) ? 'a callable' : $value), $candidate) : $candidate, WP_AI_Client_Discovery_Strategy::getCandidates('WordPress\\AiClientDependencies\\Psr\\Http\\Client\\ClientInterface')),
    'candidates for something else' => WP_AI_Client_Discovery_Strategy::getCandidates('Zz\\Nothing'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
