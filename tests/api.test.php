<?php
/**
 * Runtime API suite: runs tests/tools/api-probe.php on the engine's facade
 * (booted against the dev database) and diffs the transcript row by row
 * against the one captured from the reference. Signatures of every facade
 * function are checked against the interface inventory as well.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/lib.php'; // pins the reference theme the fixtures were captured under
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

foreach (['functions' => 'api-probe.php', 'admin' => 'admin-probe.php', 'content' => 'content-probe.php', 'media' => 'media-probe.php', 'rest' => 'rest-probe.php', 'blocks' => 'blocks-probe.php', 'interactivity' => 'interactivity-probe.php', 'script-modules' => 'script-modules-probe.php', 'html-tag-processor' => 'html-tag-processor-probe.php', 'symbols' => 'symbols-probe.php', 'plugin-surface' => 'plugin-surface-probe.php', 'plugin-surface2' => 'plugin-surface2-probe.php', 'connectors' => 'connectors-probe.php', 'block-hooks' => 'block-hooks-probe.php', 'theme-tags' => 'theme-tags-probe.php', 'plugin-symbols' => 'plugin-symbols-probe.php', 'plugin-symbols2' => 'plugin-symbols2-probe.php', 'widgets' => 'widgets-probe.php', 'plugin-symbols3' => 'plugin-symbols3-probe.php', 'kses-rules' => 'kses-rules-probe.php', 'kses-split' => 'kses-split-probe.php', 'l10n' => 'l10n-probe.php', 'script-l10n' => 'script-l10n-probe.php', 'rest-options' => 'rest-options-probe.php', 'content-filters' => 'content-filters-probe.php', 'notices' => 'notices-probe.php', 'honesty' => 'honesty-probe.php', 'comment-fields' => 'comment-fields-probe.php', 'post-insert-filters' => 'post-insert-filters-probe.php', 'meta-caps' => 'meta-caps-probe.php', 'post-caps' => 'post-caps-probe.php', 'upload-filters' => 'upload-filters-probe.php', 'user-insert-filters' => 'user-insert-filters-probe.php', 'role-caps' => 'role-caps-probe.php', 'image-sizes' => 'image-sizes-probe.php', 'term-sanitize' => 'term-sanitize-probe.php', 'term-insert-filters' => 'term-insert-filters-probe.php', 'term-lookup' => 'term-lookup-probe.php', 'rest-term-save' => 'rest-term-save-probe.php', 'slugs' => 'slugs-probe.php', 'image-meta' => 'image-meta-probe.php', 'image-pipeline' => 'image-pipeline-probe.php', 'excerpt' => 'excerpt-probe.php', 'the-content' => 'the-content-probe.php', 'revisions' => 'revisions-probe.php', 'rest-comment-save' => 'rest-comment-save-probe.php', 'permalinks' => 'permalinks-probe.php', 'rest-media-save' => 'rest-media-save-probe.php', 'sanitize-option' => 'sanitize-option-probe.php', 'rest-settings' => 'rest-settings-probe.php', 'plugin-helpers' => 'plugin-helpers-probe.php', 'rest-statuses' => 'rest-statuses-probe.php', 'rest-themes' => 'rest-themes-probe.php', 'rest-block-types' => 'rest-block-types-probe.php', 'rest-block-renderer' => 'rest-block-renderer-probe.php', 'rest-templates-lookup' => 'rest-templates-lookup-probe.php', 'oembed' => 'oembed-probe.php', 'rest-menu-locations' => 'rest-menu-locations-probe.php', 'widget-helpers' => 'widget-helpers-probe.php', 'rest-widgets' => 'rest-widgets-probe.php', 'rest-batch' => 'rest-batch-probe.php', 'insert-defaults' => 'insert-defaults-probe.php', 'query-args' => 'query-args-probe.php', 'registry-rewrites' => 'registry-rewrites-probe.php', 'post-field' => 'post-field-probe.php', 'placeholders-a' => 'placeholders-a-probe.php', 'placeholders-admin' => 'placeholders-admin-probe.php', 'style-engine' => 'style-engine-probe.php', 'editor-styles' => 'editor-styles-probe.php', 'rest-types-edit' => 'rest-types-edit-probe.php', 'rest-plugin-types' => 'rest-plugin-types-probe.php', 'rest-fields' => 'rest-fields-probe.php', 'rest-latest-revision' => 'rest-latest-revision-probe.php', 'rest-meta' => 'rest-meta-probe.php', 'meta-api' => 'meta-api-probe.php', 'meta-registry' => 'meta-registry-probe.php', 'rest-plugin-caps' => 'rest-plugin-caps-probe.php', 'rest-term-filters' => 'rest-term-filters-probe.php', 'abilities-registry' => 'abilities-registry-probe.php', 'image-downsize' => 'image-downsize-probe.php', 'query-clauses' => 'query-clauses-probe.php', 'wp-query-sql' => 'wp-query-sql-probe.php', 'rest-post-lists' => 'rest-post-lists-probe.php', 'wp-term-query-sql' => 'wp-term-query-sql-probe.php', 'query-loop' => 'query-loop-probe.php', 'wp-user-query-sql' => 'wp-user-query-sql-probe.php', 'wp-comment-query-sql' => 'wp-comment-query-sql-probe.php', 'rest-comment-lists' => 'rest-comment-lists-probe.php', 'rest-user-lists' => 'rest-user-lists-probe.php', 'rest-term-lists' => 'rest-term-lists-probe.php', 'rest-media-lists' => 'rest-media-lists-probe.php', 'application-passwords-api' => 'application-passwords-api-probe.php', 'rest-application-password-hooks' => 'rest-application-password-hooks-probe.php', 'account-flows' => 'account-flows-probe.php', 'safety-filters' => 'safety-filters-probe.php', 'debug-notices' => 'debug-notices-probe.php', 'admin-bar' => 'admin-bar-probe.php', 'script-tags' => 'script-tags-probe.php', 'module-tags' => 'module-tags-probe.php', 'content-img-tag' => 'content-img-tag-probe.php', 'feed-tags' => 'feed-tags-probe.php', 'comment-feed-query' => 'comment-feed-query-probe.php', 'feed-templates' => 'feed-templates-probe.php', 'feed-templates-more' => 'feed-templates-more-probe.php', 'comment-pages' => 'comment-pages-probe.php', 'get-pages' => 'get-pages-probe.php', 'session-tokens' => 'session-tokens-probe.php'] as $fixture => $probe) {
    $expected = json_decode(file_get_contents($root . "/contracts/fixtures/api/{$fixture}.json"), true);
    $out = shell_exec('php ' . escapeshellarg($root . '/tests/tools/run-api-probe.php') . ' ' . escapeshellarg($probe) . ' 2>/dev/null');
    $actual = json_decode((string) $out, true);
    if (!is_array($actual)) {
        echo "  FAIL {$probe} did not produce JSON:\n" . substr((string) $out, 0, 2000) . "\n";
        exit(1);
    }
    $check("{$probe}: row count", count($actual) === count($expected), count($actual) . ' vs ' . count($expected));
    $byLabel = [];
    foreach ($actual as $row) {
        $byLabel[$row[0]] = $row[1];
    }
    foreach ($expected as $row) {
        [$label, $value] = $row;
        $have = array_key_exists($label, $byLabel);
        $check("{$fixture}: {$label}", $have && json_encode($byLabel[$label]) === json_encode($value), ($have ? json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES) : 'missing') . ' vs ' . json_encode($value, JSON_UNESCAPED_SLASHES));
    }
}

require $root . '/tests/tools/engine-runtime.php';
$inventory = json_decode(file_get_contents($root . '/contracts/api/functions.json'), true);
foreach (glob($root . '/public/minn/wp-api/*.php') as $file) {
    preg_match_all('/^function\s+(\w+)\s*\(/m', file_get_contents($file), $m);
    foreach ($m[1] as $name) {
        if (str_starts_with($name, '_minn_')) {
            continue;
        }
        $spec = $inventory[$name] ?? null;
        if ($spec === null) {
            $check("{$name}: in inventory", false, basename($file));
            continue;
        }
        $f = new ReflectionFunction($name);
        $ours = [];
        foreach ($f->getParameters() as $p) {
            $ours[] = ($p->isPassedByReference() ? '&' : '') . ($p->isVariadic() ? '...' : '') . $p->getName() . ($p->isDefaultValueAvailable() ? '=' . json_encode($p->isDefaultValueConstant() ? ['const' => $p->getDefaultValueConstantName()] : $p->getDefaultValue()) : '');
        }
        $theirs = [];
        foreach ($spec['params'] as $p) {
            $theirs[] = (!empty($p['byRef']) ? '&' : '') . (!empty($p['variadic']) ? '...' : '') . $p['name'] . (array_key_exists('default', $p) ? '=' . json_encode($p['default']) : '');
        }
        $check("{$name}: signature", $ours === $theirs, implode(', ', $ours) . ' vs ' . implode(', ', $theirs));
    }
}

// A class the reference lets plugins hang properties on must allow them here too,
// or PHP warns (and, from 9, refuses) where WordPress is silent: WP-CLI's own
// post and comment lists set $post->url.
$dynamic = json_decode((string) file_get_contents($root . '/public/minn/data/dynamic-properties.json'), true)['classes'] ?? [];
$lacking = [];
foreach ($dynamic as $class) {
    if (class_exists($class) && (new ReflectionClass($class))->getAttributes('AllowDynamicProperties') === []) {
        $lacking[] = $class;
    }
}
$check('classes the reference opens to dynamic properties are open here', $lacking === [], implode(', ', $lacking));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
