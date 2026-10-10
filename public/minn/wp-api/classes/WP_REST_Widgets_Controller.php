<?php

use Minn\Rest\SchemaData;
use Minn\Rest\WidgetObject;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/widgets as a class (probe rest-widgets-class): the registered
 * widgets of every sidebar or one, a widget up close, created under the
 * next free number for its type (raw settings when the type shows them in
 * REST, encoded ones with a matching hash, or form data), updated and
 * moved, and deleted (with force) or sent to the inactive widgets.
 * Someone who can edit theme options may do all of it; anyone may read
 * the widgets of a sidebar shown in REST. The widgets are retrieved once
 * per controller. The engine answers the route itself; plugins call this
 * class directly.
 */
class WP_REST_Widgets_Controller extends WP_REST_Controller
{
    protected $widgets_retrieved = false;
    protected $allow_batch = ['v1' => true];

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'widgets';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema()],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, $this->rest_base . '/(?P<id>[\w\-]+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['description' => __('Whether to force removal of the widget, or move it to the inactive sidebar.'), 'type' => 'boolean']]],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /** True when the caller may read the sidebar asked for, or any sidebar (one shown in REST); otherwise the theme-options check. */
    public function get_items_permissions_check($request)
    {
        $this->retrieve_widgets();
        if (isset($request['sidebar']) && $this->check_read_sidebar_permission($request['sidebar'])) {
            return true;
        }
        foreach (array_keys(wp_get_sidebars_widgets()) as $sidebar_id) {
            if ($this->check_read_sidebar_permission($sidebar_id)) {
                return true;
            }
        }
        return $this->permissions_check($request);
    }

    public function get_items($request)
    {
        $this->retrieve_widgets();
        $allowed = !is_wp_error($this->permissions_check($request));
        $prepared = [];
        foreach (wp_get_sidebars_widgets() as $sidebar_id => $widget_ids) {
            if ((isset($request['sidebar']) && $sidebar_id !== $request['sidebar']) || (!$allowed && !$this->check_read_sidebar_permission($sidebar_id))) {
                continue;
            }
            foreach ((array) $widget_ids as $widget_id) {
                $response = $this->prepare_item_for_response(['widget_id' => $widget_id, 'sidebar_id' => $sidebar_id], $request);
                if (!is_wp_error($response)) {
                    $prepared[] = $this->prepare_response_for_collection($response);
                }
            }
        }
        return new WP_REST_Response($prepared);
    }

    public function get_item_permissions_check($request)
    {
        $this->retrieve_widgets();
        $sidebar_id = wp_find_widgets_sidebar($request['id']);
        return $sidebar_id && $this->check_read_sidebar_permission($sidebar_id) ? true : $this->permissions_check($request);
    }

    /** Whether anyone may read a sidebar's widgets: those of a sidebar shown in REST. */
    protected function check_read_sidebar_permission($sidebar_id)
    {
        return !empty(wp_get_sidebar($sidebar_id)['show_in_rest']);
    }

    public function get_item($request)
    {
        $this->retrieve_widgets();
        $sidebar_id = wp_find_widgets_sidebar($request['id']);
        if ($sidebar_id === null) {
            return new WP_Error('rest_widget_not_found', __('No widget was found with that id.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response(['widget_id' => $request['id'], 'sidebar_id' => $sidebar_id], $request);
    }

    public function create_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    /** A widget saved and put in the sidebar named (in none when none is), answered in the edit context with 201. */
    public function create_item($request)
    {
        $sidebar_id = $request['sidebar'];
        $widget_id = $this->save_widget($request, $sidebar_id);
        if (is_wp_error($widget_id)) {
            return $widget_id;
        }
        wp_assign_widget_to_sidebar($widget_id, (string) $sidebar_id);
        $request['context'] = 'edit';
        $response = $this->prepare_item_for_response(['sidebar_id' => $sidebar_id, 'widget_id' => $widget_id], $request);
        if (!is_wp_error($response)) {
            $response->set_status(201);
        }
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    /** Settings saved when given, the widget moved when a sidebar is; a WP_Widget no sidebar holds is no widget. */
    public function update_item($request)
    {
        $this->retrieve_widgets();
        $widget_id = $request['id'];
        $sidebar_id = wp_find_widgets_sidebar($widget_id);
        $widget_object = $GLOBALS['wp_widget_factory']->get_widget_object(wp_parse_widget_id($widget_id)['id_base']);
        if ($sidebar_id === null && $widget_object) {
            return new WP_Error('rest_widget_not_found', __('No widget was found with that id.'), ['status' => 404]);
        }
        if ($request->has_param('instance') || $request->has_param('form_data')) {
            $saved = $this->save_widget($request, $sidebar_id);
            if (is_wp_error($saved)) {
                return $saved;
            }
        }
        if ($request->has_param('sidebar') && $sidebar_id !== $request['sidebar']) {
            $sidebar_id = $request['sidebar'];
            wp_assign_widget_to_sidebar($widget_id, (string) $sidebar_id);
        }
        $request['context'] = 'edit';
        return $this->prepare_item_for_response(['widget_id' => $widget_id, 'sidebar_id' => $sidebar_id], $request);
    }

    public function delete_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    /** With force, the widget's settings removed and the widget out of every sidebar (the answer its previous form); without, sent to the inactive widgets. */
    public function delete_item($request)
    {
        $this->retrieve_widgets();
        $widget_id = $request['id'];
        $sidebar_id = wp_find_widgets_sidebar($widget_id);
        if ($sidebar_id === null) {
            return new WP_Error('rest_widget_not_found', __('No widget was found with that id.'), ['status' => 404]);
        }
        $request['context'] = 'edit';
        if ($request['force']) {
            $response = $this->prepare_item_for_response(['widget_id' => $widget_id, 'sidebar_id' => $sidebar_id], $request);
            $parsed = wp_parse_widget_id($widget_id);
            do_action('delete_widget', $widget_id, $sidebar_id, $parsed['id_base']);
            $widget_object = $GLOBALS['wp_widget_factory']->get_widget_object($parsed['id_base']);
            if ($widget_object && isset($parsed['number'])) {
                $settings = (array) $widget_object->get_settings();
                unset($settings[$parsed['number']]);
                $widget_object->save_settings($settings);
            }
            wp_assign_widget_to_sidebar($widget_id, '');
            $response->set_data(['deleted' => true, 'previous' => $response->get_data()]);
        } else {
            wp_assign_widget_to_sidebar($widget_id, 'wp_inactive_widgets');
            $response = $this->prepare_item_for_response(['sidebar_id' => 'wp_inactive_widgets', 'widget_id' => $widget_id], $request);
        }
        do_action('rest_delete_widget', $widget_id, $sidebar_id, $response, $request);
        return $response;
    }

    /** Whether the caller may edit theme options, the capability every widget answers to. */
    protected function permissions_check($request)
    {
        if (!current_user_can('edit_theme_options')) {
            return new WP_Error('rest_cannot_manage_widgets', __('Sorry, you are not allowed to manage widgets on this site.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
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
     * Saves a widget's settings through its type's own update and registers
     * it: the widget the request names, or a new one of the type it names
     * under the next free number. The widget's id, or why it could not be.
     */
    protected function save_widget($request, $sidebar_id)
    {
        if (isset($request['id'])) {
            $widget_id = $request['id'];
            $parsed = wp_parse_widget_id($widget_id);
            [$id_base, $number, $creating] = [$parsed['id_base'], $parsed['number'] ?? null, false];
        } elseif ($request['id_base']) {
            $id_base = $request['id_base'];
            $number = $GLOBALS['wp_widget_factory']->get_widget_object($id_base) ? next_widget_id_number($id_base) : null;
            [$widget_id, $creating] = [$number === null ? $id_base : $id_base . '-' . $number, true];
        } else {
            return new WP_Error('rest_invalid_widget', __('Widget type (id_base) is required.'), ['status' => 400]);
        }
        $widget_object = $GLOBALS['wp_widget_factory']->get_widget_object($id_base);
        if (!isset($GLOBALS['wp_registered_widget_updates'][$id_base])) {
            return new WP_Error('rest_invalid_widget', __('The provided widget type (id_base) cannot be updated.'), ['status' => 400]);
        }
        $new_instance = $this->minn_new_instance($request, $widget_object, $id_base, $number);
        if (is_wp_error($new_instance)) {
            return $new_instance;
        }
        if ($widget_object && $number !== null) {
            $settings = (array) $widget_object->get_settings();
            $old_instance = isset($settings[$number]) ? (array) $settings[$number] : [];
            $widget_object->_set($number);
            $instance = apply_filters('widget_update_callback', $widget_object->update($new_instance, $old_instance), $new_instance, $old_instance, $widget_object);
            if ($instance !== false) {
                $settings[$number] = $instance;
                $widget_object->save_settings($settings);
            }
            $widget_object->_register_one($number);
            $widget_object->updated = false;
        }
        do_action('rest_after_save_widget', $widget_id, $sidebar_id, $request, $creating);
        return $widget_id;
    }

    /**
     * A widget as REST shows it: its id, base and sidebar, its markup as
     * that sidebar wraps it (none among the inactive widgets), its form and
     * settings when those fields are wanted; links when they are.
     */
    public function prepare_item_for_response($item, $request)
    {
        $widget_id = $item['widget_id'];
        $sidebar_id = $item['sidebar_id'];
        if (!isset($GLOBALS['wp_registered_widgets'][$widget_id])) {
            return new WP_Error('rest_invalid_widget', __('The requested widget is invalid.'), ['status' => 500]);
        }
        $parsed = wp_parse_widget_id($widget_id);
        $fields = $this->get_fields_for_response($request);
        $prepared = ['id' => $widget_id, 'id_base' => $parsed['id_base'], 'sidebar' => $sidebar_id, 'rendered' => '', 'rendered_form' => null, 'instance' => null];
        if (rest_is_field_included('rendered', $fields) && $sidebar_id !== 'wp_inactive_widgets') {
            $prepared['rendered'] = trim((string) wp_render_widget($widget_id, (string) $sidebar_id));
        }
        if (rest_is_field_included('rendered_form', $fields)) {
            $form = wp_render_widget_control($widget_id);
            $prepared['rendered_form'] = is_string($form) ? trim($form) : null;
        }
        $widget_object = $GLOBALS['wp_widget_factory']->get_widget_object($parsed['id_base']);
        if (rest_is_field_included('instance', $fields) && $widget_object && isset($parsed['number'])) {
            $prepared['instance'] = WidgetObject::instance($widget_object, (int) $parsed['number']);
        }
        $response = rest_ensure_response($this->filter_response_by_context($this->add_additional_fields_to_object($prepared, $request), $request['context'] ?? 'view'));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($prepared));
        }
        return apply_filters('rest_prepare_widget', $response, $GLOBALS['wp_registered_widgets'][$widget_id], $request);
    }

    protected function prepare_links($prepared)
    {
        return [
            'self' => ['href' => rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, $prepared['id']))],
            'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))],
            'about' => ['href' => rest_url(sprintf('wp/v2/widget-types/%s', !empty($prepared['id_base']) ? $prepared['id_base'] : $prepared['id'])), 'embeddable' => true],
            'https://api.w.org/sidebar' => ['href' => rest_url(sprintf('wp/v2/sidebars/%s/', $prepared['sidebar']))],
        ];
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view']), 'sidebar' => ['description' => __('The sidebar to return widgets for.'), 'type' => 'string']];
    }

    /** The widget schema, its form data parsed from the form's query string. */
    public function get_item_schema()
    {
        if ($this->schema === null) {
            $schema = SchemaData::of('widget');
            $schema['properties']['form_data']['arg_options'] = ['sanitize_callback' => static function ($form_data) {
                $array = [];
                wp_parse_str($form_data, $array);
                return $array;
            }];
            $this->schema = $schema;
        }
        return $this->add_additional_fields_schema($this->schema);
    }

    /** The settings a request saves: raw (when the type shows them), encoded with a matching hash, or the form's fields for this number. */
    private function minn_new_instance($request, $widget_object, string $id_base, $number)
    {
        if (!isset($request['instance'])) {
            $form = (array) ($request['form_data'] ?? []);
            return (array) ($form["widget-{$id_base}"][$number] ?? []);
        }
        if (!$widget_object) {
            return new WP_Error('rest_invalid_widget', __('Cannot set instance on a widget that does not extend WP_Widget.'), ['status' => 400]);
        }
        $instance = $request['instance'];
        if (isset($instance['raw'])) {
            return empty($widget_object->widget_options['show_instance_in_rest']) ? new WP_Error('rest_invalid_widget', __('Widget type does not support raw instances.'), ['status' => 400]) : (array) $instance['raw'];
        }
        if (!isset($instance['encoded'], $instance['hash'])) {
            return new WP_Error('rest_invalid_widget', __('The provided instance is invalid. Must contain raw OR encoded and hash.'), ['status' => 400]);
        }
        $serialized = base64_decode((string) $instance['encoded']);
        if (!hash_equals(wp_hash($serialized), (string) $instance['hash'])) {
            return new WP_Error('rest_invalid_widget', __('The provided instance is malformed.'), ['status' => 400]);
        }
        return (array) unserialize($serialized, ['allowed_classes' => false]);
    }
}
