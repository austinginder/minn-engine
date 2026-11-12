<?php

declare(strict_types=1);

/**
 * The security sweeps of 2026-10-13 and 2026-10-18
 * (docs/security-review-<date>.md), pinned where only a request or the
 * engine itself shows it. Compared with
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
$http = static function (string $url, string $method = 'GET', array $fields = [], string $cookie = '', array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 120, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
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

// The recovery pause: failures on a visitor's input (query, header, path, a forged check token) never pause a plugin;
// one that fails on every request is paused once the engine's own request fails on it too (Austin, 2026-10-24).
$plugins = "{$PUBLIC}/wp-content/plugins/minn-test-sweep-fails";
@mkdir($plugins, 0755, true);
copy(__DIR__ . '/fixtures/sweep/minn-test-sweep-fails.php', "{$plugins}/minn-test-sweep-fails.php");
register_shutdown_function(static function () use ($wp, $plugins): void {
    $wp('minn recovery resume minn-test-sweep-fails/minn-test-sweep-fails.php');
    // The fixture throws on init while the option is set, and WP-CLI loads plugins: these run without them.
    $wp('option delete minn_test_sweep_fail_always --skip-plugins');
    $wp('plugin deactivate minn-test-sweep-fails --skip-plugins');
    @unlink("{$plugins}/minn-test-sweep-fails.php");
    @rmdir($plugins);
});
$wp('plugin activate minn-test-sweep-fails');
foreach ([1, 2] as $try) {
    $http("{$ENGINE}/?minn_test_sweep_fail={$try}");
}
$status = $wp('minn recovery');
$check('two requests that make a plugin fail on their input do not pause it', str_contains($status, 'nothing paused'), $status);
$wp('minn recovery resume-all');
$failed = [];
foreach ([1, 2] as $try) {
    [$failed[]] = $http("{$ENGINE}/", headers: ['X-Minn-Test-Sweep-Fail: 1']);
}
$status = $wp('minn recovery');
$check('two plain requests that make a plugin fail on a header they carry do not pause it', $failed === [500, 500] && str_contains($status, 'nothing paused'), json_encode($failed) . ' ' . $status);
$wp('minn recovery resume-all');
$failed = [];
foreach ([1, 2] as $try) {
    [$failed[]] = $http("{$ENGINE}/zz-sweep-fail/");
}
$status = $wp('minn recovery');
$check('two plain requests that make a plugin fail on their path do not pause it', $failed === [500, 500] && str_contains($status, 'nothing paused'), json_encode($failed) . ' ' . $status);
$wp('minn recovery resume-all');
[$forged] = $http("{$ENGINE}/", headers: ['X-Minn-Test-Sweep-Fail: 1', 'X-Minn-Recovery-Check: ' . str_repeat('0', 32)]);
$status = $wp('minn recovery');
$check('a request carrying a check token recovery did not issue pauses nothing', $forged === 500 && str_contains($status, 'nothing paused'), $forged . ' ' . $status);
$wp('minn recovery resume-all');
$wp('option update minn_test_sweep_fail_always 1');
[$first] = $http("{$ENGINE}/");
$status = $wp('minn recovery');
$check('a plugin failing on every request is paused after the first, once the engine\'s own request fails on it too', $first === 500 && str_contains($status, 'minn-test-sweep-fails'), $first . ' ' . $status);

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

// 2026-10-18: block markup a commenter writes stays text where a page's own content shows its comments.
$refWp = static fn (string $code): string => trim((string) shell_exec('cd ' . escapeshellarg("{$SITE}/wp-reference") . ' && wp eval ' . escapeshellarg($code) . ' 2>/dev/null'));
$staged = json_decode($refWp('
    $content = "<!-- wp:paragraph --><p>Sweep page.</p><!-- /wp:paragraph -->\n<!-- wp:comments --><div class=\"wp-block-comments\"><!-- wp:comment-template --><!-- wp:comment-content /--><!-- /wp:comment-template --></div><!-- /wp:comments -->";
    $post = wp_insert_post(["post_title" => "zz sweep comment blocks", "post_status" => "publish", "post_type" => "page", "post_content" => $content, "comment_status" => "open"]);
    $text = wp_unslash(wp_filter_kses(wp_slash("Hello <!-- wp:search {\"label\":\"ZZSWEEP\"} /--> there")));
    $comment = is_int($post) && $post > 1 ? wp_insert_comment(["comment_post_ID" => $post, "comment_content" => $text, "comment_approved" => 1, "comment_author" => "Zz Sweep"]) : 0;
    echo json_encode([$post, $comment, get_permalink($post)]);
'), true);
if (is_array($staged) && is_int($staged[0]) && $staged[0] > 1) {
    $path = (string) parse_url((string) $staged[2], PHP_URL_PATH);
    [, $engine] = $http($ENGINE . $path);
    [, $reference] = $http($REF . $path);
    $inert = static fn (string $body): bool => str_contains($body, 'Hello <!-- wp:search {"label":"ZZSWEEP"} /--> there') && !str_contains($body, 'class="wp-block-search');
    $check('a comment\'s block markup is printed as text, not rendered (it is on the reference)', $inert($engine) && $inert($reference), 'engine ' . ($inert($engine) ? 'inert' : 'rendered') . ', reference ' . ($inert($reference) ? 'inert' : 'rendered'));
    $refWp('wp_delete_post(' . $staged[0] . ', true);');
} else {
    $check('the comment-blocks page could be staged', false, json_encode($staged));
}

// 2026-10-18: a post that shows its own content, and two posts whose inheriting loops list each other, render as on the
// reference: a post's content never inside itself, an inheriting loop inside the main loop walking a copy of it from the start.
$staged = json_decode($refWp('
    $loop = "<!-- wp:query {\"query\":{\"inherit\":true}} --><div class=\"wp-block-query\"><!-- wp:post-template --><!-- wp:post-content /--><!-- /wp:post-template --></div><!-- /wp:query -->";
    $ids = [];
    $ids[] = wp_insert_post(["post_title" => "zz sweep self", "post_status" => "publish", "post_content" => "<!-- wp:paragraph --><p>ZZSELF</p><!-- /wp:paragraph -->\n<!-- wp:post-content /-->"]);
    $ids[] = wp_insert_post(["post_title" => "zz sweep loop a", "post_status" => "publish", "post_content" => "<!-- wp:paragraph --><p>ZZLOOPA</p><!-- /wp:paragraph -->\n" . $loop]);
    $ids[] = wp_insert_post(["post_title" => "zz sweep loop b", "post_status" => "publish", "post_content" => "<!-- wp:paragraph --><p>ZZLOOPB</p><!-- /wp:paragraph -->\n" . $loop]);
    echo json_encode([$ids, get_permalink($ids[0])]);
'), true);
$ids = array_values(array_filter((array) ($staged[0] ?? []), static fn ($id) => is_int($id) && $id > 1));
if (count($ids) === 3) {
    $sequence = static fn (string $body): string => implode(' ', array_map(static fn (array $m) => $m[1] !== '' ? $m[1] : 'post-' . $m[2], (preg_match_all('/(ZZLOOPA|ZZLOOPB|ZZSELF)|<li class="wp-block-post [^"]*\bpost-(\d+)\b/', $body, $found, PREG_SET_ORDER) ? $found : [])));
    $self = (string) parse_url((string) $staged[1], PHP_URL_PATH);
    [$status, $engine] = $http($ENGINE . $self);
    [, $reference] = $http($REF . $self);
    $check('a post that shows its own content renders it once (it does on the reference)', $status === 200 && substr_count($engine, 'ZZSELF') === 1 && substr_count($reference, 'ZZSELF') === 1, 'engine ' . substr_count($engine, 'ZZSELF') . ', reference ' . substr_count($reference, 'ZZSELF'));
    $started = microtime(true);
    [$status, $engine] = $http($ENGINE . '/');
    $took = microtime(true) - $started;
    [, $reference] = $http($REF . '/');
    $check('posts whose loops list each other render their loops as the reference does, at once', $status === 200 && $took < 10 && $sequence($engine) === $sequence($reference) && str_contains($engine, 'ZZLOOPA'), sprintf('%.1fs; engine %s; reference %s', $took, substr($sequence($engine), 0, 300), substr($sequence($reference), 0, 300)));
}
$check('the loop posts could be staged and are removed', count($ids) === 3, json_encode($staged));
if ($ids !== []) {
    $refWp('foreach (' . var_export($ids, true) . ' as $id) { wp_delete_post($id, true); }');
}

// 2026-10-18: a key or salt that is the installer's placeholder, or that another key or salt shares, is left out
// (its option keys the scheme instead), as on the reference: SECRET_KEY, which this site does not define, set four ways.
$salted = static function (string $side, string $secret) use ($ROOT, $SITE): string {
    $code = '[$side, $secret, $site] = [$argv[1], $argv[2], $argv[3]];
        $config = (string) file_get_contents("{$site}/public/wp-config.php");
        foreach (["@AUTH_KEY", "@AUTH_SALT"] as $tag) {
            if ($secret === $tag && preg_match("/define\\(\\s*.(" . substr($tag, 1) . ").,\\s*.(.*?).\\s*\\);/", $config, $m)) { $secret = stripcslashes($m[2]); }
        }
        define("SECRET_KEY", $secret);
        if ($side === "reference") { define("WP_USE_THEMES", false); $_SERVER["HTTP_HOST"] = parse_url(getenv("MINN_SALT_HOST"), PHP_URL_HOST); chdir("{$site}/wp-reference"); require "wp-load.php"; } else { require getenv("MINN_SALT_ROOT") . "/tests/tools/engine-runtime.php"; }
        echo md5(wp_salt("zz-plugin-scheme")), " ", md5(wp_salt("auth")), " ", md5(wp_salt("nonce"));';
    return trim((string) shell_exec('MINN_SALT_ROOT=' . escapeshellarg($ROOT) . ' MINN_SALT_HOST=' . escapeshellarg(minn_test_url()) . ' php -d display_errors=0 -r ' . escapeshellarg($code) . ' ' . escapeshellarg($side) . ' ' . escapeshellarg($secret) . ' ' . escapeshellarg($SITE) . ' 2>/dev/null'));
};
$hadOptions = $refWp('echo json_encode([get_option("auth_key", null) !== null, get_option("auth_salt", null) !== null]);');
foreach (['the placeholder' => 'put your unique phrase here', 'AUTH_KEY again' => '@AUTH_KEY', 'AUTH_SALT again' => '@AUTH_SALT', 'a key of its own' => 'zz-a-secret-key-of-its-own-0123456789'] as $label => $secret) {
    $engine = $salted('engine', $secret);
    $reference = $salted('reference', $secret);
    $check("SECRET_KEY as {$label} salts a plugin's scheme, auth and nonce as on the reference", $engine !== '' && $engine === $reference, "engine {$engine}, reference {$reference}");
}
// The salt options a shared key made on both stacks go again, unless the site had them.
[$hadKey, $hadSalt] = json_decode($hadOptions, true) ?: [true, true];
$refWp(($hadKey ? '' : 'delete_option("auth_key");') . ($hadSalt ? '' : 'delete_option("auth_salt");'));

// 2026-10-23: ?rest_route= is REST only where the reference's script runs the main query; wp-cron.php, admin-ajax.php
// and the other scripts answer as themselves (a REST request there ran under DOING_CRON or DOING_AJAX).
$scripts = ['/wp-cron.php', '/wp-admin/admin-ajax.php', '/wp-admin/admin-post.php', '/wp-login.php', '/wp-comments-post.php', '/wp-links-opml.php', '/wp-mail.php', '/wp-admin/post.php', '/wp-signup.php', '/wp-trackback.php', '/'];
$answers = [];
foreach ($scripts as $script) {
    foreach (['GET', 'POST'] as $method) {
        $shape = static fn (array $answer): string => $answer[0] . ' ' . substr(ltrim($answer[1]), 0, 1);
        $answers["{$method} {$script}"] = [$shape($http("{$ENGINE}{$script}?rest_route=/wp/v2/types/post", $method)), $shape($http("{$REF}{$script}?rest_route=/wp/v2/types/post", $method))];
    }
}
$apart = array_filter($answers, static fn (array $pair): bool => $pair[0] !== $pair[1]);
$check('?rest_route= is answered as REST only where the reference answers it', $apart === [], json_encode($apart));

// 2026-10-23: the engine's own quoting goes through the connection, so a multibyte DB_CHARSET (gbk) whose lead
// byte would swallow a backslash cannot close a quote: the input comes back whole as one literal, as the reference's does.
$multibyte = static function (string $side, bool $dropIn = false) use ($ROOT, $SITE): string {
    $code = '[$side, $site, $dropIn] = [$argv[1], $argv[2], $argv[3] === "1"];
        if ($side === "reference") { define("WP_USE_THEMES", false); $_SERVER["HTTP_HOST"] = parse_url(getenv("MINN_SALT_HOST"), PHP_URL_HOST); chdir("{$site}/wp-reference"); require "wp-load.php"; } else { require getenv("MINN_SALT_ROOT") . "/tests/tools/engine-runtime.php"; }
        global $wpdb;
        $evil = "\xbf\x27 OR 1=1 -- ";
        if ($dropIn) {
            // A db.php drop-in (HyperDB, LudicrousDB) runs $wpdb on a connection of its own, the engine\'s left as it was.
            $wpdb->dbh = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        }
        $wpdb->set_charset($wpdb->dbh, "gbk");
        $quoted = $side === "reference" ? $wpdb->prepare("%s", $evil) : Minn\Query\Sql::quote($evil);
        // Straight to the connection: wpdb on the reference refuses the query as invalid text before it runs.
        $result = $wpdb->dbh->query("SELECT " . $quoted);
        $wpdb->set_charset($wpdb->dbh, "utf8mb4");
        echo bin2hex($quoted), " ", $result instanceof mysqli_result ? bin2hex((string) $result->fetch_row()[0]) : "error";';
    return trim((string) shell_exec('MINN_SALT_ROOT=' . escapeshellarg($ROOT) . ' MINN_SALT_HOST=' . escapeshellarg(minn_test_url()) . ' php -d display_errors=0 -r ' . escapeshellarg($code) . ' ' . escapeshellarg($side) . ' ' . escapeshellarg($SITE) . ' ' . ($dropIn ? '1' : '0') . ' 2>/dev/null'));
};
$engine = $multibyte('engine', true);
$reference = $multibyte('reference', true);
$check('a drop-in\'s own gbk connection: the engine quotes on it, as the reference does', $engine === $reference && str_ends_with($engine, ' ' . bin2hex("\xbf\x27 OR 1=1 -- ")), "engine {$engine}, reference {$reference}");
$engine = $multibyte('engine');
$reference = $multibyte('reference');
$check('a gbk connection: a lead byte cannot close the engine\'s quote (quoted as the reference quotes it, the literal back whole)', $engine === $reference && str_ends_with($engine, ' ' . bin2hex("\xbf\x27 OR 1=1 -- ")), "engine {$engine}, reference {$reference}");

// 2026-11-12 (finding 3.2): a post's author may edit a comment on their post, but only a moderator gives it to
// another user. The reference lets the author do it and read back that user's email; Minn refuses, as create does.
$author = $wp('user create zz-sweep-author zz-sweep-author@example.com --role=author --porcelain');
$authorPass = $wp("user application-password create {$author} zz-sweep --porcelain");
$authorPost = $wp("post create --post_title='zz sweep author post' --post_status=publish --post_author={$author} --porcelain");
$authorComment = $wp("comment create --comment_post_ID={$authorPost} --comment_author=Visitor --comment_author_email=visitor@example.com --comment_content=hello --comment_approved=1 --porcelain");
register_shutdown_function(static function () use ($wp, $author, $authorPost): void {
    $wp("post delete {$authorPost} --force");
    $wp("user delete {$author} --yes");
});
$basic = ['Authorization: Basic ' . base64_encode("zz-sweep-author:{$authorPass}"), 'Content-Type: application/json'];
$commentUrl = "{$ENGINE}/wp-json/wp/v2/comments/{$authorComment}?context=edit";
$curlJson = static function (string $url, array $body) use ($basic): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_HTTPHEADER => $basic]);
    $answer = (string) curl_exec($ch);
    return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $answer];
};
[$status, $body] = $curlJson($commentUrl, ['author' => 1]);
$check('a post\'s author cannot give a comment on it to another user, nor read their email', $status === 403 && str_contains($body, 'rest_comment_invalid_author') && !str_contains($body, 'author_email'), "{$status} " . substr($body, 0, 160));
[$status, $body] = $curlJson($commentUrl, ['content' => 'edited by the post\'s author']);
$check('a post\'s author still edits a comment on it', $status === 200 && str_contains($body, 'edited by the post'), "{$status} " . substr($body, 0, 160));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
