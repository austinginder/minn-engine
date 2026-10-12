<?php
/**
 * How much plugins lean on each of the reference's hooks: the most
 * installed plugins of the lab catalogue (see /dev-minn-engine), each
 * downloaded, scanned for the hooks it subscribes to (add_action and
 * add_filter with a literal name) and thrown away. A plugin's name for a
 * dynamic hook (pre_option_blogname) counts toward the inventory's pattern
 * (pre_option_{*}). Writes contracts/api/hook-weights.json: per hook, how
 * many of the scanned plugins hook it and their installs between them.
 * Only names and counts are kept.
 *
 * php tests/tools/hook-weights.php [--top=500] [--lab=<dir>]
 *
 * Each plugin's names are kept between runs in the system temp directory,
 * so a run that stops half way picks up where it was.
 */

$options = getopt('', ['top:', 'lab:']);
$top = max(1, (int) ($options['top'] ?? 500));
$lab = rtrim((string) ($options['lab'] ?? getenv('HOME') . '/Documents/minn-lab'), '/');
$root = dirname(__DIR__, 2);
$cacheFile = sys_get_temp_dir() . '/minn-hook-weights-cache.json';

$installs = json_decode((string) @file_get_contents("{$lab}/plugins-installs.json"), true);
if (!is_array($installs)) {
    fwrite(STDERR, "No catalogue at {$lab}/plugins-installs.json\n");
    exit(1);
}
$urls = [];
foreach (file("{$lab}/plugins.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) === 2) {
        $urls[$parts[0]] = $parts[1];
    }
}
usort($installs, static fn (array $a, array $b): int => $b['installs'] <=> $a['installs']);
$installs = array_slice($installs, 0, $top);

// The inventory's names, and what a plugin's name stands for among them.
require __DIR__ . '/hook-names.php';
$resolve = minn_hook_resolver((array) json_decode((string) file_get_contents("{$root}/contracts/api/hooks.json"), true));

$cache = json_decode((string) @file_get_contents($cacheFile), true) ?: [];
$scanned = 0;
foreach ($installs as $row) {
    $slug = (string) $row['slug'];
    if (isset($cache[$slug])) {
        continue;
    }
    // The catalogue lists a download for its first plugins; the rest are fetched at their latest release.
    $url = $urls[$slug] ?? 'https://downloads.wordpress.org/plugin/' . rawurlencode($slug) . '.latest-stable.zip';
    $zip = tempnam(sys_get_temp_dir(), 'minn-hw-');
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 60]]));
    if ($body === false || file_put_contents($zip, $body) === false) {
        @unlink($zip);
        fwrite(STDERR, "  {$slug}: download failed\n");
        continue;
    }
    $names = [];
    $archive = new ZipArchive();
    if ($archive->open($zip) === true) {
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $entry = (string) $archive->getNameIndex($i);
            if (!str_ends_with(strtolower($entry), '.php')) {
                continue;
            }
            $source = (string) $archive->getFromIndex($i);
            if (preg_match_all('/\badd_(?:action|filter)\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.){1,120}?)\1\s*,/', $source, $matches)) {
                foreach ($matches[2] as $index => $name) {
                    // A double-quoted name's interpolations are its dynamic part.
                    $name = $matches[1][$index] === '"' ? (string) preg_replace('/\{\$[^}]+\}|\$[a-zA-Z_][a-zA-Z0-9_]*(?:->[a-zA-Z_][a-zA-Z0-9_]*|\[[^\]]*\])*/', '{*}', $name) : $name;
                    $names[$name] = true;
                }
            }
        }
        $archive->close();
    }
    @unlink($zip);
    $cache[$slug] = array_keys($names);
    file_put_contents($cacheFile, json_encode($cache));
    $scanned++;
    fwrite(STDERR, sprintf("  %-40s %5d names\n", $slug, count($names)));
}

$hooks = [];
$plugins = 0;
$total = 0;
foreach ($installs as $row) {
    $slug = (string) $row['slug'];
    if (!isset($cache[$slug])) {
        continue;
    }
    $plugins++;
    $total += (int) $row['installs'];
    $mine = [];
    foreach ($cache[$slug] as $name) {
        $hook = $resolve((string) $name);
        if ($hook !== null) {
            $mine[$hook] = true;
        }
    }
    foreach (array_keys($mine) as $hook) {
        $hooks[$hook] ??= [0, 0];
        $hooks[$hook][0]++;
        $hooks[$hook][1] += (int) $row['installs'];
    }
}
uasort($hooks, static fn (array $a, array $b): int => $b[1] <=> $a[1] ?: $b[0] <=> $a[0]);
$out = [
    'meta' => ['plugins' => $plugins, 'installs' => $total, 'top' => $top, 'columns' => ['plugins', 'installs']],
    'hooks' => $hooks,
];
file_put_contents("{$root}/contracts/api/hook-weights.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("%d plugins scanned (%d new), %d of the reference's hooks hooked\n", $plugins, $scanned, count($hooks));
