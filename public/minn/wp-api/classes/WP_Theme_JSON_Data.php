<?php

/**
 * One layer of theme.json data as the wp_theme_json_data_* filters hand it
 * to plugins and themes: update_with() merges what they bring (maps key by
 * key, lists replaced), get_data() is the result as held, presets under
 * their origin.
 */
#[AllowDynamicProperties]
class WP_Theme_JSON_Data
{
    private $theme_json;
    private $origin;

    public function __construct($data = ['version' => 3], $origin = 'theme')
    {
        $this->origin = $origin;
        $this->theme_json = new WP_Theme_JSON(is_array($data) ? $data : ['version' => 3], $origin);
    }

    public function update_with($new_data)
    {
        $this->theme_json->merge(new WP_Theme_JSON(is_array($new_data) ? $new_data : [], $this->origin));
        return $this;
    }

    /** The data as it is held: presets under their origin, keys in the order given (probe small-classes). */
    public function get_data()
    {
        return $this->theme_json->get_raw_data();
    }

    public function get_theme_json()
    {
        return $this->theme_json;
    }
}
