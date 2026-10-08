<?php
/**
 * The admin-side registrations plugins make at load time: menus, the
 * Settings API, meta boxes, screens, notices. The admin host (a later
 * milestone) renders from what is recorded here; shapes come from
 * contracts/fixtures/api/admin.json.
 */

use Minn\Runtime\Runtime;

/** @internal the hook names of the core parents, as the reference's own menu gives them */
function _minn_admin_page_hooks(): array
{
    return ['index.php' => 'dashboard', 'edit.php' => 'posts', 'upload.php' => 'media', 'edit.php?post_type=page' => 'pages', 'edit-comments.php' => 'comments', 'themes.php' => 'appearance', 'plugins.php' => 'plugins', 'users.php' => 'users', 'tools.php' => 'tools', 'options-general.php' => 'settings'];
}

function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null)
{
    $menu_slug = plugin_basename($menu_slug);
    $GLOBALS['admin_page_hooks'][$menu_slug] = sanitize_title((string) $menu_title);
    $hookname = get_plugin_page_hookname($menu_slug, '');
    if ($callback !== '' && is_callable($callback) && current_user_can($capability)) {
        add_action($hookname, $callback);
    }
    if ($icon_url === '') {
        $icon_url = 'dashicons-admin-generic';
        $icon_class = '';
    } else {
        $icon_class = str_starts_with((string) $icon_url, 'dashicons-') ? '' : 'menu-icon-generic ';
    }
    $row = [$menu_title, $capability, $menu_slug, $page_title, 'menu-top ' . $icon_class . $hookname, $hookname, $icon_url];
    $menu = &$GLOBALS['menu'];
    $menu = is_array($menu) ? $menu : [];
    if ($position === null || !is_numeric($position)) {
        $menu[] = $row;
    } else {
        $position = (int) $position;
        while (isset($menu[$position])) {
            $position++;
        }
        $menu[$position] = $row;
        ksort($menu);
    }
    $GLOBALS['_registered_pages'][$hookname] = true;
    return $hookname;
}

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    $menu_slug = plugin_basename($menu_slug);
    $parent_slug = plugin_basename($parent_slug);
    $parentFile = $GLOBALS['_parent_pages'][$parent_slug] ?? $parent_slug;
    if (!current_user_can($capability)) {
        $GLOBALS['_wp_submenu_nopriv'][$parentFile][$menu_slug] = true;
        return false;
    }
    $submenu = &$GLOBALS['submenu'];
    $submenu = is_array($submenu) ? $submenu : [];
    if (!isset($submenu[$parentFile]) && $menu_slug !== $parentFile) {
        foreach ((array) ($GLOBALS['menu'] ?? []) as $row) {
            if ($row[2] === $parentFile && current_user_can($row[1])) {
                $submenu[$parentFile][] = array_slice($row, 0, 4);
            }
        }
    }
    $row = [$menu_title, $capability, $menu_slug, $page_title];
    if ($position === null || !is_numeric($position)) {
        $submenu[$parentFile][] = $row;
    } else {
        $position = (int) $position;
        $list = $submenu[$parentFile] ?? [];
        array_splice($list, max(0, min($position, count($list))), 0, [$row]);
        $submenu[$parentFile] = $list;
    }
    $hookname = get_plugin_page_hookname($menu_slug, $parentFile);
    if ($callback !== '' && is_callable($callback)) {
        add_action($hookname, $callback);
    }
    $GLOBALS['_registered_pages'][$hookname] = true;
    if ($parentFile === 'admin.php' && !isset($GLOBALS['admin_page_hooks'][$menu_slug])) {
        $GLOBALS['admin_page_hooks'][$menu_slug] = sanitize_title((string) $menu_title);
    }
    $GLOBALS['_parent_pages'][$menu_slug] = $parentFile;
    return $hookname;
}

