<?php
/**
 * Hook-trace parity: what plugins are told about a write. The same REST
 * writes (posts, pages, media, terms, users, comments, settings) run on
 * both stacks as an administrator with the fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-trace.php recording every action
 * each request fires, with its arguments described in terms that mean the
 * same on both stacks. The sequences from the REST server's start to the
 * response are compared after dropping the bookkeeping actions that do not
 * describe the write (query parsing, cache stamps, settings registration).
 *
 * A step whose sequence still differs is listed in DIVERGENT with the
 * reason; the list may only shrink. A listed step that now agrees fails
 * with "agrees now: drop it", as the allow suite does.
 *
 *   php tests/hook-trace.test.php [--show=<step>] [--filters]
 *
 * --filters also records the filters each request applies and prints, per
 * step, those only one stack applied (MINN_TRACE_DUMP=<file> keeps them).
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

const DIVERGENT = [
    'comment-form' => 'wp-comments-post.php is the engine\'s own: it fires none of the submission\'s actions (pre_comment_on_post, the flood and disallowed-list checks, wp_insert_comment, comment_post, set_comment_cookies)',
];

/**
 * What the reference tells plugins that Minn does not do on purpose, dropped
 * from both sides. Pingbacks, trackbacks and enclosure checks are Mute
 * (contracts/lexicon.md): on every save of a published post the reference
 * queues them as _pingme and _encloseme and puts do_pings on the cron
 * calendar; Minn sends none, so it queues none (contracts/round-trip.md).
 */
const DELIBERATE = [
    '/^(add|added)_post_meta\(int,(int,)?\'_(pingme|encloseme)\'/',
    '/^(update_option\(\'cron\'|update_option_cron\(|updated_option\(\'cron\')/',
];

/** Actions that describe how a stack looked something up, not what it told plugins about the write. */
const NOISE = '/^(register_setting|parse_term_query|pre_get_terms|wp_cache_set_last_changed|metadata_lazyloader_queued_objects|parse_tax_query|parse_query|pre_get_posts|posts_selection|the_post|wp_default_scripts|wp_default_styles|wp_error_added|is_wp_error_instance|parse_comment_query|pre_get_comments|pre_get_users|pre_user_query|parse_site_query|loop_start|loop_end|shutdown|rest_api_init|set_current_user|clean_object_term_cache|delete_transient_is_multi_author|update_option_wp_calendar_block_has_published_posts|wp_rest_server_class)$/';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');
$show = null;
$filters = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--show=')) {
        $show = substr($arg, 7);
    }
    $filters = $filters || $arg === '--filters';
}

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

// The tracer lives in both mu-plugin folders, and records, only while the suite runs.
$fixture = dirname(__DIR__) . '/tests/fixtures/mu-plugins/minn-test-trace.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$description = trim((string) shell_exec("{$WP} option get blogdescription 2>/dev/null"));
foreach ($stacks as [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-trace.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-trace.php");
    }
    @mkdir("{$content}/minn-trace", 0755, true);
}
// The form's comments, matched exactly by their author's address (a wp-cli filter it ignores would match every comment).
// Swept before each form post too: the stacks share a database, and one stack's comment is the other's flood.
$sweepReaders = static function () use ($WP): void {
    $comments = json_decode((string) shell_exec("{$WP} comment list --status=all --fields=comment_ID,comment_author_email --format=json 2>/dev/null"), true);
    foreach (is_array($comments) ? $comments : [] as $comment) {
        if (($comment['comment_author_email'] ?? '') === 'reader@minn-engine.localhost') {
            shell_exec("{$WP} comment delete " . (int) $comment['comment_ID'] . ' --force >/dev/null 2>&1');
        }
    }
};
register_shutdown_function(static function () use ($stacks, $WP, $description, $sweepReaders): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-trace.php");
        array_map('unlink', glob("{$content}/minn-trace/*") ?: []);
        @rmdir("{$content}/minn-trace");
    }
    shell_exec("{$WP} option update blogdescription " . escapeshellarg($description) . ' >/dev/null 2>&1');
    shell_exec("{$WP} user delete \$({$WP} user list --field=ID --login__in=tracer 2>/dev/null) --reassign=1 --yes >/dev/null 2>&1");
    $sweepReaders();
});

$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(dirname(__DIR__) . '/tests/tools/mint-session.php') . ' 1 2>/dev/null'), true);
if (!is_array($mint) || empty($mint['cookie'])) {
    echo "SKIP: could not mint a reference session\n";
    exit(0);
}
$cookie = 'Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($mint['cookie']) . '; wordpress_logged_in_' . md5($REF) . '=' . rawurlencode($mint['cookie']);

