<?php

declare(strict_types=1);

use Minn\Objects\Dependency;
use Minn\Runtime\Assets;
use Minn\Runtime\AssetView;
use Minn\Runtime\Handoff;

/**
 * A registry's plugin-facing view ($wp_scripts->registered, ->queue, ->done)
 * kept current one handle at a time: what a plugin edits in it reaches the
 * registry before that handle is changed or read, and a page that registers
 * hundreds of handles builds each handle's view a few times, not the whole
 * view at every change (which cost tens of milliseconds a page).
 */
$built = 0;
Handoff::bind(['newDependency' => static function (...$args) use (&$built): Dependency {
    $built++;
    return new class (...$args) implements Dependency {
        public $extra = [];
        public $textdomain;
        public $translations_path;

        public function __construct(public $handle = null, public $src = null, public $deps = [], public $ver = false, public $args = null)
        {
        }
    };
}]);
$view = static function (): array {
    $owner = new class () {
        public $registered = [];
        public $queue = [];
        public $done = [];
    };
    $assets = new Assets('script');
    return [$assets, $owner, new AssetView($assets, $owner)];
};

return [
    'registering three hundred handles with data builds each handle\'s view twice, not the whole view each time' => static function () use ($view, &$built): bool|string {
        [$assets] = $view();
        $built = 0;
        for ($i = 0; $i < 300; $i++) {
            $assets->register("minn-unit-{$i}", "/{$i}.js", $i > 0 ? ['minn-unit-' . ($i - 1)] : [], false, null);
            $assets->addData("minn-unit-{$i}", 'group', 1);
        }
        return $built === 600 ? true : "{$built} views built";
    },
    'the view shows what was registered, queued and printed' => static function () use ($view): bool|string {
        [$assets, $owner] = $view();
        $assets->register('a', '/a.js', [], '1', null);
        $assets->addInline('a', 'after();', 'after');
        $assets->enqueue('a');
        $assets->markDone('a');
        $a = $owner->registered['a'] ?? null;
        return $a !== null && $a->src === '/a.js' && $a->extra['after'] === ['after();'] && $owner->queue === ['a'] && $owner->done === ['a'] ? true : json_encode([$a, $owner->queue, $owner->done]);
    },
    'a handle a plugin unset from the view and registers again is registered anew' => static function () use ($view): bool|string {
        [$assets, $owner] = $view();
        $assets->register('a', '/old.js', [], false, null);
        unset($owner->registered['a']);
        $again = $assets->register('a', '/new.js', [], false, null);
        return $again && $assets->item('a')['src'] === '/new.js' && $owner->registered['a']->src === '/new.js' ? true : json_encode([$again, $assets->item('a')]);
    },
    'an edit to one handle reaches the registry when that handle is next read' => static function () use ($view): bool|string {
        [$assets, $owner] = $view();
        $assets->register('g', '/g.js', [], false, null);
        $assets->register('h', '/h.js', [], false, null);
        $owner->registered['g']->deps = ['h'];
        $assets->register('x', '/x.js', [], false, null);
        return $assets->dependencies('g') === ['h'] ? true : json_encode($assets->dependencies('g'));
    },
    'every edit, a handle added in the view and the queue reach the registry when the view is pushed' => static function () use ($view): bool|string {
        [$assets, $owner, $registry] = $view();
        $assets->register('g', '/g.js', [], false, null);
        $owner->registered['g']->src = '/cdn/g.js';
        $owner->registered['n'] = Handoff::newDependency('n', '/n.js', ['g'], false, null);
        $owner->queue = ['n'];
        $registry->push();
        return $assets->items()['g']['src'] === '/cdn/g.js' && isset($assets->items()['n']) && $assets->toPrint() === ['g', 'n'] ? true : json_encode([$assets->items(), $assets->toPrint()]);
    },
];
