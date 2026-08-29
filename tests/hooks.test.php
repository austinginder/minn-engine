<?php
/**
 * Hook-API suite: runs tests/tools/hooks-probe.php on the engine's facade
 * and diffs the transcript, row by row, against the one captured from the
 * reference (contracts/fixtures/api/hooks.json). Also checks that every
 * facade function's signature matches the interface inventory.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';
$root = dirname(__DIR__);
require $root . '/public/minn/src/Minn/Autoloader.php';
Minn\Autoloader::register();
Minn\Runtime\Runtime::loadFacade($root . '/public/minn');

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

$expected = json_decode(file_get_contents($root . '/contracts/fixtures/api/hooks.json'), true);
ob_start();
require $root . '/tests/tools/hooks-probe.php';
$actual = json_decode(ob_get_clean(), true);

$check('probe row count', count($actual) === count($expected), count($actual) . ' vs ' . count($expected));
foreach ($expected as $i => $row) {
    $label = $row[0] === 'cb' ? "cb {$row[1]} #{$i}" : $row[0];
    $check($label, json_encode($actual[$i] ?? null) === json_encode($row), json_encode($actual[$i] ?? null) . ' vs ' . json_encode($row));
}

// Signatures: every function the facade defines must match the inventory's parameter names and defaults.
$inventory = json_decode(file_get_contents($root . '/contracts/api/functions.json'), true);
$before = get_defined_functions()['user'];
foreach (glob($root . '/public/minn/wp-api/*.php') as $file) {
    $source = file_get_contents($file);
    preg_match_all('/^function\s+(\w+)\s*\(/m', $source, $m);
    foreach ($m[1] as $name) {
        $spec = $inventory[$name] ?? null;
        if ($spec === null) {
            $check("{$name}: in inventory", false, basename($file));
            continue;
        }
        $f = new ReflectionFunction($name);
        $ours = [];
        foreach ($f->getParameters() as $p) {
            $ours[] = ($p->isVariadic() ? '...' : '') . $p->getName() . ($p->isDefaultValueAvailable() ? '=' . json_encode($p->isDefaultValueConstant() ? ['const' => $p->getDefaultValueConstantName()] : $p->getDefaultValue()) : '');
        }
        $theirs = [];
        foreach ($spec['params'] as $p) {
            $theirs[] = (!empty($p['variadic']) ? '...' : '') . $p['name'] . (array_key_exists('default', $p) ? '=' . json_encode($p['default']) : '');
        }
        $check("{$name}: signature", $ours === $theirs, implode(', ', $ours) . ' vs ' . implode(', ', $theirs));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
