<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Admin\AdminPlugin;
use Minn\Auth\Can;
use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Objects\Errors;
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
use Minn\Runtime\PluginFiles;
use Minn\Runtime\PluginRemoval;
use Minn\Runtime\Refusal;
use Minn\RestError;
use Minn\Html;
use Minn\Support\Lists;
use Minn\Support\Sanitize;
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
    /** Read by list(): the search and the statuses it narrows to. */
    private const LIST_ARGS = [
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'status' => ['description' => 'Limits results to plugins with the given status.', 'type' => 'array', 'items' => ['type' => 'string', 'enum' => ['inactive', 'active']], 'required' => false],
    ];

    public function __construct(
        private Site $site,
        private Inventory $inventory,
        private Loader $extensions,
        private RestUrl $url,
        private Caller $caller,
        private Packages $packages,
    ) {
    }

    /** The plugins list, narrowed by status and by a search when one is asked. */
    #[Route(Method::Get, '/wp/v2/plugins', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugins', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugins', message: 'Sorry, you are not allowed to manage plugins for this site.'), args: [Args::CONTEXT, self::LIST_ARGS])]
    public function list(Request $request): Response
    {
        $statuses = Lists::items($request->query['status'] ?? []);
        $search = Sanitize::textField($request->query['search'] ?? '');
        $items = [];
        foreach ($this->items() as [$item, $searched]) {
            if (($statuses === [] || in_array($item['status'], $statuses, true)) && ($search === '' || self::mentions($searched, $search))) {
                $items[] = $item;
            }
        }
        $fields = Fields::fromQuery($request->query);
        return Reply::item($fields === null ? $items : array_map(static fn (array $item): array => $fields->apply($item), $items), null);
    }

    /**
     * Whether a search names a plugin: found, letter case kept, in its file
     * or in one of its headers as written (probe: the reference's list).
     *
     * @param list<string> $searched
     */
    private static function mentions(array $searched, string $search): bool
    {
        foreach ($searched as $text) {
            if (str_contains($text, $search)) {
                return true;
            }
        }
        return false;
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

    /** One plugin; the refusal is named for the one, as on the reference. */
    #[Route(Method::Get, '/wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?}', policy: new Policy(Access::Cap, 'activate_plugins', signIn: 'rest_cannot_view_plugin', signInMessage: 'Sorry, you are not allowed to manage plugins for this site.', refuse: 'rest_cannot_view_plugin', message: 'Sorry, you are not allowed to manage plugins for this site.'), args: [Args::CONTEXT, PathArgs::PLUGIN])]
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
            // The plugin's own capability, which a filter may refuse for this one plugin (security review 2026-10-22).
            $verb = $status === 'active' ? 'activate' : 'deactivate';
            if (!Can::user($this->caller->id(), "{$verb}_plugin", $target)) {
                throw new RestError("rest_cannot_{$verb}_plugin", "Sorry, you are not allowed to {$verb} this plugin.", 403);
            }
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
        if ($plugin . '.php' === AdminPlugin::MAIN) {
            throw new RestError('rest_cannot_delete_minn_admin', AdminPlugin::KEPT, 400);
        }
        // A WordPress plugin is deleted as the reference deletes it (uninstalled first, the delete hooks around it).
        if (array_key_exists($plugin . '.php', $this->inventory->pluginFiles())) {
            $deleted = PluginRemoval::delete([$plugin . '.php']);
            if ($deleted instanceof Errors) {
                throw new RestError((string) $deleted->get_error_code(), (string) $deleted->get_error_message(), 500);
            }
        } else {
            // What is left is a Minn extension, removed as a package.
            $this->packages->remove('extension', explode('/', $plugin, 2)[0]);
        }
        return Reply::item(['deleted' => true, 'previous' => $item], Fields::fromQuery($request->query));
    }

    /**
     * Every plugin's item, with the text a search reads for it: a WordPress
     * plugin's file and headers as written, an extension's key and fields.
     *
     * @return list<array{0: array<string, mixed>, 1: list<string>}>
     */
    private function items(): array
    {
        $items = [];
        foreach ($this->extensions->found() as $manifest) {
            $item = $this->extensionItem($manifest, in_array($manifest, $this->extensions->active(), true));
            $items[] = [$item, [$item['plugin'] . '.php', $item['name'], $item['description']['raw'], $item['version'], $item['textdomain']]];
        }
        $stored = array_flip(Serialized::stringList($this->site->option('active_plugins')));
        $files = $this->inventory->pluginFiles();
        $headers = array_map(static fn (string $path): array => PluginFiles::data($path, false, false), $files);
        // The WordPress plugins come by name, letter case aside, as the reference lists them.
        uasort($headers, static fn (array $a, array $b): int => strnatcasecmp((string) ($a['Name'] ?? ''), (string) ($b['Name'] ?? '')));
        foreach ($headers as $relative => $data) {
            $relative = (string) $relative;
            $item = self::shape($relative, $files[$relative], $data, isset($stored[$relative]) ? 'active' : 'inactive') + ['_links' => $this->links((string) preg_replace('/\.php$/', '', $relative))];
            $items[] = [$item, [$relative, ...array_values(array_filter($data, is_string(...)))]];
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
        return self::shape($relative, $path, PluginFiles::data($path, false, false), $active ? 'active' : 'inactive') + ['_links' => $this->links((string) preg_replace('/\.php$/', '', $relative))];
    }

    /**
     * A WordPress plugin's item from its headers, as the reference's
     * controller shapes one (the runtime's WP_REST_Plugins_Controller
     * shapes through here too): the headers translated and cut to the
     * allowed markup once for the fields and once more, marked up, for the
     * rendered description.
     *
     * @param array<string, mixed> $headers the plugin's headers, as get_plugins() holds them
     * @return array<string, mixed>
     */
    public static function shape(string $relative, string $path, array $headers, string $status): array
    {
        $plain = PluginFiles::shown($path, $headers, false, true);
        return [
            'plugin' => (string) preg_replace('/\.php$/', '', $relative),
            'status' => $status,
            'name' => $plain['Name'],
            'plugin_uri' => $plain['PluginURI'],
            'author' => $plain['AuthorName'],
            'author_uri' => $plain['AuthorURI'],
            'description' => ['raw' => $plain['Description'], 'rendered' => PluginFiles::shown($path, $headers, true, true)['Description']],
            'version' => $plain['Version'],
            'network_only' => $headers['Network'],
            'requires_wp' => $headers['RequiresWP'],
            'requires_php' => $headers['RequiresPHP'],
            'textdomain' => $headers['TextDomain'],
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