function add_management_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('tools.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_theme_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('themes.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_plugins_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('plugins.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_users_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page(current_user_can('edit_users') ? 'users.php' : 'profile.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_dashboard_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('index.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_posts_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('edit.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_media_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('upload.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_links_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('link-manager.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_pages_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('edit.php?post_type=page', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function add_comments_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return add_submenu_page('edit-comments.php', $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
}

function remove_menu_page($menu_slug)
{
    foreach ((array) ($GLOBALS['menu'] ?? []) as $i => $row) {
        if ($row[2] === $menu_slug) {
            unset($GLOBALS['menu'][$i]);
            return $row;
        }
    }
    return false;
}

function remove_submenu_page($menu_slug, $submenu_slug)
{
    foreach ((array) ($GLOBALS['submenu'][$menu_slug] ?? []) as $i => $row) {
        if ($row[2] === $submenu_slug) {
            unset($GLOBALS['submenu'][$menu_slug][$i]);
            return $row;
        }
    }
    return false;
}

function menu_page_url($menu_slug, $display = true)
{
    $url = '';
    if (isset($GLOBALS['_parent_pages'][$menu_slug])) {
        $parent = $GLOBALS['_parent_pages'][$menu_slug];
        $url = $parent && $parent !== 'admin.php' ? admin_url(add_query_arg('page', $menu_slug, $parent)) : admin_url('admin.php?page=' . $menu_slug);
        $url = esc_url($url);
    }
    if ($display) {
        echo $url;
    }
    return $url;
}

function get_plugin_page_hookname($plugin_page, $parent_page)
{
    $parent = get_admin_page_parent($parent_page);
    $page_type = 'admin';
    if ($parent === '' || $parent === 'admin.php' || isset($GLOBALS['admin_page_hooks'][$plugin_page])) {
        if (isset($GLOBALS['admin_page_hooks'][$plugin_page])) {
            $page_type = 'toplevel';
        } elseif (isset($GLOBALS['admin_page_hooks'][$parent])) {
            $page_type = $GLOBALS['admin_page_hooks'][$parent];
        }
    } elseif (isset($GLOBALS['admin_page_hooks'][$parent])) {
        $page_type = $GLOBALS['admin_page_hooks'][$parent];
    }
    return $page_type . '_page_' . (string) preg_replace('|[^a-zA-Z0-9_:.]|', '-', (string) $plugin_page);
}

function get_plugin_page_hook($plugin_page, $parent_page)
{
    $hook = get_plugin_page_hookname($plugin_page, $parent_page);
    return has_action($hook) ? $hook : null;
}

function get_admin_page_parent($parent_page = '')
{
    if ($parent_page !== '' && $parent_page !== 'admin.php') {
        return $parent_page;
    }
    return (string) ($GLOBALS['parent_file'] ?? '');
}

function get_admin_page_title()
{
    return $GLOBALS['title'] ?? null;
}

function get_current_screen()
{
    return $GLOBALS['current_screen'] ?? null;
}

function set_current_screen($hook_name = '')
{
    $GLOBALS['current_screen'] = WP_Screen::get($hook_name);
    do_action('current_screen', $GLOBALS['current_screen']);
}

function convert_to_screen($hook_name)
{
    return WP_Screen::get($hook_name);
}

function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null)
{
    $screenId = $screen instanceof WP_Screen ? $screen->id : ($screen === null ? ($GLOBALS['current_screen']->id ?? 'post') : (is_array($screen) ? null : (string) $screen));
    if (is_array($screen)) {
        foreach ($screen as $one) {
            add_meta_box($id, $title, $callback, $one, $context, $priority, $callback_args);
        }
        return;
    }
    $GLOBALS['wp_meta_boxes'][$screenId][$context][$priority][$id] = ['id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $callback_args];
}

function remove_meta_box($id, $screen, $context)
{
    if (is_array($screen)) {
        foreach ($screen as $one) {
            remove_meta_box($id, $one, $context);
        }
        return;
    }
    $screenId = $screen instanceof WP_Screen ? $screen->id : (string) $screen;
    foreach (['high', 'core', 'default', 'low'] as $priority) {
        $GLOBALS['wp_meta_boxes'][$screenId][$context][$priority][$id] = false;
    }
}

function do_meta_boxes($screen, $context, $data_object)
{
    $screenId = $screen instanceof WP_Screen ? $screen->id : (string) $screen;
    $count = 0;
    echo '<div id="' . esc_attr($context) . '-sortables" class="meta-box-sortables">' . "\n";
    foreach (['high', 'core', 'default', 'low'] as $priority) {
        foreach ((array) ($GLOBALS['wp_meta_boxes'][$screenId][$context][$priority] ?? []) as $box) {
            if ($box === false || empty($box['title'])) {
                continue;
            }
            $count++;
            echo '<div id="' . esc_attr($box['id']) . '" class="postbox">' . "\n";
            echo '<div class="postbox-header"><h2 class="hndle">' . $box['title'] . "</h2></div>\n";
            echo '<div class="inside">' . "\n";
            call_user_func($box['callback'], $data_object, $box);
            echo "</div>\n</div>\n";
        }
    }
    echo "</div>\n";
    return $count;
}

function wp_add_dashboard_widget($widget_id, $widget_name, $callback, $control_callback = null, $callback_args = null, $context = 'normal', $priority = 'core')
{
    $screen = get_current_screen();
    if ($screen === null) {
        return;
    }
    add_meta_box($widget_id, $widget_name, $callback, $screen, $context, $priority, $callback_args);
}

function add_settings_section($id, $title, $callback, $page, $args = [])
{
    $defaults = ['id' => $id, 'title' => $title, 'callback' => $callback, 'before_section' => '', 'after_section' => '', 'section_class' => ''];
    $GLOBALS['wp_settings_sections'][$page][$id] = wp_parse_args($args, $defaults);
}

function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = [])
{
    $GLOBALS['wp_settings_fields'][$page][$section][$id] = ['id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args];
}

function do_settings_sections($page)
{
    if (!isset($GLOBALS['wp_settings_sections'][$page])) {
        return;
    }
    foreach ((array) $GLOBALS['wp_settings_sections'][$page] as $section) {
        if ($section['before_section'] !== '') {
            echo $section['section_class'] !== '' ? sprintf($section['before_section'], esc_attr($section['section_class'])) : $section['before_section'];
        }
        if ($section['title']) {
            $count = (int) Runtime::current()->get('settings_section_count', 0) + 1;
            Runtime::current()->set('settings_section_count', $count);
            echo '<h2 id="wp-settings-section-' . esc_attr($section['id']) . '-' . $count . '">' . $section['title'] . "</h2>\n";
        }
        if ($section['callback']) {
            call_user_func($section['callback'], $section);
        }
        if (isset($GLOBALS['wp_settings_fields'][$page][$section['id']])) {
            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section['id']);
            echo '</table>';
        }
        if ($section['after_section'] !== '') {
            echo $section['after_section'];
        }
    }
}

function do_settings_fields($page, $section)
{
    foreach ((array) ($GLOBALS['wp_settings_fields'][$page][$section] ?? []) as $field) {
        $class = '';
        if (!empty($field['args']['class'])) {
            $class = ' class="' . esc_attr($field['args']['class']) . '"';
        }
        echo "<tr{$class}>";
        if (!empty($field['args']['label_for'])) {
            echo '<th scope="row"><label for="' . esc_attr($field['args']['label_for']) . '">' . $field['title'] . '</label></th>';
        } else {
            echo '<th scope="row">' . $field['title'] . '</th>';
        }
        echo '<td>';
        call_user_func($field['callback'], $field['args']);
        echo '</td></tr>';
    }
}

function add_settings_error($setting, $code, $message, $type = 'error')
{
    $GLOBALS['wp_settings_errors'][] = ['setting' => $setting, 'code' => $code, 'message' => $message, 'type' => $type];
}

function get_settings_errors($setting = '', $sanitize = false)
{
    $errors = (array) ($GLOBALS['wp_settings_errors'] ?? []);
    if ($sanitize) {
        sanitize_option($setting, get_option($setting));
    }
    $stored = get_transient('settings_errors');
    if ($stored !== false && is_array($stored)) {
        $errors = array_merge($errors, $stored);
        delete_transient('settings_errors');
    }
    if ($setting === '') {
        return $errors;
    }
    return array_values(array_filter($errors, static fn ($e) => $e['setting'] === $setting));
}

function settings_errors($setting = '', $sanitize = false, $hide_on_update = false)
{
    if ($hide_on_update && !empty(Runtime::current()->request?->query['settings-updated'])) {
        return;
    }
    $errors = get_settings_errors($setting, $sanitize);
    if ($errors === []) {
        return;
    }
    $output = '';
    foreach ($errors as $details) {
        $type = $details['type'];
        if ($type === 'updated') {
            $type = 'success';
        }
        if (in_array($type, ['error', 'success', 'warning', 'info'], true)) {
            $type = 'notice-' . $type;
        }
        $output .= "<div id='setting-error-{$details['code']}' class='notice {$type} settings-error is-dismissible'> \n";
        $output .= "<p><strong>{$details['message']}</strong></p>";
        $output .= "</div> \n";
    }
    echo $output;
}

function settings_fields($option_group)
{
    echo "<input type='hidden' name='option_page' value='" . esc_attr($option_group) . "' />";
    echo '<input type="hidden" name="action" value="update" />';
    wp_nonce_field("{$option_group}-options");
}

function get_submit_button($text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '')
{
    if (!is_array($type)) {
        $type = explode(' ', (string) $type);
    }
    $classes = ['button'];
    foreach ($type as $t) {
        if ($t === 'primary' || $t === 'small' || $t === 'large') {
            $classes[] = 'button-' . $t;
        } elseif ($t !== 'secondary' && $t !== 'button-secondary' && $t !== '') {
            $classes[] = $t;
        }
    }
    $class = implode(' ', array_unique($classes));
    $text = $text === '' || $text === null ? 'Save Changes' : $text;
    $id = $name;
    if (is_array($other_attributes) && isset($other_attributes['id'])) {
        $id = $other_attributes['id'];
        unset($other_attributes['id']);
    }
    $attributes = '';
    if (is_array($other_attributes)) {
        foreach ($other_attributes as $attribute => $value) {
            $attributes .= $attribute . '="' . esc_attr($value) . '" ';
        }
    } elseif (!empty($other_attributes)) {
        $attributes = $other_attributes;
    }
    $button = '<input type="submit" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="' . esc_attr($class);
    $button .= '" value="' . esc_attr($text) . '" ' . $attributes . ' />';
    if ($wrap) {
        $button = '<p class="submit">' . $button . '</p>';
    }
    return $button;
}

function submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = '')
{
    echo get_submit_button($text, $type, $name, $wrap, $other_attributes);
}

function __return_true()
{
    return true;
}

function __return_false()
{
    return false;
}

function __return_null()
{
    return null;
}

function __return_zero()
{
    return 0;
}

function __return_empty_array()
{
    return [];
}

function __return_empty_string()
{
    return '';
}

function is_super_admin($user_id = false)
{
    $user = $user_id === false ? wp_get_current_user() : get_userdata($user_id);
    if (!$user || !$user->exists()) {
        return false;
    }
    return $user->has_cap('delete_users');
}

function grant_super_admin($user_id)
{
    return false;
}

function revoke_super_admin($user_id)
{
    return false;
}

/** Activation as the reference runs it (probe plugin-activation): requirements, the plugin loaded, the actions, the list. */
function activate_plugin($plugin, $redirect = '', $network_wide = false, $silent = false)
{
    $refusal = Minn\Runtime\PluginActivation::activate(plugin_basename(trim((string) $plugin)), $silent ? null : Minn\Runtime\Runtime::hooks());
    return $refusal === null ? null : new WP_Error($refusal->code, $refusal->message, $refusal->data);
}

function activate_plugins($plugins, $redirect = '', $network_wide = false, $silent = false)
{
    $errors = [];
    foreach ((array) $plugins as $plugin) {
        $result = activate_plugin($plugin, $redirect, $network_wide, $silent);
        if (is_wp_error($result)) {
            $errors[$plugin] = $result;
        }
    }
    if ($errors !== []) {
        $error = new WP_Error('plugins_invalid', 'One of the plugins is invalid.', $errors);
        return $error;
    }
    return true;
}

/** Whether a plugin can be uninstalled: it registered an uninstall callback, or ships an uninstall.php. */
function is_uninstallable_plugin($plugin)
{
    $file = plugin_basename($plugin);
    return isset(((array) get_option('uninstall_plugins'))[$file]) || file_exists(WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php');
}

function uninstall_plugin($plugin)
{
    return Minn\Runtime\PluginRemoval::uninstall((string) $plugin);
}

function delete_plugins($plugins, $deprecated = '')
{
    return Minn\Runtime\PluginRemoval::delete(array_values((array) $plugins));
}

function deactivate_plugins($plugins, $silent = false, $network_wide = null)
{
    $files = array_map(static fn ($plugin): string => plugin_basename(trim((string) $plugin)), (array) $plugins);
    Minn\Runtime\PluginActivation::deactivate($files, $silent ? null : Minn\Runtime\Runtime::hooks());
}

function validate_plugin($plugin)
{
    return is_file(WP_PLUGIN_DIR . '/' . $plugin) ? 0 : new WP_Error('plugin_not_found', 'Plugin file does not exist.');
}

/** True when the plugin's header asks for nothing the site lacks; otherwise the refusal activation gives. */
function validate_plugin_requirements($plugin)
{
    $refusal = Minn\Runtime\PluginRequirements::check((string) $plugin);
    return $refusal === null ? true : new WP_Error($refusal->code, $refusal->message, $refusal->data);
}

function validate_active_plugins()
{
    return [];
}

function is_plugin_paused($plugin)
{
    return false;
}

function wp_get_code_editor_settings($args)
{
    $settings = ['codemirror' => ['indentUnit' => 4, 'indentWithTabs' => true, 'inputStyle' => 'contenteditable', 'lineNumbers' => true, 'lineWrapping' => true, 'styleActiveLine' => true, 'continueComments' => true, 'extraKeys' => ['Ctrl-Space' => 'autocomplete', 'Ctrl-/' => 'toggleComment', 'Cmd-/' => 'toggleComment', 'Alt-F' => 'findPersistent', 'Ctrl-F' => 'findPersistent', 'Cmd-F' => 'findPersistent'], 'direction' => 'ltr', 'gutters' => []], 'csslint' => ['errors' => true, 'box-model' => true, 'display-property-grouping' => true, 'duplicate-properties' => true, 'known-properties' => true, 'outline-none' => true], 'jshint' => ['esversion' => 11, 'module' => false, 'boss' => true, 'curly' => true, 'eqeqeq' => true, 'eqnull' => true, 'expr' => true, 'immed' => true, 'noarg' => true, 'nonbsp' => true, 'quotmark' => 'single', 'undef' => true, 'unused' => true, 'browser' => true, 'globals' => ['_' => false, 'Backbone' => false, 'jQuery' => false, 'JSON' => false, 'wp' => false, 'export' => false, 'module' => false, 'require' => false, 'WorkerGlobalScope' => false, 'self' => false, 'OffscreenCanvas' => false, 'Promise' => false]], 'htmlhint' => ['tagname-lowercase' => true, 'attr-lowercase' => true, 'attr-value-double-quotes' => false, 'doctype-first' => false, 'tag-pair' => true, 'spec-char-escape' => true, 'id-unique' => true, 'src-not-empty' => true, 'attr-no-duplication' => true, 'alt-require' => true, 'space-tab-mixed-disabled' => 'tab', 'attr-unsafe-chars' => true]];
    $type = $args['type'] ?? 'text/html';
    $settings['codemirror']['mode'] = $type;
    if ($type === 'text/css') {
        $settings['codemirror'] += ['lint' => false, 'autoCloseBrackets' => true, 'matchBrackets' => true];
    }
    return apply_filters('wp_code_editor_settings', $settings, $args);
}

// Screen options, the dashboard hook, the iframe shell, filesystem, and update helpers.

function add_screen_option($option, $args = [])
{
    $screen = get_current_screen();
    if ($screen) {
        $screen->add_option($option, $args);
    }
}

function get_hidden_columns($screen)
{
    if (is_string($screen)) {
        $screen = convert_to_screen($screen);
    }
    $hidden = get_user_option('manage' . $screen->id . 'columnshidden');
    $use_defaults = !is_array($hidden);
    if ($use_defaults) {
        $hidden = [];
        $hidden = apply_filters('default_hidden_columns', $hidden, $screen);
    }
    return apply_filters('hidden_columns', $hidden, $screen, $use_defaults);
}

function set_screen_options()
{
    if (!isset($_POST['wp_screen_options']) || !is_array($_POST['wp_screen_options'])) {
        return;
    }
    check_admin_referer('screen-options-nonce', 'screenoptionnonce');
    $user = wp_get_current_user();
    if (!$user) {
        return;
    }
    $option = $_POST['wp_screen_options']['option'];
    $value = $_POST['wp_screen_options']['value'];
    if (sanitize_key($option) !== $option) {
        return;
    }
    $value = apply_filters('set_screen_option', false, $option, $value);
    $value = apply_filters("set_screen_option_{$option}", $value, $option, $value);
    if ($value === false) {
        return;
    }
    update_user_meta($user->ID, $option, $value);
    $url = remove_query_arg(['pagenum', 'apage', 'paged'], wp_get_referer());
    if (isset($_POST['mode'])) {
        $url = add_query_arg(['mode' => $_POST['mode']], $url);
    }
    wp_safe_redirect($url);
    exit;
}

function wp_dashboard_setup()
{
    do_action('wp_dashboard_setup');
}

/** The bare admin document an iframe callback prints into. */
function wp_iframe($content_func, ...$args)
{
    _wp_admin_html_begin();
    echo '<title>' . esc_html(get_bloginfo('name')) . ' &rsaquo; ' . esc_html(__('Uploads')) . ' &#8212; ' . esc_html(__('WordPress')) . '</title>' . "\n";
    echo '<style type="text/css">.hidden{display:none}</style>' . "\n";
    do_action('admin_enqueue_scripts', 'media-upload-popup');
    do_action('admin_print_styles-media-upload-popup');
    do_action('admin_print_styles');
    do_action('admin_print_scripts-media-upload-popup');
    do_action('admin_print_scripts');
    do_action('admin_head-media-upload-popup');
    do_action('admin_head');
    if (is_string($content_func)) {
        do_action("admin_head_{$content_func}");
    }
    $body_id_attr = isset($GLOBALS['body_id']) ? ' id="' . esc_attr($GLOBALS['body_id']) . '"' : '';
    echo '</head>' . "\n" . '<body' . $body_id_attr . ' class="wp-core-ui no-js">' . "\n";
    echo "<script type=\"text/javascript\">document.body.className = document.body.className.replace('no-js', 'js');</script>\n";
    call_user_func_array($content_func, $args);
    do_action('admin_print_footer_scripts');
    echo "<script type=\"text/javascript\">if(typeof wpOnload==='function')wpOnload();</script>\n</body>\n</html>\n";
}

function _wp_admin_html_begin()
{
    $admin_html_class = is_admin_bar_showing() ? 'wp-toolbar' : '';
    echo '<!DOCTYPE html>' . "\n" . '<html class="' . esc_attr($admin_html_class) . '"' . get_language_attributes() . '>' . "\n" . '<head>' . "\n" . '<meta http-equiv="Content-Type" content="' . esc_attr(get_bloginfo('html_type')) . '; charset=' . esc_attr(get_option('blog_charset')) . '" />' . "\n";
}

function iframe_header($title = '', $deprecated = false)
{
    show_admin_bar(false);
    $current_screen = get_current_screen();
    _wp_admin_html_begin();
    echo '<title>' . esc_html(get_bloginfo('name')) . ' &rsaquo; ' . esc_html($title) . ' &#8212; ' . esc_html(__('WordPress')) . '</title>' . "\n";
    echo '<meta name="viewport" content="width=device-width,initial-scale=1.0">' . "\n";
    if ($current_screen) {
        do_action("admin_enqueue_scripts", $current_screen->id);
        do_action("admin_print_styles-{$current_screen->id}");
        do_action('admin_print_styles');
        do_action("admin_print_scripts-{$current_screen->id}");
        do_action('admin_print_scripts');
        do_action("admin_head-{$current_screen->id}");
    }
    do_action('admin_head');
    $admin_body_class = $current_screen ? preg_replace('/[^a-z0-9_-]+/i', '-', $current_screen->id) : '';
    $admin_body_class .= ' iframe';
    echo '</head>' . "\n" . '<body class="wp-admin wp-core-ui no-js ' . esc_attr($admin_body_class) . '">' . "\n";
    echo "<script type=\"text/javascript\">document.body.className = document.body.className.replace('no-js', 'js');</script>\n";
}

function iframe_footer()
{
    echo "\t" . '<div class="hidden">' . "\n";
    wp_auth_check_html();
    do_action('admin_footer', '');
    $current_screen = get_current_screen();
    if ($current_screen) {
        do_action("admin_print_footer_scripts-{$current_screen->id}");
    }
    do_action('admin_print_footer_scripts');
    echo "\t</div>\n<script type=\"text/javascript\">if(typeof wpOnload==='function')wpOnload();</script>\n</body>\n</html>\n";
}


function get_home_path()
{
    $home = set_url_scheme(get_option('home'), 'http');
    $siteurl = set_url_scheme(get_option('siteurl'), 'http');
    if (!empty($home) && strcasecmp($home, $siteurl) !== 0) {
        $wp_path_rel_to_home = str_ireplace($home, '', $siteurl);
        $pos = strripos(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), trailingslashit($wp_path_rel_to_home));
        $home_path = $pos === false ? ABSPATH : substr($_SERVER['SCRIPT_FILENAME'], 0, $pos);
        $home_path = trailingslashit($home_path);
    } else {
        $home_path = ABSPATH;
    }
    return str_replace('\\', '/', $home_path);
}

function get_filesystem_method($args = [], $context = '', $allow_relaxed_file_ownership = false)
{
    return apply_filters('filesystem_method', 'direct', $args, $context, $allow_relaxed_file_ownership);
}

/** One upgrader line on the page: an error says its message, and its data when that is text; buffers flushed so it shows at once (probe upgrader). */
function show_message($message)
{
    if (is_wp_error($message)) {
        $data = $message->get_error_data();
        $message = $message->get_error_message() . (is_string($data) && $data !== '' ? ': ' . $data : '');
    }
    echo '<p>' . $message . "</p>\n";
    wp_ob_end_flush_all();
    flush();
}

function request_filesystem_credentials($form_post, $type = '', $error = false, $context = '', $extra_fields = null, $allow_relaxed_file_ownership = false)
{
    $req_cred = apply_filters('request_filesystem_credentials', '', $form_post, $type, $error, $context, $extra_fields, $allow_relaxed_file_ownership);
    if ($req_cred !== '') {
        return $req_cred;
    }
    return true;
}

function plugins_api($action, $args = [])
{
    $packages = new Minn\Ops\Packages(Runtime::current()->site, rtrim(ABSPATH, '/') . '/wp-content');
    return (new Minn\Ops\DirectoryApi('plugins', apply_filters(...), $packages, get_user_locale(), (string) $GLOBALS['wp_version']))->ask((string) $action, $args);
}

/** themes_api as plugins call it (probe themes-api): a seller's own answer first, otherwise the directory through the Minn update service. */
function themes_api($action, $args = [])
{
    $packages = new Minn\Ops\Packages(Runtime::current()->site, rtrim(ABSPATH, '/') . '/wp-content');
    return (new Minn\Ops\DirectoryApi('themes', apply_filters(...), $packages, get_user_locale(), (string) $GLOBALS['wp_version']))->ask((string) $action, $args);
}

function unzip_file($file, $to)
{
    global $wp_filesystem;
    if (!$wp_filesystem || !is_object($wp_filesystem)) {
        return new WP_Error('fs_unavailable', __('Could not access filesystem.'));
    }
    $result = (new Minn\Ops\Unzip($wp_filesystem, apply_filters(...), defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644))->into((string) $file, (string) $to);
    return $result instanceof Minn\Runtime\Refusal ? new WP_Error($result->code, __($result->message), $result->data) : $result;
}

function WP_Filesystem($args = false, $context = false, $allow_relaxed_file_ownership = false)
{
    global $wp_filesystem;
    $method = get_filesystem_method($args, $context, $allow_relaxed_file_ownership);
    if (!$method) {
        return false;
    }
    $method = "WP_Filesystem_$method";
    if (!class_exists($method)) {
        return false;
    }
    $wp_filesystem = new $method($args);
    if (!defined('FS_CHMOD_DIR')) {
        define('FS_CHMOD_DIR', (fileperms(ABSPATH) & 0777 | 0755));
    }
    if (!defined('FS_CHMOD_FILE')) {
        define('FS_CHMOD_FILE', (fileperms(ABSPATH . 'index.php') & 0777 | 0644));
    }
    if (!defined('FS_CONNECT_TIMEOUT')) {
        define('FS_CONNECT_TIMEOUT', 30);
    }
    if (!defined('FS_TIMEOUT')) {
        define('FS_TIMEOUT', 30);
    }
    if (is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
        return false;
    }
    return $wp_filesystem->connect();
}

function wp_get_translation_updates()
{
    return [];
}

function get_core_updates($options = [])
{
    $options = array_merge(['available' => true, 'dismissed' => false], $options);
    $from_api = get_site_transient('update_core');
    if (!isset($from_api->updates) || !is_array($from_api->updates)) {
        return false;
    }
    $updates = $from_api->updates;
    $result = [];
    foreach ($updates as $update) {
        if ($options['available']) {
            $update->dismissed = false;
            $result[] = $update;
        }
    }
    return $result;
}

/** The updates waiting, counted for what the user may update, with the titles that describe them. */
function wp_get_update_data()
{
    [$data, $titles] = Minn\Runtime\UpdateCounts::forCurrentUser();
    return apply_filters('wp_get_update_data', $data, $titles);
}

/** Minn's own check: the update service asked when the last answer is old, the offers left in update_plugins. */
function wp_update_plugins($extra_stats = [])
{
    Minn\Ops\Updates::forSite(Runtime::current()->site, Runtime::current()->contentDir())->checkForWordPress('update_plugins', (array) $extra_stats);
}

/** Minn's own check: the update service asked when the last answer is old, the offers left in update_themes. */
function wp_update_themes($extra_stats = [])
{
    Minn\Ops\Updates::forSite(Runtime::current()->site, Runtime::current()->contentDir())->checkForWordPress('update_themes', (array) $extra_stats);
}

function wp_clean_plugins_cache($clear_update_cache = true)
{
    if ($clear_update_cache) {
        delete_site_transient('update_plugins');
    }
    wp_cache_delete('plugins', 'plugins');
}

function wp_clean_themes_cache($clear_update_cache = true)
{
    if ($clear_update_cache) {
        delete_site_transient('update_themes');
    }
}

/** Every queued style printed (the sign-in page's head prints them so), then print_admin_styles; the handles printed. */
function print_admin_styles()
{
    $done = _minn_print_styles(false);
    apply_filters('print_admin_styles', true);
    return $done;
}

function upload_is_user_over_quota($display_message = true)
{
    return false;
}

/** A theme's folder deleted between delete_theme and deleted_theme (ThemeSwitch::delete); never the active theme or its parent. */
function delete_theme($stylesheet, $redirect = '')
{
    if (empty($stylesheet)) {
        return false;
    }
    if (get_stylesheet() === $stylesheet || get_template() === $stylesheet) {
        return new WP_Error('could_not_remove_theme', 'Could not fully remove the theme.');
    }
    return Minn\Runtime\ThemeSwitch::delete((string) $stylesheet) ? true : new WP_Error('could_not_remove_theme', 'Could not fully remove the theme.');
}

function _wp_oembed_get_object()
{
    static $wp_oembed = null;
    if ($wp_oembed === null) {
        $wp_oembed = new WP_oEmbed();
    }
    return $wp_oembed;
}

/** The site's theme's templates for a post type, name => file (WP_Theme::get_page_templates turned round). */
function get_page_templates($post = null, $post_type = 'page')
{
    return array_flip(wp_get_theme()->get_page_templates($post, $post_type));
}

/** A post's title for a list, escaped, or "(no title)". */
function _draft_or_post_title($post = 0)
{
    $title = get_the_title($post);
    return esc_html($title === '' ? __('(no title)') : $title);
}

/**
 * The states a post list shows beside a title (probe placeholders-admin),
 * through display_post_states: password, private, draft, pending, sticky,
 * scheduled, then the pages the site gives a role; a state the list is
 * already filtered to is left out.
 */
function get_post_states($post)
{
    $listing = (string) ($_REQUEST['post_status'] ?? '');
    $status = (string) $post->post_status;
    $pageRole = static fn (string $option): bool => (int) get_option($option) === (int) $post->ID;
    $front = get_option('show_on_front') === 'page';
    $states = array_filter([
        'protected' => $post->post_password !== '' ? _x('Password protected', 'post status') : null,
        'private' => $status === 'private' && $listing !== 'private' ? __('Private') : null,
        'draft' => $status === 'draft' && $listing !== 'draft' ? __('Draft') : null,
        'pending' => $status === 'pending' && $listing !== 'pending' ? _x('Pending', 'post status') : null,
        'sticky' => is_sticky($post->ID) ? _x('Sticky', 'post status') : null,
        'scheduled' => $status === 'future' ? _x('Scheduled', 'post status') : null,
        'page_on_front' => $front && $pageRole('page_on_front') ? _x('Front Page', 'page label') : null,
        'page_for_posts' => $front && $pageRole('page_for_posts') ? _x('Posts Page', 'page label') : null,
        'page_for_privacy_policy' => $pageRole('wp_page_for_privacy_policy') ? _x('Privacy Policy Page', 'page label') : null,
    ], static fn ($state) => $state !== null);
    return apply_filters('display_post_states', $states, $post);
}

/** A post's states as a list writes them after its title (Admin\PostListMarkup); echoed and returned. */
function _post_states($post, $display = true)
{
    $markup = Minn\Admin\PostListMarkup::states((array) get_post_states($post));
    if ($display) {
        echo $markup;
    }
    return $markup;
}

/** The search box's current query, escaped for its value attribute. */
function _admin_search_query()
{
    echo isset($_REQUEST['s']) ? esc_attr(wp_unslash($_REQUEST['s'])) : '';
}

/**
 * The meta boxes a screen hides (probe placeholders-admin): the user's
 * choice, or the defaults (a post, page or attachment editor hides eight,
 * another type's editor the slug box) through default_hidden_meta_boxes;
 * then hidden_meta_boxes.
 */
function get_hidden_meta_boxes($screen)
{
    $screen = is_string($screen) ? convert_to_screen($screen) : $screen;
    $hidden = get_user_option("metaboxhidden_{$screen->id}");
    $defaults = !is_array($hidden);
    if ($defaults) {
        $hidden = $screen->base !== 'post' ? [] : (in_array($screen->post_type, ['post', 'page', 'attachment'], true) ? ['slugdiv', 'trackbacksdiv', 'postcustom', 'postexcerpt', 'commentstatusdiv', 'commentsdiv', 'authordiv', 'revisionsdiv'] : ['slugdiv']);
        $hidden = apply_filters('default_hidden_meta_boxes', $hidden, $screen);
    }
    return apply_filters('hidden_meta_boxes', $hidden, $screen, $defaults);
}

/** A meta box's classes: "closed" when the user closed it (not while it is being edited), through postbox_classes_{screen}_{box}. */
function postbox_classes($box_id, $screen_id)
{
    $closed = isset($_GET['edit']) && $_GET['edit'] === $box_id ? false : get_user_option('closedpostboxes_' . $screen_id);
    $classes = is_array($closed) && in_array($box_id, $closed, true) ? ['closed'] : [''];
    return implode(' ', (array) apply_filters("postbox_classes_{$screen_id}_{$box_id}", $classes));
}

/** Registers an importer for Tools > Import; an error handed as the callback comes back. */
function register_importer($id, $name, $description, $callback)
{
    if (is_wp_error($callback)) {
        return $callback;
    }
    $GLOBALS['wp_importers'][$id] = [$name, $description, $callback];
}

/** The registered importers, sorted by name. */
function get_importers()
{
    if (is_array($GLOBALS['wp_importers'] ?? null)) {
        uasort($GLOBALS['wp_importers'], static fn (array $a, array $b): int => strnatcasecmp((string) $a[0], (string) $b[0]));
    }
    return $GLOBALS['wp_importers'] ?? null;
}

/** A plugin's suggested privacy policy text, taken only in the admin from admin_init on. */
function wp_add_privacy_policy_content($plugin_name, $policy_text)
{
    if (!is_admin() || (!doing_action('admin_init') && !did_action('admin_init'))) {
        _doing_it_wrong(__FUNCTION__, sprintf(__('The suggested privacy policy content should be added only in wp-admin by using the %s (or later) action.'), '<code>admin_init</code>'), '4.9.7');
        return;
    }
    WP_Privacy_Policy_Content::add($plugin_name, $policy_text);
}

/** A list screen's columns through manage_{screen}_columns, asked once per screen. */
function get_column_headers($screen)
{
    static $headers = [];
    $screen = is_string($screen) ? convert_to_screen($screen) : $screen;
    return $headers[$screen->id] ??= (array) apply_filters("manage_{$screen->id}_columns", []);
}

/** The hidden fields quick edit reads for a post the user may edit (Admin\PostListMarkup). */
function get_inline_data($post)
{
    $type = get_post_type_object($post->post_type);
    if ($type === null || !current_user_can('edit_post', $post->ID)) {
        return;
    }
    $fields = ['title' => esc_textarea(trim((string) $post->post_title)), 'name' => esc_textarea((string) apply_filters('editable_slug', $post->post_name, $post)), 'author' => (int) $post->post_author, 'comments' => esc_html($post->comment_status), 'pings' => esc_html($post->ping_status), 'status' => esc_html($post->post_status), 'date' => (string) $post->post_date, 'password' => esc_html($post->post_password)];
    echo Minn\Admin\PostListMarkup::inline((int) $post->ID, $fields, _minn_inline_extras($post, $type));
}

/** @internal quick edit's further fields: parent, template, order, the type's terms, sticky, format, then add_inline_data */
function _minn_inline_extras(WP_Post $post, WP_Post_Type $type): string
{
    $out = $type->hierarchical ? '<div class="post_parent">' . $post->post_parent . '</div>' : '';
    $template = (string) get_post_meta($post->ID, '_wp_page_template', true);
    $out .= '<div class="page_template">' . ($template !== '' ? esc_html($template) : 'default') . '</div>';
    $out .= post_type_supports($post->post_type, 'page-attributes') ? '<div class="menu_order">' . $post->menu_order . '</div>' : '';
    foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
        if (!$taxonomy->show_in_quick_edit) {
            continue;
        }
        $terms = get_object_term_cache($post->ID, $taxonomy->name) ?: (array) wp_get_object_terms($post->ID, $taxonomy->name);
        $out .= $taxonomy->hierarchical
            ? '<div class="post_category" id="' . $taxonomy->name . '_' . $post->ID . '">' . implode(',', wp_list_pluck($terms, 'term_id')) . '</div>'
            : '<div class="tags_input" id="' . $taxonomy->name . '_' . $post->ID . '">' . esc_textarea(implode(', ', wp_list_pluck($terms, 'name'))) . '</div>';
    }
    $out .= $type->hierarchical ? '' : '<div class="sticky">' . (is_sticky($post->ID) ? 'sticky' : '') . '</div>';
    $out .= post_type_supports($post->post_type, 'post-formats') ? '<div class="post_format">' . esc_html((string) get_post_format($post->ID)) . '</div>' : '';
    ob_start();
    do_action('add_inline_data', $post, $type);
    return $out . ob_get_clean();
}

/** The comment screen's keyboard shortcuts, for a user who turned them on. */
function enqueue_comment_hotkeys_js()
{
    if (get_user_option('comment_shortcuts') === 'true') {
        wp_enqueue_script('jquery-table-hotkeys');
    }
}
