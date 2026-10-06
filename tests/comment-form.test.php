<?php
/**
 * The comment form (wp-comments-post.php), engine vs oracle, signed out: the
 * same form posted to both stacks, compared on the status, where it sends
 * the reader (comment ids and the moderation hash masked), the cookies it
 * sets (name and attributes), the refusal's words, and the comment it
 * stored (approval, author fields, content). The stacks share a database,
 * so each case's comments are swept, by their exact author address, before
 * the other stack posts: one stack's comment would be the other's flood.
 *
 *   php tests/comment-form.test.php [--show]
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');
$show = in_array('--show', array_slice($argv, 1), true);

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

const DOMAIN = '@zz-form.example';

/** Every comment this suite made, matched exactly by its address's domain (a wp-cli filter it ignores would match every comment). */
$sweep = static function () use ($WP): void {
    $comments = json_decode((string) shell_exec("{$WP} comment list --status=any --fields=comment_ID,comment_author_email --format=json 2>/dev/null"), true);
    foreach (is_array($comments) ? $comments : [] as $comment) {
        if (str_ends_with((string) ($comment['comment_author_email'] ?? ''), DOMAIN)) {
            shell_exec("{$WP} comment delete " . (int) $comment['comment_ID'] . ' --force >/dev/null 2>&1');
        }
    }
};
// The guard plugin judges comments as spam plugins do; it lives in both plugin folders only while the suite runs.
$guard = dirname(__DIR__) . '/tests/fixtures/runtime/minn-test-comment-guard';
$links = [$SITE . '/public/wp-content/plugins/minn-test-comment-guard', $SITE . '/wp-reference/wp-content/plugins/minn-test-comment-guard'];
foreach ($links as $link) {
    if (!is_link($link)) {
        symlink($guard, $link);
    }
}
// What an earlier run left (a trashed comment is still a recent comment from this address) goes first.
$sweep();
$option = static fn (string $name): string => trim((string) shell_exec("{$WP} option get {$name} 2>/dev/null"));
$keep = ['comment_registration' => $option('comment_registration'), 'disallowed_keys' => $option('disallowed_keys')];
$closed = (int) trim((string) shell_exec("{$WP} post create --post_title='zz form closed' --post_status=publish --comment_status=closed --porcelain 2>/dev/null"));
$draft = (int) trim((string) shell_exec("{$WP} post create --post_title='zz form draft' --post_status=draft --comment_status=open --porcelain 2>/dev/null"));
$held = (int) trim((string) shell_exec("{$WP} comment create --comment_post_ID=1 --comment_author='Held' --comment_author_email=held" . DOMAIN . " --comment_content='zz held parent' --comment_approved=0 --porcelain 2>/dev/null"));
register_shutdown_function(static function () use ($WP, $keep, $closed, $draft, $sweep, $links): void {
    shell_exec("{$WP} plugin deactivate minn-test-comment-guard >/dev/null 2>&1");
    foreach ($links as $link) {
        if (is_link($link)) {
            unlink($link);
        }
    }
    foreach ($keep as $name => $value) {
        shell_exec("{$WP} option update {$name} " . escapeshellarg($value) . ' >/dev/null 2>&1');
    }
    foreach ([$closed, $draft] as $id) {
        if ($id > 0) {
            shell_exec("{$WP} post delete {$id} --force >/dev/null 2>&1");
        }
    }
    $sweep();
});

