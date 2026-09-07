<?php
/**
 * What the four media widgets share: an instance shaped by a schema (the
 * update validates and sanitises each field against it and keeps what the
 * old instance had for the rest), a title, and the content check that
 * decides whether the widget prints at all. The settings form, its scripts
 * and templates belong to wp-admin, which the engine does not serve.
 */
abstract class WP_Widget_Media extends WP_Widget
{
    public $l10n = [
        'add_to_widget' => '',
        'replace_media' => '',
        'edit_media' => '',
        'media_library_state_multi' => '',
        'media_library_state_single' => '',
        'missing_attachment' => '',
        'no_media_selected' => '',
        'add_media' => '',
    ];
    protected $registered = false;

    public function __construct($id_base, $name, $widget_options = [], $control_options = [])
    {
        $widget_options = wp_parse_args($widget_options, ['description' => 'A media item.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true, 'mime_type' => '']);
        parent::__construct($id_base, $name, $widget_options, $control_options);
        $this->l10n = array_merge($this->get_l10n_defaults(), array_filter($this->l10n));
    }

    public function get_instance_schema()
    {
        return apply_filters("widget_{$this->id_base}_instance_schema", [
            'attachment_id' => ['type' => 'integer', 'default' => 0, 'minimum' => 0, 'description' => 'Attachment post ID', 'media_prop' => 'id'],
            'url' => ['type' => 'string', 'default' => '', 'format' => 'uri', 'description' => 'URL to the media file'],
            'title' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'description' => 'Title for the widget', 'should_preview_update' => false],
        ], $this);
    }

    public function is_attachment_with_mime_type($attachment, $mime_type)
    {
        if (empty($attachment)) {
            return false;
        }
        $attachment = get_post($attachment);
        return $attachment instanceof WP_Post && $attachment->post_type === 'attachment' && str_starts_with((string) $attachment->post_mime_type, $mime_type . '/');
    }

    public function sanitize_token_list($tokens)
    {
        if (is_string($tokens)) {
            $tokens = preg_split('/\s+/', trim($tokens)) ?: [];
        }
        $tokens = array_filter(array_map('sanitize_html_class', (array) $tokens));
        return implode(' ', array_unique($tokens));
    }

    public function widget($args, $instance)
    {
        $instance = wp_parse_args($instance, wp_list_pluck($this->get_instance_schema(), 'default'));
        if (!$this->has_content($instance)) {
            return;
        }
        echo $args['before_widget'];
        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        $this->render_media($instance);
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        foreach ($this->get_instance_schema() as $field => $schema) {
            if (!array_key_exists($field, $new_instance)) {
                continue;
            }
            $value = $new_instance[$field];
            if (is_wp_error(rest_validate_value_from_schema($value, $schema, $field))) {
                continue;
            }
            $value = rest_sanitize_value_from_schema($value, $schema);
            if (isset($schema['sanitize_callback'])) {
                $value = call_user_func($schema['sanitize_callback'], $value);
            }
            if (is_wp_error($value)) {
                continue;
            }
            $instance[$field] = $value;
        }
        return $instance;
    }

    abstract public function render_media($instance);

    public function form($instance)
    {
    }

    public function display_media_state($states, $post = null)
    {
        return $states;
    }

    public function enqueue_preview_scripts()
    {
    }

    public function enqueue_admin_scripts()
    {
    }

    public function render_control_template_scripts()
    {
    }

    public function reset_default_labels()
    {
        $this->l10n = array_merge($this->l10n, $this->get_l10n_defaults());
    }

    public function has_content($instance)
    {
        return !empty($instance['attachment_id']) || !empty($instance['url']);
    }

    public function get_default_description()
    {
        return 'A media item.';
    }

    public function get_l10n_defaults()
    {
        return [
            'no_media_selected' => 'No media selected',
            'add_media' => 'Add Media',
            'replace_media' => 'Replace Media',
            'edit_media' => 'Edit Media',
            'add_to_widget' => 'Add to Widget',
            'missing_attachment' => 'That file cannot be found. Check your media library and make sure it was not deleted.',
            'media_library_state_multi' => 'Media Widget (%d)',
            'media_library_state_single' => 'Media Widget',
            'unsupported_file_type' => 'Looks like this is not the correct kind of file. Please link to an appropriate file instead.',
        ];
    }
}
