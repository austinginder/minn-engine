<?php
/**
 * WP-CLI's own feature tests on both stacks (measure 34). The Behat
 * scenarios WP-CLI's bundled commands ship (MIT, in a WP-CLI checkout's
 * vendor/wp-cli/<package>/features) run once against WordPress and once
 * against the engine, each scenario on a fresh install, and every scenario
 * is compared: a scenario that passes on the reference and fails on the
 * engine is a difference.
 *
 * Each stack has its own temporary folder, so WP-CLI's core cache (the
 * download it copies into every scenario) is its own: it is filled from the
 * test site's reference WordPress rather than downloaded, the default themes
 * with it. WP_CLI_BIN_DIR is a copy of tests/tools/cli-features/wp with the
 * stack written in: on the engine stack it lets WordPress install each
 * scenario's site and boots the engine for everything after. The scenarios' databases are made with the
 * local server's admin user (Cove's, read from its config at run time).
 *
 *   php tests/tools/cli-features.php [--features=<glob under vendor/wp-cli>]
 *       [--stacks=reference,engine] [--write] [--show]
 *
 * --features defaults to the entity commands (options, posts, terms, users,
 * comments, menus). --write records the pass in contracts/cli-features.json
 * (summary: scenarios, passedReference, passedEngine, passedBoth; then each
 * scenario the engine fails that the reference passes), which scorecard.php
 * reads. A scenario whose steps install or use a multisite network is set
 * aside (counted as setAsideMultisite): the engine serves one site. WP-CLI
 * is MINN_WPCLI_DIR, or ~/Documents/wp-cli.
 */

require_once dirname(__DIR__) . '/lib.php';

$root = dirname(__DIR__, 2);
$options = getopt('', ['features:', 'stacks:', 'write', 'show']);
$wpcli = rtrim(getenv('MINN_WPCLI_DIR') ?: getenv('HOME') . '/Documents/wp-cli', '/');
$glob = (string) ($options['features'] ?? 'entity-command/features/*.feature');
$stacks = explode(',', (string) ($options['stacks'] ?? 'reference,engine'));
$features = glob("{$wpcli}/vendor/wp-cli/{$glob}") ?: [];
if (!is_file("{$wpcli}/vendor/bin/behat") || $features === []) {
    echo "SKIP: no WP-CLI checkout with Behat and features at {$wpcli} ({$glob})\n";
    exit(0);
}

// The local server's admin user, from Cove's config (never written anywhere).
$cove = (string) @file_get_contents(getenv('HOME') . '/Cove/config');
preg_match('/^DB_USER=["\']?([^"\'\n]*)/m', $cove, $user);
preg_match('/^DB_PASSWORD=["\']?([^"\'\n]*)/m', $cove, $password);

/** Copies the reference's core (no config, no content but the default themes) into a stack's core cache. */
$fillCache = static function (string $cache): void {
    if (is_file("{$cache}/wp-config-sample.php")) {
        return;
    }
    $source = minn_test_site_root() . '/wp-reference';
    @mkdir("{$cache}/wp-content/themes", 0777, true);
    @mkdir("{$cache}/wp-content/plugins", 0777, true);
    $copy = static fn (string $from, string $to): string => (string) shell_exec('cp -R ' . escapeshellarg($from) . ' ' . escapeshellarg($to) . ' 2>&1');
    foreach (scandir($source) ?: [] as $entry) {
        if (!in_array($entry, ['.', '..', 'wp-content', 'wp-config.php', 'router.php'], true)) {
            $copy("{$source}/{$entry}", "{$cache}/{$entry}");
        }
    }
    foreach (['twentytwentythree', 'twentytwentyfour', 'twentytwentyfive'] as $theme) {
        $copy("{$source}/wp-content/themes/{$theme}", "{$cache}/wp-content/themes/{$theme}");
    }
    file_put_contents("{$cache}/wp-content/index.php", "<?php\n// Silence is golden.\n");
    file_put_contents("{$cache}/wp-content/plugins/index.php", "<?php\n// Silence is golden.\n");
};

