<?php
/**
 * The REST block and menu-location controllers as classes (probe
 * rest-block-controllers): WP_REST_Block_Types_Controller,
 * WP_REST_Block_Pattern_Categories_Controller,
 * WP_REST_Menu_Locations_Controller and WP_REST_Block_Renderer_Controller
 * called directly, as a plugin would. Their schemas and parameters, the
 * permission checks for nobody, an administrator and a contributor, lists
 * (narrowed by namespace) and single items in each context, a block type,
 * pattern categories and menu locations the probe registers among them,
 * items prepared with chosen fields, their links, and blocks rendered
 * (attributes, a post's context, an unknown block, a block that renders
 * nothing of its own). Registrations live for the request only; the
 * probe's user and post are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => [], 'menus' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $mask($item);
        }
        return $out;
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($host, '{host}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $mask($response)];
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links()), $mask($headers)];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $route, array $params = []) {
    $request = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
// Called directly, a list needs the defaults dispatch would have given it from the collection parameters.
$listing = static function ($controller, string $route, array $params = []) use ($request) {
    $out = $request($route, $params);
    $out->set_default_params(array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null));
    return $out;
};
// A protected method, called from inside its class.
$reach = static fn (object $controller, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $controller, get_class($controller))(...$args);
// A list keeps only the probe's own entries (and the ones named), so other registrations do not move it.
$ours = static function ($response, string $key, array $also = []) use ($answer) {
    if ($response instanceof WP_REST_Response && is_array($response->get_data())) {
        $response->set_data(array_values(array_filter($response->get_data(), static fn ($item) => str_starts_with((string) ($item[$key] ?? ''), 'zz') || in_array($item[$key] ?? null, $also, true))));
    }
    return $answer($response);
};

register_block_type('zzreg/notice', [
    'title' => 'Zz Notice', 'category' => 'widgets', 'icon' => 'warning', 'description' => 'A probe block.', 'keywords' => ['zz', 'probe'],
    'attributes' => ['message' => ['type' => 'string', 'default' => 'Hi'], 'level' => ['type' => 'integer', 'default' => 1]],
    'supports' => ['color' => ['background' => true], 'html' => false], 'uses_context' => ['postId'], 'provides_context' => ['zzreg/level' => 'level'],
    'parent' => ['core/group'], 'ancestor' => ['core/column'], 'example' => ['attributes' => ['message' => 'Example']],
    'styles' => [['name' => 'zz-bold', 'label' => 'Bold']], 'variations' => [['name' => 'zz-loud', 'title' => 'Loud', 'attributes' => ['level' => 3]]],
    'render_callback' => static fn ($attributes, $content, $block) => '<p class="zz-notice">' . esc_html($attributes['message']) . ' (' . (int) $attributes['level'] . ') post ' . (int) ($block->context['postId'] ?? 0) . '</p>',
]);
register_block_type('zzreg/static', ['title' => 'Zz Static', 'category' => 'text']);
register_block_style('zzreg/static', ['name' => 'zz-outline', 'label' => 'Outline']);
register_block_pattern_category('zzreg-cards', ['label' => 'Zz Cards', 'description' => 'Probe cards.']);
register_block_pattern_category('zzreg-plain', ['label' => 'Zz Plain']);
register_nav_menus(['zzreg-primary' => 'Zz Primary', 'zzreg-footer' => 'Zz Footer']);
// Menus assigned to locations without writing the theme's settings: one that exists, one that does not.
$menu = wp_create_nav_menu('zzreg block menu');
if (!is_int($menu) || $menu < 2) {
    fwrite(STDERR, "rest-block-controllers: the menu could not be made\n");
    exit(1);
}
$made['menus'][] = $menu;
$ids[$menu] = 'menu';
$assigned = static fn ($locations) => ['zzreg-primary' => 77, 'zzreg-footer' => $menu] + (array) $locations;
add_filter('theme_mod_nav_menu_locations', $assigned);

$contributor = wp_insert_user(['user_login' => 'zzregblockcontributor', 'user_email' => 'zzregblockcontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-block-controllers: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;
$post = wp_insert_post(['post_title' => 'zzregblock post', 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
if (!is_int($post) || $post < 2) {
    fwrite(STDERR, "rest-block-controllers: the post could not be made\n");
    exit(1);
}
$made['posts'][] = $post;
$ids[$post] = 'post';

$types = new WP_REST_Block_Types_Controller();
$categories = new WP_REST_Block_Pattern_Categories_Controller();
$locations = new WP_REST_Menu_Locations_Controller();
$renderer = new WP_REST_Block_Renderer_Controller();
$say('the classes', [get_parent_class($types), get_parent_class($categories), get_parent_class($locations), get_parent_class($renderer), WP_REST_Block_Types_Controller::NAME_PATTERN]);
$say('schemas and parameters', [
    'block types' => [$types->get_item_schema(), $types->get_collection_params()],
    'pattern categories' => $categories->get_item_schema(),
    'menu locations' => [$locations->get_item_schema(), $locations->get_collection_params()],
    'renderer' => $renderer->get_item_schema(),
]);

foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", [
        'block types' => [
            $try(static fn () => $types->get_items_permissions_check($request('/wp/v2/block-types'))),
            $try(static fn () => $types->get_item_permissions_check($request('/wp/v2/block-types/zzreg/notice', ['namespace' => 'zzreg', 'name' => 'notice']))),
            $try(static fn () => $types->get_item_permissions_check($request('/wp/v2/block-types/zzreg/none', ['namespace' => 'zzreg', 'name' => 'none']))),
            $try(static fn () => $reach($types, 'check_read_permission')),
        ],
        'pattern categories' => $try(static fn () => $categories->get_items_permissions_check($request('/wp/v2/block-patterns/categories'))),
        'menu locations' => [
            $try(static fn () => $locations->get_items_permissions_check($request('/wp/v2/menu-locations'))),
            $try(static fn () => $locations->get_item_permissions_check($request('/wp/v2/menu-locations/zzreg-primary', ['location' => 'zzreg-primary']))),
            $try(static fn () => $reach($locations, 'check_has_read_only_access', $request('/wp/v2/menu-locations'))),
        ],
        'renderer' => [
            $try(static fn () => $renderer->get_item_permissions_check($request('/wp/v2/block-renderer/zzreg/notice', ['name' => 'zzreg/notice', 'context' => 'edit']))),
            $try(static fn () => $renderer->get_item_permissions_check($request('/wp/v2/block-renderer/zzreg/notice', ['name' => 'zzreg/notice', 'context' => 'edit', 'post_id' => $post]))),
            $try(static fn () => $renderer->get_item_permissions_check($request('/wp/v2/block-renderer/zzreg/notice', ['name' => 'zzreg/notice', 'context' => 'edit', 'post_id' => 999999]))),
        ],
    ]);
}

wp_set_current_user(1);
$say('block types', [
    $ours($types->get_items($listing($types, '/wp/v2/block-types')), 'name', ['core/paragraph']),
    $ours($types->get_items($listing($types, '/wp/v2/block-types', ['namespace' => 'zzreg'])), 'name'),
    $ours($types->get_items($listing($types, '/wp/v2/block-types', ['context' => 'embed', 'namespace' => 'zzreg'])), 'name'),
    $try(static fn () => $types->get_item($request('/wp/v2/block-types/zzreg/notice', ['namespace' => 'zzreg', 'name' => 'notice', 'context' => 'edit']))),
    $try(static fn () => $types->get_item($request('/wp/v2/block-types/zzreg/static', ['namespace' => 'zzreg', 'name' => 'static']))),
    $try(static fn () => $types->get_item($request('/wp/v2/block-types/zzreg/none', ['namespace' => 'zzreg', 'name' => 'none']))),
    $try(static fn () => $types->prepare_item_for_response(WP_Block_Type_Registry::get_instance()->get_registered('zzreg/notice'), (static function () {
        $request = new WP_REST_Request('GET', '/wp/v2/block-types');
        $request->set_param('_fields', 'name,title,attributes.level,styles,is_dynamic');
        return $request;
    })())),
    $mask($reach($types, 'prepare_links', WP_Block_Type_Registry::get_instance()->get_registered('zzreg/notice'))),
    $mask($reach($types, 'prepare_links', WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph'))),
    (static fn ($block) => $block instanceof WP_Block_Type ? [get_class($block), $block->name] : $answer($block))($reach($types, 'get_block', 'zzreg/static')),
    $try(static fn () => $reach($types, 'get_block', 'zzreg/none')),
]);
$say('pattern categories', [
    $ours($categories->get_items($request('/wp/v2/block-patterns/categories')), 'name'),
    $try(static fn () => $categories->prepare_item_for_response(['name' => 'zzreg-made', 'label' => 'Zz Made', 'description' => 'Made here.'], $request('/wp/v2/block-patterns/categories', ['_fields' => 'name,label']))),
    $try(static fn () => $categories->prepare_item_for_response(['name' => 'zzreg-bare', 'label' => 'Zz Bare'], $request('/wp/v2/block-patterns/categories'))),
]);
$say('menu locations', [
    $ours($locations->get_items($listing($locations, '/wp/v2/menu-locations')), 'name'),
    $try(static fn () => $locations->get_item($request('/wp/v2/menu-locations/zzreg-primary', ['location' => 'zzreg-primary']))),
    $try(static fn () => $locations->get_item($request('/wp/v2/menu-locations/zzreg-footer', ['location' => 'zzreg-footer', 'context' => 'embed']))),
    $try(static fn () => $locations->get_item($request('/wp/v2/menu-locations/zzreg-none', ['location' => 'zzreg-none']))),
    $try(static fn () => $locations->prepare_item_for_response((object) ['name' => 'zzreg-made', 'description' => 'Zz Made', 'menu' => 0], $request('/wp/v2/menu-locations', ['_fields' => 'name,menu']))),
    $mask($reach($locations, 'prepare_links', (object) ['name' => 'zzreg-primary', 'description' => 'Zz Primary', 'menu' => 77])),
    $mask($reach($locations, 'prepare_links', (object) ['name' => 'zzreg-footer', 'description' => 'Zz Footer', 'menu' => $menu])),
    $mask($reach($locations, 'prepare_links', (object) ['name' => 'zzreg-made', 'description' => 'Zz Made', 'menu' => 0])),
]);
// A filter may open menu locations to readers who cannot edit the theme.
$heard = [];
$open = static function ($open, $request = null, $controller = null) use (&$heard) {
    $heard[] = [$open, is_object($request) ? get_class($request) : $request, is_object($controller) ? get_class($controller) : $controller];
    return true;
};
add_filter('rest_menu_read_access', $open, 10, 3);
wp_set_current_user($contributor);
$say('menu locations opened by a filter', [
    $try(static fn () => $locations->get_items_permissions_check($request('/wp/v2/menu-locations'))),
    $try(static fn () => $locations->get_item_permissions_check($request('/wp/v2/menu-locations/zzreg-primary', ['location' => 'zzreg-primary']))),
    $heard,
]);
remove_filter('rest_menu_read_access', $open, 10);
wp_set_current_user(1);
$rendered = static function (array $params) use ($renderer, $request, $try) {
    return $try(static fn () => $renderer->get_item($request('/wp/v2/block-renderer/' . ($params['name'] ?? ''), $params + ['context' => 'edit'])));
};
$say('blocks rendered', [
    'defaults' => $rendered(['name' => 'zzreg/notice']),
    'attributes' => $rendered(['name' => 'zzreg/notice', 'attributes' => ['message' => 'Hello <b>there</b>', 'level' => 2]]),
    'a post' => $rendered(['name' => 'zzreg/notice', 'post_id' => $post]),
    'an unknown block' => $rendered(['name' => 'zzreg/none']),
    'a block with no renderer' => $rendered(['name' => 'zzreg/static']),
]);
remove_filter('theme_mod_nav_menu_locations', $assigned);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
