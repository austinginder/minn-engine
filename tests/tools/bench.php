<?php
/**
 * How fast the engine answers, against WordPress or against itself.
 *
 * Minn against WordPress: the test site (the engine) and its twin (the
 * reference, real WordPress on the same database, served by the same Cove
 * server) are asked the same pages in turn, each over one kept-alive
 * connection, after a warm-up; the time to the first byte and to the last
 * is kept per request, and the median, the 95th percentile and the fastest
 * are reported per page with the engine's share of the reference's time.
 * --throughput adds requests per second under load (ab, keep-alive, a few
 * concurrencies). The machine must be quiet: the load average is read
 * before and after, and a run over the limit is refused unless --force.
 *
 * Minn against an earlier Minn (--engine=<commit>): the commit is checked
 * out in a scratch worktree, and each engine is served by PHP's own server
 * (opcache on) from a scratch webroot that shares the test site's files and
 * database but holds that engine; the same pages are asked of both in turn.
 * This is the engine alone, PHP's server for both, so it answers "did a
 * change make the engine slower", not "how fast is Minn behind Cove".
 *
 * Writes nothing unless --write: then contracts/benchmarks.json keeps the
 * run (commit, machine, figures) beside the earlier ones.
 *
 * php tests/tools/bench.php [--runs=20] [--throughput] [--force] [--write] [--only=/,/hello-world/]
 * php tests/tools/bench.php --engine=<commit> [--runs=20] [--force]
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/local.php';
$args = getopt('', ['runs:', 'throughput', 'force', 'write', 'only:', 'engine:', 'load:']);
$runs = max(3, (int) ($args['runs'] ?? 20));
$force = isset($args['force']);
$only = isset($args['only']) ? explode(',', (string) $args['only']) : null;

/** The pages asked, as the suites know them: the public site, a search and a feed, REST lists and items, a miss. */
$pages = [
    '/' => 'front page',
    '/hello-world/' => 'a post',
    '/sample-page/' => 'a page',
    '/category/uncategorized/' => 'a category',
    '/?s=hello' => 'a search',
    '/feed/' => 'the feed',
    '/wp-json/' => 'REST index',
    '/wp-json/wp/v2/posts' => 'REST posts',
    '/wp-json/wp/v2/posts/1' => 'REST one post',
    '/wp-json/wp/v2/categories' => 'REST categories',
    '/zz-bench-not-found/' => 'a 404',
];
if ($only !== null) {
    $pages = array_intersect_key($pages, array_flip($only));
}

$cores = (int) trim((string) shell_exec('sysctl -n hw.ncpu 2>/dev/null')) ?: 1;
$limit = (float) ($args['load'] ?? max(2.0, $cores * 0.25));
$load = static fn (): float => sys_getloadavg()[0] ?? 0.0;
$before = $load();
if ($before > $limit && !$force) {
    fwrite(STDERR, sprintf("The machine is busy (load %.1f over %.1f on %d cores); a timing now measures the other work. Wait, or --force.\n", $before, $limit, $cores));
    exit(2);
}

/** One kept-alive connection per stack, so neither pays a handshake the other does not. */
$client = static function (string $base, ?string $host = null): Closure {
    $handle = curl_init();
    return static function (string $path) use ($handle, $base, $host): array {
        curl_setopt_array($handle, [
            CURLOPT_URL => $base . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_HTTPHEADER => $host === null ? [] : ["Host: {$host}"],
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($handle);
        return [
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'first' => (float) curl_getinfo($handle, CURLINFO_STARTTRANSFER_TIME) - (float) curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME),
            'total' => (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) - (float) curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME),
            'bytes' => is_string($body) ? strlen($body) : 0,
        ];
    };
};

/** The median, the 95th percentile and the fastest of a list of seconds, in milliseconds. */
$stats = static function (array $seconds): array {
    sort($seconds);
    $n = count($seconds);
    $at = static fn (float $q): float => $seconds[min($n - 1, (int) floor($q * ($n - 1) + 0.5))];
    return ['median' => round($at(0.5) * 1000, 1), 'p95' => round($at(0.95) * 1000, 1), 'fastest' => round($seconds[0] * 1000, 1)];
};

