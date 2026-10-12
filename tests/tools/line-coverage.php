<?php
/**
 * Engine line coverage: the share of public/minn/src/Minn's executable
 * lines that the engine's own checks run. Each part runs under phpdbg (the
 * coverage driver this machine has; the site's web server loads none), with
 * tests/tools/coverage-run.php, which notes the lines each process ran:
 *
 * - unit:   tests/unit.test.php;
 * - probes: every api probe (tests/api.test.php's map) through run-api-probe.php;
 * - http:   the pages, feeds, REST routes and sign-in screens of the
 *           hook-parity battery, signed out and as an administrator, each
 *           served inside a process of its own (tests/tools/coverage-request.php);
 * - suites: the suites of tests/run-all.sh (but unit and api, which the parts
 *           above measure), each run as the full run runs it, with the test
 *           site's requests served by the engine under phpdbg for the length
 *           of the run: Cove's web server is pointed at a server of this tool
 *           (tests/tools/coverage-server.php) through its admin API, and put
 *           back after, and `wp` on the PATH runs WP-CLI under phpdbg for the
 *           test site (tests/tools/coverage-wp.sh). The suites' own verdicts
 *           are not the point here (headers plugin code sends are lost, and
 *           everything is slower); --suites=a,b runs only those.
 *
 * Writes contracts/line-coverage.json (the figure, per part, per file) and
 * prints the total and the least covered files. Each part's lines are kept
 * in the system temp directory, so a later run of some parts counts the
 * others as they last ran (--fresh forgets them).
 *
 * php tests/tools/line-coverage.php [--parts=unit,probes,http,suites] [--suites=a,b] [--files=20] [--fresh]
 */

require __DIR__ . '/../lib.php';

$options = getopt('', ['parts:', 'suites:', 'files:', 'fresh']);
$parts = array_filter(explode(',', (string) ($options['parts'] ?? 'unit,probes,http,suites')));
$root = dirname(__DIR__, 2);
$src = realpath("{$root}/public/minn/src/Minn") . '/';
$phpdbg = trim((string) shell_exec('command -v phpdbg'));
if ($phpdbg === '') {
    fwrite(STDERR, "phpdbg is not on the PATH\n");
    exit(1);
}
$runner = escapeshellarg("{$root}/tests/tools/coverage-run.php");
$dir = sys_get_temp_dir() . '/minn-line-coverage-' . getmypid();
@mkdir($dir, 0755, true);
register_shutdown_function(static function () use ($dir): void {
    array_map('unlink', glob("{$dir}/*") ?: []);
    @rmdir($dir);
});

/** Runs a script under phpdbg through the coverage runner; its output is discarded. */
$run = static function (string $script, string $args = '') use ($phpdbg, $runner, $dir): void {
    shell_exec('MINN_COVERAGE_DIR=' . escapeshellarg($dir) . ' ' . escapeshellarg($phpdbg) . " -qrr -d memory_limit=2G {$runner} " . escapeshellarg($script) . ($args === '' ? '' : ' ' . $args) . ' >/dev/null 2>&1');
};
/** @return array<string, array<int, true>> the lines the records in the coverage directory ran, then empties it */
$collect = static function () use ($dir): array {
    $ran = [];
    foreach (glob("{$dir}/*.json") ?: [] as $file) {
        foreach ((array) json_decode((string) file_get_contents($file), true) as $path => $lines) {
            foreach ((array) $lines as $line) {
                $ran[(string) $path][(int) $line] = true;
            }
        }
        unlink($file);
    }
    return $ran;
};

// The probes and requests run here write as they always do, but under phpdbg a probe that dies (a fatal the
// cli SAPI would not meet) never reaches its own clean-up: the test site's database is put back afterwards.
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference');
if (array_intersect(['probes', 'http', 'suites'], $parts) !== []) {
    $snapshot = sys_get_temp_dir() . '/minn-line-coverage-database-' . getmypid() . '.sql';
    shell_exec("{$WP} db export " . escapeshellarg($snapshot) . ' >/dev/null 2>&1');
    if (!is_file($snapshot) || filesize($snapshot) < 1000) {
        fwrite(STDERR, "could not snapshot the test site's database\n");
        exit(1);
    }
    register_shutdown_function(static function () use ($WP, $snapshot): void {
        shell_exec("{$WP} db import " . escapeshellarg($snapshot) . ' >/dev/null 2>&1');
        @unlink($snapshot);
        fwrite(STDERR, "test site's database put back\n");
    });
}

