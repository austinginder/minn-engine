<?php
/** The video widget: an attachment or a YouTube or Vimeo URL in the video shortcode, sized to its column; any other URL through oEmbed. */
class WP_Widget_Media_Video extends WP_Widget_Media
{
    public function __construct()
    {
        parent::__construct('media_video', 'Video', ['description' => 'Displays a video from the media library or from YouTube, Vimeo, or another provider.', 'mime_type' => 'video']);
    }

    public function get_instance_schema()
    {
        $schema = [
            'preload' => ['type' => 'string', 'enum' => ['none', 'auto', 'metadata'], 'default' => 'metadata', 'description' => 'Preload', 'should_preview_update' => false],
            'loop' => ['type' => 'boolean', 'default' => false, 'description' => 'Loop', 'should_preview_update' => false],
            'content' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'wp_kses_post', 'description' => 'Tracks (subtitles, captions, descriptions, chapters, or metadata)', 'should_preview_update' => false],
        ];
        foreach (wp_get_video_extensions() as $extension) {
            $schema[$extension] = ['type' => 'string', 'default' => '', 'format' => 'uri', 'description' => 'URL to the ' . $extension . ' video source file'];
        }
        return array_merge($schema, parent::get_instance_schema());
    }

    public function render_media($instance)
    {
        $instance = array_merge(wp_list_pluck($this->get_instance_schema(), 'default'), $instance);
        $attachment = $instance['attachment_id'] && $this->is_attachment_with_mime_type($instance['attachment_id'], 'video') ? get_post($instance['attachment_id']) : null;
        $src = $attachment ? wp_get_attachment_url($attachment->ID) : $instance['url'];
        if (empty($src)) {
            return;
        }
        $hosted = preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#', $src) || preg_match('#^https?://(.+\.)?vimeo\.com/#', $src);
        if ($attachment || $hosted) {
            add_filter('wp_video_shortcode', [$this, 'inject_video_max_width_style']);
            echo wp_video_shortcode(array_merge($instance, ['src' => $src]), $instance['content']);
            remove_filter('wp_video_shortcode', [$this, 'inject_video_max_width_style']);
            return;
        }
        echo $this->inject_video_max_width_style((string) wp_oembed_get($src));
    }

    public function inject_video_max_width_style($html)
    {
        $html = preg_replace('/\sheight="\d+"/', '', (string) $html);
        $html = preg_replace('/\swidth="\d+"/', '', $html);
        return preg_replace('/(?<=width:)\s*\d+px(?=;?)/', '100%', $html);
    }
}
