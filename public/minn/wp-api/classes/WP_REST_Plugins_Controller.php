<?php

use Minn\Rest\PluginsController;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * The plugins endpoints as a class (probe rest-plugins-controller). The
 * engine answers wp/v2/plugins itself, Minn extensions included; this is
 * the class plugins construct or call, WordPress-shaped over the
 * runtime's plugin functions: get_plugins() for the list, activate_plugin
 * and deactivate_plugins for a status, delete_plugins for a delete, and
 * the engine's item shape. Installing goes through the engine's route,
 * which fetches from the Minn update service.
 */
class WP_REST_Plugins_Controller extends WP_REST_Controller
{
    const PATTERN = '[^.\/]+(?:\/[^.\/]+)?';

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'plugins';
    }

    public function register_routes()
    {
        $context = ['context' => $this->get_context_param(['default' => 'view'])];
        $plugin = ['type' => 'string', 'pattern' => self::PATTERN, 'validate_callback' => [$this, 'validate_plugin_param'], 'sanitize_callback' => [$this, 'sanitize_plugin_param']];
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => [
                'slug' => ['type' => 'string', 'required' => true, 'description' => __('WordPress.org plugin directory slug.'), 'pattern' => '[\w\-]+'],
                'status' => ['description' => __('The plugin activation status.'), 'type' => 'string', 'enum' => is_multisite() ? ['inactive', 'active', 'network-active'] : ['inactive', 'active'], 'default' => 'inactive'],
            ]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<plugin>' . self::PATTERN . ')', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => $context + ['plugin' => $plugin]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $context + ['plugin' => $plugin] + $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => $context + ['plugin' => $plugin]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('rest_cannot_view_plugins', __('Sorry, you are not allowed to manage plugins for this site.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_items($request)
    {
        // Every installed plugin the request matches, each shaped as it is listed.
        $plugins = [];
        foreach (get_plugins() as $file => $item) {
            $item['_file'] = $file;
            if ($this->does_plugin_match_request($request, $item)) {
                $plugins[] = $this->prepare_response_for_collection($this->prepare_item_for_response($item, $request));
            }
        }
        return new WP_REST_Response($plugins);
    }

    public function get_item_permissions_check($request)
    {
        // Managing plugins, then a plugin that is there and may be read.
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('rest_cannot_view_plugin', __('Sorry, you are not allowed to manage plugins for this site.'), ['status' => rest_authorization_required_code()]);
        }
        $item = $this->get_plugin_data($request['plugin']);
        return is_wp_error($item) ? $item : $this->check_read_permission($request['plugin']);
    }

    public function get_item($request)
    {
        $item = $this->get_plugin_data($request['plugin']);
        return is_wp_error($item) ? $item : $this->prepare_item_for_response($item, $request);
    }

    protected function check_read_permission($plugin)
    {
        // On a network, a plugin only the network activates is the network administrator's to read.
        if (is_multisite() && !current_user_can('manage_network_plugins') && is_network_only_plugin($plugin)) {
            return new WP_Error('rest_cannot_view_plugin', __('Sorry, you are not allowed to manage this plugin.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item_permissions_check($request)
    {
        // Installing, and activating too when the new plugin is to be active.
        if (!current_user_can('install_plugins')) {
            return new WP_Error('rest_cannot_install_plugin', __('Sorry, you are not allowed to install plugins on this site.'), ['status' => rest_authorization_required_code()]);
        }
        if ($request['status'] === 'active' && !current_user_can('activate_plugins')) {
            return new WP_Error('rest_cannot_activate_plugin', __('Sorry, you are not allowed to activate plugins.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item($request)
    {
        // The engine's install route fetches the plugin (from the Minn update service) and activates it when asked; the answer is the new plugin, a 201.
        $call = clone $request;
        $call->set_route('/' . $this->namespace . '/' . $this->rest_base);
        $api = WP_REST_Server::minn_engine_api($call);
        $engine = Minn\Rest\RuntimePrepare::during($call, static fn () => $api->handleEngineOnly($call->get_route(), $call));
        $body = json_decode((string) $engine?->body, true);
        if ($engine === null || $engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_no_route'), (string) ($body['message'] ?? ''), $body['data'] ?? ['status' => $engine->status ?? 404]);
        }
        wp_clean_plugins_cache();
        $item = $this->get_plugin_data($body['plugin'] . '.php');
        $response = rest_ensure_response(is_wp_error($item) ? $body : $this->prepare_item_for_response($item, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, $body['plugin'])));
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        // Managing plugins, a plugin that is there and may be read, then the status asked for.
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('rest_cannot_manage_plugins', __('Sorry, you are not allowed to manage plugins for this site.'), ['status' => rest_authorization_required_code()]);
        }
        $item = $this->get_plugin_data($request['plugin']);
        if (is_wp_error($item)) {
            return $item;
        }
        $readable = $this->check_read_permission($request['plugin']);
        if (is_wp_error($readable) || empty($request['status'])) {
            return $readable;
        }
        return $this->plugin_status_permission_check($request['plugin'], $request['status'], $this->get_plugin_status($request['plugin']));
    }

    public function update_item($request)
    {
        // The status changed when one is asked for and differs; the answer is the plugin in the edit context.
        $item = $this->get_plugin_data($request['plugin']);
        if (is_wp_error($item)) {
            return $item;
        }
        $status = $this->get_plugin_status($request['plugin']);
        if ($request['status'] && $status !== $request['status']) {
            $handled = $this->handle_plugin_status($request['plugin'], $request['status'], $status);
            if (is_wp_error($handled)) {
                return $handled;
            }
        }
        $this->update_additional_fields_for_object($item, $request);
        $request['context'] = 'edit';
        return $this->prepare_item_for_response($item, $request);
    }

    public function delete_item_permissions_check($request)
    {
        // Managing and deleting plugins, then a plugin that is there and may be read.
        if (!current_user_can('activate_plugins') || !current_user_can('delete_plugins')) {
            return new WP_Error('rest_cannot_manage_plugins', __('Sorry, you are not allowed to manage plugins for this site.'), ['status' => rest_authorization_required_code()]);
        }
        $item = $this->get_plugin_data($request['plugin']);
        return is_wp_error($item) ? $item : $this->check_read_permission($request['plugin']);
    }

    public function delete_item($request)
    {
        // Only an inactive plugin is deleted; the answer is what it was.
        $item = $this->get_plugin_data($request['plugin']);
        if (is_wp_error($item)) {
            return $item;
        }
        if (is_plugin_active($request['plugin'])) {
            return new WP_Error('rest_cannot_delete_active_plugin', __('Cannot delete an active plugin. Please deactivate it first.'), ['status' => 400]);
        }
        $available = $this->is_filesystem_available();
        if (is_wp_error($available)) {
            return $available;
        }
        $previous = $this->prepare_item_for_response($item, $request);
        $deleted = delete_plugins([$request['plugin']]);
        if (is_wp_error($deleted)) {
            $deleted->add_data(['status' => 500]);
            return $deleted;
        }
        return new WP_REST_Response(['deleted' => true, 'previous' => $previous->get_data()]);
    }

    public function prepare_item_for_response($item, $request)
    {
        // The engine's item for the plugin's headers, whatever the context or fields; its link only when the fields take links.
        $item = (array) $item;
        $file = (string) ($item['_file'] ?? '');
        $response = new WP_REST_Response(PluginsController::shape($file, WP_PLUGIN_DIR . '/' . $file, $item, $this->get_plugin_status($file)));
        $fields = $this->get_fields_for_response($request);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($item));
        }
        return apply_filters('rest_prepare_plugin', $response, $item, $request);
    }

    protected function prepare_links($item)
    {
        return ['self' => ['href' => rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, substr((string) $item['_file'], 0, -4)))]];
    }

    protected function get_plugin_data($plugin)
    {
        $plugins = get_plugins();
        if (!isset($plugins[$plugin])) {
            return new WP_Error('rest_plugin_not_found', __('Plugin not found.'), ['status' => 404]);
        }
        return $plugins[$plugin] + ['_file' => $plugin];
    }

    protected function get_plugin_status($plugin)
    {
        if (is_plugin_active_for_network($plugin)) {
            return 'network-active';
        }
        return is_plugin_active($plugin) ? 'active' : 'inactive';
    }

    protected function plugin_status_permission_check($plugin, $new_status, $current_status)
    {
        // Network activation is the network's; otherwise the user must be able to activate or deactivate this plugin.
        if (is_multisite() && ($current_status === 'network-active' || $new_status === 'network-active') && !current_user_can('manage_network_plugins')) {
            return new WP_Error('rest_cannot_manage_network_plugins', __('Sorry, you are not allowed to manage network plugins.'), ['status' => rest_authorization_required_code()]);
        }
        if (($new_status === 'active' || $new_status === 'network-active') && !current_user_can('activate_plugin', $plugin)) {
            return new WP_Error('rest_cannot_activate_plugin', __('Sorry, you are not allowed to activate this plugin.'), ['status' => rest_authorization_required_code()]);
        }
        if ($new_status === 'inactive' && !current_user_can('deactivate_plugin', $plugin)) {
            return new WP_Error('rest_cannot_deactivate_plugin', __('Sorry, you are not allowed to deactivate this plugin.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    protected function handle_plugin_status($plugin, $new_status, $current_status)
    {
        // Activated (network-wide when asked) or deactivated; an activation the plugin refuses is a 500.
        if ($new_status === $current_status) {
            return true;
        }
        if ($new_status === 'inactive') {
            deactivate_plugins($plugin, false, $current_status === 'network-active');
            return true;
        }
        $activated = activate_plugin($plugin, '', $new_status === 'network-active');
        if (is_wp_error($activated)) {
            $activated->add_data(['status' => 500]);
            return $activated;
        }
        return true;
    }

    public function validate_plugin_param($file)
    {
        // A file that begins as a plugin's does: a folder and its file, or a file alone.
        return is_string($file) && preg_match('/^' . self::PATTERN . '/u', $file) === 1;
    }

    public function sanitize_plugin_param($file)
    {
        return $file . '.php';
    }

    protected function does_plugin_match_request($request, $item)
    {
        // The search found in any of the plugin's facts without their tags (case as written), and its status among those asked for.
        $search = (string) $request['search'];
        if ($search !== '' && array_filter((array) $item, static fn ($value) => is_string($value) && str_contains(strip_tags($value), $search)) === []) {
            return false;
        }
        $status = $request['status'];
        return !$status || in_array($this->get_plugin_status($item['_file']), $status, true);
    }

    protected function is_plugin_installed($plugin)
    {
        return file_exists(WP_PLUGIN_DIR . '/' . $plugin);
    }

    protected function is_filesystem_available()
    {
        // Plugins are written straight to disk here (a filesystem that asks for credentials is not probed).
        if (get_filesystem_method() === 'direct') {
            return true;
        }
        return new WP_Error('fs_unavailable', __('The filesystem is currently unavailable for managing plugins.'), ['status' => 500]);
    }

    public function get_item_schema()
    {
        // The plugin fields captured from the reference (data/rest-schemas.json); a network adds network-active to the statuses.
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $this->schema = SchemaData::of('plugin');
        if (is_multisite()) {
            $this->schema['properties']['status']['enum'][] = 'network-active';
        }
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return [
            'context' => $this->get_context_param(['default' => 'view']),
            'search' => ['description' => __('Limit results to those matching a string.'), 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg'],
            'status' => ['description' => __('Limits results to plugins with the given status.'), 'type' => 'array', 'items' => ['type' => 'string', 'enum' => is_multisite() ? ['inactive', 'active', 'network-active'] : ['inactive', 'active']]],
        ];
    }
}
