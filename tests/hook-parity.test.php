<?php

declare(strict_types=1);

/**
 * Hook parity (audit 2026-10-12): the hooks plugins are told about, held to
 * the reference's. A battery of scenarios runs on both stacks with every
 * hook recorded (tests/fixtures/mu-plugins/minn-test-hooks.php): each api
 * probe (tests/tools/hook-record-probe.php), and pages, feeds, REST routes
 * and the sign-in screen over HTTP, signed out and as an administrator.
 * Hooks are compared as contracts/api/hooks.json names them: a dynamic
 * hook's instances (pre_option_blogname) count toward its pattern
 * (pre_option_{*}), and names that are not the reference's (a plugin's own,
 * the engine's) are left out on both sides. In each scenario the engine
 * fires every hook the reference fires, as the same kind (action or filter)
 * with as many arguments, and none the reference does not.
 *
 * The reference's side is captured once into contracts/fixtures/hooks/
 * battery.json (--capture), twice over, and a hook only one run fired is
 * left out of that scenario. The engine's side is recorded live; a scenario
 * that differs runs again, and only what differs both times counts.
 *
 * Where a hook still differs is kept in contracts/fixtures/hooks/
 * engine-divergences.json: per hook, the scenarios it differs in and how
 * (not fired, fired here only, another kind or argument count). A hook
 * that differs where the snapshot does not say so fails (a regression);
 * one the snapshot names that now agrees is noted (some scenarios depend
 * on what ran before them), and --snapshot drops it. The snapshot only
 * shrinks: writing it refuses a new difference in a scenario it already
 * covered.
 * tests/tools/hook-status.php counts a hook proven when it agrees in at
 * least one scenario the reference fires it in, as a function is proven by
 * the probe that compares it.
 *
 *   php tests/hook-parity.test.php [--only=probe:x,http:y] [--show]
 *   php tests/hook-parity.test.php --snapshot     (after a full comparison)
 *   php tests/hook-parity.test.php --capture [--only=...]
 */

require __DIR__ . '/lib.php';
require __DIR__ . '/tools/hook-names.php';

/**
 * Hooks whose firing depends on the moment rather than the scenario (a cron
 * spawn and its loopback request), never compared. Anything else unsteady
 * is left out by the capture's two runs and the comparison's second run.
 */
const UNSTABLE = '/^(oembed_linktypes|cron_request|pre_get_ready_cron_jobs|wp_doing_cron|.*doing_cron.*|requests-.+|http_api_curl|http_api_debug|pre_http_request|http_request_args|http_request_timeout|http_request_redirection_count|http_request_reject_unsafe_urls|http_request_version|http_request_host_is_external|http_response|https_ssl_verify|https_local_ssl_verify|use_curl_transport|use_streams_transport|block_local_requests)$/';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$ROOT = dirname(__DIR__);
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');
$FIXTURE = "{$ROOT}/contracts/fixtures/hooks/battery.json";
$SNAPSHOT = "{$ROOT}/contracts/fixtures/hooks/engine-divergences.json";
$resolve = minn_hook_resolver((array) json_decode((string) file_get_contents("{$ROOT}/contracts/api/hooks.json"), true));
$capture = in_array('--capture', $argv, true);
$snapshot = in_array('--snapshot', $argv, true);
$show = in_array('--show', $argv, true);
$only = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_filter(explode(',', substr($arg, 7)));
    }
}

[$ph] = minn_test_fetch($REF . '/?rest_route=/', 3);
if (($ph['status'] ?? 0) !== 200) {
    echo "SKIP: reference WordPress not running at {$REF}\n";
    exit(0);
}

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

