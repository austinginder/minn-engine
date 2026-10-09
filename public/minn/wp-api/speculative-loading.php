<?php
/**
 * Speculative loading (probe speculative-loading): the configuration a site
 * runs with, the document rule built from it with the WordPress paths left
 * out, and the script printed on wp_footer for signed-out visitors under
 * pretty permalinks, block themes and classic ones alike.
 */

function wp_get_speculation_rules_default_configuration(): array
{
    return ['mode' => 'prefetch', 'eagerness' => 'conservative'];
}

/**
 * The mode and eagerness, through wp_speculation_rules_configuration: null
 * turns speculative loading off (as it starts for a signed-in visitor or
 * plain permalinks); auto, anything unknown, and immediate eagerness
 * settle on the defaults.
 */
function wp_get_speculation_rules_configuration(): ?array
{
    $off = is_user_logged_in() || !get_option('permalink_structure');
    $config = apply_filters('wp_speculation_rules_configuration', $off ? null : ['mode' => 'auto', 'eagerness' => 'auto']);
    if ($config === null) {
        return null;
    }
    $config = is_array($config) ? $config : [];
    $defaults = wp_get_speculation_rules_default_configuration();
    $mode = (string) ($config['mode'] ?? 'auto');
    $eagerness = (string) ($config['eagerness'] ?? 'auto');
    return [
        'mode' => WP_Speculation_Rules::is_valid_mode($mode) ? $mode : $defaults['mode'],
        'eagerness' => $eagerness !== 'immediate' && WP_Speculation_Rules::is_valid_eagerness($eagerness) ? $eagerness : $defaults['eagerness'],
    ];
}

/**
 * The site's rules: one document rule for every link under home, except
 * the WordPress paths (scripts, wp-admin, uploads, content, plugins, the
 * themes, query strings), any wp_speculation_rules_href_exclude_paths adds,
 * nofollow links and those marked no-{mode} (no-prefetch too for
 * prerender); then wp_load_speculation_rules may add more.
 */
function wp_get_speculation_rules(): ?WP_Speculation_Rules
{
    $config = wp_get_speculation_rules_configuration();
    if ($config === null) {
        return null;
    }
    $mode = $config['mode'];
    $prefixer = new WP_URL_Pattern_Prefixer();
    $exclude = [$prefixer->prefix_path_pattern('/wp-*.php', 'site'), $prefixer->prefix_path_pattern('/wp-admin/*', 'site')];
    foreach (['uploads', 'content', 'plugins', 'template', 'stylesheet'] as $context) {
        $exclude[] = $prefixer->prefix_path_pattern('/*', $context);
    }
    $exclude[] = $prefixer->prefix_path_pattern('/*\\?(.+)');
    foreach ((array) apply_filters('wp_speculation_rules_href_exclude_paths', [], $mode) as $path) {
        $exclude[] = $prefixer->prefix_path_pattern((string) $path);
    }
    $marked = $mode === 'prerender' ? ['prerender', 'prefetch'] : [$mode];
    $where = [['href_matches' => $prefixer->prefix_path_pattern('/*')], ['not' => ['href_matches' => array_values(array_unique($exclude))]], ['not' => ['selector_matches' => 'a[rel~="nofollow"]']]];
    foreach ($marked as $class) {
        $where[] = ['not' => ['selector_matches' => ".no-{$class}, .no-{$class} a"]];
    }
    $rules = new WP_Speculation_Rules();
    $rules->add_rule($mode, 'main', ['source' => 'document', 'where' => ['and' => $where], 'eagerness' => $config['eagerness']]);
    do_action('wp_load_speculation_rules', $rules);
    return $rules;
}

function wp_print_speculation_rules(): void
{
    $rules = wp_get_speculation_rules();
    if ($rules !== null) {
        wp_print_inline_script_tag((string) wp_json_encode($rules, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), ['type' => 'speculationrules']);
    }
}
