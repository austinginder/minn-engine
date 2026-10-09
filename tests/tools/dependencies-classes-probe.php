<?php
/**
 * The dependency classes (probe dependencies-classes): WP_Dependencies,
 * WP_Scripts and WP_Styles on fresh instances, so the page's own queues are
 * untouched. Dependencies resolved (all_deps, recurse_deps, groups),
 * items printed (do_items, do_item, head and footer passes, inline code,
 * localized data, loading strategies), the URL a style prints, the
 * default directories, etags, warnings for missing dependencies, and
 * reset. Every handle starts with zz-. Same protocol as api-probe.php; it
 * writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
$attribute_order = [];
add_filter('wp_script_attributes', static function ($attributes) use (&$attribute_order) {
    if (str_starts_with((string) ($attributes['id'] ?? ''), 'zz-q') || str_starts_with((string) ($attributes['id'] ?? ''), 'zz-r1')) {
        $attribute_order[] = array_keys($attributes);
    }
    return $attributes;
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => is_string($value) ? str_replace([$host, (string) $GLOBALS['wp_version']], ['{host}', '{wp}'], $value) : $value;
$printed = static function (Closure $call) use ($mask) {
    ob_start();
    $out = $call();
    return [$mask(ob_get_clean()), is_array($out) ? array_values($out) : $mask($out)];
};
$inside = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
$ours = static fn (array $handles) => array_values(array_filter($handles, static fn ($handle) => str_starts_with((string) $handle, 'zz-')));

$scripts = new WP_Scripts();
$say('a fresh scripts registry', [
    get_parent_class($scripts), $scripts->default_dirs, $mask($scripts->base_url), $scripts->default_version === $GLOBALS['wp_version'], $scripts->queue, $scripts->done, $scripts->to_do,
    $scripts !== wp_scripts(),
]);
$scripts->add('zz-a', 'https://cdn.example/a.js', [], '1.0');
$scripts->add('zz-b', '/wp-content/zz/b.js', ['zz-a'], false);
$scripts->add('zz-c', 'https://cdn.example/c.js', ['zz-b', 'zz-missing'], null);
$scripts->add('zz-alias', false, ['zz-a', 'zz-b']);
$scripts->add('zz-f', 'https://cdn.example/f.js', ['zz-a'], '2');
$scripts->add_data('zz-f', 'group', 1);
$scripts->add('zz-g', 'https://cdn.example/g.js', ['zz-f'], '3');
$scripts->add('zz-h', 'https://cdn.example/h.js', ['zz-c'], '4');
$scripts->add('zz-empty', 'https://cdn.example/empty.js', [], '');
$scripts->add('zz-alias2', false, ['zz-a']);
$scripts->add_inline_script('zz-alias2', 'window.zzAlias = 1;');

$say('dependencies resolved', [
    $scripts->all_deps(['zz-b']), $ours($scripts->to_do), $scripts->groups,
    (static function () use ($scripts, $ours) {
        $scripts->to_do = [];
        $scripts->groups = [];
        return [$scripts->all_deps(['zz-c']), $ours($scripts->to_do), $scripts->groups];
    })(),
    (static function () use ($scripts, $ours) {
        $scripts->to_do = [];
        $scripts->groups = [];
        return [$scripts->all_deps(['zz-g', 'zz-alias']), $ours($scripts->to_do), $scripts->groups];
    })(),
    (static function () use ($scripts, $ours) {
        $scripts->to_do = [];
        $scripts->groups = [];
        return [$scripts->all_deps('zz-b'), $ours($scripts->to_do), $scripts->all_deps(['zz-nothing']), $ours($scripts->to_do), $scripts->groups];
    })(),
    (static function () use ($scripts, $ours) {
        $scripts->to_do = [];
        $scripts->groups = [];
        return [$scripts->all_deps(['zz-h']), $ours($scripts->to_do), $scripts->groups, $scripts->all_deps(['zz-c'], true), $scripts->all_deps(['zz-a?ver=5', 'zz-b']), $ours($scripts->to_do), $scripts->args];
    })(),
    (static function () use ($scripts, $ours) {
        $scripts->to_do = ['zz-b'];
        $scripts->groups = ['zz-b' => 1];
        return [$scripts->all_deps(['zz-b'], false, 0), $ours($scripts->to_do), $scripts->groups];
    })(),
    (static function () use ($scripts, $ours) {
        $scripts->to_do = [];
        $scripts->groups = [];
        return [$scripts->all_deps(['zz-g'], false, 0), $ours($scripts->to_do), $scripts->groups];
    })(),
    $inside($scripts, 'recurse_deps', ['zz-c'], 'zz-a'), $inside($scripts, 'recurse_deps', ['zz-c'], 'zz-g'), $inside($scripts, 'recurse_deps', ['zz-alias'], 'zz-a'),
    $inside($scripts, 'get_dependency_warning_message', 'zz-c', ['zz-missing', 'zz-other']),
]);
$scripts->to_do = [];
$scripts->groups = [];

$say('groups', [
    $scripts->set_group('zz-a', false, 0), $scripts->groups,
    $scripts->set_group('zz-a', false, 1), $scripts->groups,
    $scripts->set_group('zz-f', true, false), $scripts->groups,
    $scripts->set_group('zz-g', false, false), $scripts->groups,
]);
$scripts->groups = [];

$scripts->localize('zz-a', 'zzData', ['one' => 1, 'two' => 'second']);
$scripts->add_inline_script('zz-b', 'window.zzBefore = 1;', 'before');
$scripts->add_inline_script('zz-b', 'window.zzAfter = 2;');
$scripts->add_inline_script('zz-b', 'window.zzAfter2 = 3;');
$say('extra and inline code', [
    $printed(static fn () => $scripts->print_extra_script('zz-a')),
    $printed(static fn () => $scripts->print_extra_script('zz-a', false)),
    $printed(static fn () => $scripts->print_extra_script('zz-b')),
    $printed(static fn () => $scripts->print_scripts_l10n('zz-a', false)),
    $mask($scripts->get_inline_script_tag('zz-b', 'before')),
    $mask($scripts->get_inline_script_tag('zz-b')),
    $mask($scripts->get_inline_script_tag('zz-a')),
    $scripts->get_inline_script_data('zz-b'),
    $printed(static fn () => $scripts->print_inline_script('zz-b', 'after', false)),
    $printed(static fn () => $scripts->print_inline_script('zz-b', 'before')),
]);

$say('items printed', [
    $printed(static fn () => $scripts->do_items(['zz-b'])), $ours($scripts->done), $ours($scripts->to_do),
    $printed(static fn () => $scripts->do_items(['zz-b'])),
    $printed(static fn () => $scripts->do_item('zz-never')),
    $printed(static fn () => $scripts->do_item('zz-alias')),
    $printed(static fn () => $scripts->do_items(['zz-c'])),
    $printed(static fn () => $scripts->print_scripts(['zz-alias'])),
    $printed(static fn () => $scripts->do_items(['zz-empty', 'zz-alias2'])),
    $printed(static fn () => $scripts->do_items(['zz-h'])),
]);

$scripts->done = [];
$scripts->enqueue(['zz-g', 'zz-b']);
$say('head and footer', [
    $printed(static fn () => $ours($scripts->do_head_items())), $ours($scripts->in_footer), $ours($scripts->done),
    $printed(static fn () => $ours($scripts->do_footer_items())), $ours($scripts->in_footer), $ours($scripts->done),
    (static function () use ($scripts) {
        $scripts->reset();
        return [$scripts->do_concat, $scripts->concat, $scripts->concat_version, $scripts->print_html, $scripts->print_code, $scripts->ext_handles, $scripts->ext_version, $scripts->in_footer];
    })(),
]);

$footer = new WP_Scripts();
$footer->add('zz-a', 'https://cdn.example/a.js', [], '1');
$footer->add('zz-f', 'https://cdn.example/f.js', ['zz-a'], '2');
$footer->add_data('zz-f', 'group', 1);
$footer->add('zz-k', 'https://cdn.example/k.js', [], '3');
$footer->add_data('zz-k', 'group', 1);
$footer->enqueue(['zz-f', 'zz-k']);
$say('a footer pass', [
    $footer->all_deps(['zz-f'], false, 0), $ours($footer->to_do), $footer->groups,
    (static function () use ($footer) {
        $footer->to_do = [];
        $footer->groups = [];
        return null;
    })(),
    $printed(static fn () => $ours($footer->do_head_items())), $ours($footer->in_footer), $footer->groups, $ours($footer->to_do),
    (static function () use ($footer) {
        $copy = clone $footer;
        $copy->reset();
        return $copy->in_footer;
    })(),
    $printed(static fn () => $ours($footer->do_footer_items())), $ours($footer->in_footer), $ours($footer->done),
    $printed(static fn () => $footer->do_item('zz-k', 0)),
]);

$say('default directories and etags', [
    $scripts->in_default_dir('/wp-includes/js/jquery/jquery.js'), $scripts->in_default_dir('/wp-content/plugins/x.js'), $scripts->in_default_dir('https://cdn.example/wp-includes/js/x.js'),
    (static function () use ($scripts) {
        $scripts->default_dirs = null;
        $out = $scripts->in_default_dir('/wp-includes/js/x.js');
        $scripts->default_dirs = ['/wp-admin/js/', '/wp-includes/js/'];
        return $out;
    })(),
    $mask($scripts->get_etag(['zz-a', 'zz-b', 'zz-c', 'zz-never'])),
    $mask($scripts->get_etag([])),
]);

$strategy = new WP_Scripts();
$strategy->add('zz-s1', 'https://cdn.example/s1.js', [], '1');
$strategy->add_data('zz-s1', 'strategy', 'defer');
$strategy->add('zz-s2', 'https://cdn.example/s2.js', ['zz-s1'], '1');
$strategy->add('zz-s3', 'https://cdn.example/s3.js', [], '1');
$strategy->add_data('zz-s3', 'strategy', 'async');
$strategy->add('zz-s4', 'https://cdn.example/s4.js', ['zz-s3'], '1');
$strategy->add_data('zz-s4', 'strategy', 'defer');
$strategy->add('zz-s5', 'https://cdn.example/s5.js', [], '1');
$strategy->add_data('zz-s5', 'strategy', 'defer');
$strategy->add_inline_script('zz-s5', 'window.zzS5 = 1;', 'before');
$strategy->add('zz-p1', 'https://cdn.example/p1.js', [], '1');
$strategy->add_data('zz-p1', 'fetchpriority', 'low');
$strategy->add('zz-p2', 'https://cdn.example/p2.js', ['zz-p1'], '1');
$strategy->add_data('zz-p2', 'fetchpriority', 'high');
$strategy->add('zz-p3', 'https://cdn.example/p3.js', [], '1');
$strategy->add_data('zz-p3', 'fetchpriority', 'urgent');
$say('loading strategies', [
    $strategy->add_data('zz-s1', 'strategy', 'later'), $strategy->get_data('zz-s1', 'strategy'),
    $printed(static fn () => $strategy->do_items(['zz-s1'])),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        return $printed(static fn () => $strategy->do_items(['zz-s2']));
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        return $printed(static fn () => $strategy->do_items(['zz-s4']));
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        return $printed(static fn () => $strategy->do_items(['zz-s5']));
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        return $printed(static fn () => $strategy->do_items(['zz-p2', 'zz-p3']));
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        $strategy->enqueue(['zz-s2', 'zz-p2']);
        $out = $printed(static fn () => $strategy->do_items());
        $strategy->dequeue(['zz-s2', 'zz-p2']);
        return $out;
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        $strategy->add('zz-s6', 'https://cdn.example/s6.js', [], '1');
        $strategy->add_data('zz-s6', 'strategy', 'async');
        $strategy->add('zz-s7', 'https://cdn.example/s7.js', ['zz-s6'], '1');
        $strategy->add_data('zz-s7', 'strategy', 'defer');
        $strategy->add('zz-s8', 'https://cdn.example/s8.js', [], '1');
        $strategy->add_data('zz-s8', 'strategy', 'defer');
        $strategy->add_inline_script('zz-s8', 'window.zzS8 = 1;');
        $strategy->add('zz-s9', 'https://cdn.example/s9.js', [], '1');
        $strategy->add_data('zz-s9', 'strategy', 'defer');
        $strategy->add_data('zz-s9', 'group', 1);
        $strategy->enqueue(['zz-s7', 'zz-s8', 'zz-s9']);
        $out = [$printed(static fn () => $strategy->do_head_items()), $printed(static fn () => $strategy->do_footer_items())];
        $strategy->dequeue(['zz-s7', 'zz-s8', 'zz-s9']);
        return $out;
    })(),
    (static function () use ($strategy, $printed) {
        $strategy->done = [];
        $strategy->in_footer = [];
        foreach (['zz-q1' => [[], 'defer', 'low'], 'zz-q2' => [['zz-q1'], null, null], 'zz-q3' => [['zz-q2'], 'defer', 'high'], 'zz-r1' => [[], 'async', 'low'], 'zz-r2' => [['zz-r1'], 'async', null], 'zz-t1' => [[], 'defer', null], 'zz-t2' => [['zz-t1'], 'defer', 'auto'], 'zz-t3' => [['zz-t1'], 'async', null]] as $handle => [$deps, $how, $priority]) {
            $strategy->add($handle, "https://cdn.example/{$handle}.js", $deps, '1');
            if ($how !== null) {
                $strategy->add_data($handle, 'strategy', $how);
            }
            if ($priority !== null) {
                $strategy->add_data($handle, 'fetchpriority', $priority);
            }
        }
        $strategy->enqueue(['zz-q3', 'zz-r2', 'zz-t2', 'zz-t3']);
        $out = $printed(static fn () => $strategy->do_items());
        $strategy->dequeue(['zz-q3', 'zz-r2', 'zz-t2', 'zz-t3']);
        return $out;
    })(),
]);

$held = static function (array $defs, array $queue) use ($printed) {
    $registry = new WP_Scripts();
    foreach ($defs as $handle => [$deps, $how, $after]) {
        $registry->add($handle, "https://cdn.example/{$handle}.js", $deps, '1');
        if ($how !== null) {
            $registry->add_data($handle, 'strategy', $how);
        }
        if ($after) {
            $registry->add_inline_script($handle, "/*{$handle}*/");
        }
    }
    $registry->enqueue($queue);
    return [$printed(static fn () => $registry->do_head_items())[0], $registry->in_footer, $printed(static fn () => $registry->do_footer_items())[0]];
};
$say('held for the footer', [
    $held(['zz-a' => [[], 'defer', true]], ['zz-a']),
    $held(['zz-a' => [[], 'async', true]], ['zz-a']),
    $held(['zz-a' => [[], null, true]], ['zz-a']),
    $held(['zz-a' => [[], 'defer', true], 'zz-b' => [['zz-a'], null, false]], ['zz-b']),
    $held(['zz-a' => [[], 'defer', true], 'zz-b' => [['zz-a'], 'defer', false]], ['zz-b']),
    $held(['zz-a' => [[], 'defer', true], 'zz-b' => [['zz-a'], 'defer', false]], ['zz-a']),
]);

