<?php
/**
 * Speculative loading (probe speculative-loading): the configuration and its
 * filter (auto settled, values checked, signed-in visitors and plain
 * permalinks left out), the rules object
 * (modes, ids, eagerness and sources checked, rules kept per mode,
 * serialized), the URL pattern prefixer, the rules the site builds with
 * the paths it leaves out and the action that adds more, and the script
 * printed. Same protocol as api-probe.php; it writes nothing.
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
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => json_decode(str_replace($host, '{host}', (string) wp_json_encode($value)), true);
$with = static function (string $hook, $callback, Closure $call, int $args = 1) {
    add_filter($hook, $callback, 10, $args);
    try {
        return $call();
    } finally {
        remove_filter($hook, $callback, 10);
    }
};
wp_set_current_user(0);
$plain = static fn () => '';

$say('configuration', [
    wp_get_speculation_rules_default_configuration(),
    wp_get_speculation_rules_configuration(),
    $with('wp_speculation_rules_configuration', static fn () => ['mode' => 'prerender', 'eagerness' => 'eager'], static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => ['mode' => 'auto', 'eagerness' => 'auto'], static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => ['mode' => 'nonsense', 'eagerness' => 'nonsense'], static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => ['mode' => 'prefetch'], static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => null, static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => 'nope', static fn () => wp_get_speculation_rules_configuration()),
    $with('wp_speculation_rules_configuration', static fn () => ['mode' => 'prerender', 'eagerness' => 'immediate'], static fn () => wp_get_speculation_rules_configuration()),
    $with('pre_option_permalink_structure', $plain, static fn () => wp_get_speculation_rules_configuration()),
    (static function () {
        wp_set_current_user(1);
        $out = wp_get_speculation_rules_configuration();
        wp_set_current_user(0);
        return $out;
    })(),
]);

$rules = new WP_Speculation_Rules();
$say('the rules object', [
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-one', ['source' => 'list', 'urls' => ['/a/', '/b/'], 'eagerness' => 'moderate'])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-one', ['source' => 'list', 'urls' => ['/c/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-two', ['where' => ['href_matches' => '/zz/*']])),
    $heard(static fn () => $rules->add_rule('prerender', 'zz-three', ['source' => 'document', 'where' => ['href_matches' => '/x/*'], 'eagerness' => 'eager'])),
    $heard(static fn () => $rules->add_rule('fetch', 'zz-four', ['source' => 'list', 'urls' => ['/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'Zz Bad Id', ['source' => 'list', 'urls' => ['/']])),
    $heard(static fn () => $rules->add_rule('prefetch', '1zz', ['source' => 'list', 'urls' => ['/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-five', ['source' => 'list', 'urls' => ['/'], 'eagerness' => 'sometimes'])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-six', ['source' => 'nowhere', 'urls' => ['/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-seven', ['source' => 'list', 'where' => ['href_matches' => '/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-eight', ['source' => 'document', 'urls' => ['/']])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-nine', ['source' => 'list'])),
    $heard(static fn () => $rules->add_rule('prefetch', 'zz-ten', ['urls' => ['/ten/']])),
    $heard(static fn () => $rules->add_rule('prerender', 'zz-eleven', ['source' => 'list', 'urls' => ['/eleven/'], 'eagerness' => 'immediate', 'tag' => 'zz'])),
    [$rules->has_rule('prefetch', 'zz-one'), $rules->has_rule('prerender', 'zz-one'), $rules->has_rule('prefetch', 'zz-never'), $rules->has_rule('fetch', 'zz-four')],
    $rules->jsonSerialize(),
    wp_json_encode($rules),
    (new WP_Speculation_Rules())->jsonSerialize(),
    [WP_Speculation_Rules::is_valid_mode('prefetch'), WP_Speculation_Rules::is_valid_mode('prerender'), WP_Speculation_Rules::is_valid_mode('PREFETCH'), WP_Speculation_Rules::is_valid_mode(5)],
    [WP_Speculation_Rules::is_valid_eagerness('immediate'), WP_Speculation_Rules::is_valid_eagerness('auto'), WP_Speculation_Rules::is_valid_eagerness('conservative'), WP_Speculation_Rules::is_valid_eagerness(null)],
    [WP_Speculation_Rules::is_valid_source('list'), WP_Speculation_Rules::is_valid_source('document'), WP_Speculation_Rules::is_valid_source('List')],
]);

$prefixer = new WP_URL_Pattern_Prefixer();
$say('the URL pattern prefixer', [
    $mask(WP_URL_Pattern_Prefixer::get_default_contexts()),
    $prefixer->prefix_path_pattern('/zz/*'), $prefixer->prefix_path_pattern('zz/*'), $prefixer->prefix_path_pattern('/*\\?(.+)'),
    $prefixer->prefix_path_pattern('/x/*', 'site'), $prefixer->prefix_path_pattern('/x/*', 'uploads'), $prefixer->prefix_path_pattern('/x/*', 'content'),
    $prefixer->prefix_path_pattern('/x/*', 'plugins'), $prefixer->prefix_path_pattern('/x/*', 'template'), $prefixer->prefix_path_pattern('/x/*', 'stylesheet'),
    $heard(static fn () => $prefixer->prefix_path_pattern('/x/*', 'nowhere')),
    (new WP_URL_Pattern_Prefixer(['home' => '/sub dir+x(1)/', 'zz' => '/zz:{a}*?/']))->prefix_path_pattern('/x/*'),
    (new WP_URL_Pattern_Prefixer(['home' => '/sub dir+x(1)/', 'zz' => '/zz:{a}*?/']))->prefix_path_pattern('/x/*', 'zz'),
    (new WP_URL_Pattern_Prefixer(['home' => '']))->prefix_path_pattern('/x/*'),
    (new WP_URL_Pattern_Prefixer(['home' => 'nested/path']))->prefix_path_pattern('/x/*'),
    array_map(static fn ($prefix) => (new WP_URL_Pattern_Prefixer(['home' => $prefix]))->prefix_path_pattern('/x/*'), ['/plain/', '/a?/', '/a*/', '/a+/', '/a:b/', '/a{b}/', '/a(b)/', '/a\\b/', '/end?', '/q?x/', '/x:/', '/x}/', '/a.b/', '/a b/', '/a-b_c~/', '/%41/', '/a@b/']),
]);

