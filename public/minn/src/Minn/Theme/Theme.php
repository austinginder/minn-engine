<?php


declare(strict_types=1);

namespace Minn\Theme;

use Minn\Runtime\Runtime;

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

    /** The active block theme, or null under a classic one. */
    public static function active(Site $site, Permalinks $permalinks, string $themesDir): ?self
    {
        $child = self::forStyles($site, $permalinks, $themesDir);
        // A block theme ships a block template index; theme.json plus an
        // empty templates/ directory is a classic theme with editor tokens
        // (wp_is_block_theme on the reference draws the same line).
        if ($child === null || !is_file("{$child->dir}/templates/index.html") && !($child->parent !== null && is_file("{$child->parent->dir}/templates/index.html"))) {
            return null;
        }
        return $child;
    }

    /**
     * The active theme as styling data (theme.json present), whether or not
     * it is a block theme; the classic renderer prints its presets where the
     * reference prints a classic theme's global styles.
     */
    public static function forStyles(Site $site, Permalinks $permalinks, string $themesDir): ?self
    {
        $slug = (string) ($site->option('stylesheet') ?? '');
        $parentSlug = (string) ($site->option('template') ?? $slug);
        $parent = $parentSlug !== '' && $parentSlug !== $slug ? self::at($parentSlug, $themesDir, $permalinks) : null;
        return self::at($slug, $themesDir, $permalinks, $parent);
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

    /** The theme.json with the parent's merged in; with plugins loaded, through wp_theme_json_data_theme. */
    public function json(): array
    {
        return Runtime::booted() ? ThemeJsonData::theme($this->rawJson(), $this->dir) : $this->rawJson();
    }

    /** The theme.json as written, the parent's merged in. */
    private function rawJson(): array
    {
        if ($this->json === null) {
            $own = (array) json_decode((string) file_get_contents("{$this->dir}/theme.json"), true);
            $this->json = $this->parent === null ? $own : self::merge($this->parent->rawJson(), $own);
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
        foreach ($this->styleFiles() as $file) {
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
        return $json;
    }

    /**
     * Every JSON file under styles/, the parent theme's first and each
     * theme's in path order: block style partials and style variations alike.
     *
     * @return list<string>
     */
    public function styleFiles(): array
    {
        $files = [];
        for ($theme = $this; $theme !== null; $theme = $theme->parent) {
            $files = [...self::partialFiles("{$theme->dir}/styles"), ...$files];
        }
        return $files;
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

    /** A template file's markup, or null. */
    public function templateFile(string $slug): ?string
    {
        $path = $this->templatePath($slug);
        return $path === null ? null : (string) file_get_contents($path);
    }

    /** The file a template is read from: this theme's, else its parent's; null when neither has one. */
    public function templatePath(string $slug): ?string
    {
        if (!self::safe($slug)) {
            return null;
        }
        $file = "{$this->dir}/templates/{$slug}.html";
        return is_file($file) ? $file : $this->parent?->templatePath($slug);
    }

    /** A template part file's markup, or null. */
    public function partFile(string $slug): ?string
    {
        $path = $this->partPath($slug);
        return $path === null ? null : (string) file_get_contents($path);
    }

    /** The file a template part is read from: this theme's, else its parent's; null when neither has one. */
    public function partPath(string $slug): ?string
    {
        if (!self::safe($slug)) {
            return null;
        }
        $file = "{$this->dir}/parts/{$slug}.html";
        return is_file($file) ? $file : $this->parent?->partPath($slug);
    }

    /**
     * The theme's display name, as the stylesheet header states it; a child
     * theme answers with its own. Falls back to theme.json's title, then the
     * folder name.
     */
    public function name(): string
    {
        $head = is_file("{$this->dir}/style.css")
            ? (string) file_get_contents("{$this->dir}/style.css", false, null, 0, 8192)
            : '';
        if (preg_match('/^[ \t\/*#@]*Theme Name:\s*(.+)$/mi', $head, $match) === 1) {
            return trim($match[1]);
        }
        return (string) ($this->json()['title'] ?? $this->slug);
    }

    /**
     * Every template (or part) slug the theme offers, the child's files
     * first and the parent's after, each in the order the directory hands
     * them back: the reference walks these directories unsorted and its
     * template list carries that order through.
     *
     * @return list<string>
     */
    public function fileSlugs(string $folder): array
    {
        $slugs = [];
        for ($theme = $this; $theme !== null; $theme = $theme->parent) {
            foreach (self::htmlFiles("{$theme->dir}/{$folder}") as $slug) {
                if (!in_array($slug, $slugs, true)) {
                    $slugs[] = $slug;
                }
            }
        }
        return $slugs;
    }

    /** @return list<string> */
    private static function htmlFiles(string $dir): array
    {
        $slugs = [];
        foreach (@scandir($dir, SCANDIR_SORT_NONE) ?: [] as $entry) {
            if (str_ends_with($entry, '.html') && self::safe($entry)) {
                $slugs[] = substr($entry, 0, -5);
            }
        }
        return $slugs;
    }

    /** A template title and description the theme declares for a custom template slug. */
    public function customTemplate(string $slug): ?array
    {
        foreach ((array) ($this->json()['customTemplates'] ?? []) as $template) {
            if (($template['name'] ?? '') === $slug) {
                return $template;
            }
        }
        return null;
    }

    /** The title theme.json gives a template part, when it names one. */
    public function partTitle(string $slug): ?string
    {
        foreach ((array) ($this->json()['templateParts'] ?? []) as $part) {
            if (($part['name'] ?? '') === $slug) {
                return isset($part['title']) ? (string) $part['title'] : null;
            }
        }
        return null;
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

    /**
     * A pattern's declared Block Types and Categories, the header fields a
     * plugin reads to tell (say) a header pattern from any other.
     *
     * @return array{blockTypes: list<string>, categories: list<string>}
     */
    public function patternMeta(string $slug): array
    {
        $file = $this->patternIndex()[$slug] ?? null;
        if ($file === null) {
            return $this->parent?->patternMeta($slug) ?? ['blockTypes' => [], 'categories' => []];
        }
        $head = (string) file_get_contents($file, false, null, 0, 2000);
        $list = static function (string $field) use ($head): array {
            if (preg_match('/^\s*\*\s*' . $field . ':\s*(.+)$/m', $head, $m) !== 1) {
                return [];
            }
            return array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        };
        return ['blockTypes' => $list('Block Types'), 'categories' => $list('Categories')];
    }

    /**
     * The header fields a pattern declares, as the metadata attribute the
     * reference writes onto the pattern's first block when it splices the
     * pattern into a template. A field the header omits is omitted here.
     *
     * @return array{patternName: string, name: string, description?: string, categories?: list<string>}|null
     */
    public function patternHeader(string $slug): ?array
    {
        $file = $this->patternIndex()[$slug] ?? null;
        if ($file === null) {
            return $this->parent?->patternHeader($slug);
        }
        $head = (string) file_get_contents($file, false, null, 0, 2000);
        $field = static function (string $name) use ($head): ?string {
            return preg_match('/^\s*\*\s*' . $name . ':\s*(.+)$/m', $head, $match) === 1 ? trim($match[1]) : null;
        };
        $header = ['patternName' => $slug, 'name' => (string) ($field('Title') ?? '')];
        $description = $field('Description');
        if ($description !== null) {
            $header['description'] = $description;
        }
        $categories = $field('Categories');
        if ($categories !== null) {
            $header['categories'] = array_values(array_filter(array_map('trim', explode(',', $categories))));
        }
        return $header;
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
