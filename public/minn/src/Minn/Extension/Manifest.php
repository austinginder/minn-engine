<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Json;

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
 *     "types": [{"slug": "zz_note", "name": "Notes", "rest_base": "zz-note"}],
 *     "needs": {"http": ["https://api.example.com/"]}
 *   }
 *
 * "replaces" names the WordPress plugin files whose behaviour this
 * extension stands in for; the extension is active whenever one of them is
 * in active_plugins, or when its own folder is listed there, or when it is
 * named in the minn_active_extensions option. "shortcodes" and "blocks"
 * are the content tokens preflight treats as provided instead of missing.
 * "types" are extra post types the engine should serve on wp/v2.
 * "needs" says what the extension reaches outside the process: "http" is
 * the URL prefixes its $minn->http may request (true for any public host).
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
        /** @var list<string>|null needs.http: the URL prefixes granted, [] for any public host, null when not asked */
        public ?array $http = null,
    ) {
    }

    /** A folder's minn.json, or null when there is none. */
    public static function read(string $dir): ?self
    {
        $file = $dir . '/minn.json';
        if (!is_file($file)) {
            return null;
        }
        $data = Json::file($file, or: null);
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
            self::http(is_array($data['needs'] ?? null) ? ($data['needs']['http'] ?? null) : null),
        );
    }

    /**
     * needs.http as a grant: true for any public host, or a URL prefix (or a
     * list of them) every request must start with; null when the manifest
     * does not ask. A prefix naming only a host is closed with "/", so
     * https://api.example.com does not also admit api.example.com.evil.net.
     *
     * @return list<string>|null
     */
    private static function http(mixed $asked): ?array
    {
        if ($asked === true) {
            return [];
        }
        $prefixes = [];
        foreach (is_string($asked) ? [$asked] : (is_array($asked) ? $asked : []) as $prefix) {
            if (is_string($prefix) && preg_match('~^https?://[^/?#]+~i', $prefix) === 1) {
                $prefixes[] = preg_match('~^https?://[^/?#]+$~i', $prefix) === 1 ? $prefix . '/' : $prefix;
            }
        }
        return $prefixes === [] ? null : $prefixes;
    }
}
