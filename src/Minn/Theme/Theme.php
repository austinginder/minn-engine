<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;
use Minn\Front\Permalinks;

/**
 * The active block theme on disk, read as data: theme.json, the templates
 * and parts directories, and the patterns index. The engine reads the
 * site's installed theme the way it reads the site's database; it never
 * runs the theme's PHP.
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
    ) {
    }

    public static function active(Site $site, Permalinks $permalinks, string $themesDir): ?self
    {
        $slug = (string) ($site->option('stylesheet') ?? '');
        if ($slug === '' || !is_file("{$themesDir}/{$slug}/theme.json") || !is_dir("{$themesDir}/{$slug}/templates")) {
            return null;
        }
        return new self($slug, "{$themesDir}/{$slug}", $permalinks->url('/wp-content/themes/' . $slug));
    }

    public function json(): array
    {
        return $this->json ??= (array) json_decode((string) file_get_contents("{$this->dir}/theme.json"), true);
    }

    public function templateFile(string $slug): ?string
    {
        $file = "{$this->dir}/templates/{$slug}.html";
        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function partFile(string $slug): ?string
    {
        $file = "{$this->dir}/parts/{$slug}.html";
        return is_file($file) ? (string) file_get_contents($file) : null;
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
            return null;
        }
        return PatternText::render((string) file_get_contents($file), $this->uri);
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
