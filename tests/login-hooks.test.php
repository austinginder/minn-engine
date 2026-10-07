<?php
/**
 * Login-hooks parity: a plugin on the sign-in page, as captcha, two-factor,
 * branding and redirect plugins are. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-login.php hooks the page and the
 * sign-in for a request whose X-Minn-Login header names a run this suite
 * opened; both stacks then answer the same requests. The page's markup is
 * each stack's own, so what is compared is what the plugin put where: its
 * title, head tag and style, body class, message, the field inside the
 * form, footer mark and header link; the page's notices (signed out,
 * registration closed, check your email) with the plugin's own error and
 * message beside them, each in its area through the plugins' filters; the
 * login actions and notice filters it heard; a
 * sign-in without its field refused in its words, one with it landing
 * where login_redirect says; a sign-out landing where logout_redirect
 * says; and the lost-password form's own field, a reset asked for
 * without it refused in the plugin's words and one with it sent on; and
 * registration (switched on for the run): the form's own field, a sign-up
 * refused without it and one landing where registration_redirect says,
 * and the form closed when registration is off. The fixture, the run and the suite's user live only while it runs.
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

// The page's own word with a plugin's error and message beside it (wp_login_errors), each in its area through login_errors and login_messages.
$words = ['signed out' => ['loggedout=true', 'You are now logged out.'], 'registration closed' => ['registration=disabled', 'User registration is currently not allowed.'], 'check your email' => ['checkemail=confirm', 'Check your email for the confirmation link']];
foreach ($words as $what => [$query, $own]) {
    $shown = $both(static function (string $stack) use ($ask, $query, $own): array {
        $html = $ask($stack, "/wp-login.php?{$query}&zz_notice=1")[2];
        $at = static fn (string $needle): int => ($found = strpos($html, $needle)) === false ? -1 : $found;
        return [
            'own word' => $at($own) >= 0,
            'plugin error, then its area\'s mark' => $at("Zz: an error of the plugin's own.") >= 0 && $at("Zz: an error of the plugin's own.") < $at('class="zz-errors"'),
            'plugin message, then its area\'s mark' => $at("Zz: a message of the plugin's own.") >= 0 && $at("Zz: a message of the plugin's own.") < $at('class="zz-messages"'),
            'the sign-in form' => str_contains($html, 'name="log"'),
        ];
    });
    $same("the page, {$what}: the notices", $shown);
    $same("the page, {$what}: the actions heard", $both($heard));
}

$refused = $both(static function (string $stack) use ($ask): array {
    [$status, , $body] = $ask($stack, '/wp-login.php', ['log' => 'login-hooks-reader', 'pwd' => 'whatever', 'testcookie' => '1']);
    return [$status, str_contains($body, 'Zz: prove you are human.')];
});
$same('a sign-in without the field: refused in the plugin\'s words', $refused);
$same('a sign-in without the field: the actions heard', $both($heard));

$landed = $both(static function (string $stack) use ($ask, $password, $stacks): array {
    [$status, $location] = $ask($stack, '/wp-login.php', ['log' => 'login-hooks-reader', 'pwd' => $password, 'zz_human' => 'yes', 'testcookie' => '1']);
    return [$status, str_replace([$stacks[$stack][0], minn_test_url()], '{site}', $location)];
});
$same('a sign-in with the field: where it lands', $landed);
$same('a sign-in with the field: the actions heard', $both($heard));

$left = $both(static function (string $stack) use ($ask, $stacks): array {
    [, , $body] = $ask($stack, '/wp-login.php?action=logout');
    preg_match('/href="([^"]*action=logout[^"]*_wpnonce=[^"]*)"/', html_entity_decode($body), $m);
    $link = (string) ($m[1] ?? '');
    $path = $link === '' ? '/wp-login.php?action=logout' : (string) preg_replace('#^https?://[^/]+#', '', $link);
    [$status, $location] = $ask($stack, $path);
    return [$status, str_replace([$stacks[$stack][0], minn_test_url()], '{site}', $location)];
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

// Registration, switched on for the run (each stack registers its own account: they share a database).
$registering = trim((string) shell_exec("{$WP} option get users_can_register 2>/dev/null"));
register_shutdown_function(static function () use ($WP, $registering): void {
    shell_exec("{$WP} option update users_can_register " . escapeshellarg($registering === '' ? '0' : $registering) . ' >/dev/null 2>&1');
    foreach (['reference', 'engine'] as $stack) {
        $id = trim((string) shell_exec("{$WP} user get zz-reg-{$stack} --field=ID 2>/dev/null"));
        $email = trim((string) shell_exec("{$WP} user get zz-reg-{$stack} --field=user_email 2>/dev/null"));
        if ($id !== '' && $email === "zz-reg-{$stack}@minn-engine.localhost") {
            shell_exec("{$WP} user delete {$id} --reassign=1 --yes >/dev/null 2>&1");
        }
    }
});
shell_exec("{$WP} option update users_can_register 1 >/dev/null 2>&1");
$registerPages = $both(static function (string $stack) use ($ask, $stacks): array {
    preg_match('/<form[^>]*>(.*?)<\/form>/s', $ask($stack, '/wp-login.php?action=register')[2], $form);
    preg_match('/name="redirect_to" value="([^"]*)"/', $form[1] ?? '', $landing);
    return [str_contains($form[1] ?? '', 'name="zz_human_reg"'), str_replace([$stacks[$stack][0], minn_test_url()], '{site}', html_entity_decode($landing[1] ?? ''))];
});
$same('the registration page: the field in the form, and where it lands', $registerPages);
$same('the registration page: the actions heard', $both($heard));
$registerRefused = $both(static fn (string $stack) => str_contains($ask($stack, '/wp-login.php?action=register', ['user_login' => "zz-reg-{$stack}", 'user_email' => "zz-reg-{$stack}@minn-engine.localhost"])[2], 'Zz: prove it to register.'));
$same('a registration without the field: refused in the plugin\'s words', $registerRefused);
$same('a registration without the field: the actions heard', $both($heard));
$registered = $both(static function (string $stack) use ($ask, $stacks, $WP): array {
    [$status, $location] = $ask($stack, '/wp-login.php?action=register', ['user_login' => "zz-reg-{$stack}", 'user_email' => "zz-reg-{$stack}@minn-engine.localhost", 'zz_human_reg' => 'yes']);
    // A relative and an absolute Location land on the same page; the path and query are compared.
    $landed = (string) parse_url($location, PHP_URL_PATH) . '?' . (string) parse_url($location, PHP_URL_QUERY);
    return [$status, '/' . ltrim($landed, '/'), trim((string) shell_exec("{$WP} user get zz-reg-{$stack} --field=roles 2>/dev/null"))];
});
$same('a registration with the field (and no landing posted): where it lands, and the account', $registered);
$same('a registration with the field: the actions heard', $both($heard));
shell_exec("{$WP} option update users_can_register 0 >/dev/null 2>&1");
$closed = $both(static function (string $stack) use ($ask): array {
    [$status, $location] = $ask($stack, '/wp-login.php?action=register');
    return [$status, str_contains($location, 'registration=disabled')];
});
$same('registration switched off: sent back saying so', $closed);
$heard('reference');
$heard('engine');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
