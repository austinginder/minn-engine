<?php
/**
 * Block rendering suite: one battery post per block family in the shared
 * database (tests/tools/blocks-battery.php builds them). The engine's
 * content.rendered must match the pinned reference rendering, and the live
 * reference when it is up.
 *
 * The wp-container-core-*-is-layout-{hash} suffix is engine-defined (see
 * contracts/blocks.md); both sides are normalised before comparing.
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'https://ref.minn.localhost';
$DIR = dirname(__DIR__) . '/contracts/fixtures/blocks';
$manifest = json_decode((string) file_get_contents("$DIR/manifest.json"), true);

function blocks_normalise(string $html, string $host, string $engine): string
{
    $html = str_replace($host, $engine, $html);
    $html = preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', $html);
    return $html;
}

function blocks_first_diff(string $a, string $b): string
{
    $la = explode("\n", $a);
    $lb = explode("\n", $b);
    foreach ($la as $i => $line) {
        if (($lb[$i] ?? null) !== $line) {
            return "line " . ($i + 1) . ":\n      want: " . substr($lb[$i] ?? '(missing)', 0, 220) . "\n      got:  " . substr($line, 0, 220);
        }
    }
    return count($lb) > count($la) ? 'engine output is shorter' : 'no line diff';
}

$live = @file_get_contents("$REF/wp-json/", false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]])) !== false;
echo $live ? "Live mode: engine vs fixture vs reference\n" : "Fixture mode\n";
$pass = 0;
$fail = 0;
foreach ($manifest['posts'] as $family => $id) {
    [$h, $body] = minn_test_fetch("$ENGINE/wp-json/wp/v2/posts/$id?_fields=content");
    $engine = blocks_normalise((string) (json_decode($body, true)['content']['rendered'] ?? ''), $REF, $ENGINE);
    $fixture = blocks_normalise((string) file_get_contents("$DIR/$family.rendered.html"), $REF, $ENGINE);
    if ($engine === $fixture) {
        $pass++;
        echo "  ok   $family matches fixture\n";
    } else {
        $fail++;
        echo "  FAIL $family vs fixture: " . blocks_first_diff($engine, $fixture) . "\n";
    }
    if ($live) {
        [$rh, $rbody] = minn_test_fetch("$REF/wp-json/wp/v2/posts/$id?_fields=content");
        $reference = blocks_normalise((string) (json_decode($rbody, true)['content']['rendered'] ?? ''), $REF, $ENGINE);
        if ($engine === $reference) {
            $pass++;
            echo "  ok   $family matches reference\n";
        } else {
            $fail++;
            echo "  FAIL $family vs reference: " . blocks_first_diff($engine, $reference) . "\n";
        }
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
