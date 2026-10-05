<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The text domains this request knows: each domain's files in the order they
 * were loaded, and the domains looked for and not found (known, with no
 * files), all in the order they first appeared, which is the order a locale
 * switch reloads them in. A lookup asks a domain's files in load order and
 * the first one with a translation answers, each under its own plural rule.
 * The registry also remembers the folder a plugin or theme named for its
 * domain (and how files there are named), and the domains unloaded for good.
 */
final class TextDomains
{
    /** @var array<string, array<string, Catalog>> domain => file => catalog */
    private array $loaded = [];

    /** @var array<string, array{0: string, 1: bool}> domain => [folder, files named by locale alone] */
    private array $folders = [];

    /** @var array<string, true> */
    private array $closed = [];

    /** Adds a loaded file to a domain; a file already loaded there is not added twice. */
    public function add(string $domain, string $file, Catalog $catalog): void
    {
        $this->loaded[$domain][$file] ??= $catalog;
        unset($this->closed[$domain]);
    }

    /** Whether any file is loaded for the domain. */
    public function has(string $domain): bool
    {
        return ($this->loaded[$domain] ?? []) !== [];
    }

    /** Forgets a domain and its files; true when it had any. */
    public function unload(string $domain): bool
    {
        $had = $this->has($domain);
        unset($this->loaded[$domain]);
        return $had;
    }

    /** Records that a domain was asked for, so it is looked for once and reloaded on a switch. */
    public function markKnown(string $domain): void
    {
        $this->loaded[$domain] ??= [];
    }

    /** Whether the domain was loaded or looked for. */
    public function known(string $domain): bool
    {
        return array_key_exists($domain, $this->loaded);
    }

    /**
     * Every known domain, in the order it first appeared.
     *
     * @return list<string>
     */
    public function knownDomains(): array
    {
        return array_map('strval', array_keys($this->loaded));
    }

    /** Drops one file from a domain; true when it was loaded. */
    public function unloadFile(string $domain, string $file): bool
    {
        $had = isset($this->loaded[$domain][$file]);
        unset($this->loaded[$domain][$file]);
        return $had;
    }

    /**
     * The domain's files and catalogs, first loaded first.
     *
     * @return array<string, Catalog>
     */
    public function catalogs(string $domain): array
    {
        return $this->loaded[$domain] ?? [];
    }

    /** The first loaded translation of a key in the domain, or null. */
    public function translate(string $domain, string $key): ?string
    {
        foreach ($this->catalogs($domain) as $catalog) {
            $translation = $catalog->translate($key);
            if ($translation !== null) {
                return $translation;
            }
        }
        return null;
    }

    /** The first loaded plural form of a key for a count, or null. */
    public function translatePlural(string $domain, string $key, int $count): ?string
    {
        foreach ($this->catalogs($domain) as $catalog) {
            if ($catalog->has($key)) {
                return $catalog->translatePlural($key, $count);
            }
        }
        return null;
    }

    /** Records the folder a plugin or theme named for its domain; files there are "{domain}-{locale}". */
    public function rememberFolder(string $domain, string $folder): void
    {
        $this->folders[$domain] = [rtrim($folder, '/'), false];
    }

    /** Records the active theme's own language folder; files there are named by locale alone. */
    public function rememberThemeFolder(string $domain, string $folder): void
    {
        $this->folders[$domain] = [rtrim($folder, '/'), true];
    }

    /** The .mo path a domain's named folder would hold for a locale, if a folder was named. */
    public function folderFile(string $domain, string $locale): ?string
    {
        if (!isset($this->folders[$domain])) {
            return null;
        }
        [$folder, $byLocale] = $this->folders[$domain];
        return $folder . '/' . ($byLocale ? $locale : "{$domain}-{$locale}") . '.mo';
    }

    /** Keeps an unloaded domain from loading again on its own. */
    public function close(string $domain): void
    {
        $this->closed[$domain] = true;
    }

    /** Whether the domain was unloaded for good. */
    public function closed(string $domain): bool
    {
        return isset($this->closed[$domain]);
    }

    /**
     * The domains with files loaded.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return array_keys(array_filter($this->loaded));
    }
}
