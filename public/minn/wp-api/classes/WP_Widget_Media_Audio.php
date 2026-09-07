<?php
/** The audio widget: an attachment or a URL in the audio player shortcode. */
class WP_Widget_Media_Audio extends WP_Widget_Media
{
    public function __construct()
    {
        parent::__construct('media_audio', 'Audio', ['description' => 'Displays an audio player.', 'mime_type' => 'audio']);
    }

    public function get_instance_schema()
    {
        $schema = [
            'preload' => ['type' => 'string', 'enum' => ['none', 'auto', 'metadata'], 'default' => 'none', 'description' => 'Preload'],
            'loop' => ['type' => 'boolean', 'default' => false, 'description' => 'Loop'],
        ];
        foreach (wp_get_audio_extensions() as $extension) {
            $schema[$extension] = ['type' => 'string', 'default' => '', 'format' => 'uri', 'description' => 'URL to the ' . $extension . ' audio source file'];
        }
        return array_merge($schema, parent::get_instance_schema());
    }

    public function render_media($instance)
    {
        $instance = array_merge(wp_list_pluck($this->get_instance_schema(), 'default'), $instance);
        $attachment = $instance['attachment_id'] && $this->is_attachment_with_mime_type($instance['attachment_id'], 'audio') ? get_post($instance['attachment_id']) : null;
        $src = $attachment ? wp_get_attachment_url($attachment->ID) : $instance['url'];
        if (empty($src)) {
            return;
        }
        echo wp_audio_shortcode(array_merge($instance, ['src' => $src]));
    }
}
