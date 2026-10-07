<?php

declare(strict_types=1);

/**
 * The 2026-08-28 security review, pinned. Each check either matches the
 * reference on the same database (same request, same cookie, same result)
 * or exercises an engine-only defence where the reference's answer needs a
 * plugin or a server. Everything written here is removed again.
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

/** A session the reference minted; its cookie and nonce are valid on both stacks. */
function mint(int $uid): array
{
    global $ROOT;
    $json = shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("$ROOT/tests/tools/mint-session.php") . " $uid 2>/dev/null");
    return (array) json_decode((string) $json, true);
}

/** @return array{0: int, 1: mixed, 2: array<string, string>} */
function call(string $base, string $route, ?array $mint, string $method = 'GET', ?array $body = null, array $extraHeaders = []): array
{
    global $REF;
    [$path, $qs] = array_pad(explode('?', $route, 2), 2, '');
    $url = "$base/?rest_route=" . rawurlencode($path) . ($qs === '' ? '' : '&' . $qs);
    $headers = $extraHeaders;
    if ($mint) {
        $headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5($REF) . '=' . $mint['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 15, 'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body === null ? '' : json_encode($body)],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    $hdrs = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int) $m[1];
        } elseif (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $hdrs[strtolower(trim($k))] = trim($v);
        }
    }
    return [$status, json_decode((string) $raw, true), $hdrs];
}

/** Same request on both stacks, same status and same error code (or same selected field). */
function parity(string $label, string $route, ?array $mint, string $method = 'GET', ?array $body = null, ?string $field = null): array
{
    global $ENGINE, $REF;
    [$es, $eb] = call($ENGINE, $route, $mint, $method, $body);
    [$rs, $rb] = call($REF, $route, $mint, $method, $body);
    $pick = static fn ($b) => $field === null ? ($b['code'] ?? null) : ($b[$field] ?? null);
    check($es === $rs && $pick($eb) === $pick($rb), $label, "engine $es " . json_encode($pick($eb)) . " / reference $rs " . json_encode($pick($rb)));
    return [$eb, $rb];
}

function wp(string $command): string
{
    global $ROOT;
    return trim((string) shell_exec('cd ' . escapeshellarg(minn_test_site_root() . '/wp-reference') . " && wp $command 2>/dev/null"));
}

/** @return array{status: string, headers: array<string, string>, body: string} a raw page fetch without following redirects */
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

echo "security suite: $ENGINE (engine) vs $REF (reference)\n";
$admin = mint(1);
$author = mint(3);
$created = ['posts' => [], 'comments' => [], 'tags' => [], 'users' => []];
register_shutdown_function(static function () use (&$created): void {
    foreach ($created['posts'] as $id) {
        wp("post delete $id --force");
    }
    foreach ($created['comments'] as $id) {
        wp("comment delete $id --force");
    }
    foreach ($created['tags'] as $id) {
        wp("term delete post_tag $id");
    }
    foreach ($created['users'] as $login) {
        wp("user delete $login --yes");
    }
    wp("user update 3 --display_name=scribe --first_name='' --nickname=scribe --user_url='' --description=''");
    wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");
});

// 1. Stored markup from an Author goes through the allowlist, byte for byte as the reference.
$payload = [
    'title' => 'zz sec <b>x</b><script>1</script>',
    'content' => '<!-- wp:paragraph {"a":1} --><p class="a" style="color:red;behavior:url(x);background:url(http://x/y.png)" onclick="1" data-x="1" aria-label="l">hi<script>alert(1)</script><a href="javascript:1" rel="nofollow" target="_blank">l</a><a href="https://ok/">o</a><iframe src="x"></iframe><img src="http://x/a.png" alt="a" srcset="a 1x" loading="lazy"><svg onload=1></svg>&lt;i&gt;<em>e</em></p><!-- /wp:paragraph -->',
    'excerpt' => '<em>e</em><script>1</script>',
    'status' => 'draft',
];
$raw = [];
foreach ([$ENGINE => 'engine', $REF => 'reference'] as $base => $side) {
    [$s, $b] = call($base, '/wp/v2/posts', $author, 'POST', $payload);
    $created['posts'][] = (int) ($b['id'] ?? 0);
    [, $edit] = call($base, "/wp/v2/posts/{$b['id']}?context=edit", $author);
    $raw[$side] = [$edit['title']['raw'] ?? null, $edit['content']['raw'] ?? null, $edit['excerpt']['raw'] ?? null];
}
check($raw['engine'] === $raw['reference'], 'author post title/content/excerpt filtered as the reference filters them', json_encode($raw));
[$s, $b] = call($ENGINE, '/wp/v2/posts', $admin, 'POST', ['title' => 'zz sec admin', 'content' => '<script>kept</script>', 'status' => 'draft']);
$created['posts'][] = (int) ($b['id'] ?? 0);
[, $edit] = call($ENGINE, "/wp/v2/posts/{$b['id']}?context=edit", $admin);
check(($edit['content']['raw'] ?? '') === '<script>kept</script>', 'unfiltered_html (administrator) markup is stored as written');

