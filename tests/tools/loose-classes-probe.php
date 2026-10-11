<?php
/**
 * The loose classes and methods left (probe loose-classes): the filter
 * sentinel's shape; the old Atom server class and the feed cache class,
 * whose calls are deprecated (silenced and recorded); a fresh locale
 * switcher's state; the nav menu walker's attribute string and the
 * privacy policy address it notes when it is made; the theme.json data the
 * registered blocks carry; and the navigation block's renderer class. A
 * page made for the privacy policy, and the option, are put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('deprecated_file_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    $deprecated[] = ['function', $function, $replacement, $version];
}, 10, 3);
add_action('deprecated_file_included', static function ($file, $replacement, $version, $message) use (&$deprecated) {
    $deprecated[] = ['file', basename((string) $file), $replacement, $version, $message];
}, 10, 4);
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$heard = static function (Closure $run) use (&$deprecated, $try) {
    $deprecated = [];
    $result = $try($run);
    return [is_object($result) ? get_class($result) : $result, $deprecated];
};
$shape = static function (string $class) {
    $reflection = new ReflectionClass($class);
    return ['final' => $reflection->isFinal(), 'parent' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null, 'methods' => array_map(static fn ($method) => $method->getName(), $reflection->getMethods()), 'properties' => count($reflection->getProperties()), 'constants' => $reflection->getConstants()];
};

$say('the filter sentinel', [$shape(WP_Filter_Sentinel::class), new WP_Filter_Sentinel() instanceof WP_Filter_Sentinel]);

$say('the atom server', [
    'a method' => $heard(static fn () => (new wp_atom_server())->handle_request('a', 2)),
    'a static one' => $heard(static fn () => wp_atom_server::get_service()),
]);

// The feed cache class lives in a file of its own the reference loads only when asked, after SimplePie.
if (!class_exists('SimplePie\\Cache')) {
    require_once ABSPATH . WPINC . '/class-simplepie.php';
}
if (!class_exists('WP_Feed_Cache')) {
    require_once ABSPATH . WPINC . '/class-wp-feed-cache.php';
}
if (!class_exists('WP_Feed_Cache_Transient')) {
    require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
}
$say('the feed cache', [
    'its parent' => get_parent_class('WP_Feed_Cache'),
    // SimplePie's cache keeps its constructor private, so the object is made without it.
    'one made' => $heard(static fn () => (new ReflectionClass(WP_Feed_Cache::class))->newInstanceWithoutConstructor()->create('ignored', 'zz-lc-feed', 'spc')),
    'what it made' => (static function () {
        $made = (new ReflectionClass(WP_Feed_Cache::class))->newInstanceWithoutConstructor()->create('ignored', 'zz-lc-feed', 'spc');
        return is_object($made) ? [get_class($made), $made->name, $made->mod_name, $made->lifetime] : $made;
    })(),
]);

$switcher = new WP_Locale_Switcher();
$say('a fresh locale switcher', [
    'switched' => $switcher->is_switched(),
    'its locale' => $switcher->get_switched_locale(),
    'its user' => $switcher->get_switched_user_id(),
    'to the locale in force' => $switcher->switch_to_locale('en_US'),
    'to one not installed' => $switcher->switch_to_locale('zz_ZZ'),
    'to a user who is not there' => $switcher->switch_to_user_locale(999999),
    'back' => [$switcher->restore_previous_locale(), $switcher->restore_current_locale()],
    'the locale filter' => $switcher->filter_locale('fr_FR'),
]);

// The nav menu walker: its attribute string, and the privacy policy address it notes when made.
$atts = static fn (array $atts) => $try(static fn () => Closure::bind(fn ($given) => $this->build_atts($given), new Walker_Nav_Menu(), Walker_Nav_Menu::class)($atts));
$say('menu link attributes', [
    'none' => $atts([]),
    'the usual' => $atts(['target' => '_blank', 'rel' => 'nofollow', 'href' => 'https://example.com/a b?x=1&y=2', 'aria-current' => 'page', 'title' => 'A "quoted" <title>']),
    'empty ones' => $atts(['target' => '', 'rel' => null, 'href' => '', 'title' => false]),
    'zero and true' => $atts(['data-zero' => 0, 'data-text-zero' => '0', 'data-true' => true, 'data-number' => 12.5]),
    'a script address' => $atts(['href' => 'javascript:alert(1)', 'class' => 'zz']),
    'an address in another attribute' => $atts(['data-href' => 'https://example.com/<b>', 'src' => 'https://example.com/a b']),
    'odd names' => $atts(['data x' => 'y', 'ok' => 'z']),
]);
$privacy = get_option('wp_page_for_privacy_policy', null);
$page = wp_insert_post(['post_title' => 'ZZ lc privacy', 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz']);
register_shutdown_function(static function () use ($privacy, $page): void {
    $privacy === null ? delete_option('wp_page_for_privacy_policy') : update_option('wp_page_for_privacy_policy', $privacy);
    if (is_int($page) && $page > 1) {
        wp_delete_post($page, true);
    }
});
if (!is_int($page) || $page <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$item = static fn (string $url, int $id = 900001, string $xfn = '') => (object) ['ID' => $id, 'db_id' => $id, 'menu_item_parent' => 0, 'title' => 'ZZ item', 'url' => $url, 'target' => '', 'attr_title' => '', 'xfn' => $xfn, 'classes' => [], 'current' => false, 'type' => 'custom', 'object' => 'custom', 'object_id' => $id];
$element = static function (Walker_Nav_Menu $walker, string $url, int $id = 900001, string $xfn = '') use ($item) {
    $output = '';
    $walker->start_el($output, $item($url, $id, $xfn), 0, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'preserve']);
    return str_replace(untrailingslashit((string) get_permalink($GLOBALS['zz_lc_page'])), '{page}', $output);
};
$GLOBALS['zz_lc_page'] = $page;
$before = new Walker_Nav_Menu();
update_option('wp_page_for_privacy_policy', $page);
$after = new Walker_Nav_Menu();
$link = (string) get_permalink($page);
$say('the privacy policy link', [
    'a walker made before it was set' => $element($before, $link),
    'one made after' => $element($after, $link),
    'another address' => $element($after, 'https://example.com/zz'),
    'the address with a slash less' => $element($after, untrailingslashit($link)),
    'with a rel of its own' => $element($after, $link, 900002, 'nofollow'),
]);
// An item's id attribute is printed once a request: the same item again, another item, and two items given one id.
$say('menu item ids', (static function () use ($element, $after) {
    $same = static fn () => 'zz-lc-same';
    $out = ['the same item again' => $element($after, 'https://example.com/a', 900002), 'another item' => $element($after, 'https://example.com/b', 900003)];
    add_filter('nav_menu_item_id', $same);
    $out['a filtered id'] = $element($after, 'https://example.com/c', 900004);
    $out['the filtered id again'] = $element($after, 'https://example.com/d', 900005);
    remove_filter('nav_menu_item_id', $same);
    $out['a fresh walker'] = $element(new Walker_Nav_Menu(), 'https://example.com/e', 900003);
    // Whether an item already given its id still asks the filter.
    $asked = 0;
    $count = static function ($id) use (&$asked) {
        $asked++;
        return $id;
    };
    add_filter('nav_menu_item_id', $count);
    $element($after, 'https://example.com/f', 900003);
    remove_filter('nav_menu_item_id', $count);
    $out['the filter asked for a repeat'] = $asked;
    return $out;
})());
delete_option('wp_page_for_privacy_policy');
$say('no privacy policy page', $element(new Walker_Nav_Menu(), $link));

$filters = [];
$listen = static function () use (&$filters) {
    if (str_contains(current_filter(), 'theme_json')) {
        $filters[] = current_filter();
    }
    return func_num_args() ? func_get_arg(0) : null;
};
add_filter('all', $listen);
$blocks = WP_Theme_JSON_Resolver::get_block_data();
$again = WP_Theme_JSON_Resolver::get_block_data();
remove_filter('all', $listen);
$say('block data', ['raw' => $blocks->get_raw_data(), 'filters' => $filters, 'the same object again' => $blocks === $again]);

// The navigation block's renderer, called as the block's render callback (as render_block_core_navigation
// calls it), so the block supports see the block being rendered; the layout hash is each stack's own.
$type = WP_Block_Type_Registry::get_instance()->get_registered('core/navigation');
$callback = $type->render_callback;
$type->render_callback = static fn ($attributes, $content, $block) => WP_Navigation_Block_Renderer::render($attributes, $content, $block);
$navigation = static function (string $attrs, bool $links = true) {
    $inner = $links ? '<!-- wp:navigation-link {"label":"ZZ one","url":"https://example.com/one","kind":"custom"} /--><!-- wp:navigation-link {"label":"ZZ two","url":"https://example.com/two","kind":"custom"} /-->' : '';
    $markup = $links ? '<!-- wp:navigation ' . $attrs . ' -->' . $inner . '<!-- /wp:navigation -->' : '<!-- wp:navigation ' . $attrs . ' /-->';
    return preg_replace(['/(wp-container-core-navigation-is-layout-)[0-9a-f]{8}/', '/(modal-)\d+/'], ['$1HASH', '$1N'], render_block(parse_blocks($markup)[0]));
};
$say('the navigation renderer', [
    'links, no name' => $navigation('{"overlayMenu":"never","layout":{"type":"flex","justifyContent":"center"}}'),
    'an aria label' => $navigation('{"overlayMenu":"never","ariaLabel":"ZZ main"}'),
    'the same aria label again' => $navigation('{"overlayMenu":"never","ariaLabel":"ZZ main"}'),
    'a missing menu' => $navigation('{"overlayMenu":"never","ref":999999}', false),
    'links again, no name' => $navigation('{"overlayMenu":"never"}'),
    'responsive, an aria label' => $navigation('{"ariaLabel":"ZZ overlay"}'),
    'responsive, a missing menu' => $navigation('{"ref":999999}', false),
]);
$type->render_callback = $callback;

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
