<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/icon-collections (probe icons): the registered icon collections,
 * and one by slug. Reading needs edit_posts. Schemas are the reference's,
 * captured.
 */
class WP_REST_Icon_Collections_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'icon-collections';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['slug' => SchemaData::of('icon-collection-slug'), 'context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return current_user_can('edit_posts') ? true : new WP_Error('rest_cannot_view', 'Sorry, you are not allowed to view the registered icon collections.', ['status' => rest_authorization_required_code()]);
    }

    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }

    public function get_items($request)
    {
        $items = [];
        foreach (WP_Icon_Collections_Registry::get_instance()->get_all_registered() as $collection) {
            $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($collection, $request));
        }
        return rest_ensure_response($items);
    }

    public function get_item($request)
    {
        $collection = $this->get_icon_collection((string) $request['slug']);
        return is_wp_error($collection) ? $collection : $this->prepare_item_for_response($collection, $request);
    }

    public function get_icon_collection($slug)
    {
        return WP_Icon_Collections_Registry::get_instance()->get_registered($slug) ?? new WP_Error('rest_icon_collection_not_found', sprintf('Icon collection not found: "%s".', $slug), ['status' => 404]);
    }

    public function prepare_item_for_response($item, $request)
    {
        $fields = $this->get_fields_for_response($request);
        $data = [];
        foreach (['slug', 'label', 'description'] as $field) {
            if (isset($item[$field]) && rest_is_field_included($field, $fields)) {
                $data[$field] = $item[$field];
            }
        }
        return rest_ensure_response($this->filter_response_by_context($data, $request['context'] ?? 'view'));
    }

    public function get_item_schema()
    {
        return SchemaData::of('icon-collection');
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])] + parent::get_collection_params();
    }
}
