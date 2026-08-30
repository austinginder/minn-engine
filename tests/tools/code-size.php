<?php

declare(strict_types=1);

/**
 * Measures how much code ships in WordPress core and in Minn (the engine
 * plus the Minn Admin plugin) and writes contracts/code-size.json plus
 * site/minn-site/content/code-size.json (the theme page /code-size/).
 *
 *   php tests/tools/code-size.php                  latest WordPress from wordpress.org
 *   php tests/tools/code-size.php --wordpress=7.1  a named release
 *   php tests/tools/code-size.php --wordpress=/path/to/wordpress  an unpacked tree
 *   MINN_ADMIN_DIR=... to point at another Minn Admin checkout
 *
 * Counting rules (the page repeats them): PHP, JS and CSS files only; a
 * minified file is skipped when its unminified sibling sits beside it, and
 * counted when it is the only copy; lines are newline counts; bundled
 * third-party libraries are measured but reported on their own row.
 * WordPress's wp-content (the bundled themes and plugins) is not core and
 * is left out. Minn Admin is measured as its release zip ships it.
 */

$root = dirname(__DIR__, 2);
$options = getopt('', ['wordpress::']);
$adminDir = getenv('MINN_ADMIN_DIR') ?: '~/Cove/Sites/minnadmin.localhost/public/wp-content/plugins/minn-admin';
$engineDir = $root . '/public/minn';

/** @return array{0: string, 1: string, 2: string} tree, version, source */
$wordpress = static function (?string $want) use ($root): array {
    if ($want !== null && is_dir($want)) {
        return [rtrim($want, '/'), wordpressVersion($want), 'local tree ' . $want];
    }
    if ($want === null || $want === '' || $want === 'latest') {
        $offer = json_decode((string) file_get_contents('https://api.wordpress.org/core/version-check/1.7/'), true)['offers'][0] ?? null;
        if (!is_array($offer)) {
            fwrite(STDERR, "could not read the wordpress.org version check\n");
            exit(1);
        }
        $want = (string) $offer['version'];
    }
    $url = "https://downloads.wordpress.org/release/wordpress-{$want}.zip";
    $cache = "{$root}/.cache/wordpress-{$want}";
    if (!is_dir("{$cache}/wordpress")) {
        @mkdir($cache, 0755, true);
        $zip = "{$cache}/wordpress.zip";
        fwrite(STDERR, "downloading {$url}\n");
        $bytes = file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 300, 'follow_location' => 1, 'user_agent' => 'Minn Engine code-size']]));
        if ($bytes === false || strlen($bytes) < 1000) {
            fwrite(STDERR, "download failed\n");
            exit(1);
        }
        file_put_contents($zip, $bytes);
        $archive = new ZipArchive();
        if ($archive->open($zip) !== true || !$archive->extractTo($cache)) {
            fwrite(STDERR, "could not unpack {$zip}\n");
            exit(1);
        }
        $archive->close();
        unlink($zip);
    }
    return ["{$cache}/wordpress", wordpressVersion("{$cache}/wordpress"), $url];
};

function wordpressVersion(string $tree): string
{
    $source = (string) @file_get_contents("{$tree}/wp-includes/version.php");
    return preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', $source, $m) ? $m[1] : 'unknown';
}

function headerVersion(string $file, string $pattern): string
{
    return preg_match($pattern, (string) @file_get_contents($file), $m) ? $m[1] : 'unknown';
}

/**
 * Walks a tree and buckets every code file into the first matching component.
 *
 * @param list<array{id: string, label: string, note?: string, thirdParty?: bool, match: callable(string): bool}> $components relative-path matchers, first wins
 * @param callable(string): bool $skip relative paths (files or dirs) to leave out entirely
 */
