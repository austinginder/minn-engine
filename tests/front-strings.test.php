<?php
/**
 * Front-end strings ask for their translation where the reference's do:
 * signed-out pages are fetched from both stacks with the fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-pseudo-l10n.php marking every
 * translated string «like this», and the marked strings of each page must
 * agree (a string printed without asking for its translation is marked on
 * the reference only). Compared against the reference.
 *
 *   php tests/front-strings.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';
minn_test_pin_locale();

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n       {$detail}" : '') . "\n";
    }
};

// The marker lives in both mu-plugin folders only while the suite runs, and marks only requests with its cookie.
$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-pseudo-l10n.php';
$folders = ["{$SITE}/wp-reference/wp-content/mu-plugins", "{$SITE}/public/wp-content/mu-plugins"];
foreach ($folders as $folder) {
    if (!is_link("{$folder}/minn-test-pseudo-l10n.php")) {
        symlink($fixture, "{$folder}/minn-test-pseudo-l10n.php");
    }
}
register_shutdown_function(static function () use ($folders): void {
    foreach ($folders as $folder) {
        @unlink("{$folder}/minn-test-pseudo-l10n.php");
    }
});

/** @return array<string, int> each marked string a page prints, with how often (signed in with the cookie given) */
$marked = static function (string $url, string $signedIn = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => 'minn_test_pseudo=1' . ($signedIn === '' ? '' : '; ' . $signedIn), CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    $html = (string) curl_exec($ch);
    // Marks inside JSON (an interactivity context, a script's settings) and entities read as the characters they are.
    $html = html_entity_decode(str_replace([chr(92) . 'u00ab', chr(92) . 'u00bb'], ['«', '»'], $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match_all('/«([^«»]*)»/u', $html, $m);
    $counts = array_count_values(array_map(static fn (string $s): string => trim((string) preg_replace('/\s+/', ' ', $s)), $m[1]));
    ksort($counts);
    return $counts;
};

$paths = [
    'the home listing' => '/',
    'a post with its comments and form' => '/hello-world/',
    'the form replying to a comment' => '/hello-world/?replytocom=1',
    'a page' => '/sample-page/',
    'a category' => '/category/uncategorized/',
    'a tag' => '/tag/engine/',
    'an author' => '/author/admin/',
    'a year' => '/2026/',
    'a month' => '/2026/08/',
    'a day' => '/2026/08/28/',
    'a search' => '/?s=hello',
    'an empty search' => '/?s=zzz-nothing-here',
    'a missing page' => '/no-such-page-here/',
];
foreach ($paths as $label => $path) {
    $reference = $marked($REF . $path);
    $engine = $marked($ENGINE . $path);
    $check("{$label}: something is translated", $reference !== [], 'the reference marked nothing: is the fixture staged?');
    $onlyRef = array_diff_key($reference, $engine);
    $onlyEngine = array_diff_key($engine, $reference);
    $check("{$label}: the same strings are translated", $onlyRef === [] && $onlyEngine === [], 'reference only: ' . json_encode(array_keys($onlyRef), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n       engine only: " . json_encode(array_keys($onlyEngine), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Signed in, the Minn Admin bar's strings too (its own text domain). The bar on WordPress also links the classic
// wp-admin, which a Minn site does not have: those strings are the reference's alone.
const CLASSIC_ADMIN_ONLY = ['Classic admin', 'The original WordPress tools', 'Open wp-admin'];
$mint = json_decode((string) shell_exec('wp --path=' . escapeshellarg("{$SITE}/wp-reference") . ' eval-file ' . escapeshellarg(__DIR__ . '/tools/mint-session.php') . ' 1 2>/dev/null'), true);
$check('signed in: a session is minted', is_array($mint) && isset($mint['cookie_name'], $mint['cookie']));
$cookie = is_array($mint) ? $mint['cookie_name'] . '=' . rawurlencode((string) $mint['cookie']) : '';
foreach (['signed in: the home listing' => '/', 'signed in: a post' => '/hello-world/', 'signed in: a category' => '/category/uncategorized/'] as $label => $path) {
    $reference = array_diff_key($marked($REF . $path, $cookie), array_flip(CLASSIC_ADMIN_ONLY));
    $engine = $marked($ENGINE . $path, $cookie);
    $check("{$label}: the bar is there", isset($reference['Minn Admin Bar'], $engine['Minn Admin Bar']), 'reference ' . (isset($reference['Minn Admin Bar']) ? 'yes' : 'no') . ', engine ' . (isset($engine['Minn Admin Bar']) ? 'yes' : 'no'));
    $onlyRef = array_diff_key($reference, $engine);
    $onlyEngine = array_diff_key($engine, $reference);
    $check("{$label}: the same strings are translated", $onlyRef === [] && $onlyEngine === [], 'reference only: ' . json_encode(array_keys($onlyRef), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n       engine only: " . json_encode(array_keys($onlyEngine), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// The built-in types', taxonomies' and statuses' labels, as the REST API gives them to an editor: each in the site's language.
foreach (['types', 'taxonomies', 'statuses'] as $route) {
    $labels = static function (string $base) use ($route, $cookie, $mint): array {
        $ch = curl_init($base . '/?rest_route=/wp/v2/' . $route . '&context=edit');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => 'minn_test_pseudo=1; ' . $cookie, CURLOPT_HTTPHEADER => ['X-WP-Nonce: ' . (string) ($mint['nonce'] ?? '')], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
        $out = [];
        foreach ((array) json_decode((string) curl_exec($ch), true) as $slug => $item) {
            $out[$slug] = ['name' => $item['name'] ?? null] + (array) ($item['labels'] ?? []);
        }
        return $out;
    };
    $reference = $labels($REF);
    $engine = $labels($ENGINE);
    $differ = [];
    foreach ($reference as $slug => $values) {
        foreach ($values as $key => $value) {
            if (($engine[$slug][$key] ?? null) !== $value) {
                $differ[] = "{$slug}.{$key}: " . json_encode($engine[$slug][$key] ?? null, JSON_UNESCAPED_UNICODE) . ' vs ' . json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
    }
    $check("the built-in {$route}' labels are translated", $reference !== [] && $differ === [], $reference === [] ? 'the reference listed none' : implode("\n       ", array_slice($differ, 0, 10)));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
