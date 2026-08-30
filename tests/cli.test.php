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
    'theme list --fields=name,title,status,version --format=json',
    'theme list --format=json',
    'theme list --field=name',
    'theme list --format=count',
    'theme list --status=active --field=name',
    'theme list --status=inactive --format=count',
    'theme install zz-no-such-theme-xyz',
    'theme install twentytwentyfive',
    'theme install zz-no-such-theme-xyz twentytwentyfive',
    'theme activate nope-nope',
    'theme activate twentytwentyfive',
    'theme delete nope-nope',
    'theme delete twentytwentyfive',
    'theme is-installed twentytwentyfive',
    'theme is-installed nope-nope',
    'plugin install zz-no-such-plugin-xyz',
    'plugin is-installed minn-admin',
    'plugin is-installed nope-nope',
    'plugin delete nope-nope',
    'cache flush',
    'user create uniqueloginzzz admin@minn-engine.localhost',
    'user create badroleuser badrole@example.test --role=not-a-role',
    'user update 99999 --display_name=x',
    'search-replace same same --report-changed-only',
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
$check('the link signs in: 302 into the admin', $headers['status'] === '302' && str_contains($headers['location'] ?? '', '/minn-admin/'), json_encode($headers));
$check('the link sets the logged_in cookie', str_contains($headers['set-cookie'] ?? '', 'wordpress_logged_in_'), json_encode($headers['set-cookie'] ?? null));
$again = $head($url);
$check('the link is spent after one use', $again['status'] === '403', json_encode($again['status']));
$bad = $head("$ENGINE/wp-login.php?user_id=1&cove_login_token=0000000");
$check('a wrong token is refused', $bad['status'] === '403', json_encode($bad['status']));
[$out, $code] = $run($ENGINE_DIR, 'user login nobody');
$check('user login for an unknown user errors', $code === 1 && $out === 'Error: User not found: nobody', $out);
[$out, $code] = $run($ENGINE_DIR, 'minn version');
$check('wp minn version answers', $code === 0 && preg_match('/^\d+\.\d+\.\d+$/', $out) === 1, $out);

[$out, $code] = $run($ENGINE_DIR, 'plugin list --format=json --fields=name,title,status,version');
$pluginRows = json_decode($out, true);
$check(
    'plugin list json is an array',
    $code === 0 && is_array($pluginRows),
    "[$code] " . substr($out, 0, 300),
);
[$out, $code] = $run($ENGINE_DIR, 'plugin list --format=count');
$check('plugin list count is an integer', $code === 0 && preg_match('/^\d+$/', $out) === 1, $out);
// plugin activate/deactivate write the same active_plugins record the reference does. Both stacks share the
// database, so each runs the whole sequence from the resting (inactive) state and the transcripts are compared.
$sequence = ['plugin activate minn-test-types', 'plugin activate minn-test-types', 'plugin deactivate minn-test-types', 'plugin deactivate minn-test-types', 'plugin activate nope-nope'];
$transcript = static fn (string $dir): array => array_map(static fn (string $c) => $run($dir, $c), $sequence);
$engineT = $transcript($ENGINE_DIR);
$refT = $transcript($REF_DIR);
foreach ($sequence as $i => $command) {
    $check("$command (step " . ($i + 1) . ') matches the reference', $engineT[$i] === $refT[$i], "engine[{$engineT[$i][1]}]: {$engineT[$i][0]}\n      ref[{$refT[$i][1]}]:    {$refT[$i][0]}");
}
[$out, $code] = $run($ENGINE_DIR, 'plugin list --format=json --fields=name,status');
$fixtureRow = array_values(array_filter((array) json_decode($out, true), static fn ($r) => ($r['name'] ?? '') === 'minn-test-types'));
$check('the fixture plugin rests inactive', $code === 0 && ($fixtureRow[0]['status'] ?? '') === 'inactive', $out);
[$out, $code] = $run($ENGINE_DIR, 'minn probe');
$check('minn probe names the engine', $code === 0 && str_starts_with($out, "engine:minn\n"), "[$code] " . substr($out, 0, 300));
$probe = [];
foreach (explode("\n", $out) as $line) {
    if (str_contains($line, ':')) {
        [$k, $v] = explode(':', $line, 2);
        $probe[$k] = $v;
    }
}
$check('minn probe plugins is json', isset($probe['plugins']) && is_array(json_decode($probe['plugins'], true)), $probe['plugins'] ?? '');
$check('minn probe themes is json', isset($probe['themes']) && is_array(json_decode($probe['themes'], true)), $probe['themes'] ?? '');
$check('minn probe core is the version.php release', ($probe['core'] ?? '') === '7.1', $probe['core'] ?? '');

