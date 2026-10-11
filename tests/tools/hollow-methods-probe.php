<?php
/**
 * Methods the facade answered with an empty body or a fixed value (probe
 * hollow-methods), checked against what the reference does: the deprecated
 * meta lazy-loaders and widget cache flush, a widget class that never wrote
 * its own widget(), the embed object's hooks, a theme's allowed check, the
 * post-format search handler's links, script module translations, text
 * split at whitespace and null bytes, and whether core would update itself
 * to an offered version. Deprecation and doing-it-wrong notices are
 * silenced and recorded; a die is caught.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('doing_it_wrong_trigger_error', '__return_false');
$notices = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$notices) {
    $notices[] = ['deprecated', $function, $replacement, $version];
}, 10, 3);
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$notices) {
    $notices[] = ['wrong', $function, $version];
}, 10, 3);
$heard = static function (Closure $run) use (&$notices) {
    $notices = [];
    try {
        $result = $run();
    } catch (Throwable $e) {
        $result = ['threw' => get_class($e)];
    }
    return [$result, $notices];
};
$dies = static function (Closure $run) {
    $handler = static fn () => static function ($message, $title = '', $args = []) {
        throw new RuntimeException((string) json_encode([is_string($message) ? $message : get_class($message), $title, is_array($args) ? ($args['response'] ?? null) : $args]));
    };
    add_filter('wp_die_handler', $handler, PHP_INT_MAX);
    try {
        $out = ['returned' => $run()];
    } catch (RuntimeException $e) {
        $out = ['died' => json_decode($e->getMessage(), true)];
    }
    remove_filter('wp_die_handler', $handler, PHP_INT_MAX);
    return $out;
};

$query = new WP_Query();
$say('deprecated', [
    'comment meta' => $heard(static fn () => $query->lazyload_comment_meta('zz-check', 5)),
    'term meta' => $heard(static fn () => $query->lazyload_term_meta(null, 7)),
    'the recent comments cache' => $heard(static fn () => (new WP_Widget_Recent_Comments())->flush_widget_cache()),
]);

$bare = new class('zz_hollow', 'ZZ hollow') extends WP_Widget {
};
// Its widget() ends the process on the reference (a plain die naming the method), so only the rest is compared.
$say('a widget class without its own widget()', [
    'a preview' => $bare->is_preview(),
    'updated' => $bare->update(['a' => 1], ['b' => 2]),
]);

// The callbacks a new embed object adds, by hook and priority.
$owned = static function (object $object): array {
    $out = [];
    foreach ($GLOBALS['wp_filter'] as $hook => $filter) {
        foreach ($filter->callbacks ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && $callback['function'][0] === $object) {
                    $out[] = [$hook, $priority, $callback['function'][1]];
                }
            }
        }
    }
    return $out;
};
$embed = new WP_Embed();
// Sorted: the registry's own order is when each hook was first used, which is the stack's business.
$hooks = $owned($embed);
sort($hooks);
$say('a new embed object\'s hooks', $hooks);

$theme = wp_get_theme();
$say('the theme allowed', [
    'both' => $theme->is_allowed(),
    'site' => $theme->is_allowed('site'),
    'network' => $theme->is_allowed('network'),
    'another theme' => wp_get_theme('twentytwentyfour')->is_allowed(),
    'a missing theme' => wp_get_theme('zz-none')->is_allowed(),
]);

$say('post format links', (new WP_REST_Post_Format_Search_Handler())->prepare_item_links('aside'));

$modules = wp_script_modules();
wp_register_script_module('zz/hollow', 'https://example.com/zz-hollow.js');
$say('script module translations', [
    'set for a module' => $modules->set_translations('zz/hollow', 'zz-hollow', '/zz/languages'),
    'set for one not registered' => $modules->set_translations('zz/none', 'zz-hollow'),
]);

// Text split where the reference splits it: whitespace that leads a text node, null bytes.
$split = static function (string $html, int $steps) {
    $p = new WP_HTML_Tag_Processor($html);
    for ($i = 0; $i < $steps; $i++) {
        $p->next_token();
    }
    $before = [$p->get_token_type(), $p->get_modifiable_text()];
    $answer = $p->subdivide_text_appropriately();
    $after = [$p->get_token_type(), $p->get_modifiable_text()];
    $p->next_token();
    return ['before' => $before, 'answer' => $answer, 'after' => $after, 'next' => [$p->get_token_type(), $p->get_modifiable_text()]];
};
$say('text subdivided', [
    'leading whitespace' => $split("<p>\n \tWords here</p>", 2),
    'all whitespace' => $split('<p>   </p>', 2),
    'no whitespace' => $split('<p>Words</p>', 2),
    'a null byte first' => $split("<p>\x00\x00Words</p>", 2),
    'a null byte later' => $split("<p>Wo\x00rds</p>", 2),
    'not on text' => $split('<p>x</p>', 1),
    'nothing yet' => $split('<p>x</p>', 0),
    'a comment' => $split('<p><!-- note --></p>', 2),
]);

if (!class_exists('Core_Upgrader')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}
$updates = static function () {
    $out = [];
    foreach (['7.1.3', '7.1.4', '7.1.10', '7.2', '7.2.1', '8.0', '7.2-beta1', '7.1.4-RC1', '7.0', '7.1.2'] as $offered) {
        $out[$offered] = Core_Upgrader::should_update_to_version($offered);
    }
    return $out;
};
// Each switch flipped on its own: a filter refusing a kind of update, then an option set aside (both put back).
$with = static function (Closure $change, Closure $undo) use ($updates) {
    $change();
    $out = $updates();
    $undo();
    return $out;
};
$cases = ['as it is' => $updates()];
foreach (['major', 'minor', 'dev'] as $kind) {
    $cases["{$kind} refused by its filter"] = $with(static fn () => add_filter("allow_{$kind}_auto_core_updates", '__return_false'), static fn () => remove_filter("allow_{$kind}_auto_core_updates", '__return_false'));
    $cases["{$kind} allowed by its filter"] = $with(static fn () => add_filter("allow_{$kind}_auto_core_updates", '__return_true'), static fn () => remove_filter("allow_{$kind}_auto_core_updates", '__return_true'));
}
// The options as they stood, autoload included, put back after each case and at the end.
global $wpdb;
$options = [];
foreach (['auto_update_core_major', 'auto_update_core_minor', 'auto_update_core_dev'] as $name) {
    $options[$name] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
}
$restore = static function () use ($options): void {
    global $wpdb;
    // Through the options API (so each stack's own cache sees it), then the autoload as it was.
    foreach ($options as $name => $row) {
        if (!is_array($row)) {
            delete_option($name);
            continue;
        }
        update_option($name, $row['option_value']);
        $wpdb->update($wpdb->options, ['autoload' => $row['autoload']], ['option_name' => $name]);
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
};
register_shutdown_function($restore);
foreach (array_keys($options) as $name) {
    $cases["{$name} unset"] = $with(static fn () => update_option($name, 'unset'), $restore);
}
$cases['the major option unset, its filter allowing'] = $with(static function () {
    update_option('auto_update_core_major', 'unset');
    add_filter('allow_major_auto_core_updates', '__return_true');
}, static function () use ($restore) {
    remove_filter('allow_major_auto_core_updates', '__return_true');
    $restore();
});
foreach (array_keys($options) as $name) {
    $cases["{$name} missing"] = $with(static fn () => delete_option($name), $restore);
}
// The constant last: once defined it holds for the rest of the run.
if (!defined('WP_AUTO_UPDATE_CORE')) {
    define('WP_AUTO_UPDATE_CORE', 'minor');
    $cases['WP_AUTO_UPDATE_CORE minor'] = $updates();
}
$say('core updating itself', $cases);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
