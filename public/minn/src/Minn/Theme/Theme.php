<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;
use Minn\Front\Permalinks;

/**
 * The active block theme on disk, read as data: theme.json, the templates
 * and parts directories, and the patterns index. A child theme's files
 * win and its parent fills in what the child does not define; theme.json
 * merges the same way, with the child's preset lists replacing the
 * parent's whole. The engine reads the site's installed theme the way it
 * reads the site's database; it never runs the theme's PHP.
 */
final class Theme
{
    private ?array $json = null;
    /** @var array<string, string>|null pattern slug => file */
    private ?array $patterns = null;

    public function __construct(
        public readonly string $slug,
        public readonly string $dir,
        public readonly string $uri,
        public readonly ?Theme $parent = null,
    ) {
    }

    public static function active(Site $site, Permalinks $permalinks, string $themesDir): ?self
    {
        $slug = (string) ($site->option('stylesheet') ?? '');
        $parentSlug = (string) ($site->option('template') ?? $slug);
        $parent = $parentSlug !== '' && $parentSlug !== $slug ? self::at($parentSlug, $themesDir, $permalinks) : null;
        $child = self::at($slug, $themesDir, $permalinks, $parent);
        if ($child === null || !is_dir("{$child->dir}/templates") && !($parent !== null && is_dir("{$parent->dir}/templates"))) {
            return null;
        }
        return $child;
    }

    private static function at(string $slug, string $themesDir, Permalinks $permalinks, ?Theme $parent = null): ?self
    {
        if ($slug === '' || !is_file("{$themesDir}/{$slug}/theme.json")) {
            return null;
        }
        return new self($slug, "{$themesDir}/{$slug}", $permalinks->url('/wp-content/themes/' . $slug), $parent);
    }

    /** The theme's own name, and the parent's, for body classes. */
    public function parentSlug(): ?string
    {
        return $this->parent?->slug;
    }

    public function json(): array
    {
        if ($this->json === null) {
            $own = (array) json_decode((string) file_get_contents("{$this->dir}/theme.json"), true);
            $this->json = $this->parent === null ? $own : self::merge($this->parent->json(), $own);
            $this->json = $this->withStylePartials($this->json);
        }
        return $this->json;
    }

    /**
     * A theme's styles/ folder can hold block style variations: a JSON file
     * with blockTypes and a slug (else the file name) whose styles become
     * styles.blocks.{type}.variations.{slug} for each named block type. The
     * parent's partials load first, the child's over them.
     */
    private function withStylePartials(array $json): array
    {
        $dirs = [];
        for ($theme = $this; $theme !== null; $theme = $theme->parent) {
            array_unshift($dirs, $theme->dir);
        }
        foreach ($dirs as $dir) {
            foreach (self::partialFiles("{$dir}/styles") as $file) {
                $partial = json_decode((string) file_get_contents($file), true);
                if (!is_array($partial) || !is_array($partial['blockTypes'] ?? null)) {
                    continue;
                }
                $slug = (string) ($partial['slug'] ?? pathinfo($file, PATHINFO_FILENAME));
                if (!self::safe($slug)) {
                    continue;
                }
                foreach ($partial['blockTypes'] as $type) {
                    if (is_string($type) && preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $type)) {
                        $json['styles']['blocks'][$type]['variations'][$slug] = (array) ($partial['styles'] ?? []);
                    }
                }
            }
        }
        return $json;
    }

    /** @return list<string> */
    private static function partialFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Layered theme.json: maps merge key by key, lists (palettes, font
     * sizes, template parts) replace as a whole.
     */
    public static function merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            $existing = $base[$key] ?? null;
            $base[$key] = is_array($value) && is_array($existing) && !array_is_list($value) && !array_is_list($existing)
                ? self::merge($existing, $value)
                : $value;
        }
        return $base;
    }

    /** A template or part name is a file name, never a path. */
    private static function safe(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $slug) === 1 && !str_contains($slug, '..');
    }

    public function templateFile(string $slug): ?string
    {
        if (!self::safe($slug)) {
            return null;
        }
        $file = "{$this->dir}/templates/{$slug}.html";
        return is_file($file) ? (string) file_get_contents($file) : $this->parent?->templateFile($slug);
    }

    public function partFile(string $slug): ?string
    {
        if (!self::safe($slug)) {
            return null;
        }
        $file = "{$this->dir}/parts/{$slug}.html";
        return is_file($file) ? (string) file_get_contents($file) : $this->parent?->partFile($slug);
    }

    /** The template-part area declared in theme.json (header, footer, or uncategorized). */
    public function partArea(string $slug): string
    {
        foreach ((array) ($this->json()['templateParts'] ?? []) as $part) {
            if (($part['name'] ?? '') === $slug) {
                return (string) ($part['area'] ?? 'uncategorized');
            }
        }
        return 'uncategorized';
    }

    /** A pattern's markup, with its PHP text subset interpreted; null when unknown. */
    public function pattern(string $slug): ?string
    {
        $file = $this->patternIndex()[$slug] ?? null;
        if ($file === null) {
            return $this->parent?->pattern($slug);
        }
        return PatternText::render((string) file_get_contents($file), $this->uri);
    }

    /** The theme's stylesheet URL when it ships one; a child's own, else nothing (the parent's is not enqueued for it). */
    public function styleUri(): ?string
    {
        return is_file($this->dir . '/style.css') ? $this->uri . '/style.css' : null;
    }

    /** @return array<string, string> */
    private function patternIndex(): array
    {
        if ($this->patterns === null) {
            $this->patterns = [];
            foreach (glob("{$this->dir}/patterns/*.php") ?: [] as $file) {
                $head = (string) file_get_contents($file, false, null, 0, 2000);
                if (preg_match('/^\s*\*\s*Slug:\s*(\S+)/m', $head, $m)) {
                    $this->patterns[$m[1]] = $file;
                }
            }
        }
        return $this->patterns;
    }
}
