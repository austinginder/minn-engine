<?php
/**
 * Writes public/minn/data/entry-files.json: the request entry points a
 * WordPress webroot has on disk, by name, read from a reference copy's file
 * listing. An install lays a shape file under each name (Ops\ShapeFiles),
 * because hosts such as nginx pass a request for a .php file to PHP only
 * when that file exists. Left out: the files the install lays from layout/
 * by their own name (index.php, wp-login.php, wp-settings.php,
 * wp-admin/index.php), the config files, and wp-load.php and
 * wp-blog-header.php, which are loaders, not entry points (contracts/layout.md).
 *
 *   php tests/tools/entry-files.php <reference webroot (a site's wp-reference/)>
 */

$root = dirname(__DIR__, 2);
$reference = rtrim($argv[1] ?? '', '/');
if ($reference === '' || !is_file("{$reference}/wp-includes/version.php")) {
    fwrite(STDERR, "usage: entry-files.php <reference webroot>\n");
    exit(1);
}
$skip = ['index.php', 'wp-login.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php', 'wp-config.php', 'wp-config-sample.php'];
$files = [];
foreach (glob("{$reference}/*.php") ?: [] as $path) {
    $name = basename($path);
    if (preg_match('/^(wp-[a-z-]+|xmlrpc)\.php$/', $name) === 1 && !in_array($name, $skip, true)) {
        $files[] = $name;
    }
}
foreach (glob("{$reference}/wp-admin/*.php") ?: [] as $path) {
    if (basename($path) !== 'index.php') {
        $files[] = 'wp-admin/' . basename($path);
    }
}
sort($files);
file_put_contents("{$root}/public/minn/data/entry-files.json", json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo 'wrote ' . count($files) . " entry points to public/minn/data/entry-files.json\n";
