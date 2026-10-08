<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\RestError;
use Minn\Support\FileHeaders;
use Minn\Runtime\PluginUpdates;
use Minn\Support\Serialized;

/**
 * Update offers for the site's plugins and themes from the directory, asked
 * through the Minn update service (Ops\Directory), which answers with
 * wordpress.org's own offers and serves their packages: the update-check
 * endpoints asked with the installed headers (each plugin's Name, Version
 * and Update URI, so a plugin that updates from elsewhere is never offered
 * the directory's plugin of the same folder name), the answer kept in the
 * minn_updates option (JSON) for twelve hours, and the offers applied by
 * downloading the release archive through the one package unpacker. A
 * plugin or theme the directory does not know keeps its folder untouched
 * and is never offered anything. The per-item
 * auto-update lists are the site's own auto_update_plugins and
 * auto_update_themes options, in the shape the app already reads.
 *
 * The directory is not the only source. A plugin that hosts itself answers
 * for its own version through the update transient's filter, which is where
 * WordPress reads it too, so Runtime\PluginUpdates asks the runtime the same
 * question and its answer is merged in. Applying such an offer goes through
 * the publisher: a package outside the directory is unpacked only when
 * `upgrader_pre_download` hands back a copy it verified.
 */
final class Updates
{
    public const OPTION = 'minn_updates';
    public const TTL = 12 * 3600;


    private ?array $state = null;

    public function __construct(
        private readonly Site $site,
        private readonly Inventory $inventory,
        private readonly Packages $packages,
        private readonly string $contentDir,
    ) {
    }

    /** The updater over a site's wp-content. */
    public static function forSite(Site $site, string $contentDir): self
    {
        return new self($site, new Inventory($contentDir, $site), new Packages($site, $contentDir), $contentDir);
    }

    /**
     * wp_update_plugins and wp_update_themes: the service asked when the
     * stored answer is old (or a fresh one is wanted), and the offers left
     * in the update transients when they are not there already.
     */
    public function checkForWordPress(string $transient, array $fresh): void
    {
        $state = $fresh === [] ? $this->state() : $this->refresh();
        $published = \get_site_transient($transient);
        if (!is_object($published) || (int) ($published->last_checked ?? 0) !== (int) $state['checked']) {
            $this->publish();
        }
    }

