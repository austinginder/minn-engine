<?php
/**
 * The admin facade's doubts settled (probe admin-doubts), each as the
 * reference does it: top-level menu entries (a taken position moved on by a
 * fraction, the generic icon's class, non-numeric positions refused),
 * submenus under submenus, plugin page hooks (".php" taken out), page
 * addresses and parents, super admins, meta boxes filed, moved, removed and
 * printed, the current screen's globals and sanitized ids, saved settings
 * errors and the update flag, submit buttons, the code editor's settings,
 * dashboard widgets, retired settings pages, the filesystem's class file,
 * privacy policy text, the bare admin documents and screen options a save
 * refuses. Admin globals are put back after each part; the probe's posts,
 * terms and user options (zzadm) are removed.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/dashboard.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
function zzadm_doubts_content(): void
{
    echo 'ZZCONTENT';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

(static function () use ($say): void {
$prev = get_current_user_id();
wp_set_current_user(1);
$keep = [];
foreach (['menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', 'parent_file', 'plugin_page', 'pagenow', '_wp_real_parent_file', 'wp_meta_boxes', 'current_screen', 'typenow', 'taxnow'] as $g) {
    $keep[$g] = $GLOBALS[$g] ?? null;
}
$GLOBALS['menu'] = [2 => ['Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard'], 5 => ['Posts', 'edit_posts', 'edit.php', '', 'menu-top', 'menu-posts', 'dashicons-admin-post'], 80 => ['Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', 'dashicons-admin-settings']];
$GLOBALS['submenu'] = ['options-general.php' => [10 => ['General', 'manage_options', 'options-general.php']], 'edit.php' => [5 => ['All Posts', 'edit_posts', 'edit.php']]];
$GLOBALS['admin_page_hooks'] = ['index.php' => 'dashboard', 'edit.php' => 'posts', 'options-general.php' => 'settings', 'tools.php' => 'tools'];
$GLOBALS['_registered_pages'] = [];
$GLOBALS['_parent_pages'] = [];
$GLOBALS['_wp_real_parent_file'] = [];
try {
    $h1 = add_menu_page('ZZ Top', 'ZZ Top', 'manage_options', 'zzadm-top', '__return_null', '', 5);
    $h2 = add_menu_page('ZZ Icon', 'ZZ Icon', 'manage_options', 'zzadm-icon', '__return_null', 'https://example.com/i.png');
    $h3 = add_menu_page('ZZ Dash', 'ZZ Dash', 'manage_options', 'zzadm-dash', '__return_null', 'dashicons-admin-generic');
    $keys = array_map('strval', array_keys($GLOBALS['menu']));
    $rows = array_map(static fn ($r) => [$r[2], $r[4], $r[6] ?? null], array_values($GLOBALS['menu']));
    $say('add_menu_page', [$h1, $h2, $h3, $keys, $rows, $GLOBALS['_parent_pages'], array_keys($GLOBALS['_registered_pages'])]);
    $s1 = add_submenu_page('zzadm-top', 'ZZ Sub', 'ZZ Sub', 'manage_options', 'zzadm-sub', '__return_null');
    $s2 = add_submenu_page('zzadm-sub', 'ZZ SubSub', 'ZZ SubSub', 'manage_options', 'zzadm-subsub', '__return_null');
    $s3 = @add_submenu_page('options-general.php', 'ZZ Opt', 'ZZ Opt', 'manage_options', 'zzadm-opt', '__return_null', 'abc');
    $s4 = add_submenu_page('zzadm/page.php', 'ZZ File', 'ZZ File', 'manage_options', 'zzadm/page.php', '__return_null');
    $s5 = add_submenu_page('edit.php', 'ZZ Edit', 'ZZ Edit', 'manage_options', 'zzadm-edit', '__return_null');
    $s6 = add_submenu_page('tools.php', 'ZZ Tools', 'ZZ Tools', 'manage_options', 'zzadm-tools', '__return_null');
    $sub = [];
    foreach ($GLOBALS['submenu'] as $parent => $items) {
        $sub[$parent] = array_map(static fn ($r) => $r[2], array_values($items));
    }
    $say('add_submenu_page', [$s1, $s2, $s3, $s4, $s5, $s6, $sub, $GLOBALS['_parent_pages']]);
    $say('menu_page_url', [menu_page_url('zzadm-top', false), menu_page_url('zzadm-sub', false), menu_page_url('zzadm-subsub', false), menu_page_url('zzadm-opt', false), menu_page_url('zzadm-tools', false), menu_page_url('nope', false)]);
    $say('get_plugin_page_hookname', [get_plugin_page_hookname('zzadm/page.php', ''), get_plugin_page_hookname('zzadm-x', 'options-general.php'), get_plugin_page_hookname('zzadm-x', 'tools.php'), get_plugin_page_hookname('zzadm-x', 'edit.php'), get_plugin_page_hookname('zzadm-x', 'options.php'), get_plugin_page_hookname('zzadm-x', 'management.php'), get_plugin_page_hookname('zzadm-x', 'edit.php?post_type=page'), get_plugin_page_hookname('zzadm-x', 'themes.php'), get_plugin_page_hookname('zzadm-x', 'zzadm-top')]);
    $GLOBALS['parent_file'] = null;
    $GLOBALS['pagenow'] = 'admin.php';
    $GLOBALS['plugin_page'] = 'zzadm-sub';
    $gp = [get_admin_page_parent(), $GLOBALS['parent_file'] ?? null];
    $GLOBALS['plugin_page'] = 'zzadm-top';
    $GLOBALS['parent_file'] = null;
    $gp[] = get_admin_page_parent();
    $gp[] = get_admin_page_parent('zzadm-sub');
    $GLOBALS['_wp_real_parent_file'] = ['zzadm-fake' => 'zzadm-real'];
    $gp[] = get_admin_page_parent('zzadm-fake');
    $GLOBALS['plugin_page'] = null;
    $GLOBALS['parent_file'] = null;
    $GLOBALS['pagenow'] = 'edit.php';
    $gp[] = get_admin_page_parent();
    $say('get_admin_page_parent', $gp);
    $say('is_super_admin', [is_super_admin(), is_super_admin(0), is_super_admin(false), is_super_admin(1), is_super_admin(999999)]);
    // Meta boxes.
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-a', 'A', '__return_null', 'zzadm-screen', 'normal', 'default');
    remove_meta_box('zzadm-a', 'zzadm-screen', 'normal');
    add_meta_box('zzadm-a', 'A', '__return_null', 'zzadm-screen', 'normal', 'core');
    add_meta_box('zzadm-b', 'B', '__return_null', 'zzadm-screen', 'normal', 'default');
    add_meta_box('zzadm-b', 'B2', '__return_null', 'zzadm-screen', 'side', 'high');
    $GLOBALS['current_screen'] = null;
    add_meta_box('zzadm-c', 'C', '__return_null', null, 'normal');
    $mb1 = $GLOBALS['wp_meta_boxes'];
    set_current_screen('zzadm-cur');
    add_meta_box('zzadm-d', 'D', '__return_null', null, 'normal');
    add_meta_box('zzadm-e', 'E', '__return_null', 'zzadm-cur', 'advanced');
    remove_meta_box('zzadm-e', '', 'advanced');
    $say('meta boxes', [$mb1, $GLOBALS['wp_meta_boxes']]);
    set_current_screen('edit-post');
    $say('set_current_screen globals', [$GLOBALS['typenow'] ?? 'unset', $GLOBALS['taxnow'] ?? 'unset', get_current_screen()->id]);
    set_current_screen('edit-category');
    $say('set_current_screen taxonomy globals', [$GLOBALS['typenow'] ?? 'unset', $GLOBALS['taxnow'] ?? 'unset', get_current_screen()->id]);
} finally {
    foreach ($keep as $g => $v) {
        $GLOBALS[$g] = $v;
    }
    wp_set_current_user($prev);
}
})();

(static function () use ($say): void {
$prev = get_current_user_id();
wp_set_current_user(1);
$keep = [];
foreach (['menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', 'parent_file', 'plugin_page', 'pagenow', '_wp_real_parent_file', 'wp_meta_boxes', 'current_screen', 'typenow', 'taxnow', 'submenu_file'] as $g) {
    $keep[$g] = $GLOBALS[$g] ?? null;
}
$wrong = [];
$hear = static function ($fn, $msg) use (&$wrong) { $wrong[] = $fn; };
add_action('doing_it_wrong_run', $hear, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
try {
    $GLOBALS['admin_page_hooks'] = ['tools.php' => 'tools', 'options-general.php' => 'settings'];
    $GLOBALS['_parent_pages'] = [];
    $names = [];
    foreach (['a b', 'a.php.x', 'A/B c.php', 'zz?x=1', 'zz.php', 'zz:x', 'zz_x.y', 'zzé', 'a/b/c.php', 'zz-PHP.PHP'] as $n) {
        $names[] = [$n, get_plugin_page_hookname($n, ''), get_plugin_page_hookname($n, 'tools.php')];
    }
    $say('hookname sanitizing', $names);
    // Positions: the fraction for a taken spot.
    $pos = [];
    foreach ([['zzadm-p1', 'P One'], ['zzadm-p1', 'P One'], ['zzadm-p2', 'P One'], ['zzadm-p1', 'P Two'], ['zzadm-p3', 'P3']] as [$slug, $title]) {
        $GLOBALS['menu'] = [5 => ['Posts', 'edit_posts', 'edit.php']];
        add_menu_page($title, $title, 'manage_options', $slug, '', '', 5);
        $k = array_keys($GLOBALS['menu']);
        $pos[] = [$slug, $title, (string) end($k), md5($slug . $title)];
    }
    $GLOBALS['menu'] = [5 => ['Posts', 'edit_posts', 'edit.php'], '5.5' => ['X', 'read', 'x']];
    add_menu_page('P', 'P', 'manage_options', 'zzadm-p4', '', '', '5.5');
    $pos[] = array_map('strval', array_keys($GLOBALS['menu']));
    $GLOBALS['menu'] = [5 => ['Posts', 'edit_posts', 'edit.php']];
    add_menu_page('P', 'P', 'manage_options', 'zzadm-p5', '', '', '7');
    add_menu_page('P', 'P', 'manage_options', 'zzadm-p6', '', '', 3.5);
    add_menu_page('P', 'P', 'manage_options', 'zzadm-p7', '', '', 'abc');
    $pos[] = array_map('strval', array_keys($GLOBALS['menu']));
    $say('menu positions', $pos);
    $say('menu position wrong', $wrong);
    $wrong = [];
    // menu_page_url parents.
    $GLOBALS['menu'] = [];
    $GLOBALS['submenu'] = [];
    $GLOBALS['_parent_pages'] = [];
    add_menu_page('T', 'T', 'manage_options', 'zzadm-t', '');
    add_submenu_page('zzadm-t', 'S', 'S', 'manage_options', 'zzadm-s', '');
    add_submenu_page('zzadm-s', 'SS', 'SS', 'manage_options', 'zzadm-ss', '');
    add_submenu_page('zzadm-unreg', 'U', 'U', 'manage_options', 'zzadm-u', '');
    add_submenu_page('zzadm-unreg.php', 'U2', 'U2', 'manage_options', 'zzadm-u2', '');
    add_submenu_page('edit.php?post_type=page', 'U3', 'U3', 'manage_options', 'zzadm-u3', '');
    add_submenu_page(null, 'U4', 'U4', 'manage_options', 'zzadm-u4', '');
    add_submenu_page('', 'U5', 'U5', 'manage_options', 'zzadm-u5', '');
    $say('menu_page_url parents', [array_map(static fn ($s) => str_replace(admin_url(), '{admin}', menu_page_url($s, false)), ['zzadm-t', 'zzadm-s', 'zzadm-ss', 'zzadm-u', 'zzadm-u2', 'zzadm-u3', 'zzadm-u4', 'zzadm-u5']), $GLOBALS['_parent_pages'], array_keys($GLOBALS['submenu']), $wrong]);
    // Meta boxes re-added.
    $GLOBALS['wp_meta_boxes'] = [];
    $mb = static fn () => $GLOBALS['wp_meta_boxes']['zzadm-s1'] ?? null;
    $rows = [];
    remove_meta_box('zzadm-x', 'zzadm-s1', 'normal');
    $rows['removed first'] = $mb();
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'default');
    $rows['then added default'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    remove_meta_box('zzadm-x', 'zzadm-s1', 'normal');
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'high');
    $rows['then added high'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    remove_meta_box('zzadm-x', 'zzadm-s1', 'normal');
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'side', 'core');
    $rows['then added side core'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'core');
    add_meta_box('zzadm-x', 'X2', '__return_null', 'zzadm-s1', 'normal', 'default');
    $rows['core then default'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'default');
    add_meta_box('zzadm-x', 'X2', '__return_null', 'zzadm-s1', 'normal', 'core');
    $rows['default then core'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'high');
    add_meta_box('zzadm-x', 'X2', '__return_null', 'zzadm-s1', 'normal', 'sorted');
    $rows['high then sorted'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'low');
    add_meta_box('zzadm-x', 'X2', '__return_null', 'zzadm-s1', 'side', 'low');
    add_meta_box('zzadm-x', 'X3', '__return_null', 'zzadm-s1', 'advanced', 'high');
    $rows['three contexts'] = $mb();
    $GLOBALS['wp_meta_boxes'] = [];
    add_meta_box('zzadm-x', 'X', '__return_null', 'zzadm-s1', 'normal', 'bogus');
    add_meta_box('zzadm-y', 'Y', '__return_null', 'zzadm-s1', 'bogus', 'default');
    add_meta_box('zzadm-z', 'Z', '__return_null', 'ZZADM S1', 'normal', 'default');
    $rows['bogus priority and context'] = [$mb(), array_keys($GLOBALS['wp_meta_boxes'])];
    $say('meta boxes re-added', $rows);
    // get_admin_page_parent.
    $GLOBALS['menu'] = [2 => ['Dashboard', 'read', 'index.php'], 5 => ['Posts', 'edit_posts', 'edit.php'], 80 => ['Settings', 'manage_options', 'options-general.php'], 90 => ['T', 'manage_options', 'zzadm-t']];
    $GLOBALS['submenu'] = ['edit.php' => [['All', 'edit_posts', 'edit.php'], ['Add', 'edit_posts', 'post-new.php'], ['Cats', 'manage_categories', 'edit-tags.php?taxonomy=category']], 'options-general.php' => [['General', 'manage_options', 'options-general.php'], ['ZO', 'manage_options', 'zzadm-o']], 'zzadm-t' => [['T', 'manage_options', 'zzadm-t'], ['S', 'manage_options', 'zzadm-s']]];
    $GLOBALS['_wp_real_parent_file'] = [];
    $case = static function ($pagenow, $plugin_page, $parent_file, $arg = '', $typenow = '') {
        $GLOBALS['pagenow'] = $pagenow;
        $GLOBALS['plugin_page'] = $plugin_page;
        $GLOBALS['parent_file'] = $parent_file;
        $GLOBALS['typenow'] = $typenow;
        unset($GLOBALS['submenu_file']);
        $r = get_admin_page_parent($arg);
        return [$r, $GLOBALS['parent_file'] ?? null, $GLOBALS['submenu_file'] ?? null, $GLOBALS['pagenow']];
    };
    $say('get_admin_page_parent cases', [
        'edit' => $case('edit.php', null, null),
        'post-new' => $case('post-new.php', null, null),
        'tags' => $case('edit-tags.php', null, null),
        'options-general' => $case('options-general.php', null, null),
        'options plugin' => $case('options-general.php', 'zzadm-o', null),
        'admin plugin sub' => $case('admin.php', 'zzadm-s', null),
        'admin plugin top' => $case('admin.php', 'zzadm-t', null),
        'admin plugin unknown' => $case('admin.php', 'zzadm-nope', null),
        'given parent_file' => $case('edit.php', null, 'zzadm-given'),
        'arg admin.php' => $case('edit.php', null, null, 'admin.php'),
        'arg other' => $case('edit.php', null, null, 'zzadm-arg'),
        'unknown page' => $case('zzadm-unknown.php', null, null),
        'index' => $case('index.php', null, null),
    ]);
    $GLOBALS['_wp_real_parent_file'] = ['edit.php' => 'zzadm-real', 'zzadm-given' => 'zzadm-given-real'];
    $say('get_admin_page_parent real parent', [$case('edit.php', null, null), $case('edit.php', null, 'zzadm-given'), $case('edit.php', null, null, 'edit.php')]);
} finally {
    remove_action('doing_it_wrong_run', $hear, 10);
    remove_filter('doing_it_wrong_trigger_error', '__return_false');
    foreach ($keep as $g => $v) {
        $GLOBALS[$g] = $v;
    }
    wp_set_current_user($prev);
}
})();

(static function () use ($say): void {
$prev = get_current_user_id();
wp_set_current_user(1);
$keep = [];
foreach (['menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', 'parent_file', 'plugin_page', 'pagenow', '_wp_real_parent_file', 'wp_meta_boxes', 'current_screen', 'typenow', 'taxnow', 'wp_settings_errors', 'wp_dashboard_control_callbacks', 'wp_settings_sections'] as $g) {
    $keep[$g] = $GLOBALS[$g] ?? null;
}
$getKeep = $_GET;
$wrong = [];
$hear = static function ($fn, $msg, $ver) use (&$wrong) { $wrong[] = [$fn, $msg, $ver]; };
add_action('doing_it_wrong_run', $hear, 10, 3);
$dep = static function ($fn, $msg, $ver) use (&$wrong) { $wrong[] = ['deprecated', $fn, $msg, $ver]; };
add_action('deprecated_argument_run', $dep, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_argument_trigger_error', '__return_false');
$made = [];
try {
    $GLOBALS['menu'] = [];
    $GLOBALS['submenu'] = [];
    $GLOBALS['_parent_pages'] = [];
    $GLOBALS['admin_page_hooks'] = [];
    add_menu_page('P', 'P', 'manage_options', 'zzadm-w1', '', '', 'abc');
    add_submenu_page('zzadm-w1', 'S', 'S', 'manage_options', 'zzadm-w2', '', 'abc');
    add_submenu_page('zzadm-w1', 'S', 'S', 'manage_options', 'zzadm-w3', '', '1');
    $say('positions refused', [$wrong, array_map(static fn ($r) => $r[2], $GLOBALS['submenu']['zzadm-w1'] ?? [])]);
    $wrong = [];
    // More parents.
    $GLOBALS['menu'] = [2 => ['Dashboard', 'read', 'index.php'], 5 => ['Posts', 'edit_posts', 'edit.php'], 20 => ['Pages', 'edit_pages', 'edit.php?post_type=page'], 90 => ['T', 'manage_options', 'zzadm-t'], 91 => ['Top file', 'manage_options', 'zzadm-file.php']];
    $GLOBALS['submenu'] = ['edit.php' => [['All', 'edit_posts', 'edit.php'], ['Cats', 'manage_categories', 'edit-tags.php?taxonomy=category'], ['Tags', 'manage_categories', 'edit-tags.php?taxonomy=post_tag']], 'edit.php?post_type=page' => [['All Pages', 'edit_pages', 'edit.php?post_type=page'], ['Add', 'edit_pages', 'post-new.php?post_type=page']], 'zzadm-t' => [['T', 'manage_options', 'zzadm-t'], ['S', 'manage_options', 'zzadm-s']]];
    $GLOBALS['_wp_real_parent_file'] = [];
    $case = static function ($pagenow, $plugin_page, $typenow = '', $taxnow = '') {
        $GLOBALS['pagenow'] = $pagenow;
        $GLOBALS['plugin_page'] = $plugin_page;
        $GLOBALS['parent_file'] = null;
        $GLOBALS['typenow'] = $typenow;
        $GLOBALS['taxnow'] = $taxnow;
        unset($GLOBALS['submenu_file']);
        return [get_admin_page_parent(), $GLOBALS['parent_file'] ?? null, $GLOBALS['submenu_file'] ?? null];
    };
    $say('get_admin_page_parent more', [
        'tags with taxnow' => $case('edit-tags.php', null, 'post', 'category'),
        'tags no typenow' => $case('edit-tags.php', null, '', 'post_tag'),
        'pages list' => $case('edit.php', null, 'page'),
        'page new' => $case('post-new.php', null, 'page'),
        'index top only' => $case('index.php', null),
        'file top' => $case('zzadm-file.php', null),
        'plugin page on file' => $case('zzadm-file.php', 'zzadm-s'),
        'plugin page top no sub' => $case('admin.php', 'zzadm-file.php'),
        'edit typenow post' => $case('edit.php', null, 'post'),
    ]);
    $GLOBALS['_wp_real_parent_file'] = ['zzadm-t' => 'zzadm-real-t'];
    $say('get_admin_page_parent real for plugin', [$case('admin.php', 'zzadm-s'), get_admin_page_parent('zzadm-t'), get_admin_page_parent('admin.php')]);
    // Settings errors and the update flag.
    $GLOBALS['wp_settings_errors'] = [];
    set_transient('settings_errors', [['setting' => 'general', 'code' => 'zzadm', 'message' => 'Saved zz', 'type' => 'updated']], 30);
    $_GET = [];
    $a = count(get_settings_errors());
    $b = get_transient('settings_errors') !== false;
    $_GET['settings-updated'] = 'true';
    $c = count(get_settings_errors());
    $d = count(get_settings_errors());
    $e = get_transient('settings_errors') !== false;
    $say('get_settings_errors and the flag', [$a, $b, $c, $d, $e, count($GLOBALS['wp_settings_errors'] ?? [])]);
    delete_transient('settings_errors');
    $GLOBALS['wp_settings_errors'] = [];
    add_settings_error('general', 'zzadm-e', 'ZZ error', 'error');
    ob_start();
    settings_errors('', false, true);
    $h1 = ob_get_clean();
    unset($_GET['settings-updated']);
    ob_start();
    settings_errors('', false, true);
    $h2 = ob_get_clean();
    $say('settings_errors hidden on update', [$h1, $h2]);
    $_GET = $getKeep;
    // Submit buttons.
    $gt = static fn ($t, $text) => $text === 'Save Changes' ? 'ZZ Save' : $t;
    add_filter('gettext', $gt, 10, 2);
    $say('get_submit_button', [get_submit_button(), get_submit_button('0'), get_submit_button(null), get_submit_button(''), get_submit_button('Go', 'secondary', 'zz', false, ['id' => 'zzid', 'data-x' => '1'])]);
    remove_filter('gettext', $gt, 10);
    // Code editor settings.
    $ce = [];
    foreach ([['type' => 'text/css'], ['file' => 'a.js'], ['file' => 'a.php'], ['file' => 'a.json'], ['file' => 'a.svg'], ['file' => 'a.xml'], ['file' => 'a.md'], ['file' => 'a.sh'], ['file' => 'a.sql'], ['file' => 'a.scss'], ['file' => 'a.less'], ['file' => 'a.html'], ['type' => 'application/javascript'], ['type' => 'text/x-php'], ['file' => 'a.txt'], ['type' => 'text/css', 'codemirror' => ['indentUnit' => 2, 'zz' => 1], 'csslint' => ['zz' => true]], []] as $args) {
        $s = wp_get_code_editor_settings($args);
        $ce[] = [$args, $s === false ? false : [$s['codemirror']['mode'] ?? null, array_keys($s), array_diff_key($s['codemirror'], array_flip(['indentUnit', 'indentWithTabs', 'inputStyle', 'lineNumbers', 'lineWrapping', 'styleActiveLine', 'continueComments', 'extraKeys', 'direction', 'gutters'])), $s['codemirror']['indentUnit'] ?? null, $s['csslint']['zz'] ?? null]];
    }
    $say('wp_get_code_editor_settings', $ce);
    // Dashboard widgets.
    set_current_screen('dashboard');
    $GLOBALS['wp_meta_boxes'] = [];
    $GLOBALS['wp_dashboard_control_callbacks'] = [];
    $_GET = [];
    wp_add_dashboard_widget('zzadm_dw', 'ZZ Widget', '__return_null', '__return_null', ['zz' => 1]);
    wp_add_dashboard_widget('dashboard_quick_press', 'Quick', '__return_null');
    wp_add_dashboard_widget('zzadm_side', 'Side', '__return_null', null, null, 'side', 'high');
    $_GET['edit'] = 'zzadm_dw';
    wp_add_dashboard_widget('zzadm_dw', 'ZZ Widget', '__return_null', '__return_null', ['zz' => 1]);
    $mb = $GLOBALS['wp_meta_boxes'];
    array_walk_recursive($mb, static function (&$v) { if ($v instanceof Closure) { $v = 'closure'; } });
    $say('wp_add_dashboard_widget', [$mb, array_keys($GLOBALS['wp_dashboard_control_callbacks'] ?? [])]);
    $_GET = $getKeep;
    // Settings sections on retired pages.
    $GLOBALS['wp_settings_sections'] = [];
    add_settings_section('zzadm-sec', 'S', '__return_null', 'misc');
    add_settings_section('zzadm-sec2', 'S', '__return_null', 'privacy');
    $say('add_settings_section retired pages', [array_keys($GLOBALS['wp_settings_sections']), $wrong]);
    $wrong = [];
    // The filesystem's class file is asked for.
    $asked = [];
    $mf = static fn () => 'zzadm';
    $ff = static function ($path, $method) use (&$asked) { $asked[] = [basename((string) $path), $method]; return $path; };
    add_filter('filesystem_method', $mf, 99);
    add_filter('filesystem_method_file', $ff, 10, 2);
    $fs = WP_Filesystem();
    remove_filter('filesystem_method', $mf, 99);
    remove_filter('filesystem_method_file', $ff, 10);
    $say('WP_Filesystem with an unknown method', [$fs, $asked]);
    // Privacy policy content outside the admin.
    $pp = wp_add_privacy_policy_content('ZZ Plugin', 'ZZ text');
    $say('wp_add_privacy_policy_content outside the admin', [$pp, $wrong]);
    $wrong = [];
    // Admin document head.
    ob_start();
    $ns = 0;
    $nsf = static function () use (&$ns) { $ns++; echo ' zzns="1"'; };
    add_action('admin_xml_ns', $nsf);
    _wp_admin_html_begin();
    remove_action('admin_xml_ns', $nsf);
    $say('_wp_admin_html_begin', [preg_replace('/charset=[^"]*/', 'charset=X', (string) ob_get_clean()), $ns]);
    // Footer hooks.
    $heard = [];
    $hook = static function () use (&$heard) { $heard[] = [current_action(), func_get_args()]; };
    $GLOBALS['hook_suffix'] = 'zzadm-suffix';
    foreach (['admin_footer', 'admin_print_footer_scripts-zzadm-suffix', 'admin_print_footer_scripts-dashboard', 'admin_print_footer_scripts'] as $h) {
        add_action($h, $hook, 10, 5);
    }
    ob_start();
    iframe_footer();
    $foot = ob_get_clean();
    foreach (['admin_footer', 'admin_print_footer_scripts-zzadm-suffix', 'admin_print_footer_scripts-dashboard', 'admin_print_footer_scripts'] as $h) {
        remove_action($h, $hook, 10);
    }
    unset($GLOBALS['hook_suffix']);
    $say('iframe_footer', [$heard, str_contains($foot, 'wp-auth-check'), preg_replace('/\s+/', ' ', strip_tags($foot))]);
} finally {
    remove_action('doing_it_wrong_run', $hear, 10);
    remove_action('deprecated_argument_run', $dep, 10);
    remove_filter('doing_it_wrong_trigger_error', '__return_false');
    remove_filter('deprecated_argument_trigger_error', '__return_false');
    delete_transient('settings_errors');
    $_GET = $getKeep;
    foreach ($keep as $g => $v) {
        $GLOBALS[$g] = $v;
    }
    wp_set_current_user($prev);
}
})();

