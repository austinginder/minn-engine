<?php
/**
 * Boots the engine's WordPress runtime from the command line against the
 * site's own database, the way Engine::respond() does for a request, so a
 * probe can run on the facade. Include it, then run the probe.
 */

$root = dirname(__DIR__, 2);
$siteRoot = getenv('MINN_SITE_ROOT') ?: $root;
$config = file_get_contents($siteRoot . '/public/wp-config.php');
$config = str_replace("require_once ABSPATH . 'wp-settings.php';", '', $config);
define('ABSPATH', $siteRoot . '/public/');
eval('?>' . $config);
if (!defined('MINN_ENGINE_VERSION')) {
    define('MINN_ENGINE_VERSION', '0.0.1');
    define('MINN_ENGINE_DIR', $root . '/public/minn');
}
require $root . '/public/minn/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$db = Minn\Db::shared();
$site = new Minn\Content\Site($db);
$capabilities = Minn\Auth\Capabilities::fromDb($db);
$runtime = new Minn\Runtime\Runtime($db, $site, null, Minn\Content\Reader::anonymous(), $capabilities, MINN_ENGINE_DIR, ABSPATH, '7.1');
Minn\Runtime\Runtime::boot($runtime);
$runtime->set('permalinks', Minn\Front\Permalinks::fromDb($db));
$runtime->set('block_theme', Minn\Theme\Theme::active($site, Minn\Front\Permalinks::fromDb($db), ABSPATH . 'wp-content/themes') !== null);
