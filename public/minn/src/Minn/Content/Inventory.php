<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Support\FileHeaders;
use Minn\Support\Serialized;

/**
 * Plugins, themes, must-use plugins, and drop-ins as they sit on disk.
 * The shapes match `wp plugin list` / `wp theme list` captured from the
 * reference: name is the directory (or the drop-in filename), title and
 * version come from the file headers, status from the options.
 */
final readonly class Inventory
{
    /** Drop-in filenames at wp-content/ that hosting tools treat as WordPress drop-ins. */
    private const DROPINS = [
        'advanced-cache.php',
        'db.php',
        'db-error.php',
        'fatal-error-handler.php',
        'install.php',
        'maintenance.php',
        'object-cache.php',
        'php-error.php',
        'sunrise.php',
    ];

    public function __construct(private string $contentDir, private Site $site)
    {
    }

    /**
     * Regular plugins, then must-use, then drop-ins, each group sorted by name.
     *
     * @return list<array<string, mixed>>
     */
    public function plugins(): array
    {
        return array_merge($this->regularPlugins(), $this->mustUse(), $this->dropins());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mustUse(): array
    {
        $dir = $this->contentDir . '/mu-plugins';
        if (!is_dir($dir)) {
            return [];
        }
        $items = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.php') || $entry[0] === '.') {
                continue;
            }
            $file = $dir . '/' . $entry;
            if (!is_file($file)) {
                continue;
            }
            $headers = FileHeaders::values($file, ['Plugin Name', 'Version']);
            $name = basename($entry, '.php');
            $items[$name] = self::item(
                $name,
                $headers['Plugin Name'] !== '' ? $headers['Plugin Name'] : $name,
                'must-use',
                $headers['Version'],
                false,
                false,
            );
        }
        ksort($items, SORT_STRING);
        return array_values($items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dropins(): array
    {
        $items = [];
        foreach (self::DROPINS as $file) {
            $path = $this->contentDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $headers = FileHeaders::values($path, ['Plugin Name']);
            // Drop-ins are named for the file; the reference leaves version empty.
            $items[] = self::item(
                $file,
                $headers['Plugin Name'] !== '' ? $headers['Plugin Name'] : $file,
                'dropin',
                '',
                false,
                false,
            );
        }
        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function themes(): array
    {
        $dir = $this->contentDir . '/themes';
        if (!is_dir($dir)) {
            return [];
        }
        $stylesheet = (string) ($this->site->option('stylesheet') ?? '');
        $template = (string) ($this->site->option('template') ?? $stylesheet);
        $items = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry[0] === '.' || !is_dir($dir . '/' . $entry)) {
                continue;
            }
            $style = $dir . '/' . $entry . '/style.css';
            $headers = FileHeaders::values($style, ['Theme Name', 'Version']);
            if ($headers['Theme Name'] === '') {
                continue;
            }
            $status = 'inactive';
            if ($entry === $stylesheet) {
                $status = 'active';
            } elseif ($entry === $template && $template !== $stylesheet) {
                $status = 'parent';
            }
            $items[$entry] = self::item($entry, $headers['Theme Name'], $status, $headers['Version'], 'none', false);
        }
        ksort($items, SORT_STRING);
        return array_values($items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function regularPlugins(): array
    {
        $dir = $this->contentDir . '/plugins';
        if (!is_dir($dir)) {
            return [];
        }
        $active = array_flip(Serialized::stringList($this->site->option('active_plugins')));
        $auto = array_flip(Serialized::stringList($this->site->option('auto_update_plugins')));
        $items = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry[0] === '.' || $entry === 'index.php') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path) && str_ends_with($entry, '.php')) {
                $relative = $entry;
                $name = basename($entry, '.php');
                $file = $path;
            } elseif (is_dir($path)) {
                $file = self::mainPluginFile($path, $entry);
                if ($file === null) {
                    continue;
                }
                $relative = $entry . '/' . basename($file);
                $name = $entry;
            } else {
                continue;
            }
            $headers = FileHeaders::values($file, ['Plugin Name', 'Version']);
            if ($headers['Plugin Name'] === '') {
                continue;
            }
            $items[$name] = self::item(
                $name,
                $headers['Plugin Name'],
                isset($active[$relative]) ? 'active' : 'inactive',
                $headers['Version'],
                'none',
                isset($auto[$relative]),
            );
        }
        ksort($items, SORT_STRING);
        return array_values($items);
    }

    private static function mainPluginFile(string $dir, string $slug): ?string
    {
        $preferred = $dir . '/' . $slug . '.php';
        $fallback = null;
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $headers = FileHeaders::values($file, ['Plugin Name']);
            if ($headers['Plugin Name'] === '') {
                continue;
            }
            if ($file === $preferred) {
                return $file;
            }
            $fallback ??= $file;
        }
        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(string $name, string $title, string $status, string $version, string|false $update, bool $auto): array
    {
        return [
            'name' => $name,
            'title' => $title,
            'status' => $status,
            'update' => $update,
            'version' => $version,
            'update_version' => '',
            'auto_update' => $auto ? 'on' : 'off',
        ];
    }
}