/** One form post, signed out: status, location, cookies and the page's words, with what differs by stack masked. */
$post = static function (string $base, array $form): array {
    $ch = curl_init($base . '/wp-comments-post.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form), CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['User-Agent: minn-comment-form-suite']]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $location = '';
    $cookies = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (preg_match('/^location:\s*(.*)$/i', $line, $m)) {
            $location = (string) preg_replace(['#^https?://[^/]+#', '/comment-\d+/', '/unapproved=\d+/', '/moderation-hash=[0-9a-f]+/'], ['{home}', 'comment-{id}', 'unapproved={id}', 'moderation-hash={hash}'], trim($m[1]));
        }
        if (preg_match('/^set-cookie:\s*([a-z_]+?)_[0-9a-f]{32}=([^;]*)(.*)$/i', $line, $m)) {
            $attributes = array_values(array_filter(array_map(static fn ($a) => strtolower(trim(explode('=', $a, 2)[0])), explode(';', $m[3])), static fn ($a) => $a !== '' && $a !== 'secure' && $a !== 'samesite'));
            $cookies[] = $m[1] . '=' . ($m[2] === '+' || $m[2] === '%20' ? '(blank)' : ($m[2] === '' ? '(empty)' : 'set')) . ' ' . implode(',', $attributes);
        }
    }
    sort($cookies);
    $words = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(style|script|title)[^>]*>.*?</\1>#is', '', substr($raw, $size)))));
    $words = trim(str_replace(['« Back', '&laquo; Back'], '', $words));
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $location, 'cookies' => $cookies, 'words' => $words];
};

/** The comment a case stored, by its exact address. */
$stored = static function (string $email) use ($WP): ?array {
    $comments = json_decode((string) shell_exec("{$WP} comment list --status=any --fields=comment_ID,comment_author,comment_author_email,comment_author_url,comment_content,comment_approved,comment_parent,comment_type,comment_agent --format=json 2>/dev/null"), true);
    foreach (is_array($comments) ? $comments : [] as $comment) {
        if (($comment['comment_author_email'] ?? '') === $email) {
            unset($comment['comment_ID']);
            $comment['comment_parent'] = (int) $comment['comment_parent'] > 0 ? 'set' : '0';
            return $comment;
        }
    }
    return null;
};

$cases = [
    // label => [form, options to set first]
    'a signed-out comment, remembered' => [['comment_post_ID' => 1, 'author' => 'Form Reader', 'email' => 'one' . DOMAIN, 'url' => 'https://elsewhere.example', 'comment' => 'A comment from the form, with <b>bold</b> and <script>alert(1)</script> and a <a href="https://elsewhere.example/">link</a>.', 'wp-comment-cookies-consent' => 'yes'], []],
    'a signed-out comment, not remembered' => [['comment_post_ID' => 1, 'author' => 'Form Reader', 'email' => 'two' . DOMAIN, 'url' => '', 'comment' => 'Another comment, the cookies declined.'], []],
    'a post that does not exist' => [['comment_post_ID' => 999999, 'author' => 'A', 'email' => 'three' . DOMAIN, 'comment' => 'hello'], []],
    'a post closed to comments' => [['comment_post_ID' => $closed, 'author' => 'A', 'email' => 'four' . DOMAIN, 'comment' => 'hello'], []],
    'a draft' => [['comment_post_ID' => $draft, 'author' => 'A', 'email' => 'five' . DOMAIN, 'comment' => 'hello'], []],
    'no name or email' => [['comment_post_ID' => 1, 'author' => '', 'email' => '', 'comment' => 'hello'], []],
    'a bad email' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'not-an-address', 'comment' => 'hello'], []],
    'no comment' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'six' . DOMAIN, 'comment' => '   '], []],
    'a name too long' => [['comment_post_ID' => 1, 'author' => str_repeat('n', 250), 'email' => 'seven' . DOMAIN, 'comment' => 'hello'], []],
    'a reply to a held comment' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'eight' . DOMAIN, 'comment' => 'a reply', 'comment_parent' => $held], []],
    'sign-in required' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'nine' . DOMAIN, 'comment' => 'hello'], ['comment_registration' => '1']],
    'a disallowed word' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'ten' . DOMAIN, 'comment' => 'this has zzforbidden in it'], ['comment_registration' => '0', 'disallowed_keys' => 'zzforbidden']],
    // A spam plugin's say (the guard fixture).
    'a plugin marks it spam' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'eleven' . DOMAIN, 'comment' => 'buy now zz-guard-spam', 'wp-comment-cookies-consent' => 'yes'], ['disallowed_keys' => $keep['disallowed_keys']]],
    'a plugin refuses it with wp_die' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'twelve' . DOMAIN, 'comment' => 'zz-guard-die please'], []],
    'a plugin refuses it with an error' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'thirteen' . DOMAIN, 'comment' => 'zz-guard-error please'], []],
    'a plugin rewrites it' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'fourteen' . DOMAIN, 'comment' => 'before zz-guard-rewrite after', 'wp-comment-cookies-consent' => 'yes'], []],
    'a plugin names a duplicate' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'fifteen' . DOMAIN, 'comment' => 'zz-guard-dupe once'], []],
    'a plugin sends the reader elsewhere' => [['comment_post_ID' => 1, 'author' => 'A', 'email' => 'sixteen' . DOMAIN, 'comment' => 'zz-guard-redirect me', 'wp-comment-cookies-consent' => 'yes'], []],
];

