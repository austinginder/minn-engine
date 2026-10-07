<?php
/**
 * The toolbar plugins add to, as the reference builds it (probe admin-bar):
 * WP_Admin_Bar's nodes (added, merged, grouped, read back, removed, a node
 * with no id), its markup for a small tree of a plugin's own nodes, the
 * nodes WordPress puts there itself for an administrator on the front end
 * (and on a single post, the edit link), the hooks the build fires, and
 * whether the bar shows (show_admin_bar, the user's preference). Nonces are
 * masked. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$mask = static fn ($value) => is_string($value) ? preg_replace('/(_wpnonce=|nonce=|"nonce":")[a-f0-9]{10}/', '$1{nonce}', $value) : $value;
$node = static fn ($node) => is_object($node) ? array_map($mask, get_object_vars($node)) : $node;
// The reference's Minn Admin plugin takes the bar over (it suppresses it, and links itself); set aside, to see WordPress's own.
foreach (['show_admin_bar', 'admin_bar_menu'] as $hook) {
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $owner = is_array($callback['function']) ? $callback['function'][0] : null;
            if ($owner !== null && str_starts_with(is_object($owner) ? get_class($owner) : (string) $owner, 'Minn_Admin')) {
                remove_filter($hook, $callback['function'], $priority);
            }
        }
    }
}
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(admin_bar_init|add_admin_bar_menus|admin_bar_menu|wp_before_admin_bar_render|wp_after_admin_bar_render|wp_admin_bar_class|show_admin_bar|wp_admin_bar_show_site_icons)$/', $hook)) {
        $fired[] = $hook;
    }
});

// The reference loads the class only when the bar is set up; a plugin that builds one itself loads it first.
if (!class_exists('WP_Admin_Bar')) {
    require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
}
$bar = new WP_Admin_Bar();
$bar->add_node(['id' => 'zz-top', 'title' => 'Zz Top', 'href' => 'https://zz.example/top', 'meta' => ['class' => 'zz-class', 'title' => 'Zz tip']]);
$say('a node as added', $node($bar->get_node('zz-top')));
$bar->add_node(['id' => 'zz-top', 'title' => 'Zz Renamed']);
$say('the node added again with a new title', $node($bar->get_node('zz-top')));
$bar->add_node(['id' => 'zz-top', 'meta' => ['target' => '_blank']]);
$say('the node added again with new meta', $node($bar->get_node('zz-top')));
$bar->add_menu(['id' => 'zz-child', 'parent' => 'zz-top', 'title' => 'Zz Child', 'href' => 'https://zz.example/child']);
$say('a child through add_menu', $node($bar->get_node('zz-child')));
$bar->add_group(['id' => 'zz-group', 'parent' => 'zz-top', 'meta' => ['class' => 'zz-grouped']]);
$say('a group', $node($bar->get_node('zz-group')));
$bar->add_node(['id' => 'zz-in-group', 'parent' => 'zz-group', 'title' => 'Zz In Group']);
$say('a node with no id but a title', [$bar->add_node(['title' => 'Zz Untitled Id']), array_keys((array) $bar->get_nodes())]);
$say('a node with neither', $bar->add_node(['href' => 'https://zz.example/none']));
$say('a node with a parent of false and no meta', [$bar->add_node(['id' => 'zz-loose', 'parent' => false, 'title' => 'Zz Loose', 'meta' => 'zz']), $node($bar->get_node('zz-loose'))]);
$copy = $bar->get_node('zz-top');
$copy->title = 'Zz Changed Outside';
$say('a node read back is a copy', $bar->get_node('zz-top')->title);
$say('a node that is not there', $bar->get_node('zz-missing'));
$bar->remove_node('zz-loose');
$bar->remove_menu('zz-zz-untitled-id');
$say('the nodes after removals', array_keys((array) $bar->get_nodes()));
$bar->add_node(['id' => 'zz-meta', 'title' => 'Zz <em>Meta</em>', 'href' => 'https://zz.example/a?b=1&c=<2>"', 'meta' => ['html' => '<span class="zz-html">x</span>', 'tabindex' => 3, 'onclick' => "zz('a')", 'rel' => 'zz', 'lang' => 'fr', 'dir' => 'rtl', 'menu_title' => 'Zz Menu Title', 'title' => 'Zz "tip"', 'target' => '_self', 'class' => ' zz-a  zz-b ']]);
$bar->add_node(['id' => 'zz-meta-child', 'parent' => 'zz-meta', 'title' => 'Zz Meta Child', 'href' => 'https://zz.example/mc', 'meta' => ['tabindex' => 'x']]);
$bar->add_node(['id' => 'zz-grandchild', 'parent' => 'zz-meta-child', 'title' => 'Zz Grandchild', 'meta' => ['tabindex' => '0']]);
$bar->add_group(['id' => 'zz-meta-outer', 'parent' => 'zz-meta']);
$bar->add_node(['id' => 'zz-meta-outer-item', 'parent' => 'zz-meta-outer', 'title' => 'Zz Meta Outer Item']);
$bar->add_group(['id' => 'zz-meta-inner', 'parent' => 'zz-meta-outer']);
$bar->add_node(['id' => 'zz-meta-inner-item', 'parent' => 'zz-meta-inner', 'title' => 'Zz Meta Inner Item']);
$bar->add_group(['id' => 'zz-meta-innermost', 'parent' => 'zz-meta-inner']);
$bar->add_node(['id' => 'zz-meta-innermost-item', 'parent' => 'zz-meta-innermost', 'title' => 'Zz Meta Innermost Item']);
$bar->add_node(['id' => 'zz-nolink-meta', 'title' => 'Zz No Link Meta', 'meta' => ['tabindex' => 2, 'onclick' => "zz('b')", 'rel' => 'zz', 'lang' => 'de', 'dir' => 'ltr', 'title' => 'Zz t', 'target' => '_blank', 'class' => 'zz-nl', 'html' => '<i>h</i>']]);
$bar->add_group(['id' => 'zz-outer-group', 'parent' => 'zz-top']);
$bar->add_group(['id' => 'zz-inner-group', 'parent' => 'zz-outer-group', 'meta' => ['class' => 'zz-inner']]);
$bar->add_node(['id' => 'zz-inner-item', 'parent' => 'zz-inner-group', 'title' => 'Zz Inner Item']);
$bar->add_node(['id' => 'zz-outer-item', 'parent' => 'zz-outer-group', 'title' => 'Zz Outer Item']);
$bar->add_group(['id' => 'zz-top-group', 'meta' => ['class' => 'zz-top-grouped']]);
$bar->add_node(['id' => 'zz-top-group-item', 'parent' => 'zz-top-group', 'title' => 'Zz Top Group Item', 'href' => 'https://zz.example/tg']);
$bar->add_group(['id' => 'zz-empty-group']);
$bar->add_node(['id' => 'zz-orphan', 'parent' => 'zz-nowhere', 'title' => 'Zz Orphan']);
$bar->add_node(['id' => 'zz-nolink', 'title' => 'Zz No Link', 'meta' => ['title' => 'Zz no-link tip', 'menu_title' => 'Zz No Link Menu']]);
$bar->add_node(['id' => 'zz-nolink-child', 'parent' => 'zz-nolink', 'title' => 'Zz Child Of No Link', 'href' => 'https://zz.example/c']);
$bar->add_node((object) ['id' => 'zz-object', 'title' => 'Zz Object']);
$say('a node given as an object', $node($bar->get_node('zz-object')));
$wrong = [];
$heard = static function (...$args) use (&$wrong): void {
    $wrong[] = $args;
};
add_action('doing_it_wrong_run', $heard, 10, 3);
add_action('deprecated_argument_run', $heard, 10, 3);
$bar->add_node(['title' => 'Zz Second Untitled']);
$bar->add_node(['id' => 'zz-old-parent', 'parent' => 'my-account-with-avatar', 'title' => 'Zz Old Parent']);
$bar->add_node(['id' => 'zz-old-blogs', 'parent' => 'my-blogs', 'title' => 'Zz Old Blogs']);
$bar->add_node('zz-top', null, ['id' => 'zz-shim', 'title' => 'Zz Shim']);
$say('the old three-argument call', $node($bar->get_node('zz-shim')));
$bar->remove_node('zz-shim');
add_action('deprecated_function_run', $heard, 10, 3);
ob_start();
$bar->recursive_render('zz-recursive', (object) ['id' => 'zz-recursive', 'title' => 'Zz Recursive', 'href' => false, 'parent' => 'root-default', 'type' => 'item', 'children' => [], 'meta' => []]);
$say('the old recursive render', ob_get_clean());
remove_action('deprecated_function_run', $heard, 10);
$say('what the old shapes raise', $wrong);
$say('the parents the old names became', [$bar->get_node('zz-old-parent')->parent ?? null, $bar->get_node('zz-old-blogs')->parent ?? null]);
remove_action('doing_it_wrong_run', $heard, 10);
remove_action('deprecated_argument_run', $heard, 10);
$bar->remove_node('zz-old-parent');
$bar->remove_node('zz-old-blogs');
$bar->remove_node('zz-zz-second-untitled');
$bar->remove_node('zz-second-untitled');
$fired = [];
ob_start();
$bar->render();
$say('a plugin\'s own nodes rendered', $mask(ob_get_clean()));
$say('what rendering fired', $fired);
$say('the nodes after rendering', $bar->get_nodes() === null ? null : array_keys((array) $bar->get_nodes()));

$admin = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID'])[0] ?? 1);
$hadPref = metadata_exists('user', $admin, 'show_admin_bar_front');
$pref = get_user_meta($admin, 'show_admin_bar_front', true);
wp_set_current_user($admin);
$say('whether the bar shows for an administrator', is_admin_bar_showing());
$fired = [];
$hookedBy = static function (): array {
    $out = [];
    foreach ($GLOBALS['wp_filter'] as $hook => $wpHook) {
        foreach ($wpHook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $out[] = $hook . ' ' . $priority . ' ' . (is_string($callback['function']) ? $callback['function'] : (is_array($callback['function']) ? (is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0]) . '::' . $callback['function'][1] : 'a closure'));
            }
        }
    }
    return $out;
};
$before = $hookedBy();
$bar = new WP_Admin_Bar();
$bar->initialize();
// Building the script registry on first use hooks its own init; that is the registry's, not the bar's.
$say('the hooks initializing adds', array_values(array_filter(array_diff($hookedBy(), $before), static fn ($entry) => !str_starts_with($entry, 'init '))));
$say('what initializing fired', $fired);
$say('what initializing enqueued', [wp_script_is('admin-bar', 'enqueued'), wp_style_is('admin-bar', 'enqueued')]);
$hooked = [];
foreach (['wp_head', 'wp_enqueue_scripts', 'admin_head', 'wp_body_open', 'wp_footer', 'in_admin_header', 'template_redirect', 'admin_init', 'admin_bar_init', 'wp_before_admin_bar_render', 'body_class', 'admin_body_class'] as $hook) {
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_string($callback['function']) && preg_match('/admin_bar|adminbar|customize_support/', $callback['function'])) {
                $hooked[] = [$hook, $priority, $callback['function']];
            }
        }
    }
}
$say('what the bar hooks once initialized', $hooked);
$say('who the bar is for', is_object($bar->user ?? null) ? array_map(static fn ($v) => is_array($v) ? array_map(static fn ($b) => is_object($b) ? array_keys(get_object_vars($b)) : $b, $v) : $v, get_object_vars($bar->user)) : null);
$say('the old properties', [$bar->proto, $bar->menu]);
$say('the update counts for an administrator', [wp_get_update_data(), function_exists('get_core_updates')]);
wp_styles()->add_data('admin-bar', 'after', []);
wp_enqueue_admin_bar_header_styles();
$header = wp_styles()->get_data('admin-bar', 'after');
wp_styles()->add_data('admin-bar', 'after', []);
wp_enqueue_admin_bar_bump_styles();
$say('the styles the bar adds to the head', [$header, wp_styles()->get_data('admin-bar', 'after')]);
wp_styles()->add_data('admin-bar', 'after', []);
wp_enqueue_admin_bar_header_styles();
wp_enqueue_admin_bar_bump_styles();
$say('the styles once their head printers are unhooked', [wp_styles()->get_data('admin-bar', 'after'), has_action('wp_head', 'wp_admin_bar_header'), has_action('wp_head', '_admin_bar_bump_cb'), has_action('admin_head', 'wp_admin_bar_header')]);
add_theme_support('admin-bar', ['callback' => '__return_false']);
$before = $hookedBy();
(new WP_Admin_Bar())->initialize();
$say('the hooks initializing adds for a theme that bumps nothing', array_values(array_filter(array_diff($hookedBy(), $before), static fn ($entry) => !str_starts_with($entry, 'init '))));
wp_styles()->add_data('admin-bar', 'after', []);
wp_enqueue_admin_bar_header_styles();
wp_enqueue_admin_bar_bump_styles();
$say('the styles for a theme that bumps nothing', wp_styles()->get_data('admin-bar', 'after'));
remove_theme_support('admin-bar');
add_theme_support('admin-bar', ['callback' => 'zz_bump']);
$before = $hookedBy();
(new WP_Admin_Bar())->initialize();
$say('the hooks initializing adds for a theme with its own bump', array_values(array_filter(array_diff($hookedBy(), $before), static fn ($entry) => !str_starts_with($entry, 'init '))));
remove_theme_support('admin-bar');
remove_action('wp_head', 'zz_bump');
remove_action('wp_head', 'wp_admin_bar_header');
remove_action('admin_head', 'wp_admin_bar_header');
remove_action('wp_head', '_admin_bar_bump_cb');
wp_styles()->add_data('admin-bar', 'after', []);
ob_start();
wp_customize_support_script();
$say('the customizer support script', ob_get_clean());
// A bar built as a page builds it: WordPress's own menus, then everything on admin_bar_menu; where the site-icons filter is asked, by the node last added.
$asked = [];
$built = null;
add_filter('wp_admin_bar_show_site_icons', static function ($show) use (&$asked, &$built) {
    $nodes = $built?->get_nodes();
    $asked[] = [$show, $nodes ? array_key_last($nodes) : null];
    return $show;
});
$build = static function () use (&$built): WP_Admin_Bar {
    $built = new WP_Admin_Bar();
    $built->add_menus();
    do_action_ref_array('admin_bar_menu', [&$built]);
    return $built;
};
$before = $hookedBy();
(new WP_Admin_Bar())->add_menus();
$say('the menus add_menus hooks', array_values(array_diff($hookedBy(), $before)));
$fired = [];
$bar = $build();
$say('what building fired', $fired);
$say('where the site-icons filter is asked', $asked);
$core = [];
foreach ((array) $bar->get_nodes() as $id => $each) {
    $core[$id] = $node($each);
}
$say('the nodes WordPress adds for an administrator', $core);
ob_start();
$bar->render();
$say('the administrator\'s bar rendered', $mask(ob_get_clean()));
$brief = static function (WP_Admin_Bar $bar, array $only = []) use ($mask): array {
    $out = [];
    foreach ((array) $bar->get_nodes() as $id => $each) {
        if ($only === [] || in_array($id, $only, true) || in_array($each->parent, $only, true)) {
            $out[$id] = [$each->parent, $mask($each->title), $mask($each->href), $each->meta];
        }
    }
    return $out;
};

$iconAsked = [];
add_filter('get_site_icon_url', $icon = static function ($url, $size) use (&$iconAsked) {
    $iconAsked[] = $size;
    return "https://zz.example/icon-{$size}.png";
}, 10, 2);
$say('the site\'s name with a site icon', $brief($build(), ['site-name']));
add_filter('wp_admin_bar_show_site_icons', '__return_false');
$say('the site\'s name with a site icon a plugin hides', $brief($build(), ['site-name'])['site-name'] ?? null);
remove_filter('wp_admin_bar_show_site_icons', '__return_false');
remove_filter('get_site_icon_url', $icon);
$say('the sizes the site icon is asked for', $iconAsked);
add_theme_support('widgets');
$say('the site menu when the theme supports widgets alone', $brief($build(), ['appearance']));
foreach (['menus', 'custom-background', 'custom-header'] as $feature) {
    add_theme_support($feature);
}
$say('the site menu when the theme supports widgets, menus, a background and a header', $brief($build(), ['site-name', 'appearance']));
add_theme_support('editor-style');
$say('taking the theme supports back', array_map(static fn ($feature) => [$feature, remove_theme_support($feature), current_theme_supports($feature)], ['widgets', 'menus', 'custom-background', 'custom-header', 'editor-style']));
$GLOBALS['_wp_current_template_id'] = get_stylesheet() . '//single';
$say('the site editor link with a template on the page', $brief($build(), ['site-editor']));
unset($GLOBALS['_wp_current_template_id']);
add_action('customize_register', '__return_null');
$say('the customizer link once a plugin registers with it', $brief($build(), ['customize']));
remove_action('customize_register', '__return_null');

$GLOBALS['wp_the_query'] = new WP_Query(['s' => 'zz "quoted" <b>']);
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$say('the search box during a search', $brief($build(), ['search']));
$post = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
if ($post) {
    $GLOBALS['wp_the_query'] = new WP_Query(['p' => $post[0]->ID]);
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
    $say('on a single post: the nodes it adds', array_diff_key($brief($build()), $core));
}
$GLOBALS['wp_the_query'] = new WP_Query(['cat' => (int) get_option('default_category')]);
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$say('on a category: the nodes it adds', array_diff_key($brief($build()), $core));
$GLOBALS['wp_the_query'] = new WP_Query(['author' => $admin]);
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$say('on an author\'s archive: the nodes it adds', array_diff_key($brief($build()), $core));
$single = $post ? new WP_Query(['p' => $post[0]->ID]) : null;
if ($single) {
    $GLOBALS['wp_the_query'] = $single;
    $GLOBALS['wp_query'] = $single;
    $bar = new WP_Admin_Bar();
    wp_admin_bar_shortlink_menu($bar);
    $say('the shortlink menu on a single post', $brief($bar));
}
wp_reset_query();
$wrong = [];
add_action('deprecated_function_run', $heard, 10, 3);
$bar = new WP_Admin_Bar();
wp_admin_bar_dashboard_view_site_menu($bar);
$say('the old dashboard menu', [$brief($bar), $wrong]);
remove_action('deprecated_function_run', $heard, 10);
add_filter('show_admin_bar', '__return_true');
$fired = [];
$say('setting the bar up', [_wp_admin_bar_init(), get_class($GLOBALS['wp_admin_bar'] ?? null), $fired]);
remove_filter('show_admin_bar', '__return_true');
unset($GLOBALS['wp_admin_bar'], $GLOBALS['show_admin_bar']);

$GLOBALS['wp_admin_bar'] = $build();
add_filter('show_admin_bar', '__return_true');
$fired = [];
ob_start();
wp_admin_bar_render();
$printed = ob_get_clean();
$say('wp_admin_bar_render when the bar shows', [$fired, str_contains($printed, 'id="wpadminbar"')]);
remove_filter('show_admin_bar', '__return_true');
$fired = [];
ob_start();
wp_admin_bar_render();
$say('wp_admin_bar_render a second time', [$fired, ob_get_clean()]);
unset($GLOBALS['wp_admin_bar']);
$wrong = [];
add_action('deprecated_function_run', $heard, 10, 3);
ob_start();
wp_admin_bar_header();
$header = ob_get_clean();
ob_start();
_admin_bar_bump_cb();
$say('the old head printers', [$header, ob_get_clean(), $wrong]);
remove_action('deprecated_function_run', $heard, 10);

$made = [];
foreach (['editor' => 'Zz Bar Editor', 'subscriber' => ''] as $role => $display) {
    $id = (int) wp_insert_user(array_filter(['user_login' => "zz_bar_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz-bar-{$role}@example.com", 'role' => $role, 'display_name' => $display]));
    $made[] = $id;
    wp_set_current_user($id);
    $nodes = [];
    foreach ((array) $build()->get_nodes() as $nodeId => $each) {
        $nodes[$nodeId] = [$each->parent, $mask($each->href)];
    }
    $say("the nodes WordPress adds for a {$role}", $nodes);
    $say("the update counts for a {$role}", wp_get_update_data());
    if ($display !== '') {
        $grant = static fn ($caps) => ['activate_plugins' => true, 'edit_theme_options' => true] + $caps;
        add_filter('user_has_cap', $grant);
        $say('an editor allowed to activate plugins and edit theme options', $brief($build(), ['site-name', 'appearance', 'site-editor']));
        remove_filter('user_has_cap', $grant);
        $say('the account nodes for a name that is not the login', $brief($build(), ['my-account', 'user-actions']));
    }
}
wp_set_current_user($admin);

add_filter('show_admin_bar', '__return_false');
$say('whether it shows when a plugin says no', is_admin_bar_showing());
remove_filter('show_admin_bar', '__return_false');
$say('and once the plugin is gone', is_admin_bar_showing());
update_user_meta($admin, 'show_admin_bar_front', 'false');
$say('the preference turned off', [_get_admin_bar_pref(), _get_admin_bar_pref('front', $admin)]);
update_user_meta($admin, 'show_admin_bar_front', 'true');
$say('the preference turned on', [_get_admin_bar_pref(), _get_admin_bar_pref('front', $admin)]);
$handed = [];
$hear = static function ($show) use (&$handed) {
    $handed[] = $show;
    return $show;
};
add_filter('show_admin_bar', $hear);
show_admin_bar(true);
is_admin_bar_showing();
show_admin_bar(false);
is_admin_bar_showing();
remove_filter('show_admin_bar', $hear);
$say('what show_admin_bar hands the filter', $handed);
unset($GLOBALS['show_admin_bar']);
update_user_meta($admin, 'show_admin_bar_front', 'false');
$say('whether it shows for someone who turned it off', is_admin_bar_showing());
unset($GLOBALS['show_admin_bar']);
wp_set_current_user(0);
$say('whether the bar shows for a visitor', is_admin_bar_showing());
unset($GLOBALS['show_admin_bar']);
if ($hadPref) {
    update_user_meta($admin, 'show_admin_bar_front', $pref);
} else {
    delete_user_meta($admin, 'show_admin_bar_front');
}
if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
foreach ($made as $id) {
    wp_delete_user($id);
}
// Once the page has opened its body the skip link is gone; a phone gets its own class.
ob_start();
do_action('wp_body_open');
ob_end_clean();
add_filter('wp_is_mobile', '__return_true');
$bar = new WP_Admin_Bar();
$bar->add_node(['id' => 'zz-last', 'title' => 'Zz Last']);
ob_start();
$bar->render();
$say('a bar rendered after the body opened, on a phone', ob_get_clean());
remove_filter('wp_is_mobile', '__return_true');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