function measure(string $tree, array $components, callable $skip): array
{
    $tally = [];
    foreach ($components as $component) {
        $tally[$component['id']] = ['id' => $component['id'], 'label' => $component['label'], 'note' => $component['note'] ?? '', 'thirdParty' => $component['thirdParty'] ?? false, 'files' => 0, 'lines' => 0, 'bytes' => 0, 'languages' => []];
    }
    $minifiedSkipped = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS), static function (SplFileInfo $file) use ($tree, $skip): bool {
        if ($file->isLink()) {
            return false;
        }
        return !$skip(substr($file->getPathname(), strlen($tree) + 1));
    }));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $relative = substr($file->getPathname(), strlen($tree) + 1);
        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['php', 'js', 'css'], true)) {
            continue;
        }
        if (preg_match('/\.min\.(js|css)$/', $file->getFilename()) && is_file(preg_replace('/\.min\.(js|css)$/', '.$1', $file->getPathname()))) {
            $minifiedSkipped++;
            continue;
        }
        foreach ($components as $component) {
            if (!$component['match']($relative)) {
                continue;
            }
            $bytes = (int) $file->getSize();
            $lines = substr_count((string) file_get_contents($file->getPathname()), "\n");
            $slot = &$tally[$component['id']];
            $slot['files']++;
            $slot['lines'] += $lines;
            $slot['bytes'] += $bytes;
            $slot['languages'][$extension] ??= ['files' => 0, 'lines' => 0, 'bytes' => 0];
            $slot['languages'][$extension]['files']++;
            $slot['languages'][$extension]['lines'] += $lines;
            $slot['languages'][$extension]['bytes'] += $bytes;
            unset($slot);
            break;
        }
    }
    $sum = static function (array $rows): array {
        $total = ['files' => 0, 'lines' => 0, 'bytes' => 0, 'languages' => []];
        foreach ($rows as $row) {
            $total['files'] += $row['files'];
            $total['lines'] += $row['lines'];
            $total['bytes'] += $row['bytes'];
            foreach ($row['languages'] as $language => $counts) {
                $total['languages'][$language] ??= ['files' => 0, 'lines' => 0, 'bytes' => 0];
                foreach ($counts as $key => $value) {
                    $total['languages'][$language][$key] += $value;
                }
            }
        }
        ksort($total['languages']);
        return $total;
    };
    $rows = array_values(array_filter($tally, static fn (array $row) => $row['files'] > 0));
    foreach ($rows as &$row) {
        ksort($row['languages']);
    }
    unset($row);
    return [
        'components' => $rows,
        'totals' => $sum($rows),
        'own' => $sum(array_filter($rows, static fn (array $row) => !$row['thirdParty'])),
        'thirdParty' => $sum(array_filter($rows, static fn (array $row) => $row['thirdParty'])),
        'minifiedSkipped' => $minifiedSkipped,
    ];
}

$under = static fn (string ...$prefixes): Closure => static function (string $path) use ($prefixes): bool {
    foreach ($prefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }
    return false;
};
$among = static fn (string ...$files): Closure => static fn (string $path): bool => in_array(preg_replace('/\.min\.(js|css)$/', '.$1', $path), $files, true);
$either = static fn (Closure ...$tests): Closure => static function (string $path) use ($tests): bool {
    foreach ($tests as $test) {
        if ($test($path)) {
            return true;
        }
    }
    return false;
};
$topLevel = static fn (string $path): bool => !str_contains($path, '/');

