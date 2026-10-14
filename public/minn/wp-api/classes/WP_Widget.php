<?php

/** The widget base and registry. Shapes from contracts/fixtures/api/media.json. */
#[AllowDynamicProperties]
class WP_Widget implements \Minn\Objects\Widget
{
    public $id_base;
    public $name;
    public $option_name;
    public $alt_option_name;
    public $widget_options;
    public $control_options;
    public $number = false;
    public $id = false;
    public $updated = false;

    /** A widget class must write its own: the reference ends the request, naming the method. */
    public function widget($args, $instance)
    {
        die('function WP_Widget::widget() must be overridden in a subclass.');
    }

    public function update($new_instance, $old_instance)
    {
        return $new_instance;
    }

    public function form($instance)
    {
        echo '<p class="no-options-widget">There are no options for this widget.</p>';
        return 'noform';
    }

    public function __construct($id_base, $name, $widget_options = [], $control_options = [])
    {
        if (!$id_base) {
            $id_base = preg_replace('/(wp_)?widget_/', '', strtolower(get_class($this)));
        }
        $this->id_base = $id_base;
        $this->name = $name;
        $this->option_name = 'widget_' . $this->id_base;
        $this->widget_options = wp_parse_args($widget_options, ['classname' => str_replace('\\', '_', $this->option_name), 'customize_selective_refresh' => false]);
        $this->control_options = wp_parse_args($control_options, ['id_base' => $this->id_base]);
    }

    /** The PHP 4 constructor: WP_Widget's own, reported deprecated since 4.3.0. */
    public function WP_Widget($id_base, $name, $widget_options = [], $control_options = [])
    {
        _deprecated_constructor('WP_Widget', '4.3.0', get_class($this));
        self::__construct($id_base, $name, $widget_options, $control_options);
    }

    public function get_field_name($field_name)
    {
        $pos = strpos($field_name, '[');
        if ($pos === false) {
            $name = 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
        } else {
            $name = 'widget-' . $this->id_base . '[' . $this->number . '][' . substr($field_name, 0, $pos) . ']' . substr($field_name, $pos);
        }
        // Neither the name nor the id runs a filter (probe widget-methods).
        return $name;
    }

    public function get_field_id($field_name)
    {
        $field = str_replace(['[]', '[', ']'], ['', '-', ''], $field_name);
        return 'widget-' . $this->id_base . '-' . $this->number . '-' . trim($field, '-');
    }

    public function _register()
    {
        $settings = $this->get_settings();
        $empty = true;
        if (is_array($settings)) {
            foreach (array_keys($settings) as $number) {
                if (is_numeric($number)) {
                    $this->_set($number);
                    $this->_register_one($number);
                    $empty = false;
                }
            }
        }
        if ($empty) {
            $this->_set(1);
            $this->_register_one();
        }
    }

    public function _set($number)
    {
        $this->number = $number;
        $this->id = $this->id_base . '-' . $number;
    }

    public function _get_display_callback()
    {
        return [$this, 'display_callback'];
    }

    public function _get_update_callback()
    {
        return [$this, 'update_callback'];
    }

    public function _get_form_callback()
    {
        return [$this, 'form_callback'];
    }

    public function is_preview()
    {
        return false;
    }

    public function display_callback($args, $widget_args = 1)
    {
        if (is_numeric($widget_args)) {
            $widget_args = ['number' => $widget_args];
        }
        $widget_args = wp_parse_args($widget_args, ['number' => -1]);
        $this->_set($widget_args['number']);
        $instances = $this->get_settings();
        if (isset($instances[$this->number])) {
            $instance = apply_filters('widget_display_callback', $instances[$this->number], $this, $args);
            if ($instance !== false) {
                $this->widget($args, $instance);
            }
        }
    }

    /**
     * The posted form saved, once per request: the first instance posted
     * for this type goes through update() with the old one, then
     * widget_update_callback (false keeps the old), and the settings are
     * saved (probe widget-classes).
     */
    public function update_callback($deprecated = 1)
    {
        $posted = $this->updated ? null : $this->posted_settings();
        if ($posted === null) {
            return;
        }
        $all = $this->get_settings();
        foreach ($posted as $number => $new_instance) {
            $new_instance = stripslashes_deep($new_instance);
            $this->_set($number);
            $instance = apply_filters('widget_update_callback', $this->update($new_instance, $all[$number] ?? []), $new_instance, $all[$number] ?? [], $this);
            if ($instance !== false) {
                $all[$number] = $instance;
            }
            break;
        }
        $this->save_settings($all);
        $this->updated = true;
    }