// The code editor by file and by type, and the caller's settings over the defaults.
(static function () use ($say): void {
    $base = wp_get_code_editor_settings(['type' => 'zz/none']);
    $differs = static fn (array $settings): array => array_udiff_assoc($settings['codemirror'], $base['codemirror'], static fn ($a, $b) => $a === $b ? 0 : 1);
    $base['jshint']['espreeModuleUrl'] = str_replace([set_url_scheme(home_url(), 'http'), set_url_scheme(home_url(), 'https')], '{home}', (string) $base['jshint']['espreeModuleUrl']);
    $say('code editor defaults', $base);
    $rows = [];
    foreach (['css', 'scss', 'less', 'sass', 'js', 'mjs', 'jsx', 'json', 'php', 'phtml', 'svg', 'xml', 'md', 'markdown', 'sh', 'bash', 'sql', 'html', 'htm', 'xhtml', 'txt', 'yml', 'csv', 'diff', 'patch', 'conf', 'pot', 'HTML'] as $ext) {
        $rows[$ext] = $differs(wp_get_code_editor_settings(['file' => 'zz.' . $ext]));
    }
    $say('code editor by file', $rows);
    $rows = [];
    foreach (['text/css', 'text/x-scss', 'text/javascript', 'application/javascript', 'application/ecmascript', 'application/json', 'application/ld+json', 'text/x-php', 'application/x-httpd-php', 'text/html', 'text/xml', 'image/svg+xml', 'application/xhtml+xml', 'text/x-markdown', 'text/x-gfm', 'text/x-sh', 'text/x-sql', 'text/x-mysql', 'text/x-diff', 'text/plain', 'text/x-yaml', 'text/nginx', 'text/x-javascript'] as $type) {
        $rows[$type] = $differs(wp_get_code_editor_settings(['type' => $type]));
    }
    $say('code editor by type', $rows);
    $s = wp_get_code_editor_settings(['type' => 'text/css', 'file' => 'zz.js', 'codemirror' => ['indentUnit' => 2, 'extraKeys' => ['Ctrl-Q' => 'zz']], 'csslint' => ['zz' => true], 'jshint' => ['esversion' => 5], 'htmlhint' => ['zz' => 1]]);
    $say('code editor overrides', [$s['codemirror']['mode'], $s['codemirror']['indentUnit'], $s['codemirror']['extraKeys'], $s['csslint'], $s['jshint']['esversion'], $s['htmlhint']['zz'] ?? null, $differs(wp_get_code_editor_settings([]))]);
})();

