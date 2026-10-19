<?php

declare(strict_types=1);

namespace Minn\Theme;

use Closure;
use Minn\Html;
use Minn\I18n\Gettext;
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

    /**
     * What keeps the folder from being a whole theme, as the reference names
     * it (probe theme-headers): no folder; no stylesheet; itself named as
     * its parent; a parent that is not there; or, standing alone, no
     * index.php and no templates/index.html. Null for a whole theme.
     *
     * @return array{0: string, 1: string}|null the error code and message
     */
    public function problem(): ?array
    {
        $dir = $this->dir();
        $named = ($this->headers['Template'] ?? '') !== '';
        return match (true) {
            !is_dir($dir) => ['theme_not_found', sprintf(Gettext::text('The theme directory "%s" does not exist.'), Html::esc($this->slug))],
            !$this->exists => ['theme_no_stylesheet', Gettext::text('Stylesheet is missing.')],
            $named && $this->template === $this->slug => ['theme_child_invalid', Gettext::text('The theme defines itself as its parent theme. Please check the <code>Template</code> header.')],
            $named && !is_file("{$this->root}/{$this->template}/style.css") => ['theme_no_parent', sprintf(Gettext::text('The parent theme is missing. Please install the "%s" parent theme.'), Html::esc($this->template))],
            !$named && !is_file("{$dir}/index.php") && !is_file("{$dir}/templates/index.html") => ['theme_no_index', Gettext::text('Template is missing. Standalone themes need to have a <code>templates/index.html</code> or <code>index.php</code> template file. <a href="https://developer.wordpress.org/themes/advanced-topics/child-themes/">Child themes</a> need to have a <code>Template</code> header in the <code>style.css</code> stylesheet.')],
            default => null,
        };
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
