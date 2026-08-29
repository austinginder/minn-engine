<?php

declare(strict_types=1);

namespace Minn\Extension;

/**
 * minn.json, read as data. A folder under wp-content/plugins (or
 * mu-plugins) with this file is an extension; the same folder may also be
 * a WordPress plugin, in which case the two share a name and a version.
 *
 *   {
 *     "name": "Block Visibility for Minn",
 *     "version": "1.0.0",
 *     "license": "MIT",
 *     "replaces": ["block-visibility/block-visibility.php"],
 *     "autoload": {"Minn\\Ext\\BlockVisibility\\": "src/"},
 *     "extension": "Minn\\Ext\\BlockVisibility\\Extension",
 *     "shortcodes": ["example_tag"],
 *     "blocks": ["vendor/block"],
 *     "types": [{"slug": "zz_note", "name": "Notes", "rest_base": "zz-note"}]
 *   }
 *
 * "replaces" names the WordPress plugin files whose behaviour this
 * extension stands in for; the extension is active whenever one of them is
 * in active_plugins, or when its own folder is listed there, or when it is
 * named in the minn_active_extensions option. "shortcodes" and "blocks"
 * are the content tokens preflight treats as provided instead of missing.
 * "types" are extra post types the engine should serve on wp/v2.
 */
final readonly class Manifest
{
    /**
     * @param list<string> $replaces
     * @param array<string, string> $autoload namespace prefix => directory
     * @param list<string> $shortcodes
     * @param list<string> $blocks
     * @param list<array<string, mixed>> $types
     */
    public function __construct(
        public string $slug,
        public string $dir,
        public string $name,
        public string $version,
        public string $license,
        public array $replaces,
        public array $autoload,
        public string $extension,
        /** what of the replaced plugin this provides, when not all of it */
        public string $covers = '',
        public array $shortcodes = [],
        public array $blocks = [],
        public array $types = [],
    ) {
    }

    public static function read(string $dir): ?self
    {
        $file = $dir . '/minn.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !is_string($data['extension'] ?? null)) {
            return null;
        }
        return new self(
            basename($dir),
            $dir,
            (string) ($data['name'] ?? basename($dir)),
            (string) ($data['version'] ?? '0.0.0'),
            (string) ($data['license'] ?? ''),
            array_values(array_map('strval', (array) ($data['replaces'] ?? []))),
            array_map('strval', (array) ($data['autoload'] ?? [])),
            $data['extension'],
            (string) ($data['covers'] ?? ''),
            array_values(array_map('strval', (array) ($data['shortcodes'] ?? []))),
            array_values(array_map('strval', (array) ($data['blocks'] ?? []))),
            array_values(array_filter((array) ($data['types'] ?? []), static fn ($row) => is_array($row))),
        );
    }
}