// Meta boxes printed: the region, its handles, closed and hidden boxes; dashboard widgets while one is edited.
(static function () use ($say): void {
    wp_set_current_user(1);
    $keep = [$GLOBALS['wp_meta_boxes'] ?? null, $GLOBALS['wp_dashboard_control_callbacks'] ?? null, $GLOBALS['current_screen'] ?? null, $_GET];
    try {
        $GLOBALS['wp_meta_boxes'] = [];
        add_meta_box('zzadm-one', 'One <b>t</b>', static function () { echo 'BODY1'; }, 'zzadm-mb', 'normal', 'high');
        add_meta_box('zzadm-two', 'Two', static function () { echo 'BODY2'; }, 'zzadm-mb', 'normal', 'default');
        add_meta_box('zzadm-three', '', static function () { echo 'BODY3'; }, 'zzadm-mb', 'normal', 'low');
        add_meta_box('zzadm-q', 'Q "quoted" &amp; <i>it</i> <span class="postbox-title-action"><a href="#">Configure</a></span>', '__return_null', 'zzadm-mb', 'side');
        update_user_option(1, 'closedpostboxes_zzadm-mb', ['zzadm-two'], true);
        update_user_option(1, 'metaboxhidden_zzadm-mb', ['zzadm-one'], true);
        $print = static function (string $context): array {
            ob_start();
            $n = do_meta_boxes('zzadm-mb', $context, null);
            return [$n, ob_get_clean()];
        };
        $say('do_meta_boxes', [$print('normal'), $print('side'), $print('advanced')]);
        delete_user_option(1, 'closedpostboxes_zzadm-mb', true);
        delete_user_option(1, 'metaboxhidden_zzadm-mb', true);
        set_current_screen('dashboard');
        $GLOBALS['wp_meta_boxes'] = [];
        $GLOBALS['wp_dashboard_control_callbacks'] = [];
        $_GET = ['edit' => 'zzadm_dw'];
        wp_add_dashboard_widget('zzadm_dw', 'ZZ Widget', '__return_null', '__return_null', ['zz' => 1]);
        $_GET = [];
        foreach (['dashboard_quick_press', 'dashboard_primary', 'dashboard_activity', 'dashboard_php_nag'] as $id) {
            wp_add_dashboard_widget($id, $id, '__return_null');
        }
        set_current_screen('zzadm-other');
        wp_add_dashboard_widget('zzadm_other', 'Other', '__return_null');
        $say('dashboard widgets while one is edited', [$GLOBALS['wp_meta_boxes'], array_keys($GLOBALS['wp_dashboard_control_callbacks'])]);
    } finally {
        [$GLOBALS['wp_meta_boxes'], $GLOBALS['wp_dashboard_control_callbacks'], $GLOBALS['current_screen'], $_GET] = $keep;
    }
})();