// ---- the battery ------------------------------------------------------------------------
$scenarios = [];
preg_match_all("/'([a-z0-9-]+)' => '([a-z0-9-]+-probe\\.php)'/", (string) file_get_contents("{$ROOT}/tests/api.test.php"), $listed, PREG_SET_ORDER);
foreach ($listed as [, $fixture, $probe]) {
    $scenarios["probe:{$fixture}"] = ['probe' => $probe];
}
$pages = [
    'front-home' => '/',
    'front-single' => '/hello-world/',
    'front-page' => '/sample-page/',
    'front-category' => '/category/uncategorized/',
    'front-author' => '/author/admin/',
    'front-search' => '/?s=hello',
    'front-404' => '/no-such-page-here/',
    'front-embed' => '/hello-world/embed/',
    'feed' => '/feed/',
    'feed-comments' => '/comments/feed/',
    'robots' => '/robots.txt',
    'sitemap-index' => '/wp-sitemap.xml',
    'sitemap-posts' => '/wp-sitemap-posts-post-1.xml',
    'rest-index' => '/?rest_route=/',
    'rest-posts' => '/?rest_route=/wp/v2/posts',
    'rest-post' => '/?rest_route=/wp/v2/posts/1',
    'rest-pages' => '/?rest_route=/wp/v2/pages',
    'rest-categories' => '/?rest_route=/wp/v2/categories',
    'rest-comments' => '/?rest_route=/wp/v2/comments',
    'rest-users' => '/?rest_route=/wp/v2/users',
    'rest-types' => '/?rest_route=/wp/v2/types',
    'rest-search' => '/?rest_route=/wp/v2/search&search=hello',
    'login' => '/wp-login.php',
    'login-lost' => '/wp-login.php?action=lostpassword',
    'ajax' => '/wp-admin/admin-ajax.php?action=minn_hook_parity',
    'canonical' => '/?p=1',
];
foreach ($pages as $name => $path) {
    $scenarios["http:{$name}"] = ['path' => $path];
}
foreach (['front-home' => '/', 'front-single' => '/hello-world/', 'rest-me' => '/?rest_route=/wp/v2/users/me', 'rest-settings' => '/?rest_route=/wp/v2/settings', 'rest-posts-edit' => '/?rest_route=/wp/v2/posts&context=edit', 'ajax' => '/wp-admin/admin-ajax.php?action=minn_hook_parity'] as $name => $path) {
    $scenarios["http:admin-{$name}"] = ['path' => $path, 'signedIn' => true];
}
// Posts that change nothing: a heartbeat, a refused sign-in, a comment with no text, a reset for nobody.
$scenarios['http:ajax-heartbeat'] = ['path' => '/wp-admin/admin-ajax.php', 'post' => ['action' => 'heartbeat', 'screen_id' => 'front', 'data' => []]];
$scenarios['http:admin-ajax-heartbeat'] = ['path' => '/wp-admin/admin-ajax.php', 'post' => ['action' => 'heartbeat', 'screen_id' => 'front', 'data' => []], 'signedIn' => true];
$scenarios['http:login-refused'] = ['path' => '/wp-login.php', 'post' => ['log' => 'admin', 'pwd' => 'not-the-password', 'testcookie' => '1']];
$scenarios['http:comment-empty'] = ['path' => '/wp-comments-post.php', 'post' => ['comment_post_ID' => '1', 'comment' => '', 'author' => 'zz', 'email' => 'zz-hooks@example.com']];
$scenarios['http:lostpassword-nobody'] = ['path' => '/wp-login.php?action=lostpassword', 'post' => ['user_login' => 'zz-nobody-' . 'hooks']];
if ($only !== []) {
    $scenarios = array_intersect_key($scenarios, array_flip($only));
}

// ---- recording ---------------------------------------------------------------------------
$recorder = "{$ROOT}/tests/fixtures/mu-plugins/minn-test-hooks.php";
$stacks = ['reference' => [$REF, "{$SITE}/wp-reference/wp-content"], 'engine' => [$ENGINE, "{$SITE}/public/wp-content"]];
$stack = $capture ? 'reference' : 'engine';
[$base, $content] = $stacks[$stack];
if (!is_link("{$content}/mu-plugins/minn-test-hooks.php")) {
    symlink($recorder, "{$content}/mu-plugins/minn-test-hooks.php");
}
@mkdir("{$content}/minn-hooks", 0755, true);
$scratch = sys_get_temp_dir() . '/minn-hook-parity-' . getmypid();
@mkdir($scratch, 0755, true);
register_shutdown_function(static function () use ($content, $scratch): void {
    @unlink("{$content}/mu-plugins/minn-test-hooks.php");
    array_map('unlink', glob("{$content}/minn-hooks/*") ?: []);
    @rmdir("{$content}/minn-hooks");
    array_map('unlink', glob("{$scratch}/*") ?: []);
    @rmdir($scratch);
});

$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg("{$ROOT}/tests/tools/mint-session.php") . ' 1 2>/dev/null'), true);
if (!is_array($mint) || empty($mint['cookie'])) {
    echo "SKIP: could not mint a reference session\n";
    exit(0);
}
$cookie = 'Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($mint['cookie']);

/**
 * One scenario's hooks on this run's stack, by inventory name: name =>
 * [kind, the most arguments any instance had]; the dynamic instances it
 * fired go to $instances. $listen names dynamic hooks to listen to.
 */
