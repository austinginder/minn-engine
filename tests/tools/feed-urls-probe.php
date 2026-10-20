<?php
/**
 * Feed addresses (probe feed-urls): the address fetch_feed() asks the HTTP
 * API for and subscribe_url() gives back (scheme and host lower-cased,
 * default ports dropped, dot segments removed, escapes of letters, digits,
 * "-", "." and "~" decoded and the rest upper-cased), the links a feed
 * carries, and when a cached feed is fetched again (its lifetime, kept in
 * the cached data). Every request is answered by a filter; nothing leaves
 * the machine, and the probe's cached feeds are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$asked = [];
$body = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><link>http://zz-probe.example/?a=1&amp;b=2</link><item><title>One</title><link>http://zz-probe.example/i?a=1&amp;b=%41%2d%2e%5f%7e%30</link></item></channel></rss>';
$answer = static function ($pre, $args, $url) use (&$asked, $body) {
    $asked[] = $url;
    return ['headers' => ['content-type' => 'application/rss+xml'], 'body' => $body, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
};
add_filter('pre_http_request', $answer, 10, 3);
$lifetime = 0;
$life = static function () use (&$lifetime) {
    return $lifetime;
};
add_filter('wp_feed_cache_transient_lifetime', $life);
$forget = static function (array $urls): void {
    foreach ($urls as $url) {
        delete_site_transient('feed_' . md5($url));
        delete_site_transient('feed_mod_' . md5($url));
    }
};

try {
    $rows = [];
    foreach (['HTTP://ZZ-Probe.Example', 'http://zz-probe.example?x=1', 'http://ZZ-probe.example/Path/%7e', 'http://zz-probe.example:80/x', 'https://zz-probe.example:443/x', 'http://zz-probe.example/a b', 'http://zz-probe.example/x#frag', 'http://zz-probe.example/%41', 'http://user:PW@ZZ-probe.example/x', 'http://zz-probe.example/./b/../c', 'HtTpS://zz-probe.example', 'http://zz-probe.example/%2f%3a%7E%5f', 'http://zz-probe.example/x?q=%41%2f&r=a b', 'http://zz-probe.example/%zz', 'http://zz-probe.example/é', 'http://ZZ%41.example/'] as $url) {
        $asked = [];
        $feed = fetch_feed($url);
        $rows[] = [$url, $asked, is_wp_error($feed) ? $feed->get_error_message() : $feed->subscribe_url()];
        $forget($asked);
    }
    $say('the address asked for and given back', $rows);

    $asked = [];
    $feed = fetch_feed('http://zz-probe.example/f?a=1&b=%2d%2e%5f%30%61');
    $say('the links a feed carries', [$feed->subscribe_url(), $feed->get_link(), $feed->get_permalink(), $feed->get_item(0)->get_link(), $feed->get_item(0)->get_permalink()]);
    $forget($asked);

    // A cached feed within its lifetime is read from the cache; past it, fetched again.
    $asked = [];
    $url = 'http://zz-probe.example/age';
    $lifetime = 3600;
    fetch_feed($url);
    fetch_feed($url);
    $counts = [count($asked)];
    $stored = get_site_transient('feed_' . md5($url));
    $stored['__cache_expiration_time'] = time() - 5;
    set_site_transient('feed_' . md5($url), $stored);
    fetch_feed($url);
    $counts[] = count($asked);
    fetch_feed($url);
    $counts[] = count($asked);
    $kept = get_site_transient('feed_' . md5($url));
    $counts[] = [$kept['__cache_expiration_time'] - time() >= 3598, $kept['cache_expiration_time'] === $kept['__cache_expiration_time']];
    $say('a cached feed is fetched again past its lifetime', $counts);
    $forget($asked);
} finally {
    remove_filter('pre_http_request', $answer, 10);
    remove_filter('wp_feed_cache_transient_lifetime', $life);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
