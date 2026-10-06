<?php

declare(strict_types=1);

use Minn\Http\Destination;
use Minn\Http\Fake;
use Minn\Http\Location;
use Minn\Http\Outbound;
use Minn\Http\RequestFailed;

/**
 * Minn\Http as a caller meets it: named arguments in, one Exchange out,
 * the destination rules judged at every hop, and the fake standing in for
 * the network. Nothing here leaves the process; tests/http.test.php sends
 * real requests to a local server.
 */
$faking = static function (array $answers, Closure $body): bool|string {
    $fake = Minn\Http::fake($answers);
    try {
        return $body($fake);
    } finally {
        $fake->restore();
    }
};
$header = static fn (Outbound $request, string $name): array => array_values(array_filter(
    $request->headers,
    static fn (string $line): bool => stripos($line, $name . ':') === 0,
));

return [
    'a JSON answer reads back with json()' => static fn (): bool|string => $faking(
        ['api.example.com/*' => ['ok' => true]],
        static fn (): bool => Minn\Http::get('https://api.example.com/status')->json() === ['ok' => true],
    ),
    'form: is encoded and labelled' => static fn (): bool|string => $faking(['*' => ''], static function (Fake $fake) use ($header): bool|string {
        Minn\Http::post('https://api.example.com/subscribe', form: ['email' => 'a@b.c', 'tags' => ['x', 'y']]);
        $sent = $fake->sent()[0];
        return $sent->method === 'POST' && $sent->body === 'email=a%40b.c&tags%5B0%5D=x&tags%5B1%5D=y'
            && $header($sent, 'Content-Type') === ['Content-Type: application/x-www-form-urlencoded'] ? true : json_encode($sent);
    }),
    'json: is encoded, and a Content-Type the caller gave is kept' => static fn (): bool|string => $faking(['*' => ''], static function (Fake $fake) use ($header): bool|string {
        Minn\Http::put('https://api.example.com/a', json: ['path' => '/x/y']);
        Minn\Http::patch('https://api.example.com/b', json: [], headers: ['content-type' => 'application/merge-patch+json']);
        [$put, $patch] = $fake->sent();
        return $put->body === '{"path":"/x/y"}' && $header($put, 'Content-Type') === ['Content-Type: application/json']
            && $patch->body === '[]' && $header($patch, 'content-type') === ['content-type: application/merge-patch+json'] ? true : json_encode([$put, $patch]);
    }),
    'headers take name => value, lists repeat, and lines pass through' => static fn (): bool|string => $faking(['*' => ''], static function (Fake $fake): bool|string {
        Minn\Http::get('https://api.example.com/', headers: ['Accept' => 'application/json', 'X-Tag' => ['one', 'two'], 'X-Raw: kept']);
        $lines = $fake->sent()[0]->headers;
        return $lines === ['Accept: application/json', 'X-Tag: one', 'X-Tag: two', 'X-Raw: kept'] ? true : json_encode($lines);
    }),
    'query: joins the URL, after any query it already has' => static fn (): bool|string => $faking(['*' => ''], static function (Fake $fake): bool|string {
        Minn\Http::get('https://api.example.com/search', query: ['q' => 'two words', 'page' => 2]);
        Minn\Http::get('https://api.example.com/search?lang=en#top', query: ['q' => 'x']);
        $urls = array_map(static fn (Outbound $r): string => $r->url, $fake->sent());
        return $urls === ['https://api.example.com/search?q=two%20words&page=2', 'https://api.example.com/search?lang=en&q=x#top'] ? true : json_encode($urls);
    }),
    'two payloads at once is a mistake the caller hears about' => static function (): bool|string {
        try {
            Minn\Http::post('https://api.example.com/', json: ['a' => 1], form: ['a' => 1]);
            return 'accepted';
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'Send one of json:, form: or body:, not 2 of them.' ? true : $e->getMessage();
        }
    },
    'a misspelt argument stops at the call' => static function (): bool|string {
        try {
            $call = 'Minn\Http::get';
            $call('https://api.example.com/', timout: 10.0);
            return 'accepted';
        } catch (Error $e) {
            return $e->getMessage() === 'Unknown named parameter $timout' ? true : $e->getMessage();
        }
    },
    'a URL no fake answers fails as if nothing were listening' => static fn (): bool|string => $faking(['api.example.com/*' => ''], static function (): bool|string {
        $reply = Minn\Http::get('https://elsewhere.example/');
        return $reply->failed() && $reply->errno === CURLE_COULDNT_CONNECT && !$reply->ok() && $reply->code === 0 ? true : (string) $reply->error;
    }),
    'a private address is refused before anything is sent' => static fn (): bool|string => $faking(['*' => ''], static function (Fake $fake): bool|string {
        $refused = [];
        foreach (['http://127.0.0.1:8123/', 'http://169.254.169.254/latest/meta-data/', 'http://[::1]/', 'http://10.0.0.5/', 'http://100.64.0.1/'] as $url) {
            $reply = Minn\Http::get($url);
            $refused[] = $reply->failed() && $reply->errno === 0 && str_contains((string) $reply->error, 'private address');
        }
        return !in_array(false, $refused, true) && $fake->sent() === [] ? true : json_encode($refused);
    }),
    'private: lets a listed host through' => static fn (): bool|string => $faking(['127.0.0.1:8123/*' => 'here'], static function (): bool|string {
        $reply = Minn\Http::get('http://127.0.0.1:8123/wp-json/', private: ['127.0.0.1']);
        return $reply->ok() && $reply->body === 'here' ? true : (string) $reply->error;
    }),
    'only http and https go out' => static fn (): bool|string => $faking(['*' => ''], static function (): bool|string {
        $reply = Minn\Http::get('file:///etc/passwd');
        return $reply->failed() && $reply->errno === 0 && str_starts_with((string) $reply->error, 'Only http and https') ? true : (string) $reply->error;
    }),
    'redirects are followed and each hop is recorded' => static fn (): bool|string => $faking([
        'api.example.com/old' => Minn\Http::reply(status: 301, headers: ['Location' => '/new']),
        'api.example.com/new' => ['moved' => true],
    ], static function (Fake $fake): bool|string {
        $reply = Minn\Http::get('https://api.example.com/old');
        return $reply->json() === ['moved' => true] && $reply->url === 'https://api.example.com/new' && count($fake->sent()) === 2 ? true : json_encode([$reply->url, count($fake->sent())]);
    }),
    'redirects: 0 hands back the 3xx' => static fn (): bool|string => $faking(['*' => Minn\Http::reply(status: 302, headers: ['Location' => '/next'])], static function (): bool|string {
        $reply = Minn\Http::get('https://api.example.com/', redirects: 0);
        return $reply->code === 302 && $reply->header('location') === '/next' ? true : (string) $reply->code;
    }),
    'a redirect loop stops at the limit' => static fn (): bool|string => $faking(['*' => Minn\Http::reply(status: 302, headers: ['Location' => '/again'])], static function (Fake $fake): bool|string {
        $reply = Minn\Http::get('https://api.example.com/', redirects: 3);
        return $reply->errno === CURLE_TOO_MANY_REDIRECTS && count($fake->sent()) === 4 ? true : json_encode([$reply->error, count($fake->sent())]);
    }),
    'a 302 after a POST becomes a GET; a 307 keeps the method and body' => static fn (): bool|string => $faking([
        'api.example.com/form' => Minn\Http::reply(status: 302, headers: ['Location' => '/thanks']),
        'api.example.com/api' => Minn\Http::reply(status: 307, headers: ['Location' => '/api2']),
        '*' => '',
    ], static function (Fake $fake): bool|string {
        Minn\Http::post('https://api.example.com/form', form: ['a' => 1]);
        Minn\Http::post('https://api.example.com/api', json: ['a' => 1]);
        $hops = array_map(static fn (Outbound $r): string => $r->method . ' ' . parse_url($r->url, PHP_URL_PATH) . ' ' . ($r->body ?? '-'), $fake->sent());
        return $hops === ['POST /form a=1', 'GET /thanks -', 'POST /api {"a":1}', 'POST /api2 {"a":1}'] ? true : json_encode($hops);
    }),
    'a redirect off the listed hosts is refused' => static fn (): bool|string => $faking(['*' => Minn\Http::reply(status: 302, headers: ['Location' => 'https://evil.example/'])], static function (Fake $fake): bool|string {
        $reply = Minn\Http::get('https://api.wordpress.org/x', hosts: ['https://api.wordpress.org/']);
        return $reply->failed() && $reply->errno === 0 && $reply->error === 'evil.example is not one of the hosts this request may reach.' && count($fake->sent()) === 1 ? true : (string) $reply->error;
    }),
    'credentials stay with their origin' => static fn (): bool|string => $faking([
        'api.example.com/a' => Minn\Http::reply(status: 302, headers: ['Location' => '/b']),
        'api.example.com/b' => Minn\Http::reply(status: 302, headers: ['Location' => 'https://cdn.example.net/c']),
        '*' => '',
    ], static function (Fake $fake): bool|string {
        Minn\Http::get('https://api.example.com/a', headers: ['Authorization' => 'Bearer t', 'Cookie' => 's=1', 'Accept' => 'text/plain']);
        $headers = array_map(static fn (Outbound $r): array => $r->headers, $fake->sent());
        return $headers[1] === $headers[0] && $headers[2] === ['Accept: text/plain'] ? true : json_encode($headers);
    }),
    'throw() returns an ok reply and throws a RuntimeException for the rest' => static fn (): bool|string => $faking([
        'api.example.com/ok' => ['ok' => true],
        'api.example.com/missing' => 404,
    ], static function (): bool|string {
        $ok = Minn\Http::get('https://api.example.com/ok')->throw()->json();
        try {
            Minn\Http::get('https://api.example.com/missing')->throw();
            return 'no throw';
        } catch (RuntimeException $e) {
            return $ok === ['ok' => true] && $e instanceof RequestFailed && $e->getMessage() === 'api.example.com answered 404.' && $e->reply->code === 404 ? true : $e->getMessage();
        }
    }),
    'header() takes any spelling and joins repeats; cookie() reads one value' => static function (): bool|string {
        $reply = Minn\Http::reply('', 200, ['X-Multi' => ['one', 'two'], 'Content-Type' => 'text/plain', 'Set-Cookie' => ['plain=one; path=/', 'flagged=two%20words; HttpOnly', 'plain=again']]);
        return $reply->header('x-multi') === 'one, two' && $reply->header('CONTENT-TYPE') === 'text/plain' && $reply->header('missing') === null
            && $reply->cookie('flagged') === 'two words' && $reply->cookie('plain') === 'again' && $reply->cookie('nope') === null ? true : json_encode($reply);
    },
    'send() is answered by the fake too, so wp_remote_*() can be faked' => static fn (): bool|string => $faking(['*' => 'faked'], static function (): bool|string {
        $reply = Minn\Http::send(new Outbound('GET', 'http://127.0.0.1:1/'));
        return $reply->body === 'faked' ? true : (string) $reply->error;
    }),
    'a fake answers from a closure' => static fn (): bool|string => $faking(['*' => static fn (Outbound $r): array => ['echo' => $r->method]], static fn (): bool => Minn\Http::delete('https://api.example.com/x')->json() === ['echo' => 'DELETE']),
    'patterns: * is anything, and scheme and query may be left off' => static function (): bool|string {
        $rows = [
            Fake::matches('*', 'https://a.example/x?y=1'),
            Fake::matches('a.example/*', 'https://a.example/x?y=1'),
            Fake::matches('a.example/x', 'https://a.example/x?y=1'),
            Fake::matches('https://a.example/x?y=1', 'https://a.example/x?y=1'),
            !Fake::matches('a.example/x', 'https://a.example/xy'),
            !Fake::matches('b.example/*', 'https://a.example/x'),
        ];
        return !in_array(false, $rows, true) ? true : json_encode($rows);
    },
    'a name that resolves somewhere private is refused; a public one is pinned' => static function (): bool|string {
        $lookup = static fn (string $host): array => ['rebind.example' => ['8.8.8.8', '10.0.0.5'], 'public.example' => ['93.184.215.14'], 'lan.example' => ['192.168.1.20']][$host] ?? [];
        $where = new Destination([], ['lan.example'], $lookup);
        $rebind = $where->pinned(new Outbound('GET', 'https://rebind.example/'));
        $public = $where->pinned(new Outbound('GET', 'https://public.example/'));
        $lan = $where->pinned(new Outbound('GET', 'http://lan.example/'));
        $none = $where->pinned(new Outbound('GET', 'https://nowhere.example/'));
        return $rebind instanceof Minn\Http\Exchange && $rebind->error === 'rebind.example resolves to 10.0.0.5, a private address. List rebind.example in private: to reach it.'
            && $public instanceof Outbound && $public->prepare !== null
            && $lan instanceof Outbound && $lan->prepare === null
            && $none instanceof Minn\Http\Exchange && $none->errno === CURLE_COULDNT_RESOLVE_HOST ? true : json_encode([$rebind, $public, $lan, $none]);
    },
    'a Location resolves against the URL that sent it' => static function (): bool|string {
        $rows = [
            Location::resolve('https://a.example/x/y?z=1', '/root') === 'https://a.example/root',
            Location::resolve('https://a.example:8443/x/y', 'sibling') === 'https://a.example:8443/x/sibling',
            Location::resolve('https://a.example/x', '//b.example/p') === 'https://b.example/p',
            Location::resolve('https://a.example/x', 'http://c.example/') === 'http://c.example/',
            Location::sameOrigin('https://a.example/x', 'https://A.example:443/y'),
            !Location::sameOrigin('https://a.example/', 'http://a.example/'),
        ];
        return !in_array(false, $rows, true) ? true : json_encode($rows);
    },
];