/**
 * One stack's pass: each scenario's outcome, keyed by feature and scenario name.
 *
 * @return array<string, bool>
 */
// Separate passes run side by side each on a database and temporary folder of their own (MINN_CLI_DB, MINN_CLI_TMP).
$database = preg_replace('/[^a-z0-9_]/', '', (string) (getenv('MINN_CLI_DB') ?: 'minn_cli_test'));
$pass = static function (string $stack) use ($root, $wpcli, $features, $user, $password, $fillCache, $database): array {
    $tmp = (getenv('MINN_CLI_TMP') ?: sys_get_temp_dir() . '/minn-cli-features') . "/{$stack}";
    @mkdir($tmp, 0777, true);
    $fillCache("{$tmp}/wp-cli-test-core-download-cache");
    $out = "{$tmp}/junit";
    shell_exec('rm -rf ' . escapeshellarg($out));
    $config = "{$tmp}/behat.yml";
    // WP-CLI hands its commands only a few variables, so the stack's own `wp` has its settings written in.
    @mkdir("{$tmp}/bin", 0777, true);
    $settings = "MINN_CLI_STACK=" . escapeshellarg($stack) . "\nMINN_WPCLI_DIR=" . escapeshellarg($wpcli) . "\nMINN_ENGINE_DIR=" . escapeshellarg("{$root}/public/minn") . "\n";
    file_put_contents("{$tmp}/bin/wp", preg_replace('/^set -u$/m', "set -u\n{$settings}", (string) file_get_contents("{$root}/tests/tools/cli-features/wp"), 1));
    chmod("{$tmp}/bin/wp", 0755);
    // Each scenario copies the core cache with `cp -R`; on macOS a `cp` that clones (APFS) makes those copies cost almost no disk.
    @mkdir("{$tmp}/shim", 0777, true);
    file_put_contents("{$tmp}/shim/cp", PHP_OS_FAMILY === 'Darwin' ? "#!/bin/sh\nexec /bin/cp -c \"\$@\"\n" : "#!/bin/sh\nexec /bin/cp \"\$@\"\n");
    chmod("{$tmp}/shim/cp", 0755);
    $env = [
        'TMPDIR' => $tmp . '/',
        'MINN_CLI_STACK' => $stack,
        'MINN_WPCLI_DIR' => $wpcli,
        'MINN_ENGINE_DIR' => "{$root}/public/minn",
        'WP_CLI_BIN_DIR' => "{$tmp}/bin",
        'PATH' => "{$tmp}/shim:" . getenv('PATH'),
        'WP_CLI_TEST_DBROOTUSER' => $user[1] ?? 'root',
        'WP_CLI_TEST_DBROOTPASS' => $password[1] ?? '',
        'WP_CLI_TEST_DBNAME' => $database,
        'WP_CLI_TEST_DBUSER' => 'minn_cli_test',
        'WP_CLI_TEST_DBPASS' => 'minn_cli_test',
        'WP_CLI_TEST_DBHOST' => 'localhost',
    ];
    $prefix = '';
    foreach ($env as $name => $value) {
        $prefix .= $name . '=' . escapeshellarg((string) $value) . ' ';
    }
    // The scenarios' database and user, made once with the admin user.
    $sql = "CREATE DATABASE IF NOT EXISTS {$database}; CREATE USER IF NOT EXISTS 'minn_cli_test'@'localhost' IDENTIFIED BY 'minn_cli_test'; GRANT ALL PRIVILEGES ON {$database}.* TO 'minn_cli_test'@'localhost';";
    shell_exec('MYSQL_PWD=' . escapeshellarg($password[1] ?? '') . ' mariadb -u' . escapeshellarg($user[1] ?? 'root') . ' -e ' . escapeshellarg($sql) . ' 2>&1');
    // One feature file at a time, its scenarios' sites removed after it: each is a whole WordPress (a full pass of them filled the disk).
    foreach ($features as $n => $feature) {
        if ((float) @disk_free_space($tmp) < 5e9) {
            fwrite(STDERR, "stopped before {$feature}: under 5 GB free\n");
            break;
        }
        file_put_contents($config, "default:\n  suites:\n    default:\n      contexts:\n        - WP_CLI\\Tests\\Context\\FeatureContext\n      paths:\n        - {$feature}\n");
        shell_exec('cd ' . escapeshellarg($wpcli) . ' && ' . $prefix . 'php vendor/bin/behat --config=' . escapeshellarg($config) . ' --format=junit --out=' . escapeshellarg("{$out}/{$n}") . ' --no-colors 2>&1');
        foreach (glob("{$tmp}/wp-cli-test-run-*") ?: [] as $site) {
            shell_exec('rm -rf ' . escapeshellarg($site) . ' ' . escapeshellarg("{$site}-parked"));
        }
    }
    $outcomes = [];
    foreach (glob("{$out}/*/*.xml") ?: [] as $file) {
        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            continue;
        }
        foreach ($xml->xpath('//testsuite') ?: [] as $suite) {
            foreach ($suite->testcase as $case) {
                $key = (string) $suite['name'] . ' :: ' . (string) $case['name'];
                $outcomes[$key] = !isset($case->failure) && !isset($case->error) && (string) ($case['status'] ?? 'passed') !== 'skipped';
            }
        }
    }
    return $outcomes;
};

