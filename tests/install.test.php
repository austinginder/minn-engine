<?php

declare(strict_types=1);

/**
 * The swap, both ways, on a scratch webroot shaped like a WordPress install
 * (stub core files, the dev site's real wp-config.php and themes): install
 * parks core and lays the engine down, wp-cli answers through the engine,
 * eject restores the tree byte for byte and leaves nothing behind.
 */

require_once __DIR__ . '/lib.php';
$ROOT = dirname(__DIR__);
// MINN_TEST_ENGINE_DIR runs the suite from an unpacked release (scripts/build-release.php) instead of the checkout.
$ENGINE_DIR = getenv( 'MINN_TEST_ENGINE_DIR' ) ?: "$ROOT/public/minn";
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
symlink(realpath(minn_test_site_root() . '/public/wp-content/themes'), "$WEBROOT/wp-content/themes");
mkdir("$WEBROOT/wp-content/plugins");
copy(minn_test_site_root() . '/public/wp-config.php', "$WEBROOT/wp-config.php");
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
// A second copy for the curl installer, at the end.
mkdir("$SCRATCH/sh");
exec('cp -a ' . escapeshellarg($WEBROOT) . ' ' . escapeshellarg("$SCRATCH/sh/public"));

[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads a WordPress webroot', $code === 0 && str_contains($out, ': wordpress'), $out);

// Minn Admin switches a WordPress site from a web request, where there is no STDOUT: the installer's
// run() hands back the exit code and the report and prints nothing, as bin/minn would have printed it.
$runner = 'require ' . var_export("$ENGINE_DIR/src/Minn/Autoloader.php", true) . '; Minn\\Autoloader::register(); ob_start(); $r = Minn\\Cli\\Installer::run(["status", ' . var_export($WEBROOT, true) . '], ' . var_export($ENGINE_DIR, true) . '); $printed = ob_get_clean(); echo json_encode([$r, $printed]);';
exec('php -r ' . escapeshellarg($runner) . ' 2>&1', $runOut, $runCode);
[$ran, $printed] = json_decode(implode("\n", $runOut), true) ?: [null, null];
$check('the installer hands its report to a caller in the same process, printing nothing', $runCode === 0 && $printed === '' && ($ran['code'] ?? null) === 0 && implode("\n", $ran['lines'] ?? []) === trim($out), implode("\n", $runOut));
[$out, $code] = $minn('preflight ' . escapeshellarg($WEBROOT));
$check('preflight passes a block-theme site (GREEN or AMBER)', $code === 0 && preg_match('/Result: (GREEN|AMBER)/', $out) === 1 && str_contains($out, 'is a block theme'), $out);
$check('preflight no longer treats classic themes as unrenderable', !str_contains($out, 'renders block themes only'), $out);
$check('preflight reports wp_navigation menus', str_contains($out, 'wp_navigation menu'), $out);
$check('preflight treats classic nav_menu as readable', !str_contains($out, 'the engine reads wp_navigation posts only'), $out);
$check('preflight reports no shortcodes on the engine site', str_contains($out, 'no shortcodes in content'), $out);
$check('preflight reports no third-party blocks on the engine site', str_contains($out, 'no third-party blocks in content'), $out);
$check('preflight reports no extra tables on the engine site', str_contains($out, 'no extra tables'), $out);
$check('preflight reports no extra post types on the engine site', str_contains($out, 'no extra post types'), $out);
[$out, $code] = $minn('eject ' . escapeshellarg($WEBROOT));
$check('eject refuses a WordPress webroot', $code === 1 && str_contains($out, 'not installed'), $out);

[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --help');
$check('install --help prints the usage and installs nothing (the field test installed with it)', $code === 0 && str_starts_with($out, 'Usage: minn') && $tree($WEBROOT) === $before, $out);
[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --yes');
$check('an option install does not read refuses it before anything changes', $code === 1 && str_contains($out, 'does not take --yes') && $tree($WEBROOT) === $before, $out);

// Security sweep 2026-11-12 (2.1): the default park sits beside the webroot, so where that folder is served (an
// addon domain inside public_html) the parked core would answer on the web. Install refuses that default.
file_put_contents("$SCRATCH/index.php", "<?php // the main site's front controller\n");
[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT));
@unlink("$SCRATCH/index.php");
$check('the default park beside a served folder is refused with the way out, and nothing changes', $code === 1 && str_contains($out, 'looks served (it holds index.php)') && str_contains($out, '--park=<dir>') && $tree($WEBROOT) === $before && !file_exists("$SCRATCH/wp-parked"), $out);
[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --park=/dev/null/wp-parked');
$check('a park folder that cannot be made is refused with the way out, and no PHP warning', $code === 1 && str_contains($out, 'cannot create /dev/null/wp-parked') && str_contains($out, '--park=<dir>') && !str_contains($out, 'Warning') && $tree($WEBROOT) === $before, $out);
$readConfig = new ReflectionMethod(Minn\Cli\Preflight::class, 'readConfig');
file_put_contents("$SCRATCH/home-config.php", "<?php\ndefine( 'DB_NAME', 'x' );\ndefine( 'WP_HOME', 'https://example.org' );\n");
file_put_contents("$SCRATCH/home-expr-config.php", "<?php\ndefine( 'DB_NAME', 'x' );\ndefine( 'WP_HOME', 'https://' . \$_SERVER['HTTP_HOST'] );\n");
$homeConfig = $readConfig->invoke(null, "$SCRATCH/home-config.php");
$homeExpr = $readConfig->invoke(null, "$SCRATCH/home-expr-config.php");
$check('preflight reads WP_HOME from wp-config.php, and knows when it is set in a way it cannot read', ($homeConfig['WP_HOME'] ?? '') === 'https://example.org' && !isset($homeExpr['WP_HOME']) && $homeExpr['homeInConfig'] === true, json_encode([$homeConfig, $homeExpr]));

// A moved content or plugin folder is refused until Minn supports one (decision 2A, 2026-10-21).
$moved = new ReflectionMethod(Minn\Cli\Preflight::class, 'moved');
$movedCases = array(
    "<?php define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' ); define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );" => array(),
    "<?php define( \"WP_CONTENT_DIR\", dirname( __FILE__ ) . \"/wp-content\" );" => array(),
    "<?php // define( 'WP_CONTENT_DIR', '/srv/app' );\n/* define( 'WP_PLUGIN_DIR', '/srv/plugins' ); */" => array(),
    "<?php define( 'WP_CONTENT_DIR', __DIR__ . '/app' );" => array('WP_CONTENT_DIR'),
    "<?php Config::define( 'WP_CONTENT_DIR', \$webroot_dir . Config::get( 'CONTENT_DIR' ) );" => array('WP_CONTENT_DIR'),
    "<?php define( 'WPMU_PLUGIN_DIR', '/srv/mu' ); define( 'WP_PLUGIN_DIR', '/srv/plugins' );" => array('WPMU_PLUGIN_DIR', 'WP_PLUGIN_DIR'),
);
$movedSeen = array_map(static fn (string $source): array => $moved->invoke(null, $source), array_keys($movedCases));
$check('preflight finds a moved content or plugin folder (Bedrock\'s Config::define too), not a default spelling or a comment', $movedSeen === array_values($movedCases), json_encode($movedSeen));
$movedRoot = "$SCRATCH/moved";
mkdir("$movedRoot/wp-content", 0755, true);
file_put_contents("$movedRoot/wp-config.php", "<?php\ndefine( 'DB_NAME', 'x' );\ndefine( 'WP_CONTENT_DIR', __DIR__ . '/app' );\n");
$movedBefore = $tree($movedRoot);
[$out, $code] = $minn('install ' . escapeshellarg($movedRoot));
$check('install refuses a site whose wp-config.php moves the content folder, and changes nothing', $code === 1 && str_contains($out, '[RED] wp-config.php moves a folder (WP_CONTENT_DIR)') && str_contains($out, 'Install refused (RED)') && $tree($movedRoot) === $movedBefore, $out);

[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --park=' . escapeshellarg($PARK));
$check('install succeeds', $code === 0 && str_contains($out, 'Installed.'), $out);
$check('core files are parked', is_file("$PARK/wp-load.php") && is_dir("$PARK/wp-admin") && is_file("$PARK/wp-includes/js/wp.js") && is_file("$PARK/index.php"));
$check('the webroot has no WordPress code: no loaders, no wp-admin assets', !file_exists("$WEBROOT/wp-load.php") && !file_exists("$WEBROOT/wp-blog-header.php") && !file_exists("$WEBROOT/wp-admin/css"));
// nginx runs a .php request only when the file exists, so each entry point is a shape file that boots the engine.
$entries = json_decode((string) file_get_contents("$ENGINE_DIR/data/entry-files.json"), true) ?: [];
$entryTemplate = (string) file_get_contents("$ENGINE_DIR/layout/entry.php");
$adminTemplate = (string) file_get_contents("$ENGINE_DIR/layout/wp-admin/index.php");
$wrong = array_filter($entries, static fn (string $f): bool => @file_get_contents("$WEBROOT/$f") !== (str_starts_with($f, 'wp-admin/') ? $adminTemplate : $entryTemplate));
$check('every entry point is laid from its template (the parked xmlrpc.php and wp-cron.php, and the ones the site lacked)', count($entries) > 90 && $wrong === [] && in_array('wp-comments-post.php', $entries, true) && in_array('wp-admin/admin-ajax.php', $entries, true), json_encode(array_values($wrong)));
$check('the entry list leaves out the loaders and the files laid by their own name', array_intersect($entries, ['wp-load.php', 'wp-blog-header.php', 'index.php', 'wp-login.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-config.php']) === []);
$check('the shape files match the layout templates', md5_file("$WEBROOT/index.php") === md5_file("$ENGINE_DIR/layout/index.php") && md5_file("$WEBROOT/wp-settings.php") === md5_file("$ENGINE_DIR/layout/wp-settings.php") && md5_file("$WEBROOT/wp-cli.yml") === md5_file("$ENGINE_DIR/layout/wp-cli.yml") && md5_file("$WEBROOT/wp-includes/version.php") === md5_file("$ENGINE_DIR/layout/wp-includes/version.php") && md5_file("$WEBROOT/wp-admin/index.php") === md5_file("$ENGINE_DIR/layout/wp-admin/index.php"));
$check('require placeholders exist so plugins can include wp-admin files', is_file("$WEBROOT/wp-admin/includes/plugin.php") && is_file("$WEBROOT/wp-includes/class-phpmailer.php"));
$check('the engine is a real copy with its sources', is_file("$WEBROOT/minn/bootstrap.php") && is_file("$WEBROOT/minn/src/Minn/Engine.php") && !is_link("$WEBROOT/minn") && is_file("$WEBROOT/minn/data/social-icons.json"));
$check(
    'static assets live inside minn/, not extra webroot folders',
    is_file("$WEBROOT/minn/admin/assets/js/app.js")
        && is_file("$WEBROOT/minn/assets/blocks.css")
        && is_file("$WEBROOT/minn/assets/vendor/jquery/jquery.min.js")
        && !file_exists("$WEBROOT/minn-admin-asset")
        && !file_exists("$WEBROOT/minn-engine"),
);
$check('wp-config.php is untouched', md5_file("$WEBROOT/wp-config.php") === $configBefore);
$check('wp-content is untouched', file_exists("$WEBROOT/wp-content/uploads") && is_link("$WEBROOT/wp-content/themes") && is_file("$WEBROOT/.htaccess"));
$record = (string) @file_get_contents("$WEBROOT/.minn-install.php");
$manifest = json_decode((string) substr($record, (int) strpos($record, "?>\n") + 3), true);
$check('the install record sits at the webroot, not in the (possibly shared) engine', str_starts_with($record, '<?php return;') && !file_exists("$WEBROOT/minn/.install.json"));
$check('the record names its webroot, the park and the moved entries', is_array($manifest) && ($manifest['root'] ?? '') === realpath($WEBROOT) && ($manifest['park'] ?? '') === $PARK && in_array('wp-load.php', $manifest['moved'] ?? [], true) && in_array('wp-admin', $manifest['moved'] ?? [], true), json_encode($manifest));
[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads the engine, naming the one running', $code === 0 && str_contains($out, ': minn') && str_contains($out, 'parked at') && str_contains($out, 'engine ' . Minn\Ops\EngineUpdate::versionOf("$WEBROOT/minn") . ','), $out);
[$out, $code] = $minn('install ' . escapeshellarg($WEBROOT) . ' --park=' . escapeshellarg($PARK));
$check('a second install is refused', $code === 1, $out);

// The installed webroot answers wp-cli through the engine.
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp option get home 2>&1', $home, $c1);
$check('wp option get home works in the installed webroot', $c1 === 0 && implode('', $home) === 'https://minn.localhost', implode("\n", $home));
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp core version 2>&1', $version, $c2);
$check('wp core version reads the shape file', $c2 === 0 && implode('', $version) === '7.1.4', implode("\n", $version));
exec('cd ' . escapeshellarg($WEBROOT) . ' && wp minn info 2>&1', $info, $c3);
$check('wp minn info reports the installed engine dir', $c3 === 0 && str_contains(implode("\n", $info), 'Engine dir: ' . realpath("$WEBROOT/minn")), implode("\n", $info));

// A record pointed at a folder install never parked in is refused (security sweep 2026-10-18): the record is a webroot file.
$decoy = "$SCRATCH/decoy";
mkdir($decoy);
file_put_contents("$decoy/wp-load.php", "<?php // not parked here\n");
file_put_contents("$WEBROOT/.minn-install.php", str_replace(json_encode($PARK, JSON_UNESCAPED_SLASHES), json_encode($decoy, JSON_UNESCAPED_SLASHES), $record));
[$out, $code] = $minn('eject ' . escapeshellarg($WEBROOT));
$check('eject refuses a park that carries no mark from this install, and moves nothing', $code === 1 && str_contains($out, 'does not carry this install') && is_file("$decoy/wp-load.php") && is_dir("$WEBROOT/minn"), $out);
file_put_contents("$WEBROOT/.minn-install.php", $record);
$check('install marks its park with the webroot it parked', trim((string) @file_get_contents("$PARK/.minn-park")) === realpath($WEBROOT));
$check('the park refuses every web request on Apache', str_contains((string) @file_get_contents("$PARK/.htaccess"), 'Require all denied'));
// Fix-delta review of the 2026-11-12 sweep: a park an earlier engine left unguarded is guarded once per engine
// version, the System view reads whether a park is served, and the served test reads layouts as hosts lay them.
@unlink("$PARK/.htaccess");
Minn\Cli\Installer::guardPark($WEBROOT);
$check('a park left without its refusal gets it back', str_contains((string) @file_get_contents("$PARK/.htaccess"), 'Require all denied'));
$check('a park outside served folders reads as safe', Minn\Cli\Installer::parkServed($WEBROOT) === null);
$served = new ReflectionMethod(Minn\Cli\Installer::class, 'served');
$layouts = array_map(static fn (string $folder): bool => $served->invoke(null, $folder) !== null, ['/var/www' => '/var/www', '/www/site_123' => '/www/site_123', '/home/u/public_html/sites' => '/home/u/public_html/sites', '/home/u/public_html' => '/home/u/public_html', '/srv/htdocs' => '/srv/htdocs', '/home/u' => '/home/u', '/var/www/html' => '/var/www/html']);
$check('the served test passes /var/www and Kinsta\'s /www/site, and refuses anything inside public_html, htdocs or nginx\'s /var/www/html', $layouts === ['/var/www' => false, '/www/site_123' => false, '/home/u/public_html/sites' => true, '/home/u/public_html' => true, '/srv/htdocs' => true, '/home/u' => false, '/var/www/html' => true], json_encode($layouts));

[$out, $code] = $minn('eject ' . escapeshellarg($WEBROOT));
$check('eject succeeds', $code === 0 && str_contains($out, 'Ejected.'), $out);
$check('the tree is back byte for byte', $tree($WEBROOT) === $before, json_encode(array_keys($tree($WEBROOT))) . ' vs ' . json_encode(array_keys($before)));
$check('the park is gone', !file_exists($PARK));
$check('no engine remains', !file_exists("$WEBROOT/minn") && !file_exists("$WEBROOT/wp-cli.yml") && !file_exists("$WEBROOT/.minn-install.php"));
$check('published asset trees are gone', !file_exists("$WEBROOT/minn-admin-asset") && !file_exists("$WEBROOT/minn-engine") && !file_exists("$WEBROOT/wp-includes/js/jquery"));
[$out, $code] = $minn('status ' . escapeshellarg($WEBROOT));
$check('status reads WordPress again', $code === 0 && str_contains($out, ': wordpress'), $out);

// Docker-style wp-config: getenv_docker('ENV', 'fallback') resolved from the
// environment, still as text, nothing in the file runs.
$realConfig = (string) file_get_contents(minn_test_site_root() . '/public/wp-config.php');
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

$kinds = "$SCRATCH/theme-kinds";
mkdir("$kinds/block/templates", 0755, true);
file_put_contents("$kinds/block/templates/index.html", '');
mkdir("$kinds/classic", 0755, true);
file_put_contents("$kinds/classic/index.php", "<?php\n");
mkdir("$kinds/hybrid", 0755, true);
file_put_contents("$kinds/hybrid/theme.json", "{}\n");
file_put_contents("$kinds/hybrid/index.php", "<?php\n");
mkdir("$kinds/child", 0755, true);
mkdir("$kinds/parent-block/templates", 0755, true);
file_put_contents("$kinds/parent-block/templates/index.html", '');
mkdir("$kinds/empty", 0755, true);
$check('themeKind: block', Minn\Cli\Preflight::themeKind("$kinds/block", "$kinds/block") === 'block');
$check('themeKind: classic', Minn\Cli\Preflight::themeKind("$kinds/classic", "$kinds/classic") === 'classic');
$check('themeKind: hybrid with theme.json is classic', Minn\Cli\Preflight::themeKind("$kinds/hybrid", "$kinds/hybrid") === 'classic');
$check('themeKind: child of a block parent is block', Minn\Cli\Preflight::themeKind("$kinds/child", "$kinds/parent-block") === 'block');
$check('themeKind: empty is none', Minn\Cli\Preflight::themeKind("$kinds/empty", "$kinds/empty") === 'none');
$check('themeKind: missing dir', Minn\Cli\Preflight::themeKind("$kinds/missing", "$kinds/missing") === 'missing');

$manifestDir = "$SCRATCH/manifest-plugin";
mkdir($manifestDir, 0755, true);
file_put_contents("$manifestDir/minn.json", json_encode([
    'name' => 'Scan fixture',
    'extension' => 'Minn\\Ext\\Scan\\Extension',
    'shortcodes' => ['eeb_protect_content'],
    'blocks' => ['jetpack/slideshow', 'mosne/dark-palette'],
    'types' => [['slug' => 'zz_note', 'name' => 'Notes', 'rest_base' => 'zz-note']],
], JSON_UNESCAPED_SLASHES));
$manifest = Minn\Extension\Manifest::read($manifestDir);
$check(
    'manifest reads shortcodes, blocks, and types',
    $manifest !== null && $manifest->shortcodes === ['eeb_protect_content'] && $manifest->blocks === ['jetpack/slideshow', 'mosne/dark-palette'] && ($manifest->types[0]['slug'] ?? '') === 'zz_note',
    json_encode($manifest),
);

// The curl installer (minn.run/install): it installs only an archive signed with a key the engine trusts.
$script = (string) file_get_contents("$ROOT/install.sh");
preg_match('/^MINN_KEYS=\((.*?)^\)/ms', $script, $keyBlock);
preg_match_all('/"([A-Za-z0-9+\/=]{44})"/', $keyBlock[1] ?? '', $scriptKeys);
$check('the curl installer trusts exactly the release keys the engine trusts', $scriptKeys[1] === Minn\Ops\EngineUpdate::KEYS, json_encode($scriptKeys[1]));
$shRoot = "$SCRATCH/sh/public";
$shBefore = $tree($shRoot);
$curlInstall = static function (string $zip, string $extra = '') use ($ROOT, $shRoot): array {
    exec('cd ' . escapeshellarg($shRoot) . ' && bash ' . escapeshellarg("$ROOT/install.sh") . ' --no-cache-flush --engine-url=' . escapeshellarg('file://' . $zip) . " {$extra} 2>&1", $out, $code);
    return [implode("\n", $out), $code];
};
$fake = "$SCRATCH/fake-release";
mkdir("$fake/minn/bin", 0755, true);
file_put_contents("$fake/minn/bootstrap.php", "<?php\n");
file_put_contents("$fake/minn/bin/minn", "<?php\nfile_put_contents(getcwd() . '/ran', 'yes');\n");
exec('cd ' . escapeshellarg($fake) . ' && zip -qr ../fake.zip minn');
[$out, $code] = $curlInstall("$SCRATCH/fake.zip");
$check('the curl installer refuses a release with no signature', $code !== 0 && str_contains($out, 'has no signature') && $tree($shRoot) === $shBefore, $out);
file_put_contents("$SCRATCH/fake.zip.sig", base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES)) . "\n");
[$out, $code] = $curlInstall("$SCRATCH/fake.zip");
$check('the curl installer refuses a release signed with no trusted key', $code !== 0 && str_contains($out, 'not signed with a Minn release key') && $tree($shRoot) === $shBefore && !is_file("$shRoot/ran"), $out);
$release = getenv('MINN_TEST_RELEASE_ZIP') ?: "$ROOT/dist/minn.zip";
$signedRelease = is_file($release) && is_file("{$release}.sig");
if ($signedRelease) {
    try {
        (new Minn\Ops\EngineUpdate($ENGINE_DIR))->verify((string) file_get_contents($release), hash_file('sha256', $release), trim((string) file_get_contents("{$release}.sig")), 'test');
    } catch (Throwable) {
        $signedRelease = false;
    }
}
if ($signedRelease) {
    [$out, $code] = $curlInstall($release, '--park=' . escapeshellarg("$SCRATCH/sh/wp-parked"));
    $check('the curl installer checks a signed release and installs it', $code === 0 && str_contains($out, 'Signature checked') && str_contains($out, 'Minn is installed') && is_file("$shRoot/minn/bootstrap.php"), $out);
    // Ejected by the engine it installed, as on a host.
    exec('php ' . escapeshellarg("$shRoot/minn/bin/minn") . ' eject ' . escapeshellarg($shRoot) . ' 2>&1', $ejected, $code);
    $out = implode("\n", $ejected);
    $check('and eject puts that webroot back as it was', $code === 0 && $tree($shRoot) === $shBefore, $out);
} else {
    echo "  skip the signed curl install: no signed release at {$release} (php scripts/build-release.php)\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