/**
 * Both stacks asked every page, in turn and in a shuffled order each round, after three warm-ups each.
 *
 * @param array<string, Closure> $stacks name => client
 * @return array<string, array<string, array<string, mixed>>> path => stack => figures
 */
$measure = static function (array $stacks) use ($pages, $runs, $stats): array {
    $times = [];
    foreach (array_keys($pages) as $path) {
        foreach ($stacks as $client) {
            for ($i = 0; $i < 3; $i++) {
                $client($path);
            }
        }
    }
    for ($r = 0; $r < $runs; $r++) {
        foreach (array_keys($pages) as $path) {
            $order = array_keys($stacks);
            shuffle($order);
            foreach ($order as $name) {
                $answer = $stacks[$name]($path);
                $times[$path][$name]['first'][] = $answer['first'];
                $times[$path][$name]['total'][] = $answer['total'];
                $times[$path][$name]['status'] = $answer['status'];
                $times[$path][$name]['bytes'] = $answer['bytes'];
            }
        }
    }
    $out = [];
    foreach ($times as $path => $byStack) {
        foreach ($byStack as $name => $t) {
            $out[$path][$name] = ['status' => $t['status'], 'bytes' => $t['bytes'], 'first' => $stats($t['first']), 'total' => $stats($t['total'])];
        }
    }
    return $out;
};

/** The figures as a table: each page's median and p95 on both stacks, and the first stack's share of the second's median. */
$table = static function (array $figures, string $a, string $b) use ($pages): void {
    printf("%-26s %10s %10s %10s %10s %8s  %s\n", 'page', "{$a} med", "{$a} p95", "{$b} med", "{$b} p95", "{$a}/{$b}", 'status');
    $shares = [];
    foreach ($figures as $path => $f) {
        $share = $f[$b]['total']['median'] > 0 ? $f[$a]['total']['median'] / $f[$b]['total']['median'] : 0.0;
        $shares[] = $share;
        printf(
            "%-26s %8.1fms %8.1fms %8.1fms %8.1fms %7.0f%%  %s\n",
            $pages[$path] ?? $path,
            $f[$a]['total']['median'],
            $f[$a]['total']['p95'],
            $f[$b]['total']['median'],
            $f[$b]['total']['p95'],
            $share * 100,
            $f[$a]['status'] . '/' . $f[$b]['status'],
        );
    }
    $geo = $shares === [] ? 0.0 : exp(array_sum(array_map(static fn ($s) => log(max($s, 1e-9)), $shares)) / count($shares));
    printf("%-26s %53s %7.0f%%\n", 'all pages (geometric mean)', '', $geo * 100);
};

/** Requests per second under load (ab, keep-alive), at each concurrency, for one address. */
$throughput = static function (string $url, int $concurrency): float {
    $out = (string) shell_exec(sprintf('ab -q -k -n %d -c %d %s 2>/dev/null', 200, $concurrency, escapeshellarg($url)));
    return preg_match('/Requests per second:\s+([\d.]+)/', $out, $m) ? (float) $m[1] : 0.0;
};

$commit = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD'));
$dirty = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain -- public/minn')) !== '';
$record = ['measured' => date('c'), 'engine' => $commit . ($dirty ? '+changes' : ''), 'php' => PHP_VERSION, 'cores' => $cores, 'runs' => $runs, 'loadBefore' => round($before, 2)];

