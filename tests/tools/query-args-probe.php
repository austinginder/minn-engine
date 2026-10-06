<?php
/**
 * add_query_arg, remove_query_arg and build_query as the reference spells
 * them (probe query-args): a URI that is only a query, or nothing, keeps
 * its question mark; a fragment stays last; a bare query string is read
 * as one; an empty value leaves its key bare and null leaves it out; a
 * repeated or nested key; build_query's own spelling of empty, null and
 * boolean values. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$bases = ['', 'c', 'c=d', '?c=d', '?', '/?', '#frag', '?#f', 'c=d#f', '?c=d#f', '/p?c=d#f', 'http://x.test', 'http://x.test/?', 'http://x.test/?#f', 'http://x.test/path?c=d#f', 'http://x.test/p?c[]=1&c[]=2', 'a=b&', '?a=1&a=2', 'https://x.test/p?a=1', '//x.test/p', 'mailto:me@x.test', 'x.test/p?q', '?&c=d', '?c', 'http://x.test#f?g=h', '?c=&d=1'];
foreach ($bases as $base) {
    $say("base {$base}", [add_query_arg(['a' => 'b'], $base), add_query_arg(['a' => false, 'c' => 'z'], $base), remove_query_arg('c', $base), add_query_arg('e', '', $base)]);
}
$say('values', [
    'empty' => add_query_arg(['e' => ''], 'http://x.test/'),
    'null' => add_query_arg(['e' => null], 'http://x.test/?f=1'),
    'zero' => add_query_arg(['e' => 0], 'http://x.test/'),
    'list' => add_query_arg(['e' => ['x', 'y']], 'http://x.test/'),
    'true' => add_query_arg(['e' => true], 'http://x.test/'),
    'spaces' => add_query_arg(['e f' => 'g h'], 'http://x.test/'),
    'several removed' => remove_query_arg(['a', 'c'], 'http://x.test/?a=1&b=2&c=3'),
]);
$say('build_query', build_query(['a' => '', 'b' => null, 'c' => 'd', 'e' => false, 'f' => 0, 'g' => true, 'h' => ['i' => 'j', 'k' => null]]));
$say('build_query keys', [build_query(['e f' => 'g h', 'x&y' => 'z', 'n' => ['o p' => 'q r']]), add_query_arg(['x&y' => 'z&w', 'n' => ['o p' => 'q']], 'http://x.test/')]);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
