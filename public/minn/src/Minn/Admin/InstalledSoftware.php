<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\Extension\Loader;
use Minn\Support\FileHeaders;

/**
 * What is installed, as the System view lists it: every extension and
 * WordPress plugin with whether it runs, the mu-plugins, the themes with
 * their parents, and the active theme's label.
 */
final readonly class InstalledSoftware
{
    public function __construct(
        private Site $site,
        private Inventory $inventory,
        private Loader $extensions,
        private string $webroot,
    ) {
    }

    /** How many Minn extensions are active. */
    public function activeExtensionCount(): int
    {
        return count($this->extensions->active());
    }

    /** Plugins, mu-plugins, and themes with their versions and whether each is active. */
    public function manifest(): array
    {
        $plugins = [];
        foreach ($this->extensions->found() as $manifest) {
            $plugins[] = ['name' => $manifest->name, 'version' => $manifest->version !== '' ? $manifest->version : '—', 'active' => in_array($manifest, $this->extensions->active(), true)];
        }
        foreach ($this->inventory->plugins() as $plugin) {
            $plugins[] = ['name' => $plugin['title'] . ' (WordPress plugin, not run)', 'version' => $plugin['version'] !== '' ? $plugin['version'] : '—', 'active' => false];
        }
        usort($plugins, static fn (array $a, array $b): int => ($b['active'] <=> $a['active']) ?: strcasecmp($a['name'], $b['name']));
        $mu = array_map(static fn (array $p) => ['name' => $p['title'], 'version' => $p['version'], 'active' => true], $this->inventory->mustUse());
        $themes = [];
        foreach ($this->inventory->themes() as $theme) {
            $headers = FileHeaders::values("{$this->webroot}/wp-content/themes/{$theme['name']}/style.css", ['Template']);
            $parent = $headers['Template'] === '' ? '' : FileHeaders::values("{$this->webroot}/wp-content/themes/{$headers['Template']}/style.css", ['Theme Name'])['Theme Name'];
            $themes[] = ['name' => $theme['title'], 'version' => $theme['version'] !== '' ? $theme['version'] : '—', 'active' => $theme['status'] === 'active', 'parent' => $parent];
        }
        usort($themes, static fn (array $a, array $b): int => ($b['active'] <=> $a['active']) ?: strcasecmp($a['name'], $b['name']));
        return [
            'plugins' => $plugins,
            'active_plugins' => count(array_filter($plugins, static fn (array $p) => $p['active'])),
            'mu_plugins' => $mu,
            'themes' => $themes,
        ];
    }

    /** The active theme's name and version, naming the parent of a child theme; the slug when the headers are missing. */
    public function activeThemeLabel(): string
    {
        $slug = (string) ($this->site->option('stylesheet') ?? '');
        $headers = FileHeaders::values("{$this->webroot}/wp-content/themes/{$slug}/style.css", ['Theme Name', 'Version', 'Template']);
        if ($headers['Theme Name'] === '') {
            return $slug === '' ? '(none)' : $slug;
        }
        $label = trim($headers['Theme Name'] . ' ' . $headers['Version']);
        if ($headers['Template'] !== '') {
            $parent = FileHeaders::values("{$this->webroot}/wp-content/themes/{$headers['Template']}/style.css", ['Theme Name'])['Theme Name'];
            $label .= ' (child of ' . ($parent !== '' ? $parent : $headers['Template']) . ')';
        }
        return $label;
    }
}
