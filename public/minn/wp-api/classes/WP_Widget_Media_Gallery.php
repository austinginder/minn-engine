<?php
/** The gallery widget: chosen attachments through the gallery shortcode. */
class WP_Widget_Media_Gallery extends WP_Widget_Media
{
    public function __construct()
    {
        parent::__construct('media_gallery', 'Gallery', ['description' => 'Displays an image gallery.', 'mime_type' => 'image']);
    }

    public function get_instance_schema()
    {
        return apply_filters("widget_{$this->id_base}_instance_schema", [
            'title' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'description' => 'Title for the widget', 'should_preview_update' => false],
            'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'sanitize_callback' => 'wp_parse_id_list'],
            'columns' => ['type' => 'integer', 'default' => 3, 'minimum' => 1, 'maximum' => 9],
            'size' => ['type' => 'string', 'enum' => array_merge(get_intermediate_image_sizes(), ['full', 'custom']), 'default' => 'thumbnail'],
            'link_type' => ['type' => 'string', 'enum' => ['post', 'file', 'none'], 'default' => 'post', 'media_prop' => 'link', 'should_preview_update' => false],
            'orderby_random' => ['type' => 'boolean', 'default' => false, 'media_prop' => '_orderbyRandom', 'should_preview_update' => false],
        ], $this);
    }

    public function render_media($instance)
    {
        $instance = array_merge(wp_list_pluck($this->get_instance_schema(), 'default'), $instance);
        $atts = array_merge($instance, ['link' => $instance['link_type']]);
        if ($instance['orderby_random']) {
            $atts['orderby'] = 'rand';
        }
        echo gallery_shortcode($atts);
    }

    public function has_content($instance)
    {
        if (!parent::has_content($instance)) {
            return !empty($instance['ids']);
        }
        return true;
    }
}
