<?php

declare(strict_types=1);

/**
 * `wp cron` on the engine, and the cron option's due hooks firing through
 * every trigger. The stable half compares the engine's `wp` against the
 * parked reference on the same database (schedule list, the recurrence
 * column read from the shared cron option, and the error wording). The
 * firing half is engine-only: a fixture plugin schedules an event the way
 * plugins do, and it fires on `wp cron event run`, on `wp minn cron`, and
 * on a real GET of wp-cron.php.
 *
 * The two time columns drift between the two runs and are never compared.
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$ENGINE_DIR = minn_test_site_root() . '/public';
$REF_DIR = minn_test_site_root() . '/wp-reference';
$FIXTURE = 'minn-test-cron/minn-test-cron.php';

if (!is_file("$REF_DIR/wp-load.php")) {
    echo "cron suite: no wp-reference; skipping\n";
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
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      {$detail}" : '') . "\n";
    }
};

/** @return array{0: string, 1: int} stdout+stderr, exit code */
$run = static function (string $dir, string $command, string $stdin = ''): array {
    $extra = str_contains($dir, 'wp-reference') ? ' --skip-plugins --skip-themes' : '';
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open("wp {$command}{$extra} --no-color", $descriptors, $pipes, $dir);
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $err = preg_replace('/^(Notice|Deprecated|Warning): (?!The DISABLE_WP_CRON|Numeric keys).*$\n?/m', '', (string) $err);
    return [rtrim((string) $out . $err), $code];
};
$engine = static fn (string $command, string $stdin = ''): array => $run($ENGINE_DIR, $command, $stdin);
$reference = static fn (string $command, string $stdin = ''): array => $run($REF_DIR, $command, $stdin);
$same = static function (string $label, string $command) use ($run, $check, $ENGINE_DIR, $REF_DIR): void {
    [$engineOut, $engineCode] = $run($ENGINE_DIR, $command);
    [$refOut, $refCode] = $run($REF_DIR, $command);
    $check($label, $engineOut === $refOut && $engineCode === $refCode, "wp {$command}\n      engine[{$engineCode}]: " . substr($engineOut, 0, 200) . "\n      ref[{$refCode}]:    " . substr($refOut, 0, 200));
};

echo "cron suite: wp in $ENGINE_DIR (engine) vs $REF_DIR (reference)\n";

// The fixture must not be active for the parity half; capture and restore active_plugins.
[$before] = $engine('option get active_plugins --format=json');
$restore = static function () use ($engine, $before, $FIXTURE): void {
    $engine("cron event delete minn_test_cron_tick");
    $engine("cron event delete minn_test_cron_once");
    $engine('option delete minn_test_cron_log');
    $engine('option delete minn_cron_lock');
    $engine('option update active_plugins ' . escapeshellarg($before) . ' --format=json');
};
register_shutdown_function($restore);
$active = json_decode($before, true) ?: [];
$engine('option update active_plugins ' . escapeshellarg(json_encode(array_values(array_diff($active, [$FIXTURE])))) . ' --format=json');

// ---- Stable parity: schedules, the shared cron option, and wording.
$same('schedule list matches the reference', 'cron schedule list');
$same('schedule list --format=json matches', 'cron schedule list --format=json');
$same('schedule list --fields=name,interval matches', 'cron schedule list --fields=name,interval');
$same('event list --fields=hook,recurrence matches on the shared option', 'cron event list --fields=hook,recurrence');
$same('event list --format=ids matches', 'cron event list --format=ids');
$same('event list --field=hook matches', 'cron event list --field=hook');
$same('cron test reports spawning works', 'cron test');
$same('event run of an unknown hook is refused', 'cron event run nonexistent_hook');
$same('event delete of an unknown hook is refused', 'cron event delete nonexistent_hook');
$same('event unschedule with no events is refused', 'cron event unschedule nonexistent_hook');
$same('event run with no selector is refused', 'cron event run');
$same('event delete with no selector is refused', 'cron event delete');
$same('event schedule rejects a bad datetime', 'cron event schedule some_hook bogus');
$same('event schedule rejects an unknown recurrence', 'cron event schedule some_hook now bogus_recurrence');

// ---- Firing half: the fixture on, engine only.
$engine("plugin activate minn-test-cron");
$engine('option delete minn_test_cron_log');

[$scheduleList] = $engine('cron schedule list --format=json');
$check("the plugin's custom schedule is listed", str_contains($scheduleList, '"name":"minn_five_minutes"'), $scheduleList);

$engine("cron event schedule minn_test_cron_once now");
[$listed] = $engine('cron event list --hook=minn_test_cron_once --fields=hook,recurrence');
$check('a scheduled single event lists as non-repeating', str_contains($listed, "minn_test_cron_once\tNon-repeating"), $listed);

[$runOut, $runCode] = $engine('cron event run minn_test_cron_once');
$check('wp cron event run reports the run', $runCode === 0 && str_contains($runOut, "Executed the cron event 'minn_test_cron_once' in") && str_contains($runOut, 'Success: Executed a total of 1 cron event.'), $runOut);
[$log] = $engine('option get minn_test_cron_log --format=json');
$check('wp cron event run fired the plugin callback', str_contains($log, '"event":"once"'), $log);
[$gone] = $engine('cron event list --hook=minn_test_cron_once --format=count');
$check('the single event is gone after it ran', trim($gone) === '0', $gone);

// wp minn cron fires due events too (the system-cron verb).
$engine('option delete minn_test_cron_log');
$engine('option delete minn_cron_lock');
$engine("cron event schedule minn_test_cron_once now");
[$minnCron] = $engine('minn cron');
$check('wp minn cron reports firing scheduled events', str_contains($minnCron, 'fired ') && str_contains($minnCron, 'scheduled event'), $minnCron);
[$log2] = $engine('option get minn_test_cron_log --format=json');
$check('wp minn cron fired the plugin callback', str_contains($log2, '"event":"once"'), $log2);

// The real host path: a GET of wp-cron.php runs what is due.
$engine('option delete minn_test_cron_log');
$engine('option delete minn_cron_lock');
$engine("cron event schedule minn_test_cron_once now");
[$probeHeaders, $probeBody] = minn_test_fetch("$ENGINE/wp-cron.php", 5);
$check('wp-cron.php answers 200 with an empty body', ($probeHeaders['status'] ?? 0) === 200 && trim($probeBody) === '', 'status ' . ($probeHeaders['status'] ?? '?'));
usleep(300000);
[$log3] = $engine('option get minn_test_cron_log --format=json');
$check('wp-cron.php fired the due event', str_contains($log3, '"event":"once"'), $log3);
[$gone2] = $engine('cron event list --hook=minn_test_cron_once --format=count');
$check('wp-cron.php removed the single event after firing', trim($gone2) === '0', $gone2);

$restore();

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
