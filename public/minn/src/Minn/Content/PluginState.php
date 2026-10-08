<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Extension\Loader;
use Minn\Extension\Manifest;
use Minn\Runtime\PluginActivation;
use Minn\Runtime\Refusal;
use Minn\Runtime\Runtime;
use Minn\Support\Serialized;

/**
 * Switching plugins on and off: a WordPress plugin file goes through the
 * reference's activation and deactivation (PluginActivation: requirements,
 * the plugin loaded, its hooks, the sorted active_plugins list); a Minn
 * extension joins or leaves minn_active_extensions, and deactivating one
 * also releases, silently, the plugin files it stood in for. The REST
 * toggle and the CLI verbs share this so they cannot drift; both run with
 * the runtime booted.
 */
final readonly class PluginState
{
    public function __construct(private Site $site, private Inventory $inventory, private Loader $extensions)
    {
    }

    /**
     * The relative "dir/file.php" of a WordPress plugin, else the extension
     * for a slug, else null. A folder carrying both records in
     * active_plugins like the reference does (the loader counts its own
     * folder there as active); only a pure extension uses the engine's list.
     */
    public function find(string $slug): Manifest|string|null
    {
        foreach (array_keys($this->inventory->pluginFiles()) as $relative) {
            if ($relative === $slug . '.php' || str_starts_with($relative, $slug . '/')) {
                return $relative;
            }
        }
        foreach ($this->extensions->found() as $manifest) {
            if ($manifest->slug === $slug) {
                return $manifest;
            }
        }
        return null;
    }

    /** Whether a plugin, by file, or a Minn extension is active. */
    public function isActive(Manifest|string $plugin): bool
    {
        if ($plugin instanceof Manifest) {
            return in_array($plugin, $this->extensions->active(), true);
        }
        return in_array($plugin, Serialized::stringList($this->site->option('active_plugins')), true);
    }

    /**
     * Records a plugin or an extension as active, in the option each kind
     * uses; null, or why a plugin was refused (unexpected_output leaves it
     * active, as the reference does).
     */
    public function activate(Manifest|string $plugin): ?Refusal
    {
        $refusal = null;
        if ($plugin instanceof Manifest) {
            $this->site->setOption('minn_active_extensions', (string) json_encode([...$this->ownWithout($plugin), $plugin->slug]));
        } else {
            $refusal = PluginActivation::activate($plugin, Runtime::hooks());
        }
        $this->extensions->refresh();
        return $refusal;
    }

    /** Records a plugin, by file, or a Minn extension as inactive; an extension also deactivates the plugins it replaced. */
    public function deactivate(Manifest|string $plugin): void
    {
        if ($plugin instanceof Manifest) {
            PluginActivation::deactivate(array_values($plugin->replaces), null);
            $this->site->setOption('minn_active_extensions', (string) json_encode($this->ownWithout($plugin)));
        } else {
            PluginActivation::deactivate([$plugin], Runtime::hooks());
        }
        $this->extensions->refresh();
    }

    /** The active extensions minus one. @return list<string> */
    private function ownWithout(Manifest $plugin): array
    {
        $own = json_decode((string) ($this->site->option('minn_active_extensions') ?? '[]'), true);
        return array_values(array_diff(is_array($own) ? array_map('strval', $own) : [], [$plugin->slug]));
    }
}
