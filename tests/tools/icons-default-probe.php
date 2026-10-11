<?php
/**
 * The core icons (probe icons-default): the names, labels and collection
 * the default icons are registered under, in order; the second
 * registration refused icon by icon; a search; an icon printed by
 * wp_get_icon(), its outer tag; and the REST listing's names and labels.
 * The artwork is Minn's own (D16), so each icon's content is checked only
 * for being a 24 by 24 SVG. The reference reads its library's art from a
 * file, so its icons carry a file_path and gain their content last; Minn's
 * art is registered as content (which the reference orders as the engine
 * does), so each icon's fields are compared as a set, file_path left out.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
if (!did_action('init')) {
    do_action('init');
}
$registry = WP_Icons_Registry::get_instance();
$svg = static fn ($content) => is_string($content) && str_starts_with($content, '<svg') && str_contains(strtolower($content), 'viewbox="0 0 24 24"');
$say('the default icons', array_map(static fn ($icon) => ['name' => $icon['name'], 'label' => $icon['label'], 'collection' => $icon['collection'], 'fields' => (static function (array $keys): array {
        sort($keys);
        return $keys;
    })(array_values(array_diff(array_keys($icon), ['file_path']))), 'a 24 by 24 svg' => $svg($icon['content'] ?? null)], $registry->get_registered_icons()));

$notes = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$notes) {
    $notes[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$returned = _wp_register_default_icons();
$say('registered again', ['returned' => $returned, 'refusals' => count($notes), 'kinds' => array_values(array_unique(array_map('json_encode', $notes)))]);

$say('a search', array_map(static fn ($icon) => $icon['name'], $registry->get_registered_icons('arrow up')));
$tag = static fn (string $html) => preg_match('/^<svg[^>]*>/', $html, $m) ? $m[0] : $html;
$say('printed', ['labelled' => $tag(wp_get_icon('core/home', ['label' => 'Home', 'size' => 32])), 'hidden' => $tag(wp_get_icon('core/search')), 'unknown' => wp_get_icon('core/zz-none')]);

$rest = rest_do_request(new WP_REST_Request('GET', '/wp/v2/icons'));
$say('over REST', ['status' => $rest->get_status(), 'icons' => array_map(static fn ($icon) => [$icon['name'] ?? null, $icon['label'] ?? null, $svg($icon['content'] ?? null)], (array) $rest->get_data())]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
