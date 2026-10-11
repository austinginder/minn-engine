<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Posts_Controller.php';

/**
 * Classic menu items (nav_menu_item posts) as a class (probe
 * rest-menus-controller): readable as menus are, shaped by the engine's
 * MenuItemObject, written through the engine's menu item routes; the
 * menu item fields a request makes, as the menu functions take them.
 */
class WP_REST_Menu_Items_Controller extends WP_REST_Posts_Controller
{

    protected function get_nav_menu_item($id)
    {
        $post = $this->get_post($id);
        return is_wp_error($post) ? $post : wp_setup_nav_menu_item($post);
    }

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

    protected function check_has_read_only_access($request)
    {
        if (apply_filters('rest_menu_read_access', false, $request, $this) || current_user_can('edit_theme_options') || current_user_can('edit_posts')) {
            return true;
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to view menu items.'), ['status' => rest_authorization_required_code()]);
    }

    public function create_item($request)
    {
        return parent::create_item($request);
    }

    public function update_item($request)
    {
        return parent::update_item($request);
    }

    public function delete_item($request)
    {
        // Menu items are never trashed; a forced delete answers the item as it was, in the view context.
        $item = $this->get_nav_menu_item($request['id']);
        if (is_wp_error($item)) {
            return $item;
        }
        $request->set_param('context', 'view');
        $previous = !empty($request['force']) ? $this->prepare_item_for_response(get_post($item->ID), $request) : null;
        $call = clone $request;
        $call->set_method('DELETE');
        $call->set_route('/' . $this->namespace . '/' . $this->rest_base . '/' . $item->ID);
        $api = WP_REST_Server::minn_engine_api($call);
        $engine = Minn\Rest\RuntimePrepare::during($call, static fn () => $api->handleEngineOnly($call->get_route(), $call));
        $body = $engine === null ? null : json_decode($engine->body, true);
        if ($engine === null || $engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_no_route'), (string) ($body['message'] ?? ''), $body['data'] ?? ['status' => 404]);
        }
        $response = new WP_REST_Response();
        $response->set_data(['deleted' => true, 'previous' => $previous->get_data()]);
        return $response;
    }

    /**
     * The menu item fields a request makes, as wp_update_nav_menu_item takes
     * them: an existing item's own values (its menu last) or a new item's
     * defaults, the request's over them, the object found from a post or a
     * term, a custom link needing a title and a URL, classes and XFN joined,
     * any status but publish as draft; then rest_pre_insert_nav_menu_item.
     */
    protected function prepare_item_for_database($request)
    {
        $existing = $this->get_nav_menu_item($request['id']);
        $item = is_wp_error($existing) ? [
            'menu-id' => 0, 'menu-item-db-id' => 0, 'menu-item-object-id' => 0, 'menu-item-object' => '', 'menu-item-parent-id' => 0, 'menu-item-position' => 1, 'menu-item-type' => 'custom', 'menu-item-title' => '', 'menu-item-url' => '',
            'menu-item-description' => '', 'menu-item-attr-title' => '', 'menu-item-target' => '', 'menu-item-classes' => [], 'menu-item-xfn' => [], 'menu-item-status' => 'publish',
        ] : [
            'menu-item-db-id' => $request['id'], 'menu-item-object-id' => $existing->object_id, 'menu-item-object' => $existing->object, 'menu-item-parent-id' => $existing->menu_item_parent, 'menu-item-position' => (int) $existing->menu_order === 0 ? 1 : $existing->menu_order,
            'menu-item-type' => $existing->type, 'menu-item-title' => $existing->title, 'menu-item-url' => $existing->url, 'menu-item-description' => $existing->description, 'menu-item-attr-title' => $existing->attr_title, 'menu-item-target' => $existing->target,
            'menu-item-classes' => $existing->classes, 'menu-item-xfn' => explode(' ', (string) $existing->xfn), 'menu-item-status' => $existing->post_status, 'menu-id' => $this->get_menu_id($request['id']),
        ];
        foreach (['id' => 'menu-item-db-id', 'object_id' => 'menu-item-object-id', 'object' => 'menu-item-object', 'parent' => 'menu-item-parent-id', 'menu_order' => 'menu-item-position', 'type' => 'menu-item-type', 'url' => 'menu-item-url', 'description' => 'menu-item-description', 'attr_title' => 'menu-item-attr-title', 'target' => 'menu-item-target', 'classes' => 'menu-item-classes', 'xfn' => 'menu-item-xfn', 'status' => 'menu-item-status'] as $field => $key) {
            if (isset($request[$field])) {
                $item[$key] = $request[$field];
            }
        }
        $menus = get_taxonomy('nav_menu');
        $base = !empty($menus->rest_base) ? $menus->rest_base : $menus->name;
        if (!empty($request[$base])) {
            $item['menu-id'] = absint($request[$base]);
        }
        if (isset($request['title'])) {
            $title = is_string($request['title']) ? $request['title'] : ($request['title']['raw'] ?? '');
            if ($title !== '' || is_string($request['title'])) {
                $item['menu-item-title'] = $title;
            }
        }
        $error = $this->minn_item_problems($item);
        if ($error->has_errors()) {
            return $error;
        }
        $item = $this->minn_item_object($item);
        foreach (['menu-item-xfn', 'menu-item-classes'] as $key) {
            $item[$key] = implode(' ', (array) $item[$key]);
        }
        if ($item['menu-item-status'] !== 'publish') {
            $item['menu-item-status'] = 'draft';
        }
        return apply_filters('rest_pre_insert_nav_menu_item', (object) $item, $request);
    }

    /** @internal what keeps an item from saving: a missing post, term or post type for its object; a custom link without a title or URL */
    private function minn_item_problems(array $item): WP_Error
    {
        $error = new WP_Error();
        if (!$item['menu-item-object'] && $item['menu-item-type'] === 'taxonomy' && !get_term(absint($item['menu-item-object-id'])) instanceof WP_Term) {
            $error->add('rest_term_invalid_id', __('Invalid term ID.'), ['status' => 400]);
        }
        if (!$item['menu-item-object'] && $item['menu-item-type'] === 'post_type' && !get_post(absint($item['menu-item-object-id']))) {
            $error->add('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 400]);
        }
        if ($item['menu-item-type'] === 'post_type_archive' && !get_post_type_object($item['menu-item-object'] ?: '')) {
            $error->add('rest_post_invalid_type', __('Invalid post type.'), ['status' => 400]);
        }
        if ($item['menu-item-type'] === 'custom' && $item['menu-item-title'] === '') {
            $error->add('rest_title_required', __('The title is required when using a custom menu item type.'), ['status' => 400]);
        }
        if ($item['menu-item-type'] === 'custom' && empty($item['menu-item-url'])) {
            $error->add('rest_url_required', __('The url is required when using a custom menu item type.'), ['status' => 400]);
        }
        return $error;
    }

    /** @internal an item's object named from the post or term it points at, when it names none */
    private function minn_item_object(array $item): array
    {
        if (!$item['menu-item-object'] && $item['menu-item-type'] === 'taxonomy') {
            $item['menu-item-object'] = get_term_field('taxonomy', get_term(absint($item['menu-item-object-id'])));
        } elseif (!$item['menu-item-object'] && $item['menu-item-type'] === 'post_type') {
            $item['menu-item-object'] = get_post_type(absint($item['menu-item-object-id']));
        }
        return $item;
    }

    /** A menu item as the engine shapes it, cut to the fields and context asked for, its links on the response when asked for. */
    public function prepare_item_for_response($item, $request)
    {
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->menuItemView((int) $item->ID, $context);
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $expanded = [];
            foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $list) {
                foreach ($list as $link) {
                    $expanded[$rel][] = ['href' => $link['href']] + array_diff_key((array) $link['attributes'], ['targetHints' => true]);
                }
            }
            $response->add_links($expanded);
        }
        return $response;
    }

