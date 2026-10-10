<?php

use Minn\Rest\RestMeta;

/**
 * An object's REST meta field (probe rest-meta-fields): the keys registered
 * to show in REST for its meta type and subtype, read, written and described
 * as the reference's controllers do it. The engine's own controllers use the
 * same rules (Minn\Rest\RestMeta).
 */
#[AllowDynamicProperties]
abstract class WP_REST_Meta_Fields
{
    abstract protected function get_meta_type();

    protected function get_meta_subtype()
    {
        return $this->get_meta_type();
    }

    abstract protected function get_rest_field_type();

    /** Deprecated since 5.6: the meta field registered on its object's REST responses. */
    public function register_field()
    {
        _deprecated_function(__METHOD__, '5.6.0');
        register_rest_field($this->get_rest_field_type(), 'meta', ['get_callback' => [$this, 'get_value'], 'update_callback' => [$this, 'update_value'], 'schema' => $this->get_field_schema()]);
    }

    /** Every registered key's value for an object, keyed by REST name, whatever the request's context. */
    public function get_value($object_id, $request)
    {
        $out = [];
        foreach ($this->get_registered_fields() as $key => $field) {
            $out[$field['name']] = RestMeta::value((string) $this->get_meta_type(), (int) $object_id, (string) $key, $field, $request);
        }
        return $out;
    }

    protected function prepare_value_for_response($value, $request, $args)
    {
        return RestMeta::prepare($value, (array) $args + ['single' => true, 'schema' => [], 'prepare_callback' => null], $request);
    }

    /** The meta a request names, written key by key; null, or every refusal in one error. */
    public function update_value($meta, $object_id)
    {
        $errors = RestMeta::errors((string) $this->get_meta_type(), (int) $object_id, (string) $this->get_meta_subtype(), (array) $meta);
        $first = array_shift($errors);
        foreach ($errors as $error) {
            $first->merge_from($error);
        }
        return $first;
    }

    protected function delete_meta_value($object_id, $meta_key, $name)
    {
        return RestMeta::delete((string) $this->get_meta_type(), (int) $object_id, (string) $meta_key, $this->field_for((string) $meta_key, (string) $name)) ?? true;
    }

    protected function update_multi_meta_value($object_id, $meta_key, $name, $values)
    {
        return RestMeta::storeAll((string) $this->get_meta_type(), (int) $object_id, (string) $this->get_meta_subtype(), (string) $meta_key, $this->field_for((string) $meta_key, (string) $name), (array) $values) ?? true;
    }

    protected function update_meta_value($object_id, $meta_key, $name, $value)
    {
        return RestMeta::store((string) $this->get_meta_type(), (int) $object_id, (string) $this->get_meta_subtype(), (string) $meta_key, $this->field_for((string) $meta_key, (string) $name), $value) ?? true;
    }

    protected function is_meta_value_same_as_stored_value($meta_key, $subtype, $stored_value, $user_value)
    {
        return RestMeta::same((string) $this->get_meta_type(), (string) $subtype, (string) $meta_key, $this->field_for((string) $meta_key, (string) $meta_key), $stored_value, $user_value);
    }

    protected function get_registered_fields()
    {
        return RestMeta::fields((string) $this->get_meta_type(), (string) $this->get_meta_subtype());
    }

    /** The meta field's schema: each key's under its REST name, and the whole checked to be an object. */
    public function get_field_schema()
    {
        $properties = [];
        foreach ($this->get_registered_fields() as $field) {
            $properties[$field['name']] = $field['schema'];
        }
        return ['description' => __('Meta fields.'), 'type' => 'object', 'context' => ['view', 'edit'], 'properties' => $properties, 'arg_options' => ['sanitize_callback' => null, 'validate_callback' => [$this, 'check_meta_is_array']]];
    }

    /** A value prepared from its schema alone: checked against it (null when it does not fit) and cast. */
    public static function prepare_value($value, $request, $args)
    {
        return RestMeta::prepare($value, ['prepare_callback' => null] + (array) $args + ['single' => true, 'schema' => []], $request);
    }

    public function check_meta_is_array($value, $request, $param)
    {
        return is_array($value) ? $value : false;
    }

    protected function default_additional_properties_to_false($schema)
    {
        _deprecated_function(__METHOD__, '5.6.0', 'rest_default_additional_properties_to_false()');
        return rest_default_additional_properties_to_false($schema);
    }

    protected static function get_empty_value_for_type($type)
    {
        return RestMeta::emptyValue((string) $type);
    }

    /** A key's registered field, or a bare one (its REST name, single, untyped) when it is not registered to show. */
    private function field_for(string $key, string $name): array
    {
        return $this->get_registered_fields()[$key] ?? ['name' => $name, 'single' => true, 'type' => null, 'schema' => [], 'prepare_callback' => null];
    }
}