$say('the rules the site builds', [
    $mask(wp_get_speculation_rules()),
    $mask($with('wp_speculation_rules_href_exclude_paths', static fn ($paths, $mode) => [...$paths, '/zz-skip/*', 'zz-relative/*', $mode], static fn () => wp_get_speculation_rules(), 2)),
    $mask($with('wp_speculation_rules_configuration', static fn () => ['mode' => 'prerender', 'eagerness' => 'moderate'], static fn () => wp_get_speculation_rules())),
    $mask($with('wp_speculation_rules_configuration', static fn () => null, static fn () => wp_get_speculation_rules())),
    (static function () use ($mask) {
        $added = static function (WP_Speculation_Rules $rules): void {
            $rules->add_rule('prerender', 'zz-added', ['source' => 'list', 'urls' => ['/zz-added/']]);
        };
        add_action('wp_load_speculation_rules', $added);
        $out = $mask(wp_get_speculation_rules());
        remove_action('wp_load_speculation_rules', $added);
        return $out;
    })(),
]);

$say('the script printed', [
    (static function () use ($mask) {
        ob_start();
        wp_print_speculation_rules();
        return $mask(ob_get_clean());
    })(),
    (static function () use ($with, $plain) {
        ob_start();
        $with('pre_option_permalink_structure', $plain, static fn () => wp_print_speculation_rules());
        return ob_get_clean();
    })(),
    has_action('wp_footer', 'wp_print_speculation_rules'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
