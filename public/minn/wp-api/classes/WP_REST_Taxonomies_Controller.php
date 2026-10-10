<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/taxonomies as a class (probe rest-registry-controllers): the
 * taxonomies shown in REST, all or a post type's, keyed by name (an empty
 * list is an object), and one by name. Edit context needs a taxonomy's
 * assign_terms capability: a list leaves out the others, and the
 * permission check refuses a caller who may assign none. The engine
 * answers the route itself; plugins call this class directly.
 */
class WP_REST_Taxonomies_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'taxonomies';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<taxonomy>[\w-]+)', [
            'args' => ['taxonomy' => ['description' => __('An alphanumeric identifier for the taxonomy.'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        if ($request['context'] !== 'edit') {
            return true;
        }
        foreach ($this->minn_taxonomies($request) as $taxonomy) {
            if (!empty($taxonomy->show_in_rest) && current_user_can($taxonomy->cap->assign_terms)) {
                return true;
            }
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to manage terms in this taxonomy.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        $data = [];
        foreach ($this->minn_taxonomies($request) as $name => $taxonomy) {
            if (empty($taxonomy->show_in_rest) || ($request['context'] === 'edit' && !current_user_can($taxonomy->cap->assign_terms))) {
                continue;
            }
            $data[$name] = $this->prepare_response_for_collection($this->prepare_item_for_response($taxonomy, $request));
        }
        return rest_ensure_response($data === [] ? (object) [] : $data);
    }

    public function get_item_permissions_check($request)
    {
        $taxonomy = get_taxonomy($request['taxonomy']);
        if (!$taxonomy) {
            return true;
        }
        if (empty($taxonomy->show_in_rest)) {
            return false;
        }
        if ($request['context'] === 'edit' && !current_user_can($taxonomy->cap->assign_terms)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to manage terms in this taxonomy.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_item($request)
    {
        $taxonomy = get_taxonomy($request['taxonomy']);
        if (empty($taxonomy)) {
            return new WP_Error('rest_taxonomy_invalid', __('Invalid taxonomy.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response($taxonomy, $request);
    }

    /** A taxonomy as REST shows it, the fields asked for in the reference's order, then filtered by context; linked to the collection and its terms. */
    public function prepare_item_for_response($item, $request)
    {
        $taxonomy = $item;
        $fields = $this->get_fields_for_response($request);
        $values = [
            'name' => static fn () => $taxonomy->label,
            'slug' => static fn () => $taxonomy->name,
            'capabilities' => static fn () => $taxonomy->cap,
            'description' => static fn () => $taxonomy->description,
            'labels' => static fn () => $taxonomy->labels,
            'types' => static fn () => array_values($taxonomy->object_type),
            'show_cloud' => static fn () => $taxonomy->show_tagcloud,
            'hierarchical' => static fn () => $taxonomy->hierarchical,
            'rest_base' => static fn () => !empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name,
            // A taxonomy hidden from REST shows its namespace as it is (false), as on the reference.
            'rest_namespace' => static fn () => $taxonomy->rest_namespace,
            'visibility' => static fn () => ['public' => (bool) $taxonomy->public, 'publicly_queryable' => (bool) $taxonomy->publicly_queryable, 'show_admin_column' => (bool) $taxonomy->show_admin_column, 'show_in_nav_menus' => (bool) $taxonomy->show_in_nav_menus, 'show_in_quick_edit' => (bool) $taxonomy->show_in_quick_edit, 'show_ui' => (bool) $taxonomy->show_ui],
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
            $response->add_links($this->prepare_links($taxonomy));
        }
        return apply_filters('rest_prepare_taxonomy', $response, $taxonomy, $request);
    }

    protected function prepare_links($taxonomy)
    {
        return [
            'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))],
            'https://api.w.org/items' => ['href' => rest_url(rest_get_route_for_taxonomy_items($taxonomy->name))],
        ];
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('taxonomy');
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view']), 'type' => ['description' => __('Limit results to taxonomies associated with a specific post type.'), 'type' => 'string']];
    }

    /** The taxonomies a request asks after: a post type's when it names one, otherwise all. @return array<string, WP_Taxonomy> */
    private function minn_taxonomies($request): array
    {
        return !empty($request['type']) ? get_object_taxonomies($request['type'], 'objects') : get_taxonomies([], 'objects');
    }
}
