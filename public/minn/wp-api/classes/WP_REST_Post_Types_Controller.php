<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/types as a class (probe rest-registry-controllers): the post types
 * shown in REST, keyed by name, and one by name. Edit context needs the
 * type's edit_posts capability (a list leaves out the others, and refuses
 * a caller who may edit none); a type hidden from REST refuses outright.
 * The engine answers the route itself; plugins call this class directly.
 */
class WP_REST_Post_Types_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'types';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<type>[\w-]+)', [
            'args' => ['type' => ['description' => __('An alphanumeric identifier for the post type.'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => '__return_true', 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        if ($request['context'] !== 'edit') {
            return true;
        }
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to edit posts in this post type.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        $data = [];
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if ($request['context'] === 'edit' && !current_user_can($post_type->cap->edit_posts)) {
                continue;
            }
            $data[$post_type->name] = $this->prepare_response_for_collection($this->prepare_item_for_response($post_type, $request));
        }
        return rest_ensure_response($data);
    }

    public function get_item($request)
    {
        $post_type = get_post_type_object($request['type']);
        if (!$post_type) {
            return new WP_Error('rest_type_invalid', __('Invalid post type.'), ['status' => 404]);
        }
        if (empty($post_type->show_in_rest)) {
            return new WP_Error('rest_cannot_read_type', __('Cannot view post type.'), ['status' => rest_authorization_required_code()]);
        }
        if ($request['context'] === 'edit' && !current_user_can($post_type->cap->edit_posts)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit posts in this post type.'), ['status' => rest_authorization_required_code()]);
        }
        return $this->prepare_item_for_response($post_type, $request);
    }

    /** A type as REST shows it, the fields asked for in the reference's order, then filtered by context; linked to the collection and its items. */
    public function prepare_item_for_response($item, $request)
    {
        $post_type = $item;
        $fields = $this->get_fields_for_response($request);
        $values = [
            'capabilities' => static fn () => $post_type->cap,
            'description' => static fn () => $post_type->description,
            'hierarchical' => static fn () => $post_type->hierarchical,
            'has_archive' => static fn () => $post_type->has_archive,
            'visibility' => static fn () => ['show_in_nav_menus' => (bool) $post_type->show_in_nav_menus, 'show_ui' => (bool) $post_type->show_ui],
            'viewable' => static fn () => is_post_type_viewable($post_type),
            'labels' => static fn () => $post_type->labels,
            'name' => static fn () => $post_type->label,
            'slug' => static fn () => $post_type->name,
            'icon' => static fn () => $post_type->menu_icon,
            'supports' => static fn () => get_all_post_type_supports($post_type->name),
            'taxonomies' => static fn () => array_values(wp_list_pluck(wp_list_filter(get_object_taxonomies($post_type->name, 'objects'), ['show_in_rest' => true]), 'name')),
            'rest_base' => static fn () => !empty($post_type->rest_base) ? $post_type->rest_base : $post_type->name,
            'rest_namespace' => static fn () => !empty($post_type->rest_namespace) ? $post_type->rest_namespace : 'wp/v2',
            'template' => static fn () => $post_type->template ?? [],
            'template_lock' => static fn () => !empty($post_type->template_lock) ? $post_type->template_lock : false,
        ];
        $data = [];
        foreach ($values as $field => $value) {
            if (rest_is_field_included($field, $fields)) {
                $data[$field] = $value();
            }
        }
        $data = $this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view');
        $response = rest_ensure_response($data);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($post_type));
        }
        return apply_filters('rest_prepare_post_type', $response, $post_type, $request);
    }

    protected function prepare_links($post_type)
    {
        return [
            'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))],
            'https://api.w.org/items' => ['href' => rest_url(rest_get_route_for_post_type_items($post_type->name))],
        ];
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('type');
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }
}
