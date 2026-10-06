<?php
/**
 * Boots the engine's WordPress runtime from the command line against the
 * site's own database, the way Engine::respond() does for a request, so a
 * probe can run on the facade. Include it, then run the probe.
 */

// The engine code is this repo's; the site it runs against is the test site,
// never the marketing site that shares this directory.
$root = dirname(__DIR__, 2);
$siteRoot = getenv('MINN_SITE_ROOT') ?: '~/Cove/Sites/minn.localhost';
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
$context = new Minn\Context($db, $site, null, Minn\Content\Reader::anonymous(), $capabilities, MINN_ENGINE_DIR, ABSPATH, '7.1');
$runtime = new Minn\Runtime\Runtime($context);
Minn\Runtime\Runtime::boot($runtime);
// No plugins load here, so the pluggable functions are defined at once.
Minn\Runtime\Runtime::loadPluggables();
// The reference a probe is compared with has loaded its plugins; what the defaults hang on that (the two big image sizes) runs here too.
do_action('plugins_loaded');
$runtime->set('permalinks', Minn\Front\Permalinks::fromDb($db));
$theme = Minn\Theme\Theme::active($site, Minn\Front\Permalinks::fromDb($db), ABSPATH . 'wp-content/themes');
$runtime->set('block_theme', $theme !== null);
$runtime->set('theme', $theme);
if ($theme !== null) {
    // The theme's blocks (template parts, post blocks, comments) register the way a page render does.
    Minn\Theme\PageRenderer::create($db, $theme, Minn\Front\Permalinks::fromDb($db), 10);
}