// Privacy policy text before admin_init, retired settings fields, the screen options a save refuses.
(static function () use ($say): void {
    $wrong = [];
    $hear = static function ($fn, $msg, $ver) use (&$wrong) { $wrong[] = [$fn, $msg, $ver]; };
    $dep = static function ($fn, $msg, $ver) use (&$wrong) { $wrong[] = ['deprecated', $fn, $msg, $ver]; };
    add_action('doing_it_wrong_run', $hear, 10, 3);
    add_action('deprecated_argument_run', $dep, 10, 3);
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    add_filter('deprecated_argument_trigger_error', '__return_false');
    $keep = [$GLOBALS['current_screen'] ?? null, $GLOBALS['wp_settings_fields'] ?? null, $_POST, $_REQUEST];
    try {
        unset($GLOBALS['current_screen']);
        $outside = [wp_add_privacy_policy_content('ZZ', 'ZZ text'), is_admin()];
        set_current_screen('dashboard');
        $inside = [wp_add_privacy_policy_content('ZZ', 'ZZ text'), is_admin()];
        $say('privacy content before admin_init, outside the admin and in it', [$outside, $inside, $wrong]);
        $wrong = [];
        $GLOBALS['wp_settings_fields'] = [];
        add_settings_field('zzadm-f', 'F', '__return_null', 'misc', 'default');
        add_settings_field('zzadm-f2', 'F', '__return_null', 'privacy', 'zzsec');
        $say('settings fields on retired pages', [$GLOBALS['wp_settings_fields'], $wrong]);
        wp_set_current_user(1);
        $refused = [];
        foreach (['edit_post_per_page' => '1000', 'edit_post_per_page' => 'abc', 'edit_post' => '0', 'zzadm_per_page' => '7', 'zzadm_option' => '9', 'layout_columns' => '2', 'edit_zzcpt_per_page' => '5', 'Bad Key' => '3'] as $option => $value) {
            $heard = [];
            $filters = ['set-screen-option', 'set_screen_option_' . $option];
            $listen = static function (...$a) use (&$heard) { $heard[] = [current_filter(), $a]; return $a[0]; };
            foreach ($filters as $f) {
                add_filter($f, $listen, 10, 3);
            }
            $_POST = ['wp_screen_options' => ['option' => $option, 'value' => $value], 'screenoptionnonce' => wp_create_nonce('screen-options-nonce')];
            $_REQUEST = $_POST;
            set_screen_options();
            foreach ($filters as $f) {
                remove_filter($f, $listen, 10);
            }
            $refused[] = [$option, $value, get_user_meta(1, $option, true), $heard];
        }
        $say('screen options a save refuses', $refused);
    } finally {
        remove_action('doing_it_wrong_run', $hear, 10);
        remove_action('deprecated_argument_run', $dep, 10);
        remove_filter('doing_it_wrong_trigger_error', '__return_false');
        remove_filter('deprecated_argument_trigger_error', '__return_false');
        [$GLOBALS['current_screen'], $GLOBALS['wp_settings_fields'], $_POST, $_REQUEST] = $keep;
    }
})();

