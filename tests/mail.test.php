<?php
/**
 * Mail as plugins send it: PHPMailer (composition, encodings, attachments,
 * DKIM, its helpers and errors), its SMTP client on the wire, and wp_mail()
 * with its filters and actions. Runs tests/tools/phpmailer-probe.php in the
 * parked WordPress and on the engine's runtime and diffs the transcripts
 * row by row; then starts tests/fixtures/mail/fake-smtp.php once per stack
 * and diffs tests/tools/phpmailer-smtp-probe.php the same way, the
 * server's recorded sessions included. Last, the engine's own mail
 * settings (the minn_mail option) route wp_mail() to their SMTP server.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/lib.php';
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

$site = minn_test_site_root();
$dump = getenv('MINN_MAIL_DUMP');
$env = static fn (array $vars): string => implode(' ', array_map(static fn ($k, $v) => $k . '=' . escapeshellarg((string) $v), array_keys($vars), $vars));
$run = static function (string $probe, array $refEnv, array $engEnv, string $name) use ($root, $site, $dump, $env): array {
    $out = [
        'reference' => trim((string) shell_exec($env($refEnv) . ' php ' . escapeshellarg("{$root}/tests/tools/run-reference-probe.php") . ' ' . escapeshellarg("{$site}/wp-reference") . ' ' . escapeshellarg("{$root}/tests/tools/{$probe}") . ' 2>/dev/null')),
        'engine' => trim((string) shell_exec($env($engEnv + ['MINN_PROBE_HOST' => 'minn.localhost']) . ' php ' . escapeshellarg("{$root}/tests/tools/run-api-probe.php") . ' ' . escapeshellarg($probe) . ' 2>/dev/null')),
    ];
    if ($dump !== false && is_dir($dump)) {
        file_put_contents("{$dump}/{$name}-ref.json", $out['reference']);
        file_put_contents("{$dump}/{$name}-eng.json", $out['engine']);
    }
    return [json_decode($out['reference'], true), json_decode($out['engine'], true), $out];
};
$compare = static function (string $title, $expected, $actual, array $raw) use ($check): void {
    echo "\n{$title}\n";
    if (!is_array($expected) || !is_array($actual)) {
        $check('the probe produced JSON on ' . (!is_array($expected) ? 'the reference' : 'the engine'), false, substr(!is_array($expected) ? $raw['reference'] : $raw['engine'], 0, 2000));
        return;
    }
    $byLabel = [];
    foreach ($actual as [$label, $value]) {
        $byLabel[$label] = $value;
    }
    foreach ($expected as [$label, $value]) {
        $have = array_key_exists($label, $byLabel);
        $check($label, $have && json_encode($byLabel[$label]) === json_encode($value), $have ? substr(json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) . ' vs ' . substr(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : 'missing');
    }
};

// A fake SMTP server per stack, each recording its sessions into its own folder.
$servers = [];
$serve = static function () use ($root, &$servers): array {
    $dir = sys_get_temp_dir() . '/minn-mail-' . bin2hex(random_bytes(4));
    mkdir($dir);
    for ($port = 2600 + random_int(0, 400), $tries = 0; $tries < 20; $port++, $tries++) {
        $proc = proc_open(['php', "{$root}/tests/fixtures/mail/fake-smtp.php", (string) $port, $dir, '8'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        usleep(300000);
        if (is_resource($proc) && proc_get_status($proc)['running']) {
            $servers[] = [$proc, $dir];
            return [$port, $dir];
        }
    }
    return [0, $dir];
};
register_shutdown_function(static function () use (&$servers): void {
    foreach ($servers as [$proc, $dir]) {
        if (is_resource($proc)) {
            proc_terminate($proc);
        }
        array_map('unlink', glob("{$dir}/*") ?: []);
        @rmdir($dir);
    }
});

[$expected, $actual, $raw] = $run('phpmailer-probe.php', [], [], 'phpmailer');
$compare('PHPMailer, composed without sending', $expected, $actual, $raw);

[$refPort, $refDir] = $serve();
[$engPort, $engDir] = $serve();
if ($refPort === 0 || $engPort === 0) {
    $check('the fake SMTP servers start', false);
} else {
    [$expected, $actual, $raw] = $run('phpmailer-smtp-probe.php', ['MINN_SMTP_PORT' => $refPort, 'MINN_SMTP_DIR' => $refDir], ['MINN_SMTP_PORT' => $engPort, 'MINN_SMTP_DIR' => $engDir], 'phpmailer-smtp');
    $compare('SMTP, PHPMailer over SMTP, and wp_mail()', $expected, $actual, $raw);
}

// The engine's own mail settings: with the minn_mail option set to SMTP, wp_mail() goes to that server.
echo "\nThe engine's mail settings\n";
[$port, $dir] = $serve();
$wp = static fn (string $command): string => trim((string) shell_exec('cd ' . escapeshellarg("{$site}/public") . " && wp {$command} 2>&1"));
$wp("option update minn_mail '" . json_encode(['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'from_email' => 'configured@example.test', 'from_name' => 'Configured']) . "' --format=json");
$sent = $wp('eval \'echo wp_mail("settings@example.test", "Through the settings", "Body") ? "sent" : "failed";\'');
$wp('option delete minn_mail');
usleep(300000);
$session = json_decode((string) @file_get_contents("{$dir}/session-1.json"), true) ?: [];
$lines = array_map(static fn ($row) => $row[0] . ': ' . $row[1], $session);
$check('wp_mail() reports the send', $sent === 'sent', $sent);
$check('the configured server received it from the configured sender', in_array('C: MAIL FROM:<configured@example.test>', $lines, true) && in_array('C: RCPT TO:<settings@example.test>', $lines, true), implode(' | ', array_slice($lines, 0, 12)));
$check('the message carries the configured name and the subject', (bool) array_filter($session, static fn ($row) => $row[0] === 'DATA' && str_contains($row[1], 'From: Configured <configured@example.test>') && str_contains($row[1], 'Subject: Through the settings')));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