// 2. Comments: the smaller allowlist, links marked nofollow ugc, unreadable posts refused.
$commentBody = ['post' => 1, 'content' => '<b>b</b><img src=x onerror=1><a href="http://x" title="t" onclick="1" rel="x">l</a><script>1</script>&lt;i&gt;<blockquote cite="c">q</blockquote><p>p</p><code>c</code><strong>s</strong>'];
$rawComments = [];
foreach ([$ENGINE => 'engine', $REF => 'reference'] as $base => $side) {
    [$s, $b] = call($base, '/wp/v2/comments', $author, 'POST', $commentBody);
    $created['comments'][] = (int) ($b['id'] ?? 0);
    [, $edit] = call($base, "/wp/v2/comments/{$b['id']}?context=edit", $admin);
    $rawComments[$side] = $edit['content']['raw'] ?? "[$s]";
    sleep($side === 'engine' ? 16 : 0);
}
check($rawComments['engine'] === $rawComments['reference'], 'author comment filtered and ugc-marked as the reference stores it', json_encode($rawComments));
parity('comment on another author\'s draft is refused', '/wp/v2/comments', $author, 'POST', ['post' => 3, 'content' => 'x']);
$closed = (int) wp("post create --post_status=publish --post_title='zz sec closed' --comment_status=closed --porcelain");
$created['posts'][] = $closed;
parity('comment on a closed post is refused', '/wp/v2/comments', $author, 'POST', ['post' => $closed, 'content' => 'x']);
$trashed = (int) wp("post create --post_status=trash --post_title='zz sec trash' --porcelain");
$created['posts'][] = $trashed;
parity('comment on a trashed post is refused', '/wp/v2/comments', $author, 'POST', ['post' => $trashed, 'content' => 'x']);

// 3. Names and profiles as text.
// The database is shared, so the same name would collide: each stack creates and removes its own.
$tagBody = ['name' => "zz sec <b>bold</b>  name\t<script>x</script>&amp;", 'description' => '<a href="javascript:1" onclick="1">l</a><script>s</script><em>e</em>'];
$tags = [];
foreach ([$ENGINE => 'engine', $REF => 'reference'] as $base => $side) {
    [$s, $b] = call($base, '/wp/v2/tags', $author, 'POST', $tagBody);
    $tags[$side] = [$s, $b['name'] ?? null, $b['description'] ?? null];
    wp('term delete post_tag ' . (int) ($b['id'] ?? 0));
}
check($tags['engine'] === $tags['reference'], 'tag name and description filtered as the reference filters them', json_encode($tags));
[$eb, $rb] = parity('profile fields filtered', '/wp/v2/users/3', $author, 'POST', ['name' => 'Scribe <img src=x onerror=1>', 'first_name' => 'F<b>x</b>', 'nickname' => 'N<i>y</i>', 'url' => 'javascript:alert(1)', 'description' => '<b>ok</b><script>1</script>'], 'name');
check(array_intersect_key($eb, array_flip(['first_name', 'nickname', 'url', 'description'])) === array_intersect_key($rb, array_flip(['first_name', 'nickname', 'url', 'description'])), 'first name, nickname, url, description match the reference', json_encode([$eb['url'] ?? null, $rb['url'] ?? null, $eb['description'] ?? null, $rb['description'] ?? null]));
parity('an author cannot take another user\'s email', '/wp/v2/users/3', $author, 'POST', ['email' => 'admin@minn-engine.localhost']);
parity('an unknown role is refused', '/wp/v2/users', $admin, 'POST', ['username' => 'zzsecrole', 'email' => 'zzsecrole@x.test', 'password' => 'xx-yy-zz-11-22', 'roles' => ['bogus']]);

