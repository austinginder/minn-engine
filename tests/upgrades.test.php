<?php
/**
 * The engine's own installs and updates through WordPress's upgraders
 * (Ops\UpgraderRun), on the test site with the runtime booted and no
 * network: throwaway zips are installed, uploaded and offered as updates.
 * What is pinned is the engine's own say, which the reference does not
 * have: a package from outside the Minn update service is installed only
 * on its publisher's word (an upgrader_pre_download answer), an upload must
 * hold one plain folder, a Minn extension is placed by the engine, and the
 * offers are published in WordPress's update transients. The probes
 * (upgrader, site-options) pin what plugins are told.
 *
 *   php tests/upgrades.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';
require __DIR__ . '/tools/engine-runtime.php';

use Minn\Content\Inventory;
use Minn\Ops\Packages;
use Minn\Ops\Updates;
use Minn\RestError;

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

$contentDir = ABSPATH . 'wp-content';
$tmp = sys_get_temp_dir() . '/minn-upgrades-test-' . getmypid();
@mkdir($tmp, 0755, true);
$saved = [];
foreach ([Updates::OPTION, '_site_transient_update_plugins', '_site_transient_update_themes'] as $option) {
    $saved[$option] = $site->option($option);
}
$sweep = static function () use ($contentDir, $tmp, $saved, $site): void {
    foreach (['plugins/zz-upg-plugin', 'plugins/zz-upg-extension', 'plugins/zz-upg-two'] as $folder) {
        if (is_dir("{$contentDir}/{$folder}")) {
            Minn\Support\Files::deleteTree("{$contentDir}/{$folder}");
        }
    }
    if (is_dir($tmp)) {
        Minn\Support\Files::deleteTree($tmp);
    }
    foreach ($saved as $option => $value) {
        $value === null ? delete_option($option) : $site->setOption($option, $value);
    }
};
register_shutdown_function($sweep);

$zip = static function (string $name, array $files) use ($tmp): string {
    $archive = new ZipArchive();
    $archive->open("{$tmp}/{$name}", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $entry => $contents) {
        $archive->addFromString($entry, $contents);
    }
    $archive->close();
    return (string) file_get_contents("{$tmp}/{$name}");
};
$plugin = static fn (string $version): string => "<?php\n/**\n * Plugin Name: ZZ Upg Plugin\n * Version: {$version}\n */\n";
$heard = [];
add_action('upgrader_process_complete', static function ($upgrader, $extra) use (&$heard): void {
    $heard[] = get_class($upgrader) . ':' . ($extra['action'] ?? '') . ':' . ($extra['type'] ?? '');
}, 10, 2);

$packages = new Packages($site, $contentDir);
$updates = new Updates($site, new Inventory($contentDir, $site), $packages, $contentDir);

