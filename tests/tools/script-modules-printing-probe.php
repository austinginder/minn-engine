<?php
/**
 * Script modules as the reference prints them, on registries of their own
 * (probe script-modules-printing): a new WP_Script_Modules keeps its own
 * registrations apart from the page's and from another new one; when the
 * notice for a module whose dependency is missing comes (counted after each
 * step: registering, queueing, and each of the printing calls, twice; which
 * call tells it first; once per module); and the preloads' order for
 * dependencies queued modules share, a chain of them included. The page's
 * own registry is never touched. Same protocol as api-probe.php.
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

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
