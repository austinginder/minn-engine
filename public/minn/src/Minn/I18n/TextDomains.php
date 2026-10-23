<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The text domains this request knows: each domain's files in the order they
 * were loaded, and the domains looked for and not found (known, with no
 * files), all in the order they first appeared, which is the order a locale
 * switch reloads them in. Each file is kept with the locales it was loaded
 * for, and a lookup for a locale asks only those files (probe
 * translation-locales): the domain's files in load order, the first one with
 * a translation answering, each under its own plural rule.
 * The registry also remembers the folder a plugin or theme named for its
 * domain (and how files there are named), and the domains unloaded for good.
 */
final class TextDomains
{
    /** @var array<string, array<string, Catalog>> domain => file => catalog */
    private array $loaded = [];

    /** @var array<string, array<string, array<string, int>>> domain => file => the locales it was loaded for, each with its turn */
    private array $locales = [];

    /** How many files have been loaded for a locale so far: a file's turn among a locale's files. */
    private int $turns = 0;

    /** The locale lookups are made in: the translation controller's, en_US until it is set. */
    private string $locale = 'en_US';

    /** @var array<string, array{0: string, 1: bool}> domain => [folder, files named by locale alone] */
    private array $folders = [];

    /** @var list<string> the active theme's folders */
    private array $themes = [];

    /** @var array<string, true> */
    private array $closed = [];

    /** @var array<string, true> domains named a folder since they were last looked for in vain */
    private array $lookAgain = [];

    /** @var array<string, true> domains load_textdomain() loaded, a file that did not parse included */
    private array $accepted = [];

    /** Sets the locale lookups are made in (the translation controller's set_locale). */
    public function useLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /** The locale lookups are made in. */
    public function locale(): string
    {
        return $this->locale;
    }

    /** Adds a loaded file to a domain for a locale; a file already loaded there is not added twice, only its locale noted. */
    public function add(string $domain, string $file, Catalog $catalog, string $locale): void
    {
        $this->loaded[$domain][$file] ??= $catalog;
        $this->locales[$domain][$file][$locale] ??= ++$this->turns;
        unset($this->closed[$domain]);
    }

    /** Whether any file is loaded for the domain, for the locale when one is given. */
    public function has(string $domain, ?string $locale = null): bool
    {
        return $this->catalogs($domain, $locale) !== [];
    }

    /** Forgets a domain and its files; true when it had any. */
    public function unload(string $domain): bool
    {
        $had = $this->has($domain);
        unset($this->loaded[$domain], $this->locales[$domain], $this->accepted[$domain]);
        return $had;
    }

    /**
     * Records that load_textdomain() loaded a file for the domain. A file
     * that does not parse adds nothing to look up, yet the reference's
     * global functions count its domain as loaded, while its translation
     * controller does not (probe translations-early-wplang).
     */
    public function accept(string $domain): void
    {
        $this->accepted[$domain] = true;
    }

    /** Whether the global functions count the domain as loaded: a file loaded, or one load_textdomain() accepted. */
    public function opened(string $domain): bool
    {
        return $this->has($domain) || isset($this->accepted[$domain]);
    }

    /** Records that a domain was asked for, so it is looked for once and reloaded on a switch. */
    public function markKnown(string $domain): void
    {
        $this->loaded[$domain] ??= [];
        unset($this->lookAgain[$domain]);
    }

    /**
     * Whether a translation lookup should look for the domain's files: it was
     * never looked for, or nothing was found and a folder has been named for
     * it since (the reference looks again after each load_plugin_textdomain;
     * probe plugin-data).
     */
    public function waiting(string $domain): bool
    {
        return !$this->known($domain) || isset($this->lookAgain[$domain]);
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

    /**
     * Drops one file from a domain for a locale, the first it was loaded for
     * when none is given (probe translation-locales); the file stays for its
     * other locales. True when it was loaded.
     */
    public function unloadFile(string $domain, string $file, ?string $locale = null): bool
    {
        $had = isset($this->loaded[$domain][$file]);
        $locales = $this->locales[$domain][$file] ?? [];
        asort($locales);
        unset($this->locales[$domain][$file][$locale ?? (string) array_key_first($locales)]);
        if (($this->locales[$domain][$file] ?? []) === []) {
            unset($this->loaded[$domain][$file], $this->locales[$domain][$file]);
        }
        return $had;
    }

    /**
     * The domain's files and catalogs, first loaded first: those loaded for
     * the locale when one is given, in the order they were loaded for it,
     * else all of them.
     *
     * @return array<string, Catalog>
     */
    public function catalogs(string $domain, ?string $locale = null): array
    {
        $catalogs = $this->loaded[$domain] ?? [];
        if ($locale === null) {
            return $catalogs;
        }
        $turns = array_filter(array_map(static fn (array $locales): ?int => $locales[$locale] ?? null, $this->locales[$domain] ?? []), 'is_int');
        asort($turns);
        return array_intersect_key(array_replace($turns, $catalogs), $turns);
    }

    /** The first loaded translation of a key in the domain (among the locale's files when one is given), or null. */
    public function translate(string $domain, string $key, ?string $locale = null): ?string
    {
        foreach ($this->catalogs($domain, $locale) as $catalog) {
            $translation = $catalog->translate($key);
            if ($translation !== null) {
                return $translation;
            }
        }
        return null;
    }

    /**
     * The first loaded plural form for a count (among the locale's files when
     * one is given), or null: each key is looked for in every file before the
     * next key (the full "singular NUL plural" key, then the singular alone).
     *
     * @param list<string> $keys
     */
    public function translatePlural(string $domain, array $keys, int $count, ?string $locale = null): ?string
    {
        foreach ($keys as $key) {
            foreach ($this->catalogs($domain, $locale) as $catalog) {
                if ($catalog->has($key)) {
                    return $catalog->translatePlural($key, $count);
                }
            }
        }
        return null;
    }

    /**
     * Records the folder a plugin or theme named for its domain: files there
     * are "{domain}-{locale}", or named by the locale alone at or below the
     * active theme's folder, whichever domain names it (probe
     * text-domain-timing).
     */
    public function rememberFolder(string $domain, string $folder): void
    {
        $folder = rtrim($folder, '/');
        $inTheme = array_filter($this->themes, static fn (string $theme): bool => str_starts_with($folder . '/', $theme . '/')) !== [];
        $this->folders[$domain] = [$folder, $inTheme];
        $this->lookAgainUnlessLoaded($domain);
    }

    /** Records a folder of the active theme (its template's or its stylesheet's). */
    public function rememberTheme(string $folder): void
    {
        $this->themes[] = rtrim($folder, '/');
    }

    private function lookAgainUnlessLoaded(string $domain): void
    {
        if ($this->known($domain) && !$this->has($domain)) {
            $this->lookAgain[$domain] = true;
        }
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
