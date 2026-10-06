<?php
/**
 * wp/v2/menu-locations as the reference answers it: the locations a theme
 * or plugin registers, one with a menu assigned and one without, to a
 * visitor, an editor and an administrator; one location; an unknown one;
 * and what rest_prepare_menu_location hands plugins. Same protocol as
 * api-probe.php; dispatched in process; the location assignments are put
 * back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$had = get_theme_mod('nav_menu_locations');
$menu = (int) wp_create_nav_menu('zz probe location menu');
register_nav_menus(['zz-probe-top' => 'Probe Top', 'zz-probe-foot' => 'Probe Foot']);
set_theme_mod('nav_menu_locations', ['zz-probe-top' => $menu] + (array) $had);
$handed = [];
add_filter('rest_prepare_menu_location', static function ($response, $location, $request) use (&$handed) {
    $handed[] = [$location->name, $location->description, get_class($response)];
    return $response;
}, 10, 3);
$mask = static fn ($value) => json_decode(str_replace(['/menus/' . $menu . '"', '"menu":' . $menu], ['/menus/{menu}"', '"menu":"{menu}"'], (string) json_encode($value, JSON_UNESCAPED_SLASHES)), true);
$send = static function (string $label, string $route) use ($say, $mask, &$handed): void {
    $handed = [];
    $response = rest_do_request(new WP_REST_Request('GET', $route));
    $data = rest_get_server()->response_to_data($response, false);
    if (is_array($data) && $route === '/wp/v2/menu-locations') {
        $data = array_intersect_key($data, array_flip(['zz-probe-top', 'zz-probe-foot']));
    }
    $say($label, $mask(['status' => $response->get_status(), 'data' => $data, 'handed' => array_values(array_filter($handed, static fn ($h) => str_starts_with($h[0], 'zz-probe')))]));
};
foreach ([0 => 'visitor', 2 => 'editor', 1 => 'admin'] as $user => $who) {
    wp_set_current_user($user);
    $send("{$who} list", '/wp/v2/menu-locations');
    $send("{$who} assigned", '/wp/v2/menu-locations/zz-probe-top');
}
$send('admin unassigned', '/wp/v2/menu-locations/zz-probe-foot');
$send('admin unknown', '/wp/v2/menu-locations/zz-nope');
wp_set_current_user(0);
$had === false ? remove_theme_mod('nav_menu_locations') : set_theme_mod('nav_menu_locations', $had);
unregister_nav_menu('zz-probe-top');
unregister_nav_menu('zz-probe-foot');
wp_delete_nav_menu($menu);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