// 4. Read gates.
$quiet = wp("user create zzsecquiet zzsecquiet@x.test --role=subscriber --porcelain");
$created['users'][] = 'zzsecquiet';
parity('a user with no published content is not public', "/wp/v2/users/$quiet", null);
parity('anonymous callers cannot order users by email', '/wp/v2/users?orderby=email', null);
parity('reusable blocks are empty for anonymous callers', '/wp/v2/blocks', null, 'GET', null, '0');
[$eb, $rb] = parity('an author lists only their own unpublished posts', '/wp/v2/posts?status=draft,pending,private&context=edit&per_page=100', $author);
// Drafts created in the same second tie on date, and the reference's order among tied rows is not stable (it moves with the table), so the rows are compared as a set.
$engineIds = array_column((array) $eb, 'id');
$referenceIds = array_column((array) $rb, 'id');
sort($engineIds);
sort($referenceIds);
check($engineIds === $referenceIds, 'the same rows on both stacks', json_encode([$engineIds, $referenceIds]));
parity('media cannot be attached to a post the caller cannot edit', '/wp/v2/media', $author, 'POST', ['post' => 1]);

// 5. Password-protected content on the front end (engine only: same words the reference showed).
$protected = (int) wp("post create --post_status=publish --post_title='zz sec protected' --post_password=pw --post_content='<!-- wp:paragraph --><p>secret body text</p><!-- /wp:paragraph -->' --porcelain");
$created['posts'][] = $protected;
$single = page("$ENGINE/zz-sec-protected/");
check(!str_contains($single['body'], 'secret body') && str_contains($single['body'], 'post-password-form') && str_contains($single['body'], 'Protected: zz sec protected'), 'a protected post shows the form, not the body');
$feed = page("$ENGINE/feed/");
check(!str_contains($feed['body'], 'secret body') && str_contains($feed['body'], 'There is no excerpt because this is a protected post.'), 'the feed carries the protected excerpt, not the body');
$refFeed = page("$REF/feed/");
check(str_contains($refFeed['body'], 'There is no excerpt because this is a protected post.') && !str_contains($refFeed['body'], 'secret body'), 'reference feed agrees');

// 6. Sign-in: open redirect closed, logout guarded, forwarded scheme ignored.
$login = page("$ENGINE/wp-login.php", 'log=admin&pwd=password&redirect_to=https://evil.example/');
check($login['status'] === '302' && str_starts_with($login['headers']['location'] ?? '', $ENGINE), 'redirect_to off-site lands in the admin instead', $login['headers']['location'] ?? '');
$login = page("$ENGINE/wp-login.php", 'log=admin&pwd=password&redirect_to=//evil.example/');
check(str_starts_with($login['headers']['location'] ?? '', $ENGINE), 'protocol-relative redirect_to is refused too');
$login = page("$ENGINE/wp-login.php", 'log=admin&pwd=password&redirect_to=/sample-page/');
check(($login['headers']['location'] ?? '') === "$ENGINE/sample-page/", 'a same-site path is honoured');
$login = page("$ENGINE/wp-login.php", 'log=admin@minn-engine.localhost&pwd=password');
check($login['status'] === '302' && str_contains($login['headers']['set-cookie'] ?? '', 'wordpress_logged_in_'), 'sign-in by email address works');
preg_match('/wordpress_logged_in_[0-9a-f]+=([^;]+)/', $login['headers']['set-cookie'] ?? '', $m);
$cookie = 'wordpress_logged_in_' . md5($ENGINE) . '=' . ($m[1] ?? '');
check(!str_contains($login['headers']['set-cookie'] ?? '', 'expires='), 'without Remember Me the cookies are session cookies');
$confirm = page("$ENGINE/wp-login.php?action=logout", '', ["Cookie: $cookie"]);
check($confirm['status'] === '200' && str_contains($confirm['body'], 'Do you really want to') && preg_match('/_wpnonce=([0-9a-f]{10})/', $confirm['body'], $n) === 1, 'logout without a nonce asks first');
$out = page("$ENGINE/wp-login.php?action=logout&_wpnonce=" . ($n[1] ?? ''), '', ["Cookie: $cookie"]);
check($out['status'] === '302' && str_contains($out['headers']['location'] ?? '', 'loggedout=true'), 'logout with the nonce signs out');
$me = page("$ENGINE/?rest_route=" . rawurlencode('/wp/v2/users/me'), '', ["Cookie: $cookie"]);
check($me['status'] === '401', 'the old cookie is dead after logout (session destroyed server-side)', $me['status']);
$xfp = page("$ENGINE/wp-login.php", 'log=admin&pwd=password', ['X-Forwarded-Proto: https']);
check(str_contains($xfp['headers']['set-cookie'] ?? '', 'wordpress_sec_') === str_contains(page("$ENGINE/wp-login.php", 'log=admin&pwd=password')['headers']['set-cookie'] ?? '', 'wordpress_sec_'), 'X-Forwarded-Proto does not change the cookie scheme');

