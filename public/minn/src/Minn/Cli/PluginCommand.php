<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\Content\Inventory;
use Minn\Content\PluginState;
use Minn\Extension\Loader;
use Minn\RestError;
use WP_CLI;
use WP_CLI\Formatter;

/** `wp plugin list|install|update|activate|deactivate|delete`: the inventory and the fleet's install/update/delete. */
final class PluginCommand
{
    private const FIELDS = ['name', 'status', 'update', 'version', 'update_version', 'auto_update'];

    /**
     * Lists installed plugins, must-use plugins, and drop-ins.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter to one status: active, inactive, must-use, or dropin.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each plugin.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function list(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $items = (new Inventory(ABSPATH . 'wp-content', $runtime->site))->plugins();
        $status = (string) ($assocArgs['status'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, static fn (array $item) => $item['status'] === $status));
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }

    /**
     * Activates one or more plugins: a WordPress plugin joins active_plugins
     * (its stored state; the engine runs none of its code), a Minn extension
     * joins the engine's own list.
     *
     * ## OPTIONS
     *
     * <plugin>...
     * : One or more plugins to activate.
     *
     * @when before_wp_load
     */
    public function activate(array $args, array $assocArgs): void
    {
        $this->switch($args, 'activate');
    }

    /**
     * Deactivates one or more plugins.
     *
     * ## OPTIONS
     *
     * <plugin>...
     * : One or more plugins to deactivate.
     *
     * @when before_wp_load
     */
    public function deactivate(array $args, array $assocArgs): void
    {
        $this->switch($args, 'deactivate');
    }

    /**
     * Installs one or more plugins from wordpress.org, a zip, or a URL.
     *
     * ## OPTIONS
     *
     * <plugin|zip|url>...
     * : A plugin slug, a local zip path, or a zip URL.
     *
     * [--version=<version>]
     * : Install that wordpress.org version instead of the current one.
     *
     * [--force]
     * : Overwrite an installed copy of the same folder.
     *
     * [--activate]
     * : Activate the plugin after it is installed (or if it is already on disk).
     *
     * @when before_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        $force = isset($assocArgs['force']);
        $activate = isset($assocArgs['activate']);
        $version = (string) ($assocArgs['version'] ?? '');
        $done = 0;
        $already = 0;
        $missing = 0;
        foreach ($args as $source) {
            $fresh = false;
            $folder = $this->installOne($packages, $source, $force, $version, $fresh);
            if ($folder === null) {
                $missing++;
                continue;
            }
            if ($fresh) {
                $done++;
            } else {
                $already++;
            }
            if ($activate) {
                $this->activateFolder($runtime, $folder);
            }
        }
        $total = count($args);
        if ($done > 0 && $missing === 0) {
            WP_CLI::success("Installed {$done} of {$total} plugins.");
            return;
        }
        if ($done > 0) {
            WP_CLI::error("Only installed {$done} of {$total} plugins.");
        }
        if ($missing === 0 && $already > 0) {
            WP_CLI::success('Plugin already installed.');
            return;
        }
        WP_CLI::error('No plugins installed.');
    }

    /**
     * Updates one or more plugins from wordpress.org.
     *
     * ## OPTIONS
     *
     * [<plugin>...]
     * : One or more plugins to update.
     *
     * [--all]
     * : If set, all plugins that have updates will be updated.
     *
     * [--exclude=<name>]
     * : Comma separated list of plugin names that should be excluded from updating.
     *
     * [--minor]
     * : Only perform updates for minor releases.
     *
     * [--patch]
     * : Only perform updates for patch releases.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - summary
     * ---
     *
     * [--version=<version>]
     * : If set, the plugin will be updated to the specified version.
     *
     * [--dry-run]
     * : Preview which plugins would be updated.
     *
     * @alias upgrade
     * @when before_wp_load
     */
    public function update(array $args, array $assocArgs): void
    {
        if ((string) ($assocArgs['version'] ?? '') !== '') {
            $this->pinVersion($args, (string) $assocArgs['version']);
            return;
        }
        AssetUpdate::boot('plugin')->run($args, $assocArgs);
    }

