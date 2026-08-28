<?php

declare(strict_types=1);

/**
 * Live parity against a real site's WordPress copy: the dogfood site runs
 * the engine, its parked wp-reference runs the reference on the same
 * database, and every path below must render the same <body>.
 *
 * There are no fixtures here on purpose. A real site carries plugins the
 * engine will never run; their output is a recorded divergence, not a
 * check, so the suite normalises the known plugin tokens and reports the
 * first differing line per path.
 *
 *   MINN_DOGFOOD_ENGINE=https://dogfood.localhost \
 *   MINN_DOGFOOD_REF=http://127.0.0.1:8124 php tests/dogfood.test.php [--all]
 */

require __DIR__ . '/lib.php';

$ENGINE = getenv('MINN_DOGFOOD_ENGINE') ?: 'https://dogfood.localhost';
$REF = getenv('MINN_DOGFOOD_REF') ?: 'http://127.0.0.1:8124';

$src = (string) file_get_contents(__DIR__ . '/theme.test.php');
preg_match('/function theme_body.*?\n}\n/s', $src, $m);
eval($m[0]);
preg_match('/function theme_first_diff.*?\n}\n/s', $src, $m);
eval($m[0]);

/** Nothing is normalised beyond the theme suite's own rules: every plugin this site uses is provided by an extension. */
function dogfood_normalise(string $body): string
{
    return $body;
}

$fetch = static function (string $base, string $path): string {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 30]]);
    return (string) @file_get_contents($base . $path, false, $context);
};

$probe = @file_get_contents($REF . '/', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
if ($probe === false || $probe === '') {
    echo "dogfood suite: reference not running at $REF (cd wp-reference && php -S 127.0.0.1:8124 router.php); skipping\n";
    exit(0);
}

$paths = [
    '/',
    '/page/2/',
    '/about/',
    '/contact/',
    '/case-studies/',
    '/corporate/',
    '/residential/11-fifth-3/',
    '/11-fifth-3/',
    '/news/',
    '/news/title-here-like-this/',
    '/nonexistent-page/',
    '/?s=design',
];

echo "dogfood suite: $ENGINE (engine) vs $REF (reference)\n";
$pass = 0;
$fail = 0;
foreach ($paths as $path) {
    $engine = dogfood_normalise(theme_body($fetch($ENGINE, $path), $REF, $ENGINE));
    $reference = dogfood_normalise(theme_body($fetch($REF, $path), $REF, $ENGINE));
    $verdict = theme_first_diff($engine, $reference);
    if ($verdict === 'identical') {
        $pass++;
        echo "  ok   $path\n";
    } else {
        $fail++;
        echo "  FAIL $path: $verdict\n";
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
