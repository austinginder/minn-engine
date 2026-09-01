<?php

declare(strict_types=1);

namespace Minn\Theme;

use Closure;
use Minn\Support\FileHeaders;

/** A theme folder read from disk: its style.css headers, which folder its templates come from, its screenshot, whether it is a block theme. */
final readonly class Folder
{
    private const SCREENSHOTS = ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];

    /** @param array<string, string> $headers keyed by the caller's labels */
    private function __construct(public string $root, public string $slug, public bool $exists, public array $headers, public string $template)
    {
    }

    /**
     * A theme folder with its style.css headers.
     *
     * @param array<string, string> $labels key => the style.css header label
     * @param Closure(string, array<string, string>): array<string, string>|null $reader the header reader (the facade's, so header filters apply); the engine's own by default
     */
    public static function read(string $root, string $slug, array $labels, ?Closure $reader = null): self
    {
        $file = "{$root}/{$slug}/style.css";
        if (!is_file($file)) {
            $headers = array_fill_keys(array_keys($labels), '');
            $headers['Name'] = $slug;
            return new self($root, $slug, false, $headers, $slug);
        }
        if ($reader !== null) {
            $headers = $reader($file, $labels);
        } else {
            $values = FileHeaders::values($file, array_values($labels));
            $headers = [];
            foreach ($labels as $key => $label) {
                $headers[$key] = $values[$label];
            }
        }
        return new self($root, $slug, true, $headers, ($headers['Template'] ?? '') !== '' ? $headers['Template'] : $slug);
    }

    /** The folder's path. */
    public function dir(): string
    {
        return "{$this->root}/{$this->slug}";
    }

    /** The screenshot file name, or null when the theme has none. */
    public function screenshot(): ?string
    {
        foreach (self::SCREENSHOTS as $extension) {
            if (is_file($this->dir() . '/screenshot.' . $extension)) {
                return 'screenshot.' . $extension;
            }
        }
        return null;
    }

    /**
     * Whether any of the folders ships a block template index.
     *
     * @param list<string> $dirs the stylesheet and template directories
     */
    public static function isBlockTheme(array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if (is_file($dir . '/templates/index.html') || is_file($dir . '/block-templates/index.html')) {
                return true;
            }
        }
        return false;
    }

    /** The first of the stylesheet and template directories that holds the file, or the template directory's path. */
    public static function filePath(string $stylesheetDir, string $templateDir, string $file): string
    {
        $file = ltrim($file, '/');
        if ($file === '') {
            return $stylesheetDir;
        }
        return is_file("{$stylesheetDir}/{$file}") ? "{$stylesheetDir}/{$file}" : "{$templateDir}/{$file}";
    }
}
