<?php

use Minn\Rest\Fields;

/** The controller base plugin code extends. Defaults from contracts/fixtures/api/rest.json. */
#[AllowDynamicProperties]
abstract class WP_REST_Controller
{
    protected $namespace;
    protected $rest_base;
    protected $schema;

    public function register_routes()
    {
        _doing_it_wrong('WP_REST_Controller::register_routes', "Method '%s' must be overridden.", '4.7.0');
    }

    public function get_items_permissions_check($request)
    {
        return new WP_Error('invalid-method', "Method 'get_items_permissions_check' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function get_items($request)
    {
        return new WP_Error('invalid-method', "Method 'get_items' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function get_item_permissions_check($request)
    {
        return new WP_Error('invalid-method', "Method 'get_item_permissions_check' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function get_item($request)
    {
        return new WP_Error('invalid-method', "Method 'get_item' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function create_item_permissions_check($request)
    {
        return new WP_Error('invalid-method', "Method 'create_item_permissions_check' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function create_item($request)
    {
        return new WP_Error('invalid-method', "Method 'create_item' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function update_item_permissions_check($request)
    {
        return new WP_Error('invalid-method', "Method 'update_item_permissions_check' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function update_item($request)
    {
        return new WP_Error('invalid-method', "Method 'update_item' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function delete_item_permissions_check($request)
    {
        return new WP_Error('invalid-method', "Method 'delete_item_permissions_check' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function delete_item($request)
    {
        return new WP_Error('invalid-method', "Method 'delete_item' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    protected function prepare_item_for_database($request)
    {
        return new WP_Error('invalid-method', "Method 'prepare_item_for_database' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function prepare_item_for_response($item, $request)
    {
        return new WP_Error('invalid-method', "Method 'prepare_item_for_response' not implemented. Must be overridden in subclass.", ['status' => 405]);
    }

    public function prepare_response_for_collection($response)
    {
        if (!($response instanceof WP_REST_Response)) {
            return $response;
        }
        $data = (array) $response->get_data();
        $links = WP_REST_Server::get_compact_response_links($response);
        if (!empty($links)) {
            $data['_links'] = $links;
        }
        return $data;
    }

    public function filter_response_by_context($response_data, $context)
    {
        $schema = $this->get_item_schema();
        return rest_filter_response_by_context($response_data, $schema, $context);
    }

    public function get_item_schema()
    {
        return $this->add_additional_fields_schema([]);
    }

    public function get_public_item_schema()
    {
        $schema = $this->get_item_schema();
        if (!empty($schema['properties'])) {
            foreach ($schema['properties'] as &$property) {
                unset($property['arg_options']);
            }
        }
        return $schema;
    }

    public function get_collection_params()
    {
        return [
            'context' => $this->get_context_param(),
            'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg', 'minimum' => 1],
            'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg'],
            'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg'],
        ];
    }

    public function get_context_param($args = [])
    {
        $param_details = ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg'];
        $schema = $this->get_item_schema();
        if (empty($schema['properties'])) {
            return array_merge($param_details, $args);
        }
        $contexts = [];
        foreach ($schema['properties'] as $attributes) {
            if (!empty($attributes['context'])) {
                $contexts = array_merge($contexts, $attributes['context']);
            }
        }
        if (!empty($contexts)) {
            $param_details['enum'] = array_unique($contexts);
            rsort($param_details['enum']);
        }
        return array_merge($param_details, $args);
    }

    protected function add_additional_fields_to_object($response_data, $request)
    {
        $additional_fields = $this->get_additional_fields();
        $requested_fields = $this->get_fields_for_response($request);
        foreach ($additional_fields as $field_name => $field_options) {
            if (!$field_options['get_callback']) {
                continue;
            }
            if (!rest_is_field_included($field_name, $requested_fields)) {
                continue;
            }
            $response_data[$field_name] = call_user_func($field_options['get_callback'], $response_data, $field_name, $request, $this->get_object_type());
        }
        return $response_data;
    }

    protected function update_additional_fields_for_object($data_object, $request)
    {
        $additional_fields = $this->get_additional_fields();
        foreach ($additional_fields as $field_name => $field_options) {
            if (!$field_options['update_callback']) {
                continue;
            }
            if (!isset($request[$field_name])) {
                continue;
            }
            $result = call_user_func($field_options['update_callback'], $request[$field_name], $data_object, $field_name, $request, $this->get_object_type());
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    protected function add_additional_fields_schema($schema)
    {
        if (empty($schema['title'])) {
            return $schema;
        }
        $additional_fields = $this->get_additional_fields($schema['title']);
        foreach ($additional_fields as $field_name => $field_options) {
            if (!$field_options['schema']) {
                continue;
            }
            $schema['properties'][$field_name] = $field_options['schema'];
        }
        return $schema;
    }

    protected function get_additional_fields($object_type = null)
    {
        if (!$object_type) {
            $object_type = $this->get_object_type();
        }
        if (!$object_type) {
            return [];
        }
        $fields = $GLOBALS['wp_rest_additional_fields'] ?? [];
        return $fields[$object_type] ?? [];
    }

    protected function get_object_type()
    {
        $schema = $this->get_item_schema();
        if (!$schema || !isset($schema['title'])) {
            return null;
        }
        return $schema['title'];
    }

    public function get_fields_for_response($request)
    {
        $properties = $this->get_item_schema()['properties'] ?? [];
        foreach ($this->get_additional_fields() as $field_name => $field_options) {
            if ($field_options['schema']) {
                $properties[$field_name] = $field_options;
            }
        }
        $properties['_links'] = ['type' => 'object'];
        $fields = array_keys($properties);
        $requested = isset($request['_fields']) ? wp_parse_list($request['_fields']) : [];
        return $requested === [] ? $fields : Fields::select($fields, $requested);
    }

    public function get_endpoint_args_for_item_schema($method = WP_REST_Server::CREATABLE)
    {
        return rest_get_endpoint_args_for_schema($this->get_item_schema(), $method);
    }

    public function sanitize_slug($slug)
    {
        return sanitize_title($slug);
    }
}
