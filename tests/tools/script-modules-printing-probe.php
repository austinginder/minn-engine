<?php
/**
 * Script modules as the reference prints them, on registries of their own
 * (probe script-modules-printing): a new WP_Script_Modules keeps its own
 * registrations apart from the page's and from another new one; when the
 * notice for a module whose dependency is missing comes (counted after each
 * step: registering, queueing, and each of the printing calls, twice; which
 * call tells it first; once per module); and the preloads' order for
 * dependencies queued modules share, a chain of them included; and the
 * priority each preload and queued module is fetched at, worked out again
 * as the queue grows and shrinks (the highest of the queued modules that
 * need it, through static or dynamic imports), its own named beside it when
 * that differs; who imports a module remembered from the first print that
 * asks, so a module registered after it does not count; and the queued
 * modules printed after the queued ones they import. The page's own
 * registry is never touched. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$printed = static function (WP_Script_Modules $modules, string $method): string {
    ob_start();
    $modules->$method();
    return (string) preg_replace(['#https?://[^/"\']+#', '/\?ver=[^"\']*/'], ['{host}', '?ver={v}'], (string) ob_get_clean());
};
$methods = ['print_import_map', 'print_head_enqueued_script_modules', 'print_enqueued_script_modules', 'print_script_module_preloads', 'print_script_module_data'];

// Registries of their own.
$first = new WP_Script_Modules();
$first->register('zz-only-first', '/zz/first.js');
$first->enqueue('zz-only-first');
$second = new WP_Script_Modules();
$second->enqueue('zz-only-first');
$page = wp_script_modules();
$say('a new registry keeps its own', [
    'the first prints its module' => str_contains($printed($first, 'print_head_enqueued_script_modules'), 'zz-only-first'),
    'a second new one does not know it' => $printed($second, 'print_head_enqueued_script_modules') === '' && $second->get_queue() === ['zz-only-first'],
    'the page\'s does not know it' => !in_array('zz-only-first', $page->get_queue(), true),
    'wp_script_modules() is one object' => wp_script_modules() === $page,
]);

// When the missing dependency is told.
$heard = [];
$listen = static function ($function, $message) use (&$heard): void {
    if (str_contains((string) $message, 'zz-')) {
        $heard[] = (string) $function;
    }
};
add_action('doing_it_wrong_run', $listen, 10, 2);
$lonely = new WP_Script_Modules();
$steps = [];
$lonely->register('zz-needs-missing', '/zz/needs.js', ['zz-not-registered']);
$steps['registered'] = count($heard);
$lonely->enqueue('zz-needs-missing');
$steps['queued'] = count($heard);
foreach ([1, 2] as $round) {
    foreach ($methods as $method) {
        $output = $printed($lonely, $method);
        $steps["{$method} ({$round})"] = [count($heard), $output];
    }
}
$say('the missing dependency, told', ['after each step' => $steps, 'by' => array_values(array_unique($heard))]);
// Which printing call tells it first, each on a registry of its own; and two such modules.
$first = [];
foreach (['print_script_module_data', 'print_enqueued_script_modules', 'print_script_module_preloads'] as $method) {
    $heard = [];
    $alone = new WP_Script_Modules();
    $alone->register('zz-needs-missing', '/zz/needs.js', ['zz-not-registered']);
    $alone->enqueue('zz-needs-missing');
    $printed($alone, $method);
    $first[$method] = count($heard);
}
$heard = [];
$pair = new WP_Script_Modules();
$pair->register('zz-needs-one', '/zz/one.js', ['zz-not-registered']);
$pair->register('zz-needs-two', '/zz/two.js', ['zz-not-registered-either']);
$pair->enqueue('zz-needs-one');
$pair->enqueue('zz-needs-two');
$printed($pair, 'print_head_enqueued_script_modules');
$printed($pair, 'print_enqueued_script_modules');
$first['two such modules, head then footer'] = count($heard);
$heard = [];
$unqueued = new WP_Script_Modules();
$unqueued->register('zz-needs-unqueued', '/zz/unqueued.js', ['zz-not-registered']);
$printed($unqueued, 'print_head_enqueued_script_modules');
$first['registered, never queued'] = count($heard);
remove_action('doing_it_wrong_run', $listen, 10);
$say('the missing dependency, first told by', $first);

// Preloads: shared dependencies and a chain, each printed after its own dependencies.
$chain = new WP_Script_Modules();
$chain->register('zz-leaf', '/zz/leaf.js');
$chain->register('zz-middle', '/zz/middle.js', ['zz-leaf']);
$chain->register('zz-shared', '/zz/shared.js', ['zz-middle']);
$chain->register('zz-other-dep', '/zz/other.js');
$chain->register('zz-first', '/zz/first.js', ['zz-shared']);
$chain->register('zz-second', '/zz/second.js', ['zz-other-dep', 'zz-shared']);
$chain->register('zz-third', '/zz/third.js', [['id' => 'zz-dynamic-only', 'import' => 'dynamic'], 'zz-other-dep']);
$chain->register('zz-dynamic-only', '/zz/dynamic.js');
foreach (['zz-first', 'zz-second', 'zz-third'] as $id) {
    $chain->enqueue($id);
}
$say('preloads', ['preloads' => $printed($chain, 'print_script_module_preloads'), 'import map' => $printed($chain, 'print_import_map')]);

