<?php
/**
 * How much of WordPress Minn covers, measured from the code:
 *
 * - Surface: each function in the reference's inventory
 *   (contracts/api/functions.json) is implemented (a body that does
 *   something, contracts/api/mappings.json), a one-line constant return, a
 *   placeholder (wp-api/placeholders.php: loads, does nothing) or missing,
 *   split between wp-includes and wp-admin.
 * - What plugins use: the same, for the WordPress functions the plugins on
 *   a site call (the minn_runtime_symbols scan Minn keeps per site),
 *   counted once and by how many plugins call each.
 * - Verified: of those, the ones a probe calls directly, so their answers
 *   are compared with the reference's on every api suite run.
 * - Hooks: the reference's fixed-name hooks Minn fires by name (a floor:
 *   names built from variables are not counted).
 *
 *   php tests/tools/coverage.php [site root] [--json]
 *   (site root: a Cove site whose plugins to weigh by; default the shop dogfood site, tests/local.json)
 */

require_once dirname(__DIR__) . '/local.php';
$root = dirname(__DIR__, 2);
$args = array_slice($argv, 1);
$json = in_array('--json', $args, true);
$site = array_values(array_filter($args, static fn ($a) => $a !== '--json'))[0] ?? minn_test_site_dir(minn_test_local('shop'));

$functions = json_decode((string) file_get_contents("{$root}/contracts/api/functions.json"), true);
$hooks = json_decode((string) file_get_contents("{$root}/contracts/api/hooks.json"), true);
$mapped = [];
foreach (json_decode((string) file_get_contents("{$root}/contracts/api/mappings.json"), true)['functions'] as $name => $info) {
    if (!str_contains($name, '::')) {
        $mapped[strtolower($name)] = $info;
    }
}
$facade = [];
foreach (glob("{$root}/public/minn/wp-api/{,*/}*.php", GLOB_BRACE) as $file) {
    if (str_contains($file, 'placeholders')) {
        continue;
    }
    preg_match_all('/^function\s+(\w+)\s*\([^)]*\)[^{]*\{\n(.*?)^\}/ms', (string) file_get_contents($file), $m, PREG_SET_ORDER);
    foreach ($m as [, $name, $body]) {
        $facade[strtolower($name)] = trim($body);
    }
}
preg_match_all('/^function\s+(\w+)\s*\(/m', (string) file_get_contents("{$root}/public/minn/wp-api/placeholders.php"), $m);
$placeholders = array_fill_keys(array_map('strtolower', $m[1]), true);

$kind = static function (string $name) use ($mapped, $facade, $placeholders): string {
    $info = $mapped[$name] ?? null;
    if ($info !== null) {
        if ($info['kind'] !== 'noop' || $info['minn'] !== [] || $info['composes'] !== []) {
            return 'implemented';
        }
        return preg_match('/^(return\s+(null|false|true|\'\'|""|\[\]|0|-1|array\(\)|\$\w+)\s*;|return;|)$/', $facade[$name] ?? '') ? 'constant' : 'implemented';
    }
    return isset($placeholders[$name]) ? 'placeholder' : 'missing';
};
$area = static fn (?string $file): string => str_starts_with((string) $file, 'wp-admin') ? 'wp-admin' : 'wp-includes';

$surface = ['wp-includes' => [], 'wp-admin' => [], 'all' => []];
foreach ($functions as $name => $info) {
    $k = $kind(strtolower($name));
    foreach ([$area($info['file'] ?? null), 'all'] as $bucket) {
        $surface[$bucket][$k] = ($surface[$bucket][$k] ?? 0) + 1;
    }
}

// What the site's plugins call: minn_runtime_symbols, read through WP-CLI.
$scan = json_decode((string) shell_exec('cd ' . escapeshellarg("{$site}/public") . ' && wp option get minn_runtime_symbols --format=json 2>/dev/null'), true) ?: [];
$reference = array_change_key_case(array_fill_keys(array_keys($functions), true));
$files = array_change_key_case(array_map(static fn ($info) => $info['file'] ?? null, $functions));
$users = [];
foreach ($scan as $plugin => $info) {
    foreach (array_unique(array_map('strtolower', (array) ($info['scan']['calls'] ?? []))) as $call) {
        if (isset($reference[$call])) {
            $users[$call] = ($users[$call] ?? 0) + 1;
        }
    }
}
$probes = '';
foreach (glob("{$root}/tests/tools/*-probe.php") as $file) {
    $probes .= (string) file_get_contents($file);
}
preg_match_all('/(?<![\w>$:])([a-z_][a-z0-9_]*)\s*\(/', $probes, $m);
$probed = array_fill_keys(array_map('strtolower', $m[1]), true);
$used = ['functions' => [], 'uses' => [], 'verified' => 0, 'verifiedUses' => 0];
$gaps = [];
foreach ($users as $name => $count) {
    $k = $kind($name);
    $used['functions'][$k] = ($used['functions'][$k] ?? 0) + 1;
    $used['uses'][$k] = ($used['uses'][$k] ?? 0) + $count;
    if (isset($probed[$name])) {
        $used['verified']++;
        $used['verifiedUses'] += $count;
    }
    if ($k === 'placeholder' || $k === 'missing') {
        $gaps[] = ['function' => $name, 'plugins' => $count, 'kind' => $k, 'area' => $area($files[$name] ?? null)];
    }
}
usort($gaps, static fn ($a, $b) => [$b['plugins'], $a['function']] <=> [$a['plugins'], $b['function']]);

