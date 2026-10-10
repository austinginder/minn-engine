<?php
/**
 * The rewrite family (probe rewrite-family): a feed added, a permastruct
 * added and removed, a rewrite tag removed, taxonomy bases cleaned, and the
 * REST API's own rewrites, query var and default filters set up again (each
 * first taken away, so what the call adds is what comes back). All of it in
 * the run's memory: no rules are flushed or stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_rewrite, $wp;

function zz_rf_feed()
{
    echo 'zz feed';
}
$added = add_feed('zzfeed', 'zz_rf_feed');
$say('a feed added', [$added, in_array('zzfeed', $wp_rewrite->feeds, true), has_action('do_feed_zzfeed', 'zz_rf_feed'), add_feed('zzfeed', 'zz_rf_feed'), count(array_keys($wp_rewrite->feeds, 'zzfeed', true))]);

add_permastruct('zz_perma', 'zz/%zz_perma_tag%');
add_permastruct('zz_perma_args', '/zz-args/%zz_perma_tag%/', ['with_front' => false, 'ep_mask' => EP_PERMALINK, 'paged' => false, 'feed' => false]);
add_permastruct('zz_perma_legacy', 'zz-legacy/%zz_perma_tag%', false, EP_NONE);
$perma = static function (string $name) use ($wp_rewrite) {
    $struct = $wp_rewrite->extra_permastructs[$name] ?? null;
    if (is_array($struct)) {
        ksort($struct);
    }
    return $struct;
};
$permastructs = [$perma('zz_perma'), $perma('zz_perma_args'), $perma('zz_perma_legacy')];
remove_permastruct('zz_perma');
remove_permastruct('zz_never_added');
$permastructs[] = [$perma('zz_perma'), $perma('zz_perma_args') !== null];
remove_permastruct('zz_perma_args');
remove_permastruct('zz_perma_legacy');
$say('permastructs added and removed', $permastructs);

add_rewrite_tag('%zz_rf_tag%', '([^/]+)', 'zz_rf_tag=');
add_rewrite_tag('%zz_rf_plain%', '(\d+)');
$tags = static fn () => [in_array('%zz_rf_tag%', $wp_rewrite->rewritecode, true), in_array('%zz_rf_plain%', $wp_rewrite->rewritecode, true), in_array('zz_rf_tag', $wp->public_query_vars, true), in_array('zz_rf_plain', $wp->public_query_vars, true)];
$rewriteTags = [$tags()];
remove_rewrite_tag('%zz_rf_tag%');
$rewriteTags[] = $tags();
remove_rewrite_tag('%zz_rf_plain%');
remove_rewrite_tag('%zz_never%');
$rewriteTags[] = $tags();
$say('rewrite tags removed', $rewriteTags);

$say('taxonomy bases cleaned', array_map(static fn ($base) => _wp_filter_taxonomy_base($base), ['/category/', 'category', '/topics/sub/', '/index.php/topics/', 'index.php/topics', '', '/', 'zz base']));

$restRules = ['^wp-json/?$', '^wp-json/(.*)?', '^index.php/wp-json/?$', '^index.php/wp-json/(.*)?'];
foreach ($restRules as $rule) {
    unset($wp_rewrite->extra_rules_top[$rule]);
}
rest_api_register_rewrites();
$say('the REST rewrites', array_map(static fn ($rule) => $wp_rewrite->extra_rules_top[$rule] ?? null, $restRules));

$wp->public_query_vars = array_values(array_diff($wp->public_query_vars, ['rest_route']));
foreach ($restRules as $rule) {
    unset($wp_rewrite->extra_rules_top[$rule]);
}
$fired = did_action('rest_api_init');
rest_api_init();
$say('rest_api_init', [in_array('rest_route', $wp->public_query_vars, true), $wp_rewrite->extra_rules_top['^wp-json/?$'] ?? null, did_action('rest_api_init') - $fired]);

// Every string callback named rest_* is taken off its hook; what rest_api_default_filters puts back is what it adds.
global $wp_filter;
foreach ($wp_filter as $hook => $callbacks) {
    foreach ($callbacks->callbacks as $priority => $list) {
        foreach ($list as $entry) {
            if (is_string($entry['function']) && str_starts_with($entry['function'], 'rest_')) {
                remove_filter((string) $hook, $entry['function'], $priority);
            }
        }
    }
}
rest_api_default_filters();
$back = [];
foreach ($wp_filter as $hook => $callbacks) {
    foreach ($callbacks->callbacks as $priority => $list) {
        foreach ($list as $entry) {
            if (is_string($entry['function']) && str_starts_with($entry['function'], 'rest_')) {
                $back[] = "{$hook} {$entry['function']} {$priority} {$entry['accepted_args']}";
            }
        }
    }
}
sort($back);
$say('the REST default filters', $back);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
