<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/sidebars as a class (probe rest-sidebar-classes): the sidebars the
 * widgets option lists after retrieve_widgets, and one by id, each with
 * its wrapping markup, status (active when registered, inactive under a
 * block theme) and the registered widgets it holds. Someone who can edit
 * theme options reads and saves any; anyone reads a sidebar shown in REST.
 * Saving a sidebar's widgets takes them from every other sidebar and sends
 * the ones it drops to the inactive widgets. The engine answers the route
 * itself; plugins call this class directly.
 */
class WP_REST_Sidebars_Controller extends WP_REST_Controller
{
    protected $widgets_retrieved = false;

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'sidebars';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\w-]+)', [
            'args' => ['id' => ['description' => __('The id of a registered sidebar'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /** True when the caller may read any one sidebar (one shown in REST), otherwise the theme-options check. */
    public function get_items_permissions_check($request)
    {
        $this->retrieve_widgets();
        foreach (array_keys(wp_get_sidebars_widgets()) as $id) {
            $sidebar = $this->get_sidebar($id);
            if ($sidebar && $this->check_read_permission($sidebar)) {
                return true;
            }
        }
        return $this->do_permissions_check();
    }

    public function get_items($request)
    {
        $this->retrieve_widgets();
        $allowed = !is_wp_error($this->do_permissions_check());
        $data = [];
        foreach (array_keys(wp_get_sidebars_widgets()) as $id) {
            $sidebar = $this->get_sidebar($id);
            if (!$sidebar || (!$allowed && !$this->check_read_permission($sidebar))) {
                continue;
            }
            $data[] = $this->prepare_response_for_collection($this->prepare_item_for_response($sidebar, $request));
        }
        return rest_ensure_response($data);
    }

    public function get_item_permissions_check($request)
    {
        $this->retrieve_widgets();
        $sidebar = $this->get_sidebar($request['id']);
        return $sidebar && $this->check_read_permission($sidebar) ? true : $this->do_permissions_check();
    }

    /** Whether anyone may read a sidebar: one registered to be shown in REST. */
    protected function check_read_permission($sidebar)
    {
        return !empty($sidebar['show_in_rest']);
    }

    public function get_item($request)
    {
        $this->retrieve_widgets();
        $sidebar = $this->get_sidebar($request['id']);
        return $sidebar ? $this->prepare_item_for_response($sidebar, $request) : new WP_Error('rest_sidebar_not_found', __('No sidebar exists with that id.'), ['status' => 404]);
    }

    public function update_item_permissions_check($request)
    {
        return $this->do_permissions_check();
    }

    /** Saves the widgets given, in order: taken from the other sidebars, those this one drops sent to the inactive widgets; answered in the edit context. */
    public function update_item($request)
    {
        if (isset($request['widgets'])) {
            $id = $request['id'];
            $wanted = (array) $request['widgets'];
            $sidebars = wp_get_sidebars_widgets();
            foreach ($sidebars as $sidebar_id => $widgets) {
                foreach ((array) $widgets as $i => $widget_id) {
                    if ($sidebar_id !== $id && in_array($widget_id, $wanted, true)) {
                        unset($sidebars[$sidebar_id][$i]);
                    }
                    if ($sidebar_id === $id && !in_array($widget_id, $wanted, true)) {
                        $sidebars['wp_inactive_widgets'][] = $widget_id;
                    }
                }
            }
            $sidebars[$id] = $wanted;
            wp_set_sidebars_widgets($sidebars);
        }
        $request['context'] = 'edit';
        $sidebar = $this->get_sidebar($request['id']);
        do_action('rest_save_sidebar', $sidebar, $request);
        return $this->prepare_item_for_response($sidebar, $request);
    }

    /** Whether the caller may edit theme options, the capability every sidebar answers to. */
    protected function do_permissions_check()
    {
        if (!current_user_can('edit_theme_options')) {
            return new WP_Error('rest_cannot_manage_widgets', __('Sorry, you are not allowed to manage widgets on this site.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    protected function get_sidebar($id)
    {
        return wp_get_sidebar($id);
    }

    /** retrieve_widgets once per controller. */
    protected function retrieve_widgets()
    {
        if (!$this->widgets_retrieved) {
            retrieve_widgets();
            $this->widgets_retrieved = true;
        }
    }

    /**
     * A sidebar as REST shows it: every schema property it has a valid
     * value for, or that property's default, whatever fields were asked
     * for; the widgets only when asked for, those registered; links when
     * they are wanted.
     */
    public function prepare_item_for_response($item, $request)
    {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $id = $item['id'];
        $sidebar = ['id' => $id];
        if (isset($wp_registered_sidebars[$id])) {
            $registered = $wp_registered_sidebars[$id];
            $sidebar += ['status' => 'active', 'name' => $registered['name'] ?? '', 'description' => wp_sidebar_description($id), 'class' => $registered['class'] ?? '', 'before_widget' => $registered['before_widget'] ?? '', 'after_widget' => $registered['after_widget'] ?? '', 'before_title' => $registered['before_title'] ?? '', 'after_title' => $registered['after_title'] ?? ''];
        } else {
            $sidebar += ['status' => 'inactive', 'name' => $item['name'] ?? '', 'description' => '', 'class' => ''];
        }
        if (wp_is_block_theme()) {
            $sidebar['status'] = 'inactive';
        }
        $fields = $this->get_fields_for_response($request);
        if (rest_is_field_included('widgets', $fields)) {
            $sidebar['widgets'] = array_values(array_filter((array) (wp_get_sidebars_widgets()[$id] ?? []), static fn ($widget_id) => isset($wp_registered_widgets[$widget_id])));
        }
        $data = [];
        foreach ($this->get_item_schema()['properties'] as $property => $schema) {
            if (isset($sidebar[$property]) && rest_validate_value_from_schema($sidebar[$property], $schema) === true) {
                $data[$property] = $sidebar[$property];
            } elseif (isset($schema['default'])) {
                $data[$property] = $schema['default'];
            }
        }
        $response = rest_ensure_response($this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view'));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($item));
        }
        return apply_filters('rest_prepare_sidebar', $response, $item, $request);
    }

    protected function prepare_links($sidebar)
    {
        return [
            'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))],
            'self' => ['href' => rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, $sidebar['id']))],
            'https://api.w.org/widget' => ['href' => add_query_arg('sidebar', $sidebar['id'], rest_url('/wp/v2/widgets')), 'embeddable' => true],
        ];
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('sidebar');
        return $this->add_additional_fields_schema($this->schema);
    }
}
