<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\FileHeaders;

/**
 * Whether a plugin can be switched on here, as the reference's
 * validate_plugin_requirements answers (probe plugin-activation): the
 * plugin header's "Requires at least" against the WordPress version the
 * site speaks, its "Requires PHP" against the running PHP, and every slug
 * in its "Requires Plugins" installed and active (a readme.txt is not
 * read). A refusal carries the reference's code, its HTML message word for
 * word, and for missing plugins the slugs found inactive (slug => name) and
 * not installed (slug => slug). The slugs are taken sorted (one that is not
 * a lower-case slug is passed over), and named in that order.
 */
final class PluginRequirements
{
    /** Null when the plugin may be activated, otherwise why not. */
    public static function check(string $plugin): ?Refusal
    {
        // Read as the plugin screens read it, translated (its text domain loaded), as the reference reads it here.
        $headers = \get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $name = (string) $headers['Name'];
        $wp = (string) ($GLOBALS['wp_version'] ?? '');
        $needsWp = (string) $headers['RequiresWP'];
        $needsPhp = (string) $headers['RequiresPHP'];
        $wpOk = \is_wp_version_compatible($needsWp);
        $phpOk = \is_php_version_compatible($needsPhp);
        $updatePhp = '<p>' . sprintf('<a href="%s">Learn more about updating PHP</a>.', \esc_url(\wp_get_update_php_url())) . '</p>';
        if (!$wpOk && !$phpOk) {
            return new Refusal('plugin_wp_php_incompatible', '<p>' . sprintf('<strong>Error:</strong> Current versions of WordPress (%1$s) and PHP (%2$s) do not meet minimum requirements for %3$s. The plugin requires WordPress %4$s and PHP %5$s.', $wp, PHP_VERSION, $name, $needsWp, $needsPhp) . '</p>' . $updatePhp);
        }
        if (!$phpOk) {
            return new Refusal('plugin_php_incompatible', '<p>' . sprintf('<strong>Error:</strong> Current PHP version (%1$s) does not meet minimum requirements for %2$s. The plugin requires PHP %3$s.', PHP_VERSION, $name, $needsPhp) . '</p>' . $updatePhp);
        }
        if (!$wpOk) {
            return new Refusal('plugin_wp_incompatible', '<p>' . sprintf('<strong>Error:</strong> Current WordPress version (%1$s) does not meet minimum requirements for %2$s. The plugin requires WordPress %3$s.', $wp, $name, $needsWp) . '</p>');
        }
        return self::dependencies($name, (string) $headers['RequiresPlugins']);
    }

    /** The required plugins that are not installed and active, refused together. */
    private static function dependencies(string $name, string $required): ?Refusal
    {
        $slugs = array_unique(array_filter(array_map('trim', explode(',', $required)), static fn (string $slug): bool => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1));
        sort($slugs, SORT_STRING);
        $active = (array) Runtime::options()->filtered('active_plugins', []);
        $unmet = [];
        $named = [];
        foreach ($slugs as $slug) {
            $file = self::installed($slug);
            if ($file === null) {
                $unmet['not_installed'][$slug] = $named[] = $slug;
            } elseif (!in_array($file, $active, true)) {
                $unmet['inactive'][$slug] = $named[] = FileHeaders::values(WP_PLUGIN_DIR . '/' . $file, ['Plugin Name'])['Plugin Name'];
            }
        }
        if ($named === []) {
            return null;
        }
        $message = sprintf(
            '<strong>Error:</strong> %1$s requires %2$s to be installed and activated: %3$s. <a href="%4$s">Manage plugins</a>.',
            $name,
            count($named) === 1 ? '1 plugin' : count($named) . ' plugins',
            implode(', ', $named),
            \esc_url(\self_admin_url('plugins.php')),
        );
        return new Refusal('plugin_missing_dependencies', "<p>{$message}</p>", $unmet);
    }

    /** The installed plugin a slug names: the first file in its folder with a plugin header, else a single-file plugin of that name. */
    private static function installed(string $slug): ?string
    {
        foreach ([...(glob(WP_PLUGIN_DIR . "/{$slug}/*.php") ?: []), WP_PLUGIN_DIR . "/{$slug}.php"] as $file) {
            if (FileHeaders::values($file, ['Plugin Name'])['Plugin Name'] !== '') {
                return substr($file, strlen(WP_PLUGIN_DIR) + 1);
            }
        }
        return null;
    }
}
