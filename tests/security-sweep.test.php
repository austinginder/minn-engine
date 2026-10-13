<?php

declare(strict_types=1);

/**
 * The security sweep of 2026-10-13 (docs/security-review-2026-10-13.md),
 * pinned where only a request or the engine itself shows it. Compared with
 * the reference: a published post of a type nobody may query is not shown,
 * and media_handle_upload keeps the caller's overrides. The engine's own:
 * a plugin is paused only after failing on plain requests (two requests
 * carrying a visitor's input cannot pause it for everyone), a long post
 * password costs no hashing, the CLI deletes no file outside the plugins
 * folder, and no SQL mode can switch backslash escaping off. Everything
 * the suite stages is removed at the end.
 */

require_once __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$PUBLIC = "{$SITE}/public";

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
/** @return array{0: int, 1: string} status and body */
$http = static function (string $url, string $method = 'GET', array $fields = [], string $cookie = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 120, CURLOPT_CUSTOMREQUEST => $method]);
    if ($fields !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    if ($cookie !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    $body = (string) curl_exec($ch);
    return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body];
};
$wp = static fn (string $args): string => trim((string) shell_exec('cd ' . escapeshellarg($PUBLIC) . ' && wp ' . $args . ' 2>&1'));

// Published posts of types nobody may query: the reference answers 404, asked by name and type, by GET or POST.
foreach (['/?name=navigation&post_type=wp_navigation', '/?name=wp-global-styles-twentytwentyfive&post_type=wp_global_styles'] as $path) {
    foreach (['GET', 'POST'] as $method) {
        [$engine] = $http($ENGINE . $path, $method);
        [$reference] = $http($REF . $path, $method);
        $check("{$method} {$path} is not shown", $engine === $reference && $engine === 404, "engine {$engine}, reference {$reference}");
    }
}

// media_handle_upload keeps the caller's overrides (types, prefilters), on both stacks.
$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-upload-overrides.php';
$folders = ["{$SITE}/wp-reference/wp-content/mu-plugins", "{$PUBLIC}/wp-content/mu-plugins"];
$note = tempnam(sys_get_temp_dir(), 'minn-sweep') ?: '';
file_put_contents($note, "a plain note\n");
foreach ($folders as $folder) {
    @symlink($fixture, "{$folder}/minn-test-upload-overrides.php");
}
$fields = ['file' => new CURLFile($note, 'text/plain', 'minn-sweep-note.txt')];
[, $engineBody] = $http("{$ENGINE}/?minn_test_upload_overrides=1", 'POST', $fields);
[, $referenceBody] = $http("{$REF}/?minn_test_upload_overrides=1", 'POST', $fields);
foreach ($folders as $folder) {
    @unlink("{$folder}/minn-test-upload-overrides.php");
}
@unlink($note);
$engineAnswer = json_decode($engineBody, true);
$check('media_handle_upload refuses a type its overrides leave out, through the upload filters', is_array($engineAnswer) && isset($engineAnswer['error']) && $engineAnswer === json_decode($referenceBody, true), "engine {$engineBody}\n      reference {$referenceBody}");

// The recovery pause: input-dependent failures never pause a plugin; failures on plain requests still do.
$plugins = "{$PUBLIC}/wp-content/plugins/minn-test-sweep-fails";
@mkdir($plugins, 0755, true);
copy(__DIR__ . '/fixtures/sweep/minn-test-sweep-fails.php', "{$plugins}/minn-test-sweep-fails.php");
register_shutdown_function(static function () use ($wp, $plugins): void {
    $wp('minn recovery resume minn-test-sweep-fails/minn-test-sweep-fails.php');
    $wp('option delete minn_test_sweep_fail_always');
    $wp('plugin deactivate minn-test-sweep-fails');
    @unlink("{$plugins}/minn-test-sweep-fails.php");
    @rmdir($plugins);
});
$wp('plugin activate minn-test-sweep-fails');
foreach ([1, 2] as $try) {
    $http("{$ENGINE}/?minn_test_sweep_fail={$try}");
}
$status = $wp('minn recovery');
$check('two requests that make a plugin fail on their input do not pause it', str_contains($status, 'nothing paused'), $status);
$wp('option update minn_test_sweep_fail_always 1');
foreach ([1, 2] as $try) {
    $http("{$ENGINE}/");
}
$status = $wp('minn recovery');
$check('a plugin failing on plain requests is still paused after the second', str_contains($status, 'minn-test-sweep-fails'), $status);

// A long post password is refused before any hashing (the reference stops at 4,096 characters).
$started = microtime(true);
$http("{$ENGINE}/wp-login.php?action=postpass", 'POST', ['post_password' => str_repeat('a', 400000)]);
$seconds = microtime(true) - $started;
$check('a 400 KB post password costs no hashing', $seconds < 5, sprintf('%.1f s', $seconds));

// The CLI deletes no file outside the plugins folder.
$canary = "{$PUBLIC}/wp-content/minn-sweep-canary.php";
file_put_contents($canary, "<?php\n");
$out = $wp('plugin delete ../minn-sweep-canary');
$kept = is_file($canary);
@unlink($canary);
$check('wp plugin delete with a slug that climbs out of the plugins folder deletes nothing', $kept && str_contains($out, 'could not be found'), $out);

// No SQL mode can switch backslash escaping off: the engine's literals depend on it.
$mode = $wp("eval 'global \$wpdb; \$wpdb->set_sql_mode([\"NO_BACKSLASH_ESCAPES\", \"ERROR_FOR_DIVISION_BY_ZERO\"]); echo \$wpdb->get_var(\"SELECT @@SESSION.sql_mode\");'");
$check('set_sql_mode keeps every mode but NO_BACKSLASH_ESCAPES', $mode === 'ERROR_FOR_DIVISION_BY_ZERO', $mode);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
