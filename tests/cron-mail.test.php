<?php

declare(strict_types=1);

/**
 * Milestone 25: scheduled posts go live the way the reference publishes
 * them, and the password-reset loop runs end to end through real mail
 * (Mailpit, Cove's catcher, receives what mail() sends). Everything here
 * is created and removed again.
 */

$ENGINE = 'https://minn-engine.localhost';
$REF = 'http://127.0.0.1:8123';
$ROOT = dirname(__DIR__);
$MAILPIT = 'http://localhost:8025/api/v1';
require_once __DIR__ . '/lib.php';

[$probe] = minn_test_fetch("$REF/?rest_route=/wp/v2/posts", 3);
if (($probe['status'] ?? 0) !== 200) {
    echo "SKIP: reference not running at $REF\n";
    exit(0);
}

$pass = 0;
$fail = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      " . substr($detail, 0, 400) : '') . "\n";
    }
}
function wp(string $command): string
{
    global $ROOT;
    return trim((string) shell_exec('cd ' . escapeshellarg("$ROOT/wp-reference") . " && wp $command 2>/dev/null"));
}
function engineWp(string $command): string
{
    global $ROOT;
    return trim((string) shell_exec('cd ' . escapeshellarg("$ROOT/public") . " && wp $command 2>&1"));
}
/** @return array{status: string, headers: array<string, string>, body: string} */
function page(string $url, string $post = '', array $headers = []): array
{
    $args = ['-sk', '-D', '-', $url];
    if ($post !== '') {
        array_push($args, '-d', $post);
    }
    foreach ($headers as $h) {
        array_push($args, '-H', $h);
    }
    exec('/usr/bin/curl ' . implode(' ', array_map('escapeshellarg', $args)), $lines);
    $status = '';
    $hdrs = [];
    $body = [];
    $inBody = false;
    foreach ($lines as $line) {
        if ($inBody) {
            $body[] = $line;
        } elseif (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = $m[1];
        } elseif (trim($line) === '') {
            $inBody = true;
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            $hdrs[$k] = isset($hdrs[$k]) ? $hdrs[$k] . "\n" . trim($v) : trim($v);
        }
    }
    return ['status' => $status, 'headers' => $hdrs, 'body' => implode("\n", $body)];
}
$created = [];
register_shutdown_function(static function () use (&$created): void {
    foreach ($created as $id) {
        wp("post delete $id --force");
    }
    wp("db query \"UPDATE wp_users SET user_activation_key='' WHERE ID IN (1,2)\"");
    wp('user update 2 --user_pass=minn-editor-pass-1');
    wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");
});

echo "cron + mail suite: $ENGINE\n";

// 1. Scheduled publishing: an engine cron run does what the reference's publish does.
$past = gmdate('Y-m-d H:i:s', time() - 30);
$future = gmdate('Y-m-d H:i:s', time() + 3600);
$due = (int) wp("post create --post_status=future --post_title='zz cron due' --post_date='$future' --post_date_gmt='$future' --porcelain");
$notDue = (int) wp("post create --post_status=future --post_title='zz cron later' --post_date='$future' --post_date_gmt='$future' --porcelain");
$created[] = $due;
$created[] = $notDue;
wp("post term add $due post_tag engine");
$countBefore = (int) wp("db query \"SELECT count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE t.slug='engine'\" --skip-column-names");
wp("db query \"UPDATE wp_posts SET post_date='$past', post_date_gmt='$past', post_modified='2026-01-01 00:00:00', post_modified_gmt='2026-01-01 00:00:00' WHERE ID=$due\"");
$out = engineWp('minn cron');
check(str_contains($out, 'published 1 scheduled post') && str_contains($out, 'Success'), 'wp minn cron publishes the due post and reports it', $out);
$row = wp("db query \"SELECT post_status, post_modified_gmt, post_date_gmt FROM wp_posts WHERE ID=$due\" --skip-column-names");
check(str_starts_with($row, "publish\t2026-01-01 00:00:00\t$past"), 'status flips to publish; modified and date untouched, as the reference publishes', $row);
check(wp("post get $notDue --field=post_status") === 'future', 'a post still in the future stays scheduled');
$countAfter = (int) wp("db query \"SELECT count FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE t.slug='engine'\" --skip-column-names");
check($countAfter === $countBefore + 1, 'term counts refresh on publish', "$countBefore -> $countAfter");
wp("db query \"UPDATE wp_posts SET post_status='future' WHERE ID=$due\"");
page("$ENGINE/");
check(wp("post get $due --field=post_status") === 'publish', 'a front request publishes a due post on its own');
wp("db query \"UPDATE wp_posts SET post_status='future' WHERE ID=$due\"");
$cron = page("$ENGINE/wp-cron.php?doing_wp_cron=" . time());
check($cron['status'] === '200' && wp("post get $due --field=post_status") === 'publish', 'wp-cron.php runs the jobs and answers 200 empty', $cron['status']);
wp("db query \"INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('_transient_timeout_zz_cron', '1', 'off'), ('_transient_zz_cron', 'x', 'off')\"");
engineWp('minn cron');
check(wp("db query \"SELECT COUNT(*) FROM wp_options WHERE option_name LIKE '%zz_cron'\" --skip-column-names") === '0', 'expired transients are swept');

