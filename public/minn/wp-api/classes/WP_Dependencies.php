<?php

use Minn\Runtime\Assets;

/**
 * The dependency registry in the shape plugins inspect and drive (probe
 * dependencies-classes): $wp_scripts->registered[$handle]->src and friends,
 * kept current from Runtime\Assets, and the reference's walk over it.
 * all_deps lines up a handle's dependencies before it in $to_do, each in
 * the lowest group that needs it; a handle whose dependency is not
 * registered is passed over, with a notice once per handle. do_items
 * prints what all_deps lined up and marks it done. A registry made with no
 * Assets of its own (new WP_Scripts) gets a fresh one.
 */
#[AllowDynamicProperties]
class WP_Dependencies
{
    public $registered = [];
    public $queue = [];
    public $to_do = [];
    public $done = [];
    public $args = [];
    public $groups = [];
    public $group = 0;
    protected $assets;
    /** @var array<string, array{0: mixed, 1: list<string>, 2: mixed, 3: mixed}> each handle as the view last showed it */
    private $synced = [];
    /** @var array{0: array, 1: array} the queue and the done list as the view last showed them */
    private $synced_lists = [[], []];
    private $reconciling = false;
    /** @var array<string, true> handles already reported for unregistered dependencies */
    private $warned = [];

    public function __construct(?Assets $assets = null)
    {
        $this->assets = $assets ?? new Assets('dependency');
        $this->assets->watch(function (): void {
            $this->sync();
        }, function (): void {
            $this->push();
        });
    }

    /** Refreshes the public arrays from the registry (after handing back what a plugin edited in them); the registry calls this after every change. */
    public function sync(): void
    {
        $this->push();
        $this->registered = [];
        foreach ($this->assets->items() as $handle => $item) {
            $dep = new _WP_Dependency($handle, $item['src'], $item['deps'], $item['ver'], $item['extra']);
            $dep->extra = ['before' => $item['inline']['before'], 'after' => $item['inline']['after']] + $item['data'];
            if (isset($item['translations'])) {
                $dep->set_translations($item['translations']['domain'], $item['translations']['path']);
            }
            $this->registered[$handle] = $dep;
        }
        $this->queue = $this->assets->queue();
        $this->done = $this->assets->doneList();
        $this->synced_lists = [$this->queue, $this->done];
        $this->synced = array_map(static fn (_WP_Dependency $dep): array => [$dep->src, array_values((array) $dep->deps), $dep->ver, $dep->args, $dep->extra['data'] ?? null], $this->registered);
    }

    /** What a plugin did to $registered directly, handed to the registry (Runtime\AssetEdits). */
    private function reconcile(): void
    {
        if ($this->reconciling) {
            return;
        }
        $this->reconciling = true;
        try {
            Minn\Runtime\AssetEdits::apply($this->assets, $this->registered, $this->synced);
        } finally {
            $this->reconciling = false;
        }
    }

    /** Hands back what a plugin edited in place (handles, the queue, the done list) before the view is refreshed or printed from. */
    public function push(): void
    {
        $this->reconcile();
        if ($this->queue !== $this->synced_lists[0]) {
            $this->assets->setQueue((array) $this->queue);
        }
        if ($this->done !== $this->synced_lists[1]) {
            $this->assets->setDone((array) $this->done);
        }
        $this->synced_lists = [(array) $this->queue, (array) $this->done];
    }

    public function do_items($handles = false, $group = false)
    {
        $this->push();
        $this->all_deps($handles === false ? $this->queue : (array) $handles);
        foreach ($this->to_do as $key => $handle) {
            if (!in_array($handle, $this->done, true) && isset($this->registered[$handle]) && $this->do_item($handle, $group)) {
                $this->assets->markDone($handle);
            }
            unset($this->to_do[$key]);
        }
        return $this->done;
    }

    public function do_item($handle, $group = false)
    {
        return isset($this->registered[$handle]);
    }

    public function all_deps($handles, $recursion = false, $group = false)
    {
        if (!$recursion) {
            $this->push();
        }
        foreach ((array) $handles as $handle) {
            if ($this->line_up((string) $handle, (bool) $recursion, $group) === false && $recursion) {
                return false;
            }
        }
        return true;
    }

    public function add($handle, $src, $deps = [], $ver = false, $args = null)
    {
        return $this->assets->register((string) $handle, $src === false ? false : (string) $src, (array) $deps, $ver, $args);
    }