$check('the fixtures exist (closed post, draft, held comment)', $closed > 0 && $draft > 0 && $held > 0);
foreach ($cases as $label => [$form, $options]) {
    if (str_starts_with($label, 'a plugin ') && !str_contains($option('active_plugins'), 'minn-test-comment-guard')) {
        shell_exec("{$WP} plugin activate minn-test-comment-guard >/dev/null 2>&1");
    }
    foreach ($options as $name => $value) {
        shell_exec("{$WP} option update {$name} " . escapeshellarg($value) . ' >/dev/null 2>&1');
    }
    $email = (string) ($form['email'] ?? '');
    $answers = [];
    foreach (['reference' => $REF, 'engine' => $ENGINE] as $stack => $base) {
        $answer = $post($base, $form);
        $answer['stored'] = $email !== '' ? $stored($email) : null;
        $answers[$stack] = $answer;
        // Each stack's comment goes before the other posts (the held parent stays).
        $sweepBut = json_decode((string) shell_exec("{$WP} comment list --status=any --fields=comment_ID,comment_author_email --format=json 2>/dev/null"), true);
        foreach (is_array($sweepBut) ? $sweepBut : [] as $comment) {
            if (str_ends_with((string) ($comment['comment_author_email'] ?? ''), DOMAIN) && (int) $comment['comment_ID'] !== $held) {
                shell_exec("{$WP} comment delete " . (int) $comment['comment_ID'] . ' --force >/dev/null 2>&1');
            }
        }
    }
    if ($show) {
        echo "--- {$label}\n  reference: " . json_encode($answers['reference'], JSON_UNESCAPED_SLASHES) . "\n  engine:    " . json_encode($answers['engine'], JSON_UNESCAPED_SLASHES) . "\n";
    }
    foreach (['status', 'location', 'cookies', 'words', 'stored'] as $part) {
        $check("{$label}: {$part}", $answers['reference'][$part] === $answers['engine'][$part], 'reference ' . json_encode($answers['reference'][$part], JSON_UNESCAPED_SLASHES) . "\n       engine    " . json_encode($answers['engine'][$part], JSON_UNESCAPED_SLASHES));
    }
}