$byPart = [];
if (in_array('unit', $parts, true)) {
    $run("{$root}/tests/unit.test.php");
    $byPart['unit'] = $collect();
    fwrite(STDERR, "unit done\n");
}
if (in_array('probes', $parts, true)) {
    preg_match_all("/'[a-z0-9-]+' => '([a-z0-9-]+-probe\\.php)'/", (string) file_get_contents("{$root}/tests/api.test.php"), $listed);
    foreach ($listed[1] as $i => $probe) {
        $run("{$root}/tests/tools/run-api-probe.php", escapeshellarg($probe));
        if ($i % 25 === 0) {
            fwrite(STDERR, "probes {$i}/" . count($listed[1]) . "\n");
        }
    }
    $byPart['probes'] = $collect();
}
if (in_array('http', $parts, true)) {
    $mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg("{$root}/tests/tools/mint-session.php") . ' 1 2>/dev/null'), true);
    $cookie = is_array($mint) ? 'wordpress_logged_in_' . md5(minn_test_url()) . '=' . rawurlencode((string) $mint['cookie']) : '';
    $signedOut = ['/', '/hello-world/', '/sample-page/', '/category/uncategorized/', '/author/admin/', '/?s=hello', '/no-such-page-here/', '/hello-world/embed/', '/feed/', '/comments/feed/', '/feed/atom/', '/robots.txt', '/wp-sitemap.xml', '/wp-sitemap-posts-post-1.xml', '/?rest_route=/', '/?rest_route=/wp/v2/posts', '/?rest_route=/wp/v2/posts/1', '/?rest_route=/wp/v2/pages', '/?rest_route=/wp/v2/categories', '/?rest_route=/wp/v2/tags', '/?rest_route=/wp/v2/comments', '/?rest_route=/wp/v2/users', '/?rest_route=/wp/v2/types', '/?rest_route=/wp/v2/taxonomies', '/?rest_route=/wp/v2/search&search=hello', '/?rest_route=/oembed/1.0/embed&url=' . rawurlencode(minn_test_url() . '/hello-world/'), '/wp-login.php', '/wp-login.php?action=lostpassword', '/wp-login.php?action=register', '/xmlrpc.php', '/wp-admin/'];
    $signedIn = ['/', '/hello-world/', '/wp-admin/', '/?rest_route=/wp/v2/users/me', '/?rest_route=/wp/v2/settings', '/?rest_route=/wp/v2/posts&context=edit', '/?rest_route=/wp/v2/pages&context=edit', '/?rest_route=/wp/v2/plugins', '/?rest_route=/wp/v2/themes', '/?rest_route=/wp/v2/templates', '/?rest_route=/wp/v2/global-styles/themes/twentytwentyfive', '/?rest_route=/wp/v2/block-types', '/?rest_route=/wp/v2/media', '/?rest_route=/minn-admin/v1/overview'];
    // Every GET route the engine declares (contracts/api/routes.json), its captures filled with values the
    // test site has; REST routes go through ?rest_route=. Signed in, so the policies let them through.
    $samples = ['id' => '1', 'parent' => '1', 'user_id' => '1', 'location' => 'primary', 'stylesheet' => 'twentytwentyfive', 'theme' => 'twentytwentyfive', 'taxonomy' => 'category', 'type' => 'post', 'post_type' => 'post', 'slug' => 'hello-world', 'name' => 'core/paragraph', 'namespace' => 'core', 'uuid' => '0', 'plugin' => 'akismet/akismet', 'area' => 'header'];
    $catalogue = (array) (json_decode((string) file_get_contents("{$root}/contracts/api/routes.json"), true)['entries'] ?? []);
    foreach ($catalogue as $route) {
        if (($route['method'] ?? '') !== 'GET') {
            continue;
        }
        $fill = (string) preg_replace_callback('/\{(\w+)(?::([^}]*))?\}/', static function (array $m) use ($samples): string {
            if (($m[2] ?? '') !== '' && preg_match('/^[a-z-]+(\|[a-z-]+)+$/', $m[2]) === 1) {
                return explode('|', $m[2])[0];
            }
            return $samples[$m[1]] ?? '1';
        }, (string) preg_replace('/\{\w+\*\}/', '', (string) $route['pattern']));
        $rest = preg_match('#^/(wp/v2|minn-admin/v1|oembed/|wp-abilities/|wp-block-editor/|wp-site-health/|batch/)#', $fill) === 1;
        $signedIn[] = $rest ? '/?rest_route=' . rawurlencode($fill) : $fill;
    }
    $signedIn = array_values(array_unique($signedIn));
    foreach ($signedOut as $path) {
        $run("{$root}/tests/tools/coverage-request.php", escapeshellarg($path));
    }
    foreach ($cookie === '' ? [] : $signedIn as $path) {
        $run("{$root}/tests/tools/coverage-request.php", escapeshellarg($path) . ' ' . escapeshellarg("--cookie={$cookie}") . ' ' . escapeshellarg('--nonce=' . $mint['nonce']));
    }
    $byPart['http'] = $collect();
    fwrite(STDERR, "http done\n");
}

