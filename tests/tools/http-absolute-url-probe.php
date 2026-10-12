<?php
/**
 * WP_Http::make_absolute_url (probe http-absolute-url; runtime.md "WordPress
 * 7.1.3" left it owed): paths read against base URLs, relative and absolute,
 * with ./ and ../ segments, climbs past the root, queries, fragments, ports,
 * user info and a protocol-relative path.
 */

$cases = [
    ['../a', 'http://x.test/b/c/d'], ['./a', 'http://x.test/b/c'], ['/a/../b', 'http://x.test/q'], ['a/./b/../c', 'http://x.test/p/q'],
    ['?x=1', 'http://x.test/p/q?y=2'], ['#f', 'http://x.test/p/q'], ['//y.test/a', 'https://x.test/'], ['../../../a', 'http://x.test/b'],
    ['a', 'http://x.test'], ['', 'http://x.test/p'], ['b/../../c', 'http://x.test/p/q/r'], ['.', 'http://x.test/p/q'], ['..', 'http://x.test/p/q/'],
    ['#f', 'http://x.test/p/q?y=2'], ['a?x=1#g', 'http://x.test/p/q?y=2'], ['a', 'http://u:pw@x.test:8080/p/q?y=2'], ['/a', 'http://u:pw@x.test:8080/p/q'],
    ['', 'http://x.test/p?y=2#h'], ['a/../../b', 'http://x.test/'], ['./../a', 'http://x.test/p/q'], ['../', 'http://x.test/p/q/r'], ['a', 'http://x.test/p/'],
    ['a//b/../c', 'http://x.test/p/'], ['?', 'http://x.test/p?y=2'], ['a', 'https://x.test?y=1'],
    ['https://z.test/../a', 'http://x.test/p'], ['mailto:a@x.test', 'http://x.test/p'], ['a', ''],
];
$log = [];
foreach ($cases as [$relative, $base]) {
    $log[] = ["{$relative} against {$base}", WP_Http::make_absolute_url($relative, $base)];
}
echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