/**
 * One traced call; returns [status, decoded body, the actions it fired].
 * A REST route goes through ?rest_route= as an administrator; a path
 * ending in .php is a form post, sent signed out.
 */
$call = static function (string $base, string $content, string $run, string $method, string $route, $body = null, array $headers = []) use ($cookie, $mint, $filters): array {
    touch("{$content}/minn-trace/{$run}.open");
    $form = str_ends_with($route, '.php');
    $query = '';
    if (str_contains($route, '?')) {
        [$route, $query] = explode('?', $route, 2);
        $query = '&' . $query;
    }
    $sent = [...($form ? [] : [$cookie, 'X-WP-Nonce: ' . $mint['nonce']]), 'X-Minn-Trace: ' . $run, ...($filters ? ['X-Minn-Trace-Filters: 1'] : []), ...$headers];
    if (is_array($body) && $form) {
        $body = http_build_query($body);
    } elseif (is_array($body)) {
        $sent[] = 'Content-Type: application/json';
        $body = json_encode($body);
    }
    $ch = curl_init($form ? $base . $route : $base . '/?rest_route=' . rawurlencode($route) . $query);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $sent, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $lines = @file("{$content}/minn-trace/{$run}.ndjson", FILE_IGNORE_NEW_LINES) ?: [];
    $applied = json_decode((string) @file_get_contents("{$content}/minn-trace/{$run}.filters.json"), true);
    @unlink("{$content}/minn-trace/{$run}.open");
    return [$status, json_decode((string) $raw, true), $lines, is_array($applied) ? $applied : []];
};

/** The part of a trace that describes the write: from the REST server's start (a form post: once WordPress has loaded), bookkeeping dropped, repeats kept. */
$window = static function (array $lines, string $start = 'rest_api_init'): array {
    $out = [];
    $started = false;
    foreach ($lines as $line) {
        [$hook, $args] = json_decode($line, true);
        if ($hook === $start) {
            $started = true;
            continue;
        }
        // What runs after the response (the reference's cron spawn) is not the write.
        if ($started && $hook === 'shutdown') {
            break;
        }
        if (!$started || preg_match(NOISE, $hook) === 1) {
            continue;
        }
        $line = $hook . '(' . implode(',', $args) . ')';
        foreach (DELIBERATE as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                continue 2;
            }
        }
        $out[] = $line;
    }
    return $out;
};

