<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Admin\Packages;
use Minn\Content\Inventory;
use Minn\Content\PluginState;
use Minn\Content\Site;
use Minn\Extension\Loader;
use Minn\Extension\Manifest;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\FileHeaders;
use Minn\Content\Texturize;
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
        private string $contentDir,
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

    /** Installs a wordpress.org plugin by slug, optionally activating it; answers 201 with the item. */
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
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'status is not one of inactive, active.']]);
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
            (new PluginState($this->site, $this->inventory, $this->extensions))->activate($key . '.php');
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
        $item = $this->find($plugin);
        $status = (string) ($request->json()['status'] ?? $request->form['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'status is not one of inactive, active.']]);
        }
        if ($status !== $item['status']) {
            $state = new PluginState($this->site, $this->inventory, $this->extensions);
            $target = $this->manifestFor($plugin) ?? $plugin . '.php';
            $status === 'active' ? $state->activate($target) : $state->deactivate($target);
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
        $folder = explode('/', $plugin, 2)[0];
        if (str_contains($plugin, '/')) {
            $this->packages->remove('extension', $folder);
        } else {
            @unlink("{$this->contentDir}/plugins/{$plugin}.php");
        }
        return Reply::item(['deleted' => true, 'previous' => $item], Fields::fromQuery($request->query));
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
        $author = self::text($h['Author']);
        $authorUri = self::uri($h['Author URI']);
        // The description keeps the inline markup the reference allows in headers,
        // raw as filtered, rendered as texturized with the author line appended.
        $description = self::description($h['Description']);
        $rendered = Texturize::html($description);
        if ($author !== '') {
            $rendered .= ' <cite>By ' . ($authorUri !== '' ? '<a href="' . $authorUri . '">' . $author . '</a>' : $author) . '.</cite>';
        }
        return [
            'plugin' => $key,
            'status' => $active ? 'active' : 'inactive',
            'name' => self::text($h['Plugin Name']),
            'plugin_uri' => self::uri($h['Plugin URI']),
            'author' => $author,
            'author_uri' => $authorUri,
            'description' => ['raw' => $description, 'rendered' => $rendered],
            'version' => $h['Version'],
            'network_only' => strtolower($h['Network']) === 'true',
            'requires_wp' => $h['Requires at least'],
            'requires_php' => $h['Requires PHP'],
            // A missing Text Domain header defaults to the folder (or the single file's name).
            'textdomain' => $h['Text Domain'] !== '' ? $h['Text Domain'] : (str_contains($relative, '/') ? dirname($relative) : basename($relative, '.php')),
            '_links' => $this->links($key),
        ];
    }

    /** A header as the reference serves it: tags stripped (or cut to an allowlist), a bare ampersand entity-encoded. */
    private static function text(string $value, string $allowed = ''): string
    {
        return (string) preg_replace('/&(?!(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);)/i', '&amp;', strip_tags($value, $allowed));
    }

    /** The Description header cut to the reference's allowlist: a (href, title), abbr and acronym (title), code, em, strong. */
    private static function description(string $value): string
    {
        $allowed = ['a' => ['href', 'title'], 'abbr' => ['title'], 'acronym' => ['title'], 'code' => [], 'em' => [], 'strong' => []];
        $text = self::text($value, '<' . implode('><', array_keys($allowed)) . '>');
        return (string) preg_replace_callback('/<([a-z]+)(\s[^>]*)?>/i', static function (array $m) use ($allowed): string {
            $tag = strtolower($m[1]);
            $kept = '';
            if (preg_match_all('/([a-z-]+)\s*=\s*("[^"]*"|\'[^\']*\')/i', $m[2] ?? '', $attrs, PREG_SET_ORDER)) {
                foreach ($attrs as $attr) {
                    if (in_array(strtolower($attr[1]), $allowed[$tag] ?? [], true)) {
                        $kept .= ' ' . strtolower($attr[1]) . '=' . $attr[2];
                    }
                }
            }
            return "<{$tag}{$kept}>";
        }, $text);
    }

    /** A header URL as the reference serves it: a bare ampersand becomes &#038;. */
    private static function uri(string $value): string
    {
        return (string) preg_replace('/&(?!(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);)/i', '&#038;', strip_tags($value));
    }

    private function links(string $key): array
    {
        return ['self' => [['href' => $this->url->to('/wp/v2/plugins/' . $key), 'targetHints' => ['allow' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']]]]];
    }

    private static function extensionKey(Manifest $manifest): string
    {
        return $manifest->slug . '/' . $manifest->slug;
    }

}