// An upload: through the upgrader, and refused when the folder is taken.
$result = $packages->unpack($zip('one.zip', ['zz-upg-plugin/zz-upg-plugin.php' => $plugin('1.0')]), 'extension');
$check('an uploaded plugin is installed', ($result['folder'] ?? '') === 'zz-upg-plugin' && is_file("{$contentDir}/plugins/zz-upg-plugin/zz-upg-plugin.php"), json_encode($result));
$check('the upload went through the upgrader', in_array('Plugin_Upgrader:install:plugin', $heard, true), json_encode($heard));
try {
    $packages->unpack($zip('again.zip', ['zz-upg-plugin/zz-upg-plugin.php' => $plugin('2.0')]), 'plugin');
    $check('a taken folder is refused', false, 'installed over it');
} catch (RestError $e) {
    $check('a taken folder is refused, naming what is there and what came', $e->errorCode === 'folder_exists' && $e->status === 409 && ($e->extra['current_version'] ?? '') === '1.0' && ($e->extra['new_version'] ?? '') === '2.0', json_encode([$e->errorCode, $e->status, $e->extra]));
}
// Fix-delta review, round 2: with the upgrade folder gone, the folder, its index and a refused install's cleanup all go
// through the site's filesystem (on an FTP or SSH method, the user that unzips), recorded here by a wrapper.
$realFilesystem = $GLOBALS['wp_filesystem'];
$GLOBALS['wp_filesystem'] = new class ($realFilesystem) {
    public array $calls = [];
    public function __construct(public object $real) {}
    public function __call(string $name, array $args): mixed { return $this->real->$name(...$args); }
    public function __get(string $name): mixed { return $this->real->$name; }
    public function mkdir(...$args): mixed { $this->calls[] = 'mkdir'; return $this->real->mkdir(...$args); }
    public function put_contents(...$args): mixed { $this->calls[] = 'put_contents'; return $this->real->put_contents(...$args); }
    public function delete(...$args): mixed { $this->calls[] = 'delete'; return $this->real->delete(...$args); }
};
$recorder = $GLOBALS['wp_filesystem'];
Minn\Support\Files::deleteTree("{$contentDir}/upgrade");
$zip('again2.zip', ['zz-upg-plugin/zz-upg-plugin.php' => $plugin('2.0')]);
$unpacked = \Minn\Runtime\Upgrade::unpack(new Plugin_Upgrader(), "{$tmp}/again2.zip");
\Minn\Runtime\Upgrade::install(new Plugin_Upgrader(), ['source' => is_string($unpacked) ? $unpacked : '', 'destination' => "{$contentDir}/plugins"]);
$GLOBALS['wp_filesystem'] = $realFilesystem;
$check('the upgrade folder, its index and a refused install\'s cleanup go through the site\'s filesystem', count(array_intersect(['mkdir', 'put_contents', 'delete'], array_unique($recorder->calls))) === 3 && is_string($unpacked) && !is_dir($unpacked), json_encode($recorder->calls));
// Security sweep 2026-11-12 (2.6): a refused install leaves no unpacked package in the upgrade folder, which keeps its index.
$left = array_values(array_diff(scandir("{$contentDir}/upgrade") ?: [], ['.', '..']));
$check('a refused install leaves only the upgrade folder\'s index behind', $left === ['index.php'], json_encode($left));
// Fix-delta review: a failed install's source that climbs out of the upgrade folder is resolved first, so nothing
// outside it is removed.
@mkdir("{$contentDir}/zz-outside-upgrade", 0755, true);
file_put_contents("{$contentDir}/zz-outside-upgrade/keep.txt", 'keep');
\Minn\Runtime\Upgrade::install(new Plugin_Upgrader(), ['source' => "{$contentDir}/upgrade/../zz-outside-upgrade", 'destination' => '']);
$check('a failed install never removes a folder outside the upgrade folder', is_file("{$contentDir}/zz-outside-upgrade/keep.txt"));
@unlink("{$contentDir}/zz-outside-upgrade/keep.txt");
@rmdir("{$contentDir}/zz-outside-upgrade");
$result = $packages->unpackReplacing($zip('replace.zip', ['zz-upg-plugin/zz-upg-plugin.php' => $plugin('2.0')]), 'plugin');
$check('an upload may replace the folder', ($result['version'] ?? '') === '2.0', json_encode($result));
try {
    $packages->unpack($zip('two.zip', ['zz-upg-two/a.php' => $plugin('1.0'), 'zz-upg-other/b.php' => $plugin('1.0')]), 'plugin');
    $check('an upload holding two folders is refused', false, 'installed');
} catch (RestError $e) {
    $check('an upload holding two folders is refused', $e->errorCode === 'bad_archive', $e->errorCode);
}
$heard = [];
$result = $packages->unpack($zip('extension.zip', ['zz-upg-extension/minn.json' => json_encode(['extension' => 'ZzUpgExtension', 'name' => 'ZZ Upg Extension', 'version' => '1.0']), 'zz-upg-extension/extension.php' => "<?php\n"]), 'extension');
$check('a Minn extension is placed by the engine, not the upgraders', ($result['kind'] ?? '') === 'extension' && $heard === [], json_encode([$result, $heard]));

