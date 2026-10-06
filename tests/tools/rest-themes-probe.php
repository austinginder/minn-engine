<?php
/**
 * wp/v2/themes as the reference answers it: the list (every installed
 * theme, or only the active one with status=active) to a visitor, an
 * editor and an administrator; one theme by stylesheet, active and not;
 * an unknown theme; the edit context; and _fields. The active theme's
 * supports are the editor's first read. Same protocol as api-probe.php;
 * dispatched in process; site addresses masked.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// Feature tags are translated only where the admin's theme feature list is loaded (WP-CLI loads it, a REST
// request does not), so they are counted here, not compared; HTTP shows them as written on both stacks.
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        if (isset($value['tags']['raw']) && is_array($value['tags']['raw'])) {
            $value['tags'] = count($value['tags']['raw']);
        }
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace([rest_url(), content_url(), home_url()], ['{rest}/', '{content}', '{home}'], $value) : $value;
};
$send = static function (string $label, string $route, array $query = []) use ($say, $mask): void {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    $say($label, ['status' => $response->get_status(), 'data' => $mask($data)]);
};
$active = get_stylesheet();
$other = '';
foreach (array_keys(wp_get_themes()) as $stylesheet) {
    if ($stylesheet !== $active && $stylesheet !== get_template()) {
        $other = $stylesheet;
        break;
    }
}
$say('themes', ['active' => $active, 'other' => $other !== '']);
wp_set_current_user(0);
$send('visitor list', '/wp/v2/themes');
$send('visitor active', '/wp/v2/themes', ['status' => 'active']);
$send('visitor one', '/wp/v2/themes/' . $active);
$send('visitor unknown', '/wp/v2/themes/zz-no-such-theme');
wp_set_current_user(2);
$send('editor active', '/wp/v2/themes', ['status' => 'active']);
$send('editor list', '/wp/v2/themes');
$send('editor other', '/wp/v2/themes/' . $other);
wp_set_current_user(1);
$send('admin active', '/wp/v2/themes', ['status' => 'active']);
$send('admin active edit', '/wp/v2/themes', ['status' => 'active', 'context' => 'edit']);
$send('admin list', '/wp/v2/themes', ['_fields' => 'stylesheet,status,name']);
$send('admin inactive', '/wp/v2/themes', ['status' => 'inactive', '_fields' => 'stylesheet,status']);
$send('admin one', '/wp/v2/themes/' . $active);
$send('admin other', '/wp/v2/themes/' . $other, ['_fields' => 'stylesheet,template,status,is_block_theme']);
$send('admin unknown', '/wp/v2/themes/zz-no-such-theme');
$send('admin bad status', '/wp/v2/themes', ['status' => 'retired']);
$send('admin status list', '/wp/v2/themes', ['status' => 'active,inactive', '_fields' => 'stylesheet']);
$send('admin supports', '/wp/v2/themes', ['status' => 'active', '_fields' => 'theme_supports']);
// A classic theme's declarations, made in this request: each feature as its schema shapes it.
add_theme_support('custom-logo', ['width' => 200, 'height' => '100', 'flex-width' => true, 'unlink-homepage-logo' => true]);
add_theme_support('custom-header', ['width' => 1200, 'height' => 280, 'header-text' => false, 'default-image' => '%s/header.jpg', 'video' => true]);
add_theme_support('custom-background', ['default-color' => 'ffffff', 'default-repeat' => 'no-repeat']);
add_theme_support('editor-color-palette', [['name' => 'Ink', 'slug' => 'ink', 'color' => '#111111'], ['name' => 'Paper', 'slug' => 'paper', 'color' => '#fafafa']]);
add_theme_support('editor-font-sizes', [['name' => 'Small', 'slug' => 'small', 'size' => 12], ['name' => 'Big', 'shortName' => 'B', 'slug' => 'big', 'size' => 32]]);
add_theme_support('editor-gradient-presets', [['name' => 'Dusk', 'slug' => 'dusk', 'gradient' => 'linear-gradient(#000,#fff)']]);
add_theme_support('post-thumbnails', ['post', 'page']);
add_theme_support('html5', ['search-form', 'gallery']);
add_theme_support('align-wide');
add_theme_support('title-tag');
add_theme_support('disable-custom-colors');
add_theme_support('dark-editor-style');
remove_theme_support('automatic-feed-links');
register_theme_feature('zz-probe-feature', ['type' => 'boolean', 'description' => 'A probe feature.', 'show_in_rest' => true]);
register_theme_feature('zz-probe-object', ['type' => 'object', 'show_in_rest' => ['schema' => ['properties' => ['size' => ['type' => 'integer'], 'mode' => ['type' => 'string', 'default' => 'auto']]]]]);
add_theme_support('zz-probe-feature');
add_theme_support('zz-probe-object', ['size' => '7']);
$send('declared supports', '/wp/v2/themes', ['status' => 'active', '_fields' => 'theme_supports']);
wp_set_current_user(0);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
