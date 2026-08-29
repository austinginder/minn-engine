<?php
/**
 * Hook-API behaviour probe. Runs unchanged on the reference (wp eval-file)
 * and on the engine's facade (tests/hooks.test.php); prints one JSON
 * transcript. The fixture captured from the reference is the spec.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$tag = static function (string $t) use (&$log) { return static function (...$args) use ($t, &$log) { $log[] = ['cb', $t, $args]; return is_string($args[0] ?? null) ? $args[0] . "+{$t}" : ($args[0] ?? null); }; };

// 1. Priority order and same-priority insertion order.
add_filter('probe_order', $tag('p20'), 20);
add_filter('probe_order', $tag('p10a'));
add_filter('probe_order', $tag('p5'), 5);
add_filter('probe_order', $tag('p10b'), 10);
add_filter('probe_order', $tag('pneg'), -1);
$say('order result', apply_filters('probe_order', 'v'));

// 2. accepted_args.
add_filter('probe_args', $tag('default'), 10);
add_filter('probe_args', $tag('three'), 10, 3);
add_filter('probe_args', $tag('zero'), 10, 0);
add_filter('probe_args', $tag('more-than-passed'), 10, 5);
$say('args result', apply_filters('probe_args', 'a', 'b', 'c'));

// 3. No callbacks: value passes through; extra args ignored.
$say('no callbacks', apply_filters('probe_nobody', 'same', 1, 2));
$say('no callbacks null', apply_filters('probe_nobody', null));

// 4. has_filter / has_action.
$say('has_filter none', has_filter('probe_nobody'));
$say('has_filter any', has_filter('probe_order'));
$fn = static function ($v) { return $v; };
add_filter('probe_has', $fn, 33);
$say('has_filter cb', has_filter('probe_has', $fn));
$say('has_filter other cb', has_filter('probe_has', 'strtoupper'));
add_filter('probe_has', 'strtoupper', 7);
add_filter('probe_has', 'strtoupper', 9);
$say('has_filter twice', has_filter('probe_has', 'strtoupper'));
$say('has_action alias', has_action('probe_has', $fn));
$say('add_filter return', add_filter('probe_ret', 'strtolower'));
$say('add_action return', add_action('probe_ret', 'strtolower'));

// 5. remove_filter.
$say('remove ok', remove_filter('probe_has', 'strtoupper', 7));
$say('remove wrong prio', remove_filter('probe_has', 'strtoupper', 8));
$say('remove default prio missing', remove_filter('probe_has', 'strtoupper'));
$say('has after remove', has_filter('probe_has', 'strtoupper'));
$say('remove_action alias', remove_action('probe_has', 'strtoupper', 9));
$say('has after all removed', has_filter('probe_has', 'strtoupper'));
$say('has_filter still any', has_filter('probe_has'));

// 6. Mutation during a run.
$selfRemover = static function ($v) use (&$selfRemover) { remove_filter('probe_mut', $selfRemover, 10); return $v . '+self'; };
add_filter('probe_mut', $selfRemover, 10);
$laterSame = $tag('later-same');
add_filter('probe_mut', $laterSame, 10);
$later20 = $tag('later-20');
add_filter('probe_mut', $later20, 20);
add_filter('probe_mut', static function ($v) use ($laterSame, $later20, &$log, $tag) {
    remove_filter('probe_mut', $laterSame, 10);
    remove_filter('probe_mut', $later20, 20);
    add_filter('probe_mut', $tag('added-same-prio'), 10);
    add_filter('probe_mut', $tag('added-higher'), 30);
    add_filter('probe_mut', $tag('added-lower'), 1);
    return $v . '+mutator';
}, 10);
$say('mutation result', apply_filters('probe_mut', 'v'));
$say('mutation second run', apply_filters('probe_mut', 'w'));

// 7. current_filter / doing_filter / doing_action / current_action, nested.
add_action('probe_outer', static function () use ($say) {
    $say('outer current_filter', current_filter());
    $say('outer current_action', current_action());
    $say('doing outer', doing_action('probe_outer'));
    $say('doing inner before', doing_filter('probe_inner'));
    apply_filters('probe_inner', 'x');
    $say('doing inner after', doing_filter('probe_inner'));
    $say('doing anything', doing_filter());
});
add_filter('probe_inner', static function ($v) use ($say) {
    $say('inner current_filter', current_filter());
    $say('inner doing outer', doing_filter('probe_outer'));
    return $v;
});
$say('current_filter outside', current_filter());
$say('doing outside', doing_filter());
$say('doing_action outside', doing_action());
do_action('probe_outer');
$say('current_filter after', current_filter());

// 8. did_action / did_filter.
$say('did before', did_action('probe_count'));
do_action('probe_count');
do_action('probe_count', 1, 2);
$say('did after', did_action('probe_count'));
$say('did_filter before', did_filter('probe_fcount'));
apply_filters('probe_fcount', 'x');
$say('did_filter after', did_filter('probe_fcount'));
$say('did_filter of action', did_filter('probe_count'));
$say('did_action of filter', did_action('probe_fcount'));

// 9. remove_all_filters.
add_filter('probe_all', 'strtoupper', 5);
add_filter('probe_all', 'strtolower', 10);
add_filter('probe_all', 'trim', 10);
$say('remove_all prio', remove_all_filters('probe_all', 10));
$say('after remove_all prio', apply_filters('probe_all', ' Ab '));
$say('remove_all', remove_all_filters('probe_all'));
$say('after remove_all', apply_filters('probe_all', ' Ab '));
$say('has after remove_all', has_filter('probe_all'));
$say('remove_all unknown', remove_all_actions('probe_never'));

// 10. The all hook.
$allSeen = [];
add_action('all', static function (...$args) use (&$allSeen) { if (str_starts_with((string) ($args[0] ?? ''), 'probe_every')) { $allSeen[] = $args; } });
do_action('probe_every_action', 'a1', 'a2');
apply_filters('probe_every_filter', 'f1', 'f2');
$say('all hook saw', $allSeen);
remove_all_actions('all');

// 11. Callback shapes.
class Minn_Probe_Obj { public $n; public function __construct($n) { $this->n = $n; } public function m($v) { return $v . '+m' . $this->n; } public static function s($v) { return $v . '+s'; } public function __invoke($v) { return $v . '+invoke'; } }
$o1 = new Minn_Probe_Obj(1);
$o2 = new Minn_Probe_Obj(2);
add_filter('probe_shapes', 'strtoupper', 1);
add_filter('probe_shapes', [$o1, 'm']);
add_filter('probe_shapes', [$o2, 'm']);
add_filter('probe_shapes', 'Minn_Probe_Obj::s');
add_filter('probe_shapes', ['Minn_Probe_Obj', 's']);
add_filter('probe_shapes', $o1);
$say('shapes result', apply_filters('probe_shapes', 'v'));
$say('has obj method', has_filter('probe_shapes', [$o1, 'm']));
$say('has other obj same method', has_filter('probe_shapes', [new Minn_Probe_Obj(1), 'm']));
$say('has static string', has_filter('probe_shapes', 'Minn_Probe_Obj::s'));
$say('has static array', has_filter('probe_shapes', ['Minn_Probe_Obj', 's']));
$say('remove static via array', remove_filter('probe_shapes', ['Minn_Probe_Obj', 's']));
$say('has static string after', has_filter('probe_shapes', 'Minn_Probe_Obj::s'));
$say('remove invokable', remove_filter('probe_shapes', $o1));
$say('shapes after removes', apply_filters('probe_shapes', 'v'));

// 12. ref_array forms.
add_filter('probe_ref', $tag('ref'), 10, 3);
$say('ref filter', apply_filters_ref_array('probe_ref', ['r', 1, 2]));
add_action('probe_ref_a', $tag('refa'), 10, 2);
do_action_ref_array('probe_ref_a', ['x', 'y']);
$say('ref action return', do_action_ref_array('probe_ref_a', ['x', 'y']));
$say('do_action return', do_action('probe_ref_a', 'z'));

// 13. A filter returning null / false / array propagates.
add_filter('probe_null', static fn ($v) => null);
$say('null propagates', apply_filters('probe_null', 'v'));
add_filter('probe_arr', static fn ($v) => array_merge($v, ['b']));
$say('array filter', apply_filters('probe_arr', ['a']));

// 14. Recursion: applying the same hook inside its own callback.
add_filter('probe_rec', static function ($v) use ($say) {
    if ($v === 'top') {
        $say('rec inner', apply_filters('probe_rec', 'inner'));
        $say('rec current', current_filter());
    }
    return $v . '+r';
}, 10);
$say('rec result', apply_filters('probe_rec', 'top'));

// 15. Priority extremes and string priorities.
add_filter('probe_ext', $tag('max'), PHP_INT_MAX);
add_filter('probe_ext', $tag('min'), PHP_INT_MIN);
add_filter('probe_ext', $tag('str10'), '10');
add_filter('probe_ext', $tag('int10'), 10);
$say('ext result', apply_filters('probe_ext', 'v'));
$say('has str prio', has_filter('probe_ext', $tag('nope')));

// 16. Actions receive args; return values ignored; accepted_args beyond passed.
add_action('probe_act', $tag('act'), 10, 4);
do_action('probe_act', 'only');
add_action('probe_act_arr', $tag('act-arr'), 10, 2);
do_action('probe_act_arr', ['an', 'array'], 'second');

// 17. Removing a hook while a different hook runs; has_filter inside callback.
add_filter('probe_x', $tag('x1'), 10);
add_filter('probe_y', static function ($v) use ($say) { $say('has x inside y', has_filter('probe_x')); remove_all_filters('probe_x'); $say('has x inside y after', has_filter('probe_x')); return $v; });
apply_filters('probe_y', 'v');
$say('x after y', apply_filters('probe_x', 'v'));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
