<?php

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * The site settings endpoint as a class (probe rest-settings-controller).
 * The engine answers wp/v2/settings itself (Rest\LiveSettings); this is
 * the class plugins construct or call, WordPress-shaped over the
 * registered settings and the options API.
 */
class WP_REST_Settings_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'settings';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'args' => [], 'permission_callback' => [$this, 'get_item_permissions_check']],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE), 'permission_callback' => [$this, 'get_item_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function get_item($request)
    {
        // Each setting: what rest_pre_get_setting answers, else the option with its default; then fitted to its schema.
        $settings = [];
        foreach ($this->get_registered_options() as $name => $args) {
            $settings[$name] = apply_filters('rest_pre_get_setting', null, $name, $args);
            if ($settings[$name] === null) {
                $settings[$name] = get_option($args['option_name'], $args['schema']['default']);
            }
            $settings[$name] = $this->prepare_value($settings[$name], $args['schema']);
        }
        return $settings;
    }

    protected function prepare_value($value, $schema)
    {
        // A value its schema refuses reads as null; otherwise it is sanitized by it.
        if (is_wp_error(rest_validate_value_from_schema($value, $schema))) {
            return null;
        }
        return rest_sanitize_value_from_schema($value, $schema);
    }

    public function update_item($request)
    {
        // Each setting the request names, as it is given: offered to rest_pre_update_setting, deleted for null (refused when what is stored does not fit the schema), else stored.
        $params = $request->get_params();
        foreach ($this->get_registered_options() as $name => $args) {
            if (!array_key_exists($name, $params) || apply_filters('rest_pre_update_setting', false, $name, $request[$name], $args)) {
                continue;
            }
            if ($request[$name] !== null) {
                update_option($args['option_name'], $request[$name]);
                continue;
            }
            if (is_wp_error(rest_validate_value_from_schema(get_option($args['option_name'], false), $args['schema']))) {
                /* translators: %s: Property name. */
                return new WP_Error('rest_invalid_stored_value', sprintf(__('The %s property has an invalid stored value, and cannot be updated to null.'), $name), ['status' => 500]);
            }
            delete_option($args['option_name']);
        }
        return $this->get_item($request);
    }

    /** The settings shown in REST by their REST names: the name, the schema (type, label, description and default, then the setting's own, objects closed), and the option. */
    protected function get_registered_options()
    {
        $options = [];
        foreach (get_registered_settings() as $option => $args) {
            if (empty($args['show_in_rest'])) {
                continue;
            }
            $rest = is_array($args['show_in_rest']) ? $args['show_in_rest'] : [];
            $rest = array_merge(['name' => !empty($rest['name']) ? $rest['name'] : $option, 'schema' => []], $rest);
            $rest['schema'] = array_merge(['type' => empty($args['type']) ? null : $args['type'], 'title' => empty($args['label']) ? '' : $args['label'], 'description' => empty($args['description']) ? '' : $args['description'], 'default' => $args['default'] ?? null], $rest['schema']);
            $rest['option_name'] = $option;
            if (!in_array($rest['schema']['type'], ['number', 'integer', 'string', 'boolean', 'array', 'object'], true)) {
                continue;
            }
            $rest['schema'] = rest_default_additional_properties_to_false($rest['schema']);
            $options[$rest['name']] = $rest;
        }
        return $options;
    }

    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'settings', 'type' => 'object', 'properties' => []];
        foreach ($this->get_registered_options() as $name => $option) {
            $schema['properties'][$name] = $option['schema'] + ['arg_options' => ['sanitize_callback' => [$this, 'sanitize_callback']]];
        }
        $this->schema = $schema;
        return $this->add_additional_fields_schema($this->schema);
    }

    public function sanitize_callback($value, $request, $param)
    {
        // Null passes as null (it deletes the setting); anything else through the parameter's own checks.
        if ($value === null) {
            return $value;
        }
        return rest_parse_request_arg($value, $request, $param);
    }

    protected function set_additional_properties_to_false($schema)
    {
        _deprecated_function(__METHOD__, '6.1.0', 'rest_default_additional_properties_to_false()');
        return rest_default_additional_properties_to_false($schema);
    }
}
