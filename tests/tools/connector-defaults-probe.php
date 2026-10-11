<?php
/**
 * The default connectors (probe connector-defaults): the AI providers a
 * fresh registry is given, each with its authentication and plugin (whether
 * the plugin is active, asked), the key settings registered for them (none
 * while their plugins are inactive; a listed plugin is not enough on the
 * reference, and no provider plugin can be loaded here), and the connectors
 * the site has, in order. Nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
if (!did_action('init')) {
    do_action('init');
}
$describe = static function ($value) use (&$describe) {
    if ($value instanceof Closure || (is_array($value) && is_callable($value) && array_is_list($value) && count($value) === 2)) {
        return ['callable, answering' => $value()];
    }
    return is_array($value) ? array_map($describe, $value) : $value;
};

$registry = new WP_Connector_Registry();
$returned = _wp_connectors_register_default_ai_providers($registry);
$say('the default AI providers', ['returned' => $returned, 'connectors' => $describe($registry->get_all_registered())]);

$settings = static function (): array {
    $out = [];
    foreach (get_registered_settings() as $name => $args) {
        if (str_starts_with((string) $name, 'connectors_')) {
            $out[$name] = array_map(static fn ($value) => is_callable($value) && !is_string($value) ? 'a callable' : $value, $args);
        }
    }
    ksort($out);
    return $out;
};
$before = $settings();
$say('their key settings, plugins inactive', ['returned' => _wp_register_default_connector_settings(), 'registered' => array_diff_key($settings(), $before)]);
// Core's connectors in the site's order; one a plugin adds (Minn Admin's CleanTalk) is the environment's.
$say('the connectors the site has', array_values(array_intersect(array_keys(wp_get_connectors()), ['anthropic', 'google', 'openai', 'akismet'])));
$say('the settings put back', array_keys(array_diff_key($settings(), $before)));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
