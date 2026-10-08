<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Ops\Packages;
use Minn\Content\Inventory;
use Minn\Content\PluginState;
use Minn\Content\Site;
use Minn\Extension\Loader;
use Minn\Extension\Manifest;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\Refusal;
use Minn\RestError;
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
        private Packages $packages,
    ) {
    }

    /** The plugins list, optionally by status. */
    #[Route(Method::Get, '/wp/v2/plugins', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    public function list(Request $request): Response
    {
        $status = $request->query('status');
        $items = array_values(array_filter($this->items(), static fn (array $p) => $status === null || $p['status'] === $status));
        return Reply::item($items, Fields::fromQuery($request->query));
    }

    /** Installs a directory plugin by slug, optionally activating it; answers 201 with the item. */
    #[Route(Method::Post, '/wp/v2/plugins', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    public function install(Request $request): Response
    {
        if (!$this->caller->can('install_plugins')) {
            throw new RestError('rest_cannot_install_plugin', 'Sorry, you are not allowed to install plugins on this site.', 403);
        }
        $body = $request->json();
        $slug = (string) ($body['slug'] ?? '');
        if ($slug === '') {
            throw RestError::missingParams(['slug']);
        }
        $status = (string) ($body['status'] ?? 'inactive');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw RestError::invalidParam('status', 'status is not one of inactive, active.');
        }
        if ($status === 'active' && !$this->caller->can('activate_plugins')) {
            throw new RestError('rest_cannot_activate_plugin', 'Sorry, you are not allowed to activate plugins.', 403);
        }
        $folder = $this->packages->installPlugin($slug);
        $key = null;
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            if (dirname($relative) === $folder) {
                $key = preg_replace('/\.php$/', '', $relative);
                break;
            }
        }
        if ($key === null) {
            throw new RestError('rest_plugin_install_failed', 'The installed folder carries no plugin file.', 500);
        }
        if ($status === 'active') {
            self::refuse((new PluginState($this->site, $this->inventory, $this->extensions))->activate($key . '.php'));
        }
        return Reply::item($this->find($key), Fields::fromQuery($request->query), 201);
    }

    /** One plugin. */
    #[Route(Method::Get, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    public function single(Request $request, string $plugin): Response
    {
        return Reply::item($this->find($plugin), Fields::fromQuery($request->query));
    }

    /** Activates or deactivates a plugin. */
    #[Route(Method::Put, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    #[Route(Method::Post, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    #[Route(Method::Patch, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    public function update(Request $request, string $plugin): Response
    {
        $current = $this->statusOf($plugin);
        $status = (string) ($request->json()['status'] ?? $request->form['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw RestError::invalidParam('status', 'status is not one of inactive, active.');
        }
        if ($status !== $current) {
            $state = new PluginState($this->site, $this->inventory, $this->extensions);
            $target = $this->manifestFor($plugin) ?? $plugin . '.php';
            if ($status === 'active') {
                self::refuse($state->activate($target));
            } else {
                $state->deactivate($target);
            }
        }
        return Reply::item($this->find($plugin), Fields::fromQuery($request->query));
    }

    /** Deletes an inactive plugin. */
    #[Route(Method::Delete, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'))]
    public function delete(Request $request, string $plugin): Response
    {
        if (!$this->caller->can('delete_plugins')) {
            throw new RestError('rest_cannot_delete_plugin', 'Sorry, you are not allowed to delete plugins for this site.', 403);
        }
        $item = $this->find($plugin);
        if ($item['status'] === 'active') {
            throw new RestError('rest_cannot_delete_active_plugin', 'Cannot delete an active plugin. Please deactivate it first.', 400);
        }
        // A WordPress plugin is deleted as the reference deletes it (uninstalled first, the delete hooks around it).
        if (array_key_exists($plugin . '.php', $this->inventory->pluginFiles())) {
            $deleted = \delete_plugins([$plugin . '.php']);
            if ($deleted instanceof \WP_Error) {
                throw new RestError((string) $deleted->get_error_code(), (string) $deleted->get_error_message(), 500);
            }
        } else {
            // What is left is a Minn extension, removed as a package.
            $this->packages->remove('extension', explode('/', $plugin, 2)[0]);
        }
        return Reply::item(['deleted' => true, 'previous' => $item], Fields::fromQuery($request->query));
    }

    /** @return list<array> */
    private function items(): array
    {
        $items = [];
        foreach ($this->extensions->found() as $manifest) {
            $items[] = $this->extensionItem($manifest, in_array($manifest, $this->extensions->active(), true));
        }
        $stored = array_flip(Serialized::stringList($this->site->option('active_plugins')));
        foreach ($this->inventory->pluginFiles() as $relative => $path) {
            $items[] = $this->pluginItem($relative, $path, isset($stored[$relative]));
        }
        return $items;
    }

    /** One plugin's item, built alone: an item reads (and loads the text domain of) only its own plugin. */
    private function find(string $plugin): array
    {
        $manifest = $this->manifestFor($plugin);
        if ($manifest !== null) {
            return $this->extensionItem($manifest, $this->statusOf($plugin) === 'active');
        }
        $path = $this->inventory->pluginFiles()[$plugin . '.php'] ?? null;
        if ($path === null) {
            throw new RestError('rest_plugin_not_found', 'Plugin not found.', 404);
        }
        return $this->pluginItem($plugin . '.php', $path, $this->statusOf($plugin) === 'active');
    }

    /** Whether a plugin is active, read without building its item (nothing translated, no text domain loaded). */
    private function statusOf(string $plugin): string
    {
        $manifest = $this->manifestFor($plugin);
        if ($manifest !== null) {
            return in_array($manifest, $this->extensions->active(), true) ? 'active' : 'inactive';
        }
        if (!array_key_exists($plugin . '.php', $this->inventory->pluginFiles())) {
            throw new RestError('rest_plugin_not_found', 'Plugin not found.', 404);
        }
        return in_array($plugin . '.php', Serialized::stringList($this->site->option('active_plugins')), true) ? 'active' : 'inactive';
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

    /**
     * A WordPress plugin as the reference's item shows it: its headers read,
     * translated and cut to the allowed markup once for the fields and once
     * more, marked up, for the rendered description (probe plugin-data).
     */
    private function pluginItem(string $relative, string $path, bool $active): array
    {
        $headers = \get_plugin_data($path, false, false);
        $plain = \_get_plugin_data_markup_translate($path, $headers, false, true);
        $key = preg_replace('/\.php$/', '', $relative);
        return [
            'plugin' => $key,
            'status' => $active ? 'active' : 'inactive',
            'name' => $plain['Name'],
            'plugin_uri' => $plain['PluginURI'],
            'author' => $plain['AuthorName'],
            'author_uri' => $plain['AuthorURI'],
            'description' => ['raw' => $plain['Description'], 'rendered' => \_get_plugin_data_markup_translate($path, $headers, true, true)['Description']],
            'version' => $plain['Version'],
            'network_only' => $headers['Network'],
            'requires_wp' => $headers['RequiresWP'],
            'requires_php' => $headers['RequiresPHP'],
            'textdomain' => $headers['TextDomain'],
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


    /** An activation the plugin refused, as the reference's REST answer carries it: HTTP 500, the refusal's own data kept beside. */
    private static function refuse(?Refusal $refusal): void
    {
        if ($refusal !== null) {
            throw new RestError($refusal->code, $refusal->message, 500, [], $refusal->data === null ? [] : ['additional_data' => [$refusal->data]]);
        }
    }
}
