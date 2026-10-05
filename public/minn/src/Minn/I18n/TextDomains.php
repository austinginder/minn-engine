<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The text domains loaded for this request: each domain's files in the order
 * they were loaded. A lookup asks the files in that order and the first one
 * with a translation answers, each file under its own plural rule. The
 * registry also remembers the folders plugins and themes named for their
 * domains, the domains unloaded for good, and which just-in-time lookups
 * have already been tried, so a missing file is looked for once.
 */
final class TextDomains
{
    /** @var array<string, array<string, Catalog>> domain => file => catalog */
    private array $loaded = [];

    /** @var array<string, string> */
    private array $folders = [];

    /** @var array<string, true> */
    private array $closed = [];

    /** @var array<string, true> */
    private array $tried = [];

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

    /** Drops a domain's files; true when there were any. */
    public function unload(string $domain): bool
    {
        $had = $this->has($domain);
        unset($this->loaded[$domain]);
        return $had;
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

    /** Records the folder a plugin or theme named for its domain's files. */
    public function rememberFolder(string $domain, string $folder): void
    {
        $this->folders[$domain] = rtrim($folder, '/');
    }

    /** The folder named for a domain, if any. */
    public function folder(string $domain): ?string
    {
        return $this->folders[$domain] ?? null;
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

    /** True the first time a domain is looked for; false after, until the locale changes. */
    public function firstTry(string $domain): bool
    {
        if (isset($this->tried[$domain])) {
            return false;
        }
        return $this->tried[$domain] = true;
    }

    /** Forgets every lookup tried, for a new locale. */
    public function forgetTries(): void
    {
        $this->tried = [];
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