    /**
     * Deletes plugin files without deactivating.
     *
     * ## OPTIONS
     *
     * [<plugin>...]
     * : One or more plugin folders to delete.
     *
     * @when before_wp_load
     */
    public function delete(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        $done = 0;
        foreach ($args as $slug) {
            $dir = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
            $file = $dir . '.php';
            if (!is_dir($dir) && !is_file($file)) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            if (is_file($file) && !is_dir($dir)) {
                unlink($file);
            } else {
                $packages->remove('plugin', $slug);
            }
            WP_CLI::log("Deleted '{$slug}' plugin.");
            $done++;
        }
        if ($done > 0) {
            WP_CLI::success("Deleted {$done} of " . count($args) . ' plugins.');
            return;
        }
        WP_CLI::success('Plugin already deleted.');
    }

    /**
     * Checks if a given plugin is installed. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <plugin>
     * : The plugin folder to check.
     *
     * @when before_wp_load
     */
    public function is_installed(array $args, array $assocArgs): void
    {
        Runtime::boot();
        $slug = (string) ($args[0] ?? '');
        $root = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
        if (is_dir($root) || is_file($root . '.php')) {
            return;
        }
        WP_CLI::halt(1);
    }

    /**
     * Checks if a given plugin is active. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <plugin>
     * : The plugin folder to check.
     *
     * [--network]
     * : Ignored on a single site.
     *
     * @when before_wp_load
     */
    public function is_active(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $contentDir = ABSPATH . 'wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $plugin = $state->find((string) ($args[0] ?? ''));
        if ($plugin === null || !$state->isActive($plugin)) {
            WP_CLI::halt(1);
        }
    }

    /**
     * Searches the wordpress.org plugin directory.
     *
     * ## OPTIONS
     *
     * <search>
     * : The string to search for.
     *
     * [--page=<page>]
     * : Optional page to display.
     * ---
     * default: 1
     * ---
     *
     * [--per-page=<per-page>]
     * : Optional number of results to display.
     * ---
     * default: 10
     * ---
     *
     * [--field=<field>]
     * : Prints the value of a single field for each plugin.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields. Defaults to name,slug,rating.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function search(array $args, array $assocArgs): void
    {
        DirectorySearch::run('plugin', $args, $assocArgs);
    }

    /** The reference's wording, line for line: one warning per miss, a Success summary or an Error with none done. */
    private function switch(array $slugs, string $verb): void
    {
        $on = $verb === 'activate';
        $runtime = Runtime::boot();
        $contentDir = ABSPATH . 'wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $verb = $on ? 'activated' : 'deactivated';
        $done = 0;
        foreach ($slugs as $slug) {
            $plugin = $state->find($slug);
            if ($plugin === null) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            if ($state->isActive($plugin) === $on) {
                WP_CLI::warning($on ? "Plugin '{$slug}' is already active." : "Plugin '{$slug}' isn't active.");
                if (count($slugs) === 1) {
                    WP_CLI::success($on ? 'Plugin already activated.' : 'Plugin already deactivated.');
                    return;
                }
                continue;
            }
            $on ? $state->activate($plugin) : $state->deactivate($plugin);
            WP_CLI::log("Plugin '{$slug}' {$verb}.");
            $done++;
        }
        $total = count($slugs);
        if ($done === 0) {
            WP_CLI::error('No plugins ' . $verb . '.');
        }
        WP_CLI::success(ucfirst($verb) . " {$done} of {$total} plugins.");
    }

