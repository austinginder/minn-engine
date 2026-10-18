<?php
/**
 * A navigation's overlay colours as the reference prints them (probe
 * navigation-overlay): named, custom, both, and custom values carrying
 * more CSS than a colour, on a submenu's list and on the mobile overlay's
 * container, with the overlay never shown and shown on mobile. Each case
 * reports the two opening tags; a page list's submenu items, which wear
 * the custom ones as well, last. Same protocol as api-probe.php; nothing
 * is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$colours = [
    'named' => ['overlayTextColor' => 'contrast', 'overlayBackgroundColor' => 'base'],
    'custom' => ['customOverlayTextColor' => '#123456', 'customOverlayBackgroundColor' => 'rgb(1, 2, 3)'],
    'named and custom' => ['overlayTextColor' => 'contrast', 'customOverlayTextColor' => '#123456', 'customOverlayBackgroundColor' => 'red'],
    'custom carrying more CSS' => ['customOverlayTextColor' => 'blue;background:url(javascript:alert(1))', 'customOverlayBackgroundColor' => 'red" data-zz="1'],
    'custom with an expression' => ['customOverlayTextColor' => 'expression(alert(1))', 'customOverlayBackgroundColor' => 'red;position:fixed'],
];
foreach (['never', 'mobile'] as $overlay) {
    foreach ($colours as $label => $attrs) {
        $nav = do_blocks('<!-- wp:navigation ' . wp_json_encode(['overlayMenu' => $overlay] + $attrs) . ' --><!-- wp:navigation-submenu {"label":"Zz","url":"/zz/"} --><!-- wp:navigation-link {"label":"Zz child","url":"/zz/child/"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->');
        preg_match('/<ul [^>]*wp-block-navigation__submenu-container[^>]*>/', $nav, $submenu);
        preg_match('/<div class="wp-block-navigation__responsive-container[^>]*?id="modal-/', $nav, $container);
        $say("{$overlay}, {$label}", ['submenu' => $submenu[0] ?? '', 'container' => preg_replace('/modal-\d+/', 'modal-{n}', $container[0] ?? '')]);
    }
}

// A page list's submenu items wear them too.
foreach (['custom' => $colours['custom'], 'custom carrying more CSS' => $colours['custom carrying more CSS'], 'custom with an expression' => $colours['custom with an expression']] as $label => $attrs) {
    $nav = do_blocks('<!-- wp:navigation ' . wp_json_encode(['overlayMenu' => 'never'] + $attrs) . ' --><!-- wp:page-list /--><!-- /wp:navigation -->');
    preg_match_all('/<li class="wp-block-pages-list__item[^"]*"[^>]*style="[^"]*"[^>]*>/', $nav, $items);
    $say("page list, {$label}", array_values(array_unique(array_map(static fn (string $tag) => (string) preg_replace('/ (current-menu-item|current-menu-ancestor|menu-item-home)/', '', $tag), $items[0]))));
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