[$wpTree, $wpVersion, $wpSource] = $wordpress($options['wordpress'] ?? null);
fwrite(STDERR, "WordPress {$wpVersion} at {$wpTree}\n");
$wordpressStack = measure($wpTree, [
    [
        'id' => 'libraries',
        'label' => 'Bundled libraries',
        'note' => 'jQuery, TinyMCE, CodeMirror, MediaElement, Plupload, Backbone, Underscore, React and lodash (js/dist/vendor), PHPMailer, Requests, SimplePie, sodium_compat, getID3, IXR, Text_Diff, php-compat',
        'thirdParty' => true,
        'match' => $either(
            $under('wp-includes/js/jquery', 'wp-includes/js/tinymce', 'wp-includes/js/codemirror', 'wp-includes/js/mediaelement', 'wp-includes/js/plupload', 'wp-includes/js/swfupload', 'wp-includes/js/imgareaselect', 'wp-includes/js/jcrop', 'wp-includes/js/thickbox', 'wp-includes/js/crop', 'wp-includes/js/dist/vendor', 'wp-includes/PHPMailer', 'wp-includes/Requests', 'wp-includes/SimplePie', 'wp-includes/sodium_compat', 'wp-includes/ID3', 'wp-includes/IXR', 'wp-includes/Text', 'wp-includes/php-compat'),
            $among('wp-includes/js/backbone.js', 'wp-includes/js/underscore.js', 'wp-includes/js/json2.js', 'wp-includes/js/swfobject.js', 'wp-includes/js/hoverIntent.js', 'wp-includes/js/hoverintent-js.js', 'wp-includes/js/twemoji.js', 'wp-includes/js/zxcvbn.js', 'wp-includes/js/zxcvbn-async.js', 'wp-includes/js/masonry.js', 'wp-includes/js/imagesloaded.js', 'wp-includes/js/clipboard.js', 'wp-includes/js/tw-sack.js'),
        ),
    ],
    ['id' => 'editor-packages', 'label' => 'Block editor packages', 'note' => 'wp-includes/js/dist: the built @wordpress/* packages', 'match' => $under('wp-includes/js/dist')],
    ['id' => 'blocks', 'label' => 'Core blocks', 'note' => 'wp-includes/blocks', 'match' => $under('wp-includes/blocks')],
    ['id' => 'wp-admin', 'label' => 'Admin screens', 'note' => 'wp-admin', 'match' => $under('wp-admin')],
    ['id' => 'wp-includes', 'label' => 'Runtime', 'note' => 'wp-includes: functions, classes, REST, the rest of the JS and CSS', 'match' => $under('wp-includes')],
    ['id' => 'root', 'label' => 'Root files', 'note' => 'index.php, wp-load.php, wp-settings.php, wp-login.php, xmlrpc.php, ...', 'match' => $topLevel],
], $under('wp-content'));

$minnVersion = headerVersion("{$engineDir}/bootstrap.php", '/MINN_ENGINE_VERSION\',\s*\'([^\']+)\'/');
$adminVersion = headerVersion("{$adminDir}/minn-admin.php", '/^\s*\*\s*Version:\s*([0-9.]+)/m');
fwrite(STDERR, "Minn {$minnVersion} at {$engineDir}, Minn Admin {$adminVersion} at {$adminDir}\n");
$engine = measure($engineDir, [
    ['id' => 'libraries', 'label' => 'Bundled libraries', 'note' => 'jQuery and jQuery Migrate (assets/vendor), for plugins that expect them', 'thirdParty' => true, 'match' => $under('assets/vendor')],
    ['id' => 'engine', 'label' => 'The engine', 'note' => 'src/Minn: request handling, content, REST, auth, blocks, theme, media, the runtime', 'match' => $under('src/Minn')],
    ['id' => 'facade', 'label' => 'The WordPress facade', 'note' => 'wp-api: the functions and classes plugin code calls, mapped onto the engine', 'match' => $under('wp-api')],
    ['id' => 'assets', 'label' => 'Front-end runtime', 'note' => 'assets: the interactivity runtime, block view scripts, the block stylesheet', 'match' => $under('assets')],
    ['id' => 'boot', 'label' => 'Boot, CLI, layout', 'note' => 'bootstrap.php, cli.php, bin/, layout/', 'match' => static fn (string $path): bool => true],
], $under('admin', 'data'));
$admin = measure($adminDir, [
    ['id' => 'admin-php', 'label' => 'Minn Admin: adapters and REST', 'note' => 'includes: the WordPress adapters and the minn-admin/v1 routes', 'match' => $under('includes')],
    ['id' => 'admin-app', 'label' => 'Minn Admin: the app', 'note' => 'assets: the admin SPA, its stylesheet', 'match' => $under('assets')],
    ['id' => 'admin-boot', 'label' => 'Minn Admin: plugin file and docs', 'note' => 'minn-admin.php, docs', 'match' => static fn (string $path): bool => true],
], $either($under('tests', 'bin', 'dist', 'languages', '.github', '.wp-playground', '.git', 'node_modules'), static fn (string $path): bool => str_starts_with($path, '.')));

