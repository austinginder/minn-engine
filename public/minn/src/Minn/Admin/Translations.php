<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Content\Users;
use Minn\RestError;

/**
 * Languages for the admin. A person's locale is their `locale` user meta,
 * else the site's WPLANG, else en_US, exactly as the reference resolves it.
 * The app translates itself from a map of its source strings, read from the
 * JED catalogs Minn Admin's language packs install under
 * wp-content/languages/plugins (the bundle's own languages folder is the
 * fallback). Installing a pack fetches the release asset the bundle's
 * manifest names for that locale, checks its hash, and unpacks only that
 * locale's files, so an eject leaves the files WordPress would have written.
 */
final readonly class Translations
{
    private const RTL = ['ar', 'ary', 'azb', 'ckb', 'dv', 'fa_AF', 'fa_IR', 'haz', 'he_IL', 'ps', 'skr', 'ug_CN', 'ur'];

    public function __construct(
        private Users $users,
        private Site $site,
        private App $app,
        private string $contentDir,
    ) {
    }

    /** The locale a person reads the admin in. */
    public function localeOf(int $userId): string
    {
        $own = trim((string) ($this->users->meta($userId, 'locale') ?? ''));
        if ($own !== '') {
            return $own;
        }
        return $this->siteLocale();
    }

    /** The site's locale from WPLANG, en_US by default. */
    public function siteLocale(): string
    {
        $site = trim((string) ($this->site->option('WPLANG') ?? ''));
        return $site === '' ? 'en_US' : $site;
    }

    /** Whether a locale reads right to left. */
    public static function isRtl(string $locale): bool
    {
        return in_array($locale, self::RTL, true);
    }

    /**
     * The app's translation map for a locale: source string => translation,
     * or the plural forms as a list. Later folders win, so the installed
     * pack lands over the bundle's fallback.
     *
     * @return array{0: array<string, string|list<string>>, 1: string} map and the Plural-Forms rule
     */
    public function catalog(string $locale): array
    {
        if ($locale === 'en_US') {
            return [[], ''];
        }
        $map = [];
        $rule = '';
        foreach ($this->catalogFiles($locale) as $file) {
            $jed = json_decode((string) file_get_contents($file), true);
            $messages = (array) ($jed['locale_data']['messages'] ?? []);
            $head = (array) ($messages[''] ?? []);
            $rule = (string) ($head['plural-forms'] ?? $head['plural_forms'] ?? $rule);
            foreach ($messages as $key => $forms) {
                if ($key === '' || !is_array($forms) || (string) ($forms[0] ?? '') === '') {
                    continue;
                }
                $map[(string) $key] = count($forms) > 1 ? array_values(array_map('strval', $forms)) : (string) $forms[0];
            }
        }
        return [$map, $rule];
    }

    /** Whether a locale has any files on this site: a core pack or Minn Admin's own. */
    public function isInstalled(string $locale): bool
    {
        return $locale === 'en_US' || in_array($locale, $this->installedCodes(), true);
    }

    /**
     * The locales with a pack on disk, the two defaults first.
     *
     * @return list<array{0: string, 1: string}> the site-default row, en_US, then every installed locale
     */
    public function installed(): array
    {
        $names = $this->names();
        $out = [['', 'Site default'], ['en_US', 'English (United States)']];
        foreach ($this->installedCodes() as $code) {
            $out[] = [$code, $names[$code] ?? $code];
        }
        return $out;
    }

    /** The languages route's payload; `current` is the raw meta of the user asked about. */
    public function payload(int $forUser): array
    {
        return $this->languages($forUser, true);
    }

    /** The same payload for a caller who may switch languages but not install one: no available list. */
    public function readOnlyPayload(int $forUser): array
    {
        return $this->languages($forUser, false);
    }

    private function languages(int $forUser, bool $canInstall): array
    {
        $installed = $this->installed();
        $have = array_flip(array_column($installed, 0));
        $available = [];
        foreach ($this->names() as $code => $name) {
            if (!isset($have[$code])) {
                $available[] = [$code, $name];
            }
        }
        usort($available, static fn (array $a, array $b): int => strcasecmp($a[1], $b[1]));
        return [
            'installed' => $installed,
            'available' => $canInstall ? $available : [],
            'canInstall' => $canInstall,
            'current' => trim((string) ($this->users->meta($forUser, 'locale') ?? '')),
            'site' => trim((string) ($this->site->option('WPLANG') ?? '')),
        ];
    }

    /**
     * Fetches and unpacks Minn Admin's language pack for a locale from the
     * release the bundle's manifest names. True when files were written;
     * false when the manifest offers no pack for the locale.
     */
    public function install(string $locale): bool
    {
        if (!preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,}(_[a-z]+)?)?$/', $locale)) {
            throw new RestError('minn_language_install', 'That is not a locale code.', 400);
        }
        $manifest = $this->app->file('manifest.json');
        $entries = $manifest === null ? [] : (array) (json_decode((string) file_get_contents($manifest), true)['translations'] ?? []);
        $entry = null;
        foreach ($entries as $row) {
            if (($row['language'] ?? '') === $locale) {
                $entry = $row;
            }
        }
        if ($entry === null || empty($entry['package'])) {
            return false;
        }
        $dir = "{$this->contentDir}/languages/plugins";
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new RestError('minn_language_install', 'The languages folder cannot be created.', 500);
        }
        if (!is_writable($dir)) {
            throw new RestError('minn_language_install', 'The languages folder is not writable.', 500);
        }
        if (empty($entry['sha256'])) {
            throw new RestError('minn_language_install', 'The manifest names no hash for that language pack, so it cannot be verified.', 500);
        }
        $zip = self::download((string) $entry['package']);
        if (!hash_equals((string) $entry['sha256'], hash('sha256', $zip))) {
            throw new RestError('minn_language_install', 'The language pack did not match the hash the manifest names.', 500);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'minn-lang-');
        file_put_contents($tmp, $zip);
        try {
            $archive = new \ZipArchive();
            if ($archive->open($tmp) !== true) {
                throw new RestError('minn_language_install', 'The language pack could not be opened.', 500);
            }
            $written = 0;
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = basename((string) $archive->getNameIndex($i));
                // Only this locale's files, and only the shapes a pack carries.
                if (!str_starts_with($name, "minn-admin-{$locale}") || !preg_match('/\.(json|mo|po|l10n\.php)$/', $name)) {
                    continue;
                }
                $content = $archive->getFromIndex($i);
                if ($content !== false && file_put_contents("{$dir}/{$name}", $content) !== false) {
                    $written++;
                }
            }
            $archive->close();
        } finally {
            @unlink($tmp);
        }
        if ($written === 0) {
            throw new RestError('minn_language_install', 'The language pack held no files for that locale.', 500);
        }
        return true;
    }

    /** @return list<string> */
    private function catalogFiles(string $locale): array
    {
        $files = [];
        foreach ([$this->app->dir() . '/languages', "{$this->contentDir}/languages/plugins"] as $dir) {
            foreach (glob("{$dir}/minn-admin-{$locale}-*.json") ?: [] as $file) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /** @return list<string> locale codes with a core pack or a Minn Admin pack on disk, sorted */
    private function installedCodes(): array
    {
        $codes = [];
        foreach (glob("{$this->contentDir}/languages/*.{mo,l10n.php}", GLOB_BRACE) ?: [] as $file) {
            $name = basename($file);
            if (preg_match('/^([a-z]{2,3}(?:_[A-Za-z]{2,}(?:_[a-z]+)?)?)\.(mo|l10n\.php)$/', $name, $m)) {
                $codes[$m[1]] = true;
            }
        }
        foreach (glob("{$this->contentDir}/languages/plugins/minn-admin-*") ?: [] as $file) {
            if (preg_match('/^minn-admin-([a-z]{2,3}(?:_[A-Za-z]{2,}(?:_[a-z]+)?)?)(?:-[0-9a-f]{32})?\.(json|mo|l10n\.php|po)$/', basename($file), $m)) {
                $codes[$m[1]] = true;
            }
        }
        unset($codes['en_US']);
        $codes = array_keys($codes);
        sort($codes, SORT_STRING);
        return $codes;
    }

    /** Native names for every locale the registry lists, from the captured catalog. @return array<string, string> */
    private function names(): array
    {
        $data = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/languages.json'), true);
        $names = [];
        foreach (array_merge((array) ($data['available'] ?? []), (array) ($data['installed'] ?? [])) as $pair) {
            if (is_array($pair) && ($pair[0] ?? '') !== '') {
                $names[(string) $pair[0]] = (string) $pair[1];
            }
        }
        return $names;
    }

    private static function download(string $url): string
    {
        try {
            return \Minn\Http\Download::https($url, 64 * 1048576, [], 'Minn Engine/' . MINN_ENGINE_VERSION);
        } catch (\RuntimeException $e) {
            throw new RestError('minn_language_install', 'The language pack could not be downloaded. ' . $e->getMessage(), 500);
        }
    }
}
