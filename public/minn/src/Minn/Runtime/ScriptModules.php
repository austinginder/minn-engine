<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Json;

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
    /** @var array<string, true> the queued modules whose missing dependencies were told */
    private array $told = [];

    /** @var array<string, list<string>> the modules that import each one, as first asked (dependentsOf) */
    private array $dependents = [];

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
        foreach ($deps as $dep) {
            $depId = is_array($dep) ? (string) ($dep['id'] ?? '') : (string) $dep;
            if ($depId === '') {
                continue;
            }
            $import = is_array($dep) && ($dep['import'] ?? '') === 'dynamic' ? 'dynamic' : 'static';
            $dependencies[] = ['id' => $depId, 'import' => $import];
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

    /**
     * The queued modules that are registered, in queue order, each as
     * registered(): what the reference's get_marked_for_enqueue() answers,
     * a module whose dependency is missing included.
     *
     * @return array<string, array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}>
     */
    public function markedForEnqueue(): array
    {
        $marked = [];
        foreach ($this->queue as $id) {
            if (isset($this->registered[$id])) {
                $marked[$id] = $this->registered($id);
            }
        }
        return $marked;
    }

    /**
     * The registered dependencies of some modules, following the given
     * import types, each as registered() (get_dependencies()).
     *
     * @param list<string> $ids
     * @param list<string> $types
     * @return array<string, array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}>
     */
    public function dependencyRecords(array $ids, array $types): array
    {
        $records = [];
        foreach ($this->dependencies($ids, $types) as $id) {
            $records[$id] = $this->registered($id);
        }
        return $records;
    }

    /** A registered module's address as the page loads it (get_src()); '' for one not registered. */
    public function source(string $id): string
    {
        return isset($this->registered[$id]) ? $this->urlOf($id) : '';
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
        return AssetQueue::inlineTag(Json::encode(['imports' => $imports], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['type' => 'importmap', 'id' => 'wp-importmap']);
    }

    /**
     * The modulepreload links: the queued modules' static dependencies not
     * queued themselves, each after its own (depth first, in queue order),
     * fetched at the priority priorities() gives, its own named beside it
     * when that differs.
     */
    public function printPreloads(): string
    {
        $this->tellMissing();
        $out = '';
        foreach ($this->dependenciesFirst($this->marked()) as $id) {
            if (in_array($id, $this->queue, true)) {
                continue;
            }
            $attributes = '';
            foreach ($this->priorityAttributes($id) as $name => $value) {
                $attributes .= ' ' . $name . '="' . $value . '"';
            }
            $out .= '<link rel="modulepreload" href="' . $this->attr($this->urlOf($id)) . '" id="' . $this->attr($id . '-js-modulepreload') . '"' . $attributes . '>' . "\n";
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
            $out .= AssetQueue::inlineTag(Json::encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['type' => 'application/json', 'id' => 'wp-script-module-data-' . $id]);
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
        $this->tellMissing();
        $out = '';
        foreach ($this->queuedInOrder() as $id) {
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
                + $this->priorityAttributes($id)
                + $item['attributes'];
            $out .= AssetQueue::tag($attributes);
        }
        return $out;
    }

    /**
     * The queued modules in the order they print: each after the queued
     * modules it imports, statically or dynamically, through any that are
     * not queued (depth first, in queue order), as the reference prints them
     * (probe script-modules-printing).
     *
     * @return list<string>
     */
    private function queuedInOrder(): array
    {
        $marked = $this->marked();
        $order = [];
        foreach ($marked as $id) {
            foreach ([...$this->dependenciesFirst([$id], ['static', 'dynamic']), $id] as $each) {
                if (in_array($each, $marked, true) && !in_array($each, $order, true)) {
                    $order[] = $each;
                }
            }
        }
        return $order;
    }

    /**
     * The priority a module is fetched at (probe script-modules-printing):
     * the highest (high, then auto, then low; none named is auto) of the
     * queued modules among it and those that import it, statically or
     * dynamically, directly or through others; its own when none is queued.
     * Who imports a module is remembered from the first time it is asked
     * (dependentsOf), as the reference remembers it.
     */
    private function priorityOf(string $id): string
    {
        $rank = ['low' => 0, 'auto' => 1, 'high' => 2];
        $marked = $this->marked();
        $best = null;
        $seen = [];
        $walk = [$id];
        while ($walk !== []) {
            $each = array_shift($walk);
            if (isset($seen[$each])) {
                continue;
            }
            $seen[$each] = true;
            if (in_array($each, $marked, true)) {
                $priority = (string) $this->registered[$each]['fetchpriority'];
                $best = $best === null || ($rank[$priority] ?? 1) > ($rank[$best] ?? 1) ? $priority : $best;
            }
            array_push($walk, ...$this->dependentsOf($each));
        }
        return $best ?? (string) $this->registered[$id]['fetchpriority'];
    }

    /**
     * The registered modules that import one, statically or dynamically:
     * worked out the first time it is asked and kept for the rest of the
     * request, so a module registered after that is not among them, as on
     * the reference.
     *
     * @return list<string>
     */
    private function dependentsOf(string $id): array
    {
        if (!isset($this->dependents[$id])) {
            $this->dependents[$id] = [];
            foreach ($this->registered as $other => $item) {
                if (in_array($id, array_column($item['dependencies'], 'id'), true)) {
                    $this->dependents[$id][] = (string) $other;
                }
            }
        }
        return $this->dependents[$id];
    }

    /**
     * A module tag's priority attributes: fetchpriority unless it is fetched
     * at auto, and data-wp-fetchpriority naming its own when that differs
     * and is not auto.
     *
     * @return array<string, string>
     */
    private function priorityAttributes(string $id): array
    {
        $own = (string) $this->registered[$id]['fetchpriority'];
        $fetched = $this->priorityOf($id);
        return ($fetched === 'auto' ? [] : ['fetchpriority' => $fetched]) + ($own !== $fetched && $own !== 'auto' ? ['data-wp-fetchpriority' => $own] : []);
    }

    /**
     * A queued module whose dependencies are not all registered is told
     * once, as the page first prints its module tags or preloads, not as it
     * registers (a dependency may be registered later).
     */
    private function tellMissing(): void
    {
        foreach ($this->queue as $id) {
            if (!isset($this->registered[$id]) || isset($this->told[$id])) {
                continue;
            }
            $missing = array_values(array_filter(array_column($this->registered[$id]['dependencies'], 'id'), fn (string $dep): bool => !isset($this->registered[$dep])));
            if ($missing !== []) {
                $this->told[$id] = true;
                $this->wrong('WP_Script_Modules::register', 'The script module with the ID "' . $id . '" was enqueued with dependencies that are not registered: ' . implode(', ', $missing) . '.', '6.5.0');
            }
        }
    }

    /**
     * The modules' registered dependencies of the import types given (static
     * unless asked), each after its own: depth first, in the order the
     * modules and their dependencies are given.
     *
     * @param list<string> $ids
     * @param list<string> $types
     * @return list<string>
     */
    private function dependenciesFirst(array $ids, array $types = ['static']): array
    {
        $order = [];
        $seen = [];
        $visit = function (string $id) use (&$visit, &$order, &$seen, $types): void {
            foreach ($this->registered[$id]['dependencies'] ?? [] as $dep) {
                if (!in_array($dep['import'], $types, true) || !isset($this->registered[$dep['id']]) || isset($seen[$dep['id']])) {
                    continue;
                }
                $seen[$dep['id']] = true;
                $visit($dep['id']);
                $order[] = $dep['id'];
            }
        };
        foreach ($ids as $id) {
            $visit($id);
        }
        return $order;
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
     * Every registered dependency of the given modules, following only the
     * given import types: the modules' own dependencies in declaration
     * order, then theirs, a level at a time (probe script-modules-private).
     *
     * @param list<string> $ids
     * @param list<string> $types
     * @return list<string>
     */
    private function dependencies(array $ids, array $types): array
    {
        $found = [];
        $level = $ids;
        while ($level !== []) {
            $next = [];
            foreach ($level as $id) {
                foreach ($this->registered[$id]['dependencies'] ?? [] as $dep) {
                    if (!in_array($dep['import'], $types, true) || !isset($this->registered[$dep['id']]) || in_array($dep['id'], $found, true)) {
                        continue;
                    }
                    $found[] = $dep['id'];
                    $next[] = $dep['id'];
                }
            }
            $level = $next;
        }
        return $found;
    }

    /** A module's address with its version, as plugins may change it (script_module_loader_src). */
    private function urlOf(string $id): string
    {
        return (string) Runtime::hooks()->filter('script_module_loader_src', [($this->url)($this->registered[$id]['src'], $this->registered[$id]['version']), $id]);
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