// A held comment is shown to its author: remembered by the cookie, or by the link the redirect carried.
$seesHeld = static function (string $base, array $form, bool $remembered) use ($post): array {
    $jar = tempnam(sys_get_temp_dir(), 'minn-form-jar');
    $ch = curl_init($base . '/wp-comments-post.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form), CURLOPT_COOKIEJAR => $jar, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    $raw = (string) curl_exec($ch);
    unset($ch); // curl writes the cookie jar when the handle goes
    preg_match('/^location:\s*(\S+)/im', $raw, $m);
    $page = curl_init($remembered ? $base . '/hello-world/' : (string) ($m[1] ?? ''));
    curl_setopt_array($page, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $remembered ? $jar : '', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    $html = (string) curl_exec($page);
    @unlink($jar);
    $at = strpos($html, (string) $form['comment']);
    $item = $at === false ? '' : substr($html, (int) strrpos(substr($html, 0, $at), '<li'), 400);
    preg_match('/<li id="comment-\d+" class="([^"]*)"/', $item, $class);
    return ['shown' => $at !== false, 'class' => $class[1] ?? '', 'waiting' => str_contains($item . substr($html, (int) $at - 300, 300), 'comment-awaiting-moderation')];
};
foreach (['remembered by the cookie' => true, 'by the link with its hash' => false] as $how => $remembered) {
    $answers = [];
    foreach (['reference' => $REF, 'engine' => $ENGINE] as $stack => $base) {
        $answers[$stack] = $seesHeld($base, ['comment_post_ID' => 1, 'author' => 'Held Reader', 'email' => 'held-' . $stack . DOMAIN, 'comment' => "zz held for its author, {$how}", ...($remembered ? ['wp-comment-cookies-consent' => 'yes'] : [])], $remembered);
        $sweep();
    }
    $check("a held comment is shown to its author, {$how}", $answers['reference'] === $answers['engine'] && $answers['reference']['shown'] && $answers['reference']['waiting'], 'reference ' . json_encode($answers['reference']) . "\n       engine    " . json_encode($answers['engine']));
}

// The form itself, for a reader, a remembered commenter and a signed-in administrator: comment_form's, hooks and all.
$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(__DIR__ . '/tools/mint-session.php') . ' 1 2>/dev/null'), true);
$respond = static function (string $base, string $cookie): string {
    $ch = curl_init($base . '/hello-world/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $cookie === '' ? [] : ['Cookie: ' . $cookie]]);
    $html = (string) curl_exec($ch);
    $at = strpos($html, '<div id="respond"');
    $form = $at === false ? '(no form)' : substr($html, $at, (int) strpos($html, '<!-- #respond -->', $at) - $at);
    $form = str_replace([$base, rawurlencode($base)], '{home}', $form);
    return (string) preg_replace(['/_wpnonce=[0-9a-f]+/', '/value="[0-9a-f]{10}"/'], ['_wpnonce={nonce}', 'value="{nonce}"'], $form);
};
foreach (['a signed-out reader' => 'none', 'a remembered commenter' => 'commenter', 'a signed-in administrator' => 'admin'] as $reader => $kind) {
    $forms = [];
    foreach (['reference' => $REF, 'engine' => $ENGINE] as $stack => $base) {
        $hash = md5($base); // COOKIEHASH: the address each stack answers at
        $cookie = match ($kind) {
            'commenter' => "comment_author_{$hash}=Remy+Reader; comment_author_email_{$hash}=remy%40example.com; comment_author_url_{$hash}=https%3A%2F%2Fremy.example",
            'admin' => 'wordpress_logged_in_' . md5($base) . '=' . rawurlencode((string) ($mint['cookie'] ?? '')),
            default => '',
        };
        $forms[$stack] = $respond($base, $cookie);
    }
    $check("the comment form for {$reader} is the reference's", $forms['reference'] === $forms['engine'] && str_contains($forms['engine'], '<form'), 'reference ' . substr($forms['reference'], 0, 400) . "\n       engine    " . substr($forms['engine'], 0, 400));
}

// The link with its hash shows the held comment for ten minutes after posting, no longer.
$aged = gmdate('Y-m-d H:i:s', time() - 11 * 60);
$agedId = (int) trim((string) shell_exec("{$WP} comment create --comment_post_ID=1 --comment_author='Aged' --comment_author_email=aged" . DOMAIN . " --comment_content='zz aged link' --comment_approved=0 --comment_date_gmt=" . escapeshellarg($aged) . ' --comment_date=' . escapeshellarg($aged) . ' --porcelain 2>/dev/null'));
$agedHash = trim((string) shell_exec("{$WP} eval " . escapeshellarg("echo wp_hash('{$aged}');") . ' 2>/dev/null'));
$agedSeen = [];
foreach (['reference' => $REF, 'engine' => $ENGINE] as $stack => $base) {
    [, $html] = minn_test_fetch($base . "/hello-world/?unapproved={$agedId}&moderation-hash={$agedHash}");
    $agedSeen[$stack] = str_contains((string) $html, 'zz aged link');
}
$sweep();
$check('a held comment\'s link stops showing it after ten minutes', $agedId > 0 && $agedSeen === ['reference' => false, 'engine' => false], json_encode($agedSeen));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
