<?php
/**
 * Writes the reference's file layout under a site root as empty placeholders:
 * every wp-includes/*.php and wp-admin/includes/*.php the reference has, so a
 * plugin's `require ABSPATH . 'wp-admin/includes/image.php'` resolves to a
 * file that does nothing (the engine provides the symbols itself). Files that
 * already exist are left alone (an empty placeholder becomes the engine's loader
 * where layout/ ships one); nothing outside those two trees is written,
 * so no URL that the engine routes gets shadowed.
 *
 *   php tests/tools/site-skeleton.php <site root with public/>
 */

$root = dirname(__DIR__, 2);
$site = rtrim($argv[1] ?? '', '/');
if ($site === '' || !is_dir($site . '/public')) {
    fwrite(STDERR, "usage: site-skeleton.php <site root>\n");
    exit(1);
}
$files = json_decode((string) file_get_contents($root . '/public/minn/data/reference-files.json'), true) ?: [];
$written = 0;
// A file whose class the reference loads only when asked is the engine's loader from layout/ (it replaces an empty placeholder).
foreach ($files as $file) {
    $path = $site . '/public/' . $file;
    $loader = $root . '/public/minn/layout/' . $file;
    $stock = "<?php\n// Minn Engine placeholder for the reference's {$file}: the engine provides these symbols itself, so a plugin that requires this file gets nothing and continues.\n";
    if (file_exists($path) && !(is_file($loader) && file_get_contents($path) === $stock)) {
        continue;
    }
    @mkdir(dirname($path), 0755, true);
    is_file($loader) ? copy($loader, $path) : file_put_contents($path, $stock);
    $written++;
}
echo "wrote {$written} placeholders under {$site}/public\n";
