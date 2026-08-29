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

function activate_plugin($plugin, $redirect = '', $network_wide = false, $silent = false)
{
    $plugin = plugin_basename(trim((string) $plugin));
    $current = get_option('active_plugins', []);
    $current = is_array($current) ? $current : [];
    if (in_array($plugin, $current, true)) {
        return null;
    }
    if (!is_file(WP_PLUGIN_DIR . '/' . $plugin)) {
        return new WP_Error('plugin_not_found', 'Plugin file does not exist.');
    }
    if (!$silent) {
        do_action('activate_plugin', $plugin, $network_wide);
        do_action("activate_{$plugin}", $network_wide);
    }
    $current[] = $plugin;
    sort($current);
    update_option('active_plugins', $current);
    if (!$silent) {
        do_action('activated_plugin', $plugin, $network_wide);
    }
    return null;
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

function deactivate_plugins($plugins, $silent = false, $network_wide = null)
{
    $current = get_option('active_plugins', []);
    $current = is_array($current) ? $current : [];
    foreach ((array) $plugins as $plugin) {
        $plugin = plugin_basename(trim((string) $plugin));
        if (!in_array($plugin, $current, true)) {
            continue;
        }
        if (!$silent) {
            do_action('deactivate_plugin', $plugin, false);
        }
        $current = array_values(array_diff($current, [$plugin]));
        if (!$silent) {
            do_action("deactivate_{$plugin}", false);
            do_action('deactivated_plugin', $plugin, false);
        }
    }
    update_option('active_plugins', $current);
}

function validate_plugin($plugin)
{
    return is_file(WP_PLUGIN_DIR . '/' . $plugin) ? 0 : new WP_Error('plugin_not_found', 'Plugin file does not exist.');
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