if (in_array('suites', $parts, true)) {
    $byPart['suites'] = (require __DIR__ . '/coverage-suites.php')($root, $dir, $phpdbg, $options, $collect);
    fwrite(STDERR, "suites done\n");
}

// The parts not run this time, as they last ran.
$cacheDir = sys_get_temp_dir() . '/minn-line-coverage-parts';
@mkdir($cacheDir, 0755, true);
foreach (['unit', 'probes', 'http', 'suites'] as $part) {
    if (isset($byPart[$part])) {
        file_put_contents("{$cacheDir}/{$part}.json", json_encode(array_map('array_keys', $byPart[$part])));
    } elseif (!isset($options['fresh']) && is_file("{$cacheDir}/{$part}.json")) {
        $byPart[$part] = array_map(static fn (array $lines): array => array_fill_keys($lines, true), (array) json_decode((string) file_get_contents("{$cacheDir}/{$part}.json"), true));
    }
}

// Every engine class's executable lines: each loaded through the engine's autoloader (the files only declare), in one process.
$classes = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $classes[] = 'Minn\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($src), -4));
    }
}
$lister = "{$dir}/executable.php";
file_put_contents("{$dir}/classes.json", json_encode($classes));
file_put_contents($lister, '<?php require ' . var_export("{$src}Autoloader.php", true) . '; Minn\Autoloader::register();'
    . ' foreach (json_decode(file_get_contents(' . var_export("{$dir}/classes.json", true) . '), true) as $class) { class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class); }'
    . ' echo json_encode(phpdbg_get_executable());');
$executable = [];
foreach ((array) json_decode((string) shell_exec(escapeshellarg($phpdbg) . ' -qrr ' . escapeshellarg($lister) . ' 2>/dev/null'), true) as $path => $lines) {
    $real = realpath((string) $path);
    if ($real !== false && str_starts_with($real, $src)) {
        $executable[substr($real, strlen($src))] = array_map('intval', array_keys((array) $lines));
    }
}
ksort($executable);

$floor = static fn (float $value): float => floor($value * 10) / 10;
$total = 0;
$covered = 0;
$perFile = [];
$perPart = array_fill_keys(array_keys($byPart), 0);
foreach ($executable as $path => $lines) {
    $hit = 0;
    foreach ($lines as $line) {
        $any = false;
        foreach ($byPart as $part => $ran) {
            if (isset($ran[$path][$line])) {
                $perPart[$part]++;
                $any = true;
            }
        }
        $hit += $any ? 1 : 0;
    }
    $total += count($lines);
    $covered += $hit;
    $perFile[$path] = [$hit, count($lines)];
}
$report = [
    'measured' => gmdate('Y-m-d'),
    'parts' => array_keys($byPart),
    'lines' => $total,
    'covered' => $covered,
    'percent' => $floor(100 * $covered / max(1, $total)),
    'byPart' => array_map(static fn (int $n): float => $floor(100 * $n / max(1, $total)), $perPart),
    'files' => $perFile,
];
file_put_contents("{$root}/contracts/line-coverage.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("src/Minn: %d of %d executable lines run (%.1f%%)\n", $covered, $total, $report['percent']);
foreach ($report['byPart'] as $part => $percent) {
    printf("  %-7s %.1f%%\n", $part, $percent);
}
$least = $perFile;
uasort($least, static fn (array $a, array $b): int => ($b[1] - $b[0]) <=> ($a[1] - $a[0]));
echo "most lines unrun:\n";
foreach (array_slice($least, 0, (int) ($options['files'] ?? 20), true) as $path => [$hit, $all]) {
    printf("  %5d unrun of %5d  %s\n", $all - $hit, $all, $path);
}
