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

/**
 * Markup that only an installed plugin produces: body-class tokens, the
 * dark-palette toggle item, the Jetpack slideshow (its stored markup is
 * rewritten by two plugins at render), and a gallery plugin's comment.
 */
function dogfood_normalise(string $body): string
{
    $body = preg_replace('/ (metaslider-plugin|modula-best-grid-gallery)(?=[ "])/', '', $body);
    $body = preg_replace('/^<!-- Gallery Custom Links:.*$\n?/m', '', $body);
    $body = preg_replace('/<li class="[^"]*wp-block-mosne-dark-palette">.*?<\/li>\n?/s', '', $body);
    $body = preg_replace('/^<div class="wp-block-jetpack-slideshow[" ].*$/m', '<div class="wp-block-jetpack-slideshow">[plugin-rendered]', $body);
    // wp-retina-2x adds @2x candidates to every srcset; a port is on the list.
    $body = preg_replace('/, https?:\/\/[^\s"]+@2x\.[a-z]+ \d+w/', '', $body);
    // A gallery-links plugin wraps images in its own anchors and, through
    // its HTML parser, lowercases attribute names on the way out.
    $body = preg_replace('/<a href="[^"]*" class="custom-link no-lightbox"[^>]*>(.*?)<\/a>/s', '$1', $body);
    $body = str_replace('viewBox=', 'viewbox=', $body);
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
