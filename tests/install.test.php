<?php

declare(strict_types=1);

/**
 * The swap, both ways, on a scratch webroot shaped like a WordPress install
 * (stub core files, the dev site's real wp-config.php and themes): install
 * parks core and lays the engine down, wp-cli answers through the engine,
 * eject restores the tree byte for byte and leaves nothing behind.
 */

$ROOT = dirname(__DIR__);
$ENGINE_DIR = "$ROOT/public/minn";
$SCRATCH = sys_get_temp_dir() . '/minn-install-' . getmypid();
$WEBROOT = "$SCRATCH/public";
$PARK = "$SCRATCH/wp-parked";

require_once "$ENGINE_DIR/src/Minn/Autoloader.php";
Minn\Autoloader::register();

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      " . substr($detail, 0, 500) : '') . "\n";
    }
};
$minn = static function (string $args) use ($ENGINE_DIR): array {
    exec('php ' . escapeshellarg("$ENGINE_DIR/bin/minn") . " $args 2>&1", $out, $code);
    return [implode("\n", $out), $code];
};
$tree = static function (string $dir) use (&$tree): array {
    $entries = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = "$dir/$entry";
        if (is_link($path)) {
            $entries[$entry] = 'link:' . readlink($path);
        } elseif (is_dir($path)) {
            $entries[$entry] = $tree($path);
        } else {
            $entries[$entry] = md5_file($path);
        }
    }
    ksort($entries);
    return $entries;
};
register_shutdown_function(static function () use ($SCRATCH): void {
    exec('rm -rf ' . escapeshellarg($SCRATCH));
});

echo "install suite: $WEBROOT\n";

// A WordPress-shaped webroot: stub core files, the real config and themes.
mkdir("$WEBROOT/wp-admin", 0755, true);
mkdir("$WEBROOT/wp-includes/js", 0755, true);
mkdir("$WEBROOT/wp-content/uploads", 0755, true);
symlink(realpath("$ROOT/public/wp-content/themes"), "$WEBROOT/wp-content/themes");
mkdir("$WEBROOT/wp-content/plugins");
copy("$ROOT/public/wp-config.php", "$WEBROOT/wp-config.php");
$stubs = [
    'index.php' => "<?php\nrequire __DIR__ . '/wp-blog-header.php';\n",
    'wp-blog-header.php' => "<?php\n// stub\n",
    'wp-load.php' => "<?php\n// stub\n",
    'wp-settings.php' => "<?php\n// stub settings\n",
    'wp-login.php' => "<?php\n// stub\n",
    'wp-cron.php' => "<?php\n// stub\n",
    'xmlrpc.php' => "<?php\n// stub\n",
    'license.txt' => "GPL\n",
    'readme.html' => "<html></html>\n",
    'wp-admin/index.php' => "<?php\n// stub admin\n",
    'wp-includes/version.php' => "<?php\n\$wp_version = '7.1';\n\$wp_db_version = 61833;\n",
    'wp-includes/js/wp.js' => "// stub\n",
    '.htaccess' => "# keep\n",
];
foreach ($stubs as $file => $content) {
    file_put_contents("$WEBROOT/$file", $content);
}
$before = $tree($WEBROOT);
$configBefore = md5_file("$WEBROOT/wp-config.php");

