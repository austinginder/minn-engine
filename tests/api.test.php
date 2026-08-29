<?php
/**
 * Runtime API suite: runs tests/tools/api-probe.php on the engine's facade
 * (booted against the dev database) and diffs the transcript row by row
 * against the one captured from the reference. Signatures of every facade
 * function are checked against the interface inventory as well.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/lib.php'; // pins the reference theme the fixtures were captured under
$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

foreach (['functions' => 'api-probe.php', 'admin' => 'admin-probe.php', 'content' => 'content-probe.php', 'media' => 'media-probe.php', 'rest' => 'rest-probe.php', 'blocks' => 'blocks-probe.php', 'interactivity' => 'interactivity-probe.php', 'script-modules' => 'script-modules-probe.php'] as $fixture => $probe) {
    $expected = json_decode(file_get_contents($root . "/contracts/fixtures/api/{$fixture}.json"), true);
    $out = shell_exec('php ' . escapeshellarg($root . '/tests/tools/run-api-probe.php') . ' ' . escapeshellarg($probe) . ' 2>/dev/null');
    $actual = json_decode((string) $out, true);
    if (!is_array($actual)) {
        echo "  FAIL {$probe} did not produce JSON:\n" . substr((string) $out, 0, 2000) . "\n";
        exit(1);
    }
    $check("{$probe}: row count", count($actual) === count($expected), count($actual) . ' vs ' . count($expected));
    $byLabel = [];
    foreach ($actual as $row) {
        $byLabel[$row[0]] = $row[1];
    }
    foreach ($expected as $row) {
        [$label, $value] = $row;
        $have = array_key_exists($label, $byLabel);
        $check("{$fixture}: {$label}", $have && json_encode($byLabel[$label]) === json_encode($value), ($have ? json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES) : 'missing') . ' vs ' . json_encode($value, JSON_UNESCAPED_SLASHES));
    }
}

require $root . '/tests/tools/engine-runtime.php';
$inventory = json_decode(file_get_contents($root . '/contracts/api/functions.json'), true);
foreach (glob($root . '/public/minn/wp-api/*.php') as $file) {
    preg_match_all('/^function\s+(\w+)\s*\(/m', file_get_contents($file), $m);
    foreach ($m[1] as $name) {
        if (str_starts_with($name, '_minn_')) {
            continue;
        }
        $spec = $inventory[$name] ?? null;
        if ($spec === null) {
            $check("{$name}: in inventory", false, basename($file));
            continue;
        }
        $f = new ReflectionFunction($name);
        $ours = [];
        foreach ($f->getParameters() as $p) {
            $ours[] = ($p->isPassedByReference() ? '&' : '') . ($p->isVariadic() ? '...' : '') . $p->getName() . ($p->isDefaultValueAvailable() ? '=' . json_encode($p->isDefaultValueConstant() ? ['const' => $p->getDefaultValueConstantName()] : $p->getDefaultValue()) : '');
        }
        $theirs = [];
        foreach ($spec['params'] as $p) {
            $theirs[] = (!empty($p['byRef']) ? '&' : '') . (!empty($p['variadic']) ? '...' : '') . $p['name'] . (array_key_exists('default', $p) ? '=' . json_encode($p['default']) : '');
        }
        $check("{$name}: signature", $ours === $theirs, implode(', ', $ours) . ' vs ' . implode(', ', $theirs));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