// The bare admin documents, the default admin hooks taken off so only their own markup and the hooks' places show.
(static function () use ($say): void {
    wp_set_current_user(1);
    $names = ['admin_enqueue_scripts', 'admin_print_styles', 'admin_print_scripts', 'admin_head', 'admin_print_footer_scripts', 'admin_footer', 'admin_body_class', 'admin_xml_ns', 'admin_print_styles-zzadm-suffix', 'admin_print_scripts-zzadm-suffix', 'admin_head-zzadm-suffix', 'admin_print_styles-media-upload-popup', 'admin_print_scripts-media-upload-popup', 'admin_head-media-upload-popup', 'admin_head_zzadm_doubts_content', 'admin_print_footer_scripts-zzadm-suffix'];
    $saved = [];
    foreach ($names as $h) {
        $saved[$h] = $GLOBALS['wp_filter'][$h] ?? null;
        remove_all_actions($h);
        add_action($h, static function (...$a) use ($h) { echo '[' . $h . ':' . implode(',', array_map('strval', $a)) . ']'; }, 10, 3);
    }
    remove_all_filters('admin_body_class');
    add_filter('admin_body_class', static fn ($c) => $c . ' zzB');
    $keep = [$GLOBALS['hook_suffix'] ?? null, $GLOBALS['current_screen'] ?? null, $GLOBALS['body_id'] ?? null];
    $clean = static fn ($html) => preg_replace('/charset=[^"]*/', 'charset=X', str_replace([admin_url(), set_url_scheme(admin_url(), 'http')], '{admin}', (string) $html));
    try {
        $GLOBALS['hook_suffix'] = 'zzadm-suffix';
        $GLOBALS['body_id'] = 'zzbody';
        set_current_screen('edit-post');
        $printed = static function (callable $print) use ($clean): string {
            ob_start();
            $print();
            return $clean(ob_get_clean());
        };
        $say('_wp_admin_html_begin printed', $printed('_wp_admin_html_begin'));
        $say('iframe_header', $printed(static fn () => iframe_header('ZZ <b>T</b>')));
        $say('iframe_footer printed', $printed('iframe_footer'));
        $say('wp_iframe', $printed(static fn () => wp_iframe('zzadm_doubts_content')));
    } finally {
        [$GLOBALS['hook_suffix'], $GLOBALS['current_screen'], $GLOBALS['body_id']] = $keep;
        foreach ($saved as $h => $hook) {
            remove_all_actions($h);
            if ($hook !== null) {
                $GLOBALS['wp_filter'][$h] = $hook;
            }
        }
    }
})();

