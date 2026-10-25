<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Texturize;
use Minn\I18n\Gettext;
use Minn\Support\Escape;
use Minn\Support\Paths;

/**
 * A plugin's files as WordPress's plugin functions know them: where a
 * plugin folder really is (a symlinked one noted), a plugin file's name
 * under the plugins folder, its headers as read and as the plugin screens
 * show them, and whether it can be uninstalled.
 */
final class PluginFiles
{
    /** The headers a plugin's main file may carry, by field. */
    private const HEADERS = ['Name' => 'Plugin Name', 'PluginURI' => 'Plugin URI', 'Version' => 'Version', 'Description' => 'Description', 'Author' => 'Author', 'AuthorURI' => 'Author URI', 'TextDomain' => 'Text Domain', 'DomainPath' => 'Domain Path', 'Network' => 'Network', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP', 'UpdateURI' => 'Update URI', 'RequiresPlugins' => 'Requires Plugins'];

    /** @var array<string, list<string>> each plugins folder as named and as it really is, once a process */
    private static array $roots = [];

    /**
     * Where a plugin's folder really is, noted under the folder as given
     * (wp_register_plugin_realpath); a file not on disk notes an empty
     * place. The plugins and must-use folders themselves are refused.
     */
    public static function registerRealpath(mixed $file): bool
    {
        $dir = Paths::normalize(dirname((string) $file));
        if (in_array($dir, [Paths::normalize(WP_PLUGIN_DIR), Paths::normalize(WPMU_PLUGIN_DIR)], true)) {
            return false;
        }
        $real = realpath((string) $file);
        $real = $real === false ? '' : Paths::normalize(dirname($real));
        if ($real !== $dir) {
            $GLOBALS['wp_plugin_paths'][$dir] = $real;
        }
        return true;
    }

    /** A plugin file's name under the plugins or must-use folder (plugin_basename), a symlinked folder read as noted. */
    public static function basename(mixed $file): string
    {
        $file = Paths::normalize((string) $file);
        foreach ((array) ($GLOBALS['wp_plugin_paths'] ?? []) as $expected => $real) {
            $real = Paths::normalize((string) $real);
            if ($real !== '' && str_starts_with($file, $real . '/')) {
                $file = Paths::normalize((string) $expected) . substr($file, strlen($real));
                break;
            }
        }
        foreach ([WP_PLUGIN_DIR, WPMU_PLUGIN_DIR] as $dir) {
            foreach (self::roots($dir) as $root) {
                if (str_starts_with($file, $root . '/')) {
                    return trim(substr($file, strlen($root)), '/');
                }
            }
        }
        return ltrim($file, '/');
    }

    /**
     * A plugins folder as named and as it really is, resolved once a process:
     * plugin_basename runs for every plugin URL and hook a plugin registers,
     * and under open_basedir each realpath checks the whole path again.
     *
     * @return list<string>
     */
    private static function roots(string $dir): array
    {
        if (!isset(self::$roots[$dir])) {
            $named = Paths::normalize($dir);
            self::$roots[$dir] = array_values(array_unique([$named, Paths::normalize((string) (realpath($dir) ?: $dir))]));
        }
        return self::$roots[$dir];
    }

    /**
     * The plugins in the plugins folder, or in a folder of it named from the
     * plugins folder on ("/name"), each by its file relative to the folder
     * read and in name order (get_plugins). The reference keeps each folder's
     * list for the rest of the request in the 'plugins' cache group (probe
     * plugin-list-cache): a later call answers the kept list, even one a
     * plugin set there, until wp_clean_plugins_cache, and a folder that is
     * not there answers nothing and keeps nothing.
     *
     * @return array<string, mixed>
     */
    public static function all(mixed $folder = ''): array
    {
        $folder = (string) $folder;
        $kept = Handoff::cacheGet('plugins', 'plugins');
        $kept = is_array($kept) ? $kept : [];
        if (isset($kept[$folder])) {
            return (array) $kept[$folder];
        }
        $root = rtrim(WP_PLUGIN_DIR . $folder, '/');
        if (!is_dir($root)) {
            return [];
        }
        $out = [];
        foreach (array_merge(glob($root . '/*.php') ?: [], glob($root . '/*/*.php') ?: []) as $file) {
            $data = self::data($file, false, false);
            if ($data['Name'] !== '') {
                $out[substr($file, strlen($root) + 1)] = $data;
            }
        }
        // By name, as the reference lists them (probe plugin-data); a tie keeps file order.
        ksort($out);
        uasort($out, static fn (array $a, array $b): int => strnatcasecmp($a['Name'], $b['Name']));
        $kept[$folder] = $out;
        Handoff::cacheSet('plugins', $kept, 'plugins');
        return $out;
    }

    /**
     * A plugin's headers (get_plugin_data), marked up and translated unless
     * asked not to be; a plugin in its own folder that names no text domain
     * uses the folder's name.
     *
     * @return array<string, mixed>
     */
    public static function data(mixed $pluginFile, mixed $markup = true, mixed $translate = true): array
    {
        $data = FileHeaders::read($pluginFile, self::HEADERS, 'plugin');
        $data['Network'] = strtolower($data['Network']) === 'true';
        $folder = dirname(self::basename((string) $pluginFile));
        if ($data['TextDomain'] === '' && $folder !== '.' && !str_contains($folder, '/')) {
            $data['TextDomain'] = $folder;
        }
        $data['Title'] = $data['Name'];
        $data['AuthorName'] = $data['Author'];
        return $markup || $translate ? self::shown($pluginFile, $data, $markup, $translate) : $data;
    }

    /**
     * The headers as the plugin screens show them
     * (_get_plugin_data_markup_translate). Translated: the plugin's domain
     * loaded from its folder unless it is already, then the name, addresses,
     * version, description and author through it. Always: the text cut to a,
     * abbr, acronym, code, em and strong, the addresses escaped. Marked up:
     * the title and author linked, the description texturized with its "By"
     * line.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function shown(mixed $pluginFile, mixed $data, mixed $markup = true, mixed $translate = true): array
    {
        $file = self::basename((string) $pluginFile);
        $domain = (string) ($data['TextDomain'] ?? '');
        if ($translate && $domain !== '') {
            if (!Runtime::textDomains()->opened($domain)) {
                Translations::loadPlugin($domain, false, dirname($file) . (string) ($data['DomainPath'] ?? ''));
            }
            foreach (['Name', 'PluginURI', 'Description', 'Author', 'AuthorURI', 'Version'] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = is_scalar($data[$field]) ? Gettext::text((string) $data[$field], $domain) : Runtime::hooks()->filter("gettext_{$domain}", [Runtime::hooks()->filter('gettext', [$data[$field], $data[$field], $domain]), $data[$field], $domain]);
                }
            }
        }
        $allowed = ['a' => array_fill_keys(['href', 'title'], true), 'abbr' => ['title' => true], 'acronym' => ['title' => true], 'code' => true, 'em' => true, 'strong' => true];
        foreach (['Name', 'Description', 'Author', 'Version'] as $field) {
            $data[$field] = KsesRules::filter((string) ($data[$field] ?? ''), $allowed);
        }
        $data['Title'] = $data['Name'];
        $data['AuthorName'] = $data['Author'];
        $data['PluginURI'] = Escape::url((string) ($data['PluginURI'] ?? ''));
        $data['AuthorURI'] = Escape::url((string) ($data['AuthorURI'] ?? ''));
        if ($markup) {
            if ($data['PluginURI'] !== '' && $data['Name'] !== '') {
                $data['Title'] = '<a href="' . $data['PluginURI'] . '">' . $data['Name'] . '</a>';
            }
            if ($data['AuthorURI'] !== '' && $data['Author'] !== '') {
                $data['Author'] = '<a href="' . $data['AuthorURI'] . '">' . $data['Author'] . '</a>';
            }
            $data['Description'] = Texturize::apply($data['Description']);
            if ($data['Author'] !== '') {
                $data['Description'] .= sprintf(' <cite>%s</cite>', sprintf(Gettext::text('By %s.'), $data['Author']));
            }
        }
        return $data;
    }

    /** Whether a plugin can be uninstalled (is_uninstallable_plugin): it registered an uninstall callback, or ships an uninstall.php. */
    public static function uninstallable(mixed $plugin): bool
    {
        $file = self::basename($plugin);
        return isset(((array) Runtime::options()->filtered('uninstall_plugins'))[$file]) || file_exists(WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php');
    }
}
