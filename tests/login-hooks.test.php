<?php
/**
 * Login-hooks parity: a plugin on the sign-in page, as captcha, two-factor,
 * branding and redirect plugins are. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-login.php hooks the page and the
 * sign-in for a request whose X-Minn-Login header names a run this suite
 * opened; both stacks then answer the same requests. The page's markup is
 * each stack's own, so what is compared is what the plugin put where: its
 * title, head tag and style, body class, message, the field inside the
 * form, footer mark and header link; the login actions it heard; a
 * sign-in without its field refused in its words, one with it landing
 * where login_redirect says; a sign-out landing where logout_redirect
 * says; and the lost-password form's own field, a reset asked for
 * without it refused in the plugin's words and one with it sent on. The fixture, the run and the suite's user live only while it runs.
 *
 *   php tests/login-hooks.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');

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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-login.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'login-' . bin2hex(random_bytes(6));
$jars = [];
foreach ($stacks as $name => [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-login.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-login.php");
    }
    @mkdir("{$content}/minn-login", 0755, true);
    touch("{$content}/minn-login/{$run}.open");
    $jars[$name] = tempnam(sys_get_temp_dir(), 'minn-login-jar');
}
register_shutdown_function(static function () use ($stacks, $WP, $jars): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-login.php");
        array_map('unlink', glob("{$content}/minn-login/*") ?: []);
        @rmdir("{$content}/minn-login");
    }
    array_map('unlink', array_filter($jars, 'is_file'));
    shell_exec("{$WP} user delete \$({$WP} user list --field=ID --login__in=login-hooks-reader 2>/dev/null) --reassign=1 --yes >/dev/null 2>&1");
});
$password = 'Zz-' . bin2hex(random_bytes(8));
shell_exec("{$WP} user create login-hooks-reader login-hooks-reader@minn-engine.localhost --role=editor --user_pass=" . escapeshellarg($password) . ' >/dev/null 2>&1');

/** Status, Location, body for a request on one stack, with its cookie jar. */
$ask = static function (string $stack, string $path, ?array $form = null) use ($stacks, $jars, $run): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $jars[$stack], CURLOPT_COOKIEFILE => $jars[$stack], CURLOPT_HTTPHEADER => ["X-Minn-Login: {$run}"]]);
    if ($form !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form)]);
    }
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    preg_match('/^location:\s*(\S+)/mi', substr($raw, 0, $size), $m);
    return [$status, $m[1] ?? '', substr($raw, $size)];
};
/** The login actions the fixture heard on one stack since the last asking. */
$heard = static function (string $stack) use ($stacks, $run): array {
    $file = $stacks[$stack][1] . "/minn-login/{$run}.log";
    $lines = is_file($file) ? array_values(array_filter(explode("\n", (string) file_get_contents($file)))) : [];
    @unlink($file);
    return $lines;
};
/** What the plugin put on a sign-in page. */
$page = static function (string $html): array {
    preg_match('/<title>([^<]*)<\/title>/', $html, $title);
    preg_match('/<body[^>]*class="([^"]*)"/', $html, $body);
    preg_match('/<form[^>]*>(.*?)<\/form>/s', $html, $form);
    $head = (string) strstr($html, '</head>', true);
    $afterForm = (string) strstr($html, '</form>');
    return [
        'title' => html_entity_decode(trim($title[1] ?? ''), ENT_QUOTES),
        'head tag' => str_contains($head, 'name="zz-login-head"'),
        'style' => str_contains($head, 'zz-login.example/zz-login.css'),
        'body class' => in_array('zz-body', preg_split('/\s+/', $body[1] ?? '') ?: [], true),
        'message' => str_contains($html, 'class="zz-message"'),
        'field in the form' => str_contains($form[1] ?? '', 'name="zz_human"'),
        'footer mark after the form' => str_contains($afterForm, 'class="zz-footer"'),
        'header link' => preg_match('#<a[^>]*href="https://zz-login\.example/"[^>]*>\s*Zz Header\s*</a>#', $html) === 1,
    ];
};
$both = static function (callable $fn) use ($stacks): array {
    $out = [];
    foreach (array_keys($stacks) as $stack) {
        $out[$stack] = $fn($stack);
    }
    return $out;
};
$same = static function (string $label, array $answers) use ($check): void {
    $check($label, $answers['engine'] === $answers['reference'], 'engine=' . json_encode($answers['engine']) . ' reference=' . json_encode($answers['reference']));
};

echo "login-hooks suite: {$ENGINE} (engine) vs {$REF} (reference)\n";
$pages = $both(static fn (string $stack) => $page($ask($stack, '/wp-login.php?action=login')[2]));
foreach (array_keys($pages['reference']) as $feature) {
    $same("the page: {$feature}", ['engine' => $pages['engine'][$feature], 'reference' => $pages['reference'][$feature]]);
}
$same('the page: the actions heard', $both($heard));

$refused = $both(static function (string $stack) use ($ask): array {
    [$status, , $body] = $ask($stack, '/wp-login.php', ['log' => 'login-hooks-reader', 'pwd' => 'whatever', 'testcookie' => '1']);
    return [$status, str_contains($body, 'Zz: prove you are human.')];
});
$same('a sign-in without the field: refused in the plugin\'s words', $refused);
$same('a sign-in without the field: the actions heard', $both($heard));

$landed = $both(static function (string $stack) use ($ask, $password, $stacks): array {
    [$status, $location] = $ask($stack, '/wp-login.php', ['log' => 'login-hooks-reader', 'pwd' => $password, 'zz_human' => 'yes', 'testcookie' => '1']);
    return [$status, str_replace($stacks[$stack][0], '{site}', $location)];
});
$same('a sign-in with the field: where it lands', $landed);
$same('a sign-in with the field: the actions heard', $both($heard));

$left = $both(static function (string $stack) use ($ask, $stacks): array {
    [, , $body] = $ask($stack, '/wp-login.php?action=logout');
    preg_match('/href="([^"]*action=logout[^"]*_wpnonce=[^"]*)"/', html_entity_decode($body), $m);
    $link = (string) ($m[1] ?? '');
    $path = $link === '' ? '/wp-login.php?action=logout' : (string) preg_replace('#^https?://[^/]+#', '', $link);
    [$status, $location] = $ask($stack, $path);
    return [$status, str_replace($stacks[$stack][0], '{site}', $location)];
});
$same('a sign-out: where it lands', $left);
$same('a sign-out: the actions heard', $both($heard));

$lostPages = $both(static function (string $stack) use ($ask): bool {
    preg_match('/<form[^>]*>(.*?)<\/form>/s', $ask($stack, '/wp-login.php?action=lostpassword')[2], $form);
    return str_contains($form[1] ?? '', 'name="zz_human_lost"');
});
$same('the lost-password page: the field in the form', $lostPages);
$same('the lost-password page: the actions heard', $both($heard));
$lostRefused = $both(static fn (string $stack) => str_contains($ask($stack, '/wp-login.php?action=lostpassword', ['user_login' => 'login-hooks-reader'])[2], 'Zz: prove it to reset.'));
$same('a reset asked for without the field: refused in the plugin\'s words', $lostRefused);
$same('a reset asked for without the field: the actions heard', $both($heard));
$lostSent = $both(static function (string $stack) use ($ask): array {
    [$status, $location] = $ask($stack, '/wp-login.php?action=lostpassword', ['user_login' => 'login-hooks-reader', 'zz_human_lost' => 'yes']);
    return [$status, str_contains($location, 'checkemail=confirm')];
});
$same('a reset asked for with the field: on to check the email', $lostSent);
$same('a reset asked for with the field: the actions heard', $both($heard));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