$styles = new WP_Styles();
$say('a fresh styles registry', [
    get_parent_class($styles), $styles->default_dirs, $styles->text_direction, $mask($styles->base_url), $styles !== wp_styles(),
]);
$styles->add('zz-st1', 'https://cdn.example/one.css', [], '1', 'screen');
$styles->add('zz-st2', '/wp-content/zz/two.css', ['zz-st1'], false);
$styles->add('zz-st3', false, ['zz-st1']);
$styles->add('zz-st4', 'https://cdn.example/four.css', ['zz-st-missing'], null, 'print');
$styles->add_inline_style('zz-st1', '.zz { color: red; }');
$styles->add_inline_style('zz-st1', '.zz2 { color: blue; }');
$styles->add_inline_style('zz-st3', '.zz3 { margin: 0; }');
$styles->add_data('zz-st2', 'title', 'Zz Title');
$styles->add_data('zz-st2', 'alt', true);
$say('styles printed', [
    $printed(static fn () => $styles->print_inline_style('zz-st1', false)),
    $printed(static fn () => $styles->print_inline_style('zz-st1')),
    $printed(static fn () => $styles->print_inline_style('zz-st2')),
    $mask($styles->_css_href('https://cdn.example/x.css', '9', 'zz-x')),
    $mask($styles->_css_href('/wp-content/x.css', false, 'zz-x')),
    $mask($styles->_css_href('/wp-content/x.css', null, 'zz-x')),
    $mask($styles->_css_href('//cdn.example/x.css', '', 'zz-x')),
    $styles->all_deps(['zz-st2']), $ours($styles->to_do),
    $printed(static fn () => $styles->do_items(['zz-st2'])),
    $printed(static fn () => $styles->do_items(['zz-st3'])),
    $printed(static fn () => $styles->do_items(['zz-st4'])),
    $printed(static fn () => $styles->do_item('zz-never')),
    $styles->in_default_dir('/wp-includes/css/dashicons.css'), $styles->in_default_dir('/wp-content/x.css'),
    (static function () use ($styles, $printed, $ours) {
        $styles->done = [];
        $styles->add('zz-rtl', 'https://cdn.example/rtl.css', [], '1');
        $styles->add_data('zz-rtl', 'rtl', 'replace');
        $styles->text_direction = 'rtl';
        $out = $printed(static fn () => $styles->do_items(['zz-rtl']));
        $styles->text_direction = 'ltr';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $styles->done = [];
        $styles->add('zz-rtl2', 'https://cdn.example/two.css', [], '1');
        $styles->add_data('zz-rtl2', 'rtl', 'https://cdn.example/other-rtl.css');
        $styles->text_direction = 'rtl';
        $out = $printed(static fn () => $styles->do_items(['zz-rtl2']));
        $styles->text_direction = 'ltr';
        return $out;
    })(),
    (static function () use ($styles) {
        $styles->reset();
        return [$styles->do_concat, $styles->concat, $styles->concat_version, $styles->print_html, $styles->print_code];
    })(),
    $printed(static fn () => $styles->do_footer_items()),
]);

