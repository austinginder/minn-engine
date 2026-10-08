<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Switching a plugin on and off the way the reference's activate_plugin
 * and deactivate_plugins do (probe plugin-activation), for the facade and
 * for the engine's own REST routes and WP-CLI verbs alike. Activating
 * checks the plugin's requirements, then loads its main file through the
 * runtime's gate (so its activation hook is registered, silent or not),
 * fires activate_plugin and activate_<file> (which runs that hook), writes
 * active_plugins sorted through update_option, and fires activated_plugin;
 * whatever the plugin printed meanwhile makes the answer unexpected_output,
 * with the plugin left active, as the reference leaves it. Silent skips the
 * actions, not the load. A plugin the gate will not load (it needs
 * functions or classes the runtime lacks) is refused rather than recorded
 * active with code that could never run its activation hook. Deactivating
 * fires deactivate_plugin, deactivate_<file> and deactivated_plugin for
 * each plugin, then writes the list once.
 */
final class PluginActivation
{
    /**
     * Activates one plugin ("dir/file.php"), announcing it to the hooks
     * given, or to nobody (silent); null when it is active afterwards with
     * nothing to report.
     */
    public static function activate(string $plugin, ?Hooks $announce): ?Refusal
    {
        $active = self::active();
        if (in_array($plugin, $active, true)) {
            return null;
        }
        if (!is_file(WP_PLUGIN_DIR . '/' . $plugin)) {
            return new Refusal('plugin_not_found', 'Plugin file does not exist.');
        }
        $unmet = PluginRequirements::check($plugin);
        if ($unmet !== null) {
            return $unmet;
        }
        ob_start();
        $refused = Plugins::loadNow($plugin, Runtime::current());
        if ($refused !== null) {
            ob_end_clean();
            return new Refusal('plugin_unsupported', $refused);
        }
        $announce?->action('activate_plugin', [$plugin, false]);
        $announce?->action("activate_{$plugin}", [false]);
        $active[] = $plugin;
        sort($active);
        \update_option('active_plugins', $active);
        $announce?->action('activated_plugin', [$plugin, false]);
        $printed = (string) ob_get_clean();
        return $printed === '' ? null : new Refusal('unexpected_output', 'The plugin generated unexpected output.', $printed);
    }

    /**
     * Deactivates plugins ("dir/file.php" each), announcing each to the hooks
     * given, or to nobody (silent); those not active are passed over.
     *
     * @param list<string> $plugins
     */
    public static function deactivate(array $plugins, ?Hooks $announce): void
    {
        $active = self::active();
        foreach ($plugins as $plugin) {
            if (!in_array($plugin, $active, true)) {
                continue;
            }
            $announce?->action('deactivate_plugin', [$plugin, false]);
            $active = array_values(array_diff($active, [$plugin]));
            $announce?->action("deactivate_{$plugin}", [false]);
            $announce?->action('deactivated_plugin', [$plugin, false]);
        }
        \update_option('active_plugins', $active);
    }

    /** @return list<string> */
    private static function active(): array
    {
        $active = Runtime::options()->filtered('active_plugins', []);
        return is_array($active) ? array_values(array_filter($active, 'is_string')) : [];
    }
}