// Quick edit's tags come through get_terms_to_edit and its filter.
(static function () use ($say): void {
    wp_set_current_user(1);
    $id = wp_insert_post(['post_title' => 'zzadm inline', 'post_status' => 'draft', 'post_content' => 'x', 'tags_input' => ['zzadm-b', 'zzadm-a'], 'post_category' => [1]]);
    $f = static fn ($terms, $taxonomy) => $terms . ($taxonomy === 'post_tag' ? ',zzadm-filtered' : '');
    try {
        if (!is_int($id) || $id <= 0) {
            throw new RuntimeException('no post');
        }
        add_filter('terms_to_edit', $f, 10, 2);
        ob_start();
        get_inline_data(get_post($id));
        $html = (string) ob_get_clean();
        $say('quick edit tags', preg_replace('/^.*(<div class="tags_input"[^<]*<\/div>).*$/s', '$1', str_replace((string) $id, '{id}', $html)));
    } finally {
        remove_filter('terms_to_edit', $f, 10);
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
        foreach (['zzadm-b', 'zzadm-a'] as $name) {
            $term = get_term_by('name', $name, 'post_tag');
            if ($term) {
                wp_delete_term($term->term_id, 'post_tag');
            }
        }
    }
})();

// The session-expired dialog: hooked for admin pages, for a signed-in user or in the admin, never on the update screens.
(static function () use ($say): void {
    $prevUser = get_current_user_id();
    $keep = $GLOBALS['current_screen'] ?? null;
    $reset = static function (): void {
        remove_action('admin_print_footer_scripts', 'wp_auth_check_html', 5);
        remove_action('wp_print_footer_scripts', 'wp_auth_check_html', 5);
        wp_dequeue_script('wp-auth-check');
        wp_dequeue_style('wp-auth-check');
    };
    $seen = [];
    $listen = static function ($show, $screen = null) use (&$seen) { $seen[] = [$show, $screen instanceof WP_Screen ? $screen->id : gettype($screen)]; return $show; };
    add_filter('wp_auth_check_load', $listen, 10, 2);
    $rows = ['hooked' => has_action('admin_enqueue_scripts', 'wp_auth_check_load')];
    try {
        foreach ([[0, null], [0, 'dashboard'], [1, null], [1, 'dashboard'], [1, 'update'], [1, 'update-network'], [1, 'update-core'], [1, 'customize']] as [$user, $screen]) {
            $reset();
            $seen = [];
            wp_set_current_user($user);
            if ($screen === null) {
                unset($GLOBALS['current_screen']);
            } else {
                set_current_screen($screen);
            }
            wp_auth_check_load();
            $rows["user {$user}, screen " . ($screen ?? 'none')] = [has_action('admin_print_footer_scripts', 'wp_auth_check_html'), has_action('wp_print_footer_scripts', 'wp_auth_check_html'), wp_script_is('wp-auth-check', 'enqueued'), wp_style_is('wp-auth-check', 'enqueued'), $seen, is_admin()];
        }
    } finally {
        $reset();
        remove_filter('wp_auth_check_load', $listen, 10);
        $GLOBALS['current_screen'] = $keep;
        wp_set_current_user($prevUser);
    }
    $say('wp_auth_check_load', $rows);
})();