// 2. Password reset, end to end through mail.
wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");
$before = json_decode((string) file_get_contents("$MAILPIT/messages?limit=1"), true)['total'] ?? 0;
$ask = page("$ENGINE/wp-login.php?action=lostpassword", 'user_login=editor@minn-engine.localhost');
check($ask['status'] === '302' && str_contains($ask['headers']['location'] ?? '', 'checkemail=confirm'), 'lostpassword redirects to checkemail=confirm, as the reference', $ask['status'] . ' ' . ($ask['headers']['location'] ?? ''));
usleep(500000);
$latest = json_decode((string) file_get_contents("$MAILPIT/messages?limit=1"), true);
$message = $latest['messages'][0] ?? [];
check(($latest['total'] ?? 0) === $before + 1 && str_contains($message['Subject'] ?? '', 'Password Reset') && ($message['To'][0]['Address'] ?? '') === 'editor@minn-engine.localhost', 'the reset email reaches the mailbox', json_encode([$message['Subject'] ?? null, $message['To'] ?? null]));
$text = (string) (json_decode((string) file_get_contents("$MAILPIT/message/" . ($message['ID'] ?? '')), true)['Text'] ?? '');
check(preg_match('#(https://minn-engine\.localhost/wp-login\.php\?action=rp&key=([A-Za-z0-9]{20})&login=editor)#', $text, $m) === 1, 'the email carries the reset link in the reference\'s shape', $text);
$link = $m[1] ?? '';
$key = $m[2] ?? '';
$stored = wp("db query \"SELECT user_activation_key FROM wp_users WHERE ID=2\" --skip-column-names");
check(preg_match('/^\d+:\$minn\$[0-9a-f]{64}$/', $stored) === 1, 'user_activation_key holds time:hash, never the key itself', $stored);
$open = page($link);
$cookieHash = md5($ENGINE);
check($open['status'] === '302' && str_contains($open['headers']['location'] ?? '', 'action=rp') && !str_contains($open['headers']['location'] ?? '', 'key=') && str_contains($open['headers']['set-cookie'] ?? '', "wp-resetpass-$cookieHash=editor%3A$key") && str_contains($open['headers']['set-cookie'] ?? '', 'path=/wp-login.php'), 'the link moves the key into the wp-login.php cookie and redirects without it', json_encode($open['headers']));
$cookie = "Cookie: wp-resetpass-$cookieHash=editor:$key";
$form = page("$ENGINE/wp-login.php?action=rp", '', [$cookie]);
check($form['status'] === '200' && str_contains($form['body'], 'name="pass1"') && str_contains($form['body'], 'name="rp_key" value="' . $key . '"'), 'the reset form shows with the key in a hidden field');
$bad = page("$ENGINE/wp-login.php?action=rp", '', ["Cookie: wp-resetpass-$cookieHash=editor:wrongkey0000000000000"]);
check($bad['status'] === '302' && str_contains($bad['headers']['location'] ?? '', 'error=invalidkey'), 'a wrong key bounces to lostpassword with error=invalidkey');
$mismatch = page("$ENGINE/wp-login.php?action=resetpass", "pass1=New-Pass-1&pass2=Other-Pass-2&rp_key=$key", [$cookie]);
check($mismatch['status'] === '200' && str_contains($mismatch['body'], 'do not match'), 'mismatched passwords are refused');
$old = page("$ENGINE/wp-login.php", 'log=editor&pwd=minn-editor-pass-1');
$oldCookie = preg_match('/wordpress_logged_in_[0-9a-f]+=([^;]+)/', $old['headers']['set-cookie'] ?? '', $c) ? 'wordpress_logged_in_' . $cookieHash . '=' . $c[1] : '';
$saved = page("$ENGINE/wp-login.php?action=resetpass", "pass1=New-Pass-1&pass2=New-Pass-1&rp_key=$key", [$cookie]);
check($saved['status'] === '200' && str_contains($saved['body'], 'Your password has been reset'), 'the new password is saved');
check(wp("db query \"SELECT user_activation_key FROM wp_users WHERE ID=2\" --skip-column-names") === '', 'the key is spent');
check(page("$ENGINE/wp-login.php", 'log=editor&pwd=New-Pass-1')['status'] === '302', 'the new password signs in on the engine');
check(wp('eval \'echo wp_check_password("New-Pass-1", get_user_by("id",2)->user_pass) ? "yes" : "no";\'') === 'yes', 'the reference verifies the new password too');
$me = page("$ENGINE/?rest_route=" . rawurlencode('/wp/v2/users/me'), '', ["Cookie: $oldCookie"]);
check($me['status'] === '401', 'sessions from before the reset are dead');
$reuse = page("$ENGINE/wp-login.php?action=rp", '', [$cookie]);
check($reuse['status'] === '302' && str_contains($reuse['headers']['location'] ?? '', 'invalidkey'), 'the spent key cannot be used again');
$unknown = page("$ENGINE/wp-login.php?action=lostpassword", 'user_login=nobody-at-all');
check($unknown['status'] === '200' && str_contains($unknown['body'], 'no account'), 'an unknown account is refused on the form');
// A key the reference issued is refused (its hash is not readable here) rather than mis-verified.
$refKey = wp('eval \'echo get_password_reset_key(get_user_by("login","admin"));\'');
$refTry = page("$ENGINE/wp-login.php?action=rp", '', ["Cookie: wp-resetpass-$cookieHash=admin:$refKey"]);
check($refTry['status'] === '302' && str_contains($refTry['headers']['location'] ?? '', 'invalidkey'), 'a reference-issued key is refused, not accepted by accident');

// 3. The mail transport switch and the test command.
check(str_contains(engineWp('minn mail cron-mail-suite@example.com'), 'Sent through the mail transport'), 'wp minn mail sends through the default transport');
engineWp("option update minn_mail '{\"transport\":\"log\"}' --format=json");
$log = "$ROOT/public/wp-content/minn-mail.log";
@unlink($log);
engineWp('minn mail log-transport@example.com');
$line = trim((string) @file_get_contents($log));
check(str_contains($line, '"log-transport@example.com"') && str_contains($line, 'Test email'), 'the log transport writes the message to wp-content/minn-mail.log', $line);
engineWp('option delete minn_mail');
@unlink($log);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