    protected function prepare_links($post)
    {
        // The engine's links for the item: one link a rel, the term and object links lists.
        $view = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $post->ID))->menuItemView((int) $post->ID, 'view');
        $links = [];
        foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => (array) ($view['_links'] ?? [])])->get_links() as $rel => $list) {
            $items = array_map(static fn ($link) => ['href' => $link['href']] + array_diff_key((array) $link['attributes'], ['targetHints' => true]), $list);
            $links[$rel] = in_array($rel, ['https://api.w.org/term', 'https://api.w.org/menu-item-object'], true) ? $items : $items[0];
        }
        return $links;
    }

    protected function get_schema_links()
    {
        // The post actions, and where the item's object is.
        $links = parent::get_schema_links();
        $links[] = ['rel' => 'https://api.w.org/menu-item-object', 'title' => __('Get linked object.'), 'href' => rest_url("{$this->namespace}/{$this->rest_base}/{id}"), 'targetSchema' => ['type' => 'object', 'properties' => ['object' => ['type' => 'integer']]]];
        return $links;
    }

    /** The menu item schema (captured from the reference), the post meta, the schema links; then the fields plugins register. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = SchemaData::of('menu-item-properties');
        $properties['status']['enum'] = array_keys(get_post_stati(['internal' => false]));
        $properties['meta'] = $this->meta->get_field_schema();
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => $this->post_type, 'type' => 'object', 'properties' => $properties, 'links' => $this->get_schema_links()];
        return $this->add_additional_fields_schema($this->schema);
    }

    /** The posts list's parameters without the author, ordered also by menu order, with the menus to keep and leave out. */
    public function get_collection_params()
    {
        $params = parent::get_collection_params();
        $params['per_page']['default'] = 100;
        $params['menu_order'] = ['description' => __('Limit result set to posts with a specific menu_order value.'), 'type' => 'integer'];
        $params['order'] = ['description' => __('Order sort attribute ascending or descending.'), 'type' => 'string', 'default' => 'asc', 'enum' => ['asc', 'desc']];
        $params['orderby'] = ['description' => __('Sort collection by object attribute.'), 'type' => 'string', 'default' => 'menu_order', 'enum' => ['author', 'date', 'id', 'include', 'modified', 'parent', 'relevance', 'slug', 'include_slugs', 'title', 'menu_order']];
        return $params;
    }

    protected function prepare_items_query($prepared_args = [], $request = NULL)
    {
        // The posts list's query, menu items cached as they load, ordered by menu order when asked.
        $query_args = parent::prepare_items_query($prepared_args, $request);
        if (isset($query_args['orderby'], $request['orderby']) && $request['orderby'] === 'menu_order') {
            $query_args['orderby'] = 'menu_order';
        }
        $query_args['update_menu_item_cache'] = true;
        return $query_args;
    }

    protected function get_menu_id($menu_item_id)
    {
        $menus = wp_get_post_terms($menu_item_id, 'nav_menu', ['fields' => 'ids']);
        return empty($menus) || is_wp_error($menus) ? 0 : (int) $menus[0];
    }
}
