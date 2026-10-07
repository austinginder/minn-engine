<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;
use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\Extension\Manifest;
use Minn\Http\Download;
use Minn\RestError;
use Minn\Support\Files;
use Minn\Support\FileHeaders;

/**
 * Putting themes and extensions on disk. Themes come from the directory
 * (wordpress.org's, asked through the Minn update service, Ops\Directory;
 * block themes render on the engine) or an uploaded zip; extensions come
 * from an uploaded zip or a URL, and must carry a minn.json: a WordPress
 * plugin would install but never run, so it is refused with the reason.
 * Every archive is unpacked through one guarded routine: exactly one
 * top-level folder that is a plain name (never "." or ".."), no absolute
 * or dotted paths, no symbolic links, bounded entry count and size, the
 * folder's identity checked and its destination proven to be a direct
 * child of the kind's directory before it is moved into place. Removal
 * proves the same containment before anything is deleted.
 */
final readonly class Packages
{
    /** The largest archive fetched or unpacked, in bytes. */
    private const THEMES_INFO = Directory::BASE . 'themes/info/1.2/';
    private const PLUGINS_INFO = Directory::BASE . 'plugins/info/1.2/';
    private const INFO_OPTION = 'minn_plugin_info';
    private const INFO_TTL = 12 * 3600;

    public function __construct(private Site $site, private string $contentDir)
    {
    }

    /** Directory theme search, or the popular list for an empty query. @return list<array> */
    public function searchThemes(string $query): array
    {
        $args = $query === '' ? 'request[browse]=popular' : 'request[search]=' . rawurlencode($query);
        $json = $this->ask(self::THEMES_INFO . '?action=query_themes&' . $args . '&request[per_page]=12&request[fields][screenshot_url]=1&request[fields][active_installs]=1');
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['themes'])) {
            throw new RestError('themes_api_failed', 'The Minn update service did not answer the theme search.', 502);
        }
        $active = (string) ($this->site->option('stylesheet') ?? '');
        $items = [];
        foreach ((array) $data['themes'] as $theme) {
            $slug = (string) ($theme['slug'] ?? '');
            $shot = (string) ($theme['screenshot_url'] ?? '');
            $items[] = [
                'slug' => $slug,
                'name' => html_entity_decode(strip_tags((string) ($theme['name'] ?? $slug)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'version' => (string) ($theme['version'] ?? ''),
                'screenshot' => str_starts_with($shot, '//') ? 'https:' . $shot : $shot,
                'installs' => (int) ($theme['active_installs'] ?? 0),
                'installed' => is_dir("{$this->contentDir}/themes/{$slug}"),
                'active' => $slug === $active,
            ];
        }
        return $items;
    }

    /**
     * Directory plugin search: twelve per page with icons, short
     * descriptions, install counts, and ratings, plus which results are
     * already installed (by folder). @return array{plugins: list<array>, page: int, pages: int, total: int}
     */
    public function searchPlugins(string $query, int $page): array
    {
        $page = max(1, $page);
        $json = $this->ask(self::PLUGINS_INFO . '?action=query_plugins&' . http_build_query(['request' => [
            'search' => $query,
            'per_page' => 12,
            'page' => $page,
            'fields' => ['icons' => 1, 'short_description' => 1, 'active_installs' => 1, 'rating' => 1],
        ]]));
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['plugins'])) {
            throw new RestError('plugins_api_failed', 'The Minn update service did not answer the plugin search.', 502);
        }
        $installed = [];
        foreach ((new Inventory($this->contentDir, $this->site))->pluginFiles() as $relative => $path) {
            $installed[dirname($relative)] = $relative;
        }
        $items = [];
        foreach ((array) $data['plugins'] as $plugin) {
            $icons = is_array($plugin['icons'] ?? null) ? $plugin['icons'] : [];
            $slug = (string) ($plugin['slug'] ?? '');
            $items[] = [
                'slug' => $slug,
                'name' => self::plain((string) ($plugin['name'] ?? $slug)),
                'description' => self::plain((string) ($plugin['short_description'] ?? '')),
                'installs' => (int) ($plugin['active_installs'] ?? 0),
                'rating' => (int) ($plugin['rating'] ?? 0),
                'version' => (string) ($plugin['version'] ?? ''),
                'icon' => (string) ($icons['1x'] ?? $icons['default'] ?? ''),
                'installed' => $installed[$slug] ?? null,
            ];
        }
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        return ['plugins' => $items, 'page' => $page, 'pages' => (int) ($info['pages'] ?? 1), 'total' => (int) ($info['results'] ?? count($items))];
    }

    /** The slim card for one directory plugin, cached twelve hours per slug. */
    public function pluginInfo(string $slug): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RestError('no_slug', 'Plugin slug is required.', 400);
        }
        $cache = json_decode((string) ($this->site->option(self::INFO_OPTION) ?? ''), true);
        $cache = is_array($cache) ? $cache : [];
        // A card from another source (wordpress.org, before the update service) is asked again: its icon points there.
        if (isset($cache[$slug]['at']) && (int) $cache[$slug]['at'] > time() - self::INFO_TTL && is_array($cache[$slug]['card'] ?? null) && ($cache[$slug]['source'] ?? '') === Directory::BASE) {
            return $cache[$slug]['card'];
        }
        $plugin = $this->directoryPlugin($slug);
        if ($plugin === null) {
            throw new RestError('plugins_api_failed', 'Plugin not found.', 404);
        }
        $icons = is_array($plugin['icons'] ?? null) ? $plugin['icons'] : [];
        $card = [
            'slug' => $slug,
            'name' => self::plain((string) ($plugin['name'] ?? $slug)),
            'author' => self::plain((string) ($plugin['author'] ?? '')),
            'description' => self::plain((string) ($plugin['short_description'] ?? '')),
            'installs' => (int) ($plugin['active_installs'] ?? 0),
            'version' => (string) ($plugin['version'] ?? ''),
            'rating' => (int) ($plugin['rating'] ?? 0),
            'icon' => (string) ($icons['2x'] ?? $icons['1x'] ?? $icons['default'] ?? ''),
            'source' => 'wporg',
        ];
        $cache[$slug] = ['at' => time(), 'card' => $card, 'source' => Directory::BASE];
        if (count($cache) > 50) {
            uasort($cache, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);
            $cache = array_slice($cache, 0, 50, true);
        }
        $this->site->setOption(self::INFO_OPTION, (string) json_encode($cache, JSON_UNESCAPED_SLASHES));
        return $card;
    }

    /** Installs a directory plugin by slug; returns its folder. */
    public function installPlugin(string $slug, string $version = ''): string
    {
        return $this->unpack($this->fetch($this->pluginPackage($slug, $version), Directory::ORIGIN), 'plugin')['folder'];
    }

    /** Installs a directory plugin over the folder already there. */
    public function replacePlugin(string $slug, string $version = ''): string
    {
        return $this->unpackReplacing($this->fetch($this->pluginPackage($slug, $version), Directory::ORIGIN), 'plugin')['folder'];
    }

    /** The download link of a directory plugin (on the update service), at a version when one is asked for. */
    private function pluginPackage(string $slug, string $version): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): slug', 400, ['params' => ['slug' => 'Invalid parameter.']]);
        }
        $data = $this->directoryPlugin($slug);
        if ($data === null) {
            throw new RestError('plugins_api_failed', 'Plugin not found.', 404);
        }
        $link = (string) ($data['download_link'] ?? '');
        if ($version !== '') {
            if (!preg_match('/^[0-9][A-Za-z0-9._-]*$/', $version)) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): version', 400, ['params' => ['version' => 'Invalid parameter.']]);
            }
            $link = Directory::PACKAGES . 'plugin/' . $slug . '.' . $version . '.zip';
        }
        if ($link === '' || !str_starts_with($link, Directory::PACKAGES)) {
            throw new RestError('rest_plugin_install_failed', 'The plugin has no download link in the directory.', 500);
        }
        return $link;
    }

    /**
     * One directory plugin record, or null when the slug is unknown.
     *
     * @return array<string, mixed>|null
     */
    public function directoryPlugin(string $slug): ?array
    {
        $json = $this->ask(self::PLUGINS_INFO . '?action=plugin_information&' . http_build_query(['request' => [
            'slug' => $slug,
            'fields' => ['short_description' => 1, 'icons' => 1, 'active_installs' => 1, 'rating' => 1, 'download_link' => 1, 'sections' => 0, 'description' => 0, 'reviews' => 0, 'ratings' => 0, 'tags' => 0, 'contributors' => 0],
        ]]));
        $data = json_decode($json, true);
        if (!is_array($data) || isset($data['error']) || !isset($data['slug'])) {
            return null;
        }
        return $data;
    }

    /**
     * The directory's answer to a plugins_api() action, its request passed
     * as given (Ops\PluginsApi); null when it did not answer. One of the
     * directory calls Track H moves behind the Minn update service.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>|null
     */
    public function pluginsAction(string $action, array $request): ?array
    {
        try {
            $data = json_decode($this->ask(self::PLUGINS_INFO . '?' . http_build_query(['action' => $action, 'request' => $request])), true);
        } catch (RestError) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    /** Directory text as the app shows it: tags stripped, entities decoded. */
    private static function plain(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * A page of directory themes for `wp theme search`.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function queryThemes(string $search, int $page, int $perPage): array
    {
        $json = $this->ask(self::THEMES_INFO . '?action=query_themes&' . http_build_query(['request' => [
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
        ]]));
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['themes'])) {
            throw new RestError('themes_api_failed', 'The Minn update service did not answer the theme search.', 502);
        }
        $items = [];
        foreach ((array) $data['themes'] as $theme) {
            if (!is_array($theme)) {
                continue;
            }
            $slug = (string) ($theme['slug'] ?? '');
            if ($slug !== '' && !isset($theme['url'])) {
                $theme['url'] = 'https://wordpress.org/themes/' . $slug . '/';
            }
            $items[] = $theme;
        }
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        return ['items' => $items, 'total' => (int) ($info['results'] ?? count($items))];
    }

    /**
     * A page of directory plugins for `wp plugin search`.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function queryPlugins(string $search, int $page, int $perPage): array
    {
        $json = $this->ask(self::PLUGINS_INFO . '?action=query_plugins&' . http_build_query(['request' => [
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
        ]]));
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['plugins'])) {
            throw new RestError('plugins_api_failed', 'The Minn update service did not answer the plugin search.', 502);
        }
        $items = [];
        foreach ((array) $data['plugins'] as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            $slug = (string) ($plugin['slug'] ?? '');
            if ($slug !== '' && !isset($plugin['url'])) {
                $plugin['url'] = 'https://wordpress.org/plugins/' . $slug . '/';
            }
            $items[] = $plugin;
        }
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        return ['items' => $items, 'total' => (int) ($info['results'] ?? count($items))];
    }

    /**
     * One directory theme record, or null when the slug is unknown.
     *
     * @return array<string, mixed>|null
     */
    public function directoryTheme(string $slug): ?array
    {
        $data = json_decode($this->ask(self::THEMES_INFO . '?action=theme_information&request[slug]=' . rawurlencode($slug) . '&request[fields][download_link]=1'), true);
        if (!is_array($data) || isset($data['error']) || !isset($data['slug'])) {
            return null;
        }
        return $data;
    }

    /** Installs a directory theme by slug; returns its stylesheet folder. */
    public function installTheme(string $slug, string $version = ''): string
    {
        return $this->unpack($this->fetch($this->themePackage($slug, $version), Directory::ORIGIN), 'theme')['folder'];
    }

    /** Installs a directory theme over the folder already there. */
    public function replaceTheme(string $slug, string $version = ''): string
    {
        return $this->unpackReplacing($this->fetch($this->themePackage($slug, $version), Directory::ORIGIN), 'theme')['folder'];
    }

    /** The download link of a directory theme (on the update service), at a version when one is asked for. */
    private function themePackage(string $slug, string $version): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RestError('bad_slug', 'That is not a theme slug.', 400);
        }
        $data = $this->directoryTheme($slug);
        $link = (string) ($data['download_link'] ?? '');
        if ($version !== '') {
            if (!preg_match('/^[0-9][A-Za-z0-9._-]*$/', $version)) {
                throw new RestError('bad_slug', 'That is not a theme version.', 400);
            }
            $link = Directory::PACKAGES . 'theme/' . $slug . '.' . $version . '.zip';
        }
        if ($data === null || $link === '' || !str_starts_with($link, Directory::PACKAGES)) {
            throw new RestError('theme_not_found', 'The directory has no theme by that slug.', 404);
        }
        return $link;
    }

    /**
     * Unpacks an uploaded or downloaded archive into wp-content/themes or
     * wp-content/plugins. @return array{folder: string, name: string, version: string, kind: string}
     */
    public function unpack(string $zip, string $kind): array
    {
        return $this->place($zip, $kind, function (string $dest, string $kind, array $identity): void {
            $current = $this->describe($dest, $kind);
            throw new RestError('folder_exists', 'Destination folder already exists.', 409, [
                'destination' => $dest,
                'current_name' => $current['name'],
                'current_version' => $current['version'],
                'new_name' => $identity['name'],
                'new_version' => $identity['version'],
            ]);
        });
    }

    /** Unpacks a zip over a folder already there, replacing it whole. */
    public function unpackReplacing(string $zip, string $kind): array
    {
        return $this->place($zip, $kind, static fn (string $dest): mixed => Files::deleteTree($dest));
    }

    /**
     * Unpacks a zip into wp-content; $onExisting decides what happens to a
     * folder already at the destination (refuse, or clear it).
     *
     * @param Closure(string, string, array): mixed $onExisting
     */
    private function place(string $zip, string $kind, Closure $onExisting): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'minn-pkg-');
        file_put_contents($tmp, $zip);
        $stage = $tmp . '-dir';
        try {
            $source = Archive::unpackFolder($tmp, $stage);
            $top = basename($source);
            $identity = $this->identify($source, $kind);
            $dest = $this->contained($kind, $top);
            if (is_dir($dest)) {
                $onExisting($dest, $kind, $identity);
            }
            if (!rename($source, $dest)) {
                throw new RestError('install_failed', 'The folder could not be moved into place.', 500);
            }
            return ['folder' => $top] + $identity;
        } finally {
            @unlink($tmp);
            if (is_dir($stage)) {
                Files::deleteTree($stage);
            }
        }
    }

    /** Removes a theme or plugin folder that is not in use. */
    public function remove(string $kind, string $folder): void
    {
        if (!Archive::isFolderName($folder)) {
            throw new RestError('bad_slug', 'That is not a folder name.', 400);
        }
        $dir = $this->contained($kind, $folder);
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        if (!is_dir($dir)) {
            throw new RestError('not_found', ucfirst($kind) . ' not found.', 404);
        }
        $kindDir = realpath(dirname($dir));
        if ($kindDir === false || dirname((string) realpath($dir)) !== $kindDir) {
            throw new RestError('bad_slug', 'That folder is not inside ' . basename($kindDir ?: dirname($dir)) . '.', 400);
        }
        Files::deleteTree($dir);
    }

    /** The path a folder of this kind lives at, proven to be a direct child of the kind's directory. */
    private function contained(string $kind, string $folder): string
    {
        $kindDir = "{$this->contentDir}/" . ($kind === 'theme' ? 'themes' : 'plugins');
        $path = "{$kindDir}/{$folder}";
        if (!Archive::isFolderName($folder) || dirname($path) !== $kindDir) {
            throw new RestError('bad_slug', 'That is not a folder name.', 400);
        }
        return $path;
    }

    /**
     * What a folder is: a theme (style.css with a Theme Name), a Minn
     * extension (minn.json), or a WordPress plugin, which is named so the
     * refusal can say what was uploaded. @return array{name: string, version: string, kind: string}
     */
    private function identify(string $dir, string $kind): array
    {
        $identity = $this->describe($dir, $kind);
        if ($identity['kind'] === 'unknown') {
            throw new RestError(
                $kind === 'theme' ? 'not_theme' : 'not_plugin',
                $kind === 'theme' ? 'The archive is not a theme: no style.css with a Theme Name.' : 'The archive is not a plugin: no minn.json and no file with a Plugin Name header in its folder.',
                400,
            );
        }
        return $identity;
    }

    /** What a folder holds by its headers; kind "unknown" when nothing identifies it. @return array{name: string, version: string, kind: string} */
    private function describe(string $dir, string $kind): array
    {
        if ($kind === 'theme') {
            $headers = FileHeaders::values("{$dir}/style.css", ['Theme Name', 'Version']);
            return ['name' => $headers['Theme Name'], 'version' => $headers['Version'], 'kind' => $headers['Theme Name'] === '' ? 'unknown' : 'theme'];
        }
        $manifest = Manifest::read($dir);
        if ($manifest !== null) {
            return ['name' => $manifest->name, 'version' => $manifest->version, 'kind' => 'extension'];
        }
        foreach (glob("{$dir}/*.php") ?: [] as $file) {
            $headers = FileHeaders::values($file, ['Plugin Name', 'Version']);
            if ($headers['Plugin Name'] !== '') {
                return ['name' => $headers['Plugin Name'], 'version' => $headers['Version'], 'kind' => 'plugin'];
            }
        }
        return ['name' => '', 'version' => '', 'kind' => 'unknown'];
    }

    /** A directory answer from the Minn update service, every redirect hop staying on it. */
    private function ask(string $url): string
    {
        return $this->fetch($url, Directory::ORIGIN);
    }

    /**
     * A package over https, every redirect hop included, refusing anything
     * else; when host prefixes are given, every hop must start with one.
     * The request names the engine, never the site's address.
     */
    public function fetch(string $url, string ...$hostPrefixes): string
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RestError('bad_url', 'Packages are fetched over https only.', 400);
        }
        try {
            return Download::https($url, Archive::MAX_BYTES, array_values($hostPrefixes), Directory::userAgent());
        } catch (\RuntimeException $e) {
            throw new RestError('download_failed', $e->getMessage(), 502);
        }
    }
}
