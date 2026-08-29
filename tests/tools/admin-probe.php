<?php
/**
 * Behaviour probe for the admin-side registration functions plugins call
 * at load time (menus, settings API, meta boxes, notices, screens) whose
 * output can be observed from the command line. Same protocol as
 * api-probe.php: one JSON transcript.
 */

if (defined('ABSPATH') && is_file(ABSPATH . 'wp-admin/includes/dashboard.php')) {
    require_once ABSPATH . 'wp-admin/includes/dashboard.php';
}
// The reference hooks its emoji styles into wp_print_styles; the engine carries no emoji plumbing.
remove_action('wp_print_styles', 'print_emoji_styles');
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$out = static function (callable $fn): string { ob_start(); $fn(); return (string) ob_get_clean(); };
$mask = static fn (string $html) => preg_replace('/value="[a-f0-9]{10}"/', 'value="N"', $html);

$say('settings_fields', $mask($out(static fn () => settings_fields('minn_group'))));
$say('wp_nonce_field named', $mask($out(static fn () => wp_nonce_field('act', 'nm'))));
$say('get_submit_button default', get_submit_button());
$say('get_submit_button text type', get_submit_button('Save it', 'secondary large', 'my_name', false, ['id' => 'x', 'data-a' => 'b']));
$say('get_submit_button delete', get_submit_button('Go', 'delete', 'submit', true, 'onclick="x"'));
$say('submit_button echo', $out(static fn () => submit_button('Hi', 'primary', 'submit', true)));
$say('get_submit_button array type', get_submit_button('T', ['primary', 'large']));

add_settings_section('minn_sec', 'Section title', static function ($args) { echo '<p>intro ' . $args['id'] . '</p>'; }, 'minn_page', ['before_section' => '<div class="wrap-%s">', 'after_section' => '</div>', 'section_class' => 'sec-class']);
add_settings_section('minn_sec2', '', '__return_null', 'minn_page');
add_settings_field('minn_field', 'Field label', static function ($args) { echo '<input name="f" value="' . esc_attr($args['label_for']) . '">'; }, 'minn_page', 'minn_sec', ['label_for' => 'f_id', 'class' => 'row-class']);
add_settings_field('minn_field2', 'Second', static function () { echo 'x'; }, 'minn_page', 'minn_sec');
add_settings_field('minn_field3', 'Third', static function () { echo 'y'; }, 'minn_page', 'minn_sec2');
$say('do_settings_sections', $out(static fn () => do_settings_sections('minn_page')));
$say('do_settings_fields', $out(static fn () => do_settings_fields('minn_page', 'minn_sec')));
$say('do_settings_sections unknown', $out(static fn () => do_settings_sections('nope')));
$say('wp_settings_sections global', array_keys($GLOBALS['wp_settings_sections']['minn_page']));
$say('wp_settings_fields global', array_keys($GLOBALS['wp_settings_fields']['minn_page']['minn_sec']));

add_settings_error('minn_opt', 'code1', 'Message one');
add_settings_error('minn_opt', 'code2', 'Saved!', 'success');
add_settings_error('minn_opt', 'code3', 'Careful', 'warning');
add_settings_error('minn_opt', 'code4', 'Note', 'info');
add_settings_error('minn_opt', 'code5', 'Legacy', 'updated');
$say('get_settings_errors', get_settings_errors());
$say('get_settings_errors setting', count(get_settings_errors('minn_opt')));
$say('get_settings_errors other', get_settings_errors('other'));
$say('settings_errors', $out(static fn () => settings_errors()));
$say('settings_errors hide', $out(static fn () => settings_errors('minn_opt', false, true)));
$say('settings_errors again', $out(static fn () => settings_errors('minn_opt')));

$say('add_menu_page', add_menu_page('Page T', 'Menu T', 'manage_options', 'minn-top', '__return_null', 'dashicons-admin-generic', 81));
$say('add_submenu_page', add_submenu_page('minn-top', 'Sub T', 'Sub M', 'manage_options', 'minn-sub', '__return_null'));
$say('add_submenu_page cap denied', add_submenu_page('minn-top', 'Sub T', 'Sub M', 'nonexistent_cap_xyz', 'minn-sub2', '__return_null'));
$say('add_options_page', add_options_page('Opt T', 'Opt M', 'manage_options', 'minn-opt', '__return_null'));
$say('add_theme_page', add_theme_page('Theme T', 'Theme M', 'edit_theme_options', 'minn-theme', '__return_null', 3));
$say('add_management_page', add_management_page('Tool T', 'Tool M', 'manage_options', 'minn-tool', '__return_null'));
$say('add_plugins_page', add_plugins_page('P', 'P', 'activate_plugins', 'minn-plug', '__return_null'));
$say('add_users_page', add_users_page('U', 'U', 'list_users', 'minn-users', '__return_null'));
$say('add_dashboard_page', add_dashboard_page('D', 'D', 'read', 'minn-dash', '__return_null'));
$say('add_posts_page', add_posts_page('Po', 'Po', 'edit_posts', 'minn-posts', '__return_null'));
$say('add_media_page', add_media_page('Me', 'Me', 'upload_files', 'minn-media', '__return_null'));
$say('add_pages_page', add_pages_page('Pa', 'Pa', 'edit_pages', 'minn-pages', '__return_null'));
$say('add_comments_page', add_comments_page('C', 'C', 'edit_posts', 'minn-comments', '__return_null'));
$say('menu global row', $GLOBALS['menu'][81] ?? null);
$say('submenu global rows', $GLOBALS['submenu']['minn-top'] ?? null);
$say('submenu options', array_values(array_filter($GLOBALS['submenu']['options-general.php'] ?? [], static fn ($r) => $r[2] === 'minn-opt')));
$say('submenu themes', array_values(array_filter($GLOBALS['submenu']['themes.php'] ?? [], static fn ($r) => $r[2] === 'minn-theme')));
$say('admin_page_hooks', array_intersect_key($GLOBALS['admin_page_hooks'], ['minn-top' => 1]));
$say('get_plugin_page_hookname', get_plugin_page_hookname('minn-sub', 'minn-top'));
$say('get_plugin_page_hookname top', get_plugin_page_hookname('minn-top', ''));
$say('get_plugin_page_hookname options', get_plugin_page_hookname('minn-opt', 'options-general.php'));
$say('get_plugin_page_hook', get_plugin_page_hook('minn-sub', 'minn-top'));
$say('remove_menu_page', remove_menu_page('minn-top'));
$say('remove_menu_page again', remove_menu_page('minn-top'));
$say('remove_submenu_page', remove_submenu_page('options-general.php', 'minn-opt'));
$say('remove_submenu_page again', remove_submenu_page('options-general.php', 'minn-opt'));
$say('menu_page_url', menu_page_url('minn-theme', false));
$say('menu_page_url unknown', menu_page_url('nope', false));

