<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Content\Site;
use Minn\Json;
use Minn\Runtime\Plugins;
use Minn\Support\Serialized;
use Throwable;

/**
 * Finds extensions under wp-content/plugins and wp-content/mu-plugins, and
 * in the active theme (a theme folder with a minn.json is an extension too,
 * active because it is the theme: the child, then its parent), decides
 * which are active, autoloads them, and asks each to register.
 * An extension that throws is logged and skipped; the page still renders.
 */
final class Loader
{
    /** @var list<Manifest> */
    private array $found = [];
    /** @var list<Manifest> */
    private array $active = [];

    public function __construct(private readonly string $contentDir, private readonly Site $site)
    {
    }

    /**
     * Every extension manifest on disk.
     *
     * @return list<Manifest> every extension on disk
     */
    public function found(): array
    {
        if ($this->found === []) {
            foreach (['plugins', 'mu-plugins'] as $area) {
                foreach (glob("{$this->contentDir}/{$area}/*/minn.json") ?: [] as $file) {
                    $manifest = Manifest::read(dirname($file));
                    if ($manifest !== null) {
                        $this->found[] = $manifest;
                    }
                }
            }
            foreach ($this->themeDirs() as $dir) {
                $manifest = Manifest::read($dir);
                if ($manifest !== null) {
                    $this->found[] = $manifest;
                }
            }
        }
        return $this->found;
    }

    /**
     * The active theme's folders, child first, that carry a minn.json.
     *
     * @return list<string>
     */
    private function themeDirs(): array
    {
        $dirs = [];
        foreach (['stylesheet', 'template'] as $option) {
            $slug = (string) ($this->site->option($option) ?? '');
            $dir = "{$this->contentDir}/themes/{$slug}";
            if (preg_match('/^[A-Za-z0-9._-]+$/', $slug) === 1 && !in_array($dir, $dirs, true) && is_file("{$dir}/minn.json")) {
                $dirs[] = $dir;
            }
        }
        return $dirs;
    }

    /** Forgets the cached activation list after an option write. */
    public function refresh(): void
    {
        $this->active = [];
    }

    /**
     * The manifests that are active.
     *
     * @return list<Manifest> the extensions this site has switched on
     */
    public function active(): array
    {
        if ($this->active !== []) {
            return $this->active;
        }
        $plugins = Serialized::stringList($this->site->option('active_plugins'));
        $own = Json::decode((string) ($this->site->option('minn_active_extensions') ?? '[]'), or: null);
        $own = is_array($own) ? array_map('strval', $own) : [];
        foreach ($this->found() as $manifest) {
            // A plugin running as code needs no stand-in.
            if (array_intersect($manifest->replaces, Plugins::loaded()) !== []) {
                continue;
            }
            $byReplacement = array_intersect($manifest->replaces, $plugins) !== [];
            $byOwnFolder = array_filter($plugins, static fn (string $p) => str_starts_with($p, $manifest->slug . '/')) !== [];
            $isTheme = str_starts_with($manifest->dir, "{$this->contentDir}/themes/");
            if ($byReplacement || $byOwnFolder || in_array($manifest->slug, $own, true) || str_contains($manifest->dir, '/mu-plugins/') || $isTheme) {
                $this->active[] = $manifest;
            }
        }
        return $this->active;
    }

    /**
     * Extra post types declared by active extensions, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function declaredTypes(): array
    {
        $out = [];
        foreach ($this->active() as $manifest) {
            foreach ($manifest->types as $row) {
                $slug = (string) ($row['slug'] ?? '');
                if ($slug !== '') {
                    $out[$slug] = $row;
                }
            }
        }
        return $out;
    }

    /**
     * Extra taxonomies declared by active extensions, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function declaredTaxonomies(): array
    {
        $out = [];
        foreach ($this->active() as $manifest) {
            foreach ($manifest->taxonomies as $row) {
                $slug = (string) ($row['slug'] ?? '');
                if ($slug !== '') {
                    $out[$slug] = $row;
                }
            }
        }
        return $out;
    }

    /** Which active WordPress plugin files an extension stands in for. @return array<string, string> plugin file => extension slug */
    public function replacements(): array
    {
        $out = [];
        foreach ($this->active() as $manifest) {
            foreach ($manifest->replaces as $file) {
                $out[$file] = $manifest->slug;
            }
        }
        return $out;
    }

    /**
     * Registers every active extension, each on seams of its own; one that
     * throws adds nothing. An extension's declared tables are settled first
     * (Tables), so its routes find them on the request that creates them.
     */
    public function register(Seams $seams): void
    {
        $tables = new Tables($seams->db, $seams->site);
        foreach ($this->active() as $manifest) {
            try {
                $tables->settle($manifest);
                self::autoload($manifest);
                $class = $manifest->extension;
                if (!class_exists($class) || !is_subclass_of($class, Extension::class)) {
                    throw new \RuntimeException("{$class} is not a Minn\\Extension\\Extension");
                }
                $own = $seams->forExtension($manifest);
                (new $class())->register($own);
                $seams->adopt($own);
            } catch (Throwable $e) {
                error_log("Minn Engine: extension {$manifest->slug} failed to register: " . $e->getMessage());
            }
        }
    }

    private static function autoload(Manifest $manifest): void
    {
        foreach ($manifest->autoload as $prefix => $dir) {
            $base = rtrim($manifest->dir . '/' . trim($dir, '/'), '/');
            spl_autoload_register(static function (string $class) use ($prefix, $base): void {
                if (str_starts_with($class, $prefix)) {
                    $file = $base . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($file)) {
                        require $file;
                    }
                }
            });
        }
    }
}
