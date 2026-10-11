<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/widget-types as a class (probe rest-sidebar-classes): the widgets
 * registered so far (a widget registered after widgets_init has no
 * instance registered yet, so it is not among them), one per id base in
 * id order, for someone who can edit theme options. encode_form_data takes
 * settings sent (encoded, with their hash) and form data as the route
 * hands it on (an array), and answers the form, a preview and the settings
 * encoded again; render answers a whole preview page. The engine answers
 * the route itself; plugins call this class directly.
 */
class WP_REST_Widget_Types_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'widget-types';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        $id = ['id' => ['description' => __('The widget type id.'), 'type' => 'string']];
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)', [
            'args' => $id,
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        $settings = ['instance' => ['description' => __('Current instance settings of the widget.'), 'type' => 'object'], 'form_data' => ['description' => __('Serialized widget form data to encode into instance settings.'), 'type' => 'string', 'sanitize_callback' => static function ($form_data) {
            $array = [];
            wp_parse_str($form_data, $array);
            return $array;
        }]];
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/encode', [
            'args' => $id + $settings + ['number' => ['description' => __('Number of the widget instance.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::CREATABLE, 'permission_callback' => [$this, 'get_item_permissions_check'], 'callback' => [$this, 'encode_form_data']],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/render', [
            ['methods' => WP_REST_Server::CREATABLE, 'permission_callback' => [$this, 'get_item_permissions_check'], 'callback' => [$this, 'render'], 'args' => $id + ['instance' => $settings['instance']]],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return $this->check_read_permission();
    }

    public function get_items($request)
    {
        $data = [];
        foreach ($this->get_widgets() as $widget) {
            $data[] = $this->prepare_response_for_collection($this->prepare_item_for_response($widget, $request));
        }
        return rest_ensure_response($data);
    }

    /** The theme-options check, then the widget type must exist. */
    public function get_item_permissions_check($request)
    {
        $check = $this->check_read_permission();
        if (is_wp_error($check)) {
            return $check;
        }
        $widget = $this->get_widget($request['id']);
        return is_wp_error($widget) ? $widget : true;
    }

    protected function check_read_permission()
    {
        if (!current_user_can('edit_theme_options')) {
            return new WP_Error('rest_cannot_manage_widgets', __('Sorry, you are not allowed to manage widgets on this site.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** One registered widget type by id base, or rest_widget_type_invalid. */
    public function get_widget($id)
    {
        return $this->get_widgets()[$id] ?? new WP_Error('rest_widget_type_invalid', __('Invalid widget type.'), ['status' => 404]);
    }

    /**
     * The registered widgets as types, one per id base (the last instance
     * registered speaks for it), by id: their registration without the
     * callback, names and descriptions decoded, a single classname, and
     * whether a WP_Widget class stands behind them.
     */
    protected function get_widgets()
    {
        global $wp_widget_factory, $wp_registered_widgets;
        $widgets = [];
        foreach ((array) $wp_registered_widgets as $widget) {
            $base = wp_parse_widget_id($widget['id'])['id_base'];
            $widget['id'] = $base;
            $widget['is_multi'] = (bool) $wp_widget_factory->get_widget_object($base);
            foreach (['name', 'description'] as $key) {
                if (isset($widget[$key])) {
                    $widget[$key] = html_entity_decode($widget[$key], ENT_QUOTES, get_bloginfo('charset'));
                }
            }
            unset($widget['callback']);
            $classname = '';
            foreach ((array) ($widget['classname'] ?? []) as $name) {
                $classname .= is_string($name) ? '_' . $name : (is_object($name) ? '_' . get_class($name) : '');
            }
            $widget['classname'] = ltrim($classname, '_');
            $widgets[$base] = $widget;
        }
        ksort($widgets);
        return $widgets;
    }

    public function get_item($request)
    {
        $widget = $this->get_widget($request['id']);
        return is_wp_error($widget) ? $widget : rest_ensure_response($this->prepare_item_for_response($widget, $request));
    }

    /** A widget type as REST shows it: its id and each field asked for, from the type or the schema's default. */
    public function prepare_item_for_response($item, $request)
    {
        $fields = $this->get_fields_for_response($request);
        $schema = $this->get_item_schema()['properties'];
        $data = ['id' => $item['id']];
        foreach (array_diff(array_keys($schema), ['id']) as $field) {
            if (rest_is_field_included($field, $fields)) {
                $data[$field] = rest_sanitize_value_from_schema($item[$field] ?? (array_key_exists('default', $schema[$field]) ? $schema[$field]['default'] : ''), $schema[$field]);
            }
        }
        $response = rest_ensure_response($this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view'));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($item));
        }
        return apply_filters('rest_prepare_widget_type', $response, $item, $request);
    }

    protected function prepare_links($widget_type)
    {
        return [
            'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))],
            'self' => ['href' => rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, $widget_type['id']))],
        ];
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('widget-type');
        return $this->add_additional_fields_schema($this->schema);
    }

    /** The form, a preview and the encoded settings for settings sent and form data given, for a widget type with a WP_Widget class. */
    public function encode_form_data($request)
    {
        $widget = $GLOBALS['wp_widget_factory']->get_widget_object($request['id']);
        if (!$widget) {
            return new WP_Error('rest_invalid_widget', __('Cannot preview a widget that does not extend WP_Widget.'), ['status' => 400]);
        }
        $widget->_set(isset($request['number']) ? (int) $request['number'] : -1);
        $instance = $this->minn_instance($request);
        if (is_wp_error($instance)) {
            return $instance;
        }
        $form_data = $request['form_data'];
        if (isset($form_data["widget-{$widget->id_base}"]) && is_array($form_data["widget-{$widget->id_base}"])) {
            $new_instance = array_values($form_data["widget-{$widget->id_base}"])[0];
            $old_instance = $instance;
            $instance = $widget->update($new_instance, $old_instance);
            $instance = apply_filters('widget_update_callback', $instance, $new_instance, $old_instance, $widget);
        }
        $serialized = serialize($instance);
        $encoded = ['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)] + (empty($widget->widget_options['show_instance_in_rest']) ? [] : ['raw' => empty($instance) ? new stdClass() : $instance]);
        ob_start();
        $widget->form($instance);
        $form = ob_get_clean();
        ob_start();
        the_widget(get_class($widget), $instance);
        $preview = ob_get_clean();
        return rest_ensure_response(['form' => trim($form), 'preview' => trim($preview), 'instance' => $encoded]);
    }

    /**
     * A whole preview page with the widget in it, as the editor shows a
     * legacy widget: the reference's page skeleton, white space and all,
     * with the head's styles reset after wp_head() (probe rest-block-patterns).
     */
    public function render($request)
    {
        $instance = $this->minn_instance($request);
        ob_start();
        echo "\t\t<!doctype html>\n\t\t<html ", get_language_attributes(), ">\n\t\t<head>\n\t\t\t<meta charset=\"", esc_attr(get_bloginfo('charset')), "\" />\n\t\t\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n\t\t\t<link rel=\"profile\" href=\"https://gmpg.org/xfn/11\" />\n\t\t\t";
        wp_head();
        echo "\t\t\t<style>\n\t\t\t\t/* Reset theme styles */\n\t\t\t\thtml, body, #page, #content {\n\t\t\t\t\tpadding: 0 !important;\n\t\t\t\t\tmargin: 0 !important;\n\t\t\t\t}\n\t\t\t</style>\n\t\t</head>\n\t\t<body ", 'class="' . esc_attr(implode(' ', get_body_class())) . '"', ">\n\t\t<div id=\"page\" class=\"site\">\n\t\t\t<div id=\"content\" class=\"site-content\">\n\t\t\t\t";
        $widget = $GLOBALS['wp_widget_factory']->get_widget_object($request['id']);
        if ($widget && !is_wp_error($instance)) {
            the_widget(get_class($widget), $instance);
        }
        echo "\t\t\t</div><!-- #content -->\n\t\t</div><!-- #page -->\n\t\t";
        wp_footer();
        echo "\t\t</body>\n\t\t</html>\n\t\t";
        return ['preview' => ob_get_clean()];
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }

    /** The settings a request sends, decoded when their hash holds; none when none were sent. */
    private function minn_instance($request)
    {
        $sent = $request['instance'];
        if (!isset($sent['encoded'], $sent['hash'])) {
            return [];
        }
        $serialized = base64_decode((string) $sent['encoded']);
        if (!hash_equals(wp_hash($serialized), (string) $sent['hash'])) {
            return new WP_Error('rest_invalid_widget', __('The provided instance is malformed.'), ['status' => 400]);
        }
        return (array) unserialize($serialized, ['allowed_classes' => false]);
    }
}