$png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$steps = [];
foreach ($stacks as $stack => [$base, $content]) {
    $ids = [];
    $run = static function (string $name, string $method, string $route, $body = null, array $headers = []) use ($stack, $base, $content, $call, $window, &$steps): array {
        [$status, $data, $lines, $applied] = $call($base, $content, "{$stack}-{$name}", $method, $route, $body, $headers);
        $steps[$name][$stack] = ['status' => $status, 'trace' => $window($lines, str_ends_with($route, '.php') ? 'wp_loaded' : 'rest_api_init'), 'filters' => $applied];
        return is_array($data) ? $data : [];
    };
    $ids['post'] = (int) ($run('post-create-draft', 'POST', '/wp/v2/posts', ['title' => 'Trace post', 'content' => 'Body', 'status' => 'draft'])['id'] ?? 0);
    $run('post-publish', 'POST', "/wp/v2/posts/{$ids['post']}", ['status' => 'publish']);
    $run('post-edit', 'POST', "/wp/v2/posts/{$ids['post']}", ['title' => 'Trace post edited']);
    $run('post-trash', 'DELETE', "/wp/v2/posts/{$ids['post']}");
    $run('post-delete', 'DELETE', "/wp/v2/posts/{$ids['post']}?force=true");
    $ids['page'] = (int) ($run('page-create', 'POST', '/wp/v2/pages', ['title' => 'Trace page', 'status' => 'publish'])['id'] ?? 0);
    $run('page-delete', 'DELETE', "/wp/v2/pages/{$ids['page']}?force=true");
    $ids['media'] = (int) ($run('media-upload', 'POST', '/wp/v2/media', $png, ['Content-Type: image/png', 'Content-Disposition: attachment; filename="trace.png"'])['id'] ?? 0);
    $run('media-edit', 'POST', "/wp/v2/media/{$ids['media']}", ['alt_text' => 'Trace']);
    $run('media-delete', 'DELETE', "/wp/v2/media/{$ids['media']}?force=true");
    $ids['term'] = (int) ($run('term-create', 'POST', '/wp/v2/categories', ['name' => "Trace category {$stack}"])['id'] ?? 0);
    $run('term-edit', 'POST', "/wp/v2/categories/{$ids['term']}", ['description' => 'Traced']);
    $run('term-delete', 'DELETE', "/wp/v2/categories/{$ids['term']}?force=true");
    $ids['user'] = (int) ($run('user-create', 'POST', '/wp/v2/users', ['username' => 'tracer', 'email' => 'tracer@minn-engine.localhost', 'password' => 'Trace-pass-1!', 'roles' => ['author']])['id'] ?? 0);
    $run('user-edit', 'POST', "/wp/v2/users/{$ids['user']}", ['first_name' => 'Trace']);
    $run('user-delete', 'DELETE', "/wp/v2/users/{$ids['user']}?force=true&reassign=1");
    $ids['comment'] = (int) ($run('comment-create', 'POST', '/wp/v2/comments', ['post' => 1, 'content' => "Traced comment from the {$stack}"])['id'] ?? 0);
    $run('comment-unapprove', 'POST', "/wp/v2/comments/{$ids['comment']}", ['status' => 'hold']);
    $run('comment-delete', 'DELETE', "/wp/v2/comments/{$ids['comment']}?force=true");
    $run('settings', 'POST', '/wp/v2/settings', ['description' => "Traced on the {$stack}"]);
    // The comment form, signed out: where spam plugins (CleanTalk, Akismet) and notifications do their work.
    $sweepReaders();
    $run('comment-form', 'POST', '/wp-comments-post.php', ['comment_post_ID' => 1, 'author' => 'Trace Reader', 'email' => 'reader@minn-engine.localhost', 'url' => '', 'comment' => "A traced comment from the {$stack} form", 'comment_parent' => 0]);
}

if ($filters) {
    // MINN_TRACE_DUMP=<file> keeps each step's filters, in the order each was first applied, for reading side by side.
    if (getenv('MINN_TRACE_DUMP')) {
        file_put_contents((string) getenv('MINN_TRACE_DUMP'), json_encode(array_map(static fn ($pair) => ['reference' => $pair['reference']['filters'], 'engine' => $pair['engine']['filters']], $steps), JSON_PRETTY_PRINT));
    }
    foreach ($steps as $name => $pair) {
        $only = static fn (array $a, array $b): string => implode(' ', array_diff($a, $b));
        echo "--- {$name} filters\n  reference only: " . $only($pair['reference']['filters'], $pair['engine']['filters']) . "\n  engine only: " . $only($pair['engine']['filters'], $pair['reference']['filters']) . "\n";
    }
}

$listed = DIVERGENT;
foreach ($steps as $name => $pair) {
    $reference = $pair['reference'];
    $engine = $pair['engine'];
    if ($show === $name) {
        echo "--- {$name}: reference | engine\n";
        $rows = max(count($reference['trace']), count($engine['trace']));
        for ($i = 0; $i < $rows; $i++) {
            $r = $reference['trace'][$i] ?? '';
            $e = $engine['trace'][$i] ?? '';
            printf("%s %-70s | %s\n", $r === $e ? ' ' : '*', substr($r, 0, 70), $e);
        }
    }
    $check("{$name}: both stacks answer the same status", $reference['status'] === $engine['status'], "reference {$reference['status']}, engine {$engine['status']}");
    $agrees = $reference['trace'] === $engine['trace'];
    $first = 0;
    while ($first < count($reference['trace']) && ($reference['trace'][$first] ?? null) === ($engine['trace'][$first] ?? null)) {
        $first++;
    }
    $detail = sprintf('%d of %d actions agree before the first difference: reference %s, engine %s', $first, count($reference['trace']), $reference['trace'][$first] ?? '(end)', $engine['trace'][$first] ?? '(end)');
    if (isset($listed[$name])) {
        $check("{$name}: still listed as divergent ({$listed[$name]})", !$agrees, 'agrees now: drop it from DIVERGENT');
        unset($listed[$name]);
        if (!$agrees) {
            echo "       {$detail}\n";
        }
        continue;
    }
    $check("{$name}: plugins are told the same, in the same order", $agrees, $detail);
}
foreach ($listed as $name => $reason) {
    $check("DIVERGENT names a step that exists ({$name})", false);
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