    public function add_data($handle, $key, $value)
    {
        return $this->assets->addData((string) $handle, (string) $key, $value);
    }

    public function get_data($handle, $key)
    {
        return $this->assets->data((string) $handle, (string) $key);
    }

    public function remove($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets->deregister((string) $handle);
        }
    }

    public function enqueue($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets->enqueue((string) $handle);
        }
    }

    public function dequeue($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets->dequeue((string) $handle);
        }
    }

    protected function recurse_deps($queue, $handle)
    {
        return $this->assets->reaches(array_map('strval', (array) $queue), (string) $handle);
    }

    public function query($handle, $status = 'registered')
    {
        return match ($status) {
            'registered', 'scripts' => $this->registered[$handle] ?? false,
            'enqueued', 'queue' => $this->assets->enqueued((string) $handle),
            'done' => $this->assets->done((string) $handle),
            default => false,
        };
    }

    public function set_group($handle, $recursion, $group)
    {
        $group = (int) $group;
        if (isset($this->groups[$handle]) && $this->groups[$handle] <= $group) {
            return false;
        }
        $this->groups[$handle] = $group;
        return true;
    }

    /** A weak etag over the WordPress version and each registered handle's version (an unversioned one counts as the WordPress version). */
    public function get_etag($load)
    {
        $etag = 'WP:' . $GLOBALS['wp_version'] . ';';
        foreach ((array) $load as $handle) {
            if (isset($this->registered[$handle])) {
                $ver = $this->registered[$handle]->ver;
                $etag .= $handle . ':' . ($ver === null ? $GLOBALS['wp_version'] : (string) $ver) . ';';
            }
        }
        return 'W/"' . md5($etag) . '"';
    }

    protected function get_dependency_warning_message($handle, $missing_dependency_handles)
    {
        return sprintf('The handle "%1$s" was enqueued with dependencies that are not registered: %2$s.', $handle, implode(', ', $missing_dependency_handles));
    }

    /**
     * One handle of all_deps ("handle?args" keeps its args): null when it is
     * passed over (done, or already lined up in a group as low), false when
     * it cannot load, true when it is in $to_do.
     */
    private function line_up(string $handle, bool $recursion, $group): ?bool
    {
        [$name, $args] = array_pad(explode('?', $handle, 2), 2, null);
        $queued = in_array($name, $this->to_do, true);
        if (in_array($name, $this->done, true) || (!$this->set_group($name, $recursion, $group) && $queued)) {
            return null;
        }
        if (!$this->loadable($name)) {
            return false;
        }
        if (!$queued) {
            $this->args = $args === null ? $this->args : array_replace($this->args, [$name => $args]);
            $this->to_do[] = $name;
        }
        return true;
    }

    /** Whether a handle can load: registered, its dependencies registered (the missing ones reported once) and each loadable in turn. */
    private function loadable(string $name): bool
    {
        if (!$this->assets->registered($name)) {
            return false;
        }
        $deps = $this->assets->dependencies($name);
        $missing = array_values(array_filter($deps, fn (string $dep): bool => !$this->assets->registered($dep)));
        if ($missing !== [] && !isset($this->warned[$name])) {
            $this->warned[$name] = true;
            _doing_it_wrong(static::class . '::add', $this->get_dependency_warning_message($name, $missing), '6.9.1');
        }
        return $missing === [] && ($deps === [] || $this->all_deps($deps, true, $this->groups[$name] ?? false));
    }
}

#[AllowDynamicProperties]
class _WP_Dependency implements \Minn\Objects\Dependency
{
    public $handle;
    public $src;
    public $deps = [];
    public $ver = false;
    public $args = null;
    public $extra = [];
    public $textdomain;
    public $translations_path;

    public function __construct(...$args)
    {
        [$this->handle, $this->src, $this->deps, $this->ver, $this->args] = $args + [null, null, [], false, null];
        if (!is_array($this->deps)) {
            $this->deps = [];
        }
    }

    public function add_data($name, $data)
    {
        if (!is_scalar($name)) {
            return false;
        }
        $this->extra[$name] = $data;
        return true;
    }

    public function set_translations($domain, $path = '')
    {
        $this->textdomain = $domain;
        $this->translations_path = $path;
        return true;
    }
}