    private function installOne(Packages $packages, string $source, bool $force, string $version, ?bool &$fresh): ?string
    {
        $fresh = false;
        $plugins = rtrim(ABSPATH, '/') . '/wp-content/plugins';
        if (preg_match('#^https?://#i', $source)) {
            return $this->installArchive($packages, $source, $force, $fresh);
        }
        if (is_file($source) || str_ends_with(strtolower($source), '.zip')) {
            if (!is_file($source)) {
                WP_CLI::warning("{$source}: Invalid plugin slug.");
                WP_CLI::warning("The '{$source}' plugin could not be found.");
                return null;
            }
            return $this->installArchive($packages, $source, $force, $fresh);
        }
        if (!preg_match('/^[a-z0-9-]+$/', $source)) {
            WP_CLI::warning("{$source}: Invalid plugin slug.");
            WP_CLI::warning("The '{$source}' plugin could not be found.");
            return null;
        }
        $dest = "{$plugins}/{$source}";
        if ((is_dir($dest) || is_file($dest . '.php')) && !$force) {
            WP_CLI::warning("{$source}: Plugin already installed.");
            return $source;
        }
        try {
            $info = $packages->directoryPlugin($source);
        } catch (RestError) {
            $info = null;
        }
        if ($info === null) {
            WP_CLI::warning("{$source}: Plugin not found.");
            WP_CLI::warning("The '{$source}' plugin could not be found.");
            return null;
        }
        $name = html_entity_decode(strip_tags((string) ($info['name'] ?? $source)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $useVersion = $version !== '' ? $version : (string) ($info['version'] ?? '');
        $link = $version !== ''
            ? 'https://downloads.wordpress.org/plugin/' . $source . '.' . $version . '.zip'
            : (string) ($info['download_link'] ?? '');
        WP_CLI::log("Installing {$name} ({$useVersion})");
        WP_CLI::log("Downloading installation package from {$link}...");
        $existed = is_dir($dest);
        try {
            WP_CLI::log('Unpacking the package...');
            WP_CLI::log('Installing the plugin...');
            if ($existed) {
                WP_CLI::log('Removing the old version of the plugin...');
            }
            $folder = ($force ? $packages->replacePlugin($source, $version) : $packages->installPlugin($source, $version));
        } catch (RestError $error) {
            WP_CLI::warning($source . ': ' . $error->getMessage());
            WP_CLI::warning("The '{$source}' plugin could not be found.");
            return null;
        }
        WP_CLI::log($existed ? 'Plugin updated successfully.' : 'Plugin installed successfully.');
        $fresh = true;
        return $folder;
    }

    private function installArchive(Packages $packages, string $source, bool $force, ?bool &$fresh): ?string
    {
        $fresh = false;
        try {
            if (preg_match('#^https?://#i', $source)) {
                WP_CLI::log("Downloading installation package from {$source}...");
                $bytes = $packages->fetch(preg_replace('#^http://#i', 'https://', $source) ?? $source);
            } else {
                $bytes = (string) file_get_contents($source);
            }
            WP_CLI::log('Unpacking the package...');
            WP_CLI::log('Installing the plugin...');
            $result = ($force ? $packages->unpackReplacing($bytes, 'plugin') : $packages->unpack($bytes, 'plugin'));
        } catch (RestError $error) {
            if ($error->status === 409) {
                $folder = basename((string) ($error->extra['destination'] ?? ''));
                WP_CLI::warning(($folder !== '' ? $folder : $source) . ': Plugin already installed.');
                return $folder !== '' ? $folder : null;
            }
            WP_CLI::warning($source . ': ' . $error->getMessage());
            WP_CLI::warning("The '{$source}' plugin could not be found.");
            return null;
        }
        WP_CLI::log('Plugin installed successfully.');
        $fresh = true;
        return $result['folder'];
    }

    /** `--version` on update force-installs that release using the install wording. */
    private function pinVersion(array $args, string $version): void
    {
        if ($args === []) {
            WP_CLI::error('Please specify one or more plugins, or use --all.');
        }
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        $done = 0;
        foreach ($args as $slug) {
            $dest = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
            if (!is_dir($dest) && !is_file($dest . '.php')) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            $fresh = false;
            if ($this->installOne($packages, $slug, true, $version, $fresh) !== null && $fresh) {
                $done++;
            }
        }
        if ($done > 0) {
            WP_CLI::success("Installed {$done} of " . count($args) . ' plugins.');
        }
    }

    private function activateFolder(Runtime $runtime, string $folder): void
    {
        $contentDir = rtrim(ABSPATH, '/') . '/wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $plugin = $state->find($folder);
        WP_CLI::log("Activating '{$folder}'...");
        if ($plugin === null) {
            WP_CLI::warning("The '{$folder}' plugin could not be found.");
            return;
        }
        if ($state->isActive($plugin)) {
            return;
        }
        $state->activate($plugin);
        WP_CLI::log("Plugin '{$folder}' activated.");
    }
}
