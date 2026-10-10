<?php

use Minn\Rest\BlockTypesController;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/block-types as a class (probe rest-block-controllers): every
 * registered block type in registration order, or a namespace's, and one
 * by name, for anyone who may edit posts of a type shown in REST. A type
 * carries its registration, its styles (its own, then those registered for
 * it) and its variations; a dynamic one links to its renderer. The engine
 * answers the route itself; plugins call this class directly.
 */
class WP_REST_Block_Types_Controller extends WP_REST_Controller
{
    const NAME_PATTERN = '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$';

    protected $block_registry;
    protected $style_registry;

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'block-types';
        $this->block_registry = WP_Block_Type_Registry::get_instance();
        $this->style_registry = WP_Block_Styles_Registry::get_instance();
    }

    public function register_routes()
    {
        $read = ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()];
        register_rest_route($this->namespace, '/' . $this->rest_base, [$read, 'schema' => [$this, 'get_public_item_schema']]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<namespace>[a-zA-Z0-9_-]+)', [$read, 'schema' => [$this, 'get_public_item_schema']]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<namespace>[a-zA-Z0-9_-]+)/(?P<name>[a-zA-Z0-9_-]+)', [
            'args' => ['name' => ['description' => __('Block name.'), 'type' => 'string'], 'namespace' => ['description' => __('Block namespace.'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return $this->check_read_permission();
    }

    public function get_items($request)
    {
        $namespace = (string) ($request['namespace'] ?? '');
        $data = [];
        foreach ($this->block_registry->get_all_registered() as $name => $block_type) {
            if ($namespace !== '' && strtok((string) $name, '/') !== $namespace) {
                continue;
            }
            $data[] = $this->prepare_response_for_collection($this->prepare_item_for_response($block_type, $request));
        }
        return rest_ensure_response($data);
    }

    /** Read permission first, then the block must exist. */
    public function get_item_permissions_check($request)
    {
        $check = $this->check_read_permission();
        if (is_wp_error($check)) {
            return $check;
        }
        $block_type = $this->get_block(sprintf('%s/%s', $request['namespace'], $request['name']));
        return is_wp_error($block_type) ? $block_type : true;
    }

    /** Whether the caller may edit posts, of any type shown in REST. */
    protected function check_read_permission()
    {
        if (current_user_can('edit_posts')) {
            return true;
        }
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return new WP_Error('rest_block_type_cannot_view', __('Sorry, you are not allowed to manage block types.'), ['status' => rest_authorization_required_code()]);
    }

    protected function get_block($name)
    {
        $block_type = $this->block_registry->get_registered($name);
        return empty($block_type) ? new WP_Error('rest_block_type_invalid', __('Invalid block type.'), ['status' => 404]) : $block_type;
    }

    public function get_item($request)
    {
        $block_type = $this->get_block(sprintf('%s/%s', $request['namespace'], $request['name']));
        return is_wp_error($block_type) ? $block_type : rest_ensure_response($this->prepare_item_for_response($block_type, $request));
    }

    /** A block type as REST shows it (Minn\Rest\BlockTypesController::fields), the fields asked for, then filtered by context. */
    public function prepare_item_for_response($item, $request)
    {
        $block_type = $item;
        $fields = $this->get_fields_for_response($request);
        $data = array_filter(BlockTypesController::fields($block_type), static fn ($field) => rest_is_field_included($field, $fields), ARRAY_FILTER_USE_KEY);
        $data = $this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view');
        $response = rest_ensure_response($data);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($block_type));
        }
        return apply_filters('rest_prepare_block_type', $response, $block_type, $request);
    }

    protected function prepare_links($block_type)
    {
        $base = sprintf('%s/%s', $this->namespace, $this->rest_base);
        $links = [
            'collection' => ['href' => rest_url($base)],
            'self' => ['href' => rest_url($base . '/' . $block_type->name)],
            'up' => ['href' => rest_url($base . '/' . strtok((string) $block_type->name, '/'))],
        ];
        if ($block_type->is_dynamic()) {
            $links['https://api.w.org/render-block'] = ['href' => add_query_arg('context', 'edit', rest_url('wp/v2/block-renderer/' . $block_type->name))];
        }
        return $links;
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('block-type');
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view']), 'namespace' => ['description' => __('Block namespace.'), 'type' => 'string']];
    }
}