// The priority a module is fetched at, printed after each change to the queue.
$ranked = static function (): WP_Script_Modules {
    $modules = new WP_Script_Modules();
    $modules->register('zz-plain', '/zz/plain.js');
    $modules->register('zz-low-dep', '/zz/low-dep.js', [], false, ['fetchpriority' => 'low']);
    $modules->register('zz-high-dep', '/zz/high-dep.js', [], false, ['fetchpriority' => 'high']);
    $modules->register('zz-auto', '/zz/auto.js', ['zz-plain', 'zz-low-dep', 'zz-high-dep']);
    $modules->register('zz-low', '/zz/low.js', ['zz-auto'], false, ['fetchpriority' => 'low']);
    $modules->register('zz-high', '/zz/high.js', ['zz-low', ['id' => 'zz-dynamic', 'import' => 'dynamic']], false, ['fetchpriority' => 'high']);
    $modules->register('zz-dynamic', '/zz/dynamic.js', ['zz-low-dep'], false, ['fetchpriority' => 'low']);
    $modules->register('zz-asks', '/zz/asks.js', [['id' => 'zz-dynamic', 'import' => 'dynamic']], false, ['fetchpriority' => 'high']);
    return $modules;
};
$both = static fn (WP_Script_Modules $modules): array => [$printed($modules, 'print_script_module_preloads'), $printed($modules, 'print_enqueued_script_modules')];
$modules = $ranked();
$modules->enqueue('zz-low');
$steps = ['low queued' => $both($modules)];
$modules->enqueue('zz-high');
$steps['then high'] = $both($modules);
$modules->dequeue('zz-high');
$steps['high dequeued'] = $both($modules);
$say('priorities as the queue changes', $steps);
$modules = $ranked();
$modules->enqueue('zz-auto');
$modules->enqueue('zz-low');
$say('an auto module and a low one that needs it, queued', $both($modules));
$modules = $ranked();
$modules->enqueue('zz-dynamic');
$modules->enqueue('zz-asks');
$say('a low module a high one imports dynamically, queued', $both($modules));

// Who imports a module is remembered from the first print that asks: a module registered after that does not count,
// nor does one registered again after its removal.
$modules = new WP_Script_Modules();
$modules->register('zz-base', '/zz/base.js', [], false, ['fetchpriority' => 'low']);
$modules->register('zz-middle', '/zz/middle.js', ['zz-base'], false, ['fetchpriority' => 'low']);
$modules->enqueue('zz-middle');
$remembered = ['first' => $both($modules)];
$modules->register('zz-later', '/zz/later.js', ['zz-middle'], false, ['fetchpriority' => 'high']);
$modules->enqueue('zz-later');
$remembered['a high module registered after'] = $both($modules);
$say('who imports a module, remembered', $remembered);
$modules = new WP_Script_Modules();
$modules->register('zz-base', '/zz/base.js', [], false, ['fetchpriority' => 'low']);
$modules->register('zz-high', '/zz/high.js', ['zz-base'], false, ['fetchpriority' => 'high']);
$modules->enqueue('zz-high');
$again = ['first' => $printed($modules, 'print_script_module_preloads')];
$modules->dequeue('zz-high');
$modules->deregister('zz-high');
$modules->register('zz-high', '/zz/high-two.js', ['zz-base'], false, ['fetchpriority' => 'high']);
$modules->register('zz-plain', '/zz/plain.js', ['zz-base']);
$modules->enqueue('zz-plain');
$modules->enqueue('zz-base');
$again['removed, registered again, and a new one'] = $printed($modules, 'print_enqueued_script_modules');
$say('who imports a module, kept through removal', $again);

// The queued modules print after the queued ones they import, through others and dynamic imports.
$orders = [];
foreach ([['zz-a', 'zz-l'], ['zz-x', 'zz-a', 'zz-l'], ['zz-a', 'zz-x', 'zz-l'], ['zz-c', 'zz-l', 'zz-a'], ['zz-d', 'zz-l', 'zz-x'], ['zz-e', 'zz-l'], ['zz-f', 'zz-a', 'zz-l']] as $queue) {
    $modules = new WP_Script_Modules();
    $modules->register('zz-l', '/zz/l.js');
    $modules->register('zz-x', '/zz/x.js');
    $modules->register('zz-a', '/zz/a.js', ['zz-l']);
    $modules->register('zz-c', '/zz/c.js', ['zz-a', 'zz-x']);
    $modules->register('zz-d', '/zz/d.js', [['id' => 'zz-l', 'import' => 'dynamic']]);
    $modules->register('zz-e', '/zz/e.js', ['zz-mid']);
    $modules->register('zz-mid', '/zz/mid.js', ['zz-l']);
    $modules->register('zz-f', '/zz/f.js', ['zz-l', 'zz-a']);
    foreach ($queue as $id) {
        $modules->enqueue($id);
    }
    $orders[implode(',', $queue)] = preg_match_all('#id="(zz-[a-z]+)-js-module"#', $printed($modules, 'print_enqueued_script_modules'), $found) ? $found[1] : [];
}
$say('the order queued modules print in', $orders);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
