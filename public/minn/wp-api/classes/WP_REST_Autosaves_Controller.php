<?php

require_once __DIR__ . '/WP_REST_Revisions_Controller.php';

/**
 * A post type's autosaves as a class (probe rest-revisions). Creating one
 * runs the engine's own autosave route; reading lists the parent's autosave
 * revisions, shaped as revisions are, with a preview link in the edit
 * context.
 */
class WP_REST_Autosaves_Controller extends WP_REST_Revisions_Controller
{
    private $parent_post_type = NULL;
    private $parent_controller = NULL;
    private $revisions_controller = NULL;
    private $parent_base = NULL;

    public function __construct($parent_post_type)
    {
        $this->parent_post_type = $parent_post_type;
        $object = get_post_type_object($parent_post_type);
        $this->parent_controller = $object->get_rest_controller() ?: new WP_REST_Posts_Controller($parent_post_type);
        $this->revisions_controller = new WP_REST_Revisions_Controller($parent_post_type);
        $this->rest_base = 'autosaves';
        $this->parent_base = !empty($object->rest_base) ? $object->rest_base : $object->name;
        $this->namespace = !empty($object->rest_namespace) ? $object->rest_namespace : 'wp/v2';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->parent_base . '/(?P<id>[\d]+)/' . $this->rest_base, [
            'args' => ['parent' => ['description' => __('The ID for the parent of the autosave.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->parent_controller->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->parent_base . '/(?P<parent>[\d]+)/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['parent' => ['description' => __('The ID for the parent of the autosave.'), 'type' => 'integer'], 'id' => ['description' => __('The ID for the autosave.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this->revisions_controller, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    protected function get_parent($parent_id)
    {
        return $this->revisions_controller->get_parent($parent_id);
    }

    public function get_items_permissions_check($request)
    {
        $parent = $this->get_parent($request['id']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        if (!current_user_can('edit_post', $parent->ID)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view autosaves of this post.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item_permissions_check($request)
    {
        // Saving an autosave is editing the post.
        if (empty($request->get_param('id'))) {
            return new WP_Error('rest_post_invalid_id', __('Invalid item ID.'), ['status' => 404]);
        }
        return $this->parent_controller->update_item_permissions_check($request);
    }

    public function create_item($request)
    {
        // The engine's autosave route writes it (one slot per author); the answer is its edit view here.
        $post = get_post($request['id']);
        if (!$post instanceof WP_Post) {
            return new WP_Error('rest_post_invalid_id', __('Invalid item ID.'), ['status' => 404]);
        }
        $write = clone $request;
        $write->set_method('POST');
        $write->set_route('/' . $this->namespace . '/' . $this->parent_base . '/' . $post->ID . '/' . $this->rest_base);
        $api = WP_REST_Server::minn_engine_api($write);
        $engine = Minn\Rest\RuntimePrepare::during($write, static fn () => $api->handleEngineOnly($write->get_route(), $write));
        $body = $engine === null ? null : json_decode($engine->body, true);
        if ($engine === null || $engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_no_route'), (string) ($body['message'] ?? __('No route was found matching the URL and request method.')), $body['data'] ?? ['status' => 404]);
        }
        // The engine wrote the row itself: the post cache forgets the old one.
        clean_post_cache((int) $body['id']);
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_post((int) $body['id']), $request));
    }

    public function get_item($request)
    {
        // The user's autosave of the parent, whichever id was asked for.
        $parent_id = (int) $request->get_param('parent');
        if ($parent_id <= 0) {
            return new WP_Error('rest_post_invalid_id', __('Invalid post parent ID.'), ['status' => 404]);
        }
        $autosave = wp_get_post_autosave($parent_id);
        if (!$autosave) {
            return new WP_Error('rest_post_no_autosave', __('There is no autosave revision for this post.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response($autosave, $request);
    }

    public function get_items($request)
    {
        $parent = $this->get_parent($request['id']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        $items = [];
        foreach (wp_get_post_revisions($parent->ID, ['check_enabled' => false]) as $revision) {
            if (str_contains($revision->post_name, "{$parent->ID}-autosave")) {
                $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($revision, $request));
            }
        }
        return rest_ensure_response($items);
    }

    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $schema = $this->revisions_controller->get_item_schema();
        $schema['properties']['preview_link'] = Minn\Rest\SchemaData::of('autosave-preview-link');
        $this->schema = $schema;
        return $this->add_additional_fields_schema($this->schema);
    }

    /**
     * Saves the user's autosave of a post through the engine's one slot per
     * author; fields the same as the post's leave an existing autosave as it
     * is, and answer its id.
     */
    public function create_post_autosave($post_data, $meta = [])
    {
        $post_id = (int) $post_data['ID'];
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        $existing = wp_get_post_autosave($post_id, $user_id);
        $fields = ['post_title', 'post_content', 'post_excerpt'];
        $same = $post && array_filter($fields, static fn ($field) => array_key_exists($field, (array) $post_data) && normalize_whitespace((string) $post_data[$field]) !== normalize_whitespace((string) $post->{$field})) === [];
        if ($existing && $same) {
            return $existing->ID;
        }
        $saved = WP_REST_Server::minn_engine_api(new WP_REST_Request('POST', '/' . $this->namespace . '/' . $this->parent_base . '/' . $post_id . '/' . $this->rest_base))->saveAutosave($post_id, $user_id, (string) ($post_data['post_title'] ?? ''), (string) ($post_data['post_content'] ?? ''), (string) ($post_data['post_excerpt'] ?? ''));
        clean_post_cache($saved);
        return $saved;
    }

    /** A revision's shape, with where to preview the draft in the edit context. */
    public function prepare_item_for_response($item, $request)
    {
        $response = $this->revisions_controller->prepare_item_for_response($item, $request);
        if (($request['context'] ?? 'view') === 'edit' && rest_is_field_included('preview_link', $this->get_fields_for_response($request))) {
            $data = $response->get_data();
            $data['preview_link'] = get_preview_post_link($item->post_parent, ['preview_id' => $item->post_parent, 'preview_nonce' => wp_create_nonce('post_preview_' . $item->post_parent)]);
            $response->set_data($data);
        }
        return apply_filters('rest_prepare_autosave', $response, $item, $request);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }
}