// script_concat_settings: the site option read every call, a set global kept, compression from the option.
(static function () use ($say): void {
    $names = ['concatenate_scripts', 'compress_scripts', 'compress_css'];
    $keep = array_map(static fn (string $name) => $GLOBALS[$name] ?? null, $names);
    $kept = get_site_option('can_compress_scripts', null);
    $reads = 0;
    $count = static function ($value) use (&$reads) {
        $reads++;
        return $value;
    };
    add_filter('pre_site_option_can_compress_scripts', $count);
    $rows = [];
    try {
        foreach ([[null, null], [null, [true, true, true]], [null, [true, null, null]], [1, null], ['0', null], ['yes', [false, null, false]]] as [$option, $preset]) {
            $option === null ? delete_site_option('can_compress_scripts') : update_site_option('can_compress_scripts', $option);
            foreach ($names as $i => $name) {
                unset($GLOBALS[$name]);
                if ($preset !== null && $preset[$i] !== null) {
                    $GLOBALS[$name] = $preset[$i];
                }
            }
            $reads = 0;
            script_concat_settings();
            $rows[json_encode([$option, $preset])] = [$reads, array_map(static fn (string $name) => $GLOBALS[$name] ?? 'unset', $names)];
        }
    } finally {
        remove_filter('pre_site_option_can_compress_scripts', $count);
        $kept === null ? delete_site_option('can_compress_scripts') : update_site_option('can_compress_scripts', $kept);
        foreach ($names as $i => $name) {
            unset($GLOBALS[$name]);
            if ($keep[$i] !== null) {
                $GLOBALS[$name] = $keep[$i];
            }
        }
    }
    $say('script_concat_settings', $rows);
})();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
