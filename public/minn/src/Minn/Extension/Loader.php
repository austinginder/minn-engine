<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Content\Site;
use Minn\Runtime\Plugins;
use Minn\Support\Serialized;
use Throwable;

/**
 * Finds extensions under wp-content/plugins and wp-content/mu-plugins,
 * decides which are active, autoloads them, and asks each to register.
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
        }
        return $this->found;
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
        $own = json_decode((string) ($this->site->option('minn_active_extensions') ?? '[]'), true);
        $own = is_array($own) ? array_map('strval', $own) : [];
        foreach ($this->found() as $manifest) {
            // A plugin running as code needs no stand-in.
            if (array_intersect($manifest->replaces, Plugins::loaded()) !== []) {
                continue;
            }
            $byReplacement = array_intersect($manifest->replaces, $plugins) !== [];
            $byOwnFolder = array_filter($plugins, static fn (string $p) => str_starts_with($p, $manifest->slug . '/')) !== [];
            if ($byReplacement || $byOwnFolder || in_array($manifest->slug, $own, true) || str_contains($manifest->dir, '/mu-plugins/')) {
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

    /** Registers every active extension's seams. */
    public function register(Seams $seams): void
    {
        foreach ($this->active() as $manifest) {
            try {
                self::autoload($manifest);
                $class = $manifest->extension;
                if (!class_exists($class) || !is_subclass_of($class, Extension::class)) {
                    throw new \RuntimeException("{$class} is not a Minn\\Extension\\Extension");
                }
                (new $class())->register($seams);
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