$merge = static function (array $a, array $b): array {
    $rows = [...$a['components'], ...$b['components']];
    $sum = static function (array $rows): array {
        $total = ['files' => 0, 'lines' => 0, 'bytes' => 0, 'languages' => []];
        foreach ($rows as $row) {
            $total['files'] += $row['files'];
            $total['lines'] += $row['lines'];
            $total['bytes'] += $row['bytes'];
            foreach ($row['languages'] as $language => $counts) {
                $total['languages'][$language] ??= ['files' => 0, 'lines' => 0, 'bytes' => 0];
                foreach ($counts as $key => $value) {
                    $total['languages'][$language][$key] += $value;
                }
            }
        }
        ksort($total['languages']);
        return $total;
    };
    return [
        'components' => $rows,
        'totals' => $sum($rows),
        'own' => $sum(array_filter($rows, static fn (array $row) => !$row['thirdParty'])),
        'thirdParty' => $sum(array_filter($rows, static fn (array $row) => $row['thirdParty'])),
        'minifiedSkipped' => $a['minifiedSkipped'] + $b['minifiedSkipped'],
    ];
};

$report = [
    'measured' => gmdate('Y-m-d'),
    'method' => [
        'languages' => 'PHP, JavaScript and CSS files',
        'lines' => 'newline counts, blank lines and comments included',
        'minified' => 'a minified file is skipped when the unminified copy sits beside it, and counted when it is the only copy',
        'thirdParty' => 'bundled third-party libraries are measured and shown on their own row',
        'wordpressExcludes' => 'wp-content (the bundled themes and plugins are not the runtime)',
        'minnExcludes' => 'the engine\'s data files and Minn Admin\'s tests, translation sources and release tooling (what its release zip leaves out)',
        'refresh' => 'php tests/tools/code-size.php',
    ],
    'stacks' => [
        ['id' => 'wordpress', 'label' => 'WordPress', 'version' => $wpVersion, 'source' => $wpSource, 'describe' => 'The admin screens, the runtime and the root files of the release zip'] + $wordpressStack,
        ['id' => 'minn', 'label' => 'Minn', 'version' => $minnVersion, 'adminVersion' => $adminVersion, 'source' => 'minn-run/minn-engine public/minn and the minn-admin plugin', 'describe' => 'The engine deploy unit (public/minn) and the Minn Admin plugin as it ships'] + $merge($engine, $admin),
    ],
];
file_put_contents("{$root}/contracts/code-size.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$themeReport = "{$root}/site/minn-site/content/code-size.json";
if (is_dir(dirname($themeReport))) {
    file_put_contents($themeReport, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
foreach ($report['stacks'] as $stack) {
    printf("%-10s %-8s %9s files %12s lines %14s bytes (own %s lines, libraries %s lines)\n", $stack['label'], $stack['version'], number_format($stack['totals']['files']), number_format($stack['totals']['lines']), number_format($stack['totals']['bytes']), number_format($stack['own']['lines']), number_format($stack['thirdParty']['lines']));
    foreach ($stack['components'] as $row) {
        printf("    %-38s %7s files %11s lines\n", $row['label'], number_format($row['files']), number_format($row['lines']));
    }
}
echo "wrote contracts/code-size.json\n";
if (is_file($themeReport ?? '')) {
    echo "wrote site/minn-site/content/code-size.json\n";
}