if (isset($args['engine'])) {
    // ---- Minn against an earlier Minn: each engine behind PHP's own server --------------------------
    $then = (string) $args['engine'];
    $site = minn_test_sites_dir() . '/minn.localhost/public';
    $scratch = sys_get_temp_dir() . '/minn-bench-' . getmypid();
    $tree = "{$scratch}/tree";
    exec('git -C ' . escapeshellarg($root) . ' worktree add --detach --quiet ' . escapeshellarg($tree) . ' ' . escapeshellarg($then), $o, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Could not check out {$then}.\n");
        exit(1);
    }
    $servers = [];
    $webroot = static function (string $name, string $engine) use ($scratch, $site): string {
        $dir = "{$scratch}/{$name}";
        mkdir($dir, 0777, true);
        foreach (scandir($site) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'minn') {
                continue;
            }
            symlink("{$site}/{$entry}", "{$dir}/{$entry}");
        }
        symlink($engine, "{$dir}/minn");
        return $dir;
    };
    $router = "{$scratch}/router.php";
    file_put_contents($router, <<<'PHP'
<?php
// The request as Cove hands it to the site: the site's host, over HTTPS; files that exist are sent as they are.
$_SERVER['HTTP_HOST'] = 'minn.localhost';
$_SERVER['SERVER_NAME'] = 'minn.localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$file = $_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['SCRIPT_FILENAME'];
PHP);
    $port = 8300 + random_int(0, 500);
    $stacks = [];
    foreach (['now' => "{$root}/public/minn", 'then' => "{$tree}/public/minn"] as $name => $engine) {
        $dir = $webroot($name, $engine);
        $p = $port++;
        $servers[] = proc_open(['php', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0', '-S', "127.0.0.1:{$p}", '-t', $dir, $router], [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        $stacks[$name] = $client("http://127.0.0.1:{$p}");
    }
    usleep(500000);
    try {
        $figures = $measure($stacks);
        echo "Minn now ({$record['engine']}) against Minn at {$then}, PHP's own server for both, {$runs} rounds\n\n";
        $table($figures, 'now', 'then');
    } finally {
        foreach ($servers as $server) {
            proc_terminate($server);
        }
        exec('git -C ' . escapeshellarg($root) . ' worktree remove --force ' . escapeshellarg($tree));
        exec('rm -rf ' . escapeshellarg($scratch));
    }
    $record += ['mode' => 'engine', 'then' => $then, 'pages' => $figures];
} else {
    // ---- Minn against WordPress: the test site and its twin, both behind Cove ------------------------
    // As the suites name them (lib.php, which this does not load: it pins the site's language as it loads).
    $engineUrl = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
    $referenceUrl = rtrim(getenv('MINN_TEST_REF') ?: 'https://ref.minn.localhost', '/');
    $figures = $measure(['minn' => $client($engineUrl), 'wordpress' => $client($referenceUrl)]);
    echo "Minn ({$record['engine']}) against WordPress, the same database and server, {$runs} rounds\n\n";
    $table($figures, 'minn', 'wordpress');
    $record += ['mode' => 'wordpress', 'pages' => $figures];
    if (isset($args['throughput'])) {
        echo "\nRequests per second (ab, keep-alive, 200 requests)\n";
        printf("%-26s %6s %12s %12s\n", 'page', 'conc.', 'minn', 'wordpress');
        foreach (['/', '/hello-world/', '/wp-json/wp/v2/posts'] as $path) {
            foreach ([1, 4, 8] as $c) {
                $m = $throughput($engineUrl . $path, $c);
                $w = $throughput($referenceUrl . $path, $c);
                $record['throughput'][$path][$c] = ['minn' => $m, 'wordpress' => $w];
                printf("%-26s %6d %12.1f %12.1f\n", $pages[$path] ?? $path, $c, $m, $w);
            }
        }
    }
}

$after = $load();
$record['loadAfter'] = round($after, 2);
printf("\nload %.1f before, %.1f after (limit %.1f on %d cores)%s\n", $before, $after, $limit, $cores, $after > $limit ? ': the machine got busy during the run, read the figures with care' : '');

if (isset($args['write'])) {
    $file = "{$root}/contracts/benchmarks.json";
    $kept = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $kept['runs'][] = $record;
    file_put_contents($file, json_encode($kept, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "kept in contracts/benchmarks.json\n";
}
