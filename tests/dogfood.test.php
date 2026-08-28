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

/**
 * The head's identity elements: the title, every meta tag, the links that
 * name the page (canonical, alternates, icons, the API), and the structured
 * data, with hosts normalised and one generated id masked. Stylesheets,
 * scripts, and the reference's discovery and emoji plumbing are not part of
 * the comparison. Sorted, because plugins print in hook order.
 *
 * @return list<string>
 */
function dogfood_head(string $html, string $host, string $engine): array
{
    if (!preg_match('/<head>(.*?)<\/head>/s', $html, $m)) {
        return ['(no head)'];
    }
    $head = str_replace([$host, str_replace('https://', 'http://', $engine)], $engine, $m[1]);
    preg_match_all('/<title>.*?<\/title>|<(?:meta|link)\b[^>]*>/s', $head, $tags);
    $keep = [];
    foreach ($tags[0] as $tag) {
        if (preg_match('/rel=[\'"](?:stylesheet|preload|modulepreload|dns-prefetch|preconnect|EditURI|wlwmanifest|profile|pingback|shortlink)|type=[\'"](?:text\/xml\+oembed|application\/json\+oembed)|name=[\'"](?:generator|viewport)|charset=/i', $tag)) {
            continue;
        }
        $keep[] = preg_replace('/\s+/', ' ', trim($tag));
    }
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $head, $scripts);
    foreach ($scripts[1] as $json) {
        $json = preg_replace('/#\/schema\/Person\/[0-9a-f]{32}/', '#/schema/Person/ID', $json);
        $data = json_decode($json, true);
        $keep[] = 'ld+json ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    sort($keep);
    return $keep;
}

echo "dogfood suite: $ENGINE (engine) vs $REF (reference)\n";
$pass = 0;
$fail = 0;
foreach ($paths as $path) {
    $engineHtml = $fetch($ENGINE, $path);
    $referenceHtml = $fetch($REF, $path);
    $engine = dogfood_normalise(theme_body($engineHtml, $REF, $ENGINE));
    $reference = dogfood_normalise(theme_body($referenceHtml, $REF, $ENGINE));
    $verdict = theme_first_diff($engine, $reference);
    if ($verdict === 'identical') {
        $pass++;
        echo "  ok   $path\n";
    } else {
        $fail++;
        echo "  FAIL $path: $verdict\n";
    }
    $engineHead = dogfood_head($engineHtml, $REF, $ENGINE);
    $referenceHead = dogfood_head($referenceHtml, $REF, $ENGINE);
    if ($engineHead === $referenceHead) {
        $pass++;
        echo "  ok   $path head\n";
    } else {
        $fail++;
        $missing = array_diff($referenceHead, $engineHead);
        $extra = array_diff($engineHead, $referenceHead);
        echo "  FAIL $path head\n" . ($missing === [] ? '' : "      missing: " . implode("\n               ", array_map(static fn (string $t) => substr($t, 0, 160), $missing)) . "\n") . ($extra === [] ? '' : "      extra:   " . implode("\n               ", array_map(static fn (string $t) => substr($t, 0, 160), $extra)) . "\n");
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