$say('get_current_screen', get_current_screen());
$say('add_meta_box', add_meta_box('minn_box', 'Box', '__return_null', 'post', 'side', 'high', ['a' => 1]));
$say('wp_meta_boxes global', $GLOBALS['wp_meta_boxes']['post']['side']['high']['minn_box'] ?? null);
$say('remove_meta_box', remove_meta_box('minn_box', 'post', 'side'));
$say('wp_meta_boxes after remove', $GLOBALS['wp_meta_boxes']['post']['side']['high']['minn_box'] ?? null);
$say('wp_add_dashboard_widget', wp_add_dashboard_widget('minn_widget', 'Widget', '__return_null'));
$say('dashboard widget stored', $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['minn_widget'] ?? null);
$say('deactivate_plugins unknown', deactivate_plugins('nope/nope.php'));
$say('is_plugin_active', is_plugin_active('nope/nope.php'));
$say('current_screen filter', has_action('current_screen'));
$say('wp_dashboard_setup', did_action('wp_dashboard_setup'));
$say('add_filter plugin_action_links', add_filter('plugin_action_links_x/x.php', '__return_empty_array'));
$say('remove_all_actions admin_menu', remove_all_actions('admin_menu'));
$say('__return', [__return_true(), __return_false(), __return_null(), __return_zero(), __return_empty_array(), __return_empty_string()]);
$say('wp_get_code_editor_settings keys', array_keys(wp_get_code_editor_settings(['type' => 'text/css'])));
$say('is_super_admin', [is_super_admin(1), is_super_admin(2), is_super_admin(0)]);
$say('get_admin_page_title', get_admin_page_title());
$say('get_admin_page_parent', get_admin_page_parent());
$say('wp_admin_css_color count', count($GLOBALS['_wp_admin_css_colors'] ?? []));
$say('get_screen_option', null);
$say('wp_add_inline_script unknown', wp_add_inline_script('nope', 'x'));
$say('wp_register_script twice', [wp_register_script('minn-s', 'https://x/a.js'), wp_register_script('minn-s', 'https://x/b.js')]);
$say('wp_scripts registered src', wp_scripts()->registered['minn-s']->src);
$say('wp_script_is', [wp_script_is('minn-s', 'registered'), wp_script_is('minn-s'), wp_script_is('nope', 'registered')]);
wp_enqueue_script('minn-s');
$say('wp_script_is enqueued', wp_script_is('minn-s'));
wp_add_inline_script('minn-s', 'console.log(1);');
wp_localize_script('minn-s', 'minnObj', ['a' => '1', 'b' => ['c' => 'd&e'], 'n' => 5]);
wp_register_style('minn-c', 'https://x/a.css', [], '2.0', 'screen');
wp_enqueue_style('minn-c');
wp_add_inline_style('minn-c', 'body{color:red}');
wp_enqueue_script('minn-f', 'https://x/f.js', ['minn-s'], null, true);
wp_enqueue_script('minn-d', '/rel/d.js', [], '3', ['strategy' => 'defer']);
wp_enqueue_script('minn-a', 'rel/a.js', [], false, ['strategy' => 'async', 'in_footer' => true]);
$say('wp_print_styles', $out(static fn () => wp_print_styles()));
$say('wp_print_head_scripts', $out(static fn () => wp_print_head_scripts()));
$say('wp_print_footer_scripts', $out(static fn () => wp_print_footer_scripts()));
$say('_wp_footer_scripts', $out(static fn () => _wp_footer_scripts()));
$say('wp_script_is done', [wp_script_is('minn-s', 'done'), wp_script_is('minn-f', 'done')]);
$say('wp_enqueue_code_editor', is_array(wp_enqueue_code_editor(['type' => 'text/css'])) ? 'settings' : false);
$say('code editor enqueued', [wp_script_is('code-editor'), wp_style_is('code-editor'), wp_script_is('wp-codemirror')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
