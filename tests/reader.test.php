<?php

declare(strict_types=1);

/**
 * Milestones 22 and 23: the front-end comment form's target, the
 * post-password cookie, private posts for signed-in readers, and
 * previews. Each check matches the reference on the same database
 * (same request, same cookie) or exercises the cross-stack acceptance of
 * a cookie the other stack minted. Everything created here is removed.
 */

$ENGINE = 'https://minn.localhost';
$REF = 'https://ref.minn.localhost';
$ROOT = dirname(__DIR__);
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
    return trim((string) shell_exec('cd ' . escapeshellarg(minn_test_site_root() . '/wp-reference') . " && wp $command 2>/dev/null"));
}
function mint(int $uid): array
{
    global $ROOT;
    return (array) json_decode((string) shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("$ROOT/tests/tools/mint-session.php") . " $uid 2>/dev/null"), true);
}
function sessionCookie(array $mint, string $base): string
{
    return 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5($base) . '=' . $mint['cookie'];
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
/**
 * The same POST on both stacks: status and the Location's path+fragment shape must
 * agree. The database is shared, so the reference goes first and its comment is
 * removed before the engine posts the same words.
 */
function bothPost(string $label, string $path, string $data, array $headersFor = null, string $purge = ''): array
{
    global $ENGINE, $REF;
    $r = page($REF . $path, $data, $headersFor === null ? [] : [$headersFor[$REF]]);
    if ($purge !== '') {
        wp("db query \"DELETE FROM wp_comments WHERE comment_content = '" . str_replace("'", "''", $purge) . "'\"");
        sleep(16);
    }
    $e = page($ENGINE . $path, $data, $headersFor === null ? [] : [$headersFor[$ENGINE]]);
    $shape = static fn (array $p, string $base): string => $p['status'] . ' ' . preg_replace(['/^(?:' . preg_quote($base, '/') . '|' . preg_quote(minn_test_url(), '/') . ')/', '/comment-\d+/', '/unapproved=\d+/', '/moderation-hash=[0-9a-f]+/'], ['', 'comment-N', 'unapproved=N', 'moderation-hash=H'], $p['headers']['location'] ?? '');
    check($shape($e, $ENGINE) === $shape($r, $REF), $label, 'engine ' . $shape($e, $ENGINE) . ' / reference ' . $shape($r, $REF));
    return [$e, $r];
}
$created = [];
register_shutdown_function(static function () use (&$created): void {
    foreach ($created as $id) {
        wp("post delete $id --force");
    }
    foreach (explode("\n", wp("db query \"SELECT comment_ID FROM wp_comments WHERE comment_author IN ('Zed Tester', 'Erin Editor', 'Yan Reader') OR comment_content LIKE 'zz reader%'\" --skip-column-names")) as $id) {
        if (ctype_digit(trim($id))) {
            wp('comment delete ' . trim($id) . ' --force');
        }
    }
    wp("db query \"DELETE FROM wp_posts WHERE post_type='revision' AND post_parent=10 AND post_name LIKE '10-autosave%'\"");
    wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");
});
$engineCookieHash = md5($ENGINE);
$refCookieHash = md5(minn_test_url()); // the reference answers as the site, so its siteurl (and cookie hash) is the site's

echo "reader suite: $ENGINE (engine) vs $REF (reference)\n";

// 1. wp-comments-post.php
$anon = 'comment_post_ID=1&author=Zed+Tester&email=zed@example.com&url=https://zed.example&comment=zz+reader+hello&comment_parent=0&wp-comment-cookies-consent=yes';
[$e] = bothPost('a first-time commenter is held and sent to the unapproved anchor', '/wp-comments-post.php', $anon, null, 'zz reader hello');
check(str_contains($e['headers']['set-cookie'] ?? '', "comment_author_{$engineCookieHash}=Zed") && str_contains($e['headers']['set-cookie'] ?? '', "comment_author_email_{$engineCookieHash}=zed") && str_contains($e['headers']['set-cookie'] ?? '', "comment_author_url_{$engineCookieHash}="), 'with consent, the three author cookies are set for a year', $e['headers']['set-cookie'] ?? '');
$row = wp("db query \"SELECT comment_approved, comment_author_email, comment_content, user_id FROM wp_comments WHERE comment_author='Zed Tester' ORDER BY comment_ID DESC LIMIT 1\" --skip-column-names");
check(str_starts_with($row, "0\tzed@example.com\tzz reader hello\t0"), 'the held comment is stored with its author fields', $row);
$e = page("$ENGINE/wp-comments-post.php", str_replace('zz+reader+hello', 'zz+reader+again', $anon));
check($e['status'] === '429' && str_contains($e['body'], 'too quickly'), 'posting again within fifteen seconds is 429', $e['status']);
sleep(16);
$e = page("$ENGINE/wp-comments-post.php", $anon);
check($e['status'] === '409' && str_contains($e['body'], 'Duplicate comment'), 'the same words again are a 409 duplicate');
bothPost('missing name and email is a 200 refusal page', '/wp-comments-post.php', 'comment_post_ID=1&author=&email=&comment=zz+reader+x');
bothPost('an empty comment is a 200 refusal page', '/wp-comments-post.php', 'comment_post_ID=1&author=Yan+Reader&email=yan@example.com&comment=');
$closed = (int) wp("post create --post_status=publish --post_title='zz reader closed' --comment_status=closed --porcelain");
$created[] = $closed;
bothPost('comments closed is a 403', '/wp-comments-post.php', "comment_post_ID=$closed&author=Yan+Reader&email=yan@example.com&comment=zz+reader+closed");
$get = page("$ENGINE/wp-comments-post.php");
check($get['status'] === '405' && ($get['headers']['allow'] ?? '') === 'POST', 'GET is 405 with Allow: POST', $get['status']);
$editor = mint(2);
sleep(16);
[$e, $r] = bothPost('a signed-in moderator\'s comment is approved and linked', '/wp-comments-post.php', 'comment_post_ID=1&comment=zz+reader+editor+says&comment_parent=0', [$ENGINE => sessionCookie($editor, $ENGINE), $REF => sessionCookie($editor, $REF)], 'zz reader editor says');
$row = wp("db query \"SELECT comment_approved, comment_author, comment_author_email, user_id FROM wp_comments WHERE comment_content='zz reader editor says' ORDER BY comment_ID DESC LIMIT 1\" --skip-column-names");
check(str_starts_with($row, "1\tErin Editor\teditor@minn-engine.localhost\t2"), 'the signed-in comment carries the account\'s name, email, and id', $row);
check(!isset($e['headers']['set-cookie']) || !str_contains($e['headers']['set-cookie'], 'comment_author_'), 'no author cookies for a signed-in commenter');
sleep(16);
$xss = page("$ENGINE/wp-comments-post.php", 'comment_post_ID=1&author=Yan+Reader<script>x</script>&email=yan@example.com&comment=zz+reader+<b>b</b><script>alert(1)</script><a+href="http://x"+onclick="1">l</a>');
$row = wp("db query \"SELECT comment_author, comment_content FROM wp_comments WHERE comment_author LIKE 'Yan Reader%' ORDER BY comment_ID DESC LIMIT 1\" --skip-column-names");
// The reference strips the tags from a name and keeps their text (strip_tags), so the script's "x" stays.
check($row === "Yan Readerx\tzz reader <b>b</b>alert(1)<a href=\"http://x\" rel=\"nofollow ugc\">l</a>", 'front-end comments go through the comment allowlist with ugc links', $row);

// 2. Post passwords: the cookie the reference minted unlocks here, and the other way round.
$pp = (int) wp("post create --post_status=publish --post_title='zz reader locked' --post_password=secret --post_content='<!-- wp:paragraph --><p>hidden words</p><!-- /wp:paragraph -->' --porcelain");
$created[] = $pp;
$unlockRef = page("$REF/wp-login.php?action=postpass", "post_password=secret&redirect_to=$REF/zz-reader-locked/");
preg_match('/wp-postpass_[0-9a-f]+=([^;]+)/', $unlockRef['headers']['set-cookie'] ?? '', $rc);
$refCookie = urldecode($rc[1] ?? '');
check(str_starts_with($refCookie, '$P$'), 'the reference stores a phpass hash in its cookie', $refCookie);
$unlockEngine = page("$ENGINE/wp-login.php?action=postpass", "post_password=secret&redirect_to=$ENGINE/zz-reader-locked/");
preg_match('/wp-postpass_[0-9a-f]+=([^;]+)/', $unlockEngine['headers']['set-cookie'] ?? '', $ec);
$engineCookie = urldecode($ec[1] ?? '');
check($unlockEngine['status'] === '302' && ($unlockEngine['headers']['location'] ?? '') === "$ENGINE/zz-reader-locked/" && str_starts_with($engineCookie, '$P$') && str_contains($unlockEngine['headers']['set-cookie'] ?? '', 'path=/'), 'the engine answers postpass the same way with a phpass cookie', json_encode($unlockEngine['headers']));
check(str_contains(page("$ENGINE/zz-reader-locked/", '', ["Cookie: wp-postpass_{$engineCookieHash}=" . rawurlencode($refCookie)])['body'], 'hidden words'), 'the reference\'s cookie unlocks the post on the engine');
check(str_contains(page("$REF/zz-reader-locked/", '', ["Cookie: wp-postpass_{$refCookieHash}=" . rawurlencode($engineCookie)])['body'], 'hidden words'), 'the engine\'s cookie unlocks the post on the reference');
$otherCookie = urldecode(preg_match('/wp-postpass_[0-9a-f]+=([^;]+)/', page("$ENGINE/wp-login.php?action=postpass", 'post_password=other')['headers']['set-cookie'] ?? '', $oc) ? $oc[1] : '');
$locked = page("$ENGINE/zz-reader-locked/", '', ["Cookie: wp-postpass_{$engineCookieHash}=" . rawurlencode($otherCookie)]);
check(!str_contains($locked['body'], 'hidden words') && str_contains($locked['body'], 'post-password-form'), 'a cookie for another password keeps the form');
$unlocked = page("$ENGINE/zz-reader-locked/", '', ["Cookie: wp-postpass_{$engineCookieHash}=" . rawurlencode($engineCookie)]);
check(str_contains($unlocked['body'], 'Protected: zz reader locked') && !str_contains($unlocked['body'], 'post-password-form'), 'unlocked, the title keeps its prefix and the form is gone');
$offsite = page("$ENGINE/wp-login.php?action=postpass", 'post_password=secret&redirect_to=https://evil.example/');
check(($offsite['headers']['location'] ?? '') === "$ENGINE/", 'postpass never redirects off-site');

// 3. Private posts.
$priv = (int) wp("post create --post_status=private --post_title='zz reader private' --post_author=2 --post_content='<!-- wp:paragraph --><p>private words</p><!-- /wp:paragraph -->' --porcelain");
$created[] = $priv;
$author = mint(3);
foreach (['anonymous' => null, 'another author' => $author] as $who => $m) {
    $e = page("$ENGINE/zz-reader-private/", '', $m === null ? [] : [sessionCookie($m, $ENGINE)]);
    $r = page("$REF/zz-reader-private/", '', $m === null ? [] : [sessionCookie($m, $REF)]);
    check($e['status'] === '404' && $r['status'] === '404', "a private post is 404 for $who on both stacks", $e['status'] . '/' . $r['status']);
}
$e = page("$ENGINE/zz-reader-private/", '', [sessionCookie($editor, $ENGINE)]);
$r = page("$REF/zz-reader-private/", '', [sessionCookie($editor, $REF)]);
check($e['status'] === '200' && $r['status'] === '200' && str_contains($e['body'], 'Private: zz reader private') && str_contains($r['body'], 'Private: zz reader private') && str_contains($e['body'], 'private words'), 'an editor reads it on both, with the Private: prefix');
check(preg_match('/<body class="[^"]*\blogged-in\b/', $e['body']) === 1, 'a signed-in reader gets the logged-in body class');
check(str_contains(page("$ENGINE/", '', [sessionCookie($editor, $ENGINE)])['body'], 'zz-reader-private') && !str_contains(page("$ENGINE/")['body'], 'zz-reader-private'), 'listings include the private post for the editor only');
check(str_contains(page("$ENGINE/feed/", '', [sessionCookie($editor, $ENGINE)])['body'], 'zz-reader-private') && !str_contains(page("$ENGINE/feed/")['body'], 'zz-reader-private'), 'the feed does the same');

// 4. Previews: the autosave shows in place of the draft, on both stacks, from the engine's own preview link.
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'method' => 'POST', 'header' => "Content-Type: application/json\r\n" . sessionCookie($author, $ENGINE) . "\r\nX-WP-Nonce: {$author['nonce']}", 'content' => json_encode(['title' => 'zz reader autosaved title', 'content' => '<!-- wp:paragraph --><p>zz reader autosave words</p><!-- /wp:paragraph -->'])]]);
$autosave = json_decode((string) @file_get_contents("$ENGINE/?rest_route=" . rawurlencode('/wp/v2/posts/10/autosaves'), false, $ctx), true);
$link = (string) ($autosave['preview_link'] ?? '');
check(preg_match('#\?p=10&preview_id=10&preview_nonce=[0-9a-f]{10}&preview=true$#', $link) === 1, 'the autosave answers with a preview link carrying a real nonce', $link);
$path = substr($link, strlen($ENGINE));
$e = page($ENGINE . $path, '', [sessionCookie($author, $ENGINE)]);
$r = page($REF . $path, '', [sessionCookie($author, $REF)]);
check($e['status'] === '200' && str_contains($e['body'], 'zz reader autosave words') && str_contains($e['body'], 'zz reader autosaved title'), 'the engine shows the autosave to its author', $e['status']);
check($r['status'] === '200' && str_contains($r['body'], 'zz reader autosave words'), 'the reference accepts the engine\'s preview nonce and shows the same autosave', $r['status']);
check(page($ENGINE . $path)['status'] === '404', 'the preview link is nothing to an anonymous reader');
$plain = page("$ENGINE/?p=10", '', [sessionCookie($author, $ENGINE)]);
check($plain['status'] === '200' && !str_contains($plain['body'], 'zz reader autosave words'), 'without the preview link the draft shows its stored content');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
