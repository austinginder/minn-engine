<?php

use Minn\Rest\Schema;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/view-config (probe rest-view-config): how the admin's data views
 * show an entity (a post type shown in REST, a taxonomy shown in REST, or
 * any other kind), for someone who holds the capability the entity asks:
 * a type's edit_posts, a taxonomy's manage_terms, manage_options for the
 * root, edit_posts otherwise. The configuration is the entity's
 * (wp_get_entity_view_config, wp-api/view-config.php), as plugins patch it
 * through WP_View_Config_Data; empty objects are cast by the schema.
 */
class WP_REST_View_Config_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'view-config';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => ['kind' => ['description' => __('Entity kind.'), 'type' => 'string', 'required' => true], 'name' => ['description' => __('Entity name.'), 'type' => 'string', 'required' => true]]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /** An entity that is not shown is not found; otherwise the caller needs the capability it asks. */
    public function get_items_permissions_check($request)
    {
        $capability = $this->get_required_capability((string) $request['kind'], (string) $request['name']);
        if ($capability === null) {
            return new WP_Error('rest_view_config_invalid_entity', __('Invalid entity kind or name.'), ['status' => 404]);
        }
        if (!current_user_can($capability)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to read view config.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** The capability an entity asks; null for a post type or taxonomy that is missing or hidden from REST. */
    protected function get_required_capability($kind, $name)
    {
        if ($kind === 'postType' || $kind === 'taxonomy') {
            $object = $kind === 'postType' ? get_post_type_object($name) : get_taxonomy($name);
            if (!$object || empty($object->show_in_rest)) {
                return null;
            }
            return $kind === 'postType' ? $object->cap->edit_posts : $object->cap->manage_terms;
        }
        return $kind === 'root' ? 'manage_options' : 'edit_posts';
    }

    public function get_items($request)
    {
        $kind = (string) $request['kind'];
        $name = (string) $request['name'];
        $config = wp_get_entity_view_config($kind, $name);
        return rest_ensure_response($this->cast_empty_objects(['kind' => $kind, 'name' => $name, 'version' => 1] + $config, $this->get_item_schema()));
    }

    /** A value with each empty array the schema calls an object made one (Minn\Rest\Schema::castEmptyObjects). */
    protected function cast_empty_objects($value, $schema)
    {
        return Schema::castEmptyObjects($value, (array) $schema);
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('view-config');
        return $this->add_additional_fields_schema($this->schema);
    }

    protected function get_view_base_schema()
    {
        return SchemaData::of('view-config-view-base');
    }

    protected function get_column_style_schema()
    {
        return SchemaData::of('view-config-column-style');
    }

    protected function get_table_layout_schema()
    {
        return SchemaData::of('view-config-table-layout');
    }

    protected function get_list_layout_schema()
    {
        return SchemaData::of('view-config-list-layout');
    }

    protected function get_combined_layout_schema()
    {
        return SchemaData::of('view-config-combined-layout');
    }

    protected function get_grid_layout_schema()
    {
        return SchemaData::of('view-config-grid-layout');
    }

    protected function get_form_layout_schema()
    {
        return SchemaData::of('view-config-form-layout');
    }

    protected function get_form_field_schema()
    {
        return SchemaData::of('view-config-form-field');
    }

    protected function get_form_schema()
    {
        return SchemaData::of('view-config-form');
    }
}