$filtered = [];
add_filter('print_scripts_array', static function ($to_do) use (&$filtered) {
    $filtered[] = ['scripts', array_values(array_filter($to_do, static fn ($h) => str_starts_with((string) $h, 'zz-')))];
    return $to_do;
});
add_filter('print_styles_array', static function ($to_do) use (&$filtered) {
    $filtered[] = ['styles', array_values(array_filter($to_do, static fn ($h) => str_starts_with((string) $h, 'zz-')))];
    return $to_do;
});
$scripts->to_do = [];
$styles->to_do = [];
$scripts->all_deps(['zz-b']);
$styles->all_deps(['zz-st2']);
$say('print array filters', $filtered);

$strategy->add('zz-v1', 'https://cdn.example/v1.js', [], '1');
$say('strategy and priority values', [
    $strategy->add_data('zz-v1', 'strategy', ''), $strategy->get_data('zz-v1', 'strategy'),
    $strategy->add_data('zz-v1', 'strategy', 'async'), $strategy->add_data('zz-v1', 'strategy', false),
    $strategy->add_data('zz-v1', 'fetchpriority', 'auto'), $strategy->add_data('zz-v1', 'fetchpriority', ''),
    $strategy->add_data('zz-v1', 'fetchpriority', 'HIGH'), $strategy->get_data('zz-v1', 'fetchpriority'),
    wp_register_script('zz-v2', 'https://cdn.example/v2.js', [], '1', ['strategy' => 'eventually', 'fetchpriority' => 'high', 'in_footer' => true]),
    wp_scripts()->get_data('zz-v2', 'strategy'), wp_scripts()->get_data('zz-v2', 'fetchpriority'), wp_scripts()->get_data('zz-v2', 'group'),
    wp_register_script('zz-v3', 'https://cdn.example/v3.js', [], '1', ['fetchpriority' => 'urgent']),
    wp_scripts()->get_data('zz-v3', 'fetchpriority'),
]);
wp_deregister_script('zz-v2');
wp_deregister_script('zz-v3');

