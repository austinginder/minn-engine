<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/menu-locations as a class (probe rest-block-controllers): the
 * registered locations keyed by name, and one by name, each with the menu
 * assigned to it (0 for none). Reading needs edit_theme_options, unless
 * rest_menu_read_access opens it. A location links to the menu assigned
 * when that menu exists. The engine answers the route itself; plugins call
 * this class directly.
 */
class WP_REST_Menu_Locations_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'menu-locations';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<location>[\w-]+)', [
            'args' => ['location' => ['description' => __('An alphanumeric identifier for the menu location.'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return $this->check_has_read_only_access($request);
    }

    public function get_items($request)
    {
        $data = [];
        foreach (get_registered_nav_menus() as $name => $description) {
            $data[$name] = $this->prepare_response_for_collection($this->prepare_item_for_response((object) ['name' => $name, 'description' => $description], $request));
        }
        return rest_ensure_response($data);
    }

    public function get_item_permissions_check($request)
    {
        return $this->check_has_read_only_access($request);
    }

    public function get_item($request)
    {
        $registered = get_registered_nav_menus();
        if (!array_key_exists($request['location'], $registered)) {
            return new WP_Error('rest_menu_location_invalid', __('Invalid menu location.'), ['status' => 404]);
        }
        return rest_ensure_response($this->prepare_item_for_response((object) ['name' => $request['location'], 'description' => $registered[$request['location']]], $request));
    }

    /** Whether the caller may read menu locations: when rest_menu_read_access says so, or with edit_theme_options. */
    protected function check_has_read_only_access($request)
    {
        if (apply_filters('rest_menu_read_access', false, $request, $this)) {
            return true;
        }
        if (current_user_can('edit_theme_options')) {
            return true;
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to view menu locations.'), ['status' => rest_authorization_required_code()]);
    }

    /** A location as REST shows it: its name, description and the menu assigned (from the theme's locations, not the item), filtered by fields and context. */
    public function prepare_item_for_response($item, $request)
    {
        $fields = $this->get_fields_for_response($request);
        $values = ['name' => $item->name, 'description' => $item->description ?? '', 'menu' => (int) (get_nav_menu_locations()[$item->name] ?? 0)];
        $data = [];
        foreach ($values as $field => $value) {
            if (rest_is_field_included($field, $fields)) {
                $data[$field] = $value;
            }
        }
        $data = $this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view');
        $response = rest_ensure_response($data);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($item));
        }
        return apply_filters('rest_prepare_menu_location', $response, $item, $request);
    }

    protected function prepare_links($location)
    {
        $base = sprintf('%s/%s', $this->namespace, $this->rest_base);
        $links = ['self' => ['href' => rest_url(trailingslashit($base) . $location->name)], 'collection' => ['href' => rest_url($base)]];
        $menu = (int) (get_nav_menu_locations()[$location->name] ?? 0);
        $route = $menu > 0 ? rest_get_route_for_term($menu) : '';
        if ($route !== '') {
            $links['https://api.w.org/menu'][] = ['href' => rest_url($route), 'embeddable' => true];
        }
        return $links;
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('menu-location');
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }
}