// 7. Rendering defences (engine only).
$evil = (int) wp("post create --post_status=publish --post_title='zz sec render' --post_content='" . str_replace("'", "'\\''", '<!-- wp:template-part {"slug":"../../../../etc/passwd","tagName":"script>alert(1)</script"} /--><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"red}</style><script>x</script>"}}}},"layout":{"type":"flex","justifyContent":"center\\" onmouseover=\\"alert(1)"}} --><div class="wp-block-group">g</div><!-- /wp:group --><!-- wp:navigation-link {"label":"j","url":"javascript:alert(1)"} /--><!-- wp:block {"ref":0} /-->') . "' --porcelain");
$created['posts'][] = $evil;
$render = page("$ENGINE/zz-sec-render/");
check($render['status'] === '200' && !str_contains($render['body'], 'root:') && !str_contains($render['body'], '<script>alert') && !str_contains($render['body'], '<script>x</script>') && !str_contains($render['body'], 'onmouseover="') && !str_contains($render['body'], 'href="javascript:'), 'traversal, tag, style, attribute, and href injections are neutralised', substr($render['body'], 0, 200));
$deep = str_repeat('<!-- wp:group --><div class="wp-block-group">', 300) . 'deep' . str_repeat('</div><!-- /wp:group -->', 300);
$deepId = (int) wp("post create --post_status=publish --post_title='zz sec deep' --post_content='$deep' --porcelain");
$created['posts'][] = $deepId;
check(page("$ENGINE/zz-sec-deep/")['status'] === '200', 'three hundred nested groups render without a fatal');
$loop = (int) wp("post create --post_type=wp_block --post_status=publish --post_title='zz sec loop' --post_content='<!-- wp:paragraph --><p>loop</p><!-- /wp:paragraph -->' --porcelain");
$created['posts'][] = $loop;
wp("post update $loop --post_content='<!-- wp:block {\"ref\":$loop} /--><!-- wp:paragraph --><p>loop</p><!-- /wp:paragraph -->'");
$loopPost = (int) wp("post create --post_status=publish --post_title='zz sec loop post' --post_content='<!-- wp:block {\"ref\":$loop} /-->' --porcelain");
$created['posts'][] = $loopPost;
$looped = page("$ENGINE/zz-sec-loop-post/");
check($looped['status'] === '200' && substr_count($looped['body'], '>loop</p>') === 1, 'a synced pattern that references itself renders once', (string) substr_count($looped['body'], '>loop</p>'));

// 8. Uploads: no SVG, no intermediate PHP extension.
$boundary = 'minnsec' . bin2hex(random_bytes(4));
$multipart = static fn (string $name, string $content): string => "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n--$boundary--\r\n";
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'method' => 'POST', 'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\nCookie: {$admin['cookie_name']}={$admin['cookie']}\r\nX-WP-Nonce: {$admin['nonce']}", 'content' => $multipart('x.svg', '<svg onload="alert(1)"/>')]]);
@file_get_contents("$ENGINE/?rest_route=" . rawurlencode('/wp/v2/media'), false, $ctx);
check(str_contains($http_response_header[0] ?? '', '500') || str_contains($http_response_header[0] ?? '', '400'), 'an SVG upload is refused', $http_response_header[0] ?? '');
require_once "$ROOT/public/minn/src/Minn/Autoloader.php";
Minn\Autoloader::register();
check(Minn\Media\Uploads::sanitizeName('shell.php.png') === 'shell_php.png' && Minn\Media\Uploads::sanitizeName('a.b.jpg.png') === 'a_b.jpg.png', 'intermediate extensions are neutralised');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