$record = static function (string $name, array $scenario, array $listen = [], ?array &$instances = null) use ($stack, $base, $content, $scratch, $WP, $ROOT, $cookie, $mint, $resolve): array {
    $run = substr(preg_replace('/[^a-z0-9-]+/', '-', $name), 0, 56) . '-' . bin2hex(random_bytes(3));
    $instances = [];
    if (isset($scenario['probe'])) {
        $file = "{$scratch}/{$run}.json";
        file_put_contents("{$scratch}/{$run}.listen", json_encode(array_values($listen)));
        $env = 'MINN_HOOK_PROBE=' . escapeshellarg($scenario['probe']) . ' MINN_HOOK_RECORD=' . escapeshellarg($file) . ' MINN_HOOK_LISTEN=' . escapeshellarg("{$scratch}/{$run}.listen");
        $command = $stack === 'reference'
            ? "{$env} {$WP} eval-file " . escapeshellarg("{$ROOT}/tests/tools/hook-record-probe.php")
            : "{$env} php " . escapeshellarg("{$ROOT}/tests/tools/run-api-probe.php") . ' hook-record-probe.php';
        shell_exec($command . ' >/dev/null 2>&1');
    } else {
        $file = "{$content}/minn-hooks/{$run}.json";
        file_put_contents("{$content}/minn-hooks/{$run}.open", json_encode(array_values($listen)));
        $headers = ['X-Minn-Hooks: ' . $run, ...(!empty($scenario['signedIn']) ? [$cookie, 'X-WP-Nonce: ' . $mint['nonce']] : [])];
        $ch = curl_init($base . $scenario['path']);
        // A redirect is followed: the hooks of every request the scenario makes are one record.
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => !isset($scenario['post']), CURLOPT_MAXREDIRS => 3]);
        if (isset($scenario['post'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($scenario['post']));
        }
        curl_exec($ch);
        @unlink("{$content}/minn-hooks/{$run}.open");
        // The record is written as the request ends, after the response.
        for ($i = 0; $i < 20 && !is_file($file); $i++) {
            usleep(100000);
        }
    }
    $hooks = json_decode((string) @file_get_contents($file), true);
    @unlink($file);
    $out = [];
    foreach (is_array($hooks) ? $hooks : [] as $hook => [$kind, $args]) {
        $known = $resolve((string) $hook);
        if ($known === null || preg_match(UNSTABLE, (string) $hook) === 1 || preg_match(UNSTABLE, $known) === 1) {
            continue;
        }
        if ($known !== $hook) {
            $instances[] = (string) $hook;
        }
        $row = $out[$known] ?? [$kind, $args];
        $out[$known] = [$row[0] === 'action' || $kind === 'action' ? 'action' : 'filter', max($row[1], $args)];
    }
    ksort($out);
    return $out;
};

// ---- capture -----------------------------------------------------------------------------
if ($capture) {
    $battery = is_file($FIXTURE) ? (array) (json_decode((string) file_get_contents($FIXTURE), true)['scenarios'] ?? []) : [];
    $listens = is_file($FIXTURE) ? (array) (json_decode((string) file_get_contents($FIXTURE), true)['instances'] ?? []) : [];
    foreach ($scenarios as $name => $scenario) {
        $first = $record($name, $scenario, [], $seen);
        $second = $record($name, $scenario, [], $again);
        $listens[$name] = array_values(array_intersect($seen, $again));
        // Only what both runs fired, as both fired it.
        $battery[$name] = array_filter($first, static fn (array $row, string $hook): bool => ($second[$hook] ?? null) === $row, ARRAY_FILTER_USE_BOTH);
        printf("  %-48s %4d hooks (%d unsteady)\n", $name, count($battery[$name]), count($first) + count($second) - 2 * count($battery[$name]));
    }
    ksort($battery);
    @mkdir(dirname($FIXTURE), 0755, true);
    $meta = json_decode((string) file_get_contents("{$ROOT}/contracts/api/meta.json"), true);
    ksort($listens);
    // One line per scenario: the file stays diffable by scenario at a fraction of the pretty-printed size.
    $lines = static fn (array $rows): string => implode(",\n", array_map(static fn (string $name, $value): string => json_encode($name) . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES), array_keys($rows), $rows));
    file_put_contents($FIXTURE, "{\n\"meta\": " . json_encode(['version' => $meta['version'] ?? '', 'captured' => gmdate('c')]) . ",\n\"scenarios\": {\n" . $lines($battery) . "\n},\n\"instances\": {\n" . $lines($listens) . "\n}\n}\n");
    echo 'captured ' . count($scenarios) . " scenarios into contracts/fixtures/hooks/battery.json\n";
    exit(0);
}

// ---- comparison --------------------------------------------------------------------------
$battery = is_file($FIXTURE) ? (array) (json_decode((string) file_get_contents($FIXTURE), true)['scenarios'] ?? []) : [];
$listens = is_file($FIXTURE) ? (array) (json_decode((string) file_get_contents($FIXTURE), true)['instances'] ?? []) : [];
$check('the reference battery is captured (php tests/hook-parity.test.php --capture)', $battery !== []);

