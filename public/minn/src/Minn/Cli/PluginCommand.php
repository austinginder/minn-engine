<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\Inventory;
use Minn\Content\PluginState;
use Minn\Extension\Loader;
use WP_CLI;
use WP_CLI\Formatter;

/** `wp plugin list|activate|deactivate`: the inventory CaptainCore's fetch-site-data reads, and the switch the off page names. */
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
        $this->switch($args, true);
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
        $this->switch($args, false);
    }

    /** The reference's wording, line for line: one warning per miss, a Success summary or an Error with none done. */
    private function switch(array $slugs, bool $on): void
    {
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
            $state->setActive($plugin, $on);
            WP_CLI::log("Plugin '{$slug}' {$verb}.");
            $done++;
        }
        $total = count($slugs);
        if ($done === 0) {
            WP_CLI::error('No plugins ' . $verb . '.');
        }
        WP_CLI::success(ucfirst($verb) . " {$done} of {$total} plugins.");
    }
}