$login = 'minncli' . getmypid();
[$out, $code] = $run($ENGINE_DIR, "user create {$login} {$login}@example.test --role=author --first_name=Ada --last_name=Lovelace --user_pass=TestPass123! --porcelain");
$newId = trim($out);
$check('user create porcelain prints an id', $code === 0 && ctype_digit($newId), "[$code] {$out}");
[$got] = $run($ENGINE_DIR, "user get {$newId} --fields=user_login,user_email,display_name,roles --format=json");
$gotJson = json_decode($got, true);
$check(
    'user create stores login email display role',
    is_array($gotJson) && ($gotJson['user_login'] ?? '') === $login && ($gotJson['display_name'] ?? '') === 'Ada Lovelace' && ($gotJson['roles'] ?? '') === 'author',
    $got,
);
[$out, $code] = $run($ENGINE_DIR, "user update {$newId} --display_name='Ada L.'");
$check('user update succeeds', $code === 0 && str_contains($out, "Updated user {$newId}"), $out);
[$out, $code] = $run($ENGINE_DIR, "user delete {$newId} --yes --reassign=1");
$check('user delete succeeds', $code === 0 && str_contains($out, "Removed user {$newId} from"), $out);

[$out, $code] = $run($ENGINE_DIR, 'option update minn_sr_probe alpha-token-zzz');
$check('search-replace probe option written', $code === 0, $out);
[$engineReplace, $engineCode] = $run($ENGINE_DIR, 'search-replace alpha-token-zzz beta-token-zzz --report-changed-only');
$check(
    'search-replace reports one PHP replacement',
    $engineCode === 0 && str_contains($engineReplace, 'wp_options') && str_contains($engineReplace, 'option_value') && str_contains($engineReplace, 'Success: Made 1 replacement.'),
    $engineReplace,
);
[$got] = $run($ENGINE_DIR, 'option get minn_sr_probe');
$check('search-replace updated the option', trim($got) === 'beta-token-zzz', $got);
$run($ENGINE_DIR, 'option delete minn_sr_probe');

[$out, $code] = $run($ENGINE_DIR, 'option set minn_cli_set hello');
$check('option set is update', $code === 0 && str_contains($out, "Updated 'minn_cli_set' option."), $out);
$run($ENGINE_DIR, 'option delete minn_cli_set');

$stripCache = static function (string $out): string {
    return (string) preg_replace("/\nUsing cached file '[^']+'\\.\\.\\.\n/", "\n", $out);
};
$compareInstall = static function (string $label, string $command) use ($run, $check, $stripCache, $ENGINE_DIR, $REF_DIR): void {
    $cleanup = static function (string $dir) use ($run): void {
        $run($dir, 'theme activate twentytwentyfive');
        $run($dir, 'theme delete twentysixteen');
    };
    [$engineOut, $engineCode] = $run($ENGINE_DIR, $command);
    $cleanup($ENGINE_DIR);
    [$refOut, $refCode] = $run($REF_DIR, $command);
    $cleanup($REF_DIR);
    $check(
        $label,
        $stripCache($engineOut) === $stripCache($refOut) && $engineCode === $refCode,
        "wp {$command}\n      engine[{$engineCode}]: " . substr($stripCache($engineOut), 0, 400) . "\n      ref[{$refCode}]:    " . substr($stripCache($refOut), 0, 400),
    );
};
$compareInstall('theme install twentysixteen matches the reference', 'theme install twentysixteen');
$compareInstall('theme install twentysixteen twentytwentyfive matches the reference', 'theme install twentysixteen twentytwentyfive');
$compareInstall('theme install twentysixteen --activate matches the reference', 'theme install twentysixteen --activate');

$comparePlugin = static function (string $label, string $command) use ($run, $check, $stripCache, $ENGINE_DIR, $REF_DIR): void {
    $cleanup = static function (string $dir) use ($run): void {
        $run($dir, 'plugin deactivate hello-dolly');
        $run($dir, 'plugin delete hello-dolly');
    };
    [$engineOut, $engineCode] = $run($ENGINE_DIR, $command);
    $cleanup($ENGINE_DIR);
    [$refOut, $refCode] = $run($REF_DIR, $command);
    $cleanup($REF_DIR);
    $check(
        $label,
        $stripCache($engineOut) === $stripCache($refOut) && $engineCode === $refCode,
        "wp {$command}\n      engine[{$engineCode}]: " . substr($stripCache($engineOut), 0, 400) . "\n      ref[{$refCode}]:    " . substr($stripCache($refOut), 0, 400),
    );
};
$comparePlugin('plugin install hello-dolly matches the reference', 'plugin install hello-dolly');
$comparePlugin('plugin install hello-dolly --activate matches the reference', 'plugin install hello-dolly --activate');
$run($ENGINE_DIR, 'plugin install hello-dolly');
$run($REF_DIR, 'plugin install hello-dolly');
$same('plugin install hello-dolly when already on disk', 'plugin install hello-dolly');
$run($ENGINE_DIR, 'plugin delete hello-dolly');
$run($REF_DIR, 'plugin delete hello-dolly');

[$out, $code] = $run($ENGINE_DIR, 'rewrite flush');
$check('rewrite flush succeeds', $code === 0 && $out === 'Success: Rewrite rules flushed.', "[$code] {$out}");
[$out, $code] = $run($ENGINE_DIR, "rewrite structure '/%year%/%postname%/'");
$check(
    'rewrite structure sets and flushes',
    $code === 0 && str_contains($out, 'Success: Rewrite structure set.') && str_contains($out, 'Success: Rewrite rules flushed.'),
    "[$code] {$out}",
);
[$got] = $run($ENGINE_DIR, 'option get permalink_structure');
$check('rewrite structure stored permalink_structure', trim($got) === '/%year%/%postname%/', $got);
$run($ENGINE_DIR, "rewrite structure '/%postname%/'");

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