$base = new WP_Dependencies();
$say('the base class', [
    $inside($base, 'get_dependency_warning_message', 'zz-x', ['zz-y', 'zz-z']), $base->do_item('zz-x'), $base->all_deps(['zz-x']), $base->groups, $base->set_group('zz-x', false, 2), $base->groups,
]);

wp_register_script('zz-head', 'https://cdn.example/head.js', ['zz-dep'], '1');
wp_register_script('zz-dep', 'https://cdn.example/dep.js', [], '1', true);
wp_register_script('zz-foot', 'https://cdn.example/foot.js', [], '1', true);
wp_enqueue_script('zz-head');
wp_enqueue_script('zz-foot');
wp_register_style('zz-gs', 'https://cdn.example/gs.css', [], '1');
wp_add_inline_style('zz-gs', '.zzgs { color: green; }');
$say('the global printers', [
    $printed(static fn () => $ours((array) wp_print_scripts())), $ours(wp_scripts()->in_footer),
    $printed(static fn () => $ours((array) wp_print_scripts('zz-foot'))),
    $printed(static fn () => $ours((array) wp_print_styles(['zz-gs']))),
    $printed(static fn () => $ours((array) wp_print_styles(['zz-gs']))),
]);
foreach (['zz-head', 'zz-dep', 'zz-foot'] as $handle) {
    wp_dequeue_script($handle);
    wp_deregister_script($handle);
}
wp_deregister_style('zz-gs');

$say('attributes in the order the filter hears them', $attribute_order);
$say('notices', [$wrong, $deprecated]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
