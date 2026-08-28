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
 *     "extension": "Minn\\Ext\\BlockVisibility\\Extension"
 *   }
 *
 * "replaces" names the WordPress plugin files whose behaviour this
 * extension stands in for; the extension is active whenever one of them is
 * in active_plugins, or when its own folder is listed there, or when it is
 * named in the minn_active_extensions option.
 */
final readonly class Manifest
{
    /**
     * @param list<string> $replaces
     * @param array<string, string> $autoload namespace prefix => directory
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
        );
    }
}
