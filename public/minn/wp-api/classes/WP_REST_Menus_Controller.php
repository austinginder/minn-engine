<?php

use Minn\Content\TermRecord;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Terms_Controller.php';

/**
 * Classic navigation menus (nav_menu terms) as a class (probe
 * rest-menus-controller): readable by anyone who edits posts or theme
 * options, or by anyone when rest_menu_read_access opens them; shaped by
 * the engine's MenuObject with the menu's locations and auto add; written
 * through the engine's menu routes.
 */
class WP_REST_Menus_Controller extends WP_REST_Terms_Controller
{

    public function get_items_permissions_check($request)
    {
        $allowed = parent::get_items_permissions_check($request);
        return $allowed === true ? $this->check_has_read_only_access($request) : $allowed;
    }

    public function get_item_permissions_check($request)
    {
        $allowed = parent::get_item_permissions_check($request);
        return $allowed === true ? $this->check_has_read_only_access($request) : $allowed;
    }

    protected function get_term($id)
    {
        // The nav menu term, with its auto add.
        $term = parent::get_term($id);
        if (is_wp_error($term)) {
            return $term;
        }
        $menu = wp_get_nav_menu_object($term);
        $menu->auto_add = $this->get_menu_auto_add($menu->term_id);
        return $menu;
    }

    protected function check_has_read_only_access($request)
    {
        if (apply_filters('rest_menu_read_access', false, $request, $this) || current_user_can('edit_theme_options') || current_user_can('edit_posts')) {
            return true;
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to view menus.'), ['status' => rest_authorization_required_code()]);
    }

    /** A menu as the engine shapes it, cut to the fields and context asked for, its links on the response when asked for. */
    public function prepare_item_for_response($term, $request)
    {
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->menuObject()->view(TermRecord::fromRow((array) get_term($term->term_id, 'nav_menu', ARRAY_A)));
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->minn_expanded_links($links));
        }
        return $response;
    }

    /** @internal the engine's links as add_links takes them: rels expanded, the transport's target hints left to the server */
    private function minn_expanded_links(array $links): array
    {
        $expanded = [];
        foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $items) {
            foreach ($items as $item) {
                $expanded[$rel][] = ['href' => $item['href']] + array_diff_key((array) $item['attributes'], ['targetHints' => true]);
            }
        }
        return $expanded;
    }

    protected function prepare_links($term)
    {
        // The engine's links for the menu: one link a rel, the post type and location links lists.
        $view = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $term->term_id))->menuObject()->view(TermRecord::fromRow((array) get_term($term->term_id, 'nav_menu', ARRAY_A)));
        $links = [];
        foreach ($this->minn_expanded_links((array) ($view['_links'] ?? [])) as $rel => $items) {
            $links[$rel] = in_array($rel, ['https://api.w.org/post_type', 'https://api.w.org/menu-location'], true) ? $items : $items[0];
        }
        return $links;
    }

    public function prepare_item_for_database($request)
    {
        // The term's fields, and the menu name the menu functions take.
        $prepared = parent::prepare_item_for_database($request);
        if (isset($request['name']) && !empty($this->get_item_schema()['properties']['name'])) {
            $prepared->{'menu-name'} = $request['name'];
        }
        return $prepared;
    }

    public function create_item($request)
    {
        // Made as any term is (its id ends the location header); its locations and auto add follow, and the answer shows them.
        $response = parent::create_item($request);
        if (is_wp_error($response)) {
            return $response;
        }
        return $this->minn_settled($response, (int) substr((string) strrchr($response->get_headers()['Location'], '/'), 1), $request);
    }

    public function update_item($request)
    {
        $response = parent::update_item($request);
        if (is_wp_error($response)) {
            return $response;
        }
        return $this->minn_settled($response, (int) $request['id'], $request);
    }

    public function delete_item($request)
    {
        return parent::delete_item($request);
    }

    /** The menu's locations and auto add applied as asked, then the answer shaped again with the same status and headers. */
    private function minn_settled(WP_REST_Response $response, int $menu_id, $request)
    {
        $locations = $this->handle_locations($menu_id, $request);
        if (is_wp_error($locations)) {
            return $locations;
        }
        $this->handle_auto_add($menu_id, $request);
        $settled = rest_ensure_response($this->prepare_item_for_response(get_term($menu_id, 'nav_menu'), $request));
        $settled->set_status($response->get_status());
        $settled->set_headers($response->get_headers());
        return $settled;
    }

    protected function get_menu_auto_add($menu_id)
    {
        $options = (array) get_option('nav_menu_options', ['auto_add' => []]);
        return in_array($menu_id, $options['auto_add'] ?? [], true);
    }

    protected function handle_auto_add($menu_id, $request)
    {
        // Adds or drops the menu from the auto add list as asked; nothing asked, nothing to do.
        if (!isset($request['auto_add'])) {
            return true;
        }
        $options = (array) get_option('nav_menu_options', ['auto_add' => []]);
        $options['auto_add'] = $options['auto_add'] ?? [];
        $at = array_search($menu_id, $options['auto_add'], true);
        if ($request['auto_add'] && $at === false) {
            $options['auto_add'][] = $menu_id;
        } elseif (!$request['auto_add'] && $at !== false) {
            array_splice($options['auto_add'], $at, 1);
        }
        $updated = update_option('nav_menu_options', $options);
        do_action('wp_update_nav_menu', $menu_id);
        return $updated;
    }

    protected function get_menu_locations($menu_id)
    {
        return array_keys(array_filter(get_nav_menu_locations(), static fn ($assigned) => $assigned === $menu_id));
    }

    protected function handle_locations($menu_id, $request)
    {
        // The menu takes exactly the registered locations asked for, leaving others' alone.
        if (!isset($request['locations'])) {
            return true;
        }
        $registered = array_keys(get_registered_nav_menus());
        $asked = [];
        foreach ((array) $request['locations'] as $location) {
            if (!in_array($location, $registered, true)) {
                return new WP_Error('rest_invalid_menu_location', __('Invalid menu location.'), ['status' => 400, 'location' => $location]);
            }
            $asked[$location] = $menu_id;
        }
        $kept = array_filter(get_nav_menu_locations(), static fn ($assigned) => $assigned !== $menu_id);
        set_theme_mod('nav_menu_locations', array_merge($kept, $asked));
        return true;
    }

    /** The term schema without the count, link, taxonomy and parent, with the menu's locations and auto add (captured from the reference). */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $terms = parent::get_item_schema();
        $properties = array_intersect_key($terms['properties'], array_flip(['id', 'description', 'name', 'slug', 'meta'])) + SchemaData::of('menu-properties');
        $properties['locations']['arg_options'] = ['validate_callback' => static function ($locations, $request, $param) {
            $valid = rest_validate_request_arg($locations, $request, $param);
            if ($valid !== true) {
                return $valid;
            }
            foreach ((array) $locations as $location) {
                if (!array_key_exists($location, get_registered_nav_menus())) {
                    return new WP_Error('rest_invalid_menu_location', __('Invalid menu location.'), ['location' => $location]);
                }
            }
            return true;
        }];
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'nav_menu', 'type' => 'object', 'properties' => $properties];
        return $this->add_additional_fields_schema($this->schema);
    }
}