/** @return array<string, string> hook => how it differs in this scenario */
$differences = static function (array $reference, array $engine): array {
    $out = [];
    foreach ($reference as $hook => [$kind, $args]) {
        $mine = $engine[$hook] ?? null;
        if ($mine === null) {
            $out[$hook] = 'not fired';
        } elseif ($mine[0] !== $kind) {
            $out[$hook] = "fired as {$mine[0]}, not {$kind}";
        } elseif ($mine[1] !== $args) {
            $out[$hook] = "{$mine[1]} arguments, not {$args}";
        }
    }
    foreach (array_diff_key($engine, $reference) as $hook => $row) {
        $out[$hook] = 'fired here only';
    }
    return $out;
};

$known = is_file($SNAPSHOT) ? (array) (json_decode((string) file_get_contents($SNAPSHOT), true) ?? []) : [];
$knownHooks = (array) ($known['hooks'] ?? []);
$knownScenarios = array_flip((array) ($known['scenarios'] ?? []));
$divergent = [];
$agreeing = [];
foreach ($scenarios as $name => $scenario) {
    if (!isset($battery[$name])) {
        $check("{$name}: captured on the reference", false);
        continue;
    }
    // What UNSTABLE names is left out on both sides, whenever the capture was taken.
    $reference = array_filter($battery[$name], static fn (string $hook): bool => preg_match(UNSTABLE, $hook) !== 1, ARRAY_FILTER_USE_KEY);
    $listen = (array) ($listens[$name] ?? []);
    $found = $differences($reference, $record($name, $scenario, $listen));
    if ($found !== []) {
        // Again, and only what differs both times.
        $found = array_intersect_assoc($found, $differences($reference, $record($name, $scenario, $listen)));
    }
    foreach ($found as $hook => $how) {
        $divergent[$hook][$name] = $how;
    }
    foreach (array_keys(array_diff_key($reference, $found)) as $hook) {
        $agreeing[$hook] = true;
    }
    $check("{$name}: " . count($reference) . ' hooks compared', true);
}
ksort($divergent);

if ($snapshot) {
    // The snapshot only shrinks: a difference in a scenario it already covered is refused.
    $grown = [];
    foreach ($divergent as $hook => $where) {
        foreach (array_keys($where) as $name) {
            if (isset($knownScenarios[$name]) && !isset($knownHooks[$hook][$name])) {
                $grown[] = "{$hook} in {$name}";
            }
        }
    }
    if ($grown !== [] && $only === []) {
        echo "refused: the snapshot would grow:\n  " . implode("\n  ", array_slice($grown, 0, 40)) . "\n";
        exit(1);
    }
    if ($only !== []) {
        echo "refused: write the snapshot after a full comparison\n";
        exit(1);
    }
    $hooks = array_map(static function (array $where): array {
        ksort($where);
        return $where;
    }, $divergent);
    file_put_contents($SNAPSHOT, json_encode(['scenarios' => array_keys($scenarios), 'hooks' => $hooks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'wrote the snapshot: ' . count($hooks) . " hooks differ somewhere\n";
    exit(0);
}

$check('the engine\'s differences are snapshotted (php tests/hook-parity.test.php --snapshot)', $known !== []);
foreach ($divergent as $hook => $where) {
    $new = array_filter($where, static fn (string $name): bool => !isset($knownHooks[$hook][$name]), ARRAY_FILTER_USE_KEY);
    if ($new !== []) {
        $detail = implode('; ', array_map(static fn (string $name, string $how): string => "{$name}: {$how}", array_keys($new), $new));
        $check("{$hook} fires as on the reference", false, $show ? $detail : substr($detail, 0, 400));
    }
}
// A recorded difference that agrees now is noted, not failed: some scenarios depend on what the run
// before them left (an option there or not), and a stale entry only understates what is proven.
// --snapshot drops them, and refuses to add any.
$agreesNow = 0;
foreach ($knownHooks as $hook => $names) {
    foreach (array_keys((array) $names) as $name) {
        if (isset($scenarios[$name], $battery[$name]) && !isset($divergent[$hook][$name])) {
            $agreesNow++;
            if ($show) {
                echo "  --   {$hook} in {$name}: agrees now\n";
            }
        }
    }
}
if ($agreesNow > 0) {
    echo "  --   {$agreesNow} recorded differences agree now: php tests/hook-parity.test.php --snapshot drops them\n";
}
printf("\n%d hooks agree everywhere, %d differ somewhere\n", count(array_diff_key($agreeing, $divergent)), count($divergent));
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