$source = '';
foreach (array_merge(glob("{$root}/public/minn/wp-api/{,*/,*/*/}*.php", GLOB_BRACE), glob("{$root}/public/minn/src/Minn/{,*/,*/*/}*.php", GLOB_BRACE)) as $file) {
    $source .= (string) file_get_contents($file);
}
preg_match_all("/(?:apply_filters|apply_filters_ref_array|apply_filters_deprecated|do_action|do_action_ref_array|do_action_deprecated)\\(\\s*'([^'\$]+)'/", $source, $a);
preg_match_all("/->(?:action|filter|filterWithout|actionRefArray|filterRefArray)\\(\\s*'([^'\$]+)'/", $source, $b);
$fired = array_fill_keys(array_merge($a[1], $b[1]), true);
$hookCounts = ['wp-includes' => ['all' => 0, 'fired' => 0], 'wp-admin' => ['all' => 0, 'fired' => 0]];
foreach ($hooks as $name => $info) {
    if ($name === '' || str_contains($name, '{') || str_contains($name, '$')) {
        continue;
    }
    $bucket = array_filter((array) ($info['files'] ?? []), static fn ($f) => !str_starts_with($f, 'wp-admin')) === [] ? 'wp-admin' : 'wp-includes';
    $hookCounts[$bucket]['all']++;
    $hookCounts[$bucket]['fired'] += isset($fired[$name]) ? 1 : 0;
}
$fixtures = 0;
$rows = 0;
foreach (glob("{$root}/contracts/fixtures/api/*.json") as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    $fixtures++;
    $rows += is_array($data) && array_is_list($data) ? count($data) : 1;
}

$report = [
    'generated' => date('c'),
    'site' => basename($site),
    'plugins' => count($scan),
    'surface' => $surface,
    'used' => ['distinct' => count($users), 'uses' => array_sum($users)] + $used,
    'hooks' => $hookCounts,
    'probes' => ['fixtures' => $fixtures, 'rows' => $rows],
    'gaps' => array_slice($gaps, 0, 40),
];
if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    return;
}
$pct = static fn (int $part, int $whole): string => $whole === 0 ? '-' : sprintf('%.1f%%', 100 * $part / $whole);
echo "WordPress functions (reference inventory)\n";
foreach ($surface as $bucket => $counts) {
    $total = array_sum($counts);
    printf("  %-12s %5d: implemented %5d (%s), constant %d, placeholder %d, missing %d\n", $bucket, $total, $counts['implemented'] ?? 0, $pct($counts['implemented'] ?? 0, $total), $counts['constant'] ?? 0, $counts['placeholder'] ?? 0, $counts['missing'] ?? 0);
}
printf("What %d plugins on %s call: %d functions, %d uses\n", count($scan), basename($site), count($users), array_sum($users));
foreach (['implemented', 'constant', 'placeholder', 'missing'] as $k) {
    printf("  %-12s %5d (%s), by use %s\n", $k, $used['functions'][$k] ?? 0, $pct($used['functions'][$k] ?? 0, count($users)), $pct($used['uses'][$k] ?? 0, array_sum($users)));
}
printf("  verified by a probe: %d (%s), by use %s\n", $used['verified'], $pct($used['verified'], count($users)), $pct($used['verifiedUses'], array_sum($users)));
foreach ($hookCounts as $bucket => $c) {
    printf("Hooks %-12s fired %d of %d (%s)\n", $bucket, $c['fired'], $c['all'], $pct($c['fired'], $c['all']));
}
printf("Probe fixtures: %d files, %d rows compared with the reference\n", $fixtures, $rows);
echo 'Most-used gaps: ' . implode(', ', array_map(static fn ($g) => "{$g['function']} ({$g['plugins']})", array_slice($gaps, 0, 20))) . "\n";
