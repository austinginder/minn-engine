<?php

declare(strict_types=1);

/**
 * WP-CLI on the engine: the same `wp` binary runs against the engine's
 * webroot (where wp-cli.yml routes the verbs to the engine) and against
 * the parked WordPress on the same database; stdout and the exit code must
 * match. Write verbs run as a cycle on a probe key, each side cleaning up
 * after itself. The one-time login link is engine-only (the reference
 * needs a helper plugin for it) and is exercised end to end over HTTP.
 */

require_once __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE_DIR = "$ROOT/public";
$REF_DIR = "$ROOT/wp-reference";
$ENGINE = 'https://minn-engine.localhost';

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

if (!is_file("$REF_DIR/wp-load.php")) {
    echo "cli suite: no wp-reference; skipping\n";
    exit(0);
}

/** @return array{0: string, 1: int} stdout+stderr and the exit code */
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
    // Deprecation notices from the reference's own plugins are not the contract.
    $err = preg_replace('/^(Notice|Deprecated|Warning): .*$\n?/m', '', (string) $err);
    return [rtrim((string) $out . $err), $code];
};

$same = static function (string $label, string $command, string $stdin = '') use ($run, $check, $ENGINE_DIR, $REF_DIR): void {
    [$engineOut, $engineCode] = $run($ENGINE_DIR, $command, $stdin);
    [$refOut, $refCode] = $run($REF_DIR, $command, $stdin);
    $check(
        $label,
        $engineOut === $refOut && $engineCode === $refCode,
        "wp {$command}\n      engine[{$engineCode}]: " . substr($engineOut, 0, 300) . "\n      ref[{$refCode}]:    " . substr($refOut, 0, 300),
    );
};

echo "cli suite: wp in $ENGINE_DIR (engine) vs $REF_DIR (reference)\n";

foreach ([
    'option get home',
    'option get siteurl',
    'option get blogname',
    'option get posts_per_page',
    'option get posts_per_page --format=json',
    'option get sticky_posts',
    'option get sticky_posts --format=json',
    'option get sticky_posts --format=yaml',
    'option get wp_user_roles --format=json',
    'option get nonexistent_option',
    'user list',
    'user list --format=csv',
    'user list --format=json',
    'user list --format=yaml',
    'user list --format=ids',
    'user list --format=count',
    'user list --field=user_email',
    'user list --fields=ID,user_login,roles',
    'user list --role=author',
    'user list --role=author --format=ids',
    'user list --role=nobody --format=count',
    'user get 1',
    'user get admin --format=json',
    'user get admin --format=csv',
    'user get scribe@minn-engine.localhost --field=roles',
    'user get admin --fields=user_login,roles --format=json',
    'user get nobody',
] as $command) {
    $same($command, $command);
}

// Write cycle on a probe key, both sides in turn.
$cycle = [
    ['option add minn_cli_probe hello', ''],
    ['option add minn_cli_probe again', ''],
    ['option get minn_cli_probe', ''],
    ['option update minn_cli_probe hello', ''],
    ['option update minn_cli_probe changed', ''],
    ['option get minn_cli_probe', ''],
    ['option update minn_cli_probe \'["x",1]\' --format=json', ''],
    ['option get minn_cli_probe', ''],
    ['option get minn_cli_probe --format=json', ''],
    ['option update minn_cli_probe --format=json', '{"k":"v"}'],
    ['option get minn_cli_probe --format=yaml', ''],
    ['option delete minn_cli_probe', ''],
    ['option delete minn_cli_probe', ''],
    ['option update minn_cli_probe2 fresh', ''],
    ['option get minn_cli_probe2', ''],
    ['option delete minn_cli_probe2', ''],
];
foreach ([$ENGINE_DIR => 'engine', $REF_DIR => 'reference'] as $dir => $side) {
    $transcript[$side] = '';
    foreach ($cycle as [$command, $stdin]) {
        [$out, $code] = $run($dir, $command, $stdin);
        $transcript[$side] .= "\$ wp {$command} [{$code}]\n{$out}\n";
        // Both sides share the database; the reference's db command reads the row either way.
        if (str_starts_with($command, 'option update minn_cli_probe2')) {
            $autoload[$side] = $run($REF_DIR, "db query \"SELECT autoload FROM wp_options WHERE option_name='minn_cli_probe2'\" --skip-column-names")[0];
        }
        if (str_starts_with($command, 'option add minn_cli_probe hello')) {
            $autoload[$side . '-add'] = $run($REF_DIR, "db query \"SELECT autoload FROM wp_options WHERE option_name='minn_cli_probe'\" --skip-column-names")[0];
        }
    }
}
$check('option write cycle transcript matches', $transcript['engine'] === $transcript['reference'], "engine:\n" . $transcript['engine'] . "\nreference:\n" . $transcript['reference']);
$check('option add autoloads "on"', ($autoload['engine-add'] ?? '') === ($autoload['reference-add'] ?? '?'), json_encode($autoload));
$check('option update on a new key autoloads "auto"', ($autoload['engine'] ?? '') === ($autoload['reference'] ?? '?'), json_encode($autoload));

/** Response headers without following redirects. @return array<string, string> lower-cased names; set-cookie joined */
$head = static function (string $url): array {
    exec('/usr/bin/curl -sk -o /dev/null -D - ' . escapeshellarg($url), $lines);
    $headers = ['status' => ''];
    foreach ($lines as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $headers['status'] = $m[1];
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            $headers[$k] = isset($headers[$k]) ? $headers[$k] . "\n" . trim($v) : trim($v);
        }
    }
    return $headers;
};

// The one-time login link (engine only).
[$url, $code] = $run($ENGINE_DIR, 'user login admin');
$check('user login prints a wp-login.php link', $code === 0 && preg_match('#^https://minn-engine\.localhost/wp-login\.php\?user_id=1&cove_login_token=[0-9a-f]{7}$#', $url) === 1, $url);
$headers = $head($url);
$check('the link signs in: 302 to /wp-admin/', $headers['status'] === '302' && str_contains($headers['location'] ?? '', '/wp-admin/'), json_encode($headers));
$check('the link sets the logged_in cookie', str_contains($headers['set-cookie'] ?? '', 'wordpress_logged_in_'), json_encode($headers['set-cookie'] ?? null));
$again = $head($url);
$check('the link is spent after one use', $again['status'] === '403', json_encode($again['status']));
$bad = $head("$ENGINE/wp-login.php?user_id=1&cove_login_token=0000000");
$check('a wrong token is refused', $bad['status'] === '403', json_encode($bad['status']));
[$out, $code] = $run($ENGINE_DIR, 'user login nobody');
$check('user login for an unknown user errors', $code === 1 && $out === 'Error: User not found: nobody', $out);
[$out, $code] = $run($ENGINE_DIR, 'minn version');
$check('wp minn version answers', $code === 0 && preg_match('/^\d+\.\d+\.\d+$/', $out) === 1, $out);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
