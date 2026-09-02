<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\RestError;
use Minn\Support\FileHeaders;
use Minn\Support\Serialized;

/**
 * Update offers from wordpress.org for the site's plugins and themes: the
 * directory's update-check endpoints asked with the installed headers, the
 * answer kept in the minn_updates option (JSON) for twelve hours, and the
 * offers applied by downloading the release archive through the one
 * package unpacker. A plugin or theme the directory does not know keeps
 * its folder untouched and is never offered anything. The per-item
 * auto-update lists are the site's own auto_update_plugins and
 * auto_update_themes options, in the shape the app already reads.
 */
final class Updates
{
    public const OPTION = 'minn_updates';
    public const TTL = 12 * 3600;

    private const PLUGINS_API = 'https://api.wordpress.org/plugins/update-check/1.1/';
    private const THEMES_API = 'https://api.wordpress.org/themes/update-check/1.1/';
    private const PACKAGE_HOST = 'https://downloads.wordpress.org/';

    private ?array $state = null;

    public function __construct(
        private readonly Site $site,
        private readonly Inventory $inventory,
        private readonly Packages $packages,
        private readonly string $contentDir,
        private readonly string $home,
        private readonly string $wpVersion,
    ) {
    }

    /** The stored answer, refreshed when older than the TTL or absent. */
    public function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }
        $stored = json_decode((string) ($this->site->option(self::OPTION) ?? ''), true);
        if (is_array($stored) && (int) ($stored['checked'] ?? 0) > time() - self::TTL) {
            return $this->state = $stored;
        }
        return $this->refresh();
    }

    /** Asks wordpress.org now, whatever the cache says, and keeps the answer. */
    public function refresh(): array
    {
        return $this->state = $this->check();
    }

    /** Asks the directory now and stores the answer. */
    public function check(): array
    {
        $plugins = [];
        $active = Serialized::stringList($this->site->option('active_plugins'));
        foreach ($this->pluginVersions() as $file => $version) {
            $plugins[$file] = ['Version' => $version];
        }
        $themes = [];
        foreach ($this->themeHeaders() as $slug => $h) {
            $themes[$slug] = [
                'Name' => $h['Theme Name'], 'Title' => $h['Theme Name'], 'Version' => $h['Version'],
                'Author' => $h['Author'], 'Author URI' => $h['Author URI'],
                'Template' => $h['Template'] === '' ? $slug : $h['Template'], 'Stylesheet' => $slug,
            ];
        }
        $stylesheet = (string) ($this->site->option('stylesheet') ?? '');
        $locale = (string) ($this->site->option('WPLANG') ?: 'en_US');

        $pluginAnswer = $plugins === [] ? [] : $this->post(self::PLUGINS_API, [
            'plugins' => json_encode(['plugins' => $plugins, 'active' => array_values($active)]),
            'translations' => '{}',
            'locale' => json_encode([$locale]),
            'all' => 'true',
        ]);
        $themeAnswer = $themes === [] ? [] : $this->post(self::THEMES_API, [
            'themes' => json_encode(['active' => $stylesheet, 'themes' => $themes]),
            'translations' => '{}',
            'locale' => json_encode([$locale]),
        ]);
        $stored = json_decode((string) ($this->site->option(self::OPTION) ?? ''), true);
        $state = [
            'checked' => time(),
            'plugins' => self::map($pluginAnswer['plugins'] ?? []),
            'no_update' => self::map($pluginAnswer['no_update'] ?? []),
            'themes' => self::map($themeAnswer['themes'] ?? []),
            'themes_current' => self::map($themeAnswer['no_update'] ?? []),
            // What was installed is a record, not an answer from the directory; it rides across every check.
            'archives' => is_array($stored['archives'] ?? null) ? $stored['archives'] : [],
        ];
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
        return $state;
    }

    /** Plugin file => offered version, only where the installed version is older. @return array<string, string> */
    public function pluginOffers(): array
    {
        $installed = $this->pluginVersions();
        $out = [];
        foreach ($this->state()['plugins'] as $file => $offer) {
            $new = (string) ($offer['new_version'] ?? '');
            if ($new !== '' && isset($installed[$file]) && version_compare($installed[$file], $new, '<')) {
                $out[$file] = $new;
            }
        }
        return $out;
    }

    /** Stylesheet => offered version. @return array<string, string> */
    public function themeOffers(): array
    {
        $installed = $this->themeHeaders();
        $out = [];
        foreach ($this->state()['themes'] as $slug => $offer) {
            $new = (string) ($offer['new_version'] ?? '');
            if ($new !== '' && isset($installed[$slug]) && version_compare($installed[$slug]['Version'], $new, '<')) {
                $out[$slug] = $new;
            }
        }
        return $out;
    }

    /** Plugin file => slug, icon, directory URL, for every plugin the directory knows. @return array<string, array{slug: string, icon: string, url: string}> */
    public function pluginMeta(): array
    {
        $state = $this->state();
        $out = [];
        foreach ([$state['plugins'], $state['no_update']] as $bucket) {
            foreach ($bucket as $file => $data) {
                $icons = is_array($data['icons'] ?? null) ? $data['icons'] : [];
                $slug = (string) ($data['slug'] ?? '') ?: dirname((string) $file);
                $out[$file] = [
                    'slug' => $slug,
                    'icon' => (string) ($icons['svg'] ?? $icons['2x'] ?? $icons['1x'] ?? ''),
                    'url' => self::safeUrl((string) ($data['url'] ?? '')) ?: 'https://wordpress.org/plugins/' . $slug . '/',
                ];
            }
        }
        return $out;
    }

    /** Whether wordpress.org knows this theme. */
    public function themeOnDirectory(string $stylesheet): bool
    {
        $state = $this->state();
        return isset($state['themes'][$stylesheet]) || isset($state['themes_current'][$stylesheet]);
    }

    /** Applies the offer for one plugin file; returns the installed version afterwards. */
    public function updatePlugin(string $file): string
    {
        if (!isset($this->pluginOffers()[$file])) {
            $this->refresh();
        }
        if (!isset($this->pluginOffers()[$file])) {
            throw new RestError('no_update', 'No update available for that plugin.', 400);
        }
        $this->install((string) ($this->state()['plugins'][$file]['package'] ?? ''), 'plugin', dirname($file));
        $this->consume('plugins', 'no_update', $file);
        return $this->pluginVersions()[$file] ?? '';
    }

    /** Updates one theme to its offer; the new version. */
    public function updateTheme(string $stylesheet): string
    {
        if (!isset($this->themeOffers()[$stylesheet])) {
            $this->refresh();
        }
        if (!isset($this->themeOffers()[$stylesheet])) {
            throw new RestError('no_update', 'No update available for that theme.', 400);
        }
        $this->install((string) ($this->state()['themes'][$stylesheet]['package'] ?? ''), 'theme', $stylesheet);
        $this->consume('themes', 'themes_current', $stylesheet);
        return $this->themeHeaders()[$stylesheet]['Version'] ?? '';
    }

    /** The auto-update list for plugins or themes, trimmed to what is installed. @return list<string> */
    public function auto(string $type): array
    {
        $known = $type === 'plugin' ? array_keys($this->pluginVersions()) : array_keys($this->themeHeaders());
        return array_values(array_intersect(Serialized::stringList($this->site->option("auto_update_{$type}s")), $known));
    }

    /**
     * Turns auto-updates on or off for one plugin or theme.
     *
     * @return list<string> the list after the change
     */
    public function enableAuto(string $type, string $asset): array
    {
        return $this->saveAuto($type, $asset, [...$this->auto($type), $asset]);
    }

    /** Takes one plugin or theme off the auto-update list; the list after. */
    public function disableAuto(string $type, string $asset): array
    {
        return $this->saveAuto($type, $asset, array_diff($this->auto($type), [$asset]));
    }

    /**
     * Stores an auto-update list, kept to assets that exist; the asset being
     * changed must be one of them.
     *
     * @param list<string> $list
     * @return list<string>
     */
    private function saveAuto(string $type, string $asset, array $list): array
    {
        $known = $type === 'plugin' ? array_keys($this->pluginVersions()) : array_keys($this->themeHeaders());
        if (!in_array($asset, $known, true)) {
            throw new RestError('minn_auto_updates_unknown', $type === 'plugin' ? 'Unknown plugin.' : 'Unknown theme.', 404);
        }
        $list = array_values(array_unique(array_intersect($list, $known)));
        $this->site->setOption("auto_update_{$type}s", Serialized::serializeStringList($list));
        return $list;
    }

    /** Applies every offer on the auto-update lists; returns what was updated. @return list<string> */
    public function runAuto(): array
    {
        $done = [];
        foreach (array_intersect(array_keys($this->pluginOffers()), $this->auto('plugin')) as $file) {
            try {
                $this->updatePlugin($file);
                $done[] = $file;
            } catch (RestError) {
            }
        }
        foreach (array_intersect(array_keys($this->themeOffers()), $this->auto('theme')) as $slug) {
            try {
                $this->updateTheme($slug);
                $done[] = $slug;
            } catch (RestError) {
            }
        }
        return $done;
    }

    /** Plugin file => installed version. @return array<string, string> */
    public function pluginVersions(): array
    {
        $out = [];
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            $out[$relative] = FileHeaders::values($path, ['Version'])['Version'];
        }
        return $out;
    }

    /** Plugin file => Plugin Name. @return array<string, string> */
    public function pluginNames(): array
    {
        $out = [];
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            $out[$relative] = FileHeaders::values($path, ['Plugin Name'])['Plugin Name'];
        }
        return $out;
    }

    /** Stylesheet => style.css headers. @return array<string, array<string, string>> */
    public function themeHeaders(): array
    {
        $dir = "{$this->contentDir}/themes";
        $out = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
            if ($entry[0] === '.' || !is_dir("{$dir}/{$entry}")) {
                continue;
            }
            $headers = FileHeaders::values("{$dir}/{$entry}/style.css", ['Theme Name', 'Version', 'Author', 'Author URI', 'Theme URI', 'Template']);
            if ($headers['Theme Name'] !== '') {
                $out[$entry] = $headers;
            }
        }
        return $out;
    }

    /**
     * Fetches and unpacks an offer's package. Every redirect hop must stay
     * on the wordpress.org download host, and the archive's SHA-256 is kept
     * under "archives" in the state so an audit can ask what code arrived.
     */
    private function install(string $package, string $kind, string $folder): void
    {
        if (!str_starts_with($package, self::PACKAGE_HOST)) {
            throw new RestError('update_failed', 'The offer carries no wordpress.org package.', 500);
        }
        $zip = $this->packages->fetch($package, self::PACKAGE_HOST);
        $result = $this->packages->unpackReplacing($zip, $kind);
        if ($result['folder'] !== $folder) {
            throw new RestError('update_failed', "The package unpacked as {$result['folder']}, not {$folder}.", 500);
        }
        $state = $this->state();
        $state['archives']["{$kind}/{$folder}"] = ['sha256' => hash('sha256', $zip), 'version' => $result['version'], 'package' => $package, 'installed' => time()];
        $this->state = $state;
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    /** An applied offer moves to the current bucket so the next read agrees with the folder. */
    private function consume(string $from, string $to, string $key): void
    {
        $state = $this->state();
        if (isset($state[$from][$key])) {
            $state[$to][$key] = $state[$from][$key];
            unset($state[$from][$key]);
        }
        $this->state = $state;
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, string> $fields */
    private function post(string $url, array $fields): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: WordPress/{$this->wpVersion}; {$this->home}",
                'content' => http_build_query($fields),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RestError('check_failed', 'Could not check for updates.', 500);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RestError('check_failed', 'Could not check for updates.', 500);
        }
        return $decoded;
    }

    /** The directory answers an empty bucket as a list; a map either way. */
    private static function map(mixed $bucket): array
    {
        if (!is_array($bucket) || array_is_list($bucket)) {
            return [];
        }
        return array_filter($bucket, is_array(...));
    }

    private static function safeUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : '';
    }
}
