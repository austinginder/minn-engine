<?php
/**
 * The customizer API surface, so plugins written for it load; the engine
 * does not serve the customizer (block themes have none), so registering
 * here records and renders nothing.
 */

class WP_Customize_Manager
{
    use \Minn\Runtime\DeadEndCalls;

    private $settings = [];
    private $sections = [];
    private $panels = [];
    private $controls = [];

    public function add_setting($id, $args = [])
    {
        $setting = $id instanceof WP_Customize_Setting ? $id : new WP_Customize_Setting($this, $id, $args);
        $this->settings[$setting->id] = $setting;
        return $setting;
    }

    public function get_setting($id)
    {
        return $this->settings[$id] ?? null;
    }

    public function remove_setting($id)
    {
        unset($this->settings[$id]);
    }

    public function add_section($id, $args = [])
    {
        $section = $id instanceof WP_Customize_Section ? $id : new WP_Customize_Section($this, $id, $args);
        $this->sections[$section->id] = $section;
        return $section;
    }

    public function get_section($id)
    {
        return $this->sections[$id] ?? null;
    }

    public function remove_section($id)
    {
        unset($this->sections[$id]);
    }

    public function add_panel($id, $args = [])
    {
        $panel = $id instanceof WP_Customize_Panel ? $id : new WP_Customize_Panel($this, $id, $args);
        $this->panels[$panel->id] = $panel;
        return $panel;
    }

    public function get_panel($id)
    {
        return $this->panels[$id] ?? null;
    }

    public function remove_panel($id)
    {
        unset($this->panels[$id]);
    }

    public function add_control($id, $args = [])
    {
        $control = $id instanceof WP_Customize_Control ? $id : new WP_Customize_Control($this, $id, $args);
        $this->controls[$control->id] = $control;
        return $control;
    }

    public function get_control($id)
    {
        return $this->controls[$id] ?? null;
    }

    public function remove_control($id)
    {
        unset($this->controls[$id]);
    }

    public function is_preview()
    {
        return false;
    }

    public function selective_refresh()
    {
        return new WP_Customize_Selective_Refresh();
    }

    public function settings()
    {
        return $this->settings;
    }

    public function controls()
    {
        return $this->controls;
    }

    public function sections()
    {
        return $this->sections;
    }

    public function panels()
    {
        return $this->panels;
    }
}

class WP_Customize_Selective_Refresh
{
    use \Minn\Runtime\DeadEndCalls;

    public function add_partial($id, $args = [])
    {
        return null;
    }
}

class WP_Customize_Setting
{
    use \Minn\Runtime\DeadEndCalls;

    public $id;
    public $type = 'theme_mod';
    public $capability = 'edit_theme_options';
    public $theme_supports = '';
    public $default = '';
    public $transport = 'refresh';
    public $sanitize_callback = '';
    public $sanitize_js_callback = '';
    public $validate_callback = '';
    public $dirty = false;

    public function __construct($manager, $id, $args = [])
    {
        $this->id = $id;
        foreach ((array) $args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function value()
    {
        return $this->type === 'option' ? get_option($this->id, $this->default) : get_theme_mod($this->id, $this->default);
    }

    public function check_capabilities()
    {
        return current_user_can($this->capability);
    }
}

class WP_Customize_Section
{
    use \Minn\Runtime\DeadEndCalls;

    public $id;
    public $priority = 160;
    public $panel = '';
    public $capability = 'edit_theme_options';
    public $theme_supports = '';
    public $title = '';
    public $description = '';
    public $type = 'default';
    public $active_callback = '';

    public function __construct($manager, $id, $args = [])
    {
        $this->id = $id;
        foreach ((array) $args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}

class WP_Customize_Panel extends WP_Customize_Section
{
}

class WP_Customize_Control
{
    use \Minn\Runtime\DeadEndCalls;

    public $id;
    public $settings;
    public $setting = 'default';
    public $capability;
    public $priority = 10;
    public $section = '';
    public $label = '';
    public $description = '';
    public $choices = [];
    public $input_attrs = [];
    public $allow_addition = false;
    public $json = [];
    public $type = 'text';
    public $active_callback = '';

    public function __construct($manager, $id, $args = [])
    {
        $this->id = $id;
        foreach ((array) $args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function value($setting_key = 'default')
    {
        return null;
    }

    public function render_content()
    {
    }
}

class WP_Customize_Code_Editor_Control extends WP_Customize_Control
{
    public $type = 'code_editor';
    public $code_type = '';
    public $editor_settings = [];
}

class WP_Customize_Color_Control extends WP_Customize_Control
{
    public $type = 'color';
}

class WP_Customize_Image_Control extends WP_Customize_Control
{
    public $type = 'image';
}

class WP_Customize_Media_Control extends WP_Customize_Control
{
    public $type = 'media';
}

class WP_Customize_Upload_Control extends WP_Customize_Control
{
    public $type = 'upload';
}

class WP_Customize_Cropped_Image_Control extends WP_Customize_Image_Control
{
    public $type = 'cropped_image';
}

// The customizer is Mute: its objects are recorded so a theme's registrations
// do not fatal, and nothing renders. These sit here rather than among the
// generated placeholders because their parent is declared in this file.
class WP_Customize_Themes_Section extends WP_Customize_Section
{
}

class WP_Customize_Sidebar_Section extends WP_Customize_Section
{
}

class WP_Customize_Background_Position_Control extends WP_Customize_Control
{
    public $type = 'background_position';
}

class WP_Customize_Custom_CSS_Setting extends WP_Customize_Setting
{
    public $type = 'custom_css';
    public $transport = 'postMessage';
    public $capability = 'edit_css';
    public $stylesheet = '';
}

class WP_Customize_Filter_Setting extends WP_Customize_Setting
{
}

class WP_Customize_Partial
{
    use \Minn\Runtime\DeadEndCalls;

    public $id = '';
    public $type = 'default';
    public $selector = '';
    public $settings = [];
    public $primary_setting = '';
    public $render_callback = null;
    public $container_inclusive = false;
    public $fallback_refresh = true;

    public function __construct($manager = null, $id = '', $args = [])
    {
        $this->id = (string) $id;
        foreach ((array) $args as $key => $value) {
            $this->$key = $value;
        }
    }

    public function id_data()
    {
        return ['base' => $this->id, 'keys' => []];
    }

    public function json()
    {
        return ['settings' => $this->settings, 'primarySetting' => $this->primary_setting, 'selector' => $this->selector, 'type' => $this->type, 'containerInclusive' => $this->container_inclusive, 'fallbackRefresh' => $this->fallback_refresh];
    }

    public function check_capabilities()
    {
        return false;
    }

    public function render($container_context = [])
    {
        return false;
    }
}
