<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\Extension\Loader;
use Minn\Extension\Manifest;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\FileHeaders;
use Minn\Support\Html;
use Minn\Support\Serialized;

/**
 * wp/v2 plugins: what sits in wp-content/plugins, in the reference's
 * shape. Minn extensions (a minn.json manifest) are listed alongside the
 * WordPress plugins the folder still holds; the engine runs the former
 * and only records the latter's stored state. Activation writes the same
 * options the reference and the extension loader read; files are never
 * installed or deleted from here.
 */
final readonly class PluginsController
{
    public function __construct(
        private Site $site,
        private Inventory $inventory,
        private Loader $extensions,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/plugins')]
    public function list(Request $request): Response
    {
        $this->requireManager();
        $status = $request->query('status');
        $items = array_values(array_filter($this->items(), static fn (array $p) => $status === null || $p['status'] === $status));
        return Reply::item($items, Fields::fromQuery($request->query));
    }

    #[Route(Method::Get, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}')]
    public function single(Request $request, string $plugin): Response
    {
        $this->requireManager();
        return Reply::item($this->find($plugin), Fields::fromQuery($request->query));
    }

    #[Route(Method::Put, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}')]
    #[Route(Method::Post, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}')]
    #[Route(Method::Patch, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}')]
    public function update(Request $request, string $plugin): Response
    {
        $this->requireManager();
        $item = $this->find($plugin);
        $status = (string) ($request->json()['status'] ?? $request->form['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'status is not one of inactive, active.']]);
        }
        if ($status !== $item['status']) {
            $manifest = $this->manifestFor($plugin);
            if ($manifest !== null) {
                $this->setExtensionActive($manifest, $status === 'active');
            } else {
                $this->setPluginActive($plugin . '.php', $status === 'active');
            }
        }
        return Reply::item($this->find($plugin), Fields::fromQuery($request->query));
    }

    #[Route(Method::Delete, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}')]
    public function delete(Request $request, string $plugin): Response
    {
        $this->requireManager();
        $this->find($plugin);
        throw new RestError('rest_cannot_delete_plugin', 'Minn Engine does not delete files from the plugins folder. Remove the folder over SSH or SFTP.', 403);
    }

    /** @return list<array> */
    private function items(): array
    {
        $active = array_flip($this->extensions->active());
        $items = [];
        foreach ($this->extensions->found() as $manifest) {
            $items[] = $this->extensionItem($manifest, in_array($manifest, $this->extensions->active(), true));
        }
        $stored = array_flip(Serialized::stringList($this->site->option('active_plugins')));
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            $items[] = $this->pluginItem($relative, $path, isset($stored[$relative]));
        }
        unset($active);
        return $items;
    }

    private function find(string $plugin): array
    {
        foreach ($this->items() as $item) {
            if ($item['plugin'] === $plugin) {
                return $item;
            }
        }
        throw new RestError('rest_plugin_not_found', 'Plugin not found.', 404);
    }

    private function manifestFor(string $plugin): ?Manifest
    {
        foreach ($this->extensions->found() as $manifest) {
            if (self::extensionKey($manifest) === $plugin) {
                return $manifest;
            }
        }
        return null;
    }

    private function extensionItem(Manifest $manifest, bool $active): array
    {
        $key = self::extensionKey($manifest);
        $covers = $manifest->replaces === []
            ? 'A Minn Engine extension.'
            : 'A Minn Engine extension standing in for ' . implode(', ', array_map(static fn (string $f) => explode('/', $f)[0], $manifest->replaces)) . ($manifest->covers !== '' ? ' (' . $manifest->covers . ')' : '') . '.';
        return [
            'plugin' => $key,
            'status' => $active ? 'active' : 'inactive',
            'name' => $manifest->name,
            'plugin_uri' => '',
            'author' => '',
            'author_uri' => '',
            'description' => ['raw' => $covers, 'rendered' => Html::esc($covers)],
            'version' => $manifest->version,
            'network_only' => false,
            'requires_wp' => '',
            'requires_php' => '8.3',
            'textdomain' => $manifest->slug,
            '_links' => $this->links($key),
        ];
    }

    private function pluginItem(string $relative, string $path, bool $active): array
    {
        $h = FileHeaders::values($path, ['Plugin Name', 'Plugin URI', 'Version', 'Description', 'Author', 'Author URI', 'Text Domain', 'Network', 'Requires at least', 'Requires PHP']);
        $key = preg_replace('/\.php$/', '', $relative);
        $author = strip_tags($h['Author']);
        $description = strip_tags($h['Description']);
        $rendered = Html::esc($description);
        if ($author !== '') {
            $rendered .= ' <cite>By ' . ($h['Author URI'] !== '' ? '<a href="' . Html::attr($h['Author URI']) . '">' . Html::esc($author) . '</a>' : Html::esc($author)) . '.</cite>';
        }
        return [
            'plugin' => $key,
            'status' => $active ? 'active' : 'inactive',
            'name' => $h['Plugin Name'],
            'plugin_uri' => $h['Plugin URI'],
            'author' => $h['Author'],
            'author_uri' => $h['Author URI'],
            'description' => ['raw' => $h['Description'], 'rendered' => $rendered],
            'version' => $h['Version'],
            'network_only' => strtolower($h['Network']) === 'true',
            'requires_wp' => $h['Requires at least'],
            'requires_php' => $h['Requires PHP'],
            'textdomain' => $h['Text Domain'],
            '_links' => $this->links($key),
        ];
    }

    private function links(string $key): array
    {
        return ['self' => [['href' => $this->url->to('/wp/v2/plugins/' . $key), 'targetHints' => ['allow' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']]]]];
    }

    private static function extensionKey(Manifest $manifest): string
    {
        return $manifest->slug . '/' . $manifest->slug;
    }

    private function setPluginActive(string $file, bool $active): void
    {
        $list = Serialized::stringList($this->site->option('active_plugins'));
        $list = array_values(array_diff($list, [$file]));
        if ($active) {
            $list[] = $file;
            sort($list, SORT_STRING);
        }
        $this->site->setOption('active_plugins', Serialized::serializeStringList($list));
    }

    /** Own-list activation; deactivating also releases the WordPress plugin files the extension stood in for. */
    private function setExtensionActive(Manifest $manifest, bool $active): void
    {
        $own = json_decode((string) ($this->site->option('minn_active_extensions') ?? '[]'), true);
        $own = array_values(array_diff(is_array($own) ? array_map('strval', $own) : [], [$manifest->slug]));
        if ($active) {
            $own[] = $manifest->slug;
        } else {
            foreach ($manifest->replaces as $file) {
                $this->setPluginActive($file, false);
            }
        }
        $this->site->setOption('minn_active_extensions', (string) json_encode($own));
        $this->extensions->refresh();
    }

    private function requireManager(): void
    {
        $this->caller->require('rest_cannot_view_plugins', 'Sorry, you are not allowed to manage plugins for this site.');
        if (!$this->caller->can('activate_plugins')) {
            throw new RestError('rest_cannot_view_plugins', 'Sorry, you are not allowed to manage plugins for this site.', 403);
        }
    }
}