[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads a WordPress webroot', $code === 0 && str_contains($out, ': wordpress'), $out);
[$out, $code] = $minn('preflight ' . escapeshellarg($WEBROOT));
$check('preflight passes a block-theme site (GREEN or AMBER)', $code === 0 && preg_match('/Result: (GREEN|AMBER)/', $out) === 1 && str_contains($out, 'is a block theme'), $out);
$check('preflight reports wp_navigation menus', str_contains($out, 'wp_navigation menu'), $out);
$check('preflight reports no shortcodes on the engine site', str_contains($out, 'no shortcodes in content'), $out);
$check('preflight reports no third-party blocks on the engine site', str_contains($out, 'no third-party blocks in content'), $out);
$check('preflight reports no extra tables on the engine site', str_contains($out, 'no extra tables'), $out);
$check('preflight reports no extra post types on the engine site', str_contains($out, 'no extra post types'), $out);
[$out, $code] = $minn('eject ' . escapeshellarg($WEBROOT));
$check('eject refuses a WordPress webroot', $code === 1 && str_contains($out, 'not installed'), $out);

[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --park=' . escapeshellarg($PARK));
$check('install succeeds', $code === 0 && str_contains($out, 'Installed.'), $out);
$check('core files are parked', is_file("$PARK/wp-load.php") && is_dir("$PARK/wp-admin") && is_file("$PARK/wp-includes/js/wp.js") && is_file("$PARK/index.php"));
$check('the webroot has no WordPress entry points', !file_exists("$WEBROOT/wp-load.php") && !file_exists("$WEBROOT/wp-admin") && !file_exists("$WEBROOT/xmlrpc.php"));
$check('the shape files match the layout templates', md5_file("$WEBROOT/index.php") === md5_file("$ENGINE_DIR/layout/index.php") && md5_file("$WEBROOT/wp-settings.php") === md5_file("$ENGINE_DIR/layout/wp-settings.php") && md5_file("$WEBROOT/wp-cli.yml") === md5_file("$ENGINE_DIR/layout/wp-cli.yml") && md5_file("$WEBROOT/wp-includes/version.php") === md5_file("$ENGINE_DIR/layout/wp-includes/version.php"));
$check('the engine is a real copy with its sources', is_file("$WEBROOT/minn/bootstrap.php") && is_file("$WEBROOT/minn/src/Minn/Engine.php") && !is_link("$WEBROOT/minn") && is_file("$WEBROOT/minn/data/social-icons.json"));
$check('wp-config.php is untouched', md5_file("$WEBROOT/wp-config.php") === $configBefore);
$check('wp-content is untouched', file_exists("$WEBROOT/wp-content/uploads") && is_link("$WEBROOT/wp-content/themes") && is_file("$WEBROOT/.htaccess"));
$manifest = json_decode((string) @file_get_contents("$WEBROOT/minn/.install.json"), true);
$check('the manifest records the park and the moved entries', is_array($manifest) && ($manifest['park'] ?? '') === $PARK && in_array('wp-load.php', $manifest['moved'] ?? [], true) && in_array('wp-admin', $manifest['moved'] ?? [], true), json_encode($manifest));
[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads the engine', $code === 0 && str_contains($out, ': minn') && str_contains($out, 'parked at'), $out);
[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --park=' . escapeshellarg($PARK));
$check('a second install is refused', $code === 1, $out);

// The installed webroot answers wp-cli through the engine.
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp option get home 2>&1', $home, $c1);
$check('wp option get home works in the installed webroot', $c1 === 0 && implode('', $home) === 'https://minn-engine.localhost', implode("\n", $home));
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp core version 2>&1', $version, $c2);
$check('wp core version reads the shape file', $c2 === 0 && implode('', $version) === '7.1', implode("\n", $version));
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp minn info 2>&1', $info, $c3);
$check('wp minn info reports the installed engine dir', $c3 === 0 && str_contains(implode("\n", $info), 'Engine dir: ' . realpath("$WEBROOT/minn")), implode("\n", $info));

[$out, $code] = $minn('eject ' . escapeshellarg($WEBROOT));
$check('eject succeeds', $code === 0 && str_contains($out, 'Ejected.'), $out);
$check('the tree is back byte for byte', $tree($WEBROOT) === $before, json_encode(array_keys($tree($WEBROOT))) . ' vs ' . json_encode(array_keys($before)));
$check('the park is gone', !file_exists($PARK));
$check('no engine remains', !file_exists("$WEBROOT/minn") && !file_exists("$WEBROOT/wp-cli.yml"));
[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads WordPress again', $code === 0 && str_contains($out, ': wordpress'), $out);

// Docker-style wp-config: getenv_docker('ENV', 'fallback') resolved from the
// environment, still as text, nothing in the file runs.
$realConfig = (string) file_get_contents("$ROOT/public/wp-config.php");
preg_match_all('/define\s*\(\s*[\'"](DB_[A-Z_]+)[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)/', $realConfig, $dm, PREG_SET_ORDER);
$real = [];
foreach ($dm as $pair) {
    $real[$pair[1]] = stripslashes($pair[2]);
}
preg_match('/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $realConfig, $pm);
$dockerRoot = "$SCRATCH/docker";
mkdir($dockerRoot, 0755, true);
file_put_contents("{$dockerRoot}/wp-config.php", "<?php\n"
    . "define('DB_NAME', getenv_docker('MINN_INSTALL_DB_NAME', 'missing'));\n"
    . "define('DB_USER', getenv_docker('MINN_INSTALL_DB_USER', 'missing'));\n"
    . "define('DB_PASSWORD', getenv_docker('MINN_INSTALL_DB_PASSWORD', ''));\n"
    . "define('DB_HOST', getenv_docker('MINN_INSTALL_DB_HOST', '127.0.0.1'));\n"
    . "\$table_prefix = '" . ($pm[1] ?? 'wp_') . "';\n");
[$out] = $minn('preflight ' . escapeshellarg($dockerRoot));
$check(
    'docker wp-config without env uses the fallback and tries to connect',
    !str_contains($out, 'no database constants') && str_contains($out, 'database unreachable'),
    $out,
);
$env = 'MINN_INSTALL_DB_NAME=' . escapeshellarg($real['DB_NAME'] ?? '')
    . ' MINN_INSTALL_DB_USER=' . escapeshellarg($real['DB_USER'] ?? '')
    . ' MINN_INSTALL_DB_PASSWORD=' . escapeshellarg($real['DB_PASSWORD'] ?? '')
    . ' MINN_INSTALL_DB_HOST=' . escapeshellarg($real['DB_HOST'] ?? '127.0.0.1');
exec($env . ' php ' . escapeshellarg("$ENGINE_DIR/bin/minn") . ' preflight ' . escapeshellarg($dockerRoot) . ' 2>&1', $dockerOut, $dockerCode);
$dockerText = implode("\n", $dockerOut);
$check(
    'docker getenv_docker preflight connects with env values',
    $dockerCode === 0 || str_contains($dockerText, 'reachable'),
    $dockerText,
);
$check(
    'docker getenv_docker preflight does not claim the constants are missing',
    !str_contains($dockerText, 'no database constants'),
    $dockerText,
);

$scan = Minn\Content\ContentScan::shortcodes('See [eeb_protect_content]secret[/eeb_protect_content] and [[gallery]] and [metaslider id="1"] and [/close].');
$check('content scan finds opening shortcodes and skips escaped ones', $scan === ['eeb_protect_content' => 1, 'metaslider' => 1], json_encode($scan));
$blocks = Minn\Content\ContentScan::blocks('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --><!-- wp:jetpack/slideshow {"ids":[1]} /-->');
$check('content scan names core and third-party blocks', isset($blocks['core/paragraph'], $blocks['jetpack/slideshow']), json_encode($blocks));
$check('content scan flags only third-party block names', array_keys(Minn\Content\ContentScan::thirdParty($blocks)) === ['jetpack/slideshow'], json_encode($blocks));
$extra = Minn\Content\ContentScan::extraTables(['wp_posts', 'wp_options', 'wp_woocommerce_orders', 'other_table'], 'wp_');
$check('content scan extra tables drop the prefix and skip core', $extra === ['woocommerce_orders'], json_encode($extra));
$families = Minn\Content\ContentScan::tableFamilies(['actionscheduler_actions', 'wfHits', 'wfls_settings', 'itsec_logs', 'wfBlocks7']);
$check('content scan groups extra tables into families', $families === ['actionscheduler', 'itsec', 'wordfence'], json_encode($families));
$check('content scan extra types drop built-in ones', Minn\Content\ContentScan::extraTypes(['post', 'page', 'product', 'foogallery']) === ['foogallery', 'product']);
$check('content scan lists a long set with a cap', Minn\Content\ContentScan::listed(['d', 'b', 'c', 'a'], 3) === 'a, b, c and 1 more');

$manifestDir = "$SCRATCH/manifest-plugin";
mkdir($manifestDir, 0755, true);
file_put_contents("$manifestDir/minn.json", json_encode([
    'name' => 'Scan fixture',
    'extension' => 'Minn\\Ext\\Scan\\Extension',
    'shortcodes' => ['eeb_protect_content'],
    'blocks' => ['jetpack/slideshow', 'mosne/dark-palette'],
], JSON_UNESCAPED_SLASHES));
$manifest = Minn\Extension\Manifest::read($manifestDir);
$check(
    'manifest reads shortcodes and blocks',
    $manifest !== null && $manifest->shortcodes === ['eeb_protect_content'] && $manifest->blocks === ['jetpack/slideshow', 'mosne/dark-palette'],
    json_encode($manifest),
);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
