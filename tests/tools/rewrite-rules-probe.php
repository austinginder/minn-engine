<?php
/**
 * The rule list WP_Rewrite::rewrite_rules assembles, as the reference
 * assembles it (probe rewrite-rules): every rule in order for the site as
 * it stands, its sections' filters ({section}_rewrite_rules, a
 * permastruct's own, the deprecated tag_rewrite_rules), the
 * generate_rewrite_rules action and rewrite_rules_array, each heard with
 * how many rules it was handed and its first, and where a rule a plugin
 * adds through each one lands; the rules with a static front page (and
 * page_on_front left set without one), with plain permalinks, and
 * wp_rewrite_rules / the stored option untouched. Read only. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_rewrite;
$extraTop = $wp_rewrite->extra_rules_top;
$rules = $wp_rewrite->rewrite_rules();
$say('how many', count($rules));
$say('the rules', $rules);
$say('the extra permastructs', array_map(static fn ($args) => [$args['struct'], $args['ep_mask'], $args['paged'], $args['feed'], $args['forcomments'], $args['walk_dirs'], $args['endpoints']], $wp_rewrite->extra_permastructs));

$heard = [];
$sections = ['post_rewrite_rules', 'date_rewrite_rules', 'root_rewrite_rules', 'comments_rewrite_rules', 'search_rewrite_rules', 'author_rewrite_rules', 'page_rewrite_rules', 'category_rewrite_rules', 'post_tag_rewrite_rules', 'tag_rewrite_rules', 'post_format_rewrite_rules'];
$callbacks = [];
foreach ($sections as $hook) {
    $callbacks[$hook] = static function ($rules) use (&$heard, $hook) {
        $heard[] = [$hook, count((array) $rules), array_key_first((array) $rules)];
        return ["zz-{$hook}/?$" => "index.php?zz={$hook}"] + (array) $rules;
    };
    add_filter($hook, $callbacks[$hook]);
}
$generate = static function ($rewrite) use (&$heard) {
    $heard[] = ['generate_rewrite_rules', get_class($rewrite), count((array) $rewrite->rules), array_key_first((array) $rewrite->rules)];
    $rewrite->rules = ['zz-generate/?$' => 'index.php?zz=generate'] + (array) $rewrite->rules;
};
$array = static function ($rules) use (&$heard) {
    $heard[] = ['rewrite_rules_array', count((array) $rules), array_key_first((array) $rules)];
    return (array) $rules + ['zz-array/?$' => 'index.php?zz=array'];
};
add_action('generate_rewrite_rules', $generate);
add_filter('rewrite_rules_array', $array);
$filtered = $wp_rewrite->rewrite_rules();
$positions = [];
foreach (array_keys($filtered) as $n => $regex) {
    if (str_starts_with((string) $regex, 'zz-')) {
        $positions[$regex] = [$n, array_keys($filtered)[$n - 1] ?? null, array_keys($filtered)[$n + 1] ?? null];
    }
}
$say('with a plugin on every filter', [count($filtered), $heard, $positions]);
foreach ($callbacks as $hook => $callback) {
    remove_filter($hook, $callback);
}
remove_action('generate_rewrite_rules', $generate);
remove_filter('rewrite_rules_array', $array);

$front = static fn () => 'page';
$frontPage = static fn () => 2;
add_filter('pre_option_show_on_front', $front);
add_filter('pre_option_page_on_front', $frontPage);
$say('a page in front', array_values(array_diff_key($wp_rewrite->rewrite_rules(), $rules)));
remove_filter('pre_option_show_on_front', $front);
$say('page_on_front left set', array_values(array_diff_key($wp_rewrite->rewrite_rules(), $rules)));
remove_filter('pre_option_page_on_front', $frontPage);

foreach (['/%year%/%monthnum%/%postname%/', '/%category%/%postname%/', '/%postname%.html', '/archives/%post_id%'] as $structure) {
    $pinned = static fn () => $structure;
    add_filter('pre_option_permalink_structure', $pinned);
    $other = new WP_Rewrite();
    $other->init();
    remove_filter('pre_option_permalink_structure', $pinned);
    $other->extra_permastructs = $wp_rewrite->extra_permastructs;
    $other->extra_rules_top = $extraTop;
    $other->endpoints = $wp_rewrite->endpoints;
    $say("the sections under {$structure}", [$other->use_verbose_page_rules, $other->use_verbose_rules, array_keys($other->rewrite_rules())]);
}

$plain = new WP_Rewrite();
$empty = static fn () => '';
add_filter('pre_option_permalink_structure', $empty);
$plain->init();
$say('plain permalinks', [$plain->using_permalinks(), $plain->rewrite_rules()]);
remove_filter('pre_option_permalink_structure', $empty);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
