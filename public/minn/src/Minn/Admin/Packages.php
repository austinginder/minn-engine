<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Extension\Manifest;
use Minn\RestError;
use Minn\Support\FileHeaders;

/**
 * Putting themes and extensions on disk. Themes come from wordpress.org
 * (block themes render on the engine) or an uploaded zip; extensions come
 * from an uploaded zip or a URL, and must carry a minn.json: a WordPress
 * plugin would install but never run, so it is refused with the reason.
 * Every archive is unpacked through one guarded routine: exactly one
 * top-level folder, no absolute or dotted paths, the folder's identity
 * checked before it is moved into place.
 */
final readonly class Packages
{
    private const WPORG_THEMES = 'https://api.wordpress.org/themes/info/1.2/';

    public function __construct(private Site $site, private string $contentDir)
    {
    }

    /** wordpress.org theme search, or the popular list for an empty query. @return list<array> */
    public function searchThemes(string $query): array
    {
        $args = $query === '' ? 'request[browse]=popular' : 'request[search]=' . rawurlencode($query);
        $json = $this->fetch(self::WPORG_THEMES . '?action=query_themes&' . $args . '&request[per_page]=12&request[fields][screenshot_url]=1&request[fields][active_installs]=1');
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['themes'])) {
            throw new RestError('themes_api_failed', 'wordpress.org did not answer the theme search.', 502);
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

    /** Installs a wordpress.org theme by slug; returns its stylesheet folder. */
    public function installTheme(string $slug): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RestError('bad_slug', 'That is not a theme slug.', 400);
        }
        $data = json_decode($this->fetch(self::WPORG_THEMES . '?action=theme_information&request[slug]=' . rawurlencode($slug) . '&request[fields][download_link]=1'), true);
        $link = (string) ($data['download_link'] ?? '');
        if ($link === '' || !str_starts_with($link, 'https://downloads.wordpress.org/')) {
            throw new RestError('theme_not_found', 'wordpress.org has no theme by that slug.', 404);
        }
        return $this->unpack($this->fetch($link), 'theme', false)['folder'];
    }

    /**
     * Unpacks an uploaded or downloaded archive into wp-content/themes or
     * wp-content/plugins. @return array{folder: string, name: string, version: string, kind: string}
     */
    public function unpack(string $zip, string $kind, bool $overwrite): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'minn-pkg-');
        file_put_contents($tmp, $zip);
        $stage = $tmp . '-dir';
        try {
            $archive = new \ZipArchive();
            if ($archive->open($tmp) !== true) {
                throw new RestError('not_zip', 'The archive could not be opened.', 400);
            }
            $top = null;
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = (string) $archive->getNameIndex($i);
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_starts_with($name, '__MACOSX/')) {
                    if (!str_starts_with($name, '__MACOSX/')) {
                        throw new RestError('bad_archive', 'The archive holds a path that leaves its folder.', 400);
                    }
                    continue;
                }
                $first = explode('/', $name, 2)[0];
                if ($top !== null && $first !== $top) {
                    throw new RestError('bad_archive', 'The archive must hold exactly one folder.', 400);
                }
                $top = $first;
            }
            if ($top === null || $top === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $top)) {
                throw new RestError('bad_archive', 'The archive must hold exactly one folder.', 400);
            }
            mkdir($stage, 0755, true);
            if (!$archive->extractTo($stage)) {
                throw new RestError('extract_failed', 'The archive could not be unpacked.', 500);
            }
            $archive->close();
            $source = "{$stage}/{$top}";
            $identity = $this->identify($source, $kind);
            $dest = "{$this->contentDir}/" . ($kind === 'theme' ? 'themes' : 'plugins') . "/{$top}";
            if (is_dir($dest) && !$overwrite) {
                $current = $this->identify($dest, $kind, false);
                throw new RestError('folder_exists', 'Destination folder already exists.', 409, [
                    'destination' => $dest,
                    'current_name' => $current['name'],
                    'current_version' => $current['version'],
                    'new_name' => $identity['name'],
                    'new_version' => $identity['version'],
                ]);
            }
            if (is_dir($dest)) {
                self::removeTree($dest);
            }
            if (!rename($source, $dest)) {
                throw new RestError('install_failed', 'The folder could not be moved into place.', 500);
            }
            return ['folder' => $top] + $identity;
        } finally {
            @unlink($tmp);
            if (is_dir($stage)) {
                self::removeTree($stage);
            }
        }
    }

    /** Removes a theme or plugin folder that is not in use. */
    public function remove(string $kind, string $folder): void
    {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $folder)) {
            throw new RestError('bad_slug', 'That is not a folder name.', 400);
        }
        $dir = "{$this->contentDir}/" . ($kind === 'theme' ? 'themes' : 'plugins') . "/{$folder}";
        if (!is_dir($dir)) {
            throw new RestError('not_found', ucfirst($kind) . ' not found.', 404);
        }
        self::removeTree($dir);
    }

    /**
     * What a folder is: a theme (style.css with a Theme Name), a Minn
     * extension (minn.json), or a WordPress plugin, which is named so the
     * refusal can say what was uploaded. @return array{name: string, version: string, kind: string}
     */
    private function identify(string $dir, string $kind, bool $strict = true): array
    {
        if ($kind === 'theme') {
            $headers = FileHeaders::values("{$dir}/style.css", ['Theme Name', 'Version']);
            if ($headers['Theme Name'] === '' && $strict) {
                throw new RestError('not_theme', 'The archive is not a theme: no style.css with a Theme Name.', 400);
            }
            return ['name' => $headers['Theme Name'], 'version' => $headers['Version'], 'kind' => 'theme'];
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
        if ($strict) {
            throw new RestError('not_plugin', 'The archive is not a plugin: no minn.json and no file with a Plugin Name header in its folder.', 400);
        }
        return ['name' => '', 'version' => '', 'kind' => 'unknown'];
    }

    public function fetch(string $url): string
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RestError('bad_url', 'Packages are fetched over https only.', 400);
        }
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'user_agent' => 'Minn Engine/' . MINN_ENGINE_VERSION], 'ssl' => ['verify_peer' => true]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            throw new RestError('download_failed', 'The download failed. Check the site can reach ' . (string) parse_url($url, PHP_URL_HOST) . ' and try again.', 502);
        }
        return $body;
    }

    private static function removeTree(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