    /** The instances posted for this type (a new one by its multi_number or widget_number when only the type is named); null when none are. */
    private function posted_settings(): ?array
    {
        $posted = $_POST['widget-' . $this->id_base] ?? null;
        if (!empty($posted) && is_array($posted)) {
            return $posted;
        }
        if (($_POST['id_base'] ?? null) === $this->id_base) {
            return [(int) (!empty($_POST['multi_number']) ? $_POST['multi_number'] : ($_POST['widget_number'] ?? 0)) => []];
        }
        return null;
    }

    public function form_callback($widget_args = 1)
    {
        if (is_numeric($widget_args)) {
            $widget_args = ['number' => $widget_args];
        }
        $widget_args = wp_parse_args($widget_args, ['number' => -1]);
        // Number -1 is the template form a new widget copies: drawn as __i__ with no settings.
        $template = (int) $widget_args['number'] === -1;
        $this->_set($template ? '__i__' : $widget_args['number']);
        $instances = $this->get_settings();
        $instance = $template ? [] : ($instances[$this->number] ?? []);
        $instance = apply_filters('widget_form_callback', $instance, $this);
        $return = null;
        if ($instance !== false) {
            $return = $this->form($instance);
            do_action_ref_array('in_widget_form', [&$this, &$return, $instance]);
        }
        return $return;
    }

    /** One instance registered: its display callback, the type's update callback, and the instance's form. */
    public function _register_one($number = -1)
    {
        wp_register_sidebar_widget($this->id, $this->name, $this->_get_display_callback(), $this->widget_options, ['number' => $number]);
        _register_widget_update_callback($this->id_base, $this->_get_update_callback(), $this->control_options, ['multi_number' => -1]);
        _register_widget_form_callback($this->id, $this->name, $this->_get_form_callback(), $this->control_options, ['number' => $number]);
    }

    public function save_settings($settings)
    {
        $settings['_multiwidget'] = 1;
        update_option($this->option_name, $settings);
    }

    public function get_settings()
    {
        $settings = get_option($this->option_name);
        if ($settings === false && isset($this->alt_option_name)) {
            $settings = get_option($this->alt_option_name);
        }
        if (!is_array($settings) && !($settings instanceof ArrayAccess)) {
            $settings = [];
        }
        if (!empty($settings) && !isset($settings['_multiwidget'])) {
            $settings = wp_convert_widget_settings($this->id_base, $this->option_name, $settings);
        }
        unset($settings['_multiwidget'], $settings['__i__']);
        return $settings;
    }
}

#[AllowDynamicProperties]
final class WP_Widget_Factory
{
    const INSTANCE_KEY_PREFIX = 'spl_object_id:';

    public $widgets = [];

    public function __construct()
    {
        add_action('widgets_init', [$this, '_register_widgets'], 100);
    }

    /** The PHP 4 constructor, reported deprecated since 4.3.0. */
    public function WP_Widget_Factory()
    {
        _deprecated_constructor('WP_Widget_Factory', '4.3.0');
        self::__construct();
    }

    public function register($widget)
    {
        // An instance is keyed by its object id behind INSTANCE_KEY_PREFIX, as the reference keys it since 7.1.3 (probe widget-factory).
        if ($widget instanceof WP_Widget) {
            $this->widgets[self::INSTANCE_KEY_PREFIX . spl_object_id($widget)] = $widget;
        } else {
            $this->widgets[$widget] = new $widget();
        }
    }

    public function unregister($widget)
    {
        if ($widget instanceof WP_Widget) {
            unset($this->widgets[self::INSTANCE_KEY_PREFIX . spl_object_id($widget)]);
        } else {
            unset($this->widgets[$widget]);
        }
    }

    public function _register_widgets()
    {
        foreach (array_keys($this->widgets) as $key) {
            $this->widgets[$key]->_register();
        }
    }

    public function get_widget_object($id_base)
    {
        $key = $this->get_widget_key($id_base);
        return $key === '' ? null : $this->widgets[$key];
    }

    public function get_widget_key($id_base)
    {
        foreach ($this->widgets as $key => $widget) {
            if ($widget->id_base === $id_base) {
                return $key;
            }
        }
        return '';
    }
}
