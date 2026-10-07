<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * The script modules registry: registrations with typed dependencies, the
 * queue, and the four things a page prints from it (the import map, module
 * preloads, the module tags split head/footer, and per-module JSON data).
 * Behaviour pinned by the script-modules probe fixture.
 */
final class ScriptModules
{
    private const PRIORITIES = ['high', 'low', 'auto'];

    /** @var array<string, array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string, attributes: array<string, string>}> */
    private array $registered = [];
    /** @var list<string> */
    private array $queue = [];
    /** @var list<string> */
    private array $done = [];
    private bool $a11yAvailable = false;
    private bool $a11yPrinted = false;

    /**
     * @param Closure(string, string|false|null): string $url turns a src and version into the printed URL
     * @param Closure(string, array<string, mixed>): array<string, mixed> $data applies the module data filter
     */
    public function __construct(private readonly Closure $url, private readonly Closure $data)
    {
    }

    /**
     * Registers a module by id.
     *
     * @param list<string|array{id: string, import?: string}> $deps
     */
    public function register(string $id, string $src, array $deps, string|false|null $version, array $args): void
    {
        if (isset($this->registered[$id])) {
            return;
        }
        $dependencies = [];
        $missing = [];
        foreach ($deps as $dep) {
            $depId = is_array($dep) ? (string) ($dep['id'] ?? '') : (string) $dep;
            if ($depId === '') {
                continue;
            }
            $import = is_array($dep) && ($dep['import'] ?? '') === 'dynamic' ? 'dynamic' : 'static';
            $dependencies[] = ['id' => $depId, 'import' => $import];
            if (!isset($this->registered[$depId])) {
                $missing[] = $depId;
            }
        }
        if ($missing !== []) {
            $this->wrong('WP_Script_Modules::register', 'The script module with the ID "' . $id . '" was enqueued with dependencies that are not registered: ' . implode(', ', $missing) . '.', '6.5.0');
        }
        $priority = (string) ($args['fetchpriority'] ?? 'auto');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $this->wrong('WP_Script_Modules::register', 'Invalid fetchpriority `' . $priority . '` defined for `' . $id . '` during script registration.', '6.9.0');
            $priority = 'auto';
        }
        $this->registered[$id] = [
            'src' => $src,
            'version' => $version,
            'dependencies' => $dependencies,
            'in_footer' => (bool) ($args['in_footer'] ?? false),
            'fetchpriority' => $priority,
            'attributes' => array_map('strval', (array) ($args['attributes'] ?? [])),
        ];
    }

    /** Queues a module, registering it when a source is given. */
    public function enqueue(string $id, string $src, array $deps, string|false|null $version, array $args): void
    {
        if ($src !== '') {
            $this->register($id, $src, $deps, $version, $args);
        }
        if (!in_array($id, $this->queue, true)) {
            $this->queue[] = $id;
        }
    }

    /** Removes a module from the queue. */
    public function dequeue(string $id): void
    {
        $this->queue = array_values(array_filter($this->queue, static fn (string $q) => $q !== $id));
    }

    /** Forgets a module. */
    public function deregister(string $id): void
    {
        unset($this->registered[$id]);
        $this->dequeue($id);
    }

    /** Sets a module's fetch priority. */
    public function setFetchpriority(string $id, string $priority): bool
    {
        if (!isset($this->registered[$id])) {
            return false;
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $this->wrong('WP_Script_Modules::set_fetchpriority', 'Invalid fetchpriority: ' . $priority, '6.9.0');
            return false;
        }
        $this->registered[$id]['fetchpriority'] = $priority;
        return true;
    }

    /** Moves a module to the footer or the head. */
    public function moveToFooter(string $id): bool
    {
        return $this->placeIn($id, 'footer');
    }

    /** Prints a module in the head; false when it is not registered. */
    public function moveToHead(string $id): bool
    {
        return $this->placeIn($id, 'head');
    }

    private function placeIn(string $id, string $where): bool
    {
        if (!isset($this->registered[$id])) {
            return false;
        }
        $this->registered[$id]['in_footer'] = $where === 'footer';
        return true;
    }

    /**
     * The module ids queued.
     *
     * @return list<string>
     */
    public function queue(): array
    {
        return $this->queue;
    }

    /**
     * One registered module, or null.
     *
     * @return array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}|null
     */
    public function registered(string $id): ?array
    {
        if (!isset($this->registered[$id])) {
            return null;
        }
        $item = $this->registered[$id];
        unset($item['attributes']);
        return $item;
    }

    /** The import map script tag. */
    public function printImportMap(): string
    {
        $imports = [];
        foreach ($this->dependencies($this->marked(), ['static', 'dynamic']) as $id) {
            $imports[$id] = $this->urlOf($id);
        }
        if ($imports === []) {
            return '';
        }
        return \wp_get_inline_script_tag((string) json_encode(['imports' => $imports], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['type' => 'importmap', 'id' => 'wp-importmap']);
    }

    /** The modulepreload links. */
    public function printPreloads(): string
    {
        $out = '';
        foreach ($this->dependencies($this->marked(), ['static']) as $id) {
            if (in_array($id, $this->queue, true)) {
                continue;
            }
            $priority = $this->registered[$id]['fetchpriority'];
            $out .= '<link rel="modulepreload" href="' . $this->attr($this->urlOf($id)) . '" id="' . $this->attr($id . '-js-modulepreload') . '"' . ($priority === 'auto' ? '' : ' fetchpriority="' . $priority . '"') . '>' . "\n";
        }
        return $out;
    }

    /** The head's module tags. */
    public function printHead(): string
    {
        return $this->printTags(false);
    }

    /** The footer's module tags. */
    public function printFooter(): string
    {
        return $this->printTags(true);
    }

    /** The script-module-data tags. */
    public function printData(): string
    {
        $marked = $this->marked();
        $ids = array_values(array_unique(array_merge($marked, $this->dependencies($marked, ['static', 'dynamic']))));
        $out = '';
        foreach ($ids as $id) {
            $data = ($this->data)($id, []);
            if ($data === []) {
                continue;
            }
            $out .= \wp_get_inline_script_tag((string) json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['type' => 'application/json', 'id' => 'wp-script-module-data-' . $id]);
        }
        return $out;
    }

    /** The a11y module's tag, once. */
    public function printA11y(): string
    {
        if (!$this->a11yAvailable || $this->a11yPrinted) {
            return '';
        }
        $this->a11yPrinted = true;
        return '<div style="position:absolute;margin:-1px;padding:0;height:1px;width:1px;overflow:hidden;clip-path:inset(50%);border:0;word-wrap:normal !important;"><p id="a11y-speak-intro-text" class="a11y-speak-intro-text" hidden>Notifications</p><div id="a11y-speak-assertive" class="a11y-speak-region" aria-live="assertive" aria-relevant="additions text" aria-atomic="true"></div><div id="a11y-speak-polite" class="a11y-speak-region" aria-live="polite" aria-relevant="additions text" aria-atomic="true"></div></div>';
    }

    private function printTags(bool $footer): string
    {
        $out = '';
        foreach ($this->marked() as $id) {
            if (in_array($id, $this->done, true)) {
                continue;
            }
            $item = $this->registered[$id];
            if (!$footer && $item['in_footer']) {
                continue;
            }
            $this->done[] = $id;
            if ($id === '@wordpress/a11y') {
                $this->a11yAvailable = true;
            }
            $attributes = ['type' => 'module', 'src' => $this->urlOf($id), 'id' => $id . '-js-module']
                + ($item['fetchpriority'] !== 'auto' ? ['fetchpriority' => $item['fetchpriority']] : [])
                + $item['attributes'];
            $out .= \wp_get_script_tag($attributes);
        }
        return $out;
    }

    /** Queued modules that are registered with every dependency registered, in queue order. */
    private function marked(): array
    {
        $marked = [];
        foreach ($this->queue as $id) {
            if (isset($this->registered[$id]) && $this->complete($id, [])) {
                $marked[] = $id;
            }
        }
        return $marked;
    }

    /** @param list<string> $seen */
    private function complete(string $id, array $seen): bool
    {
        if (in_array($id, $seen, true)) {
            return true;
        }
        $seen[] = $id;
        foreach ($this->registered[$id]['dependencies'] as $dep) {
            if (!isset($this->registered[$dep['id']]) || !$this->complete($dep['id'], $seen)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Every registered dependency of the given modules, depth first in
     * declaration order, following only the given import types.
     *
     * @param list<string> $ids
     * @param list<string> $types
     * @return list<string>
     */
    private function dependencies(array $ids, array $types): array
    {
        $found = [];
        $visit = function (string $id) use (&$found, &$visit, $types): void {
            foreach ($this->registered[$id]['dependencies'] ?? [] as $dep) {
                if (!in_array($dep['import'], $types, true) || !isset($this->registered[$dep['id']]) || in_array($dep['id'], $found, true)) {
                    continue;
                }
                $found[] = $dep['id'];
                $visit($dep['id']);
            }
        };
        foreach ($ids as $id) {
            $visit($id);
        }
        return $found;
    }

    private function urlOf(string $id): string
    {
        return ($this->url)($this->registered[$id]['src'], $this->registered[$id]['version']);
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    private function wrong(string $function, string $message, string $version): void
    {
        Runtime::hooks()->action('doing_it_wrong_run', [$function, $message, $version]);
    }
}
