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

foreach (['functions' => 'api-probe.php', 'admin' => 'admin-probe.php', 'content' => 'content-probe.php', 'media' => 'media-probe.php', 'rest' => 'rest-probe.php', 'blocks' => 'blocks-probe.php', 'interactivity' => 'interactivity-probe.php', 'script-modules' => 'script-modules-probe.php', 'html-tag-processor' => 'html-tag-processor-probe.php', 'symbols' => 'symbols-probe.php', 'plugin-surface' => 'plugin-surface-probe.php', 'plugin-surface2' => 'plugin-surface2-probe.php', 'connectors' => 'connectors-probe.php', 'block-hooks' => 'block-hooks-probe.php', 'theme-tags' => 'theme-tags-probe.php', 'plugin-symbols' => 'plugin-symbols-probe.php', 'plugin-symbols2' => 'plugin-symbols2-probe.php', 'widgets' => 'widgets-probe.php', 'plugin-symbols3' => 'plugin-symbols3-probe.php', 'kses-rules' => 'kses-rules-probe.php', 'kses-split' => 'kses-split-probe.php', 'l10n' => 'l10n-probe.php', 'script-l10n' => 'script-l10n-probe.php', 'rest-options' => 'rest-options-probe.php', 'content-filters' => 'content-filters-probe.php', 'notices' => 'notices-probe.php', 'honesty' => 'honesty-probe.php', 'comment-fields' => 'comment-fields-probe.php', 'post-insert-filters' => 'post-insert-filters-probe.php', 'meta-caps' => 'meta-caps-probe.php', 'post-caps' => 'post-caps-probe.php', 'upload-filters' => 'upload-filters-probe.php', 'user-insert-filters' => 'user-insert-filters-probe.php', 'role-caps' => 'role-caps-probe.php', 'image-sizes' => 'image-sizes-probe.php', 'term-sanitize' => 'term-sanitize-probe.php', 'term-insert-filters' => 'term-insert-filters-probe.php', 'term-lookup' => 'term-lookup-probe.php', 'rest-term-save' => 'rest-term-save-probe.php', 'slugs' => 'slugs-probe.php', 'image-meta' => 'image-meta-probe.php', 'image-pipeline' => 'image-pipeline-probe.php', 'excerpt' => 'excerpt-probe.php', 'the-content' => 'the-content-probe.php', 'revisions' => 'revisions-probe.php', 'rest-comment-save' => 'rest-comment-save-probe.php', 'permalinks' => 'permalinks-probe.php', 'rest-media-save' => 'rest-media-save-probe.php', 'sanitize-option' => 'sanitize-option-probe.php', 'rest-settings' => 'rest-settings-probe.php', 'plugin-helpers' => 'plugin-helpers-probe.php', 'rest-statuses' => 'rest-statuses-probe.php', 'rest-themes' => 'rest-themes-probe.php', 'rest-block-types' => 'rest-block-types-probe.php', 'rest-block-renderer' => 'rest-block-renderer-probe.php', 'rest-templates-lookup' => 'rest-templates-lookup-probe.php', 'oembed' => 'oembed-probe.php', 'rest-menu-locations' => 'rest-menu-locations-probe.php', 'widget-helpers' => 'widget-helpers-probe.php', 'rest-widgets' => 'rest-widgets-probe.php', 'rest-batch' => 'rest-batch-probe.php', 'insert-defaults' => 'insert-defaults-probe.php', 'query-args' => 'query-args-probe.php', 'registry-rewrites' => 'registry-rewrites-probe.php', 'post-field' => 'post-field-probe.php', 'placeholders-a' => 'placeholders-a-probe.php', 'placeholders-admin' => 'placeholders-admin-probe.php', 'style-engine' => 'style-engine-probe.php', 'editor-styles' => 'editor-styles-probe.php', 'rest-types-edit' => 'rest-types-edit-probe.php', 'rest-plugin-types' => 'rest-plugin-types-probe.php', 'rest-fields' => 'rest-fields-probe.php', 'rest-latest-revision' => 'rest-latest-revision-probe.php', 'rest-meta' => 'rest-meta-probe.php', 'meta-api' => 'meta-api-probe.php', 'meta-registry' => 'meta-registry-probe.php', 'rest-plugin-caps' => 'rest-plugin-caps-probe.php', 'rest-term-filters' => 'rest-term-filters-probe.php', 'abilities-registry' => 'abilities-registry-probe.php', 'image-downsize' => 'image-downsize-probe.php', 'query-clauses' => 'query-clauses-probe.php', 'wp-query-sql' => 'wp-query-sql-probe.php', 'rest-post-lists' => 'rest-post-lists-probe.php', 'wp-term-query-sql' => 'wp-term-query-sql-probe.php', 'query-loop' => 'query-loop-probe.php', 'wp-user-query-sql' => 'wp-user-query-sql-probe.php', 'wp-comment-query-sql' => 'wp-comment-query-sql-probe.php', 'rest-comment-lists' => 'rest-comment-lists-probe.php', 'rest-user-lists' => 'rest-user-lists-probe.php', 'rest-term-lists' => 'rest-term-lists-probe.php', 'rest-media-lists' => 'rest-media-lists-probe.php', 'application-passwords-api' => 'application-passwords-api-probe.php', 'rest-application-password-hooks' => 'rest-application-password-hooks-probe.php', 'account-flows' => 'account-flows-probe.php', 'safety-filters' => 'safety-filters-probe.php', 'debug-notices' => 'debug-notices-probe.php', 'admin-bar' => 'admin-bar-probe.php', 'script-tags' => 'script-tags-probe.php', 'module-tags' => 'module-tags-probe.php', 'content-img-tag' => 'content-img-tag-probe.php', 'feed-tags' => 'feed-tags-probe.php', 'comment-feed-query' => 'comment-feed-query-probe.php', 'feed-templates' => 'feed-templates-probe.php', 'feed-templates-more' => 'feed-templates-more-probe.php', 'comment-pages' => 'comment-pages-probe.php', 'get-pages' => 'get-pages-probe.php', 'session-tokens' => 'session-tokens-probe.php', 'small-hooks' => 'small-hooks-probe.php', 'plugin-uninstall' => 'plugin-uninstall-probe.php', 'theme-switch' => 'theme-switch-probe.php', 'theme-json-data' => 'theme-json-data-probe.php', 'av-shortcodes' => 'av-shortcodes-probe.php', 'default-scripts' => 'default-scripts-probe.php', 'rest-menu-save' => 'rest-menu-save-probe.php', 'nav-menu-items' => 'nav-menu-items-probe.php', 'archive-links' => 'archive-links-probe.php', 'rewrite-generate' => 'rewrite-generate-probe.php', 'rewrite-rules' => 'rewrite-rules-probe.php', 'rewrite-flush' => 'rewrite-flush-probe.php', 'cpt-rules' => 'cpt-rules-probe.php', 'feed-links' => 'feed-links-probe.php', 'unzip-file' => 'unzip-file-probe.php', 'plugins-api' => 'plugins-api-probe.php', 'date-flags' => 'date-flags-probe.php', 'post-format-terms' => 'post-format-terms-probe.php', 'search-flags' => 'search-flags-probe.php', 'front-query' => 'front-query-probe.php', 'password-rehash' => 'password-rehash-probe.php', 'plugin-activation' => 'plugin-activation-probe.php', 'plugin-data' => 'plugin-data-probe.php', 'upgrader' => 'upgrader-probe.php', 'site-options' => 'site-options-probe.php', 'themes-api' => 'themes-api-probe.php', 'admin-terms' => 'admin-terms-probe.php', 'custom-header' => 'custom-header-probe.php', 'theme-symbols' => 'theme-symbols-probe.php', 'comment-walker' => 'comment-walker-probe.php', 'comments-template' => 'comments-template-probe.php', 'plugin-queue' => 'plugin-queue-probe.php', 'shortcode-run' => 'shortcode-run-probe.php', 'plugin-queue2' => 'plugin-queue2-probe.php', 'facade-stubs' => 'facade-stubs-probe.php', 'block-bindings' => 'block-bindings-probe.php', 'user-requests' => 'user-requests-probe.php', 'plugin-queue3' => 'plugin-queue3-probe.php', 'plugin-queue4' => 'plugin-queue4-probe.php', 'plugin-queue5' => 'plugin-queue5-probe.php', 'plugin-queue6' => 'plugin-queue6-probe.php', 'plugin-queue7' => 'plugin-queue7-probe.php', 'plugin-queue8' => 'plugin-queue8-probe.php', 'deprecated' => 'deprecated-probe.php', 'core-blocks' => 'core-blocks-probe.php', 'block-supports' => 'block-supports-probe.php', 'navigation-blocks' => 'navigation-blocks-probe.php', 'content-blocks' => 'content-blocks-probe.php', 'rest-validators' => 'rest-validators-probe.php', 'media-tags' => 'media-tags-probe.php', 'functions-utils' => 'functions-utils-probe.php', 'privacy' => 'privacy-probe.php', 'comment-helpers' => 'comment-helpers-probe.php', 'kses-internals' => 'kses-internals-probe.php', 'compat' => 'compat-probe.php', 'formatting-callbacks' => 'formatting-callbacks-probe.php', 'link-template-tags' => 'link-template-tags-probe.php', 'general-template-more' => 'general-template-more-probe.php', 'option-more' => 'option-more-probe.php', 'post-more' => 'post-more-probe.php', 'comment-template-more' => 'comment-template-more-probe.php', 'taxonomy-more' => 'taxonomy-more-probe.php', 'revision-more' => 'revision-more-probe.php', 'utf8' => 'utf8-probe.php', 'user-more' => 'user-more-probe.php', 'auth-cookies' => 'auth-cookies-probe.php', 'http-classes' => 'http-classes-probe.php', 'object-cache' => 'object-cache-probe.php', 'wpdb' => 'wpdb-probe.php', 'wp-theme' => 'wp-theme-probe.php', 'rest-controller' => 'rest-controller-probe.php', 'image-editor' => 'image-editor-probe.php', 'widget-classes' => 'widget-classes-probe.php', 'translation-classes' => 'translation-classes-probe.php', 'pomo-streams' => 'pomo-streams-probe.php', 'embed-classes' => 'embed-classes-probe.php', 'abilities-classes' => 'abilities-classes-probe.php', 'abilities-rest-controllers' => 'abilities-rest-controllers-probe.php', 'dependencies-classes' => 'dependencies-classes-probe.php', 'block-processor' => 'block-processor-probe.php', 'icons' => 'icons-probe.php', 'query-clause-internals' => 'query-clause-internals-probe.php', 'query-compat' => 'query-compat-probe.php', 'fonts' => 'fonts-probe.php', 'plugin-dependencies' => 'plugin-dependencies-probe.php', 'speculative-loading' => 'speculative-loading-probe.php', 'category-menu-helpers' => 'category-menu-helpers-probe.php', 'script-loader-helpers' => 'script-loader-helpers-probe.php', 'canonical-helpers' => 'canonical-helpers-probe.php', 'json-schema' => 'json-schema-probe.php', 'l10n-more' => 'l10n-more-probe.php', 'post-template-more' => 'post-template-more-probe.php', 'site-logo' => 'site-logo-probe.php', 'widgets-more' => 'widgets-more-probe.php', 'embed-helpers' => 'embed-helpers-probe.php', 'oembed-cache' => 'oembed-cache-probe.php', 'template-loading' => 'template-loading-probe.php', 'footnotes' => 'footnotes-probe.php', 'block-template-helpers' => 'block-template-helpers-probe.php', 'load-helpers' => 'load-helpers-probe.php', 'block-hooks-rest' => 'block-hooks-rest-probe.php', 'breadcrumbs' => 'breadcrumbs-probe.php', 'https-helpers' => 'https-helpers-probe.php', 'bootstrap-guards' => 'bootstrap-guards-probe.php', 'output-helpers' => 'output-helpers-probe.php', 'runtime-internals' => 'runtime-internals-probe.php', 'tabs-and-parts' => 'tabs-and-parts-probe.php', 'abilities-lookup' => 'abilities-lookup-probe.php', 'template-loader-more' => 'template-loader-more-probe.php', 'block-collections' => 'block-collections-probe.php', 'schema-and-menus' => 'schema-and-menus-probe.php', 'sitemap-classes' => 'sitemap-classes-probe.php', 'core-objects' => 'core-objects-probe.php', 'style-engine-classes' => 'style-engine-classes-probe.php', 'block-registries' => 'block-registries-probe.php', 'widget-factory' => 'widget-factory-probe.php', 'translation-files' => 'translation-files-probe.php', 'functions-remaining' => 'functions-remaining-probe.php', 'patch-security' => 'patch-security-probe.php', 'autop' => 'autop-probe.php', 'rest-api-helpers' => 'rest-api-helpers-probe.php', 'formatting-remaining' => 'formatting-remaining-probe.php', 'link-template-remaining' => 'link-template-remaining-probe.php', 'post-remaining' => 'post-remaining-probe.php', 'theme-remaining' => 'theme-remaining-probe.php', 'media-remaining' => 'media-remaining-probe.php', 'template-getters' => 'template-getters-probe.php', 'comment-remaining' => 'comment-remaining-probe.php', 'block-helpers-remaining' => 'block-helpers-remaining-probe.php', 'pluggable-remaining' => 'pluggable-remaining-probe.php', 'user-remaining' => 'user-remaining-probe.php', 'load-remaining' => 'load-remaining-probe.php', 'embed-remaining' => 'embed-remaining-probe.php', 'taxonomy-remaining' => 'taxonomy-remaining-probe.php', 'kses-remaining' => 'kses-remaining-probe.php', 'l10n-remaining' => 'l10n-remaining-probe.php', 'query-remaining' => 'query-remaining-probe.php', 'post-template-remaining' => 'post-template-remaining-probe.php', 'template-tags-remaining' => 'template-tags-remaining-probe.php', 'general-template-remaining' => 'general-template-remaining-probe.php', 'deprecated-remaining' => 'deprecated-remaining-probe.php', 'admin-bar-remaining' => 'admin-bar-remaining-probe.php', 'widgets-remaining' => 'widgets-remaining-probe.php', 'robots-template' => 'robots-template-probe.php', 'revision-remaining' => 'revision-remaining-probe.php', 'nav-menu-remaining' => 'nav-menu-remaining-probe.php', 'capabilities-remaining' => 'capabilities-remaining-probe.php', 'cache-remaining' => 'cache-remaining-probe.php', 'option-remaining' => 'option-remaining-probe.php', 'block-supports-remaining' => 'block-supports-remaining-probe.php', 'template-family' => 'template-family-probe.php', 'http-remaining' => 'http-remaining-probe.php', 'rewrite-family' => 'rewrite-family-probe.php', 'core-block-helpers' => 'core-block-helpers-probe.php', 'query-pagination' => 'query-pagination-probe.php', 'block-renderers' => 'block-renderers-probe.php', 'block-renderers-rendered' => 'block-renderers-rendered-probe.php', 'rest-meta-fields' => 'rest-meta-fields-probe.php', 'rest-search-handlers' => 'rest-search-handlers-probe.php', 'http-transports' => 'http-transports-probe.php', 'rest-registry-controllers' => 'rest-registry-controllers-probe.php', 'rest-block-controllers' => 'rest-block-controllers-probe.php', 'rest-sidebar-classes' => 'rest-sidebar-classes-probe.php', 'rest-widgets-class' => 'rest-widgets-class-probe.php', 'rest-view-config' => 'rest-view-config-probe.php'] as $fixture => $probe) {
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
        // Captured from WordPress, which points readers at wordpress.org (emoji images); the engine points them at the Minn update service.
        $value = minn_test_via_service($value);
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
