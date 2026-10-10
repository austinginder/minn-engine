<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/block-patterns/categories as a class (probe rest-block-controllers):
 * the registered pattern categories, each its name, label and description
 * (when it has one), for anyone who may edit posts of a type shown in REST.
 * The engine answers the route itself; plugins call this class directly.
 */
class WP_REST_Block_Pattern_Categories_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'block-patterns/categories';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        if (current_user_can('edit_posts')) {
            return true;
        }
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to view the registered block pattern categories.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        $data = [];
        foreach (WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $category) {
            $data[] = $this->prepare_response_for_collection($this->prepare_item_for_response($category, $request));
        }
        return rest_ensure_response($data);
    }

    public function prepare_item_for_response($item, $request)
    {
        $fields = $this->get_fields_for_response($request);
        $data = [];
        foreach (['name', 'label', 'description'] as $key) {
            if (isset($item[$key]) && rest_is_field_included($key, $fields)) {
                $data[$key] = $item[$key];
            }
        }
        return rest_ensure_response($this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view'));
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('block-pattern-category');
        return $this->add_additional_fields_schema($this->schema);
    }
}