    /** The stored answer, refreshed when older than the TTL or absent. */
    public function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }
        $stored = json_decode((string) ($this->site->option(self::OPTION) ?? ''), true);
        // An answer from another source (wordpress.org, before the update service) is asked again.
        if (is_array($stored) && (int) ($stored['checked'] ?? 0) > time() - self::TTL && ($stored['source'] ?? '') === Directory::BASE) {
            return $this->state = $stored;
        }
        try {
            return $this->refresh();
        } catch (RestError) {
            // The service did not answer: the last answer stands, as the reference's check keeps its own.
            return $this->state = is_array($stored) ? $stored : ['checked' => 0, 'plugins' => [], 'no_update' => [], 'themes' => [], 'themes_current' => [], 'archives' => [], 'supplied' => []];
        }
    }

    /** Asks the directory now, whatever the cache says, and keeps the answer. */
    public function refresh(): array
    {
        return $this->state = $this->check();
    }

    /** Asks the directory now and stores the answer. */
    public function check(): array
    {
        $plugins = [];
        $active = Serialized::stringList($this->site->option('active_plugins'));
        foreach ($this->pluginHeaders() as $file => $headers) {
            $plugins[$file] = $headers;
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

        $pluginAnswer = $plugins === [] ? [] : Directory::post('plugins/update-check/1.1/', [
            'plugins' => json_encode(['plugins' => $plugins, 'active' => array_values($active)]),
            'translations' => '{}',
            'locale' => json_encode([$locale]),
            'all' => 'true',
        ]);
        $themeAnswer = $themes === [] ? [] : Directory::post('themes/update-check/1.1/', [
            'themes' => json_encode(['active' => $stylesheet, 'themes' => $themes]),
            'translations' => '{}',
            'locale' => json_encode([$locale]),
        ]);
        $stored = json_decode((string) ($this->site->option(self::OPTION) ?? ''), true);
        $supplied = $this->supplied(is_array($stored) ? $stored : []);
        $state = [
            'checked' => time(),
            'source' => Directory::BASE,
            // A plugin's own answer wins over the directory's, as it does on the
            // reference: the filter runs last there, and a plugin that hosts
            // itself is the only one who knows its versions.
            'plugins' => array_merge(self::map($pluginAnswer['plugins'] ?? []), $supplied['plugins']),
            'no_update' => array_diff_key(array_merge(self::map($pluginAnswer['no_update'] ?? []), $supplied['no_update']), $supplied['plugins']),
            'themes' => self::map($themeAnswer['themes'] ?? []),
            'themes_current' => self::map($themeAnswer['no_update'] ?? []),
            // What was installed is a record, not an answer from the directory; it rides across every check.
            'archives' => is_array($stored['archives'] ?? null) ? $stored['archives'] : [],
            // Which files answered for themselves, so a check with no runtime keeps their offers.
            'supplied' => array_values(array_unique([...array_keys($supplied['plugins']), ...array_keys($supplied['no_update'])])),
        ];
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
        $this->state = $state;
        $this->publish();
        return $state;
    }

    /**
     * The offers in WordPress's update transients, update_plugins and
     * update_themes, in the shape wp_update_plugins and wp_update_themes
     * leave there: where plugins, the facade's update helpers and the
     * upgraders read them. Written after every check and after an update.
     */
    public function publish(): void
    {
        $state = $this->state();
        $themes = [];
        foreach ($this->themeHeaders() as $slug => $headers) {
            $themes[$slug] = $headers['Version'];
        }
        \set_site_transient('update_plugins', (object) [
            'last_checked' => (int) $state['checked'],
            'response' => array_map(static fn (array $offer): object => (object) $offer, $state['plugins']),
            'translations' => [],
            'no_update' => array_map(static fn (array $offer): object => (object) $offer, $state['no_update']),
            'checked' => $this->pluginVersions(),
        ]);
        \set_site_transient('update_themes', (object) [
            'last_checked' => (int) $state['checked'],
            'checked' => $themes,
            'response' => $state['themes'],
            'no_update' => $state['themes_current'],
            'translations' => [],
        ]);
    }

    /**
     * What the site's own plugins offer for themselves. A check that runs
     * without a booted runtime (a cron trigger that loads no plugins) asks
     * nobody, so the last answer rides across rather than the offer
     * vanishing until the next request.
     *
     * @param array<string, mixed> $stored the previous state
     * @return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}
     */
    private function supplied(array $stored): array
    {
        $installed = $this->pluginVersions();
        $supplied = PluginUpdates::supplied($installed);
        if ($supplied['plugins'] !== [] || $supplied['no_update'] !== []) {
            return $supplied;
        }
        foreach (is_array($stored['supplied'] ?? null) ? $stored['supplied'] : [] as $file) {
            $file = (string) $file;
            if (!isset($installed[$file])) {
                continue;
            }
            foreach (['plugins', 'no_update'] as $bucket) {
                if (is_array($stored[$bucket][$file] ?? null)) {
                    $supplied[$bucket][$file] = $stored[$bucket][$file];
                }
            }
        }
        return $supplied;
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
                    // Only a plugin the directory knows gets a directory link; a
                    // self-hosted one that published no URL gets none.
                    'url' => self::safeUrl((string) ($data['url'] ?? '')) ?: (str_starts_with((string) ($data['id'] ?? ''), 'w.org/') ? 'https://wordpress.org/plugins/' . $slug . '/' : ''),
                ];
            }
        }
        return $out;
    }

    /** Whether the directory knows this theme. */
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
        $this->apply('plugin', $file, (string) ($this->state()['plugins'][$file]['package'] ?? ''));
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
        $this->apply('theme', $stylesheet, (string) ($this->state()['themes'][$stylesheet]['package'] ?? ''));
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

    /**
     * Applies every offer on the auto-update lists: what was updated, and
     * what was refused with the reason, by plugin file or theme slug.
     *
     * @return array{done: list<string>, failed: array<string, string>}
     */
    public function runAuto(AutoUpdates $gate): array
    {
        $done = [];
        $failed = [];
        $plugins = $gate->enabledFor('plugin') ? array_intersect(array_keys($this->pluginOffers()), $this->auto('plugin')) : [];
        $themes = $gate->enabledFor('theme') ? array_intersect(array_keys($this->themeOffers()), $this->auto('theme')) : [];
        foreach ($plugins as $file) {
            try {
                $this->updatePlugin($file);
                $done[] = $file;
            } catch (RestError $refusal) {
                $failed[$file] = $refusal->getMessage();
            }
        }
        foreach ($themes as $slug) {
            try {
                $this->updateTheme($slug);
                $done[] = $slug;
            } catch (RestError $refusal) {
                $failed[$slug] = $refusal->getMessage();
            }
        }
        return ['done' => $done, 'failed' => $failed];
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

    /**
     * Plugin file => the headers the update check sends, in WordPress's keys.
     *
     * @return array<string, array{Name: string, Version: string, UpdateURI: string}>
     */
    private function pluginHeaders(): array
    {
        $out = [];
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            $h = FileHeaders::values($path, ['Plugin Name', 'Version', 'Update URI']);
            $out[$relative] = ['Name' => $h['Plugin Name'], 'Version' => $h['Version'], 'UpdateURI' => $h['Update URI']];
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
     * Applies one offer through the upgrader (UpgraderRun): the update
     * transients carry the offers first, as they do on WordPress when an
     * update starts. The archive's SHA-256 is kept under "archives" in the
     * state when the package came from the update service, so an audit can
     * ask what code arrived.
     */
    private function apply(string $kind, string $item, string $package): void
    {
        $bucket = $kind === 'theme' ? 'update_themes' : 'update_plugins';
        if (!isset(((array) (\get_site_transient($bucket)->response ?? []))[$item])) {
            $this->publish();
        }
        $sha256 = (new UpgraderRun($this->packages))->update($kind, $item, $package);
        $folder = $kind === 'theme' ? $item : dirname($item);
        $state = $this->state();
        $version = $kind === 'theme' ? ($this->themeHeaders()[$item]['Version'] ?? '') : ($this->pluginVersions()[$item] ?? '');
        $state['archives']["{$kind}/{$folder}"] = ['sha256' => $sha256, 'version' => $version, 'package' => $package, 'installed' => time()];
        $this->state = $state;
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    /**
     * An applied offer moves to the current bucket so the next read agrees
     * with the folder, and the offers left go back into the update
     * transients the upgrader cleared, as Minn Admin puts them back on
     * WordPress.
     */
    private function consume(string $from, string $to, string $key): void
    {
        $state = $this->state();
        if (isset($state[$from][$key])) {
            $state[$to][$key] = $state[$from][$key];
            unset($state[$from][$key]);
        }
        $this->state = $state;
        $this->site->setOption(self::OPTION, (string) json_encode($state, JSON_UNESCAPED_SLASHES));
        $this->publish();
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