// An update: the offer published in the transient, then applied on the publisher's word only.
$file = 'zz-upg-plugin/zz-upg-plugin.php';
$offer = ['id' => 'example.com/zz-upg', 'slug' => 'zz-upg-plugin', 'plugin' => $file, 'new_version' => '3.0', 'package' => 'https://example.com/zz-upg-plugin.3.0.zip'];
$state = ['checked' => time(), 'source' => Minn\Ops\Directory::BASE, 'plugins' => [$file => $offer], 'no_update' => [], 'themes' => [], 'themes_current' => [], 'archives' => [], 'supplied' => [$file]];
$site->setOption(Updates::OPTION, (string) json_encode($state));
$updates = new Updates($site, new Inventory($contentDir, $site), $packages, $contentDir);
$updates->publish();
$transient = get_site_transient('update_plugins');
$check('the offers are published in update_plugins', is_object($transient) && ($transient->response[$file]->new_version ?? '') === '3.0' && ($transient->checked[$file] ?? '') === '2.0', json_encode($transient));
try {
    $updates->updatePlugin($file);
    $check('a package from outside the service is refused on nobody\'s word', false, 'updated');
} catch (RestError $e) {
    $check('a package from outside the service is refused on nobody\'s word', $e->errorCode === 'update_failed' && str_contains($e->getMessage(), 'publisher did not verify'), $e->errorCode . ': ' . $e->getMessage());
}
$check('the refused update leaves the plugin as it was', Minn\Support\FileHeaders::values("{$contentDir}/plugins/{$file}", ['Version'])['Version'] === '2.0');
$refuse = static fn ($reply, $package) => $package === $offer['package'] ? new WP_Error('zz_bad_hash', 'The package does not match its published hash.') : $reply;
add_filter('upgrader_pre_download', $refuse, 10, 2);
try {
    $updates->updatePlugin($file);
    $check('a publisher\'s refusal stops the update', false, 'updated');
} catch (RestError $e) {
    $check('a publisher\'s refusal stops the update, with its reason', $e->errorCode === 'zz_bad_hash', $e->errorCode);
}
remove_filter('upgrader_pre_download', $refuse, 10);
// Files the site cannot write directly, with no connection set: the upgrader stops before any download
// and records no error, so the refusal names the method rather than a bare "could not be installed".
$ftp = static fn () => 'ftpext';
add_filter('filesystem_method', $ftp);
try {
    $updates->updatePlugin($file);
    $check('unreachable files stop the update, naming the method', false, 'updated');
} catch (RestError $e) {
    $check('unreachable files stop the update, naming the method', $e->errorCode === 'fs_unavailable' && $e->status === 500 && str_contains($e->getMessage(), 'ftpext') && str_contains($e->getMessage(), 'FS_METHOD'), $e->errorCode . ': ' . $e->getMessage());
}
remove_filter('filesystem_method', $ftp);
$check('the stopped update leaves the plugin as it was', Minn\Support\FileHeaders::values("{$contentDir}/plugins/{$file}", ['Version'])['Version'] === '2.0');
$verified = "{$tmp}/verified.zip";
file_put_contents($verified, $zip('verified-source.zip', ['zz-upg-plugin/zz-upg-plugin.php' => $plugin('3.0')]));
$vouch = static fn ($reply, $package) => $package === $offer['package'] ? $verified : $reply;
add_filter('upgrader_pre_download', $vouch, 10, 2);
$heard = [];
try {
    $version = $updates->updatePlugin($file);
    $check('the publisher\'s verified copy is installed', $version === '3.0', $version);
} catch (RestError $e) {
    $check('the publisher\'s verified copy is installed', false, $e->errorCode . ': ' . $e->getMessage());
}
remove_filter('upgrader_pre_download', $vouch, 10);
$check('the update was a bulk update, as Minn Admin runs it', in_array('Plugin_Upgrader:update:plugin', $heard, true), json_encode($heard));
$transient = get_site_transient('update_plugins');
$check('the applied offer leaves the published offers', is_object($transient) && !isset($transient->response[$file]), json_encode($transient->response ?? null));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
