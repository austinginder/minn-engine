<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/block-renderer as a class (probe rest-block-controllers): a
 * dynamic block rendered from the attributes given, inside the post named
 * by post_id when there is one (it becomes the global post, so the block's
 * postId context is that post). Someone who can edit posts may render, or
 * with a post named, someone who can edit that post. A block that is not
 * registered or renders nothing of its own is block_invalid. The engine
 * answers the route itself; plugins call this class directly.
 */
class WP_REST_Block_Renderer_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'block-renderer';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<name>[a-z0-9-]+/[a-z0-9-]+)', [
            'args' => ['name' => ['description' => __('Unique registered name for the block.'), 'type' => 'string']],
            [
                'methods' => [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE],
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => [
                    'context' => $this->get_context_param(['default' => 'view']),
                    'attributes' => ['description' => __('Attributes for the block.'), 'type' => 'object', 'default' => [], 'validate_callback' => [$this, 'minn_validate_attributes'], 'sanitize_callback' => [$this, 'minn_sanitize_attributes']],
                    'post_id' => ['description' => __('ID of the post context.'), 'type' => 'integer'],
                ],
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_item_permissions_check($request)
    {
        $post_id = isset($request['post_id']) ? (int) $request['post_id'] : 0;
        if ($post_id > 0) {
            $post = get_post($post_id);
            if (!$post || !current_user_can('edit_post', $post->ID)) {
                return new WP_Error('block_cannot_read', __('Sorry, you are not allowed to read blocks of this post.'), ['status' => rest_authorization_required_code()]);
            }
            return true;
        }
        if (!current_user_can('edit_posts')) {
            return new WP_Error('block_cannot_read', __('Sorry, you are not allowed to read blocks as this user.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_item($request)
    {
        $post_id = isset($request['post_id']) ? (int) $request['post_id'] : 0;
        if ($post_id > 0) {
            $GLOBALS['post'] = get_post($post_id);
            setup_postdata($GLOBALS['post']);
        }
        $registered = WP_Block_Type_Registry::get_instance()->get_registered($request['name']);
        if ($registered === null || !$registered->is_dynamic()) {
            return new WP_Error('block_invalid', __('Invalid block.'), ['status' => 404]);
        }
        $block = ['blockName' => $request['name'], 'attrs' => $request->get_param('attributes'), 'innerHTML' => '', 'innerContent' => []];
        return rest_ensure_response(['rendered' => render_block($block)]);
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('rendered-block');
        return $this->add_additional_fields_schema($this->schema);
    }

    /** @internal the attributes judged against the block's own, for the route's argument */
    public function minn_validate_attributes($value, $request, $param)
    {
        $block = WP_Block_Type_Registry::get_instance()->get_registered($request['name']);
        return $block ? rest_validate_value_from_schema($value, ['type' => 'object', 'properties' => $block->get_attributes(), 'additionalProperties' => false], $param) : true;
    }

    /** @internal the attributes as the block's own schema keeps them, for the route's argument */
    public function minn_sanitize_attributes($value, $request, $param)
    {
        $block = WP_Block_Type_Registry::get_instance()->get_registered($request['name']);
        return $block ? rest_sanitize_value_from_schema($value, ['type' => 'object', 'properties' => $block->get_attributes(), 'additionalProperties' => false], $param) : $value;
    }
}