// Scenarios that install or work on a multisite network are set aside: the engine serves one site, and a single site never loads multisite.
$asideMultisite = [];
foreach ($features as $feature) {
    $text = (string) file_get_contents($feature);
    $title = preg_match('/^\s*Feature:\s*(.+)$/m', $text, $t) === 1 ? trim($t[1]) : '';
    foreach (preg_split('/^(?=\s*Scenario(?: Outline)?:)/m', $text) ?: [] as $block) {
        if (preg_match('/^\s*Scenario(?: Outline)?:\s*(.+)$/m', $block, $n) === 1 && stripos($block, 'multisite') !== false) {
            $asideMultisite["{$title} :: " . trim($n[1])] = true;
        }
    }
}

$results = [];
foreach ($stacks as $stack) {
    $started = microtime(true);
    $results[$stack] = $pass($stack);
    printf("%s: %d scenarios, %d passed (%d s)\n", $stack, count($results[$stack]), count(array_filter($results[$stack])), (int) (microtime(true) - $started));
}
if (!isset($results['reference'], $results['engine'])) {
    exit(0);
}
$scenarios = array_values(array_filter(array_keys($results['reference'] + $results['engine']), static fn (string $k): bool => !isset($asideMultisite[$k])));
$both = array_filter($scenarios, static fn (string $k): bool => ($results['reference'][$k] ?? false) && ($results['engine'][$k] ?? false));
$failing = array_values(array_filter($scenarios, static fn (string $k): bool => ($results['reference'][$k] ?? false) && !($results['engine'][$k] ?? false)));
$passed = static fn (string $stack): int => count(array_filter($scenarios, static fn (string $k): bool => $results[$stack][$k] ?? false));
$summary = ['scenarios' => count($scenarios), 'passedReference' => $passed('reference'), 'passedEngine' => $passed('engine'), 'passedBoth' => count($both), 'setAsideMultisite' => count(array_intersect_key($asideMultisite, $results['reference'] + $results['engine']))];
printf("cli-features: %d scenarios; reference passes %d, engine %d, both %d; %d pass on the reference only\n", $summary['scenarios'], $summary['passedReference'], $summary['passedEngine'], $summary['passedBoth'], count($failing));
if (isset($options['show'])) {
    foreach ($failing as $key) {
        echo "  ENGINE FAILS  {$key}\n";
    }
}
if (isset($options['write'])) {
    $record = ['method' => "tests/tools/cli-features.php: WP-CLI's bundled feature scenarios ({$glob}) on the reference and on the engine, each on a fresh install", 'measured' => date('Y-m-d'), 'summary' => $summary, 'engineFails' => $failing];
    file_put_contents("{$root}/contracts/cli-features.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    echo "wrote contracts/cli-features.json\n";
}
